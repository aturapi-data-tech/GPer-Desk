<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use App\Services\PatientSafetyIncidentService;

new class extends Component {
    use WithPagination;

    public string $filterStatus  = '';
    public string $filterType    = '';
    public string $filterGrading = '';
    public string $searchKeyword = '';
    public int    $itemsPerPage  = 10;
    public ?int   $detailId      = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['admin', 'manager']), 403);
    }

    public function updatedFilterStatus(): void  { $this->resetPage(); }
    public function updatedFilterType(): void    { $this->resetPage(); }
    public function updatedFilterGrading(): void { $this->resetPage(); }
    public function updatedSearchKeyword(): void { $this->resetPage(); }

    #[Computed]
    public function stats(): array
    {
        $total  = DB::table('patient_safety_incidents')->count();
        $byStat = DB::table('patient_safety_incidents')->select('status', DB::raw('COUNT(*) as c'))->groupBy('status')->pluck('c', 'status')->all();
        $byType = DB::table('patient_safety_incidents')->select('incident_type', DB::raw('COUNT(*) as c'))->groupBy('incident_type')->pluck('c', 'incident_type')->all();
        $byGrad = DB::table('patient_safety_incidents')->select('grading', DB::raw('COUNT(*) as c'))->groupBy('grading')->pluck('c', 'grading')->all();
        return ['total' => $total, 'byStatus' => $byStat, 'byType' => $byType, 'byGrading' => $byGrad];
    }

    #[Computed]
    public function rows()
    {
        $query = DB::table('patient_safety_incidents')
            ->select('id', 'report_no', 'incident_type', 'title', 'grading', 'is_anonymous', 'status', 'source',
                DB::raw("TO_CHAR(created_at, 'dd/mm/yyyy HH24:MI') as tgl"))
            ->orderByDesc('id');

        if ($this->filterStatus !== '')  $query->where('status', $this->filterStatus);
        if ($this->filterType !== '')    $query->where('incident_type', $this->filterType);
        if ($this->filterGrading !== '') $query->where('grading', $this->filterGrading);
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
        return $this->detailId ? DB::table('patient_safety_incidents')->where('id', $this->detailId)->first() : null;
    }

    public function openDetail(int $id): void
    {
        $this->detailId = $id;
        $this->dispatch('open-modal', name: 'ikp-detail');
    }

    public function updateStatus(int $id, string $status): void
    {
        if (!in_array($status, PatientSafetyIncidentService::STATUSES, true)) return;
        DB::table('patient_safety_incidents')->where('id', $id)->update(['status' => $status, 'updated_at' => now()]);
        $this->dispatch('toast', type: 'success', message: 'Status laporan diperbarui.');
    }

    public function updateGrading(int $id, string $grading): void
    {
        if ($grading !== '' && !in_array($grading, PatientSafetyIncidentService::GRADINGS, true)) return;
        DB::table('patient_safety_incidents')->where('id', $id)->update(['grading' => $grading ?: null, 'updated_at' => now()]);
        $this->dispatch('toast', type: 'success', message: 'Grading risiko diperbarui.');
    }

    public function requestDelete(int $id): void
    {
        DB::table('patient_safety_incidents')->where('id', $id)->delete();
        $this->dispatch('toast', type: 'success', message: 'Laporan dihapus.');
        $this->resetPage();
    }
};
?>

@php
    $typeLabels = ['kpc' => 'KPC', 'knc' => 'KNC', 'ktc' => 'KTC', 'ktd' => 'KTD', 'sentinel' => 'Sentinel'];
    $typeFull   = [
        'kpc' => 'Kondisi Potensial Cedera', 'knc' => 'Kejadian Nyaris Cedera', 'ktc' => 'Kejadian Tidak Cedera',
        'ktd' => 'Kejadian Tidak Diharapkan', 'sentinel' => 'Kejadian Sentinel',
    ];
    $statLabels  = ['baru' => 'Baru', 'investigasi' => 'Investigasi', 'selesai' => 'Selesai', 'ditutup' => 'Ditutup'];
    $statVariant = ['baru' => 'info', 'investigasi' => 'warning', 'selesai' => 'success', 'ditutup' => 'gray'];
    $gradLabels  = ['biru' => 'Biru', 'hijau' => 'Hijau', 'kuning' => 'Kuning', 'merah' => 'Merah'];
    $gradClass   = [
        'biru'   => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
        'hijau'  => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        'kuning' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300',
        'merah'  => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    ];
@endphp

<div>
    <x-page-title title="Insiden Keselamatan Pasien (IKP)" subtitle="Rekap, grading & tindak lanjut insiden keselamatan pasien (web + API bridging)" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- EVALUASI --}}
            @php $statistik = $this->stats; @endphp
            <div class="grid grid-cols-2 gap-3 mt-2 md:grid-cols-4">
                <x-stat-tile label="Total laporan" :value="$statistik['total']" accent="#c64545" />
                <x-stat-tile label="Baru" :value="$statistik['byStatus']['baru'] ?? 0" accent="#2e7da6" />
                <x-stat-tile label="Investigasi" :value="$statistik['byStatus']['investigasi'] ?? 0" accent="#d4a017" />
                <x-stat-tile label="Selesai" :value="$statistik['byStatus']['selesai'] ?? 0" accent="#3fae6a" />
            </div>
            <div class="flex flex-wrap gap-2 mt-3 text-xs">
                <span class="text-muted dark:text-gray-400">Jenis:</span>
                @foreach ($typeLabels as $k => $lbl)
                    <span class="px-2 py-0.5 rounded-md bg-canvas border border-hairline dark:bg-gray-800 dark:border-gray-700">{{ $lbl }}: <strong>{{ $statistik['byType'][$k] ?? 0 }}</strong></span>
                @endforeach
                <span class="ml-2 text-muted dark:text-gray-400">Grading:</span>
                @foreach ($gradLabels as $k => $lbl)
                    <span class="px-2 py-0.5 rounded-md {{ $gradClass[$k] }}">{{ $lbl }}: <strong>{{ $statistik['byGrading'][$k] ?? 0 }}</strong></span>
                @endforeach
                <span class="px-2 py-0.5 rounded-md bg-canvas border border-hairline dark:bg-gray-800 dark:border-gray-700">Belum digrading: <strong>{{ $statistik['byGrading'][''] ?? ($statistik['byGrading'][null] ?? 0) }}</strong></span>
            </div>

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 mt-4 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="w-full lg:w-56">
                            <x-text-input wire:model.live.debounce.300ms="searchKeyword" placeholder="Cari no. / judul..." class="block w-full" />
                        </div>
                        <div class="w-36">
                            <x-select-input wire:model.live="filterStatus">
                                <option value="">Semua Status</option>
                                @foreach ($statLabels as $k => $lbl) <option value="{{ $k }}">{{ $lbl }}</option> @endforeach
                            </x-select-input>
                        </div>
                        <div class="w-32">
                            <x-select-input wire:model.live="filterType">
                                <option value="">Semua Jenis</option>
                                @foreach ($typeLabels as $k => $lbl) <option value="{{ $k }}">{{ $lbl }}</option> @endforeach
                            </x-select-input>
                        </div>
                        <div class="w-36">
                            <x-select-input wire:model.live="filterGrading">
                                <option value="">Semua Grading</option>
                                @foreach ($gradLabels as $k => $lbl) <option value="{{ $k }}">{{ $lbl }}</option> @endforeach
                            </x-select-input>
                        </div>
                    </div>
                    <a href="{{ route('ikp.publik') }}" target="_blank" class="text-sm font-medium text-brand-green hover:underline dark:text-brand-lime">Buka form pelaporan publik ↗</a>
                </div>
            </div>

            {{-- TABEL --}}
            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="ds-table">
                        <thead class="sticky top-0 z-10">
                            <tr>
                                <th>No. Laporan</th>
                                <th class="ds-c">Jenis</th>
                                <th>Judul</th>
                                <th class="ds-c">Grading</th>
                                <th class="ds-c">Sumber</th>
                                <th class="ds-c">Status</th>
                                <th>Tanggal</th>
                                <th class="ds-c">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- $row = satu baris laporan IKP (item koleksi $this->rows) — idiom baku repo untuk item tabel. --}}
                            @forelse ($this->rows as $row)
                                <tr wire:key="ikp-{{ $row->id }}">
                                    <td class="ds-td-token">{{ $row->report_no }}</td>
                                    <td class="ds-c"><x-badge variant="gray" title="{{ $typeFull[$row->incident_type] ?? '' }}">{{ $typeLabels[$row->incident_type] ?? strtoupper($row->incident_type) }}</x-badge></td>
                                    <td class="max-w-xs truncate ds-td-strong">{{ $row->title }} @if ($row->is_anonymous)<span class="ml-1 text-[10px] italic text-muted-soft">(anonim)</span>@endif</td>
                                    <td class="ds-c">
                                        <x-select-input wire:change="updateGrading({{ $row->id }}, $event.target.value)" class="text-xs">
                                            <option value="" @selected(!$row->grading)>—</option>
                                            @foreach ($gradLabels as $k => $lbl)
                                                <option value="{{ $k }}" @selected($row->grading === $k)>{{ $lbl }}</option>
                                            @endforeach
                                        </x-select-input>
                                    </td>
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
                                <tr><td colspan="8" class="px-6 py-10 text-center text-muted dark:text-gray-400">Belum ada laporan insiden.</td></tr>
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
    <x-modal name="ikp-detail" size="lg" focusable>
        @if ($this->detail)
            @php $detail = $this->detail; @endphp
            <div class="p-1">
                <div class="flex items-center justify-between gap-2 pb-3 mb-3 border-b border-hairline dark:border-gray-700">
                    <span class="font-mono text-sm font-semibold text-brand-green dark:text-brand-lime">{{ $detail->report_no }}</span>
                    <div class="flex items-center gap-2">
                        @if ($detail->grading)<span class="px-2 py-0.5 text-xs rounded-md {{ $gradClass[$detail->grading] ?? '' }}">Grading: {{ $gradLabels[$detail->grading] ?? $detail->grading }}</span>@endif
                        <x-badge :variant="$statVariant[$detail->status] ?? 'gray'">{{ $statLabels[$detail->status] ?? $detail->status }}</x-badge>
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-ink dark:text-gray-100">{{ $detail->title }}</h3>
                <div class="flex flex-wrap gap-2 mt-2 text-xs">
                    <span class="px-2 py-0.5 rounded-md bg-surface-soft dark:bg-gray-700">{{ $typeFull[$detail->incident_type] ?? $detail->incident_type }} ({{ strtoupper($detail->incident_type) }})</span>
                    <span class="px-2 py-0.5 rounded-md bg-surface-soft dark:bg-gray-700">Sumber: {{ strtoupper($detail->source) }}</span>
                    @if ($detail->incident_date)<span class="px-2 py-0.5 rounded-md bg-surface-soft dark:bg-gray-700">Kejadian: {{ \Illuminate\Support\Carbon::parse($detail->incident_date)->format('d/m/Y') }}@if ($detail->incident_time) {{ $detail->incident_time }}@endif</span>@endif
                    @if ($detail->location)<span class="px-2 py-0.5 rounded-md bg-surface-soft dark:bg-gray-700">📍 {{ $detail->location }}</span>@endif
                    @if ($detail->patient_ref)<span class="px-2 py-0.5 rounded-md bg-surface-soft dark:bg-gray-700">Pasien: {{ $detail->patient_ref }}</span>@endif
                </div>

                <div class="mt-3 space-y-3">
                    <div>
                        <p class="text-xs font-semibold text-muted dark:text-gray-400">Kronologi</p>
                        <p class="p-3 mt-1 text-sm rounded-lg bg-surface-soft text-body dark:bg-gray-900 dark:text-gray-200 whitespace-pre-line">{{ $detail->chronology }}</p>
                    </div>
                    @if ($detail->patient_impact)
                        <div>
                            <p class="text-xs font-semibold text-muted dark:text-gray-400">Dampak pada Pasien</p>
                            <p class="p-3 mt-1 text-sm rounded-lg bg-surface-soft text-body dark:bg-gray-900 dark:text-gray-200 whitespace-pre-line">{{ $detail->patient_impact }}</p>
                        </div>
                    @endif
                    @if ($detail->immediate_action)
                        <div>
                            <p class="text-xs font-semibold text-muted dark:text-gray-400">Tindakan Segera</p>
                            <p class="p-3 mt-1 text-sm rounded-lg bg-surface-soft text-body dark:bg-gray-900 dark:text-gray-200 whitespace-pre-line">{{ $detail->immediate_action }}</p>
                        </div>
                    @endif
                </div>

                <div class="mt-3 text-xs text-muted dark:text-gray-400">
                    <p>Pelapor: <strong>{{ $detail->is_anonymous ? 'Anonim' : ($detail->reporter_name ?: '—') }}</strong>
                        @if (!$detail->is_anonymous && $detail->reporter_role) · {{ $detail->reporter_role }} @endif
                        @if (!$detail->is_anonymous && $detail->reporter_contact) · {{ $detail->reporter_contact }} @endif
                    </p>
                </div>
                <div class="flex justify-end mt-4">
                    <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'ikp-detail' })">Tutup</x-secondary-button>
                </div>
            </div>
        @endif
    </x-modal>
</div>
