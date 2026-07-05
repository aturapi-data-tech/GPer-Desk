<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use App\Services\IncidentReportService;

new class extends Component {
    use WithPagination;

    public string $filterStatus   = '';
    public string $filterCategory = '';
    public string $searchKeyword  = '';
    public int    $itemsPerPage   = 10;
    public ?int   $detailId       = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['admin', 'manager']), 403);
    }

    public function updatedFilterStatus(): void   { $this->resetPage(); }
    public function updatedFilterCategory(): void { $this->resetPage(); }
    public function updatedSearchKeyword(): void  { $this->resetPage(); }

    #[Computed]
    public function stats(): array
    {
        $total   = DB::table('incident_reports')->count();
        $byStat  = DB::table('incident_reports')->select('status', DB::raw('COUNT(*) as c'))->groupBy('status')->pluck('c', 'status')->all();
        $byCat   = DB::table('incident_reports')->select('category', DB::raw('COUNT(*) as c'))->groupBy('category')->pluck('c', 'category')->all();
        $bySrc   = DB::table('incident_reports')->select('source', DB::raw('COUNT(*) as c'))->groupBy('source')->pluck('c', 'source')->all();
        return ['total' => $total, 'byStatus' => $byStat, 'byCategory' => $byCat, 'bySource' => $bySrc];
    }

    #[Computed]
    public function rows()
    {
        $query = DB::table('incident_reports')
            ->select('id', 'report_no', 'category', 'title', 'is_anonymous', 'status', 'source',
                DB::raw("TO_CHAR(created_at, 'dd/mm/yyyy HH24:MI') as tgl"))
            ->orderByDesc('id');

        if ($this->filterStatus !== '')   $query->where('status', $this->filterStatus);
        if ($this->filterCategory !== '') $query->where('category', $this->filterCategory);
        if (trim($this->searchKeyword) !== '') {
            $keyword = mb_strtoupper(trim($this->searchKeyword));
            $query->where(function ($subQuery) use ($keyword) {
                $subQuery->whereRaw('UPPER(report_no) LIKE ?', ["%{$keyword}%"])
                  ->orWhereRaw('UPPER(title) LIKE ?', ["%{$keyword}%"]);
            });
        }
        return $query->paginate($this->itemsPerPage);
    }

    #[Computed]
    public function detail()
    {
        return $this->detailId ? DB::table('incident_reports')->where('id', $this->detailId)->first() : null;
    }

    public function openDetail(int $id): void
    {
        $this->detailId = $id;
        $this->dispatch('open-modal', name: 'insiden-detail');
    }

    public function updateStatus(int $id, string $status): void
    {
        if (!in_array($status, IncidentReportService::STATUSES, true)) return;
        DB::table('incident_reports')->where('id', $id)->update(['status' => $status, 'updated_at' => now()]);
        $this->dispatch('toast', type: 'success', message: 'Status laporan diperbarui.');
    }

    public function requestDelete(int $id): void
    {
        DB::table('incident_reports')->where('id', $id)->delete();
        $this->dispatch('toast', type: 'success', message: 'Laporan dihapus.');
        $this->resetPage();
    }
};
?>

@php
    $catLabels = ['etika' => 'Etika & Perilaku', 'k3' => 'Keselamatan (K3)', 'kecurangan' => 'Kecurangan', 'layanan' => 'Layanan', 'lainnya' => 'Lainnya'];
    $statLabels = ['baru' => 'Baru', 'diproses' => 'Diproses', 'selesai' => 'Selesai', 'ditutup' => 'Ditutup'];
    $statVariant = ['baru' => 'info', 'diproses' => 'warning', 'selesai' => 'success', 'ditutup' => 'gray'];
@endphp

<div>
    <x-page-title title="Pengaduan & Aspirasi" subtitle="Rekap & evaluasi pengaduan karyawan (web + API bridging)" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- EVALUASI --}}
            @php $statistik = $this->stats; @endphp
            <div class="grid grid-cols-2 gap-3 mt-2 md:grid-cols-4">
                <x-stat-tile label="Total laporan" :value="$statistik['total']" accent="#c64545" />
                <x-stat-tile label="Baru" :value="$statistik['byStatus']['baru'] ?? 0" accent="#2e7da6" />
                <x-stat-tile label="Diproses" :value="$statistik['byStatus']['diproses'] ?? 0" accent="#d4a017" />
                <x-stat-tile label="Selesai" :value="$statistik['byStatus']['selesai'] ?? 0" accent="#3fae6a" />
            </div>
            <div class="flex flex-wrap gap-2 mt-3 text-xs">
                <span class="text-muted dark:text-gray-400">Per kategori:</span>
                @foreach ($catLabels as $k => $lbl)
                    <span class="px-2 py-0.5 rounded-md bg-canvas border border-hairline dark:bg-gray-800 dark:border-gray-700">{{ $lbl }}: <strong>{{ $statistik['byCategory'][$k] ?? 0 }}</strong></span>
                @endforeach
                <span class="ml-2 text-muted dark:text-gray-400">Sumber:</span>
                <span class="px-2 py-0.5 rounded-md bg-canvas border border-hairline dark:bg-gray-800 dark:border-gray-700">Web: <strong>{{ $statistik['bySource']['web'] ?? 0 }}</strong></span>
                <span class="px-2 py-0.5 rounded-md bg-canvas border border-hairline dark:bg-gray-800 dark:border-gray-700">API: <strong>{{ $statistik['bySource']['api'] ?? 0 }}</strong></span>
            </div>

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 mt-4 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="w-full lg:w-64">
                            <x-text-input wire:model.live.debounce.300ms="searchKeyword" placeholder="Cari no. / judul..." class="block w-full" />
                        </div>
                        <div class="w-40">
                            <x-select-input wire:model.live="filterStatus">
                                <option value="">Semua Status</option>
                                @foreach ($statLabels as $k => $lbl) <option value="{{ $k }}">{{ $lbl }}</option> @endforeach
                            </x-select-input>
                        </div>
                        <div class="w-40">
                            <x-select-input wire:model.live="filterCategory">
                                <option value="">Semua Kategori</option>
                                @foreach ($catLabels as $k => $lbl) <option value="{{ $k }}">{{ $lbl }}</option> @endforeach
                            </x-select-input>
                        </div>
                    </div>
                    <a href="{{ route('insiden.publik') }}" target="_blank" class="text-sm font-medium text-brand-green hover:underline dark:text-brand-lime">Buka form pelaporan publik ↗</a>
                </div>
            </div>

            {{-- TABEL --}}
            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="ds-table">
                        <thead class="sticky top-0 z-10">
                            <tr>
                                <th>No. Laporan</th>
                                <th>Kategori</th>
                                <th>Judul</th>
                                <th class="ds-c">Sumber</th>
                                <th class="ds-c">Status</th>
                                <th>Tanggal</th>
                                <th class="ds-c">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- $row = satu baris laporan pengaduan (item koleksi $this->rows) — idiom baku repo untuk item tabel. --}}
                            @forelse ($this->rows as $row)
                                <tr wire:key="inc-{{ $row->id }}">
                                    <td class="ds-td-token">{{ $row->report_no }}</td>
                                    <td>{{ $catLabels[$row->category] ?? $row->category }}</td>
                                    <td class="max-w-xs truncate ds-td-strong">{{ $row->title }} @if ($row->is_anonymous)<span class="ml-1 text-[10px] italic text-muted-soft">(anonim)</span>@endif</td>
                                    <td class="ds-c"><x-badge :variant="$row->source === 'api' ? 'purple' : 'gray'">{{ strtoupper($row->source) }}</x-badge></td>
                                    <td class="ds-c">
                                        <x-select-input wire:change="updateStatus({{ $row->id }}, $event.target.value)" class="text-xs">
                                            @foreach ($statLabels as $k => $lbl)
                                                <option value="{{ $k }}" @selected($row->status === $k)>{{ $lbl }}</option>
                                            @endforeach
                                        </x-select-input>
                                    </td>
                                    <td class="text-xs text-muted">{{ $row->tgl }}</td>
                                    <td class="ds-c">
                                        <div class="flex items-center justify-center gap-2">
                                            <x-outline-button type="button" wire:click="openDetail({{ $row->id }})">Detail</x-outline-button>
                                            <x-action-delete :action="'requestDelete(' . $row->id . ')'" title="Hapus Laporan" message="Hapus laporan {{ $row->report_no }}?" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-6 py-10 text-center text-muted dark:text-gray-400">Belum ada laporan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>
        </div>
    </div>

    {{-- DETAIL --}}
    <x-modal name="insiden-detail" size="lg" focusable>
        @if ($this->detail)
            @php $detail = $this->detail; @endphp
            <div class="p-1">
                <div class="flex items-center justify-between gap-2 pb-3 mb-3 border-b border-hairline dark:border-gray-700">
                    <span class="font-mono text-sm font-semibold text-brand-green dark:text-brand-lime">{{ $detail->report_no }}</span>
                    <x-badge :variant="$statVariant[$detail->status] ?? 'gray'">{{ $statLabels[$detail->status] ?? $detail->status }}</x-badge>
                </div>
                <h3 class="text-lg font-semibold text-ink dark:text-gray-100">{{ $detail->title }}</h3>
                <div class="flex flex-wrap gap-2 mt-2 text-xs">
                    <span class="px-2 py-0.5 rounded-md bg-surface-soft dark:bg-gray-700">{{ $catLabels[$detail->category] ?? $detail->category }}</span>
                    <span class="px-2 py-0.5 rounded-md bg-surface-soft dark:bg-gray-700">Sumber: {{ strtoupper($detail->source) }}</span>
                    @if ($detail->incident_date)<span class="px-2 py-0.5 rounded-md bg-surface-soft dark:bg-gray-700">Kejadian: {{ \Illuminate\Support\Carbon::parse($detail->incident_date)->format('d/m/Y') }}</span>@endif
                    @if ($detail->location)<span class="px-2 py-0.5 rounded-md bg-surface-soft dark:bg-gray-700">📍 {{ $detail->location }}</span>@endif
                </div>
                <p class="p-3 mt-3 text-sm rounded-lg bg-surface-soft text-body dark:bg-gray-900 dark:text-gray-200 whitespace-pre-line">{{ $detail->description }}</p>
                <div class="mt-3 text-xs text-muted dark:text-gray-400">
                    <p>Pelapor: <strong>{{ $detail->is_anonymous ? 'Anonim' : ($detail->reporter_name ?: '—') }}</strong> @if (!$detail->is_anonymous && $detail->reporter_contact) · {{ $detail->reporter_contact }} @endif</p>
                </div>
                <div class="flex justify-end mt-4">
                    <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'insiden-detail' })">Tutup</x-secondary-button>
                </div>
            </div>
        @endif
    </x-modal>
</div>
