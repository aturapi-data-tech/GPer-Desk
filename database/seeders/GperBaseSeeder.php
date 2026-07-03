<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Seed dasar GPER — role inti + 1 user admin.
 * Idempotent (firstOrCreate) — aman dijalankan berulang.
 */
class GperBaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Role inti (guard 'web' — sama dgn default Livewire/Breeze).
        $roles = ['admin', 'manager', 'teknisi', 'direktur'];
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // 2. User admin pertama.
        $admin = User::firstOrCreate(
            ['email' => 'admin@gper.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('admin123'),
                'myuser_profesi' => 'Administrator',
            ]
        );

        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        $this->command?->info('Admin: admin@gper.com / admin123 (role: admin)');
    }
}
