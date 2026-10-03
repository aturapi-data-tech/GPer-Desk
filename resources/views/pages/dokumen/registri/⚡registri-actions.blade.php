<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Services\DokumenService;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

/**
 * Buat / ubah dokumen — sengaja ringkas: jenis + judul (+ induk bila nomornya ikut induk).
 * Nomor, tanggal terbit, revisi dan status diisi sistem; sesudah "Buat" penyusun isi langsung terbuka
 * (Buat → Susun isi → PDF). Hanya jenis yang punya penyusun (DOK_JENIS.BISA_DISUSUN = 'Y').
 */
new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode = 'create';
    public int $originalId = 0;
    public array $renderVersions = [];
    public array $form = [];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->form = ['jenis_kode' => 'SPO', 'dok_judul' => '', 'induk_id' => null, 'unit_kode' => ''];
        $this->resetValidation();
    }

    /** Jenis yang bisa dibuat di sini: yang punya penyusun isi; bila ada induk, hanya yang setingkat/lebih rendah. */
    public function getJenisBuatProperty()
    {
        $jenis = DokumenService::jenis()->where('bisa_disusun', 'Y');
        $induk = $this->induk;

        return $induk ? $jenis->filter(fn ($j) => (int) $j->tingkat >= (int) $induk->tingkat) : $jenis;
    }

    /** Dokumen induk terpilih (judul, nomor, tingkat) — untuk tampilan "Di bawah: …". */
    public function getIndukProperty(): ?object
    {
        if (! $this->form['induk_id']) {
            return null;
        }

        return DB::table('dok_dokumen as d')->join('dok_jenis as j', 'j.kode', '=', 'd.jenis_kode')
            ->where('d.dok_id', $this->form['induk_id'])->first(['d.dok_id', 'd.dok_judul', 'd.dok_no', 'j.nama as jenis_nama', 'j.tingkat']);
    }

    public function getJenisTerpilihProperty(): ?object
    {
        return DokumenService::jenis()[$this->form['jenis_kode']] ?? null;
    }

    #[On('dokumen.registri.openCreate')]
    public function openCreate(?int $indukId = null): void
    {
        abort_unless(DokumenService::bolehUbah(), 403);
        $this->resetForm();
        $this->formMode = 'create';
        $this->originalId = 0;
        $this->form['induk_id'] = $indukId;
        if ($indukId && ($induk = $this->induk)) {
            // jenis bawaan: tepat satu tingkat di bawah induk (SK → Pedoman, Pedoman → SPO); bila tidak ada, yang setingkat
            $pilihan = $this->jenisBuat;
            $this->form['jenis_kode'] = (string) ($pilihan->first(fn ($j) => (int) $j->tingkat === (int) $induk->tingkat + 1)?->kode
                ?? $pilihan->first(fn ($j) => (int) $j->tingkat === (int) $induk->tingkat)?->kode
                ?? $pilihan->first()?->kode ?? $this->form['jenis_kode']);
        }
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'dokumen-registri-actions');
    }

    #[On('dokumen.registri.openEdit')]
    public function openEdit(int $dokId): void
    {
        abort_unless(DokumenService::bolehUbah(), 403);
        $data = DokumenService::baca($dokId);
        if (! $data) return;
        [$row, $json] = $data;
        $this->resetForm();
        $this->formMode = 'edit';
        $this->originalId = $dokId;
        $this->form = [
            'jenis_kode' => $row->jenis_kode, 'dok_judul' => (string) ($json['judulAsli'] ?? $row->dok_judul),
            'induk_id' => $row->induk_id ? (int) $row->induk_id : null, 'unit_kode' => (string) ($row->unit_kode ?? ''),
        ];
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'dokumen-registri-actions');
    }

    #[On('lov.selected.dokumen-induk')]
    public function pilihInduk(string $target, array $payload): void
    {
        $this->form['induk_id'] = (int) $payload['dok_id'];
    }

    #[On('lov.cleared.dokumen-induk')]
    public function hapusInduk(): void
    {
        $this->form['induk_id'] = null;
    }

    #[On('dokumen.registri.requestDelete')]
    public function hapus(int $dokId): void
    {
        abort_unless(DokumenService::bolehUbah(), 403);
        try {
            DB::table('dok_akreditasi')->where('dok_id', $dokId)->delete();
            DB::table('dok_dokumen')->where('dok_id', $dokId)->delete();
            $this->dispatch('toast', type: 'success', message: 'Dokumen dihapus.');
            $this->dispatch('dokumen.registri.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Dokumen masih menjadi induk dokumen lain — hapus/pindahkan turunannya dulu.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        abort_unless(DokumenService::bolehUbah(), 403);
        $jenis = $this->jenisTerpilih;
        $this->validate([
            'form.jenis_kode' => 'required|in:' . $this->jenisBuat->keys()->implode(','),
            'form.dok_judul' => 'required|string|max:1000',
            'form.unit_kode' => ($jenis?->pakai_unit === 'Y' ? 'required' : 'nullable') . '|string|max:10',
        ], [
            'form.dok_judul.required' => 'Judul dokumen wajib diisi.',
            'form.unit_kode.required' => 'Pilih bagian — kode bagian masuk ke nomor dokumen.',
        ]);

        $header = [
            'jenis_kode' => $this->form['jenis_kode'],
            'dok_judul' => trim($this->form['dok_judul']),
            'induk_id' => $this->form['induk_id'] ?: null,      // semua jenis boleh berinduk (pohon registri)
            'unit_kode' => ($jenis?->pakai_unit === 'Y' ? $this->form['unit_kode'] : null) ?: null,
        ];
        if ($this->formMode === 'edit') {
            unset($header['jenis_kode']);                  // jenis menentukan format isi — tidak diganti sesudah dibuat
        } else {
            $header += ['status' => 'DRAF', 'no_revisi' => 0, 'klasifikasi' => 'BIASA', 'sumber' => 'INTERNAL'];
        }

        try {
            if ($this->formMode === 'edit') {
                DokumenService::pastikanTidakTerkunci($this->originalId);
                $json = DokumenService::baca($this->originalId)[1];
            } else {
                $json = DokumenService::jsonKosong();
                if ($jenis?->disahkan_oleh === 'Direktur') {
                    $json['penandatangan']['ditetapkan'] = config('dokumen.pejabat.direktur');
                }
            }
            $id = DokumenService::simpan($this->formMode === 'edit' ? $this->originalId : null, $header, $json,
                $this->formMode === 'edit' ? 'UBAH' : 'BUAT');
        } catch (\DomainException $e) {
            $this->addError('form.induk_id', $e->getMessage());
            return;
        }

        $baru = $this->formMode === 'create';
        $this->closeModal();
        $this->dispatch('dokumen.registri.saved');
        if ($baru) {
            $this->dispatch(DokumenService::penyusun($id), dokId: $id);   // langsung ke penyusun isi
        } else {
            $this->dispatch('toast', type: 'success', message: 'Dokumen tersimpan.');
        }
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'dokumen-registri-actions');
        $this->resetVersion();
    }
};
?>

<div>
    <x-modal name="dokumen-registri-actions" size="full" height="full" focusable>
        <div class="flex flex-col h-full">
            <div class="px-6 py-4 border-b bg-surface-soft border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <x-badge variant="brand">{{ $formMode === 'edit' ? 'Ubah' : 'Dokumen baru' }}</x-badge>
                        <h2 class="mt-2 ds-display-sm dark:text-gray-100">{{ $formMode === 'edit' ? 'Ubah Dokumen' : 'Buat Dokumen' }}</h2>
                        <p class="text-sm text-muted dark:text-gray-400">Nomor, tanggal terbit dan revisi diisi otomatis.</p>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="closeModal">✕</x-icon-button>
                </div>
            </div>

            <div class="flex-1 px-6 py-5 overflow-y-auto" wire:key="form-{{ $renderVersions['modal'] ?? 0 }}">
                <x-border-form title="Identitas Dokumen">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                        <div>
                            <x-input-label value="Jenis naskah" />
                            <x-select-input wire:model.live="form.jenis_kode" class="w-full mt-1" :disabled="$formMode === 'edit'">
                                @foreach ($this->jenisBuat as $j)
                                    <option value="{{ $j->kode }}">{{ $j->nama }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error :messages="$errors->get('form.jenis_kode')" class="mt-1" />
                        </div>
                        <div class="md:col-span-3">
                            <x-input-label value="Judul" />
                            <x-text-input wire:model="form.dok_judul" class="w-full mt-1" :error="$errors->has('form.dok_judul')"
                                placeholder="mis. Penggunaan Kode, Singkatan, dan Simbol dalam Rekam Medis" />
                            <x-input-error :messages="$errors->get('form.dok_judul')" class="mt-1" />
                        </div>
                        @if ($this->jenisTerpilih?->pakai_unit === 'Y')
                            <div>
                                <x-input-label value="Bagian" />
                                <x-select-input wire:model="form.unit_kode" class="w-full mt-1">
                                    <option value="">—</option>
                                    @foreach (DokumenService::unit() as $u)
                                        <option value="{{ $u->kode }}">{{ $u->kode }} · {{ $u->nama }}</option>
                                    @endforeach
                                </x-select-input>
                                <x-input-error :messages="$errors->get('form.unit_kode')" class="mt-1" />
                            </div>
                        @endif
                        @if ($this->induk && $this->jenisTerpilih?->model_nomor !== 'INDUK')
                            <div class="md:col-span-4">
                                <x-input-label value="Di bawah dokumen" />
                                <div class="flex items-center gap-2 px-3 py-2 mt-1 text-sm border rounded-lg border-hairline bg-canvas dark:bg-gray-800 dark:border-gray-700">
                                    <x-badge variant="gray">{{ $this->induk->jenis_nama }}</x-badge>
                                    <span class="flex-1 font-medium">{{ $this->induk->dok_judul }}</span>
                                    <span class="font-mono text-xs text-muted">{{ $this->induk->dok_no ?: 'belum bernomor' }}</span>
                                    <x-icon-button color="gray" type="button" wire:click="hapusInduk" title="Lepas dari induk">✕</x-icon-button>
                                </div>
                            </div>
                        @endif
                        @if ($this->jenisTerpilih?->model_nomor === 'INDUK')
                            <div class="md:col-span-2">
                                <livewire:lov.dokumen.lov-dokumen target="dokumen-induk" label="SK / Peraturan Direktur penetapnya"
                                    :initialDokId="$form['induk_id']" :maksTingkat="1" :kecualiDokId="$originalId ?: null"
                                    wire:key="lov-induk-{{ $renderVersions['modal'] ?? 0 }}-{{ $form['jenis_kode'] }}" />
                                <p class="mt-1 text-xs text-muted dark:text-gray-400">Nomor {{ strtolower($this->jenisTerpilih->nama) }} mengikuti nomor SK ini. Boleh diisi nanti.</p>
                                <x-input-error :messages="$errors->get('form.induk_id')" class="mt-1" />
                            </div>
                        @endif
                    </div>
                </x-border-form>
            </div>

            <div class="sticky bottom-0 z-10 px-6 py-4 border-t bg-surface-soft border-hairline shrink-0 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-2">
                    <x-secondary-button type="button" wire:click="closeModal">Batal</x-secondary-button>
                    <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                        {{ $formMode === 'edit' ? 'Simpan' : 'Buat & Susun Isi' }}
                    </x-primary-button>
                </div>
            </div>
        </div>
    </x-modal>
</div>
