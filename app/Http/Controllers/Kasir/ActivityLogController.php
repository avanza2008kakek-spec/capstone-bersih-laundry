<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Menu khusus Owner → Log Aktivitas (/kasir/log-aktivitas):
 * Memantau seluruh riwayat aktivitas dari semua user (Owner, Kasir, Operator, Kurir, dan Pelanggan).
 */
class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        ActivityLog::ensureSchema();

        $q        = trim((string) $request->query('q'));
        $userId   = $request->query('user_id');
        $role     = $request->query('role');
        $category = $request->query('category');
        $dari     = $request->query('dari');
        $sampai   = $request->query('sampai');

        $logs = ActivityLog::query()
            ->when($userId !== null && $userId !== '', fn ($query) => $query->where('user_id', (int) $userId))
            ->when($role !== null && $role !== '', fn ($query) => $query->where('actor_role', $role))
            ->when($category !== null && $category !== '', fn ($query) => $query->where('category', $category))
            ->when($dari, fn ($query) => $query->whereDate('created_at', '>=', $dari))
            ->when($sampai, fn ($query) => $query->whereDate('created_at', '<=', $sampai))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(fn ($w) => $w
                    ->where('actor_name', 'like', "%{$q}%")
                    ->orWhere('action', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhere('device', 'like', "%{$q}%")
                    ->orWhere('ip_address', 'like', "%{$q}%"));
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('kasir.activity-logs.index', [
            'logs'          => $logs,
            'staffList'     => User::orderBy('name')->get(['id', 'name', 'role']),
            'roles'         => ActivityLog::ROLE_LABELS,
            'categories'    => ActivityLog::CATEGORIES,
            'todayCount'    => ActivityLog::whereDate('created_at', today())->count(),
            'staffCount'    => ActivityLog::where('actor_role', '!=', 'pelanggan')->count(),
            'customerCount' => ActivityLog::where('actor_role', 'pelanggan')->count(),
            'totalCount'    => ActivityLog::count(),
        ]);
    }

    public function destroyOld(Request $request)
    {
        ActivityLog::ensureSchema();

        $data = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:3650'],
        ], [], ['days' => 'jumlah hari']);

        $cutoff = now()->subDays((int) $data['days']);
        $deleted = ActivityLog::where('created_at', '<', $cutoff)->delete();

        return redirect()->route('kasir.activity-logs.index')
            ->with('status', "{$deleted} catatan log aktivitas yang lebih lama dari {$data['days']} hari berhasil dihapus.");
    }
}

