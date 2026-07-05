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
    public int    $itemsPerPage  = 10;

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

    public function updatedSearchKeyword(): void { $this->resetPage(); }
    public function updatedItemsPerPage(): void  { $this->resetPage(); }
    public function updatedFilterStatus(): void  { $this->resetPage(); }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterStatus']);
        $this->itemsPerPage = 10;
        $this->resetPage();
    }

    public function openCreate(): void  { $this->dispatch('my-tickets.openCreate'); }
    public function openEdit(int $id): void { $this->dispatch('my-tickets.openEdit', id: $id); }
    public function openRespond(int $id): void { $this->dispatch('tickets.openRespond', id: $id); }
    public function requestDelete(int $id): void { $this->dispatch('my-tickets.requestDelete', id: $id); }

    #[On('my-tickets.saved')]
    #[On('tickets.saved')]
    public function refreshAfterSaved(): void { $this->resetPage(); }

    /** ID status awal (Open) — pelapor hanya boleh edit/batalkan saat tiket masih di status ini. */
    #[Computed]
    public function openStatusId(): ?int
    {
        return DB::table('ticket_statuses')->where('is_active', 1)->orderBy('sort_order')->value('id');
    }

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
            ->leftJoin('users as u', 'u.id', '=', 't.technician_id')
            ->leftJoin('departments as sec', 'sec.id', '=', 't.sector_id')
            ->where('t.reported_by', auth()->id())
            ->select(
                't.id', 't.ticket_no', 't.title', 't.status_id',
                'c.name as category',
                'sec.name as sector',
                'p.name as priority', 'p.color as priority_color',
                's.name as status', 's.color as status_color', 's.is_closed',
                'u.name as technician',
                'p.sla_hours',
                DB::raw("TO_CHAR(t.reported_at, 'dd/mm/yyyy HH24:MI') as reported_at"),
                DB::raw("TO_CHAR(t.reported_at, 'YYYY-MM-DD HH24:MI:SS') as rep_raw"),
                DB::raw("TO_CHAR(t.resolved_at, 'YYYY-MM-DD HH24:MI:SS') as res_raw"),
                DB::raw('(SELECT COUNT(*) FROM ticket_comments tc WHERE tc.ticket_id = t.id) as comment_count')
            )
            ->orderByDesc('t.id');

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

        $rows = $q->paginate($this->itemsPerPage);
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
};
?>

<div>

    <x-page-title
        title="Tiket Saya"
        subtitle="Lapor gangguan & pantau status tiket yang Anda kirim" />

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
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <div class="w-28">
                            <x-input-label for="itemsPerPage" value="Per halaman" class="sr-only" />
                            <x-select-input id="itemsPerPage" wire:model.live="itemsPerPage">
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="25">25</option>
                            </x-select-input>
                        </div>
                        <x-primary-button type="button" wire:click="openCreate">
                            + Lapor Tiket
                        </x-primary-button>
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
                                <x-ticket-card wire:key="mtkt-{{ $row->id }}" :row="$row"
                                    :editable="false" lockedNote="" />
                            @endforeach
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center gap-3 py-20">
                            <svg class="w-12 h-12 text-muted-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            <p class="text-base font-medium text-muted dark:text-gray-400">Anda belum punya tiket. Klik "Lapor Tiket".</p>
                        </div>
                    @endif
                </div>
                <div class="pt-3 mt-auto">
                    {{ $this->rows->links() }}
                </div>
            </div>

            <livewire:pages::transaksi.my-tickets.my-tickets-actions wire:key="my-tickets-actions" />
            <livewire:pages::transaksi.tickets.tickets-respond wire:key="tickets-respond" />

        </div>
    </div>
</div>
