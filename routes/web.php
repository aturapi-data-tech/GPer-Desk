<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'welcome')->name('home');

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
