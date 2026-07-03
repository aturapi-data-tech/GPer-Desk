<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed data master GPER: ticket_categories, locations, priorities, departments.
 * Idempotent — insert hanya bila 'name' belum ada (aman dijalankan berulang).
 */
class MasterSeeder extends Seeder
{
    public function run(): void
    {
        $this->seed('ticket_categories', 'name', [
            ['name' => 'Hardware',        'code' => 'HW',   'description' => 'Kerusakan perangkat keras (PC, laptop, monitor, dll)'],
            ['name' => 'Network',         'code' => 'NET',  'description' => 'Gangguan jaringan / internet / LAN / WiFi'],
            ['name' => 'Software',        'code' => 'SW',   'description' => 'Masalah aplikasi / OS / instalasi software'],
            ['name' => 'Printer',         'code' => 'PRN',  'description' => 'Printer & scanner'],
            ['name' => 'Email',           'code' => 'MAIL', 'description' => 'Akun & layanan email'],
            ['name' => 'Aplikasi / SIM',  'code' => 'APP',  'description' => 'Sistem informasi / aplikasi internal'],
            ['name' => 'Listrik / Power', 'code' => 'PWR',  'description' => 'Suplai daya, UPS, stop kontak'],
            ['name' => 'AC',              'code' => 'AC',   'description' => 'Pendingin ruangan'],
            ['name' => 'CCTV',            'code' => 'CCTV', 'description' => 'Kamera pengawas'],
            ['name' => 'Telepon',         'code' => 'TEL',  'description' => 'Telepon & PABX'],
        ]);

        $this->seed('locations', 'name', [
            ['name' => 'Server Room',         'code' => 'SRV',  'building' => 'Gedung A', 'floor' => '1'],
            ['name' => 'Ruang IT',            'code' => 'IT',   'building' => 'Gedung A', 'floor' => '1'],
            ['name' => 'Ruang Direktur',      'code' => 'DIR',  'building' => 'Gedung A', 'floor' => '2'],
            ['name' => 'Ruang HRD',           'code' => 'HRD',  'building' => 'Gedung A', 'floor' => '2'],
            ['name' => 'Ruang Keuangan',      'code' => 'FIN',  'building' => 'Gedung A', 'floor' => '2'],
            ['name' => 'Front Office',        'code' => 'FO',   'building' => 'Gedung A', 'floor' => '1'],
            ['name' => 'Ruang Rapat',         'code' => 'MTG',  'building' => 'Gedung A', 'floor' => '2'],
            ['name' => 'Gudang',              'code' => 'WH',   'building' => 'Gedung B', 'floor' => '1'],
        ]);

        $this->seed('priorities', 'name', [
            ['name' => 'Low',      'level' => 1, 'color' => '#6b7280', 'sla_hours' => 72],
            ['name' => 'Medium',   'level' => 2, 'color' => '#3b82f6', 'sla_hours' => 24],
            ['name' => 'High',     'level' => 3, 'color' => '#f59e0b', 'sla_hours' => 8],
            ['name' => 'Critical', 'level' => 4, 'color' => '#ef4444', 'sla_hours' => 2],
        ]);

        $this->seed('ticket_statuses', 'name', [
            ['name' => 'Open',        'code' => 'OPEN', 'color' => '#3b82f6', 'sort_order' => 1, 'is_closed' => 0],
            ['name' => 'In Progress', 'code' => 'PROG', 'color' => '#f59e0b', 'sort_order' => 2, 'is_closed' => 0],
            ['name' => 'Pending',     'code' => 'PEND', 'color' => '#a855f7', 'sort_order' => 3, 'is_closed' => 0],
            ['name' => 'Resolved',    'code' => 'RESV', 'color' => '#10b981', 'sort_order' => 4, 'is_closed' => 1],
            ['name' => 'Closed',      'code' => 'CLSD', 'color' => '#6b7280', 'sort_order' => 5, 'is_closed' => 1],
            ['name' => 'Cancelled',   'code' => 'CANC', 'color' => '#ef4444', 'sort_order' => 6, 'is_closed' => 1],
        ]);

        $this->seed('departments', 'name', [
            ['name' => 'IT',          'code' => 'IT',   'head_name' => null],
            ['name' => 'HRD',         'code' => 'HRD',  'head_name' => null],
            ['name' => 'Keuangan',    'code' => 'FIN',  'head_name' => null],
            ['name' => 'Umum',        'code' => 'GA',   'head_name' => null],
            ['name' => 'Direksi',     'code' => 'DIR',  'head_name' => null],
            ['name' => 'Operasional', 'code' => 'OPS',  'head_name' => null],
            ['name' => 'Marketing',   'code' => 'MKT',  'head_name' => null],
            ['name' => 'Purchasing',  'code' => 'PUR',  'head_name' => null],
        ]);
    }

    /**
     * Insert baris bila belum ada (match by $key). Isi is_active=1 + timestamps.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function seed(string $table, string $key, array $rows): void
    {
        $inserted = 0;
        foreach ($rows as $row) {
            $exists = DB::table($table)->where($key, $row[$key])->exists();
            if ($exists) {
                continue;
            }
            DB::table($table)->insert([
                ...$row,
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $inserted++;
        }
        $this->command?->info(sprintf('%-18s : +%d baru (total %d)', $table, $inserted, DB::table($table)->count()));
    }
}
