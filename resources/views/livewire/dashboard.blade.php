<?php

use App\Services\AppMenu;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public string $search = '';

    /** Pengawas & handler (admin/manager/teknisi/direktur) lihat semua tiket; pelapor hanya miliknya. */
    private function canViewAll(): bool
    {
        return (bool) auth()->user()?->canViewAllTickets();
    }

    /** Query dasar tiket + join status/prioritas, sudah ter-scope sesuai role. */
    private function ticketQuery()
    {
        return DB::table('tickets as t')
            ->leftJoin('ticket_statuses as s', 's.id', '=', 't.status_id')
            ->leftJoin('priorities as p', 'p.id', '=', 't.priority_id')
            ->when(!$this->canViewAll(), fn ($q) => $q->where('t.reported_by', auth()->id()));
    }

    #[Computed]
    public function ticketStats(): array
    {
        return [
            'total'   => (clone $this->ticketQuery())->count(),
            'open'    => (clone $this->ticketQuery())->where('s.is_closed', 0)->count(),
            'closed'  => (clone $this->ticketQuery())->where('s.is_closed', 1)->count(),
            'noresp'  => (clone $this->ticketQuery())->where('s.is_closed', 0)->whereNull('t.first_responded_at')->count(),
            'overdue' => (clone $this->ticketQuery())->where('s.is_closed', 0)
                ->whereNotNull('p.sla_hours')
                ->whereRaw("t.reported_at + NUMTODSINTERVAL(p.sla_hours, 'HOUR') < SYSTIMESTAMP")
                ->count(),
        ];
    }

    /** Rincian jumlah tiket per status (utk donut + legend). */
    #[Computed]
    public function statusBreakdown()
    {
        return DB::table('tickets as t')
            ->join('ticket_statuses as s', 's.id', '=', 't.status_id')
            ->when(!$this->canViewAll(), fn ($q) => $q->where('t.reported_by', auth()->id()))
            ->groupBy('s.name', 's.color', 's.sort_order')
            ->orderBy('s.sort_order')
            ->select('s.name', 's.color', DB::raw('COUNT(*) as cnt'))
            ->get();
    }

    /** 5 tiket terbaru yang belum selesai (menunggu penanganan/respon). */
    #[Computed]
    public function waitingTickets()
    {
        return DB::table('tickets as t')
            ->leftJoin('ticket_statuses as s', 's.id', '=', 't.status_id')
            ->leftJoin('priorities as p', 'p.id', '=', 't.priority_id')
            ->where('s.is_closed', 0)
            ->when(!$this->canViewAll(), fn ($q) => $q->where('t.reported_by', auth()->id()))
            ->orderByDesc('t.id')
            ->select(
                't.ticket_no', 't.title',
                's.name as status', 's.color as status_color',
                'p.color as priority_color',
                't.first_responded_at',
                DB::raw("TO_CHAR(t.reported_at, 'dd/mm HH24:MI') as reported_at")
            )
            ->limit(5)->get();
    }

    // ✅ Semua role user (lowercase) dipakai untuk filtering menu
    #[Computed]
    public function userRoles(): array
    {
        return auth()->user()->getRoleNames()->map(fn($r) => trim(strtolower($r)))->values()->toArray();
    }

    #[Computed]
    public function masterMenus(): array
    {
        // Definisi menu dipusatkan di App\Services\AppMenu (dipakai dashboard + sidebar).
        return AppMenu::all();
    }

    #[Computed]
    public function visibleMenus(): array
    {
        return AppMenu::forRoles($this->userRoles);
    }

    #[Computed]
    public function groupedMenus()
    {
        $grouped = AppMenu::grouped($this->userRoles);

        $q = trim(mb_strtolower($this->search));
        if ($q === '') {
            return $grouped;
        }

        // Cari di title / desc / badge / nama group. Group dengan 0 match auto-hidden.
        return $grouped
            ->map(fn($items, $groupName) => $items->filter(function ($item) use ($q, $groupName) {
                $haystack = mb_strtolower(($item['title'] ?? '') . ' ' . ($item['desc'] ?? '') . ' ' . ($item['badge'] ?? '') . ' ' . $groupName);
                return str_contains($haystack, $q);
            }))
            ->filter(fn($items) => $items->isNotEmpty());
    }
};
?>

<div>

    {{-- Judul di topbar (sebelah logo) — pola master --}}
    <x-page-title
        title="Dashboard"
        subtitle="Pusat menu aplikasi — Role Aktif : {{ auth()->user()->getRoleNames()->implode(', ') }}" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR (persis master poli: sticky py-3) --}}
            <div
                class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    {{-- SEARCH --}}
                    <div class="w-full lg:max-w-md">
                        <x-input-label for="search" value="Cari Menu" class="sr-only" />
                        <x-text-input id="search" wire:model.live.debounce.250ms="search" placeholder="Cari menu..."
                            class="block w-full" />
                    </div>
                    <div class="hidden lg:block"></div>
                </div>
            </div>

            {{-- GRID MENU — Accordion (area scroll, jarak mt-4 spt kartu master) --}}
            <div class="flex-1 min-h-0 mt-4 overflow-y-auto" x-data="{ activeGroup: null }">

                {{-- ===== RINGKASAN TIKET (sembunyi saat mencari menu) ===== --}}
                @if (trim($search) === '')
                    @php
                        $stats   = $this->ticketStats;
                        $bd      = $this->statusBreakdown;
                        $bdTotal = $bd->sum('cnt');
                        $deg = 0; $stops = [];
                        foreach ($bd as $b) {
                            if (!$b->cnt) { continue; }
                            $end     = $deg + ($b->cnt / $bdTotal) * 360;
                            $stops[] = ($b->color ?: '#9ca3af') . ' ' . round($deg, 2) . 'deg ' . round($end, 2) . 'deg';
                            $deg     = $end;
                        }
                        $donut = $bdTotal ? 'conic-gradient(' . implode(',', $stops) . ')' : '';
                    @endphp

                    {{-- eyebrow --}}
                    <div class="flex items-center gap-3 mb-3">
                        <h2 class="text-xs font-semibold uppercase tracking-[0.12em] whitespace-nowrap text-muted dark:text-gray-500">Ringkasan Tiket</h2>
                        <div class="flex items-center flex-1 gap-2">
                            <span class="w-7 h-[3px] rounded-full bg-brand-lime shrink-0"></span>
                            <span class="flex-1 h-px bg-hairline dark:bg-gray-700"></span>
                        </div>
                    </div>

                    {{-- KPI tiles --}}
                    <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                        <x-stat-tile label="Total tiket" :value="$stats['total']" accent="#157547">
                            <x-slot:icon><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 00-2.15-1.588H6.911a2.25 2.25 0 00-2.15 1.588L2.35 13.177a2.25 2.25 0 00-.1.661z" /></svg></x-slot:icon>
                        </x-stat-tile>
                        <x-stat-tile label="Belum selesai" :value="$stats['open']" accent="#d4a017"
                            :hint="$stats['noresp'] ? $stats['noresp'] . ' belum direspon' : null">
                            <x-slot:icon><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg></x-slot:icon>
                        </x-stat-tile>
                        <x-stat-tile label="Overdue SLA" :value="$stats['overdue']" accent="#c64545">
                            <x-slot:icon><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg></x-slot:icon>
                        </x-stat-tile>
                        <x-stat-tile label="Selesai" :value="$stats['closed']" accent="#3fae6a">
                            <x-slot:icon><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg></x-slot:icon>
                        </x-stat-tile>
                    </div>

                    {{-- donut distribusi + daftar menunggu --}}
                    <div class="grid grid-cols-1 gap-4 mt-4 lg:grid-cols-2">
                        <x-section-card title="Distribusi Status">
                            @if ($bdTotal)
                                <div class="flex items-center gap-5">
                                    <div class="relative shrink-0 h-32 w-32 rounded-full" style="background: {{ $donut }}">
                                        <div class="absolute inset-[14px] grid rounded-full place-items-center bg-canvas dark:bg-gray-800">
                                            <div class="text-center">
                                                <p class="text-2xl font-bold text-ink dark:text-gray-100">{{ $bdTotal }}</p>
                                                <p class="text-[10px] uppercase tracking-wide text-muted-soft">Total</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0 space-y-2">
                                        @foreach ($bd as $b)
                                            <div class="flex items-center gap-2 text-sm">
                                                <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background: {{ $b->color ?: '#9ca3af' }}"></span>
                                                <span class="flex-1 truncate text-body dark:text-gray-300">{{ $b->name }}</span>
                                                <span class="font-semibold text-ink dark:text-gray-100">{{ $b->cnt }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <p class="py-8 text-sm text-center text-muted dark:text-gray-400">Belum ada data tiket.</p>
                            @endif
                        </x-section-card>

                        <x-section-card title="Menunggu Respon / Penanganan"
                            :subtitle="$stats['open'] . ' tiket belum selesai'" body-class="p-3 space-y-2">
                            @forelse ($this->waitingTickets as $w)
                                <x-list-row :title="$w->ticket_no . ' · ' . $w->title"
                                    :meta="($w->first_responded_at ? 'Sudah direspon' : 'Belum direspon') . ' · Dilapor ' . $w->reported_at">
                                    <x-slot:leading>
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859M12 3v8.25m0 0l-3-3m3 3l3-3" /></svg>
                                    </x-slot:leading>
                                    <x-slot:trailing>
                                        <x-color-pill :color="$w->status_color" dot>{{ $w->status }}</x-color-pill>
                                    </x-slot:trailing>
                                </x-list-row>
                            @empty
                                <p class="py-8 text-sm text-center text-muted dark:text-gray-400">Tidak ada tiket menunggu. 🎉</p>
                            @endforelse
                        </x-section-card>
                    </div>

                    {{-- pemisah sebelum menu --}}
                    <div class="mt-8 mb-2 border-t border-hairline dark:border-gray-700"></div>
                @endif

                @forelse ($this->groupedMenus as $groupName => $menus)
                    <div x-data="{ group: '{{ $groupName }}' }">

                        {{-- GROUP HEADER (eyebrow) --}}
                        <button type="button" @click="activeGroup = (activeGroup === group) ? null : group"
                            class="flex items-center w-full gap-3 mt-6 mb-3 group/header">
                            <h2 class="text-xs font-semibold uppercase tracking-[0.12em] whitespace-nowrap transition-colors
                                       text-muted group-hover/header:text-ink dark:text-gray-500 dark:group-hover/header:text-gray-300"
                                :class="activeGroup === group ? 'text-brand-green dark:text-brand-lime' : ''">
                                {{ $groupName }}
                            </h2>
                            {{-- Ornamen garis lime + hairline (Graphic Standard Manual) --}}
                            <div class="flex items-center flex-1 gap-2">
                                <span class="w-7 h-[3px] rounded-full bg-brand-lime shrink-0 transition-all duration-500 ease-out group-hover/header:w-24"></span>
                                <span class="flex-1 h-px bg-hairline dark:bg-gray-700"></span>
                            </div>
                            <svg class="w-4 h-4 transition-transform duration-200 shrink-0 text-muted"
                                :class="activeGroup === group ? 'rotate-0 text-brand-green dark:text-brand-lime' : '-rotate-90'"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        {{-- GRID KARTU --}}
                        <div x-show="activeGroup === group" x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 -translate-y-2"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 -translate-y-2"
                            class="grid grid-cols-1 gap-4 mb-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">

                            @foreach ($menus as $m)
                                <a href="{{ $m['href'] }}" wire:navigate
                                    class="relative block p-4 pt-5 overflow-hidden transition-all duration-200 border group bg-surface-elevated border-hairline rounded-xl shadow-sm hover:border-brand-green/40 hover:shadow-md hover:-translate-y-0.5 dark:bg-gray-800/40 dark:border-gray-700 dark:hover:border-brand-lime/40">
                                    {{-- Ornamen aksen lime (Graphic Standard Manual) — memanjang saat hover --}}
                                    <span class="absolute top-0 left-0 h-1 transition-all duration-500 ease-out rounded-b w-9 bg-brand-lime group-hover:w-full"></span>
                                    <div class="flex items-start justify-between gap-2">
                                        <h3 class="text-base font-semibold leading-snug transition-colors text-ink group-hover:text-brand-green dark:text-gray-100 dark:group-hover:text-brand-lime">
                                            {{ $m['title'] }}
                                        </h3>
                                        @if (!empty($m['badge']))
                                            <span class="shrink-0 inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full bg-brand-green/10 text-brand-green dark:bg-brand-lime/15 dark:text-brand-lime">
                                                {{ $m['badge'] }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-sm leading-snug line-clamp-2 text-muted dark:text-gray-400">
                                        {{ $m['desc'] }}
                                    </p>
                                </a>
                            @endforeach

                        </div>
                    </div>

                @empty
                    <div class="py-10 text-sm text-center text-muted dark:text-gray-400">
                        Menu tidak ditemukan / tidak ada akses.
                    </div>
                @endforelse

            </div>

        </div>
    </div>
</div>
