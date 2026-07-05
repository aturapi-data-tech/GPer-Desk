<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * User contoh untuk menguji alur peran & routing sektor.
 * Password semua: gper123. Idempotent (firstOrCreate + update sektor/role).
 */
class GperDemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $sectorId = fn (string $code) => DB::table('departments')->where('code', $code)->value('id');

        $users = [
            ['email' => 'teknisi.it@gper.com',   'name' => 'Teknisi IT',   'role' => 'teknisi',  'sector' => $sectorId('IT')],
            ['email' => 'teknisi.ips@gper.com',  'name' => 'Teknisi IPS',  'role' => 'teknisi',  'sector' => $sectorId('IPS')],
            ['email' => 'teknisi.cssd@gper.com', 'name' => 'Teknisi CSSD', 'role' => 'teknisi',  'sector' => $sectorId('CSSD')],
            ['email' => 'manager@gper.com',      'name' => 'Manager Umum', 'role' => 'manager',  'sector' => null],
            ['email' => 'direktur@gper.com',     'name' => 'Direktur',     'role' => 'direktur', 'sector' => null],
            ['email' => 'pelapor@gper.com',      'name' => 'Budi Pelapor', 'role' => null,       'sector' => null],
        ];

        foreach ($users as $u) {
            $user = User::firstOrCreate(
                ['email' => $u['email']],
                ['name' => $u['name'], 'password' => Hash::make('gper123')],
            );

            // sector_id tidak fillable → set via query builder.
            DB::table('users')->where('id', $user->id)
                ->update(['sector_id' => $u['sector'], 'updated_at' => DB::raw('SYSDATE')]);

            if ($u['role'] && ! $user->hasRole($u['role'])) {
                $user->assignRole($u['role']);
            }
        }

        $this->command?->info('Demo users dibuat/diperbarui — password semua: gper123');
    }
}
