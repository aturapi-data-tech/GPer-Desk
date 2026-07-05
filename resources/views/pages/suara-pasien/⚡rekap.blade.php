<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public string $searchKeyword = '';
    public int    $itemsPerPage  = 10;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['admin', 'manager', 'direktur']), 403);
    }

    public function updatedSearchKeyword(): void { $this->resetPage(); }
    public function updatedItemsPerPage(): void  { $this->resetPage(); }

    public function resetFilters(): void
    {
        $this->reset('searchKeyword');
        $this->itemsPerPage = 10;
        $this->resetPage();
    }

    #[Computed]
    public function stats(): array
    {
        $total = DB::table('patient_reviews')->count();
        $avg   = $total ? round((float) DB::table('patient_reviews')->avg('rating'), 2) : 0.0;
        $distribusi  = [];
        for ($i = 5; $i >= 1; $i--) {
            $distribusi[$i] = DB::table('patient_reviews')->where('rating', $i)->count();
        }
        return ['total' => $total, 'avg' => $avg, 'dist' => $distribusi];
    }

    #[Computed]
    public function rows()
    {
        $query = DB::table('patient_reviews')
            ->select('id', 'reviewer_name', 'rating', 'comment', 'photo_path', 'is_visible',
                DB::raw("TO_CHAR(created_at, 'dd/mm/yyyy HH24:MI:SS') as tgl"))
            ->orderByDesc('id');

        if (trim($this->searchKeyword) !== '') {
            $keyword = mb_strtoupper(trim($this->searchKeyword));
            $query->where(function ($subQuery) use ($keyword) {
                $subQuery->whereRaw('UPPER(reviewer_name) LIKE ?', ["%{$keyword}%"])
                  ->orWhereRaw('UPPER(comment) LIKE ?', ["%{$keyword}%"]);
            });
        }

        return $query->paginate($this->itemsPerPage);
    }

    public function toggleVisible(int $id): void
    {
        $cur = DB::table('patient_reviews')->where('id', $id)->value('is_visible');
        DB::table('patient_reviews')->where('id', $id)->update(['is_visible' => $cur ? 0 : 1, 'updated_at' => now()]);
        $this->dispatch('toast', type: 'success', message: $cur ? 'Ulasan disembunyikan dari publik.' : 'Ulasan ditampilkan lagi.');
    }

    public function requestDelete(int $id): void
    {
        DB::table('patient_reviews')->where('id', $id)->delete();
        $this->dispatch('toast', type: 'success', message: 'Ulasan dihapus.');
        $this->resetPage();
    }
};
?>

<div>
    <x-page-title title="Suara Pasien" subtitle="Rekap ulasan & penilaian pasien (Google-review style)" />

    {{-- $statistik['dist'] = distribusi jumlah ulasan per bintang (index 1..5). $distMaks = nilai terbanyak, dipakai skala lebar bar. --}}
    @php $statistik = $this->stats; $distMaks = max(1, max($statistik['dist'])); @endphp

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- RINGKASAN --}}
            <div class="grid grid-cols-1 gap-4 mt-2 lg:grid-cols-3">
                <div class="flex items-center gap-4 p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                    <div class="text-center">
                        <div class="text-4xl font-bold text-ink dark:text-gray-100">{{ number_format($statistik['avg'], 1) }}</div>
                        <div class="flex items-center gap-0.5 mt-1">
                            @for ($i = 1; $i <= 5; $i++)
                                <svg class="w-4 h-4 {{ $i <= round($statistik['avg']) ? 'text-amber-400' : 'text-hairline dark:text-gray-600' }}" fill="currentColor" viewBox="0 0 24 24"><path d="M11.48 3.5a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.562.562 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" /></svg>
                            @endfor
                        </div>
                    </div>
                    <div class="pl-4 border-l border-hairline dark:border-gray-700">
                        <p class="text-2xl font-bold text-ink dark:text-gray-100">{{ $statistik['total'] }}</p>
                        <p class="text-xs text-muted dark:text-gray-400">total ulasan</p>
                    </div>
                </div>

                <div class="p-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700 lg:col-span-2">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted dark:text-gray-400">Distribusi Bintang</p>
                    {{-- $i = tingkat bintang, dihitung mundur 5 → 1 agar bintang tertinggi tampil di atas. --}}
                    @for ($i = 5; $i >= 1; $i--)
                        <div class="flex items-center gap-2 mb-1">
                            <span class="w-3 text-xs font-semibold text-muted">{{ $i }}</span>
                            <svg class="w-3.5 h-3.5 text-amber-400 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M11.48 3.5a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.562.562 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" /></svg>
                            <div class="flex-1 h-2 overflow-hidden rounded-full bg-surface-soft dark:bg-gray-700">
                                <div class="h-full rounded-full bg-amber-400" style="width: {{ round($statistik['dist'][$i] / $distMaks * 100) }}%"></div>
                            </div>
                            <span class="w-8 text-xs text-right text-muted">{{ $statistik['dist'][$i] }}</span>
                        </div>
                    @endfor
                </div>
            </div>

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 mt-4 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="w-full lg:max-w-md">
                        <x-input-label for="searchKeyword" value="Cari ulasan" class="sr-only" />
                        <x-text-input id="searchKeyword" type="text" wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Cari nama / isi ulasan..." class="block w-full" />
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <div class="w-28">
                            <x-select-input wire:model.live="itemsPerPage">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                            </x-select-input>
                        </div>
                        <x-toolbar-refresh-reset :label="null" />
                    </div>
                </div>
            </div>

            {{-- TABEL --}}
            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="ds-table">
                        <thead class="sticky top-0 z-10">
                            <tr>
                                <th>Nama</th>
                                <th class="ds-c">Bintang</th>
                                <th>Ulasan</th>
                                <th>Tanggal</th>
                                <th class="ds-c">Tampil?</th>
                                <th class="ds-c">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- $row = satu baris ulasan (item koleksi $this->rows) — idiom baku repo untuk item tabel. --}}
                            @forelse ($this->rows as $row)
                                <tr wire:key="rev-{{ $row->id }}">
                                    <td class="ds-td-strong">{{ $row->reviewer_name }}</td>
                                    <td class="ds-c">
                                        <div class="inline-flex items-center gap-0.5">
                                            @for ($i = 1; $i <= 5; $i++)
                                                <svg class="w-4 h-4 {{ $i <= $row->rating ? 'text-amber-400' : 'text-hairline dark:text-gray-600' }}" fill="currentColor" viewBox="0 0 24 24"><path d="M11.48 3.5a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.562.562 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" /></svg>
                                            @endfor
                                        </div>
                                    </td>
                                    <td class="max-w-md text-sm text-body dark:text-gray-300">
                                        {{ $row->comment ?: '—' }}
                                        @if (!empty($row->photo_path))
                                            <a href="{{ asset('storage/' . $row->photo_path) }}" target="_blank" rel="noopener" class="inline-block mt-1">
                                                <img src="{{ asset('storage/' . $row->photo_path) }}" alt="foto" class="object-cover w-16 h-16 border rounded border-hairline dark:border-gray-700" />
                                            </a>
                                        @endif
                                    </td>
                                    <td class="text-xs text-muted">{{ $row->tgl }}</td>
                                    <td class="ds-c">
                                        <x-badge :variant="$row->is_visible ? 'success' : 'gray'">
                                            {{ $row->is_visible ? 'Tampil' : 'Disembunyikan' }}
                                        </x-badge>
                                    </td>
                                    <td class="ds-c">
                                        <div class="flex items-center justify-center gap-2">
                                            <x-outline-button type="button" wire:click="toggleVisible({{ $row->id }})">
                                                {{ $row->is_visible ? 'Sembunyikan' : 'Tampilkan' }}
                                            </x-outline-button>
                                            <x-action-delete :action="'requestDelete(' . $row->id . ')'"
                                                title="Hapus Ulasan" message="Yakin hapus ulasan dari {{ $row->reviewer_name }}?" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center text-muted dark:text-gray-400">Belum ada ulasan.</td>
                                </tr>
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
</div>
