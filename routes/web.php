<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'welcome')->name('home');

// Form lapor publik/anonim — tanpa login (nama pelapor wajib).
Route::livewire('/lapor', 'pages::lapor-publik.lapor-publik')->name('lapor');

// Suara Pasien — ulasan/penilaian publik (ala Google Review), tanpa login.
Route::livewire('/suara-pasien', 'pages::suara-pasien.publik')->name('suara-pasien');

// Cek progress perbaikan berdasarkan nomor tiket — publik, tanpa login.
Route::livewire('/cek-tiket', 'pages::cek-tiket.cek-tiket')->name('cek-tiket');

// Survei publik — isi survei yang sedang aktif, tanpa login.
Route::livewire('/survei', 'pages::survei-publik.survei-publik')->name('survei.publik');

// Pelaporan insiden karyawan (whistleblowing) — publik, bisa anonim.
Route::livewire('/lapor-insiden', 'pages::insiden-publik.insiden-publik')->name('insiden.publik');

// Pelaporan Insiden Keselamatan Pasien (IKP) — publik, bisa anonim (no blame).
Route::livewire('/lapor-ikp', 'pages::ikp-publik.ikp-publik')->name('ikp.publik');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/dashboard', 'dashboard')->name('dashboard');
});

Route::middleware(['auth'])->group(function () {
    // Halaman acuan standarisasi UI (design system internal)
    Route::livewire('/standarisasi-ui', 'pages::standarisasi-ui.standarisasi-ui')
        ->name('standarisasi-ui');

    // ===========================================
    // GPER — TRANSAKSI TIKET
    // ===========================================
    Route::livewire('/transaksi/my-tickets', 'pages::transaksi.my-tickets.my-tickets')
        ->name('transaksi.my-tickets');
    Route::livewire('/transaksi/tickets', 'pages::transaksi.tickets.tickets')
        ->name('transaksi.tickets');

    // Rekap Suara Pasien (moderasi ulasan) — admin/manager/direktur.
    Route::livewire('/suara-pasien/rekap', 'pages::suara-pasien.rekap')
        ->name('suara-pasien.rekap');

    // Survei — kelola (admin/manager): daftar, builder, hasil.
    Route::livewire('/survei/kelola', 'pages::survei.kelola')->name('survei.kelola');
    Route::livewire('/survei/kelola/builder', 'pages::survei.builder')->name('survei.builder');
    Route::livewire('/survei/kelola/hasil', 'pages::survei.hasil')->name('survei.hasil');

    // Pelaporan Insiden — rekap & evaluasi (admin/manager).
    Route::livewire('/insiden/rekap', 'pages::insiden.rekap')->name('insiden.rekap');
    // Dokumentasi API bridging pengaduan (admin — menampilkan token).
    Route::livewire('/insiden/api-docs', 'pages::insiden.api-docs')->name('insiden.api-docs');

    // Insiden Keselamatan Pasien (IKP) — rekap, grading & tindak lanjut (admin/manager).
    Route::livewire('/ikp/rekap', 'pages::ikp.rekap')->name('ikp.rekap');

    // ===========================================
    // DOKUMEN REGULASI & AKREDITASI — registri bertingkat, penomoran, penyusun SPO
    // ===========================================
    Route::livewire('/dokumen/registri', 'pages::dokumen.registri.registri')->name('dokumen.registri');
    Route::livewire('/dokumen/pengaturan', 'pages::dokumen.pengaturan.pengaturan')->name('dokumen.pengaturan');
    Route::livewire('/dokumen/ttd-saya', 'pages::dokumen.ttd-saya.ttd-saya')->name('dokumen.ttd-saya');
    Route::get('/dokumen/{dokId}/cetak.pdf', [\App\Http\Controllers\DokumenCetakController::class, 'pdf'])
        ->whereNumber('dokId')->name('dokumen.cetak.pdf');
    Route::get('/dokumen/{dokId}/unduh.docx', [\App\Http\Controllers\DokumenCetakController::class, 'docx'])
        ->whereNumber('dokId')->name('dokumen.cetak.docx');
    Route::get('/dokumen/{dokId}/gambar/{src}', [\App\Http\Controllers\DokumenCetakController::class, 'gambar'])
        ->whereNumber('dokId')->name('dokumen.gambar');
    Route::get('/dokumen/{dokId}/berkas/{berkasId}', [\App\Http\Controllers\DokumenCetakController::class, 'berkas'])
        ->whereNumber('dokId')->name('dokumen.berkas');

    // ===========================================
    // GPER — MASTER DATA (ticketing & strategis)
    // ===========================================
    Route::livewire('/master/ticket-categories', 'pages::master.master-ticket-categories.master-ticket-categories')
        ->name('master.ticket-categories');
    Route::livewire('/master/locations', 'pages::master.master-locations.master-locations')
        ->name('master.locations');
    Route::livewire('/master/priorities', 'pages::master.master-priorities.master-priorities')
        ->name('master.priorities');
    Route::livewire('/master/departments', 'pages::master.master-departments.master-departments')
        ->name('master.departments');
    Route::livewire('/master/ticket-statuses', 'pages::master.master-ticket-statuses.master-ticket-statuses')
        ->name('master.ticket-statuses');

    // ===========================================
    // GPER — PENAWARAN
    // ===========================================
    Route::livewire('/penawaran/perbaikan-siklik', 'pages::penawaran.perbaikan-siklik.perbaikan-siklik')
        ->name('penawaran.perbaikan-siklik');

    // ===========================================
    // SISTEM — Database Monitor
    // ===========================================
    Route::livewire('/database-monitor/user-control', 'pages::database-monitor.user-control.user-control')
        ->name('database-monitor.user-control');
    Route::livewire('/database-monitor/role-control', 'pages::database-monitor.role-control.role-control')
        ->name('database-monitor.role-control');
    Route::livewire('/database-monitor/user-online', 'pages::database-monitor.user-online.user-online')
        ->name('database-monitor.user-online');
    Route::livewire('/database-monitor/monitoring-dashboard', 'pages::database-monitor.monitoring-dashboard.monitoring-dashboard')
        ->name('database-monitor.monitoring-dashboard');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
