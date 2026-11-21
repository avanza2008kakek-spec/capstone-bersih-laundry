<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\CourierTask;                                     // Tugas kurir
use App\Models\Expense;                                         // Pengeluaran
use App\Models\Order;                                           // Nota transaksi
use App\Models\OrderStatusLog;                                  // Riwayat status nota
use App\Models\Payment;                                         // Pembayaran
use App\Models\Reservation;                                     // Reservasi
use App\Models\User;                                            // Akun staf (tabel users)
use Illuminate\Http\Request;                                    // Penangan request HTTP
use Illuminate\Support\Facades\DB;                              // Transaksi database
use Illuminate\Support\Facades\Schema;                          // Cek keberadaan tabel
use Illuminate\Validation\Rule;                                 // Aturan validasi (unik, pilihan role)
use Illuminate\Validation\Rules\Password;                       // Aturan kekuatan password

/**
 * Menu owner → Kelola Staf: buat akun, beri role (owner/kasir/operator), ganti password, hapus akun.
 * Pengaman: owner tidak bisa menurunkan role / menghapus akunnya sendiri, dan minimal selalu ada 1 owner.
 */
class StaffController extends Controller
{
    // 1. Daftar semua staf
    public function index()
    {
        return view('kasir.staff.index', [
            // Urut: owner dulu, lalu kasir, lalu operator; dalam role yang sama urut nama
            'staff' => User::orderByRaw("CASE role WHEN 'owner' THEN 1 WHEN 'kasir' THEN 2 WHEN 'operator' THEN 3 ELSE 4 END")->orderBy('name')->get(),
        ]);
    }

    // 2. Form tambah staf
    public function create()
    {
        return view('kasir.staff.form', ['staff' => new User(['role' => User::KASIR])]);
    }

    // 3. Simpan staf baru
    public function store(Request $request)
    {
        $data = $request->validate($this->rules(null), [], $this->attributes());

        $user = User::create([
            'name'     => trim($data['name']),
            'email'    => mb_strtolower(trim($data['email'])),
            'phone'    => $data['phone'] ?? null,
            'role'     => $data['role'],
            'password' => $data['password'],                    // Di-hash otomatis (cast "hashed")
        ]);

        return redirect()->route('kasir.staff.index')
            ->with('status', "Akun {$user->name} ({$user->roleLabel()}) berhasil dibuat. Berikan email & password-nya ke yang bersangkutan.");
    }

    // 4. Form ubah staf (nama, email, HP, role, password baru opsional)
    public function edit(User $user)
    {
        return view('kasir.staff.form', ['staff' => $user]);
    }

    // 5. Simpan perubahan staf
    public function update(Request $request, User $user)
    {
        $data = $request->validate($this->rules($user), [], $this->attributes());

        // Pengaman: owner tidak boleh menurunkan role dirinya sendiri (bisa terkunci dari menu owner)
        if ($user->is($request->user()) && $data['role'] !== User::OWNER) {
            return back()->withInput()->withErrors(['role' => 'Anda tidak bisa mengubah role akun Anda sendiri.']);
        }
        // Pengaman: owner terakhir tidak boleh diturunkan menjadi kasir/operator
        if ($user->isOwner() && $data['role'] !== User::OWNER && $this->ownerCount() <= 1) {
            return back()->withInput()->withErrors(['role' => 'Minimal harus ada 1 owner.']);
        }

        $changes = [
            'name'  => trim($data['name']),
            'email' => mb_strtolower(trim($data['email'])),
            'phone' => $data['phone'] ?? null,
            'role'  => $data['role'],
        ];
        if (! empty($data['password'])) {
            $changes['password'] = $data['password'];           // Hanya diganti jika diisi
        }
        $user->update($changes);

        return redirect()->route('kasir.staff.index')
            ->with('status', "Data {$user->name} berhasil disimpan" . (! empty($data['password']) ? ' (password diganti).' : '.'));
    }

    // 6. Hapus akun staf
    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['staff' => 'Anda tidak bisa menghapus akun Anda sendiri.']);
        }
        if ($user->isOwner() && $this->ownerCount() <= 1) {
            return back()->withErrors(['staff' => 'Minimal harus ada 1 owner.']);
        }

        $name = $user->name;
        $ownerId = $request->user()->id;

        try {
            DB::transaction(function () use ($user, $ownerId) {
                // Alihkan riwayat transaksi/keuangan staf yang dihapus ke akun owner agar laporan tetap utuh
                // dan tidak terhalang foreign key constraint (orders_user_id_foreign, dll.)
                Order::where('user_id', $user->id)->update(['user_id' => $ownerId]);
                OrderStatusLog::where('user_id', $user->id)->update(['user_id' => $ownerId]);
                Payment::where('user_id', $user->id)->update(['user_id' => $ownerId]);
                Expense::where('user_id', $user->id)->update(['user_id' => $ownerId]);

                if (Schema::hasTable('reservations')) {
                    Reservation::where('handled_by', $user->id)->update(['handled_by' => null]);
                }
                if (Schema::hasTable('activity_logs')) {
                    \App\Models\ActivityLog::where('user_id', $user->id)->update(['user_id' => null]);
                }
                if (Schema::hasTable('courier_tasks')) {
                    CourierTask::where('courier_id', $user->id)->active()->update([
                        'status'      => 'cancelled',
                        'finished_at' => now(),
                    ]);
                    CourierTask::where('courier_id', $user->id)->update(['courier_id' => $ownerId]);
                    CourierTask::where('assigned_by', $user->id)->update(['assigned_by' => $ownerId]);
                }

                $user->delete();
            });
        } catch (\Illuminate\Database\QueryException $e) {
            return back()->withErrors(['staff' => "Akun {$name} tidak dapat dihapus karena masih terkait dengan data transaksi."]);
        }

        return redirect()->route('kasir.staff.index')->with('status', "Akun {$name} berhasil dihapus.");
    }

    // ---------- Fungsi bantu ----------

    /** Aturan isian form. $user = null saat tambah (password wajib), berisi saat ubah (password opsional). */
    private function rules(?User $user): array
    {
        return [
            'name'     => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)], // Email tidak boleh dobel
            'phone'    => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s]{8,20}$/'],
            'role'     => ['required', Rule::in(array_keys(User::ROLES))],                                    // owner / kasir / operator
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
        ];
    }

    private function attributes(): array
    {
        return ['name' => 'nama', 'phone' => 'no. HP', 'password' => 'password'];
    }

    private function ownerCount(): int
    {
        return User::where('role', User::OWNER)->count();
    }
}
