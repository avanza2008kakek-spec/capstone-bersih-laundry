<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\User;                                            // Akun staf/kasir (tabel users)
use Illuminate\Http\Request;                                    // Penangan request HTTP
use Illuminate\Support\Facades\Auth;                            // Login/logout staf
use Illuminate\Validation\Rules\Password;                       // Aturan kekuatan password

/**
 * Login staf untuk Area Kasir (email + password, tabel users).
 * Terpisah dari login pelanggan (nomor WA) di /masuk.
 */
class AuthController extends Controller
{
    // 1. Form login kasir
    public function showLogin()
    {
        if (User::count() === 0) {
            return redirect()->route('kasir.setup');            // Belum ada akun sama sekali → buat akun pertama
        }
        return view('kasir.login');
    }

    // 2. Proses login
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->onlyInput('email')->withErrors(['email' => 'Email atau password salah.']);
        }

        $request->session()->regenerate();                      // Ganti ID sesi setelah login (keamanan)
        return redirect()->intended(route('kasir.home'));       // Kembali ke halaman kasir yang tadi dituju
    }

    // 3. Form buat akun kasir pertama (hanya bisa dibuka selama tabel users masih kosong)
    public function setupForm()
    {
        abort_if(User::count() > 0, 404);
        return view('kasir.setup');
    }

    // 4. Simpan akun kasir pertama lalu langsung masuk
    public function setup(Request $request)
    {
        abort_if(User::count() > 0, 404);                       // Setelah ada 1 akun, halaman ini tertutup selamanya

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'max:150'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [], ['name' => 'nama', 'password' => 'password']);

        // Akun pertama selalu owner, supaya bisa membuat akun kasir & operator lewat menu Kelola Staf
        $user = User::create($data + ['role' => User::OWNER]); // Password otomatis di-hash (cast "hashed" di model User)
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('kasir.home')->with('status', 'Akun owner berhasil dibuat.');
    }

    /**
     * 5. Atur password akun owner tanpa SSH (/kasir/atur-owner).
     * Hanya aktif jika KASIR_SETUP_KEY diisi di .env; pengisi form wajib mengetik kunci itu.
     * Setelah selesai, hapus KASIR_SETUP_KEY dari .env supaya halaman ini mati lagi.
     */
    public function ownerPasswordForm()
    {
        abort_if($this->setupKey() === '', 404);
        return view('kasir.owner-password', [
            'owners' => User::where('role', User::OWNER)->orderBy('id')->get(['id', 'name', 'email']),
        ]);
    }

    public function ownerPassword(Request $request)
    {
        abort_if($this->setupKey() === '', 404);

        $data = $request->validate([
            'setup_key' => ['required', 'string'],
            'email'     => ['required', 'email'],
            'password'  => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [], ['setup_key' => 'kunci pengaturan', 'password' => 'password baru']);

        // hash_equals: bandingkan kunci dengan aman (tidak bocor lewat lama waktu proses)
        if (! hash_equals($this->setupKey(), $data['setup_key'])) {
            return back()->onlyInput('email')->withErrors(['setup_key' => 'Kunci pengaturan salah.']);
        }

        // Hanya akun ber-role owner yang bisa diatur dari sini
        $owner = User::where('email', $data['email'])->where('role', User::OWNER)->first();
        if (! $owner) {
            return back()->onlyInput('email')->withErrors(['email' => 'Email ini bukan akun owner.']);
        }

        $owner->update(['password' => $data['password']]);     // Di-hash otomatis
        Auth::login($owner);
        $request->session()->regenerate();

        return redirect()->route('kasir.home')
            ->with('status', 'Password owner berhasil diatur. Sekarang hapus KASIR_SETUP_KEY dari file .env di server.');
    }

    // 6. Keluar dari Area Kasir
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();                      // Hapus sesi staf
        $request->session()->regenerateToken();

        return redirect()->route('kasir.login');
    }

    // Kunci pengaturan dari .env (KASIR_SETUP_KEY); kosong = halaman atur owner tidak aktif
    private function setupKey(): string
    {
        return (string) config('services.laundry.setup_key');
    }
}
