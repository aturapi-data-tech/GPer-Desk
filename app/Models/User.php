<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * Kapabilitas tiket — sumber tunggal supaya tidak drift antar komponen.
     *
     * Konsep peran (GPER-Desk):
     *  - pelapor (tanpa role) : hanya lapor & pantau tiket sendiri ("Tiket Saya").
     *  - teknisi / admin      : HANDLER — respon, ubah status, tutup, assign, CRUD tiket.
     *  - manager / direktur   : PENGAWAS — lihat semua tiket (read-only), TIDAK menangani.
     *                           (manager juga kelola Master GPER — diatur di AppMenu.)
     */

    /** Boleh MENANGANI tiket: respon, ubah status/tutup, assign (teknisi & admin). Bukan CRUD. */
    public function canHandleTickets(): bool
    {
        return $this->hasAnyRole(['admin', 'teknisi']);
    }

    /** Boleh MENGELOLA tiket: buat / edit / hapus. Hanya admin. */
    public function canManageTickets(): bool
    {
        return $this->hasRole('admin');
    }

    /** Teknisi murni (di-scope ke sektornya) — bukan admin/manager/direktur yang lihat semua sektor. */
    public function isSectorTechnician(): bool
    {
        return $this->hasRole('teknisi') && ! $this->hasAnyRole(['admin', 'manager', 'direktur']);
    }

    /** Boleh melihat SEMUA tiket (pengawas + handler): halaman "Kelola Tiket" & dashboard global. */
    public function canViewAllTickets(): bool
    {
        return $this->hasAnyRole(['admin', 'manager', 'teknisi', 'direktur']);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
