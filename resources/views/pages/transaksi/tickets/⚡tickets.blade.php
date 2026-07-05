<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

new class extends Component {
    use WithPagination;

    public string $searchKeyword = '';
    public string $filterStatus  = '';
    public string $filterSector  = '';
    public int    $itemsPerPage  = 10;

    public function mount(): void
    {
        // Pelapor biasa diarahkan ke "Tiket Saya". Teknisi/admin menangani;
        // pengawas (manager/direktur) hanya melihat daftar (read-only).
        abort_unless(auth()->user()?->canViewAllTickets(), 403);
    }

    /** Handler = boleh merespon/menangani (teknisi/admin). */
    public function isHandler(): bool
    {
        return (bool) auth()->user()?->canHandleTickets();
    }

    /** Kelola = boleh buat/edit/hapus tiket. Admin saja. */
    public function canManage(): bool
    {
        return (bool) auth()->user()?->canManageTickets();
    }

    /** Teknisi murni → daftar di-scope ke sektornya. */
    public function isSectorTech(): bool
    {
        return (bool) auth()->user()?->isSectorTechnician();
    }

    /** Nama sektor user login (badge konteks teknisi). */
    public function mySectorName(): ?string
    {
        $sid = auth()->user()?->sector_id;
        return $sid ? DB::table('departments')->where('id', $sid)->value('name') : null;
    }

    #[Computed]
    public function sectors(): array
    {
        return DB::table('departments')->where('is_active', 1)->where('is_sector', 1)
            ->orderBy('name')->pluck('name', 'id')->all();
    }

    private function humanDur(int $s): string
    {
        if ($s < 60)   return $s . ' dtk';
        $m = intdiv($s, 60);
        if ($m < 60)   return $m . ' mnt';
        $h = intdiv($m, 60); $mm = $m % 60;
        if ($h < 24)   return $h . 'j' . ($mm ? ' ' . $mm . 'm' : '');
        $d = intdiv($h, 24); $hh = $h % 24;
        return $d . 'h' . ($hh ? ' ' . $hh . 'j' : '');
    }

    /** Lampirkan umur tiket + flag overdue (lewat SLA prioritas) ke tiap baris. */
    private function decorate($rows)
    {
        $rows->getCollection()->transform(function ($r) {
            $start = $r->rep_raw ? Carbon::parse($r->rep_raw) : null;
            $end   = ($r->is_closed && $r->res_raw) ? Carbon::parse($r->res_raw) : Carbon::now();
            $r->age_label = $start ? $this->humanDur((int) $start->diffInSeconds($end)) : '—';
            $r->overdue   = ($start && $r->sla_hours && !$r->is_closed)
                ? $start->diffInHours(Carbon::now()) > $r->sla_hours
                : false;
            return $r;
        });
        return $rows;
    }

    public function updatedSearchKeyword(): void { $this->resetPage(); }
    public function updatedItemsPerPage(): void  { $this->resetPage(); }
    public function updatedFilterStatus(): void   { $this->resetPage(); }
    public function updatedFilterSector(): void   { $this->resetPage(); }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterStatus', 'filterSector']);
        $this->itemsPerPage = 10;
        $this->resetPage();
    }

    public function openCreate(): void  { $this->dispatch('tickets.openCreate'); }
    public function openEdit(int $id): void { $this->dispatch('tickets.openEdit', id: $id); }
    public function openRespond(int $id): void { $this->dispatch('tickets.openRespond', id: $id); }
    public function requestDelete(int $id): void { $this->dispatch('tickets.requestDelete', id: $id); }

    #[On('tickets.saved')]
    public function refreshAfterSaved(): void { $this->resetPage(); }

    #[Computed]
    public function statusOptions(): array
    {
        return DB::table('ticket_statuses')->where('is_active', 1)
            ->orderBy('sort_order')->pluck('name', 'id')->all();
    }

    #[Computed]
    public function rows()
    {
        $q = DB::table('tickets as t')
            ->leftJoin('ticket_categories as c', 'c.id', '=', 't.category_id')
            ->leftJoin('priorities as p', 'p.id', '=', 't.priority_id')
            ->leftJoin('ticket_statuses as s', 's.id', '=', 't.status_id')
            ->leftJoin('locations as l', 'l.id', '=', 't.location_id')
            ->leftJoin('users as u', 'u.id', '=', 't.technician_id')
            ->leftJoin('departments as sec', 'sec.id', '=', 't.sector_id')
            ->select(
                't.id', 't.ticket_no', 't.title',
                'c.name as category',
                'sec.name as sector',
                'p.name as priority', 'p.color as priority_color',
                's.name as status', 's.color as status_color', 's.is_closed',
                'l.name as location', 't.location_detail',
                'u.name as technician',
                'p.sla_hours',
                DB::raw("TO_CHAR(t.reported_at, 'dd/mm/yyyy HH24:MI') as reported_at"),
                DB::raw("TO_CHAR(t.reported_at, 'YYYY-MM-DD HH24:MI:SS') as rep_raw"),
                DB::raw("TO_CHAR(t.resolved_at, 'YYYY-MM-DD HH24:MI:SS') as res_raw"),
                DB::raw('(SELECT COUNT(*) FROM ticket_comments tc WHERE tc.ticket_id = t.id) as comment_count')
            )
            ->orderByDesc('t.id');

        // Routing: teknisi murni hanya lihat tiket sektornya; admin/manager/direktur lihat semua.
        if ($this->isSectorTech()) {
            $q->where('t.sector_id', auth()->user()->sector_id);
        } elseif ($this->filterSector !== '') {
            $q->where('t.sector_id', (int) $this->filterSector);
        }

        if ($this->filterStatus !== '') {
            $q->where('t.status_id', $this->filterStatus);
        }

        if (trim($this->searchKeyword) !== '') {
            $kw = mb_strtoupper(trim($this->searchKeyword));
            $q->where(function ($w) use ($kw) {
                $w->whereRaw('UPPER(t.ticket_no) LIKE ?', ["%{$kw}%"])
                  ->orWhereRaw('UPPER(t.title) LIKE ?', ["%{$kw}%"]);
            });
        }

        return $this->decorate($q->paginate($this->itemsPerPage));
    }
};
?>

<div>

    <x-page-title
        title="Kelola Tiket"
        subtitle="Teknisi: respon tiket sektornya · Admin: kelola semua · Manager/Direktur: pantau" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="w-full lg:w-72">
                            <x-input-label for="searchKeyword" value="Cari Tiket" class="sr-only" />
                            <x-text-input id="searchKeyword" type="text"
                                wire:model.live.debounce.300ms="searchKeyword"
                                placeholder="Cari no. tiket / judul..."
                                class="block w-full" />
                        </div>
                        <div class="w-44">
                            <x-input-label for="filterStatus" value="Status" class="sr-only" />
                            <x-select-input id="filterStatus" wire:model.live="filterStatus">
                                <option value="">Semua Status</option>
                                @foreach ($this->statusOptions as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </x-select-input>
                        </div>
                        {{-- Sektor: teknisi terkunci ke sektornya (badge); lainnya bisa filter --}}
                        @if ($this->isSectorTech())
                            <div class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold bg-brand-green/10 text-brand-green dark:bg-brand-lime/15 dark:text-brand-lime">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" /></svg>
                                Sektor: {{ $this->mySectorName() ?? '—' }}
                            </div>
                        @else
                            <div class="w-44">
                                <x-input-label for="filterSector" value="Sektor" class="sr-only" />
                                <x-select-input id="filterSector" wire:model.live="filterSector">
                                    <option value="">Semua Sektor</option>
                                    @foreach ($this->sectors as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </x-select-input>
                            </div>
                        @endif
                    </div>

                    <div class="flex items-center justify-end gap-2">
                        <div class="w-28">
                            <x-input-label for="itemsPerPage" value="Per halaman" class="sr-only" />
                            <x-select-input id="itemsPerPage" wire:model.live="itemsPerPage">
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                            </x-select-input>
                        </div>
                        @if ($this->canManage())
                            <x-primary-button type="button" wire:click="openCreate">
                                + Tiket Baru
                            </x-primary-button>
                        @endif
                        <x-toolbar-refresh-reset :label="null" />
                    </div>
                </div>
            </div>

            {{-- CARD GRID (gaya tiket) --}}
            <div class="mt-4 flex flex-col flex-1 min-h-0">
                <div class="flex-1 min-h-0 overflow-y-auto pb-2 pr-1">
                    @if ($this->rows->count())
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 2xl:grid-cols-3">
                            @foreach ($this->rows as $row)
                                <x-ticket-card wire:key="tkt-{{ $row->id }}" :row="$row" show-location
                                    :editable="$this->canManage()" lockedNote=""
                                    deleteTitle="Hapus Tiket" />
                            @endforeach
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center gap-3 py-20">
                            <svg class="w-12 h-12 text-muted-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            <p class="text-base font-medium text-muted dark:text-gray-400">Belum ada tiket.</p>
                        </div>
                    @endif
                </div>
                <div class="pt-3 mt-auto">
                    {{ $this->rows->links() }}
                </div>
            </div>

            <livewire:pages::transaksi.tickets.tickets-actions wire:key="tickets-actions" />
            <livewire:pages::transaksi.tickets.tickets-respond wire:key="tickets-respond" />

        </div>
    </div>
</div>
