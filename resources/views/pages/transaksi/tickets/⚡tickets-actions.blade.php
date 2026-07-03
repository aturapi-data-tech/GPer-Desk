<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Services\TicketLog;
use App\Http\Traits\WithRenderVersioning\WithRenderVersioningTrait;

new class extends Component {
    use WithRenderVersioningTrait;

    public string $formMode   = 'create';
    public int    $originalId = 0;
    public string $ticketNo   = '';
    public array  $renderVersions = [];
    protected array $renderAreas  = ['modal'];

    public array $form = [
        'title'         => '',
        'description'   => '',
        'category_id'   => '',
        'priority_id'   => '',
        'location_id'   => '',
        'department_id' => '',
        'technician_id' => '',
        'part_cost'     => 0,
    ];

    public function mount(): void { $this->registerAreas(['modal']); }

    #[Computed] public function categories(): array { return DB::table('ticket_categories')->where('is_active', 1)->orderBy('name')->pluck('name', 'id')->all(); }
    #[Computed] public function priorities(): array { return DB::table('priorities')->where('is_active', 1)->orderByDesc('level')->pluck('name', 'id')->all(); }
    #[Computed] public function statuses(): array   { return DB::table('ticket_statuses')->where('is_active', 1)->orderBy('sort_order')->pluck('name', 'id')->all(); }
    #[Computed] public function locations(): array  { return DB::table('locations')->where('is_active', 1)->orderBy('name')->pluck('name', 'id')->all(); }
    #[Computed] public function departments(): array { return DB::table('departments')->where('is_active', 1)->orderBy('name')->pluck('name', 'id')->all(); }
    #[Computed] public function technicians(): array { return DB::table('users')->orderBy('name')->pluck('name', 'id')->all(); }

    #[On('tickets.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = 0;
        $this->ticketNo   = '(otomatis saat simpan)';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'tickets-actions');
        $this->dispatch('focus-title');
    }

    #[On('tickets.openEdit')]
    public function openEdit(int $id): void
    {
        $row = DB::table('tickets')->where('id', $id)->first();
        if (!$row) return;

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $id;
        $this->ticketNo   = (string) $row->ticket_no;
        $this->form = [
            'title'         => (string) $row->title,
            'description'   => (string) ($row->description ?? ''),
            'category_id'   => $row->category_id ? (string) $row->category_id : '',
            'priority_id'   => $row->priority_id ? (string) $row->priority_id : '',
            'location_id'   => $row->location_id ? (string) $row->location_id : '',
            'department_id' => $row->department_id ? (string) $row->department_id : '',
            'technician_id' => $row->technician_id ? (string) $row->technician_id : '',
            'part_cost'     => (float) ($row->part_cost ?? 0),
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'tickets-actions');
        $this->dispatch('focus-title');
    }

    #[On('tickets.requestDelete')]
    public function delete(int $id): void
    {
        DB::table('tickets')->where('id', $id)->delete();
        $this->dispatch('toast', type: 'success', message: 'Tiket berhasil dihapus.');
        $this->dispatch('tickets.saved');
    }

    private function statusName($id): ?string
    {
        return $id ? DB::table('ticket_statuses')->where('id', $id)->value('name') : null;
    }

    private function userName($id): ?string
    {
        return $id ? DB::table('users')->where('id', $id)->value('name') : null;
    }

    private function generateTicketNo(): string
    {
        $year = date('Y');
        $seq  = DB::table('tickets')->where('ticket_no', 'like', "TIC-{$year}-%")->count() + 1;
        do {
            $no = 'TIC-' . $year . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
            $seq++;
        } while (DB::table('tickets')->where('ticket_no', $no)->exists());

        return $no;
    }

    public function save(): void
    {
        $rules = [
            'form.title'         => 'required|string|max:255',
            'form.description'   => 'nullable|string|max:2000',
            'form.category_id'   => 'required|integer|exists:ticket_categories,id',
            'form.priority_id'   => 'required|integer|exists:priorities,id',
            'form.location_id'   => 'nullable|integer|exists:locations,id',
            'form.department_id' => 'nullable|integer|exists:departments,id',
            'form.technician_id' => 'nullable|integer|exists:users,id',
            'form.part_cost'     => 'required|numeric|min:0',
        ];

        $messages = [
            'form.title.required'       => 'Judul tiket wajib diisi.',
            'form.category_id.required' => 'Kategori wajib dipilih.',
            'form.priority_id.required' => 'Prioritas wajib dipilih.',
        ];

        $attributes = [
            'form.title'         => 'Judul',
            'form.category_id'   => 'Kategori',
            'form.priority_id'   => 'Prioritas',
            'form.location_id'   => 'Lokasi',
            'form.department_id' => 'Departemen',
            'form.technician_id' => 'Teknisi',
            'form.part_cost'     => 'Biaya Part',
        ];

        $this->validate($rules, $messages, $attributes);

        $payload = [
            'title'         => trim($this->form['title']),
            'description'   => $this->form['description'] !== '' ? trim($this->form['description']) : null,
            'category_id'   => (int) $this->form['category_id'],
            'priority_id'   => (int) $this->form['priority_id'],
            'location_id'   => $this->form['location_id'] !== '' ? (int) $this->form['location_id'] : null,
            'department_id' => $this->form['department_id'] !== '' ? (int) $this->form['department_id'] : null,
            'technician_id' => $this->form['technician_id'] !== '' ? (int) $this->form['technician_id'] : null,
            'part_cost'     => (float) $this->form['part_cost'],
            'updated_at'    => now(),
        ];

        if ($this->formMode === 'create') {
            // Status & solusi diatur lewat menu Respon — di sini status awal = Open.
            $payload['status_id']   = DB::table('ticket_statuses')->where('is_active', 1)->orderBy('sort_order')->value('id');
            $payload['ticket_no']   = $this->generateTicketNo();
            $payload['reported_by'] = auth()->id();
            $payload['reported_at'] = now();
            $payload['created_at']  = now();
            $id = DB::table('tickets')->insertGetId($payload);
            TicketLog::log($id, 'created', null, null, 'Tiket dibuat');
            if ($payload['technician_id']) {
                TicketLog::log($id, 'assigned', null, $this->userName($payload['technician_id']), 'Teknisi ditugaskan');
            }
        } else {
            $current = DB::table('tickets')->where('id', $this->originalId)->first();
            DB::table('tickets')->where('id', $this->originalId)->update($payload);

            // Catat perubahan penugasan teknisi (status/solusi tidak diubah di sini).
            if ($current && (int) $current->technician_id !== (int) ($payload['technician_id'] ?? 0)) {
                TicketLog::log(
                    $this->originalId, 'assigned',
                    $this->userName($current->technician_id),
                    $this->userName($payload['technician_id']),
                );
            }
        }

        $this->dispatch('toast', type: 'success', message: 'Tiket berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('tickets.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'tickets-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = [
            'title' => '', 'description' => '', 'category_id' => '', 'priority_id' => '',
            'location_id' => '', 'department_id' => '', 'technician_id' => '', 'part_cost' => 0,
        ];
        $this->ticketNo = '';
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="tickets-actions" size="full" height="full" focusable>
        <x-dirty-modal-content
            name="tickets-actions"
            event="tickets.saved"
            label="Tiket"
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
                                    {{ $formMode === 'edit' ? 'Ubah Tiket' : 'Tiket Baru' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                    No. Tiket: <span class="font-mono font-semibold">{{ $ticketNo ?: '-' }}</span>
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
            <div class="flex-1 px-4 py-4 overflow-y-auto bg-surface-soft dark:bg-gray-950/20" x-enter-chain
                 x-data
                 x-on:focus-title.window="$nextTick(() => setTimeout(() => $refs.inputTitle?.focus(), 150))">

                <x-border-form title="Informasi Tiket">
                    <div class="space-y-4">
                        {{-- Judul --}}
                        <div>
                            <x-input-label value="Judul Gangguan *" />
                            <x-text-input wire:model.live="form.title" x-ref="inputTitle"
                                maxlength="255" placeholder="mis. PC akunting tidak menyala"
                                :error="$errors->has('form.title')" class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('form.title')" class="mt-1" />
                        </div>

                        {{-- Deskripsi --}}
                        <div>
                            <x-input-label value="Deskripsi" />
                            <textarea wire:model.live="form.description" rows="3" maxlength="2000"
                                class="w-full mt-1 rounded-lg border-hairline bg-canvas text-body dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100 focus:border-brand-green focus:ring-brand-green"
                                placeholder="Detail gangguan..."></textarea>
                            <x-input-error :messages="$errors->get('form.description')" class="mt-1" />
                        </div>

                        {{-- Kategori / Prioritas --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label value="Kategori *" />
                                <x-select-input wire:model.live="form.category_id" class="w-full mt-1" :error="$errors->has('form.category_id')">
                                    <option value="">— pilih —</option>
                                    @foreach ($this->categories as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </x-select-input>
                                <x-input-error :messages="$errors->get('form.category_id')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Prioritas *" />
                                <x-select-input wire:model.live="form.priority_id" class="w-full mt-1" :error="$errors->has('form.priority_id')">
                                    <option value="">— pilih —</option>
                                    @foreach ($this->priorities as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </x-select-input>
                                <x-input-error :messages="$errors->get('form.priority_id')" class="mt-1" />
                            </div>
                        </div>

                        {{-- Lokasi / Departemen / Teknisi --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="Lokasi" />
                                <x-select-input wire:model.live="form.location_id" class="w-full mt-1">
                                    <option value="">— pilih —</option>
                                    @foreach ($this->locations as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </x-select-input>
                                <x-input-error :messages="$errors->get('form.location_id')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Departemen Pelapor" />
                                <x-select-input wire:model.live="form.department_id" class="w-full mt-1">
                                    <option value="">— pilih —</option>
                                    @foreach ($this->departments as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </x-select-input>
                                <x-input-error :messages="$errors->get('form.department_id')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Teknisi" />
                                <x-select-input wire:model.live="form.technician_id" class="w-full mt-1">
                                    <option value="">— belum ditugaskan —</option>
                                    @foreach ($this->technicians as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </x-select-input>
                                <x-input-error :messages="$errors->get('form.technician_id')" class="mt-1" />
                            </div>
                        </div>

                        {{-- Biaya --}}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <x-input-label value="Biaya Part (Rp)" />
                                <x-text-input wire:model.live="form.part_cost" type="number" min="0" step="0.01"
                                    :error="$errors->has('form.part_cost')" class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('form.part_cost')" class="mt-1" />
                                <p class="mt-1 text-xs text-muted dark:text-gray-400">Status & Solusi diatur lewat menu <strong>Respon</strong>.</p>
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
