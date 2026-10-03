<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Services\DokumenService;
use App\Support\OracleLob;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

/**
 * Tambah / ubah / hapus unit organisasi: nama, induk, jenis, kepala, + ORG_JSON:
 *   {versi, kepala: {nik}, urgas: {namaJabatan, pengertian, persyaratan: {pendidikan, lain[]},
 *    tanggungJawabWewenang[], tugasPokok, uraianTugas[]}, jobdesc: [{jabatan, uraian[]}]}
 * urgas mengikuti format Uraian Tugas RS (URGAS_*.pdf, TKRS 3 EP a); atasan, bawahan & kedudukan diambil dari pohon.
 * Induk tidak boleh unit itu sendiri atau keturunannya (cegah lingkaran). Unit baru ditaruh paling akhir di induknya.
 */
new class extends Component {
    use WithRenderVersioningTrait;

    /** Pilihan jenis dari master ORG_JENIS. */
    public function getJenisProperty(): array
    {
        return DB::table('org_jenis')->orderBy('urut')->orderBy('nama')->pluck('nama', 'kode')->all();
    }

    public string $formMode = 'create';
    public int $originalId = 0;
    public array $renderVersions = [];
    public array $form = [];
    /** Isian ORG_JSON di form — daftar disunting sebagai teks satu butir per baris. */
    public array $urgas = [];
    /** @var array<int, array{jabatan: string, uraian: string}> */
    public array $jobdesc = [];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->form = ['nama' => '', 'induk_id' => '', 'jenis' => (string) array_key_first($this->jenis ?? []), 'nama_kepala' => ''];
        $this->urgas = ['nik' => '', 'namaJabatan' => '', 'pengertian' => '', 'pendidikan' => '', 'lain' => '',
            'tanggungJawabWewenang' => '', 'tugasPokok' => '', 'uraianTugas' => ''];
        $this->jobdesc = [];
        $this->resetValidation();
    }

    /** Pilihan induk: pohon berindentasi, tanpa unit ini & keturunannya. */
    public function getPilihanProperty(): array
    {
        $kecuali = $this->originalId ? array_map(fn ($r) => (int) $r->unit_id, DB::select(
            'SELECT unit_id FROM org_unit START WITH unit_id = ? CONNECT BY PRIOR unit_id = induk_id', [$this->originalId])) : [];

        return collect(DB::select('SELECT LEVEL lv, unit_id, nama FROM org_unit START WITH induk_id IS NULL
                CONNECT BY PRIOR unit_id = induk_id ORDER SIBLINGS BY urut, nama'))
            ->reject(fn ($r) => in_array((int) $r->unit_id, $kecuali, true))
            ->mapWithKeys(fn ($r) => [(int) $r->unit_id => str_repeat("\u{00A0}\u{00A0}\u{00A0}", $r->lv - 1) . $r->nama])->all();
    }

    #[On('master.organisasi.openCreate')]
    public function openCreate(?int $indukId = null): void
    {
        abort_unless(DokumenService::bolehUbah(), 403);
        $this->resetForm();
        $this->formMode = 'create';
        $this->originalId = 0;
        $this->form['induk_id'] = $indukId ? (string) $indukId : '';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-organisasi-actions');
    }

    #[On('master.organisasi.openEdit')]
    public function openEdit(int $id): void
    {
        abort_unless(DokumenService::bolehUbah(), 403);
        $r = DB::table('org_unit')->where('unit_id', $id)->first();
        if (! $r) return;
        $this->resetForm();
        $this->formMode = 'edit';
        $this->originalId = $id;
        $this->form = ['nama' => (string) $r->nama, 'induk_id' => (string) ($r->induk_id ?? ''),
            'jenis' => (string) $r->jenis, 'nama_kepala' => (string) ($r->nama_kepala ?? '')];
        $j = json_decode(OracleLob::read($r->org_json ?? null, 'org_unit', 'unit_id', $id, 'org_json') ?: '{}', true) ?: [];
        $baris = fn ($a) => implode("\n", (array) ($a ?? []));
        $u = $j['urgas'] ?? [];
        $this->urgas = ['nik' => (string) ($j['kepala']['nik'] ?? ''), 'namaJabatan' => (string) ($u['namaJabatan'] ?? ''),
            'pengertian' => (string) ($u['pengertian'] ?? ''), 'pendidikan' => (string) ($u['persyaratan']['pendidikan'] ?? ''),
            'lain' => $baris($u['persyaratan']['lain'] ?? []), 'tanggungJawabWewenang' => $baris($u['tanggungJawabWewenang'] ?? []),
            'tugasPokok' => (string) ($u['tugasPokok'] ?? ''), 'uraianTugas' => $baris($u['uraianTugas'] ?? [])];
        $this->jobdesc = array_map(fn ($x) => ['jabatan' => (string) ($x['jabatan'] ?? ''), 'uraian' => $baris($x['uraian'] ?? [])], $j['jobdesc'] ?? []);
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-organisasi-actions');
    }

    #[On('master.organisasi.requestDelete')]
    public function delete(int $id): void
    {
        abort_unless(DokumenService::bolehUbah(), 403);
        try {
            DB::table('org_unit')->where('unit_id', $id)->delete();
            $this->dispatch('toast', type: 'success', message: 'Unit dihapus.');
            $this->dispatch('master.organisasi.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Unit masih punya unit di bawahnya — pindahkan atau hapus dulu unit di bawahnya.');
                return;
            }
            throw $e;
        }
    }

    public function tambahJobdesc(): void
    {
        $this->jobdesc[] = ['jabatan' => '', 'uraian' => ''];
    }

    public function hapusJobdesc(int $i): void
    {
        unset($this->jobdesc[$i]);
        $this->jobdesc = array_values($this->jobdesc);
    }

    /** Susun ORG_JSON dari isian form (teks per baris → array). */
    private function jsonDariForm(): array
    {
        $daftar = fn (string $t) => array_values(array_filter(array_map('trim', preg_split('/\R/', $t))));
        $u = $this->urgas;

        return [
            'versi' => 1,
            'kepala' => ['nik' => trim($u['nik'])],
            'urgas' => [
                'namaJabatan' => trim($u['namaJabatan']), 'pengertian' => trim($u['pengertian']),
                'persyaratan' => ['pendidikan' => trim($u['pendidikan']), 'lain' => $daftar($u['lain'])],
                'tanggungJawabWewenang' => $daftar($u['tanggungJawabWewenang']),
                'tugasPokok' => trim($u['tugasPokok']), 'uraianTugas' => $daftar($u['uraianTugas']),
            ],
            'jobdesc' => array_values(array_filter(array_map(fn ($x) => ['jabatan' => trim($x['jabatan']), 'uraian' => $daftar($x['uraian'])],
                $this->jobdesc), fn ($x) => $x['jabatan'] !== '' || $x['uraian'])),
        ];
    }

    public function hapusDariForm(): void
    {
        $sebelum = DB::table('org_unit')->where('unit_id', $this->originalId)->exists();
        $this->delete($this->originalId);
        if ($sebelum && ! DB::table('org_unit')->where('unit_id', $this->originalId)->exists()) {
            $this->closeModal();
        }
    }

    public function save(): void
    {
        abort_unless(DokumenService::bolehUbah(), 403);
        $this->validate([
            'form.nama' => 'required|string|max:150',
            'form.induk_id' => ['nullable', 'in:' . implode(',', array_keys($this->pilihan))],
            'form.jenis' => 'required|in:' . implode(',', array_keys($this->jenis)),
            'form.nama_kepala' => 'nullable|string|max:150',
            'urgas.nik' => 'nullable|string|max:30',
            'jobdesc.*.jabatan' => 'required|string|max:150',
        ], [
            'jobdesc.*.jabatan.required' => 'Isi nama jabatan untuk job description ini.',
            'form.nama.required' => 'Nama unit wajib diisi.',
            'form.induk_id.in' => 'Induk tidak boleh unit ini sendiri atau unit di bawahnya.',
        ]);

        $indukId = $this->form['induk_id'] !== '' ? (int) $this->form['induk_id'] : null;
        $data = ['nama' => trim($this->form['nama']), 'induk_id' => $indukId, 'jenis' => $this->form['jenis'],
            'nama_kepala' => trim($this->form['nama_kepala']) ?: null, 'updated_at' => now(),
            // TANPA JSON_UNESCAPED_UNICODE: basis data ber-charset Latin-1 — escape \uXXXX menjaga JSON murni ASCII
            'org_json' => json_encode($this->jsonDariForm(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)];

        if ($this->formMode === 'create') {
            $urut = 1 + (int) DB::table('org_unit')->when($indukId, fn ($q) => $q->where('induk_id', $indukId), fn ($q) => $q->whereNull('induk_id'))->max('urut');
            $id = (int) DB::selectOne('SELECT org_unit_seq.NEXTVAL AS id FROM dual')->id;   // hindari insertGetId oci8
            DB::table('org_unit')->insert([...$data, 'unit_id' => $id, 'urut' => $urut, 'created_at' => now()]);
        } else {
            DB::table('org_unit')->where('unit_id', $this->originalId)->update($data);
        }

        $this->dispatch('toast', type: 'success', message: 'Unit disimpan.');
        $this->closeModal();
        $this->dispatch('master.organisasi.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-organisasi-actions');
        $this->resetVersion();
    }
};
?>

<div>
    <x-modal name="master-organisasi-actions" size="full" height="full" focusable>
        <x-dirty-modal-content name="master-organisasi-actions" event="master.organisasi.saved" label="Unit"
            :wireKey="$this->renderKey('modal', [$formMode, $originalId])">

            {{-- HEADER --}}
            <div class="px-6 py-4 border-b bg-surface-soft border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">{{ $formMode === 'edit' ? 'Ubah' : 'Unit baru' }}</x-badge>
                        <h2 class="mt-2 ds-display-sm dark:text-gray-100">{{ $formMode === 'edit' ? 'Ubah Unit' : 'Tambah Unit' }}</h2>
                    </div>
                    <x-icon-button color="gray" type="button" x-on:click="tryClose()">✕</x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-6 py-5 space-y-4 bg-surface-soft dark:bg-gray-950/20" x-data="{ t: 'unit' }">
                <x-tabs variant="pill">
                    <x-tab active-expr="t === 'unit'" x-on:click="t = 'unit'">Unit</x-tab>
                    <x-tab active-expr="t === 'urgas'" x-on:click="t = 'urgas'">Uraian Tugas Kepala</x-tab>
                    <x-tab active-expr="t === 'jobdesc'" x-on:click="t = 'jobdesc'">Job Description <span class="ml-1 text-xs opacity-70">({{ count($jobdesc) }})</span></x-tab>
                </x-tabs>

                <div x-show="t === 'unit'">
                <x-border-form title="Unit Organisasi">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <x-input-label value="Nama unit" />
                            <x-text-input wire:model="form.nama" class="w-full mt-1" :error="$errors->has('form.nama')" placeholder="mis. Instalasi Gawat Darurat" />
                            <x-input-error :messages="$errors->get('form.nama')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Induk (atasan langsung)" />
                            <x-select-input wire:model="form.induk_id" class="w-full mt-1">
                                <option value="">— puncak (tanpa induk) —</option>
                                @foreach ($this->pilihan as $id => $nm)
                                    <option value="{{ $id }}">{{ $nm }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error :messages="$errors->get('form.induk_id')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Jenis" />
                            <x-select-input wire:model="form.jenis" class="w-full mt-1">
                                @foreach ($this->jenis as $k => $nm)<option value="{{ $k }}">{{ $nm }}</option>@endforeach
                            </x-select-input>
                        </div>
                        <div>
                            <x-input-label value="Kepala (nama & gelar)" />
                            <x-text-input wire:model="form.nama_kepala" class="w-full mt-1" placeholder="kosongkan bila belum ditetapkan" />
                            <x-input-error :messages="$errors->get('form.nama_kepala')" class="mt-1" />
                        </div>
                    </div>
                </x-border-form>
                </div>

                {{-- Uraian tugas kepala — format URGAS RS (TKRS 3 EP a) --}}
                <div x-show="t === 'urgas'" x-cloak>
                    <x-border-form title="Uraian Tugas Kepala Unit">
                        <p class="mb-3 text-xs text-muted dark:text-gray-400">
                            Mengikuti format Uraian Tugas RS. Nama pejabat, jabatan, atasan langsung, bawahan dan kedudukan dalam struktur
                            diambil dari pohon organisasi. Isian daftar: satu butir per baris.
                        </p>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <x-input-label value="Nama jabatan" />
                                <x-text-input wire:model="urgas.namaJabatan" class="w-full mt-1" placeholder="mis. Kepala Instalasi Gawat Darurat" />
                            </div>
                            <div>
                                <x-input-label value="NIK pejabat" />
                                <x-text-input wire:model="urgas.nik" class="w-full mt-1 font-mono" placeholder="mis. 140.5121.21" />
                                <x-input-error :messages="$errors->get('urgas.nik')" class="mt-1" />
                            </div>
                            <div class="md:col-span-2">
                                <x-input-label value="Pengertian" />
                                <x-textarea wire:model="urgas.pengertian" rows="2" class="w-full mt-1" placeholder="Seorang tenaga … yang diberi tanggung jawab dan wewenang mengelola …" />
                            </div>
                            <div>
                                <x-input-label value="Persyaratan — pendidikan" />
                                <x-textarea wire:model="urgas.pendidikan" rows="3" class="w-full mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Persyaratan — lain-lain (satu per baris)" />
                                <x-textarea wire:model="urgas.lain" rows="3" class="w-full mt-1" />
                            </div>
                            <div class="md:col-span-2">
                                <x-input-label value="Tugas pokok" />
                                <x-textarea wire:model="urgas.tugasPokok" rows="2" class="w-full mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Tanggung jawab dan wewenang (satu per baris)" />
                                <x-textarea wire:model="urgas.tanggungJawabWewenang" rows="8" class="w-full mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Uraian tugas (satu per baris)" />
                                <x-textarea wire:model="urgas.uraianTugas" rows="8" class="w-full mt-1" />
                            </div>
                        </div>
                    </x-border-form>
                </div>

                {{-- Job description jabatan staf di unit ini --}}
                <div x-show="t === 'jobdesc'" x-cloak>
                    <x-border-form title="Job Description Staf">
                        <p class="mb-3 text-xs text-muted dark:text-gray-400">Satu blok per jabatan staf di unit ini (mis. Pelaksana IGD, Perawat Pelaksana). Uraian: satu tugas per baris.</p>
                        <div class="space-y-4">
                            @foreach ($jobdesc as $i => $jd)
                                <div class="p-3 border rounded-xl border-hairline dark:border-gray-700" wire:key="jd-{{ $i }}">
                                    <div class="flex items-start gap-2">
                                        <div class="flex-1">
                                            <x-input-label value="Jabatan" />
                                            <x-text-input wire:model="jobdesc.{{ $i }}.jabatan" class="w-full mt-1" placeholder="mis. Pelaksana Instalasi Gawat Darurat" />
                                            <x-input-error :messages="$errors->get('jobdesc.' . $i . '.jabatan')" class="mt-1" />
                                        </div>
                                        <x-icon-button color="red" type="button" wire:click="hapusJobdesc({{ $i }})" title="Hapus job description ini" class="mt-6">✕</x-icon-button>
                                    </div>
                                    <div class="mt-3">
                                        <x-input-label value="Uraian tugas (satu per baris)" />
                                        <x-textarea wire:model="jobdesc.{{ $i }}.uraian" rows="5" class="w-full mt-1" />
                                    </div>
                                </div>
                            @endforeach
                            <x-secondary-button type="button" wire:click="tambahJobdesc">+ Jabatan</x-secondary-button>
                        </div>
                    </x-border-form>
                </div>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto border-t bg-surface-soft border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-end gap-2">
                    @if ($formMode === 'edit')
                        <x-confirm-button variant="danger" action="hapusDariForm" title="Hapus unit"
                            message="Hapus {{ $form['nama'] }}? Unit yang masih punya unit di bawahnya tidak bisa dihapus." class="mr-auto">Hapus</x-confirm-button>
                    @endif
                    <x-secondary-button type="button" x-on:click="tryClose()">Batal</x-secondary-button>
                    <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">Simpan</x-primary-button>
                </div>
            </div>

        </x-dirty-modal-content>
    </x-modal>
</div>
