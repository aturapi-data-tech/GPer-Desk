<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Services\DokumenService;

/**
 * Registri Dokumen — daftar induk dokumen bertingkat menurut pembuatannya (Peraturan → Pedoman → SPO →
 * Formulir → Bukti). Sengaja tidak dikelompokkan per Pokja/EP: satu dokumen dibuat sekali lalu dipakai
 * banyak Pokja; kaitan EP tetap tersimpan di dokumen (lihat detail).
 * Pola halaman: master sirus (list + "-actions" child via event). Modal anak:
 *   registri-actions (tambah/ubah), registri-detail (lihat, nomor, status, revisi),
 *   registri-spo (penyusun isi SPO → PDF/DOCX).
 */
new class extends Component {
    use WithPagination;

    public string $searchKeyword = '';
    public string $filterJenis = '';
    public string $filterStatus = '';
    public string $tampilan = 'pohon';   // pohon (induk → turunan) | daftar
    public int $itemsPerPage = 15;
    /** @var int[] simpul pohon yang dibuka */
    public array $terbuka = [];

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
    }

    public function updated($prop): void
    {
        if (in_array($prop, ['searchKeyword', 'filterJenis', 'filterStatus', 'itemsPerPage', 'tampilan'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword', 'filterJenis', 'filterStatus', 'terbuka']);
        $this->itemsPerPage = 15;
        $this->resetPage();
    }

    public function openCreate(?int $indukId = null): void
    {
        if ($indukId && ! in_array($indukId, $this->terbuka, true)) {
            $this->terbuka[] = $indukId;               // pohon: buka induk supaya dokumen baru langsung terlihat
        }
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
    /** Pratinjau PDF (iframe) — hanya bila isinya sudah disusun. */
    public ?int $pdfDokId = null;
    public string $pdfJudul = '';
    public string $pdfVersi = '';   // waktu simpan terakhir — URL berubah tiap isi berubah, iframe tidak memakai PDF lama

    public function lihatPdf(int $dokId): void
    {
        $data = DokumenService::baca($dokId);
        if (! $data || ! DokumenService::punyaIsi($data[1])) {
            $this->dispatch('toast', type: 'info', message: 'Isi dokumen belum disusun — klik Susun isi dulu.');
            return;
        }
        $this->pdfDokId = $dokId;
        $this->pdfJudul = DokumenService::judul($data[0], $data[1]);
        $this->pdfVersi = (string) strtotime((string) $data[0]->updated_at) . '-' . count($data[1]['riwayat'] ?? []);
        $this->dispatch('open-modal', name: 'dokumen-registri-pdf');
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
        unset($this->rows);
    }

    private function dasar()
    {
        return DB::table('dok_dokumen as dk')
            ->join('dok_jenis as dj', 'dj.kode', '=', 'dk.jenis_kode')
            ->leftJoin('dok_dokumen as ind', 'ind.dok_id', '=', 'dk.induk_id')
            ->select('dk.dok_id', 'dk.jenis_kode', 'dk.induk_id', 'dk.dok_no', 'dk.dok_judul', 'dk.unit_kode',
                'dk.tgl_terbit', 'dk.tgl_kaji_ulang', 'dk.no_revisi', 'dk.status', 'dk.klasifikasi', 'dk.sumber',
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
        if ($this->filterStatus !== '') $q->where('dk.status', $this->filterStatus);

        return $q;
    }

    private function adaSaringan(): bool
    {
        return trim($this->searchKeyword) !== '' || $this->filterJenis !== ''
            || $this->filterStatus !== '';
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
};
?>

<div>
    <x-page-title title="Registri Dokumen" subtitle="Peraturan → Pedoman → SPO → Formulir → Bukti — Pedoman Tata Naskah RSUI Madinah" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 border-b bg-surface-soft border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
                    <div class="grid flex-1 grid-cols-2 gap-2 md:grid-cols-4">
                        <div class="col-span-2">
                            <x-text-input type="text" wire:model.live.debounce.300ms="searchKeyword"
                                placeholder="Cari judul / nomor dokumen..." class="block w-full" />
                        </div>
                        <x-select-input wire:model.live="filterJenis">
                            <option value="">Semua jenis</option>
                            @foreach (DokumenService::jenis()->where('bisa_disusun', 'Y') as $j)
                                <option value="{{ $j->kode }}">{{ $j->nama }}</option>
                            @endforeach
                        </x-select-input>
                        <x-select-input wire:model.live="filterStatus">
                            <option value="">Semua status</option>
                            @foreach (DokumenService::STATUS as $k => $nm)
                                <option value="{{ $k }}">{{ $nm }}</option>
                            @endforeach
                        </x-select-input>
                    </div>

                    <div class="flex items-center justify-end gap-2">
                        <x-tabs variant="pill">
                            <x-tab :active="$tampilan === 'pohon'" wire:click="$set('tampilan', 'pohon')">Pohon</x-tab>
                            <x-tab :active="$tampilan === 'daftar'" wire:click="$set('tampilan', 'daftar')">Daftar</x-tab>
                        </x-tabs>
                        <div class="w-24">
                            <x-select-input wire:model.live="itemsPerPage">
                                @foreach ([15, 30, 50, 100] as $n)
                                    <option value="{{ $n }}">{{ $n }}</option>
                                @endforeach
                            </x-select-input>
                        </div>
                        @if (DokumenService::bolehUbah())
                            <x-primary-button type="button" wire:click="openCreate">+ Buat Dokumen</x-primary-button>
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
                                    <td colspan="6" class="px-6 py-16 text-center text-muted dark:text-gray-400">
                                        Belum ada dokumen yang cocok.
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

            {{-- MODAL: pratinjau & unduh PDF --}}
            <x-modal name="dokumen-registri-pdf" size="full" height="full">
                <div class="flex flex-col h-full">
                    <div class="px-6 py-4 border-b bg-surface-soft border-hairline dark:bg-gray-900 dark:border-gray-700">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <x-badge variant="brand">Pratinjau PDF</x-badge>
                                <h2 class="mt-2 ds-display-sm dark:text-gray-100">{{ $pdfJudul }}</h2>
                            </div>
                            <x-icon-button color="gray" type="button" x-on:click="$dispatch('close-modal', { name: 'dokumen-registri-pdf' })">✕</x-icon-button>
                        </div>
                    </div>
                    <div class="flex-1 min-h-0 bg-gray-100 dark:bg-gray-950">
                        @if ($pdfDokId)
                            <iframe wire:key="pdf-{{ $pdfDokId }}-{{ $pdfVersi }}" src="{{ route('dokumen.cetak.pdf', ['dokId' => $pdfDokId, 'v' => $pdfVersi]) }}#view=FitH"
                                class="w-full h-full border-0" title="Pratinjau {{ $pdfJudul }}"></iframe>
                        @endif
                    </div>
                    <div class="sticky bottom-0 z-10 px-6 py-4 border-t bg-surface-soft border-hairline shrink-0 dark:bg-gray-900 dark:border-gray-700">
                        <div class="flex justify-end gap-2">
                            <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'dokumen-registri-pdf' })">Tutup</x-secondary-button>
                            @if ($pdfDokId)
                                <a href="{{ route('dokumen.cetak.pdf', ['dokId' => $pdfDokId, 'unduh' => 1, 'v' => $pdfVersi]) }}">
                                    <x-cetak-button type="button" label="Unduh PDF" />
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </x-modal>

            <livewire:pages::dokumen.registri.registri-actions wire:key="dokumen-registri-actions" />
            <livewire:pages::dokumen.registri.registri-detail wire:key="dokumen-registri-detail" />
            <livewire:pages::dokumen.registri.registri-spo wire:key="dokumen-registri-spo" />
            <livewire:pages::dokumen.registri.registri-naskah wire:key="dokumen-registri-naskah" />
        </div>
    </div>
</div>
