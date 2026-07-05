<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

/**
 * Seed konsep SEKTOR PENANGANAN (mirip "jasa kirim" di Shopee).
 * - Tandai/siapkan departemen sektor: IT, CSSD, IPS (is_sector=1).
 * - Petakan kategori tiket ke sektor penanganannya.
 * Idempotent — aman dijalankan berulang (updateOrInsert + update by name).
 */
class GperSectorSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // 1. Departemen yang jadi SEKTOR penanganan.
        //    IT sudah ada → cukup di-flag. CSSD & IPS dibuat bila belum ada.
        DB::table('departments')->where('code', 'IT')->update(['is_sector' => 1, 'updated_at' => $now]);

        $sectors = [
            ['code' => 'CSSD', 'name' => 'CSSD'],
            ['code' => 'IPS',  'name' => 'IPS'],
        ];
        foreach ($sectors as $s) {
            DB::table('departments')->updateOrInsert(
                ['name' => $s['name']],
                ['code' => $s['code'], 'is_sector' => 1, 'is_active' => 1, 'updated_at' => $now, 'created_at' => $now],
            );
        }

        // 2. Petakan kategori → sektor.
        $itId  = DB::table('departments')->where('code', 'IT')->value('id');
        $ipsId = DB::table('departments')->where('code', 'IPS')->value('id');

        $map = [
            $itId  => ['Hardware', 'Network', 'Software', 'Printer', 'Email', 'Aplikasi / SIM', 'CCTV', 'Telepon'],
            $ipsId => ['Listrik / Power', 'AC'],
        ];
        foreach ($map as $sectorId => $names) {
            if (!$sectorId) {
                continue;
            }
            DB::table('ticket_categories')->whereIn('name', $names)
                ->update(['sector_id' => $sectorId, 'updated_at' => $now]);
        }

        // 3. Kategori khusus CSSD (belum ada di seed awal) — buat bila belum ada.
        $cssdId = DB::table('departments')->where('code', 'CSSD')->value('id');
        if ($cssdId) {
            $cssdCats = ['Sterilisasi Instrumen', 'Autoclave', 'Set Instrumen Bedah', 'Indikator Steril', 'Packing / Wrapping'];
            foreach ($cssdCats as $name) {
                DB::table('ticket_categories')->updateOrInsert(
                    ['name' => $name],
                    ['sector_id' => $cssdId, 'is_active' => 1, 'updated_at' => $now, 'created_at' => $now],
                );
            }
        }
    }
}
