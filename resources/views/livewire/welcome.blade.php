<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;

new class extends Component {
    /** 10 ulasan terbaru yang tampil — untuk ticker berjalan di beranda. */
    #[Computed]
    public function reviews()
    {
        return DB::table('patient_reviews')->where('is_visible', 1)
            ->orderByDesc('id')->limit(10)
            ->get(['reviewer_name', 'rating', 'comment']);
    }

    /** Survei yang sedang aktif (untuk memunculkan ajakan isi survei). */
    #[Computed]
    public function activeSurvey()
    {
        return DB::table('surveys')->where('status', 'active')->orderByDesc('id')->first();
    }
};
?>

@extends('layouts.app-welcome')

@section('content')
    <div class="relative w-full min-h-[calc(100vh-5rem)] overflow-hidden bg-canvas dark:bg-gray-900">
        {{-- BG Welcome --}}
        <div class="absolute inset-0 z-10 overflow-hidden pointer-events-none">
            <img src="{{ asset('images/Logogram black solid.png') }}" alt=""
                class="absolute -left-[5%] -bottom-[10%] w-[40rem] lg:w-[48rem] opacity-5">
        </div>

        {{-- WRAPPER flex supaya footer nempel bawah --}}
        <section class="relative z-20 flex flex-col min-h-[calc(100vh-5rem)]">
            {{-- HERO --}}
            <div class="flex-1">
                <div class="grid items-center grid-cols-1 gap-10 px-6 py-12 mx-auto max-w-7xl lg:grid-cols-2 lg:py-20">
                    {{-- LEFT --}}
                    <div>
                        <p
                            class="inline-flex items-center gap-2 px-3 py-1.5 text-caption font-semibold uppercase tracking-wide rounded-full ring-1
                                   bg-brand-green/10 text-brand-green ring-brand-green/20
                                   dark:bg-brand-lime/10 dark:text-brand-lime dark:ring-brand-lime/20">
                            <span class="w-2 h-2 rounded-full bg-brand-lime animate-pulse"></span>
                            Sistem Internal Layanan &amp; Mutu
                        </p>

                        <h1 class="mt-5 font-bold tracking-tight text-5xl leading-tight sm:text-6xl text-ink dark:text-white">
                            Selamat Datang di
                            <span class="text-brand-green dark:text-brand-lime whitespace-nowrap">SIGAP</span>
                        </h1>

                        <p class="mt-2 text-sm font-medium tracking-wide uppercase text-muted dark:text-gray-400">
                            <span class="text-brand-green dark:text-brand-lime">S</span>istem
                            <span class="text-brand-green dark:text-brand-lime">I</span>nformasi
                            <span class="text-brand-green dark:text-brand-lime">G</span>erak-cepat
                            <span class="text-brand-green dark:text-brand-lime">A</span>duan &amp;
                            <span class="text-brand-green dark:text-brand-lime">P</span>elayanan
                        </p>

                        <p class="max-w-xl mt-5 text-lg leading-relaxed text-body dark:text-gray-300">
                            Satu pintu untuk tiket gangguan, keselamatan pasien, pengaduan, &amp; suara pasien.
                            Punya keluhan, gangguan, atau masukan?
                            <strong class="text-ink dark:text-white">Lapor langsung — tanpa perlu login.</strong>
                            Petugas silakan masuk untuk menangani.
                        </p>

                        {{-- CTA --}}
                        <div class="flex flex-wrap items-center gap-3 mt-8">
                            <a href="{{ route('lapor') }}" wire:navigate>
                                <x-primary-button type="button" class="px-5 py-3">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 110-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 01-1.44-4.282m3.102.069a18.03 18.03 0 01-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 018.835 2.535M10.34 6.66a23.847 23.847 0 008.835-2.535m0 0A23.74 23.74 0 0018.795 3m.38 1.125a23.91 23.91 0 011.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 001.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 010 3.46" /></svg>
                                    Lapor Gangguan
                                </x-primary-button>
                            </a>

                            <a href="{{ route('cek-tiket') }}" wire:navigate>
                                <x-outline-button type="button" class="px-5 py-3">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
                                    Cek Tiket
                                </x-outline-button>
                            </a>

                            <a href="{{ route('suara-pasien') }}" wire:navigate>
                                <x-outline-button type="button" class="px-5 py-3">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M11.48 3.5a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.562.562 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" /></svg>
                                    Beri Ulasan
                                </x-outline-button>
                            </a>

                            <a href="{{ Route::has('login') ? route('login') : '#' }}" wire:navigate>
                                <x-secondary-button type="button" class="px-5 py-3">
                                    Masuk (Petugas)
                                </x-secondary-button>
                            </a>
                        </div>

                        @if ($this->activeSurvey)
                            <p class="mt-4 text-sm text-muted dark:text-gray-400">
                                📋 Ada survei aktif:
                                <a href="{{ route('survei.publik') }}" wire:navigate class="font-medium text-brand-green hover:underline dark:text-brand-lime">{{ $this->activeSurvey->title }} — Isi sekarang →</a>
                            </p>
                        @endif
                        <p class="mt-2 text-sm text-muted dark:text-gray-400">
                            <a href="{{ route('insiden.publik') }}" wire:navigate class="font-medium text-brand-green hover:underline dark:text-brand-lime">🔒 Sampaikan pengaduan / aspirasi karyawan (rahasia) →</a>
                        </p>

                        {{-- Partner --}}
                        <div class="pt-6 mt-10 border-t border-hairline dark:border-white/10">
                            <div class="flex items-center gap-4 mt-3">
                                <p class="text-caption font-semibold text-muted dark:text-gray-400">
                                    Powered by Aturapi Data Technology
                                </p>
                                <span class="w-px h-6 bg-hairline dark:bg-white/10"></span>
                                <div class="w-16 h-1 rounded-full bg-brand-lime"></div>
                            </div>
                        </div>
                    </div>

                    {{-- RIGHT (Ticker Suara Pasien — 10 ulasan terbaru, jalan bawah→atas) --}}
                    <div class="relative">
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-amber-400" fill="currentColor" viewBox="0 0 24 24"><path d="M11.48 3.5a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.562.562 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" /></svg>
                                <span class="font-semibold text-ink dark:text-gray-100">Suara Pasien</span>
                            </div>
                            <a href="{{ route('suara-pasien') }}" wire:navigate class="text-sm font-medium text-brand-green hover:underline dark:text-brand-lime">Lihat semua →</a>
                        </div>

                        <div class="relative h-[26rem] overflow-hidden">
                            {{-- fade atas & bawah --}}
                            <div class="absolute inset-x-0 top-0 z-10 h-16 pointer-events-none bg-gradient-to-b from-canvas to-transparent dark:from-gray-900"></div>
                            <div class="absolute inset-x-0 bottom-0 z-10 h-16 pointer-events-none bg-gradient-to-t from-canvas to-transparent dark:from-gray-900"></div>

                            @if ($this->reviews->count())
                                <div class="space-y-3 animate-marquee-up">
                                    @foreach ($this->reviews->concat($this->reviews) as $rev)
                                        <div class="p-4 bg-canvas border shadow-sm border-hairline rounded-xl dark:bg-gray-800 dark:border-gray-700">
                                            <div class="flex items-center justify-between gap-2">
                                                <div class="flex items-center gap-2 min-w-0">
                                                    <span class="grid text-sm font-bold rounded-full shrink-0 w-8 h-8 place-items-center bg-brand-green/10 text-brand-green dark:bg-brand-lime/15 dark:text-brand-lime">{{ mb_strtoupper(mb_substr($rev->reviewer_name, 0, 1)) }}</span>
                                                    <span class="font-semibold truncate text-ink dark:text-gray-100">{{ $rev->reviewer_name }}</span>
                                                </div>
                                                <div class="flex items-center gap-0.5 shrink-0">
                                                    @for ($i = 1; $i <= 5; $i++)
                                                        <svg class="w-3.5 h-3.5 {{ $i <= $rev->rating ? 'text-amber-400' : 'text-hairline dark:text-gray-600' }}" fill="currentColor" viewBox="0 0 24 24"><path d="M11.48 3.5a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.562.562 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" /></svg>
                                                    @endfor
                                                </div>
                                            </div>
                                            @if ($rev->comment)
                                                <p class="mt-2 text-sm leading-relaxed line-clamp-3 text-body dark:text-gray-300">{{ $rev->comment }}</p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="flex flex-col items-center justify-center h-full gap-2 text-center">
                                    <p class="text-sm text-muted dark:text-gray-400">Belum ada ulasan.</p>
                                    <a href="{{ route('suara-pasien') }}" wire:navigate>
                                        <x-outline-button type="button">Jadilah yang pertama</x-outline-button>
                                    </a>
                                </div>
                            @endif
                        </div>

                        <div class="absolute w-24 h-24 rounded-full -top-6 -right-6 bg-brand-lime blur-2xl opacity-40 -z-10"></div>
                        <div class="absolute w-32 h-32 rounded-full opacity-25 -bottom-10 -left-10 bg-brand-green blur-3xl -z-10"></div>
                    </div>
                </div>
            </div>

            {{-- FOOTER --}}
            <footer class="py-6 border-t border-hairline dark:border-white/10">
                <p class="text-center text-caption text-muted dark:text-gray-400">
                    © {{ date('Y') }} SIGAP · Powered by Aturapi Data Technology
                </p>
            </footer>
        </section>
    </div>
@endsection
