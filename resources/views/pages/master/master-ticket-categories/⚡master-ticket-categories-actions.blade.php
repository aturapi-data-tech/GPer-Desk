<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
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
        'sector_id'   => '',
        'description' => '',
        'is_active'   => 1,
    ];

    public function mount(): void
    {
        $this->registerAreas(['modal']);
    }

    /** Sektor penanganan (departemen is_sector=1): IT, CSSD, IPS. */
    #[Computed]
    public function sectors(): array
    {
        return DB::table('departments')->where('is_active', 1)->where('is_sector', 1)
            ->orderBy('name')->pluck('name', 'id')->all();
    }

    #[On('master.ticket-categories.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = 0;
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-ticket-categories-actions');
        $this->dispatch('focus-name');
    }

    #[On('master.ticket-categories.openEdit')]
    public function openEdit(int $id): void
    {
        $row = DB::table('ticket_categories')->where('id', $id)->first();
        if (!$row) return;

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $id;
        $this->form = [
            'name'        => (string) $row->name,
            'code'        => (string) ($row->code ?? ''),
            'sector_id'   => $row->sector_id ? (string) $row->sector_id : '',
            'description' => (string) ($row->description ?? ''),
            'is_active'   => (int) $row->is_active,
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'master-ticket-categories-actions');
        $this->dispatch('focus-name');
    }

    #[On('master.ticket-categories.requestDelete')]
    public function delete(int $id): void
    {
        try {
            $deleted = DB::table('ticket_categories')->where('id', $id)->delete();
            if ($deleted === 0) {
                $this->dispatch('toast', type: 'error', message: 'Kategori tidak ditemukan.');
                return;
            }
            $this->dispatch('toast', type: 'success', message: 'Kategori berhasil dihapus.');
            $this->dispatch('master.ticket-categories.saved');
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'ORA-02292')) {
                $this->dispatch('toast', type: 'error', message: 'Kategori tidak bisa dihapus karena masih dipakai di tiket.');
                return;
            }
            throw $e;
        }
    }

    public function save(): void
    {
        $unique = 'unique:ticket_categories,name';
        if ($this->formMode === 'edit') {
            $unique .= ',' . $this->originalId;
        }

        $rules = [
            'form.name'        => "required|string|max:100|{$unique}",
            'form.code'        => 'nullable|string|max:30',
            'form.sector_id'   => 'required|integer|exists:departments,id',
            'form.description' => 'nullable|string|max:1000',
            'form.is_active'   => 'required|boolean',
        ];

        $messages = [
            'form.name.required'      => 'Nama kategori wajib diisi.',
            'form.name.unique'        => 'Nama kategori sudah digunakan.',
            'form.name.max'           => 'Nama kategori maksimal 100 karakter.',
            'form.sector_id.required' => 'Sektor penanganan wajib dipilih.',
        ];

        $attributes = [
            'form.name'        => 'Nama Kategori',
            'form.code'        => 'Kode',
            'form.sector_id'   => 'Sektor',
            'form.description' => 'Deskripsi',
            'form.is_active'   => 'Status',
        ];

        $this->validate($rules, $messages, $attributes);

        $payload = [
            'name'        => trim($this->form['name']),
            'code'        => $this->form['code'] !== '' ? trim($this->form['code']) : null,
            'sector_id'   => (int) $this->form['sector_id'],
            'description' => $this->form['description'] !== '' ? trim($this->form['description']) : null,
            'is_active'   => (int) $this->form['is_active'],
            'updated_at'  => now(),
        ];

        if ($this->formMode === 'create') {
            DB::table('ticket_categories')->insert([...$payload, 'created_at' => now()]);
        } else {
            DB::table('ticket_categories')->where('id', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Kategori berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('master.ticket-categories.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'master-ticket-categories-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = ['name' => '', 'code' => '', 'sector_id' => '', 'description' => '', 'is_active' => 1];
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="master-ticket-categories-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="master-ticket-categories-actions"
            event="master.ticket-categories.saved"
            label="Kategori Tiket"
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
                                    {{ $formMode === 'edit' ? 'Ubah Kategori Tiket' : 'Tambah Kategori Tiket' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                    Lengkapi informasi kategori gangguan.
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

                <x-border-form title="Data Kategori">
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div class="sm:col-span-2">
                                <x-input-label value="Nama Kategori" />
                                <x-text-input wire:model.live="form.name" x-ref="inputName"
                                    maxlength="100"
                                    :error="$errors->has('form.name')"
                                    class="w-full mt-1"
                                    x-on:keydown.enter.prevent="$refs.inputCode?.focus()" />
                                <x-input-error :messages="$errors->get('form.name')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Kode" />
                                <x-text-input wire:model.live="form.code" x-ref="inputCode"
                                    maxlength="30"
                                    :error="$errors->has('form.code')"
                                    class="w-full mt-1 uppercase" />
                                <x-input-error :messages="$errors->get('form.code')" class="mt-1" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label value="Sektor Penanganan *" />
                                <x-select-input wire:model.live="form.sector_id" class="w-full mt-1" :error="$errors->has('form.sector_id')">
                                    <option value="">— pilih sektor —</option>
                                    @foreach ($this->sectors as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </x-select-input>
                                <x-input-error :messages="$errors->get('form.sector_id')" class="mt-1" />
                                <p class="mt-1 text-xs text-muted dark:text-gray-400">Tim yang menangani kategori ini (mis. AC/Listrik → IPS, Hardware → IT).</p>
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
