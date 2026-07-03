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
        'name'      => '',
        'level'     => 0,
        'color'     => '#6b7280',
        'sla_hours' => '',
        'is_active' => 1,
    ];

    public function mount(): void { $this->registerAreas(['modal']); }

    #[On('master.priorities.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = 0;
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-priorities-actions');
        $this->dispatch('focus-name');
    }

    #[On('master.priorities.openEdit')]
    public function openEdit(int $id): void
    {
        $row = DB::table('priorities')->where('id', $id)->first();
        if (!$row) return;

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $id;
        $this->form = [
            'name'      => (string) $row->name,
            'level'     => (int) $row->level,
            'color'     => (string) ($row->color ?? '#6b7280'),
            'sla_hours' => $row->sla_hours !== null ? (string) $row->sla_hours : '',
            'is_active' => (int) $row->is_active,
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-priorities-actions');
        $this->dispatch('focus-name');
    }

    #[On('master.priorities.requestDelete')]
    public function delete(int $id): void
    {
        try {
            $deleted = DB::table('priorities')->where('id', $id)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Prioritas tidak ditemukan.');
                return;
            }
            $this->dispatch('toast', type: 'success', message: 'Prioritas berhasil dihapus.');
            $this->dispatch('master.priorities.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Prioritas tidak bisa dihapus karena masih dipakai di tiket.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $unique = 'unique:priorities,name';
        if ($this->formMode === 'edit') {
            $unique .= ',' . $this->originalId;
        }

        $rules = [
            'form.name'      => "required|string|max:50|{$unique}",
            'form.level'     => 'required|integer|min:0|max:999',
            'form.color'     => 'required|string|max:20',
            'form.sla_hours' => 'nullable|integer|min:0',
            'form.is_active' => 'required|boolean',
        ];

        $messages = [
            'form.name.required'  => 'Nama prioritas wajib diisi.',
            'form.name.unique'    => 'Nama prioritas sudah digunakan.',
            'form.level.required' => 'Level wajib diisi.',
            'form.color.required' => 'Warna wajib diisi.',
        ];

        $attributes = [
            'form.name'      => 'Nama Prioritas',
            'form.level'     => 'Level',
            'form.color'     => 'Warna',
            'form.sla_hours' => 'SLA (jam)',
            'form.is_active' => 'Status',
        ];

        $this->validate($rules, $messages, $attributes);

        $payload = [
            'name'       => trim($this->form['name']),
            'level'      => (int) $this->form['level'],
            'color'      => trim($this->form['color']),
            'sla_hours'  => $this->form['sla_hours'] !== '' ? (int) $this->form['sla_hours'] : null,
            'is_active'  => (int) $this->form['is_active'],
            'updated_at' => now(),
        ];

        if ($this->formMode === 'create') {
            DB::table('priorities')->insert([...$payload, 'created_at' => now()]);
        } else {
            DB::table('priorities')->where('id', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Prioritas berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.priorities.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-priorities-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = ['name' => '', 'level' => 0, 'color' => '#6b7280', 'sla_hours' => '', 'is_active' => 1];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-priorities-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-priorities-actions"
            event="master.priorities.saved"
            label="Prioritas"
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
                                    {{ $formMode === 'edit' ? 'Ubah Prioritas' : 'Tambah Prioritas' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                    Atur tingkat, SLA, dan warna badge prioritas.
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

                <x-border-form title="Data Prioritas">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="Nama Prioritas" />
                                <x-text-input wire:model.live="form.name" x-ref="inputName"
                                    maxlength="50"
                                    :error="$errors->has('form.name')"
                                    class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('form.name')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Level" />
                                <x-text-input wire:model.live="form.level"
                                    type="number" min="0" max="999"
                                    :error="$errors->has('form.level')"
                                    class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('form.level')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="SLA (jam)" />
                                <x-text-input wire:model.live="form.sla_hours"
                                    type="number" min="0"
                                    :error="$errors->has('form.sla_hours')"
                                    class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('form.sla_hours')" class="mt-1" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="Warna Badge" />
                                <div class="flex items-center gap-2 mt-1">
                                    <input type="color" wire:model.live="form.color"
                                        class="w-10 h-10 p-1 border rounded-lg cursor-pointer border-hairline dark:border-gray-600 bg-canvas" />
                                    <x-text-input wire:model.live="form.color"
                                        maxlength="20"
                                        :error="$errors->has('form.color')"
                                        class="flex-1 font-mono" />
                                </div>
                                <x-input-error :messages="$errors->get('form.color')" class="mt-1" />
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
