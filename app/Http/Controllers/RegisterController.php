<?php

namespace App\Http\Controllers;

use App\Models\Customer;                                        // Model pelanggan
use App\Models\PhoneVerification;                               // Model kode verifikasi (OTP)
use App\Services\VerificationHub;                               // Pengirim kode lewat POSAJA Verification Hub
use App\Support\Phone;                                          // Helper format nomor HP
use App\Support\Settings;                                       // Pengaturan usaha & pengiriman kode verifikasi
use Illuminate\Http\Request;                                    // Penangan request HTTP
use Illuminate\Support\Facades\Hash;                            // Hash kode OTP
use Illuminate\Support\Facades\URL;                             // Membuat link bertanda tangan (signed URL)

/**
 * Daftar & Masuk pelanggan dengan nomor WhatsApp.
 * Keduanya memakai kode verifikasi yang dikirim ke WA lewat POSAJA Verification Hub
 * (pengaturannya dapat diubah oleh Owner di menu Pengaturan Usaha).
 */
class RegisterController extends Controller
{
    private const RESEND_SECONDS = 60;                          // Nilai bawaan jeda kirim ulang (detik)
    private const MAX_PER_HOUR   = 5;                           // Nilai bawaan maksimal permintaan kode per nomor per jam

    public function __construct(
        private VerificationHub $hub,
        private Settings $settings,
    ) {
    }

    // 1. Halaman form pendaftaran (nama + nomor WA)
    public function create(Request $request)
    {
        $this->rememberNext($request);
        return view('register.index');
    }

    // 2. Proses klik "Daftar": cek nomor belum terdaftar, lalu kirim kode verifikasi
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
        ], [], ['name' => 'nama', 'phone' => 'nomor WhatsApp']);

        $phone = Phone::normalize($data['phone']);              // Samakan format jadi 628xxx
        if (! $phone) {
            return back()->withInput()->withErrors(['phone' => 'Nomor WhatsApp tidak valid. Contoh: 081234567890']);
        }

        // Nomor yang sudah pernah terverifikasi tidak perlu daftar lagi → arahkan ke Masuk
        $customer = $this->findCustomer($phone);
        if ($customer && $customer->phone_verified_at) {
            $request->session()->put('prefill_phone', Phone::toLocal($phone)); // Nomor langsung terisi di tab Masuk
            return back()->withInput()->with('registered', true)
                ->withErrors(['phone' => 'Nomor ini sudah terdaftar. Silakan masuk.']);
        }

        return $this->sendCode($request, $phone, trim($data['name']), 'register', 'phone');
    }

    // 3. Halaman form masuk (nomor WA saja)
    public function loginForm(Request $request)
    {
        $this->rememberNext($request);
        if ($request->session()->has('customer_id')) {
            // Sudah masuk: langsung ke tujuan (cth: form reservasi) atau halaman berhasil
            return redirect()->to($request->session()->pull('after_login', route('register.done')));
        }
        return view('register.login');
    }

    // 4. Proses klik "Masuk": cek nomor sudah terdaftar, lalu kirim kode verifikasi
    public function login(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
        ], [], ['phone' => 'nomor WhatsApp']);

        $phone = Phone::normalize($data['phone']);
        if (! $phone) {
            return back()->withInput()->withErrors(['phone' => 'Nomor WhatsApp tidak valid. Contoh: 081234567890']);
        }

        // Nomor belum ada di data pelanggan → arahkan ke Daftar
        $customer = $this->findCustomer($phone);
        if (! $customer) {
            $request->session()->put('prefill_phone', Phone::toLocal($phone)); // Nomor langsung terisi di tab Daftar
            return back()->withInput()->with('unregistered', true)
                ->withErrors(['phone' => 'Nomor ini belum terdaftar. Silakan daftar dulu.']);
        }

        return $this->sendCode($request, $phone, $customer->name, 'login', 'phone');
    }

    // 5. Halaman memasukkan kode OTP (dipakai untuk daftar & masuk)
    public function verifyForm(Request $request)
    {
        $verification = $this->currentVerification($request);
        if (! $verification) {
            return redirect()->route('register.create');        // Belum minta kode → kembali ke form
        }

        $cfg = $this->settings->verificationConfig();
        $resendSeconds = (int) $request->session()->get('register.resend_seconds', $cfg['resend_seconds']);

        return view('register.verify', [
            'verification' => $verification,
            'phoneLocal'   => Phone::toLocal($verification->phone),
            'mode'         => $request->session()->get('register.mode', 'register'),
            'resendIn'     => max(0, $resendSeconds - (int) $verification->created_at->diffInSeconds(now())),
        ]);
    }

    // 6. Proses pengecekan kode OTP yang dimasukkan pelanggan
    public function verify(Request $request)
    {
        $request->validate(['code' => ['required', 'digits:6']], [], ['code' => 'kode verifikasi']);

        $verification = $this->currentVerification($request);
        if (! $verification) {
            return redirect()->route('register.create');
        }

        $maxAttempts = (int) $this->settings->verificationConfig()['max_attempts'];

        if (! $verification->isUsable($maxAttempts)) {
            return back()->withErrors(['code' => 'Kode sudah tidak berlaku. Silakan klik "Kirim ulang kode".']);
        }

        if (! Hash::check($request->code, $verification->code_hash)) {
            $verification->increment('attempts');               // Catat percobaan salah
            $left = $maxAttempts - $verification->attempts;
            return back()->withErrors(['code' => $left > 0
                ? "Kode salah. Sisa percobaan: {$left} kali."
                : 'Kode salah terlalu sering. Silakan klik "Kirim ulang kode".']);
        }

        // Kode benar: tandai terverifikasi, lalu simpan/perbarui data pelanggan
        $verification->update(['verified_at' => now()]);

        $customer = $this->findCustomer($verification->phone);  // Pelanggan lama (daftar sebelumnya / dicatat kasir)?
        if ($customer) {
            $customer->update(['phone_verified_at' => $customer->phone_verified_at ?? now()]); // Nama lama tidak ditimpa
        } else {
            $customer = Customer::create([
                'name'              => $verification->name,
                'phone'             => Phone::toLocal($verification->phone), // Disimpan format 08xx seperti input kasir
                'phone_verified_at' => now(),
            ]);
        }

        $mode = $request->session()->get('register.mode', 'register');
        $afterLogin = $request->session()->pull('after_login'); // Tujuan setelah masuk (cth: form reservasi), jika ada
        $request->session()->forget(['register.verification_id', 'register.mode']);
        $request->session()->regenerate();                      // Ganti ID sesi setelah verifikasi (keamanan)
        $request->session()->put('customer_id', $customer->id); // Pelanggan sekarang sudah masuk

        if ($afterLogin) {
            return redirect()->to($afterLogin);                 // Kembali ke form reservasi
        }
        return redirect()->route('register.done')->with('mode', $mode);
    }

    // 7. Kirim ulang kode ke nomor yang sama (untuk daftar maupun masuk)
    public function resend(Request $request)
    {
        $old = $this->currentVerification($request);
        if (! $old) {
            return redirect()->route('register.create');
        }

        $mode = $request->session()->get('register.mode', 'register');
        $response = $this->sendCode($request, $old->phone, $old->name, $mode, 'code');

        return $response->getTargetUrl() === route('register.verify')
            ? $response->with('info', 'Kode baru sudah dikirim ke WhatsApp Anda.')
            : $response;
    }

    // 8. Halaman berhasil daftar / masuk
    public function done(Request $request)
    {
        $customer = Customer::find($request->session()->get('customer_id'));
        if (! $customer) {
            return redirect()->route('register.create');
        }

        return view('register.done', [
            'customer' => $customer,
            'mode'     => session('mode', 'register'),
        ]);
    }

    // 9. Keluar: hapus status masuk pelanggan
    public function logout(Request $request)
    {
        $request->session()->forget('customer_id');
        $request->session()->regenerateToken();                 // Token CSRF baru setelah keluar

        return redirect()->route('customer.home');
    }

    /**
     * 10. Link verifikasi otomatis dari pesan WA (/daftar/v/{id}?kode=...&signature=...).
     * Membuka halaman kode dengan isian sudah terisi lalu dikirim otomatis.
     * Bisa dibuka di browser mana pun (termasuk browser bawaan WhatsApp), karena tanda tangan link membuktikan
     * link ini benar-benar dari pesan WA kita.
     */
    public function magic(Request $request, PhoneVerification $verification)
    {
        // Tanda tangan salah/kedaluwarsa, atau kode sudah dipakai/hangus → minta kode baru
        if (! $request->hasValidSignature() || ! $verification->isUsable()) {
            $route = $request->query('mode') === 'login' ? 'login.form' : 'register.create';
            return redirect()->route($route)->withErrors(['phone' => 'Link verifikasi sudah tidak berlaku. Silakan minta kode baru.']);
        }

        // Jadikan verifikasi ini milik pengunjung ini, lalu isi kode secara otomatis di halaman kode
        $request->session()->put('register.verification_id', $verification->id);
        $request->session()->put('register.mode', $request->query('mode') === 'login' ? 'login' : 'register');

        return redirect()->route('register.verify')->with('autofill_code', (string) $request->query('kode'));
    }

    // ---------- Fungsi bantu ----------

    /**
     * ?next=reservasi → setelah berhasil masuk/daftar, pelanggan dikembalikan ke form reservasi.
     * Hanya tujuan dari daftar ini yang diterima (bukan alamat bebas), supaya tidak bisa dipakai mengalihkan ke situs lain.
     */
    private function rememberNext(Request $request): void
    {
        $targets = ['reservasi' => route('customer.home') . '#pesan'];
        if (isset($targets[$request->query('next')])) {
            $request->session()->put('after_login', $targets[$request->query('next')]);
        }
    }

    /**
     * Cek batas kirim, kirim kode lewat Hub, simpan ke sesi, lalu arahkan ke halaman verifikasi.
     * $errorField = nama input tempat pesan error ditampilkan ("phone" di form, "code" di halaman verifikasi).
     */
    private function sendCode(Request $request, string $phone, string $name, string $mode, string $errorField)
    {
        $cfg = $this->settings->verificationConfig();

        // Jika owner menonaktifkan pengiriman kode verifikasi sementara, langsung selesaikan daftar/masuk
        if (! $cfg['enabled']) {
            $customer = $this->findCustomer($phone);
            if ($customer) {
                $customer->update(['phone_verified_at' => $customer->phone_verified_at ?? now()]);
            } else {
                $customer = Customer::create([
                    'name'              => $name,
                    'phone'             => Phone::toLocal($phone),
                    'phone_verified_at' => now(),
                ]);
            }
            $afterLogin = $request->session()->pull('after_login');
            $request->session()->forget(['register.verification_id', 'register.mode']);
            $request->session()->regenerate();
            $request->session()->put('customer_id', $customer->id);

            return $afterLogin
                ? redirect()->to($afterLogin)
                : redirect()->route('register.done')->with('mode', $mode);
        }

        if ($error = $this->rateLimitError($phone)) {           // Cegah kirim kode berulang-ulang ke nomor yang sama
            return back()->withInput()->withErrors([$errorField => $error]);
        }

        $result = $this->issueCode($phone, $name, $mode);
        if (is_string($result)) {                               // String = pesan error dari Hub
            return back()->withInput()->withErrors([$errorField => $result . ' Silakan coba lagi.']);
        }

        $request->session()->put('register.verification_id', $result->id); // Ingat verifikasi milik pengunjung ini
        $request->session()->put('register.mode', $mode);       // "register" atau "login" (untuk teks halaman)

        return redirect()->route('register.verify');
    }

    /**
     * Buat kode 6 digit, kirim lewat Verification Hub, lalu simpan hash-nya.
     * Mengembalikan PhoneVerification jika berhasil, atau string pesan error jika Hub gagal.
     */
    private function issueCode(string $phone, string $name, string $mode): PhoneVerification|string
    {
        $cfg     = $this->settings->verificationConfig();
        $code    = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT); // Angka acak aman, cth: 048213
        $minutes = (int) $cfg['otp_minutes'];
        $purpose = $mode === 'login' ? $cfg['purpose_login'] : $cfg['purpose_register'];
        $expires = now()->addMinutes($minutes);

        // Kode lama yang belum dipakai untuk nomor ini dinonaktifkan, hanya kode terbaru yang berlaku
        PhoneVerification::where('phone', $phone)->whereNull('verified_at')
            ->where('expires_at', '>', now())->update(['expires_at' => now()]);

        // Simpan dulu (hanya hash kodenya) supaya ID-nya bisa dipakai di link verifikasi otomatis
        $verification = PhoneVerification::create([
            'phone'      => $phone,
            'name'       => $name,
            'code_hash'  => Hash::make($code),
            'expires_at' => $expires,
        ]);

        // Alamat tujuan link verifikasi otomatis: ditandatangani Laravel (tidak bisa diubah) & kedaluwarsa bersama kodenya.
        // Dikirim ke Hub sebagai "callback_url"; Hub yang membuat link di pesan WA dan mengarahkannya ke alamat ini.
        $callbackUrl = URL::temporarySignedRoute('register.magic', $expires, [
            'verification' => $verification->id,
            'kode'         => $code,
            'mode'         => $mode,
        ]);

        $sent = $this->hub->send(Phone::toLocal($phone), $code, $purpose, $callbackUrl);
        if (! $sent['ok']) {
            $verification->delete();                            // Gagal terkirim → kode tidak disimpan
            return $sent['error'];
        }

        $cooldownSeconds = (int) ($sent['cooldown_seconds'] ?? $cfg['resend_seconds']);
        session(['register.resend_seconds' => $cooldownSeconds > 0 ? $cooldownSeconds : $cfg['resend_seconds']]);

        $updateData = ['hub_request_id' => $sent['request_id']];
        if (! empty($sent['otp_expiry_minutes']) && (int) $sent['otp_expiry_minutes'] > 0) {
            $updateData['expires_at'] = now()->addMinutes((int) $sent['otp_expiry_minutes']);
        }
        $verification->update($updateData);
        return $verification;
    }

    // Cari pelanggan berdasarkan nomor, apa pun format penyimpanannya (08xx / 628xx / +628xx)
    private function findCustomer(string $phone): ?Customer
    {
        return Customer::whereIn('phone', Phone::variants($phone))->first();
    }

    // Kembalikan pesan error jika nomor ini terlalu sering meminta kode, atau null jika boleh
    private function rateLimitError(string $phone): ?string
    {
        $cfg = $this->settings->verificationConfig();
        $resendSeconds = (int) $cfg['resend_seconds'];
        $maxPerHour    = (int) $cfg['max_per_hour'];

        $last = PhoneVerification::where('phone', $phone)->latest('id')->first();
        if ($last) {
            $wait = $resendSeconds - (int) $last->created_at->diffInSeconds(now());
            if ($wait > 0) {
                return "Tunggu {$wait} detik sebelum meminta kode baru.";
            }
        }

        $lastHour = PhoneVerification::where('phone', $phone)->where('created_at', '>', now()->subHour())->count();
        if ($lastHour >= $maxPerHour) {
            return 'Terlalu banyak permintaan kode untuk nomor ini. Coba lagi 1 jam lagi.';
        }

        return null;
    }

    // Ambil data verifikasi milik pengunjung ini (disimpan di sesi saat klik Daftar / Masuk)
    private function currentVerification(Request $request): ?PhoneVerification
    {
        $id = $request->session()->get('register.verification_id');
        return $id ? PhoneVerification::find($id) : null;
    }
}
