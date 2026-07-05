<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode = 'create';
    public ?int $userId = null;

    public array $renderVersions = [];
    protected array $renderAreas = ['modal'];

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $sector_id = ''; // sektor penanganan (khusus teknisi)

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

    #[On('userControl.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode = 'create';
        $this->resetValidation();
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'user-control-actions');
        $this->dispatch('focus-name');
    }

    #[On('userControl.openEdit')]
    public function openEdit(?int $userId = null): void
    {
        if ($userId === null) {
            $this->dispatch('toast', type: 'error', message: 'ID user tidak valid.');
            return;
        }

        $this->resetForm();
        $this->formMode = 'edit';
        $this->resetValidation();

        $user = DB::table('users')->where('id', $userId)->first();
        if (!$user) {
            $this->dispatch('toast', type: 'error', message: 'User tidak ditemukan.');
            return;
        }

        $this->userId = $user->id;
        $this->name = $user->name ?? '';
        $this->email = $user->email ?? '';
        $this->sector_id = $user->sector_id ? (string) $user->sector_id : '';

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'user-control-actions');
        $this->dispatch('focus-name');
    }

    #[On('userControl.requestDelete')]
    public function deleteFromGrid(int $userId): void
    {
        $this->userId = $userId;
        $this->hapus();
    }

    protected function rules(): array
    {
        $rules = [
            'name'           => 'required|string|max:100',
            'email'          => 'required|email|max:100|unique:users,email',
            'sector_id'      => 'required|integer|exists:departments,id',
            'password'       => 'required|string|min:6|confirmed',
        ];

        if ($this->formMode === 'edit') {
            $rules['email']    = 'required|email|max:100|unique:users,email,' . $this->userId;
            $rules['password'] = 'nullable|string|min:6|confirmed';
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'required'  => ':attribute wajib diisi.',
            'unique'    => ':attribute sudah digunakan.',
            'email'     => 'Format email tidak valid.',
            'min'       => ':attribute minimal :min karakter.',
            'confirmed' => 'Konfirmasi password tidak cocok.',
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name'           => 'Nama User',
            'email'          => 'Email',
            'password'       => 'Password',
            'sector_id'      => 'Sektor',
        ];
    }

    public function save(): void
    {
        $this->validate();

        try {
            DB::transaction(function () {
                $data = [
                    'name'           => $this->name,
                    'email'          => $this->email,
                    'sector_id'      => $this->sector_id !== '' ? (int) $this->sector_id : null,
                    'updated_at'     => DB::raw('SYSDATE'),
                ];

                if (!empty($this->password)) {
                    $data['password'] = Hash::make($this->password);
                }

                if ($this->formMode === 'create') {
                    $data['created_at'] = DB::raw('SYSDATE');
                    $data['email_verified_at'] = null;
                    $data['remember_token'] = null;
                    DB::table('users')->insert($data);
                    $message = 'User berhasil ditambahkan.';
                } else {
                    DB::table('users')->where('id', $this->userId)->update($data);
                    $message = 'User berhasil diperbarui.';
                }

                $this->dispatch('toast', type: 'success', message: $message);
            });

            $this->closeModal();
            $this->dispatch('refresh-after-user-control.saved');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal: ' . $e->getMessage());
        }
    }

    public function hapus(): void
    {
        if (!$this->userId) {
            return;
        }

        try {
            DB::table('users')->where('id', $this->userId)->delete();
            $this->dispatch('toast', type: 'success', message: 'User berhasil dihapus.');
            $this->closeModal();
            $this->dispatch('refresh-after-user-control.saved');
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Gagal hapus: ' . $e->getMessage());
        }
    }

    public function closeModal(): void
    {
        $this->resetValidation();
        $this->resetForm();
        $this->dispatch('close-modal', name: 'user-control-actions');
    }

    protected function resetForm(): void
    {
        $this->resetVersion();
        $this->formMode = 'create';
        $this->userId = null;
        $this->reset(['name', 'email', 'password', 'password_confirmation', 'sector_id']);
    }
};
?>

<div>
    <x-modal name="user-control-actions" size="full" height="full" focusable>
        <div class="flex flex-col min-h-[calc(100vh-8rem)]"
            wire:key="{{ $this->renderKey('modal', [$formMode, $userId ?? 'new']) }}">

            {{-- ── HEADER ── --}}
            <div class="relative px-6 py-5 border-b border-hairline dark:border-gray-700">
                <div class="absolute inset-0 opacity-[0.06] dark:opacity-[0.10]"
                    style="background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 14px 14px;">
                </div>
                <div class="relative flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-xl bg-brand-green/10 dark:bg-brand-lime/15">
                                <img src="{{ asset('images/Logogram black solid.png') }}"
                                    class="block w-6 h-6 dark:hidden" alt="Logo" />
                                <img src="{{ asset('images/Logogram white solid.png') }}"
                                    class="hidden w-6 h-6 dark:block" alt="Logo" />
                            </div>
                            <div>
                                <h2 class="text-xl font-semibold text-ink dark:text-gray-100">
                                    {{ $formMode === 'edit' ? 'Ubah User' : 'Tambah User Baru' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                    Kelola data pengguna & hak akses sistem.
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-3">
                            <x-badge :variant="$formMode === 'edit' ? 'warning' : 'success'">
                                {{ $formMode === 'edit' ? 'Mode: Edit' : 'Mode: Tambah' }}
                            </x-badge>
                        </div>
                    </div>
                    <x-secondary-button type="button" wire:click="closeModal" class="!p-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </x-secondary-button>
                </div>
            </div>

            {{-- ── BODY ── --}}
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft/70 dark:bg-gray-950/20" x-data
                x-on:focus-name.window="$nextTick(() => setTimeout(() => $refs.inputName?.querySelector('input')?.focus(), 150))">
                <div class="max-w-3xl mx-auto p-1">
                    <div class="p-6 space-y-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-900 dark:border-gray-700">

                        {{-- Nama --}}
                        <div x-ref="inputName">
                            <x-input-label value="Nama User *" class="mb-1" />
                            <x-text-input wire:model="name" class="w-full" placeholder="Nama lengkap"
                                :error="$errors->has('name')"
                                x-on:keydown.enter.prevent="$nextTick(() => $refs.inputEmail?.querySelector('input')?.focus())" />
                            <x-input-error :messages="$errors->get('name')" class="mt-1" />
                        </div>

                        {{-- Email --}}
                        <div x-ref="inputEmail">
                            <x-input-label value="Email *" class="mb-1" />
                            <x-text-input wire:model="email" type="email" class="w-full"
                                placeholder="user@gper.com" :error="$errors->has('email')"
                                x-on:keydown.enter.prevent="$nextTick(() => $refs.inputPassword?.querySelector('input')?.focus())" />
                            <x-input-error :messages="$errors->get('email')" class="mt-1" />
                        </div>

                        {{-- Sektor Penanganan (khusus teknisi) --}}
                        <div>
                            <x-input-label value="Sektor Penanganan *" class="mb-1" />
                            <x-select-input wire:model="sector_id" class="w-full" :error="$errors->has('sector_id')">
                                <option value="">— pilih sektor —</option>
                                @foreach ($this->sectors as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </x-select-input>
                            <x-input-error :messages="$errors->get('sector_id')" class="mt-1" />
                            <p class="mt-1 text-xs text-muted dark:text-gray-400">
                                Wajib. Untuk <strong>teknisi</strong> menentukan tiket sektor mana (IT / CSSD / IPS) yang ia tangani.
                            </p>
                        </div>

                        {{-- Password --}}
                        <div x-ref="inputPassword">
                            <x-input-label
                                value="{{ $formMode === 'create' ? 'Password *' : 'Password (kosongkan jika tidak diubah)' }}"
                                class="mb-1" />
                            <x-text-input wire:model="password" type="password" class="w-full"
                                placeholder="Minimal 6 karakter" :error="$errors->has('password')"
                                x-on:keydown.enter.prevent="$nextTick(() => $refs.inputPasswordConfirmation?.querySelector('input')?.focus())" />
                            <x-input-error :messages="$errors->get('password')" class="mt-1" />
                        </div>

                        {{-- Konfirmasi Password --}}
                        <div x-ref="inputPasswordConfirmation">
                            <x-input-label value="Konfirmasi Password" class="mb-1" />
                            <x-text-input wire:model="password_confirmation" type="password" class="w-full"
                                placeholder="Ulangi password" :error="$errors->has('password_confirmation')"
                                x-on:keydown.enter.prevent="$wire.save()" />
                            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
                        </div>

                        <p class="text-xs text-muted dark:text-gray-400">
                            💡 Pengaturan <strong>role</strong> dilakukan lewat dropdown Role di tabel User Control.
                        </p>

                    </div>
                </div>
            </div>

            {{-- ── FOOTER ── --}}
            <div
                class="sticky bottom-0 z-10 px-6 py-4 bg-canvas border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-between">
                    <div>
                        @if ($formMode === 'edit')
                            <x-danger-button wire:click="hapus"
                                wire:confirm="Yakin ingin menghapus user ini?">
                                Hapus User
                            </x-danger-button>
                        @endif
                    </div>
                    <div class="flex gap-3">
                        <x-secondary-button wire:click="closeModal">Batal</x-secondary-button>
                        <x-primary-button wire:click.prevent="save" wire:loading.attr="disabled"
                            wire:target="save" class="min-w-[100px]">
                            <span wire:loading.remove>{{ $formMode === 'edit' ? 'Perbarui' : 'Simpan' }}</span>
                            <span wire:loading><x-loading /> Menyimpan...</span>
                        </x-primary-button>
                    </div>
                </div>
            </div>

        </div>
    </x-modal>
</div>
