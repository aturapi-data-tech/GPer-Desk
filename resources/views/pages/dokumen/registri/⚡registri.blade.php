<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Services\DokumenService;

/**
 * Registri Dokumen Regulasi & Akreditasi — daftar induk dokumen bertingkat.
 * Pola halaman: master sirus (list + "-actions" child via event). Modal anak:
 *   registri-actions (tambah/ubah), registri-detail (lihat, nomor, status, revisi),
 *   registri-spo (penyusun isi SPO → PDF/DOCX).
 */
new class extends Component {
    use WithPagination;

    public string $searchKeyword = '';
    public string $filterJenis = '';
    public string $filterTingkat = '';
    public string $filterStatus = '';
    public string $filterPokja = '';
    public string $tampilan = 'daftar';   // daftar | pohon
    public int $itemsPerPage = 15;
    /** @var int[] simpul pohon yang dibuka */
    public array $terbuka = [];

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
    }

    public function updated($prop): void
    {
        if (in_array($prop, ['searchKeyword', 'filterJenis', 'filterTingkat', 'filterStatus', 'filterPokja', 'itemsPerPage', 'tampilan'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterJenis', 'filterTingkat', 'filterStatus', 'filterPokja', 'terbuka']);
        $this->itemsPerPage = 15;
        $this->resetPage();
    }

    public function openCreate(?int $indukId = null): void
    {
        $this->dispatch('dokumen.registri.openCreate', indukId: $indukId);
    }

    public function openEdit(int $dokId): void { $this->dispatch('dokumen.registri.openEdit', dokId: $dokId); }
    public function openDetail(int $dokId): void { $this->dispatch('dokumen.registri.openDetail', dokId: $dokId); }
    public function openSpo(int $dokId): void { $this->dispatch('dokumen.registri.openSpo', dokId: $dokId); }

    /** Buka penyusun sesuai format naskah: SPO → penyusun SPO, lainnya → penyusun naskah (JSON tata naskah). */
    public function openSusun(int $dokId): void
    {
        $this->dispatch(DokumenService::penyusun($dokId), dokId: $dokId);
    }
    public function requestDelete(int $dokId): void { $this->dispatch('dokumen.registri.requestDelete', dokId: $dokId); }

    public function toggle(int $dokId): void
    {
        $this->terbuka = in_array($dokId, $this->terbuka, true)
            ? array_values(array_diff($this->terbuka, [$dokId]))
            : [...$this->terbuka, $dokId];
    }

    #[On('dokumen.registri.saved')]
    public function refreshAfterSaved(): void
    {
        unset($this->rows, $this->stats);
    }

    private function dasar()
    {
        return DB::table('dok_dokumen as dk')
            ->join('dok_jenis as dj', 'dj.kode', '=', 'dk.jenis_kode')
            ->leftJoin('dok_dokumen as ind', 'ind.dok_id', '=', 'dk.induk_id')
            ->select('dk.dok_id', 'dk.jenis_kode', 'dk.induk_id', 'dk.dok_no', 'dk.dok_judul', 'dk.unit_kode',
                'dk.tgl_terbit', 'dk.tgl_kaji_ulang', 'dk.no_revisi', 'dk.status', 'dk.klasifikasi', 'dk.sumber', 'dk.pokja',
                'dj.nama as jenis_nama', 'dj.tingkat', 'dj.bisa_disusun', 'ind.dok_judul as induk_judul', 'ind.dok_no as induk_no')
            ->selectRaw('(SELECT COUNT(*) FROM dok_dokumen an WHERE an.induk_id = dk.dok_id) AS jml_turunan');
    }

    private function saring($q)
    {
        if (trim($this->searchKeyword) !== '') {
            $kw = mb_strtoupper(trim($this->searchKeyword));
            $q->where(fn ($w) => $w->whereRaw('UPPER(dk.dok_judul) LIKE ?', ["%{$kw}%"])
                ->orWhereRaw('UPPER(dk.dok_no) LIKE ?', ["%{$kw}%"]));
        }
        if ($this->filterJenis !== '') $q->where('dk.jenis_kode', $this->filterJenis);
        if ($this->filterTingkat !== '') $q->where('dj.tingkat', (int) $this->filterTingkat);
        if ($this->filterStatus !== '') $q->where('dk.status', $this->filterStatus);
        if ($this->filterPokja !== '') $q->whereRaw("(',' || dk.pokja || ',') LIKE ?", ['%,' . $this->filterPokja . ',%']);

        return $q;
    }

    private function adaSaringan(): bool
    {
        return trim($this->searchKeyword) !== '' || $this->filterJenis !== '' || $this->filterTingkat !== ''
            || $this->filterStatus !== '' || $this->filterPokja !== '';
    }

    #[Computed]
    public function rows()
    {
        $q = $this->saring($this->dasar());
        // Pohon tanpa saringan: mulai dari akar (dokumen tanpa induk); dengan saringan tampil rata
        if ($this->tampilan === 'pohon' && ! $this->adaSaringan()) {
            $q->whereNull('dk.induk_id');
        }

        return $q->orderBy('dj.tingkat')->orderBy('dj.urut')->orderByRaw('dk.dok_no_urut NULLS LAST')
            ->orderBy('dk.dok_judul')->paginate($this->itemsPerPage);
    }

    /** Turunan simpul yang dibuka (pohon) — dikelompokkan per induk. */
    #[Computed]
    public function turunan(): array
    {
        if ($this->tampilan !== 'pohon' || ! $this->terbuka) {
            return [];
        }

        return $this->dasar()->whereIn('dk.induk_id', $this->terbuka)
            ->orderBy('dj.tingkat')->orderBy('dk.dok_judul')->get()->groupBy('induk_id')->all();
    }

    #[Computed]
    public function stats(): array
    {
        $r = DB::selectOne("SELECT COUNT(*) total,
                SUM(CASE WHEN status='BERLAKU' THEN 1 ELSE 0 END) berlaku,
                SUM(CASE WHEN status='DRAF' THEN 1 ELSE 0 END) draf,
                SUM(CASE WHEN status='BERLAKU' AND tgl_kaji_ulang <= SYSDATE + 60 THEN 1 ELSE 0 END) kaji,
                SUM(CASE WHEN dok_no IS NULL AND jenis_kode IN (SELECT kode FROM dok_jenis WHERE pola_nomor IS NOT NULL) THEN 1 ELSE 0 END) tanpa_no
            FROM dok_dokumen");

        return ['total' => (int) $r->total, 'berlaku' => (int) $r->berlaku, 'draf' => (int) $r->draf,
            'kaji' => (int) $r->kaji, 'tanpa_no' => (int) $r->tanpa_no];
    }
};
?>

<div>
    <x-page-title title="Registri Dokumen" subtitle="Regulasi & bukti akreditasi bertingkat — Pedoman Tata Naskah RSUI Madinah" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- STAT --}}
            <div class="grid grid-cols-2 gap-3 mt-2 md:grid-cols-5">
                <x-stat-tile label="Dokumen tercatat" :value="number_format($this->stats['total'], 0, ',', '.')" />
                <x-stat-tile label="Berlaku" :value="number_format($this->stats['berlaku'], 0, ',', '.')" accent="#16a34a" />
                <x-stat-tile label="Draf" :value="number_format($this->stats['draf'], 0, ',', '.')" accent="#6b7280" />
                <x-stat-tile label="Kaji ulang ≤ 60 hari" :value="number_format($this->stats['kaji'], 0, ',', '.')" accent="#d97706" />
                <x-stat-tile label="Belum bernomor" :value="number_format($this->stats['tanpa_no'], 0, ',', '.')" accent="#dc2626"
                    hint="jenis yang wajib bernomor registrasi" />
            </div>

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 mt-3 border-b bg-surface-soft border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
                    <div class="grid flex-1 grid-cols-2 gap-2 md:grid-cols-6">
                        <div class="col-span-2">
                            <x-text-input type="text" wire:model.live.debounce.300ms="searchKeyword"
                                placeholder="Cari judul / nomor dokumen..." class="block w-full" />
                        </div>
                        <x-select-input wire:model.live="filterTingkat">
                            <option value="">Semua tingkat</option>
                            @foreach (DokumenService::TINGKAT as $t => $nm)
                                <option value="{{ $t }}">{{ $t }} · {{ $nm }}</option>
                            @endforeach
                        </x-select-input>
                        <x-select-input wire:model.live="filterJenis">
                            <option value="">Semua jenis</option>
                            @foreach (DokumenService::jenis() as $j)
                                <option value="{{ $j->kode }}">{{ $j->nama }}</option>
                            @endforeach
                        </x-select-input>
                        <x-select-input wire:model.live="filterStatus">
                            <option value="">Semua status</option>
                            @foreach (DokumenService::STATUS as $k => $nm)
                                <option value="{{ $k }}">{{ $nm }}</option>
                            @endforeach
                        </x-select-input>
                        <x-select-input wire:model.live="filterPokja">
                            <option value="">Semua pokja</option>
                            @foreach (config('dokumen.pokja') as $k => $nm)
                                <option value="{{ $k }}">{{ $k }}</option>
                            @endforeach
                        </x-select-input>
                    </div>

                    <div class="flex items-center justify-end gap-2">
                        <div class="inline-flex p-1 border rounded-xl border-hairline bg-canvas dark:bg-gray-800 dark:border-gray-700">
                            @foreach (['daftar' => 'Daftar', 'pohon' => 'Pohon'] as $k => $nm)
                                <button type="button" wire:click="$set('tampilan','{{ $k }}')"
                                    class="px-3 py-1.5 text-sm rounded-lg {{ $tampilan === $k ? 'bg-brand-green text-white' : 'text-muted dark:text-gray-300' }}">{{ $nm }}</button>
                            @endforeach
                        </div>
                        <div class="w-24">
                            <x-select-input wire:model.live="itemsPerPage">
                                @foreach ([15, 30, 50, 100] as $n)
                                    <option value="{{ $n }}">{{ $n }}</option>
                                @endforeach
                            </x-select-input>
                        </div>
                        @if (DokumenService::bolehUbah())
                            <x-primary-button type="button" wire:click="openCreate">+ Dokumen</x-primary-button>
                        @endif
                        <x-toolbar-refresh-reset :label="null" />
                    </div>
                </div>
                @if ($tampilan === 'pohon')
                    <p class="mt-2 text-xs text-muted dark:text-gray-400">
                        Pohon dimulai dari dokumen tanpa induk (tingkat tertinggi). Klik ▸ untuk membuka turunannya.
                        Dengan saringan aktif, hasil ditampilkan rata.
                    </p>
                @endif
            </div>

            {{-- TABEL --}}
            <div class="flex flex-col flex-1 min-h-0 mt-4 border shadow-sm bg-canvas border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-auto rounded-t-2xl">
                    <table class="ds-table">
                        <thead class="sticky top-0 z-10">
                            <tr>
                                <th class="w-28">Jenis</th>
                                <th>Judul &amp; nomor</th>
                                <th class="w-28">Terbit</th>
                                <th class="ds-c w-14">Rev</th>
                                <th class="w-28">Status</th>
                                <th>Pokja</th>
                                <th class="ds-c w-44">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $turunan = $this->turunan;
                                $bolehUbah = DokumenService::bolehUbah();
                            @endphp
                            @forelse ($this->rows as $row)
                                @include('pages.dokumen.registri.baris-registri', [
                                    'row' => $row, 'kedalaman' => 0, 'turunan' => $turunan,
                                    'terbuka' => $terbuka, 'pohon' => $tampilan === 'pohon', 'bolehUbah' => $bolehUbah,
                                ])
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-10 text-center text-muted dark:text-gray-400">
                                        Belum ada dokumen yang cocok. {{ DokumenService::bolehUbah() ? 'Tambah dokumen atau jalankan impor akreditasi.' : '' }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="sticky bottom-0 z-10 px-4 py-3 border-t bg-canvas border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

            <livewire:pages::dokumen.registri.registri-actions wire:key="dokumen-registri-actions" />
            <livewire:pages::dokumen.registri.registri-detail wire:key="dokumen-registri-detail" />
            <livewire:pages::dokumen.registri.registri-spo wire:key="dokumen-registri-spo" />
            <livewire:pages::dokumen.registri.registri-naskah wire:key="dokumen-registri-naskah" />
        </div>
    </div>
</div>
