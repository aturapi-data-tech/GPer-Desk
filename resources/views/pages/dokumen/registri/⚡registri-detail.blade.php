<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use App\Services\DokumenService;

new class extends Component {
    public ?int $dokId = null;
    public ?array $dok = null;     // header (array)
    public array $json = [];
    public array $jalur = [];      // induk sampai akar
    public array $anak = [];
    public string $alasanRevisi = '';

    #[On('dokumen.registri.openDetail')]
    public function buka(int $dokId): void
    {
        $this->dokId = $dokId;
        $this->muat();
        $this->dispatch('open-modal', name: 'dokumen-registri-detail');
    }

    #[On('dokumen.registri.saved')]
    public function muatUlang(): void
    {
        if ($this->dokId) $this->muat();
    }

    private function muat(): void
    {
        $data = DokumenService::baca($this->dokId);
        if (! $data) { $this->dok = null; return; }
        [$row, $this->json] = $data;
        $jenis = DokumenService::jenis()[$row->jenis_kode] ?? null;
        $this->dok = (array) $row + ['jenis_nama' => $jenis->nama ?? $row->jenis_kode, 'tingkat' => (int) ($jenis->tingkat ?? 5),
            'bisa_disusun' => $jenis->bisa_disusun ?? 'N', 'berpola' => filled($jenis->pola_nomor ?? null)];
        $ids = array_slice(DokumenService::jalurInduk($this->dokId), 1);
        $this->jalur = $ids ? DB::table('dok_dokumen')->whereIn('dok_id', $ids)->get(['dok_id', 'dok_no', 'dok_judul', 'jenis_kode'])
            ->sortBy(fn ($r) => array_search((int) $r->dok_id, $ids))->reverse()->map(fn ($r) => (array) $r)->values()->all() : [];
        $this->anak = DB::table('dok_dokumen as dk')->join('dok_jenis as dj', 'dj.kode', '=', 'dk.jenis_kode')
            ->where('dk.induk_id', $this->dokId)->orderBy('dj.tingkat')->orderBy('dk.dok_judul')
            ->get(['dk.dok_id', 'dk.dok_no', 'dk.dok_judul', 'dk.status', 'dj.nama as jenis_nama'])->map(fn ($r) => (array) $r)->all();
    }

    private function jalankan(callable $f, string $pesan): void
    {
        abort_unless(DokumenService::bolehUbah(), 403);
        try {
            $f();
            $this->dispatch('toast', type: 'success', message: $pesan);
            $this->dispatch('dokumen.registri.saved');
        } catch (\DomainException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function ambilNomor(): void
    {
        $this->jalankan(function () { $this->dispatch('toast', type: 'info', message: 'Nomor: ' . DokumenService::ambilNomor($this->dokId)); }, 'Nomor registrasi diterbitkan.');
    }

    public function ubahStatus(string $status): void
    {
        $this->jalankan(fn () => DokumenService::ubahStatus($this->dokId, $status), 'Status diperbarui.');
    }

    public function naikkanRevisi(): void
    {
        $this->jalankan(function () { DokumenService::naikkanRevisi($this->dokId, trim($this->alasanRevisi)); $this->alasanRevisi = ''; }, 'Nomor revisi dinaikkan.');
    }

    public function tambahTurunan(): void
    {
        $this->dispatch('close-modal', name: 'dokumen-registri-detail');
        $this->dispatch('dokumen.registri.openCreate', indukId: $this->dokId);
    }

    public function ubah(): void
    {
        $this->dispatch('close-modal', name: 'dokumen-registri-detail');
        $this->dispatch('dokumen.registri.openEdit', dokId: $this->dokId);
    }

    public function susun(): void
    {
        $this->dispatch('close-modal', name: 'dokumen-registri-detail');
        $this->dispatch('dokumen.registri.openSpo', dokId: $this->dokId);
    }

    public function lompat(int $dokId): void
    {
        $this->dokId = $dokId;
        $this->muat();
    }
};
?>

<div>
    <x-modal name="dokumen-registri-detail" size="4xl" height="full">
        @if ($dok)
            <div class="flex flex-col h-full">
                <div class="px-6 py-5 border-b bg-surface-soft border-hairline dark:bg-gray-900 dark:border-gray-700">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-badge variant="brand">T{{ $dok['tingkat'] }} · {{ $dok['jenis_nama'] }}</x-badge>
                                <x-badge :variant="DokumenService::STATUS_BADGE[$dok['status']] ?? 'gray'">{{ DokumenService::STATUS[$dok['status']] }}</x-badge>
                                <x-badge variant="gray">Rev. {{ $dok['no_revisi'] }}</x-badge>
                                @if ($dok['klasifikasi'] !== 'BIASA')
                                    <x-badge variant="danger">{{ DokumenService::KLASIFIKASI[$dok['klasifikasi']] }}</x-badge>
                                @endif
                                @if ($dok['sumber'] === 'IMPOR')
                                    <x-badge variant="info">Impor akreditasi</x-badge>
                                @endif
                            </div>
                            <h2 class="mt-2 ds-display-sm dark:text-gray-100">{{ $dok['dok_judul'] }}</h2>
                            <p class="mt-1 font-mono text-sm text-muted dark:text-gray-400">{{ $dok['dok_no'] ?: 'belum bernomor' }}</p>
                        </div>
                        <x-icon-button color="gray" type="button" x-on:click="$dispatch('close-modal', { name: 'dokumen-registri-detail' })">✕</x-icon-button>
                    </div>

                    {{-- jalur induk --}}
                    @if ($jalur)
                        <nav class="flex flex-wrap items-center gap-1 mt-3 text-xs text-muted">
                            @foreach ($jalur as $j)
                                <button type="button" wire:click="lompat({{ $j['dok_id'] }})" class="hover:underline">{{ $j['dok_no'] ?: $j['dok_judul'] }}</button>
                                <span>›</span>
                            @endforeach
                            <span class="font-semibold">ini</span>
                        </nav>
                    @endif
                </div>

                <div class="flex-1 px-6 py-4 space-y-4 overflow-y-auto">
                    <div class="grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
                        <div><div class="text-xs text-muted">Terbit</div>{{ DokumenService::tanggalIndonesia($dok['tgl_terbit']) ?: '—' }}</div>
                        <div><div class="text-xs text-muted">Kaji ulang</div>{{ DokumenService::tanggalIndonesia($dok['tgl_kaji_ulang']) ?: '—' }}</div>
                        <div><div class="text-xs text-muted">Bagian</div>{{ $dok['unit_kode'] ?: '—' }}</div>
                        <div><div class="text-xs text-muted">Diperbarui</div>{{ $dok['updated_by'] }}</div>
                    </div>
                    @if (filled($json['ringkasan'] ?? ''))
                        <p class="text-sm">{{ $json['ringkasan'] }}</p>
                    @endif

                    <x-border-form title="Kaitan akreditasi">
                        <div class="flex flex-wrap gap-2">
                            @forelse ($json['akreditasi'] as $ak)
                                <x-badge variant="gray">{{ $ak['standar'] ?: $ak['bab'] }}{{ $ak['ep'] ? ' EP ' . $ak['ep'] : '' }}{{ $ak['elemen'] ? ' (' . $ak['elemen'] . ')' : '' }}</x-badge>
                            @empty
                                <span class="text-sm text-muted">—</span>
                            @endforelse
                        </div>
                    </x-border-form>

                    <x-border-form title="Berkas">
                        <div class="space-y-2">
                            @forelse ($json['berkas'] as $b)
                                <div class="flex items-center justify-between gap-3 text-sm" wire:key="db-{{ $b['id'] }}">
                                    <div class="min-w-0">
                                        <div class="truncate">{{ $b['nama'] }}</div>
                                        <div class="text-xs text-muted">{{ $b['peran'] }} · {{ number_format(($b['ukuran'] ?? 0) / 1024, 0, ',', '.') }} KB
                                            @if (! DokumenService::pathBerkas($b)) · <span class="text-red-600">berkas tidak ditemukan</span> @endif</div>
                                    </div>
                                    <a href="{{ route('dokumen.berkas', [$dok['dok_id'], $b['id']]) }}" target="_blank">
                                        <x-secondary-button type="button">Buka</x-secondary-button>
                                    </a>
                                </div>
                            @empty
                                <span class="text-sm text-muted">Belum ada berkas.</span>
                            @endforelse
                            @if (filled($json['isi']['spo'] ?? null))
                                <div class="flex gap-2 pt-2 border-t border-hairline">
                                    <a href="{{ route('dokumen.cetak.pdf', $dok['dok_id']) }}" target="_blank"><x-cetak-button type="button">Cetak SPO (PDF)</x-cetak-button></a>
                                    <a href="{{ route('dokumen.cetak.docx', $dok['dok_id']) }}"><x-secondary-button type="button">Unduh SPO (DOCX)</x-secondary-button></a>
                                </div>
                            @endif
                        </div>
                    </x-border-form>

                    <x-border-form title="Turunan ({{ count($anak) }})">
                        <div class="space-y-1">
                            @forelse ($anak as $a)
                                <button type="button" wire:click="lompat({{ $a['dok_id'] }})" class="block w-full text-sm text-left hover:underline">
                                    <span class="text-xs text-muted">{{ $a['jenis_nama'] }}</span> · {{ $a['dok_judul'] }}
                                    <span class="font-mono text-xs text-muted">{{ $a['dok_no'] }}</span>
                                </button>
                            @empty
                                <span class="text-sm text-muted">—</span>
                            @endforelse
                        </div>
                    </x-border-form>

                    @if ($json['tataNaskah'] ?? null)
                        <x-border-form title="Pemeriksaan tata naskah (impor)">
                            <div class="text-sm">Golongan: {{ $json['tataNaskah']['golongan'] ?? '—' }}</div>
                            <ul class="mt-1 text-xs list-disc list-inside text-muted">
                                @foreach (($json['tataNaskah']['butir'] ?? []) as $k => $v)
                                    @if (($v[0] ?? '') === 'tidak')
                                        <li>{{ $k }}: {{ $v[1] ?? '' }}</li>
                                    @endif
                                @endforeach
                            </ul>
                        </x-border-form>
                    @endif

                    <x-border-form title="Riwayat">
                        <ol class="space-y-1 text-xs">
                            @foreach (array_reverse($json['riwayat']) as $r)
                                <li><span class="font-mono text-muted">{{ $r['waktu'] }}</span> · <b>{{ $r['aksi'] }}</b> · {{ $r['oleh'] }}{{ $r['catatan'] ? ' — ' . $r['catatan'] : '' }}</li>
                            @endforeach
                        </ol>
                    </x-border-form>
                </div>

                @if (DokumenService::bolehUbah())
                    <div class="sticky bottom-0 px-6 py-4 border-t bg-surface-soft border-hairline dark:bg-gray-900 dark:border-gray-700">
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            @if (! $dok['dok_no'] && $dok['berpola'])
                                <x-confirm-button variant="primary" action="ambilNomor" title="Ambil nomor registrasi"
                                    message="Nomor resmi akan diterbitkan dan tidak bisa ditarik kembali. Lanjutkan?" confirmText="Ambil nomor">Ambil Nomor</x-confirm-button>
                            @endif
                            @if ($dok['status'] !== 'BERLAKU')
                                <x-confirm-button variant="primary" action="ubahStatus('BERLAKU')" title="Berlakukan dokumen" message="Tandai dokumen ini BERLAKU?">Berlakukan</x-confirm-button>
                            @else
                                <x-confirm-button variant="warning-soft" action="ubahStatus('TIDAK_BERLAKU')" title="Tidak berlaku" message="Tandai dokumen ini TIDAK BERLAKU (obsolete)?">Tidak Berlaku</x-confirm-button>
                            @endif
                            <div class="flex items-center gap-1">
                                <x-text-input wire:model="alasanRevisi" class="w-48" placeholder="alasan revisi" />
                                <x-confirm-button variant="secondary" action="naikkanRevisi" title="Naikkan revisi" message="Naikkan nomor revisi dokumen ini?">Revisi +1</x-confirm-button>
                            </div>
                            <x-secondary-button type="button" wire:click="tambahTurunan">+ Turunan</x-secondary-button>
                            @if ($dok['bisa_disusun'] === 'Y')
                                <x-secondary-button type="button" wire:click="susun">Susun isi</x-secondary-button>
                            @endif
                            <x-primary-button type="button" wire:click="ubah">Ubah</x-primary-button>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </x-modal>
</div>
