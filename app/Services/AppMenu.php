<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * AppMenu — sumber tunggal definisi menu aplikasi (dashboard + sidebar).
 *
 * - `all()` mengembalikan semua entri (defensive: skip route yang tidak terdaftar)
 * - `forRoles($roles)` filter by role names (lowercase)
 * - `grouped(array $roles)` filter + sort + group by 'group' untuk render UI.
 *
 * Tambah/edit menu di method `definitions()` saja. Dashboard & sidebar otomatis ikut.
 */
class AppMenu
{
    /** Role yang berhak melihat semua menu Master. */
    private const MASTER_ROLES = ['admin', 'manager'];

    /** @return array<int, array<string, mixed>> */
    public static function all(): array
    {
        $entry = function (array $m): ?array {
            if (!Route::has($m['route'])) {
                return null;
            }
            $m['href'] = route($m['route'], $m['params'] ?? []);
            return $m;
        };

        return array_values(array_filter(self::definitions($entry)));
    }

    /**
     * Filter entries by role names (case-insensitive).
     *
     * @param  array<int, string>  $userRoles
     * @return array<int, array<string, mixed>>
     */
    public static function forRoles(array $userRoles): array
    {
        // trim() penting: nama role di DB pernah mengandung trailing space ('Manager Umum ')
        // → strtolower saja tidak match dan seluruh menu role tsb hilang.
        $roles = array_map(fn($r) => trim(strtolower($r)), $userRoles);
        // roles '*' = tampil utk semua user login (mis. "Tiket Saya" — siapa saja bisa lapor).
        return array_values(array_filter(self::all(), fn($m) =>
            in_array('*', $m['roles'], true) || !empty(array_intersect($m['roles'], $roles))
        ));
    }

    /**
     * Sorted + grouped by 'group'. Cocok untuk render sidebar/dashboard.
     *
     * @param  array<int, string>  $userRoles
     */
    public static function grouped(array $userRoles): Collection
    {
        return collect(self::forRoles($userRoles))
            ->sortBy([['groupOrder', 'asc'], ['order', 'asc']])
            ->groupBy('group');
    }

    /**
     * Definisi semua menu. Helper $entry guard route existence + inject 'href'.
     * Setiap entry: ['group','groupOrder','order','route','title','desc','roles','badge'].
     *
     * @param  callable(array):?array  $entry
     * @return array<int, ?array<string, mixed>>
     */
    private static function definitions(callable $entry): array
    {
        $masterRoles = self::MASTER_ROLES;

        return [
            // ── Tiket (operasional) ─────────────────────────────────────
            $entry(['group' => 'Tiket', 'groupOrder' => 0, 'order' => 1, 'route' => 'transaksi.my-tickets', 'title' => 'Tiket Saya', 'desc' => 'Lapor gangguan & pantau tiket yang Anda kirim', 'roles' => ['*'], 'badge' => 'TIKET']),
            $entry(['group' => 'Tiket', 'groupOrder' => 0, 'order' => 2, 'route' => 'transaksi.tickets', 'title' => 'Kelola Tiket', 'desc' => 'Semua tiket — respon, update progress & status (teknisi)', 'roles' => ['admin', 'manager', 'teknisi'], 'badge' => 'TIKET']),

            // ── Master GPER (ticketing) ─────────────────────────────────
            $entry(['group' => 'Master GPER', 'groupOrder' => 1, 'order' => 1, 'route' => 'master.ticket-categories', 'title' => 'Master Kategori Tiket', 'desc' => 'Jenis gangguan: Hardware, Network, Software, dll', 'roles' => $masterRoles, 'badge' => 'GPER']),
            $entry(['group' => 'Master GPER', 'groupOrder' => 1, 'order' => 2, 'route' => 'master.locations', 'title' => 'Master Lokasi', 'desc' => 'Lokasi / ruangan tempat gangguan terjadi', 'roles' => $masterRoles, 'badge' => 'GPER']),
            $entry(['group' => 'Master GPER', 'groupOrder' => 1, 'order' => 3, 'route' => 'master.priorities', 'title' => 'Master Prioritas', 'desc' => 'Tingkat prioritas tiket + SLA & warna badge', 'roles' => $masterRoles, 'badge' => 'GPER']),
            $entry(['group' => 'Master GPER', 'groupOrder' => 1, 'order' => 4, 'route' => 'master.departments', 'title' => 'Master Departemen', 'desc' => 'Unit kerja / departemen pelapor tiket', 'roles' => $masterRoles, 'badge' => 'GPER']),
            $entry(['group' => 'Master GPER', 'groupOrder' => 1, 'order' => 5, 'route' => 'master.ticket-statuses', 'title' => 'Master Status Tiket', 'desc' => 'Status alur tiket (Open, In Progress, Resolved, dll)', 'roles' => $masterRoles, 'badge' => 'GPER']),

            // ── Penawaran ─────────────────────────────────────────────
            $entry(['group' => 'Penawaran', 'groupOrder' => 2, 'order' => 1, 'route' => 'penawaran.perbaikan-siklik', 'title' => 'Proposal SIKLIK', 'desc' => 'Sistem Informasi Klinik — kesiapan piloting rujukan Tulungagung', 'roles' => $masterRoles, 'badge' => 'GPER']),

            // ── Sistem ────────────────────────────────────────────────
            $entry(['group' => 'Sistem', 'groupOrder' => 14, 'order' => 1, 'route' => 'database-monitor.monitoring-dashboard', 'title' => 'Oracle Session Monitor', 'desc' => 'Locks, long-running SQL & kill session', 'roles' => ['admin'], 'badge' => 'DB']),
            $entry(['group' => 'Sistem', 'groupOrder' => 14, 'order' => 2, 'route' => 'database-monitor.user-control', 'title' => 'User Control', 'desc' => 'Kelola user & hak akses (role)', 'roles' => ['admin'], 'badge' => 'USR']),
            $entry(['group' => 'Sistem', 'groupOrder' => 14, 'order' => 3, 'route' => 'database-monitor.role-control', 'title' => 'Role Control', 'desc' => 'Kelola role & permission sistem', 'roles' => ['admin'], 'badge' => 'ROL']),
            $entry(['group' => 'Sistem', 'groupOrder' => 14, 'order' => 4, 'route' => 'database-monitor.user-online', 'title' => 'User Online', 'desc' => 'Daftar user yang sedang aktif login', 'roles' => ['admin'], 'badge' => 'ONL']),
            $entry(['group' => 'Sistem', 'groupOrder' => 14, 'order' => 5, 'route' => 'standarisasi-ui', 'title' => 'Standarisasi UI', 'desc' => 'Acuan design system internal — warna, tipografi & komponen', 'roles' => ['admin'], 'badge' => 'UI']),
        ];
    }
}
