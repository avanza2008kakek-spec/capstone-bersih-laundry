<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;                                       // Rincian nota (cek layanan sudah dipakai)
use App\Models\Service;                                         // Paket layanan & harga
use App\Models\ServiceCategory;                                 // Kategori layanan
use Illuminate\Http\Request;                                    // Penangan request HTTP
use Illuminate\Validation\Rule;                                 // Aturan validasi

/**
 * Menu owner → Layanan & Harga: kelola paket layanan (nama, harga, satuan, lama pengerjaan, aktif/tidak)
 * dan kategorinya. Layanan yang sudah dipakai di nota tidak dihapus (cukup dinonaktifkan) supaya riwayat nota tetap utuh.
 */
class ServiceController extends Controller
{
    public const UNITS = ['kg' => 'kg', 'pcs' => 'pcs', 'meter' => 'meter']; // Sesuai enum services.unit di database

    // 1. Daftar layanan per kategori + form kategori
    public function index()
    {
        return view('kasir.services.index', [
            'categories' => ServiceCategory::with(['services' => fn ($q) => $q->orderBy('name')])->orderBy('name')->get(),
        ]);
    }

    // 2. Form tambah layanan
    public function create()
    {
        return view('kasir.services.form', [
            'service'    => new Service(['unit' => 'kg', 'duration_hours' => 48, 'is_active' => 1]),
            'categories' => ServiceCategory::orderBy('name')->get(),
        ]);
    }

    // 3. Simpan layanan baru
    public function store(Request $request)
    {
        $service = Service::create($this->validated($request));
        return redirect()->route('kasir.services.index')->with('status', "Layanan \"{$service->name}\" ditambahkan.");
    }

    // 4. Form ubah layanan
    public function edit(Service $service)
    {
        return view('kasir.services.form', ['service' => $service, 'categories' => ServiceCategory::orderBy('name')->get()]);
    }

    // 5. Simpan perubahan (harga baru hanya berlaku untuk nota berikutnya; nota lama menyimpan harganya sendiri)
    public function update(Request $request, Service $service)
    {
        $service->update($this->validated($request));
        return redirect()->route('kasir.services.index')->with('status', "Layanan \"{$service->name}\" disimpan.");
    }

    // 6. Aktif / nonaktifkan (layanan nonaktif tidak tampil di website & tidak bisa dipilih di nota baru)
    public function toggle(Service $service)
    {
        $service->update(['is_active' => ! $service->is_active]);
        return back()->with('status', "Layanan \"{$service->name}\" " . ($service->is_active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    // 7. Hapus layanan (hanya jika belum pernah dipakai di nota)
    public function destroy(Service $service)
    {
        if (OrderItem::where('service_id', $service->id)->exists()) {
            return back()->withErrors(['service' => "\"{$service->name}\" sudah dipakai di nota, jadi tidak bisa dihapus. Nonaktifkan saja."]);
        }
        $name = $service->name;
        $service->delete();
        return back()->with('status', "Layanan \"{$name}\" dihapus.");
    }

    // ---------- Kategori ----------

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100', 'unique:service_categories,name'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [], ['name' => 'nama kategori']);
        ServiceCategory::create($data);
        return back()->with('status', "Kategori \"{$data['name']}\" ditambahkan.");
    }

    public function updateCategory(Request $request, ServiceCategory $category)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('service_categories', 'name')->ignore($category->id)],
        ], [], ['name' => 'nama kategori']);
        $category->update($data);
        return back()->with('status', 'Nama kategori disimpan.');
    }

    public function destroyCategory(ServiceCategory $category)
    {
        if ($category->services()->exists()) {
            return back()->withErrors(['service' => "Kategori \"{$category->name}\" masih berisi layanan. Pindahkan/hapus layanannya dulu."]);
        }
        $category->delete();
        return back()->with('status', 'Kategori dihapus.');
    }

    // ---------- Fungsi bantu ----------

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'category_id'    => ['required', 'integer', 'exists:service_categories,id'],
            'name'           => ['required', 'string', 'max:255'],
            'unit'           => ['required', Rule::in(array_keys(self::UNITS))],
            'price'          => ['required', 'numeric', 'min:0', 'max:99999999'],
            'duration_hours' => ['required', 'integer', 'min:1', 'max:720'],   // 1 jam s.d. 30 hari
            'is_active'      => ['nullable', 'boolean'],
        ], [], [
            'category_id' => 'kategori', 'name' => 'nama layanan', 'unit' => 'satuan', 'price' => 'harga', 'duration_hours' => 'lama pengerjaan',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['name'] = trim($data['name']);
        return $data;
    }
}
