<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Services\DokumenService;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

/**
 * Tambah / ubah / hapus unit organisasi: nama, induk, jenis, kepala.
 * Induk tidak boleh unit itu sendiri atau keturunannya (cegah lingkaran). Unit baru ditaruh paling akhir di induknya.
 */
new class extends Component {
    use WithRenderVersioningTrait;

    public const JENIS = ['DIREKSI' => 'Direksi', 'BAGIAN' => 'Bagian', 'INSTALASI' => 'Instalasi', 'UNIT' => 'Unit', 'KOMITE' => 'Komite', 'TIM' => 'Tim'];

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
        $this->form = ['nama' => '', 'induk_id' => '', 'jenis' => 'INSTALASI', 'nama_kepala' => ''];
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

    public function save(): void
    {
        abort_unless(DokumenService::bolehUbah(), 403);
        $this->validate([
            'form.nama' => 'required|string|max:150',
            'form.induk_id' => ['nullable', 'in:' . implode(',', array_keys($this->pilihan))],
            'form.jenis' => 'required|in:' . implode(',', array_keys(self::JENIS)),
            'form.nama_kepala' => 'nullable|string|max:150',
        ], [
            'form.nama.required' => 'Nama unit wajib diisi.',
            'form.induk_id.in' => 'Induk tidak boleh unit ini sendiri atau unit di bawahnya.',
        ]);

        $indukId = $this->form['induk_id'] !== '' ? (int) $this->form['induk_id'] : null;
        $data = ['nama' => trim($this->form['nama']), 'induk_id' => $indukId, 'jenis' => $this->form['jenis'],
            'nama_kepala' => trim($this->form['nama_kepala']) ?: null, 'updated_at' => now()];

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
            <div class="flex-1 px-6 py-5 bg-surface-soft dark:bg-gray-950/20">
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
                                @foreach ($this::JENIS as $k => $nm)<option value="{{ $k }}">{{ $nm }}</option>@endforeach
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

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto border-t bg-surface-soft border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-end gap-2">
                    <x-secondary-button type="button" x-on:click="tryClose()">Batal</x-secondary-button>
                    <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">Simpan</x-primary-button>
                </div>
            </div>

        </x-dirty-modal-content>
    </x-modal>
</div>
