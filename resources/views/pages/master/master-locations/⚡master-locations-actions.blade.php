<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode   = 'create';
    public int    $originalId = 0;
    public array  $renderVersions = [];
    protected array $renderAreas  = ['modal'];

    public array $form = [
        'name'        => '',
        'code'        => '',
        'building'    => '',
        'floor'       => '',
        'description' => '',
        'is_active'   => 1,
    ];

    public function mount(): void { $this->registerAreas(['modal']); }

    #[On('master.locations.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = 0;
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-locations-actions');
        $this->dispatch('focus-name');
    }

    #[On('master.locations.openEdit')]
    public function openEdit(int $id): void
    {
        $row = DB::table('locations')->where('id', $id)->first();
        if (!$row) return;

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $id;
        $this->form = [
            'name'        => (string) $row->name,
            'code'        => (string) ($row->code ?? ''),
            'building'    => (string) ($row->building ?? ''),
            'floor'       => (string) ($row->floor ?? ''),
            'description' => (string) ($row->description ?? ''),
            'is_active'   => (int) $row->is_active,
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-locations-actions');
        $this->dispatch('focus-name');
    }

    #[On('master.locations.requestDelete')]
    public function delete(int $id): void
    {
        try {
            $deleted = DB::table('locations')->where('id', $id)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Lokasi tidak ditemukan.');
                return;
            }
            $this->dispatch('toast', type: 'success', message: 'Lokasi berhasil dihapus.');
            $this->dispatch('master.locations.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Lokasi tidak bisa dihapus karena masih dipakai di tiket.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $unique = 'unique:locations,name';
        if ($this->formMode === 'edit') {
            $unique .= ',' . $this->originalId;
        }

        $rules = [
            'form.name'        => "required|string|max:100|{$unique}",
            'form.code'        => 'nullable|string|max:30',
            'form.building'    => 'nullable|string|max:100',
            'form.floor'       => 'nullable|string|max:30',
            'form.description' => 'nullable|string|max:1000',
            'form.is_active'   => 'required|boolean',
        ];

        $messages = [
            'form.name.required' => 'Nama lokasi wajib diisi.',
            'form.name.unique'   => 'Nama lokasi sudah digunakan.',
            'form.name.max'      => 'Nama lokasi maksimal 100 karakter.',
        ];

        $attributes = [
            'form.name'        => 'Nama Lokasi',
            'form.code'        => 'Kode',
            'form.building'    => 'Gedung',
            'form.floor'       => 'Lantai',
            'form.description' => 'Deskripsi',
            'form.is_active'   => 'Status',
        ];

        $this->validate($rules, $messages, $attributes);

        $payload = [
            'name'        => trim($this->form['name']),
            'code'        => $this->form['code'] !== '' ? trim($this->form['code']) : null,
            'building'    => $this->form['building'] !== '' ? trim($this->form['building']) : null,
            'floor'       => $this->form['floor'] !== '' ? trim($this->form['floor']) : null,
            'description' => $this->form['description'] !== '' ? trim($this->form['description']) : null,
            'is_active'   => (int) $this->form['is_active'],
            'updated_at'  => now(),
        ];

        if ($this->formMode === 'create') {
            DB::table('locations')->insert([...$payload, 'created_at' => now()]);
        } else {
            DB::table('locations')->where('id', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Lokasi berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.locations.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-locations-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = ['name' => '', 'code' => '', 'building' => '', 'floor' => '', 'description' => '', 'is_active' => 1];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-locations-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-locations-actions"
            event="master.locations.saved"
            label="Lokasi"
            :wireKey="$this->renderKey('modal', [$formMode, $originalId])">

            {{-- HEADER --}}
            <div class="relative px-6 py-5 bg-surface-soft">
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}" alt="Logo" class="block w-6 h-6 dark:hidden" />
                                <img src="{{ asset('images/Logogram white solid.png') }}" alt="Logo" class="hidden w-6 h-6 dark:block" />
                            </div>
                            <div>
                                <h2 class="ds-display-sm dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah Lokasi' : 'Tambah Lokasi' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                    Lengkapi informasi lokasi / ruangan.
                                </p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
                        </div>
                    </div>
                    <x-icon-button color="gray" type="button" x-on:click="tryClose()">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </x-icon-button>
                </div>
            </div>

            {{-- BODY --}}
            <div class="flex-1 px-4 py-4 bg-surface-soft dark:bg-gray-950/20" x-enter-chain
                 x-data
                 x-on:focus-name.window="$nextTick(() => setTimeout(() => $refs.inputName?.focus(), 150))">

                <x-border-form title="Data Lokasi">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div class="sm:col-span-2">
                                <x-input-label value="Nama Lokasi" />
                                <x-text-input wire:model.live="form.name" x-ref="inputName"
                                    maxlength="100"
                                    :error="$errors->has('form.name')"
                                    class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('form.name')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Kode" />
                                <x-text-input wire:model.live="form.code"
                                    maxlength="30"
                                    :error="$errors->has('form.code')"
                                    class="w-full mt-1 uppercase" />
                                <x-input-error :messages="$errors->get('form.code')" class="mt-1" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="Gedung" />
                                <x-text-input wire:model.live="form.building"
                                    maxlength="100"
                                    :error="$errors->has('form.building')"
                                    class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('form.building')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Lantai" />
                                <x-text-input wire:model.live="form.floor"
                                    maxlength="30"
                                    :error="$errors->has('form.floor')"
                                    class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('form.floor')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Status" />
                                <x-select-input wire:model.live="form.is_active" class="w-full mt-1">
                                    <option value="1">Aktif</option>
                                    <option value="0">Nonaktif</option>
                                </x-select-input>
                                <x-input-error :messages="$errors->get('form.is_active')" class="mt-1" />
                            </div>
                        </div>

                        <div>
                            <x-input-label value="Deskripsi" />
                            <x-text-input wire:model.live="form.description"
                                maxlength="1000"
                                :error="$errors->has('form.description')"
                                class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('form.description')" class="mt-1" />
                        </div>
                    </div>
                </x-border-form>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-surface-soft border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-end gap-2">
                    <x-secondary-button type="button" x-on:click="tryClose()">Batal</x-secondary-button>
                    <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                        <span wire:loading.remove>Simpan</span>
                        <span wire:loading>Saving...</span>
                    </x-primary-button>
                </div>
            </div>

        </x-dirty-modal-content>
    </x-modal>
</div>
