<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use App\Services\DokumenService;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait, WithFileUploads;

    public string $formMode = 'create';
    public int $originalId = 0;
    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    public array $form = [];
    /** baris kaitan akreditasi: [{bab, standar, ep, elemen}] */
    public array $akreditasi = [];
    /** berkas yang sudah tercatat (dari JSON) */
    public array $berkas = [];
    public $unggahan = null;
    public string $ringkasan = '';

    public function mount(): void
    {
        $this->registerAreas(['modal']);
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->form = [
            'jenis_kode' => 'SPO', 'induk_id' => null, 'dok_judul' => '', 'dok_no' => '', 'unit_kode' => '',
            'tgl_terbit' => '', 'tgl_kaji_ulang' => '', 'no_revisi' => 0, 'status' => 'DRAF',
            'klasifikasi' => 'BIASA', 'sumber' => 'INTERNAL',
        ];
        $this->akreditasi = [];
        $this->berkas = [];
        $this->unggahan = null;
        $this->ringkasan = '';
        $this->resetValidation();
    }

    #[On('dokumen.registri.openCreate')]
    public function openCreate(?int $indukId = null): void
    {
        abort_unless(DokumenService::bolehUbah(), 403);
        $this->resetForm();
        $this->formMode = 'create';
        $this->originalId = 0;
        if ($indukId) {
            $this->form['induk_id'] = $indukId;
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
        foreach (array_keys($this->form) as $k) {
            $this->form[$k] = $row->{$k} ?? $this->form[$k];
        }
        $this->form['tgl_terbit'] = $row->tgl_terbit ? substr((string) $row->tgl_terbit, 0, 10) : '';
        $this->form['tgl_kaji_ulang'] = $row->tgl_kaji_ulang ? substr((string) $row->tgl_kaji_ulang, 0, 10) : '';
        $this->form['induk_id'] = $row->induk_id ? (int) $row->induk_id : null;
        $this->akreditasi = $json['akreditasi'] ?? [];
        $this->berkas = $json['berkas'] ?? [];
        $this->ringkasan = (string) ($json['ringkasan'] ?? '');
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

    public function tambahAkreditasi(): void
    {
        $this->akreditasi[] = ['bab' => '', 'standar' => '', 'ep' => '', 'elemen' => ''];
    }

    public function hapusAkreditasi(int $i): void
    {
        unset($this->akreditasi[$i]);
        $this->akreditasi = array_values($this->akreditasi);
    }

    public function hapusBerkas(string $id): void
    {
        $this->berkas = array_values(array_filter($this->berkas, fn ($b) => ($b['id'] ?? '') !== $id));
    }

    /** Tingkat jenis terpilih — LOV induk hanya menawarkan yang setingkat/lebih tinggi. */
    public function getTingkatProperty(): int
    {
        return (int) (DokumenService::jenis()[$this->form['jenis_kode']]->tingkat ?? 5);
    }

    #[On('dokumen.registri.requestDelete')]
    public function hapus(int $dokId): void
    {
        abort_unless(DokumenService::bolehUbah(), 403);
        try {
            DB::table('dok_dokumen')->where('dok_id', $dokId)->delete();
            $this->dispatch('toast', type: 'success', message: 'Dokumen dihapus dari registri.');
            $this->dispatch('dokumen.registri.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Dokumen masih menjadi induk / pengganti dokumen lain — pindahkan turunannya dulu.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        abort_unless(DokumenService::bolehUbah(), 403);
        $this->validate([
            'form.jenis_kode' => 'required|string',
            'form.dok_judul' => 'required|string|max:1000',
            'form.dok_no' => 'nullable|string|max:150',
            'form.tgl_terbit' => 'nullable|date',
            'form.tgl_kaji_ulang' => 'nullable|date',
            'form.no_revisi' => 'required|integer|min:0|max:' . config('dokumen.revisi_maks'),
            'form.status' => 'required|in:' . implode(',', array_keys(DokumenService::STATUS)),
            'form.klasifikasi' => 'required|in:' . implode(',', array_keys(DokumenService::KLASIFIKASI)),
            'akreditasi.*.bab' => 'required|in:' . implode(',', array_keys(config('dokumen.pokja'))),
            'unggahan' => 'nullable|file|max:20480|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
        ], [
            'form.dok_judul.required' => 'Judul dokumen wajib diisi.',
            'form.no_revisi.max' => 'Revisi maksimal 04 — revisi ke-5 wajib dokumen baru (Pedoman hal. 71).',
            'akreditasi.*.bab.required' => 'Pilih bab akreditasi.',
            'unggahan.mimes' => 'Berkas harus PDF, Word, Excel, atau gambar.',
        ]);

        $header = [
            'jenis_kode' => $this->form['jenis_kode'],
            'induk_id' => $this->form['induk_id'] ?: null,
            'dok_judul' => trim($this->form['dok_judul']),
            'dok_no' => trim((string) $this->form['dok_no']) ?: null,
            'unit_kode' => $this->form['unit_kode'] ?: null,
            'tgl_terbit' => $this->form['tgl_terbit'] ?: null,
            'tgl_kaji_ulang' => $this->form['tgl_kaji_ulang'] ?: null,
            'no_revisi' => (int) $this->form['no_revisi'],
            'status' => $this->form['status'],
            'klasifikasi' => $this->form['klasifikasi'],
            'sumber' => $this->form['sumber'] ?: 'INTERNAL',
            'pokja' => implode(',', array_values(array_unique(array_filter(array_column($this->akreditasi, 'bab'))))) ?: null,
        ];
        // nomor urut dari nomor yang diketik manual (supaya penghitung bisa disinkronkan)
        if ($header['dok_no'] && preg_match('/^(\d{1,6})(?:\.\d+)?\//', $header['dok_no'], $m)) {
            $header['dok_no_urut'] = (int) $m[1];
        }

        $berkas = $this->berkas;
        if ($this->unggahan) {
            $folder = config('dokumen.folder_unggah') . '/' . now()->format('Y/m');
            $nama = $this->unggahan->getClientOriginalName();
            $path = $this->unggahan->storeAs($folder, now()->format('His') . '-' . Str::slug(pathinfo($nama, PATHINFO_FILENAME)) . '.' . $this->unggahan->getClientOriginalExtension(), 'local');
            $berkas[] = [
                'id' => (string) Str::uuid(), 'peran' => 'UNGGAH', 'lokasi' => 'unggah', 'path' => $path,
                'nama' => $nama, 'ukuran' => $this->unggahan->getSize(), 'mime' => $this->unggahan->getMimeType(),
                'waktu' => now()->format('Y-m-d H:i:s'), 'oleh' => DokumenService::namaPengguna(),
            ];
        }

        try {
            if ($this->formMode === 'edit') {
                DokumenService::pastikanTidakTerkunci($this->originalId);
            }
            $json = $this->formMode === 'edit' ? DokumenService::baca($this->originalId)[1] : DokumenService::jsonKosong();
            $json['akreditasi'] = array_values($this->akreditasi);
            $json['berkas'] = $berkas;
            $json['ringkasan'] = $this->ringkasan;
            if (empty($json['penandatangan']['ditetapkan']) && ($jenis = DokumenService::jenis()[$header['jenis_kode']] ?? null) && $jenis->disahkan_oleh === 'Direktur') {
                $json['penandatangan']['ditetapkan'] = config('dokumen.pejabat.direktur');
            }
            $id = DokumenService::simpan($this->formMode === 'edit' ? $this->originalId : null, $header, $json,
                $this->formMode === 'edit' ? 'UBAH' : 'BUAT', $this->unggahan ? 'unggah ' . $this->unggahan->getClientOriginalName() : '');
        } catch (\DomainException $e) {
            $this->addError('form.induk_id', $e->getMessage());
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Dokumen tersimpan.');
        $this->closeModal();
        $this->dispatch('dokumen.registri.saved');
        $this->dispatch('dokumen.registri.openDetail', dokId: $id);
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
        <x-dirty-modal-content name="dokumen-registri-actions" event="dokumen.registri.saved" label="Dokumen"
            :wireKey="$this->renderKey('modal', [$formMode, $originalId])">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 bg-surface-soft">
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <h2 class="ds-display-sm dark:text-gray-100">{{ $formMode === 'edit' ? 'Ubah Dokumen' : 'Tambah Dokumen' }}</h2>
                        <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                            Tingkatan & penomoran mengikuti Pedoman Tata Naskah (BAB IV.C, BAB V).
                        </p>
                        <div class="mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">{{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}</x-badge>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" x-on:click="tryClose()">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 space-y-4 bg-surface-soft dark:bg-gray-950/20">
                <x-border-form title="Identitas Dokumen">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                        <div>
                            <x-input-label value="Jenis naskah" />
                            <x-select-input wire:model.live="form.jenis_kode" class="w-full mt-1">
                                @foreach (DokumenService::jenis()->groupBy('tingkat') as $t => $grup)
                                    <optgroup label="Tingkat {{ $t }} · {{ DokumenService::TINGKAT[$t] ?? '' }}">
                                        @foreach ($grup as $j)
                                            <option value="{{ $j->kode }}">{{ $j->nama }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </x-select-input>
                        </div>
                        <div class="md:col-span-3">
                            <x-input-label value="Judul" />
                            <x-text-input wire:model="form.dok_judul" class="w-full mt-1" :error="$errors->has('form.dok_judul')"
                                placeholder="mis. Penggunaan Kode, Singkatan, dan Simbol dalam Rekam Medis" />
                            <x-input-error :messages="$errors->get('form.dok_judul')" class="mt-1" />
                        </div>
                        <div class="md:col-span-2">
                            <livewire:lov.dokumen.lov-dokumen target="dokumen-induk" label="Dokumen induk (lebih tinggi)"
                                :initialDokId="$form['induk_id']" :maksTingkat="$this->tingkat" :kecualiDokId="$originalId ?: null"
                                wire:key="lov-induk-{{ $renderVersions['modal'] ?? 0 }}" />
                            <x-input-error :messages="$errors->get('form.induk_id')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Bagian / unit (kode nomor)" />
                            <x-select-input wire:model="form.unit_kode" class="w-full mt-1">
                                <option value="">—</option>
                                @foreach (DokumenService::unit() as $u)
                                    <option value="{{ $u->kode }}">{{ $u->kode }} · {{ $u->nama }}</option>
                                @endforeach
                            </x-select-input>
                        </div>
                        <div>
                            <x-input-label value="Klasifikasi keamanan" />
                            <x-select-input wire:model="form.klasifikasi" class="w-full mt-1">
                                @foreach (DokumenService::KLASIFIKASI as $k => $nm)
                                    <option value="{{ $k }}">{{ $nm }}</option>
                                @endforeach
                            </x-select-input>
                        </div>
                        <div class="md:col-span-4">
                            <x-input-label value="Ringkasan (opsional)" />
                            <x-textarea wire:model="ringkasan" rows="2" class="w-full mt-1" />
                        </div>
                    </div>
                </x-border-form>

                <x-border-form title="Penomoran, Revisi & Status">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-6">
                        <div class="md:col-span-2">
                            <x-input-label value="Nomor dokumen" />
                            <x-text-input wire:model="form.dok_no" class="w-full mt-1 font-mono" placeholder="kosongkan → ambil nomor otomatis di detail" />
                            <p class="mt-1 text-xs text-muted dark:text-gray-400">
                                Pola: {{ DokumenService::contohNomor(DokumenService::jenis()[$form['jenis_kode']] ?? (object) ['pola_nomor' => null]) }}
                            </p>
                        </div>
                        <div>
                            <x-input-label value="Tanggal terbit" />
                            <x-text-input type="date" wire:model="form.tgl_terbit" class="w-full mt-1" />
                        </div>
                        <div>
                            <x-input-label value="No. revisi" />
                            <x-select-input wire:model="form.no_revisi" class="w-full mt-1">
                                @for ($r = 0; $r <= config('dokumen.revisi_maks'); $r++)
                                    <option value="{{ $r }}">{{ $r }}</option>
                                @endfor
                            </x-select-input>
                        </div>
                        <div>
                            <x-input-label value="Status" />
                            <x-select-input wire:model="form.status" class="w-full mt-1">
                                @foreach (DokumenService::STATUS as $k => $nm)
                                    <option value="{{ $k }}">{{ $nm }}</option>
                                @endforeach
                            </x-select-input>
                        </div>
                        <div>
                            <x-input-label value="Kaji ulang" />
                            <x-text-input type="date" wire:model="form.tgl_kaji_ulang" class="w-full mt-1" />
                        </div>
                    </div>
                </x-border-form>

                <x-border-form title="Kaitan Akreditasi (Std KEMKES 2024)">
                    <div class="space-y-2">
                        @foreach ($akreditasi as $i => $ak)
                            <div class="grid items-end grid-cols-12 gap-2" wire:key="ak-{{ $i }}">
                                <div class="col-span-3">
                                    <x-select-input wire:model="akreditasi.{{ $i }}.bab" class="w-full" :error="$errors->has('akreditasi.' . $i . '.bab')">
                                        <option value="">Bab…</option>
                                        @foreach (config('dokumen.pokja') as $k => $nm)
                                            <option value="{{ $k }}">{{ $k }} · {{ $nm }}</option>
                                        @endforeach
                                    </x-select-input>
                                </div>
                                <div class="col-span-3"><x-text-input wire:model="akreditasi.{{ $i }}.standar" class="w-full" placeholder="Standar, mis. MRMIK 9" /></div>
                                <div class="col-span-2"><x-text-input wire:model="akreditasi.{{ $i }}.ep" class="w-full" placeholder="EP, mis. a" /></div>
                                <div class="col-span-3">
                                    <x-select-input wire:model="akreditasi.{{ $i }}.elemen" class="w-full">
                                        <option value="">Elemen bukti…</option>
                                        @foreach (['R' => 'R · Regulasi', 'D' => 'D · Dokumen', 'O' => 'O · Observasi', 'W' => 'W · Wawancara', 'S' => 'S · Simulasi'] as $k => $nm)
                                            <option value="{{ $k }}">{{ $nm }}</option>
                                        @endforeach
                                    </x-select-input>
                                </div>
                                <div class="col-span-1 text-right">
                                    <x-icon-button color="red" type="button" wire:click="hapusAkreditasi({{ $i }})" title="Hapus">✕</x-icon-button>
                                </div>
                            </div>
                        @endforeach
                        <x-secondary-button type="button" wire:click="tambahAkreditasi">+ Kaitan EP</x-secondary-button>
                    </div>
                </x-border-form>

                <x-border-form title="Berkas">
                    <div class="space-y-3">
                        @forelse ($berkas as $b)
                            <div class="flex items-center justify-between gap-3 px-3 py-2 text-sm border rounded-lg border-hairline dark:border-gray-700" wire:key="bk-{{ $b['id'] }}">
                                <div class="min-w-0">
                                    <div class="font-medium truncate">{{ $b['nama'] }}</div>
                                    <div class="text-xs text-muted">{{ $b['peran'] }} · {{ $b['lokasi'] === 'impor' ? 'arsip akreditasi' : 'unggahan' }} · {{ number_format(($b['ukuran'] ?? 0) / 1024, 0, ',', '.') }} KB</div>
                                </div>
                                <x-icon-button color="red" type="button" wire:click="hapusBerkas('{{ $b['id'] }}')" title="Lepas dari dokumen">✕</x-icon-button>
                            </div>
                        @empty
                            <p class="text-sm text-muted">Belum ada berkas.</p>
                        @endforelse
                        <div>
                            <x-input-label value="Unggah berkas (PDF / Word / Excel / gambar, maks. 20 MB)" />
                            <input type="file" wire:model="unggahan" class="block w-full mt-1 text-sm" />
                            <div wire:loading wire:target="unggahan" class="mt-1 text-xs text-muted">Mengunggah…</div>
                            <x-input-error :messages="$errors->get('unggahan')" class="mt-1" />
                        </div>
                    </div>
                </x-border-form>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto border-t bg-surface-soft border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-2">
                    <x-secondary-button type="button" x-on:click="tryClose()">Batal</x-secondary-button>
                    <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="save">Simpan</span>
                        <span wire:loading wire:target="save">Menyimpan…</span>
                    </x-primary-button>
                </div>
            </div>
        </x-dirty-modal-content>
    </x-modal>
</div>
