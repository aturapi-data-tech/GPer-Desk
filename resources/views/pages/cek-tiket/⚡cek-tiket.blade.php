<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.public-form')] class extends Component {
    public string $ticketNo = '';
    public bool   $searched = false;

    public function mount(): void
    {
        // Boleh diakses langsung dengan ?no=TIC-... (mis. dari link di form lapor).
        $pref = request()->query('no');
        if ($pref) {
            $this->ticketNo = (string) $pref;
            $this->searched = true;
        }
    }

    public function search(): void
    {
        $this->searched = true;
        unset($this->ticket, $this->timeline);
    }

    #[Computed]
    public function ticket()
    {
        $nomor = mb_strtoupper(trim($this->ticketNo));
        if ($nomor === '') {
            return null;
        }
        return DB::table('tickets as t')
            ->leftJoin('ticket_statuses as s', 's.id', '=', 't.status_id')
            ->leftJoin('ticket_categories as c', 'c.id', '=', 't.category_id')
            ->leftJoin('priorities as p', 'p.id', '=', 't.priority_id')
            ->leftJoin('departments as sec', 'sec.id', '=', 't.sector_id')
            ->leftJoin('users as u', 'u.id', '=', 't.technician_id')
            ->whereRaw('UPPER(t.ticket_no) = ?', [$nomor])
            ->select(
                't.id', 't.ticket_no', 't.title', 't.resolution',
                's.name as status', 's.color as status_color', 's.is_closed',
                'c.name as category', 'sec.name as sector', 'p.name as priority',
                'u.name as technician',
                DB::raw("TO_CHAR(t.reported_at, 'dd/mm/yyyy HH24:MI') as reported_at"),
                DB::raw("TO_CHAR(t.resolved_at, 'dd/mm/yyyy HH24:MI') as resolved_at")
            )
            ->first();
    }

    /** Riwayat status (dibuat / ubah status / ditugaskan) — tanpa chat internal. */
    #[Computed]
    public function timeline()
    {
        $tiket = $this->ticket;
        if (!$tiket) {
            return [];
        }
        return DB::table('ticket_activities as a')
            ->where('a.ticket_id', $tiket->id)
            ->orderBy('a.created_at')->orderBy('a.id')
            ->select('a.action', 'a.from_value', 'a.to_value', 'a.note',
                DB::raw("TO_CHAR(a.created_at, 'dd/mm/yyyy HH24:MI') as at"))
            ->get();
    }
};
?>

<div class="w-full max-w-2xl mx-auto">

        {{-- Brand header --}}
        <div class="flex items-start gap-3 mb-6">
            <div class="flex items-center justify-center shrink-0 w-12 h-12 rounded-2xl bg-brand-green/10 dark:bg-brand-lime/15">
                <img src="{{ asset('images/Logogram black solid.png') }}" alt="Logo" class="block w-7 h-7 dark:hidden" />
                <img src="{{ asset('images/Logogram white solid.png') }}" alt="Logo" class="hidden w-7 h-7 dark:block" />
            </div>
            <div>
                <h1 class="text-2xl font-bold leading-tight text-ink dark:text-gray-100">Cek Progress Perbaikan</h1>
                <p class="mt-1 text-sm leading-relaxed text-muted dark:text-gray-400">
                    Masukkan <strong>nomor tiket</strong> laporan Anda untuk melihat status & perkembangan penanganannya.
                </p>
            </div>
        </div>

        {{-- Form cari --}}
        <form wire:submit="search" class="flex items-end gap-2 p-4 mb-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="flex-1">
                <x-input-label value="Nomor Tiket" />
                <x-text-input wire:model="ticketNo" placeholder="mis. TIC-2026-0005" class="w-full mt-1 uppercase" />
            </div>
            <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="search">
                <span wire:loading.remove wire:target="search">Cari</span>
                <span wire:loading wire:target="search">Mencari...</span>
            </x-primary-button>
        </form>

        @if ($searched)
            @php $tiket = $this->ticket; @endphp
            @if ($tiket)
                {{-- KARTU TIKET --}}
                <div class="overflow-hidden bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                    <div class="flex items-center justify-between gap-2 px-5 py-4 border-b border-hairline dark:border-gray-700">
                        <span class="font-mono text-sm font-semibold text-brand-green dark:text-brand-lime">{{ $tiket->ticket_no }}</span>
                        @if ($tiket->status)
                            <x-color-pill :color="$tiket->status_color" dot>{{ $tiket->status }}</x-color-pill>
                        @endif
                    </div>
                    <div class="p-5">
                        <h2 class="text-lg font-semibold text-ink dark:text-gray-100">{{ $tiket->title }}</h2>
                        <div class="flex flex-wrap gap-1.5 mt-2">
                            @if ($tiket->sector)
                                <span class="inline-flex items-center px-2.5 py-1 text-xs font-bold rounded-lg bg-brand-green/10 text-brand-green dark:bg-brand-lime/15 dark:text-brand-lime">{{ $tiket->sector }}</span>
                            @endif
                            @if ($tiket->category)
                                <span class="inline-flex items-center px-2 py-0.5 text-[11px] font-medium rounded-md bg-surface-soft text-muted dark:bg-gray-700 dark:text-gray-300">{{ $tiket->category }}</span>
                            @endif
                            @if ($tiket->priority)
                                <span class="inline-flex items-center px-2 py-0.5 text-[11px] font-medium rounded-md bg-surface-soft text-muted dark:bg-gray-700 dark:text-gray-300">Prioritas: {{ $tiket->priority }}</span>
                            @endif
                        </div>

                        <div class="grid grid-cols-2 gap-3 mt-4 sm:grid-cols-3">
                            <div class="px-3 py-2 rounded-xl border border-hairline bg-surface-soft dark:bg-gray-900 dark:border-gray-700">
                                <p class="text-[10px] uppercase tracking-wide text-muted-soft">Dilapor</p>
                                <p class="text-xs font-semibold text-ink dark:text-gray-200">{{ $tiket->reported_at ?: '—' }}</p>
                            </div>
                            <div class="px-3 py-2 rounded-xl border border-hairline bg-surface-soft dark:bg-gray-900 dark:border-gray-700">
                                <p class="text-[10px] uppercase tracking-wide text-muted-soft">Ditangani</p>
                                <p class="text-xs font-semibold text-ink dark:text-gray-200">{{ $tiket->technician ?: 'Belum ditugaskan' }}</p>
                            </div>
                            <div class="px-3 py-2 rounded-xl border border-hairline bg-surface-soft dark:bg-gray-900 dark:border-gray-700">
                                <p class="text-[10px] uppercase tracking-wide text-muted-soft">Selesai</p>
                                <p class="text-xs font-semibold {{ $tiket->is_closed ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted' }}">{{ $tiket->is_closed ? ($tiket->resolved_at ?: 'Ya') : 'Belum' }}</p>
                            </div>
                        </div>

                        @if ($tiket->is_closed && $tiket->resolution)
                            <div class="p-3 mt-4 border rounded-xl bg-success-tint border-success/30 dark:bg-green-900/20 dark:border-green-700">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-success-deep dark:text-green-300">Solusi / Tindakan</p>
                                <p class="mt-1 text-sm text-body dark:text-gray-200 whitespace-pre-line">{{ $tiket->resolution }}</p>
                            </div>
                        @endif
                    </div>

                    {{-- TIMELINE PROGRESS --}}
                    <div class="px-5 py-4 border-t border-hairline bg-surface-soft/60 dark:bg-gray-900/40 dark:border-gray-700">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-muted dark:text-gray-400">Perkembangan</p>
                        <ol class="relative pl-5 border-l-2 border-hairline dark:border-gray-700 space-y-4">
                            @forelse ($this->timeline as $aktivitas)
                                @php
                                    $label = match ($aktivitas->action) {
                                        'created'        => 'Tiket dibuat',
                                        'status_changed' => 'Status: ' . ($aktivitas->from_value ? $aktivitas->from_value . ' → ' : '') . $aktivitas->to_value,
                                        'assigned'       => $aktivitas->to_value ? 'Ditugaskan ke ' . $aktivitas->to_value : 'Penugasan diperbarui',
                                        default          => ucfirst($aktivitas->action),
                                    };
                                @endphp
                                <li class="relative">
                                    <span class="absolute -left-[27px] top-1 w-3 h-3 rounded-full bg-brand-green ring-2 ring-canvas dark:bg-brand-lime dark:ring-gray-800"></span>
                                    <p class="text-sm font-semibold text-ink dark:text-gray-100">{{ $label }}</p>
                                    @if ($aktivitas->note)
                                        <p class="text-xs text-muted dark:text-gray-400">{{ $aktivitas->note }}</p>
                                    @endif
                                    <p class="text-[11px] text-muted-soft">{{ $aktivitas->at }}</p>
                                </li>
                            @empty
                                <li class="relative">
                                    <span class="absolute -left-[27px] top-1 w-3 h-3 rounded-full bg-brand-green ring-2 ring-canvas dark:bg-brand-lime dark:ring-gray-800"></span>
                                    <p class="text-sm text-muted dark:text-gray-400">Laporan diterima, menunggu penanganan.</p>
                                </li>
                            @endforelse
                        </ol>
                    </div>
                </div>
            @else
                {{-- TIDAK DITEMUKAN --}}
                <div class="p-8 text-center bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                    <div class="inline-flex items-center justify-center w-14 h-14 mb-3 rounded-full bg-warning-tint text-warning-deep dark:bg-amber-900/30 dark:text-amber-300">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" /></svg>
                    </div>
                    <p class="font-semibold text-ink dark:text-gray-100">Nomor tiket tidak ditemukan</p>
                    <p class="mt-1 text-sm text-muted dark:text-gray-400">Periksa kembali nomor tiket Anda (contoh: <span class="font-mono">TIC-2026-0005</span>).</p>
                </div>
            @endif
        @endif

        <p class="mt-6 text-xs text-center text-muted-soft">
            Belum punya tiket? <a href="{{ route('lapor') }}" wire:navigate class="font-medium text-brand-green hover:underline dark:text-brand-lime">Lapor gangguan di sini</a>.
        </p>
    </div>

