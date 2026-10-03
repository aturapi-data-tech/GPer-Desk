<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Services\DokumenService;

/**
 * Penyusun isi SPO — pola editor resume medis sirus (TinyMCE dalam modal, flush sebelum simpan).
 * Isi disimpan ke DOK_JSON.isi.spo; cetak PDF/DOCX lewat DokumenCetakController.
 * Bagian mengikuti format SPO Pedoman hal. 25–26.
 */
new class extends Component {
    public ?int $dokId = null;
    public string $judul = '';
    public string $nomor = '';

    public string $pengertian = '';
    public string $tujuan = '';
    public string $kebijakan = '';
    public string $prosedur = '';
    public string $unitTerkait = '';
    public string $dokumenTerkait = '';

    public string $jabatanPenetap = '';
    public string $namaPenetap = '';

    #[On('dokumen.registri.openSpo')]
    public function buka(int $dokId): void
    {
        $data = DokumenService::baca($dokId);
        if (! $data) return;
        [$row, $json] = $data;
        $this->dokId = $dokId;
        $this->judul = (string) $row->dok_judul;
        $this->nomor = (string) ($row->dok_no ?? '');
        $spo = $json['isi']['spo'] ?? [];
        foreach (array_keys(DokumenService::BAGIAN_SPO) as $k) {
            $this->{$k} = (string) ($spo[$k] ?? '');
        }
        $pen = $json['penandatangan']['ditetapkan'] ?? config('dokumen.pejabat.direktur');
        $this->jabatanPenetap = (string) ($pen['jabatan'] ?? 'Direktur');
        $this->namaPenetap = (string) ($pen['nama'] ?? '');
        $this->dispatch('open-modal', name: 'dokumen-registri-spo');
        $this->dispatch('dokumen-spo.reload');
    }

    public function simpan(bool $diam = false): bool
    {
        abort_unless(DokumenService::bolehUbah(), 403);
        try {
            DokumenService::pastikanTidakTerkunci((int) $this->dokId);
        } catch (\DomainException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
            return false;
        }
        if (! $this->dokId) return false;
        $isi = [];
        foreach (array_keys(DokumenService::BAGIAN_SPO) as $k) {
            $isi[$k] = trim($this->{$k});
        }
        $penetap = ['jabatan' => trim($this->jabatanPenetap) ?: 'Direktur', 'nama' => trim($this->namaPenetap)];
        DokumenService::catat($this->dokId, 'SUSUN', 'isi SPO diperbarui', function ($json) use ($isi, $penetap) {
            $json['isi']['spo'] = $isi;
            $json['penandatangan']['ditetapkan'] = $penetap;
            return $json;
        });
        if (! $diam) {
            $this->dispatch('toast', type: 'success', message: 'Isi SPO tersimpan.');
        }
        $this->dispatch('dokumen.registri.saved');

        return true;
    }

    public function simpanLaluCetak(string $format): void
    {
        if (! $this->simpan(true)) {
            return;                                  // gagal simpan (mis. terkunci) → jangan cetak isi lama
        }
        $url = $format === 'docx' ? route('dokumen.cetak.docx', $this->dokId) : route('dokumen.cetak.pdf', ['dokId' => $this->dokId, 'unduh' => 1]);
        $this->dispatch('dokumen-spo.buka-url', url: $url);
    }

    public function terbitkanNomor(): void
    {
        abort_unless(DokumenService::bolehUbah(), 403);
        try {
            $this->nomor = DokumenService::ambilNomor((int) $this->dokId);
        } catch (\DomainException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
            return;
        }
        $this->dispatch('toast', type: 'success', message: 'Nomor terbit: ' . $this->nomor);
        $this->dispatch('dokumen.registri.saved');
    }

    public function tutup(): void
    {
        $this->dispatch('close-modal', name: 'dokumen-registri-spo');
    }
};
?>

<div x-data x-on:dokumen-spo.buka-url.window="window.location.href = $event.detail.url">
    <x-modal name="dokumen-registri-spo" size="full" height="full">
        <div class="flex flex-col h-full">
            <div class="px-6 py-4 border-b bg-surface-soft border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <x-badge variant="brand">Penyusun SPO</x-badge>
                        <h2 class="mt-2 ds-display-sm dark:text-gray-100">{{ $judul }}</h2>
                        @include('pages.dokumen.registri.nomor-dokumen', ['dokId' => $dokId, 'nomor' => $nomor])
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="tutup">✕</x-icon-button>
                </div>
            </div>

            <div class="flex-1 px-6 py-5 space-y-5 overflow-y-auto">
                <p class="text-sm text-muted dark:text-gray-400">
                    Format SPO Pedoman Tata Naskah hal. 25–26. Pakai daftar bernomor untuk langkah prosedur.
                    Kop, nomor, revisi, tanggal terbit dan halaman berjalan dibuat otomatis saat cetak.
                </p>
                @foreach (DokumenService::BAGIAN_SPO as $k => $label)
                    <div wire:key="spo-{{ $k }}">
                        <x-input-label :value="$label" />
                        <x-tinymce-editor :name="$k" modal-event="dokumen-registri-spo" flush-event="dokumen-spo.flush"
                            reload-event="dokumen-spo.reload" :height="$k === 'prosedur' ? 520 : 220" class="mt-1" />
                    </div>
                @endforeach
                <x-border-form title="Penetapan (kop SPO)">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div><x-input-label value="Jabatan" /><x-text-input wire:model="jabatanPenetap" class="w-full mt-1" /></div>
                        <div><x-input-label value="Nama" /><x-text-input wire:model="namaPenetap" class="w-full mt-1" /></div>
                    </div>
                </x-border-form>
            </div>

            <div class="sticky bottom-0 z-10 px-6 py-4 border-t bg-surface-soft border-hairline shrink-0 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-2">
                    <x-secondary-button type="button" wire:click="tutup">Tutup</x-secondary-button>
                    <x-secondary-button type="button"
                        x-on:click="window.dispatchEvent(new Event('dokumen-spo.flush')); $nextTick(() => $wire.simpanLaluCetak('docx'))">Simpan &amp; DOCX</x-secondary-button>
                    <x-cetak-button type="button" label="Simpan & PDF"
                        x-on:click="window.dispatchEvent(new Event('dokumen-spo.flush')); $nextTick(() => $wire.simpanLaluCetak('pdf'))" />
                    @if (DokumenService::bolehUbah())
                        <x-primary-button type="button"
                            x-on:click="window.dispatchEvent(new Event('dokumen-spo.flush')); $nextTick(() => $wire.simpan())">Simpan</x-primary-button>
                    @endif
                </div>
            </div>
        </div>
    </x-modal>
</div>
