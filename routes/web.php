<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\Kasir\AuthController as KasirAuthController;
use App\Http\Controllers\Kasir\SettingController as KasirSettingController;
use App\Http\Controllers\Kasir\StaffController as KasirStaffController;
use App\Http\Controllers\Kasir\DashboardController as KasirDashboardController;
use App\Http\Controllers\Kasir\OrderController as KasirOrderController;
use App\Http\Controllers\Kasir\QueueController as KasirQueueController;
use App\Http\Controllers\Kasir\CustomerController as KasirCustomerController;
use App\Http\Controllers\Kasir\ExpenseController as KasirExpenseController;
use App\Http\Controllers\Kasir\ServiceController as KasirServiceController;
use App\Http\Controllers\Kasir\ReservationController as KasirReservationController;
use App\Http\Controllers\Kasir\CourierTaskController as KasirCourierTaskController;
use App\Http\Controllers\Kasir\CourierController as KasirCourierController;
use App\Http\Controllers\Kasir\ActivityLogController as KasirActivityLogController;

// 1. Halaman Depan Pelanggan (Lacak Nota, Katalog Harga, Kalkulator)
Route::get('/', [CustomerController::class, 'index'])->name('customer.home');
Route::get('/lacak-cek', [CustomerController::class, 'checkStatus'])->name('customer.track.check');

// 2 & 3. Transaksi, Antrean, Pelanggan, Pengeluaran → sekarang di Area Kasir (/kasir/..., lihat bagian 7)

// 4. Modul Laporan Keuangan (Arus Kas, Laba Rugi, Piutang) — khusus owner yang sudah login
Route::get('/laporan', [ReportController::class, 'index'])->middleware(['auth', 'role:owner'])->name('reports.index');

// 5. Pendaftaran Pelanggan dengan Verifikasi Kode lewat WhatsApp
//    throttle:6,1 = maksimal 6 permintaan per menit per alamat IP (cegah spam kirim kode)
Route::get('/daftar', [RegisterController::class, 'create'])->name('register.create');
Route::post('/daftar', [RegisterController::class, 'store'])->middleware('throttle:6,1')->name('register.store');
Route::get('/daftar/verifikasi', [RegisterController::class, 'verifyForm'])->name('register.verify');
Route::post('/daftar/verifikasi', [RegisterController::class, 'verify'])->middleware('throttle:10,1')->name('register.check');
Route::post('/daftar/kirim-ulang', [RegisterController::class, 'resend'])->middleware('throttle:6,1')->name('register.resend');
// Jika alamat kirim-ulang terbuka lewat GET (cth: dimuat ulang browser / CDN), kembalikan ke halaman verifikasi, bukan error 405
Route::get('/daftar/kirim-ulang', fn () => redirect()->route('register.verify'));
Route::get('/daftar/berhasil', [RegisterController::class, 'done'])->name('register.done');
// Link verifikasi otomatis di pesan WA (bertanda tangan); whereNumber: ID harus angka
Route::get('/daftar/v/{verification}', [RegisterController::class, 'magic'])->whereNumber('verification')->middleware('throttle:10,1')->name('register.magic');

// 6. Masuk Pelanggan (nomor WA yang sudah terdaftar) dengan kode verifikasi WhatsApp, dan Keluar
Route::get('/masuk', [RegisterController::class, 'loginForm'])->name('login.form');
Route::post('/masuk', [RegisterController::class, 'login'])->middleware('throttle:6,1')->name('login.send');
Route::post('/keluar', [RegisterController::class, 'logout'])->name('logout');

// 6b. Reservasi laundry (khusus pelanggan yang sudah masuk) → diteruskan ke WhatsApp admin
Route::post('/reservasi', [ReservationController::class, 'store'])->middleware('throttle:10,1')->name('reservation.store');

// 6c. Edit Alamat pelanggan (alamat default + lokasi Google Maps)
Route::get('/akun/alamat', [AddressController::class, 'edit'])->name('address.edit');
Route::put('/akun/alamat', [AddressController::class, 'update'])->middleware('throttle:10,1')->name('address.update');
// Tombol "Gunakan lokasi saya": titik GPS → alamat teks (maks. 20x per menit)
Route::get('/akun/alamat/dari-lokasi', [AddressController::class, 'fromLocation'])->middleware('throttle:20,1')->name('address.from-location');
// Kotak "Cari" di peta pilih titik: cari jalan/tempat (maks. 20x per menit)
// Link pendek Google Maps yang ditempel → link berkoordinat (maks. 20x per menit)
Route::get('/akun/alamat/buka-link', [AddressController::class, 'resolveLink'])->middleware('throttle:20,1')->name('address.resolve-link');
Route::get('/akun/alamat/cari-tempat', [AddressController::class, 'searchPlace'])->middleware('throttle:20,1')->name('address.search-place');

// 7. Area Kasir (khusus staf, login email + password)
//    guest = hanya untuk yang BELUM login, auth = wajib sudah login
Route::prefix('kasir')->name('kasir.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/masuk', [KasirAuthController::class, 'showLogin'])->name('login');
        Route::post('/masuk', [KasirAuthController::class, 'login'])->middleware('throttle:5,1')->name('login.attempt'); // Maks. 5 percobaan/menit
        Route::get('/buat-akun', [KasirAuthController::class, 'setupForm'])->name('setup');  // Hanya saat belum ada akun
        Route::post('/buat-akun', [KasirAuthController::class, 'setup'])->middleware('throttle:5,1')->name('setup.store');
        // Atur password owner tanpa SSH: hanya aktif jika KASIR_SETUP_KEY diisi di .env
        Route::get('/atur-owner', [KasirAuthController::class, 'ownerPasswordForm'])->name('owner-password');
        Route::post('/atur-owner', [KasirAuthController::class, 'ownerPassword'])->middleware('throttle:5,1')->name('owner-password.store');
    });

    Route::middleware('auth')->group(function () {
        Route::get('/', KasirDashboardController::class)->name('home');                          // Dashboard ringkasan hari ini (isi menyesuaikan role)
        Route::post('/keluar', [KasirAuthController::class, 'logout'])->name('logout');

        Route::middleware('role:owner,kasir,operator')->group(function () {
            // Antrean Cucian: owner, kasir, operator
            Route::get('/antrean', [KasirQueueController::class, 'index'])->name('queue.index');
            Route::post('/antrean/{order}', [KasirQueueController::class, 'move'])->whereNumber('order')->name('queue.move');

            // Reservasi dari website (notifikasi reservasi baru + ubah status jemput)
            Route::get('/reservasi', [KasirReservationController::class, 'index'])->name('reservations.index');
            Route::get('/reservasi/cek', [KasirReservationController::class, 'check'])->name('reservations.check');      // JSON untuk notifikasi otomatis
            Route::put('/reservasi/{reservation}', [KasirReservationController::class, 'update'])->whereNumber('reservation')->name('reservations.update');
        });

        // Foto bukti jemput/antar: owner, kasir, dan kurir yang bersangkutan (dicek di controller)
        Route::get('/tugas-kurir/{task}/foto', [KasirCourierTaskController::class, 'photo'])->whereNumber('task')->name('courier-tasks.photo');

        // Menu kurir: tugas jemput & antar dari kasir
        Route::middleware('role:kurir')->prefix('tugas-saya')->name('courier.')->group(function () {
            Route::get('/', [KasirCourierController::class, 'index'])->name('index');
            Route::get('/cek', [KasirCourierController::class, 'check'])->name('check');                                        // JSON untuk notifikasi tugas baru
            Route::post('/{task}/berangkat', [KasirCourierController::class, 'start'])->whereNumber('task')->name('start');
            Route::post('/{task}/selesai', [KasirCourierController::class, 'complete'])->whereNumber('task')->name('complete'); // Wajib foto bukti
            Route::post('/{task}/serah-kasir', [KasirCourierController::class, 'handoverToKasir'])->whereNumber('task')->name('handover'); // Foto serah jemputan ke kasir
            Route::post('/{task}/gagal', [KasirCourierController::class, 'fail'])->whereNumber('task')->name('fail');
        });

        // Menu owner & kasir: Transaksi, Pelanggan, Pengeluaran, Tugas Kurir
        Route::middleware('role:owner,kasir')->group(function () {
            // Tugas Kurir: tugaskan jemput (dari reservasi) & antar (dari nota), ganti kurir, batalkan
            Route::get('/tugas-kurir', [KasirCourierTaskController::class, 'index'])->name('courier-tasks.index');
            Route::post('/reservasi/{reservation}/kurir', [KasirCourierTaskController::class, 'assignPickup'])->whereNumber('reservation')->name('courier-tasks.pickup');
            Route::post('/transaksi/{order}/kurir', [KasirCourierTaskController::class, 'assignDelivery'])->whereNumber('order')->name('courier-tasks.delivery');
            Route::put('/tugas-kurir/{task}/kurir', [KasirCourierTaskController::class, 'reassign'])->whereNumber('task')->name('courier-tasks.reassign');
            Route::post('/tugas-kurir/{task}/serah-terima', [KasirCourierTaskController::class, 'handover'])->whereNumber('task')->name('courier-tasks.handover');
            Route::post('/tugas-kurir/{task}/batal', [KasirCourierTaskController::class, 'cancel'])->whereNumber('task')->name('courier-tasks.cancel');

            Route::get('/transaksi', [KasirOrderController::class, 'index'])->name('orders.index');
            Route::get('/transaksi/baru', [KasirOrderController::class, 'create'])->name('orders.create');
            Route::post('/transaksi', [KasirOrderController::class, 'store'])->name('orders.store');
            Route::get('/transaksi/{order}', [KasirOrderController::class, 'show'])->whereNumber('order')->name('orders.show');
            Route::post('/transaksi/{order}/bayar', [KasirOrderController::class, 'pay'])->whereNumber('order')->name('orders.pay');
            Route::post('/transaksi/{order}/diambil', [KasirOrderController::class, 'pickup'])->whereNumber('order')->name('orders.pickup');
            Route::get('/transaksi/{order}/cetak', [KasirOrderController::class, 'receipt'])->whereNumber('order')->name('orders.receipt');

            Route::get('/pelanggan', [KasirCustomerController::class, 'index'])->name('customers.index');
            Route::get('/pelanggan/cari', [KasirCustomerController::class, 'search'])->name('customers.search');     // Untuk form nota baru (JSON)
            Route::get('/pelanggan/tambah', [KasirCustomerController::class, 'create'])->name('customers.create');
            Route::post('/pelanggan', [KasirCustomerController::class, 'store'])->name('customers.store');
            Route::get('/pelanggan/{customer}', [KasirCustomerController::class, 'show'])->whereNumber('customer')->name('customers.show');
            Route::get('/pelanggan/{customer}/ubah', [KasirCustomerController::class, 'edit'])->whereNumber('customer')->name('customers.edit');
            Route::put('/pelanggan/{customer}', [KasirCustomerController::class, 'update'])->whereNumber('customer')->name('customers.update');

            Route::get('/pengeluaran', [KasirExpenseController::class, 'index'])->name('expenses.index');
            Route::post('/pengeluaran', [KasirExpenseController::class, 'store'])->name('expenses.store');
        });

        // Menu khusus owner
        Route::middleware('role:owner')->group(function () {
            Route::get('/pengaturan', [KasirSettingController::class, 'edit'])->name('settings');   // Pengaturan Usaha
            Route::put('/pengaturan', [KasirSettingController::class, 'update'])->name('settings.update');               // Nama laundry
            Route::put('/pengaturan/lokasi', [KasirSettingController::class, 'updateLocation'])->name('settings.location'); // Lokasi laundry
            Route::put('/pengaturan/ongkos', [KasirSettingController::class, 'updateRates'])->name('settings.rates');       // Ongkos jemput per km
            Route::put('/pengaturan/pembayaran', [KasirSettingController::class, 'updatePayment'])->name('settings.payment'); // Rekening transfer & gambar QRIS
            Route::delete('/pengaturan/qris', [KasirSettingController::class, 'destroyQris'])->name('settings.qris.destroy'); // Hapus gambar QRIS
            Route::put('/pengaturan/verifikasi', [KasirSettingController::class, 'updateVerification'])->name('settings.verification'); // Pengiriman kode verifikasi WA (OTP)
            Route::post('/pengaturan/verifikasi/tes', [KasirSettingController::class, 'testVerification'])->name('settings.verification.test'); // Tes kirim kode verifikasi ke WA
            Route::put('/pengaturan/tema', [KasirSettingController::class, 'updateTheme'])->name('settings.theme');         // Tema web

            // Layanan & Harga: paket layanan + kategorinya
            Route::get('/layanan', [KasirServiceController::class, 'index'])->name('services.index');
            Route::get('/layanan/tambah', [KasirServiceController::class, 'create'])->name('services.create');
            Route::post('/layanan', [KasirServiceController::class, 'store'])->name('services.store');
            Route::get('/layanan/{service}/ubah', [KasirServiceController::class, 'edit'])->whereNumber('service')->name('services.edit');
            Route::put('/layanan/{service}', [KasirServiceController::class, 'update'])->whereNumber('service')->name('services.update');
            Route::post('/layanan/{service}/aktif', [KasirServiceController::class, 'toggle'])->whereNumber('service')->name('services.toggle');
            Route::delete('/layanan/{service}', [KasirServiceController::class, 'destroy'])->whereNumber('service')->name('services.destroy');
            Route::post('/layanan/kategori', [KasirServiceController::class, 'storeCategory'])->name('services.categories.store');
            Route::put('/layanan/kategori/{category}', [KasirServiceController::class, 'updateCategory'])->whereNumber('category')->name('services.categories.update');
            Route::delete('/layanan/kategori/{category}', [KasirServiceController::class, 'destroyCategory'])->whereNumber('category')->name('services.categories.destroy');

            // Kelola Staf: buat akun, beri role (owner/kasir/operator), ganti password, hapus
            Route::get('/staf', [KasirStaffController::class, 'index'])->name('staff.index');
            Route::get('/staf/tambah', [KasirStaffController::class, 'create'])->name('staff.create');
            Route::post('/staf', [KasirStaffController::class, 'store'])->name('staff.store');
            Route::get('/staf/{user}/ubah', [KasirStaffController::class, 'edit'])->whereNumber('user')->name('staff.edit');
            Route::put('/staf/{user}', [KasirStaffController::class, 'update'])->whereNumber('user')->name('staff.update');
            Route::delete('/staf/{user}', [KasirStaffController::class, 'destroy'])->whereNumber('user')->name('staff.destroy');

            // Pengeluaran: hapus catatan & tambah kategori biaya
            Route::delete('/pengeluaran/{expense}', [KasirExpenseController::class, 'destroy'])->whereNumber('expense')->name('expenses.destroy');
            Route::post('/pengeluaran/kategori', [KasirExpenseController::class, 'storeCategory'])->name('expenses.categories.store');

            // Foto Bukti kurir (disimpan di database): lihat pemakaian, hapus satu foto, hapus foto lama
            Route::get('/foto-bukti', [KasirCourierTaskController::class, 'photos'])->name('courier-photos.index');
            Route::delete('/foto-bukti/lama', [KasirCourierTaskController::class, 'destroyOldPhotos'])->name('courier-photos.destroy-old');
            Route::delete('/tugas-kurir/{task}/foto', [KasirCourierTaskController::class, 'destroyPhoto'])->whereNumber('task')->name('courier-tasks.photo.destroy');

            // Log Aktivitas semua user (Owner, Kasir, Operator, Kurir, dan Pelanggan)
            Route::get('/log-aktivitas', [KasirActivityLogController::class, 'index'])->name('activity-logs.index');
            Route::delete('/log-aktivitas/lama', [KasirActivityLogController::class, 'destroyOld'])->name('activity-logs.destroy-old');
        });
    });
});
