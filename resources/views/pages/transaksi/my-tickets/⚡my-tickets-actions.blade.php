<?php

use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
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
    ];

    public function mount(): void { $this->registerAreas(['modal']); }

    #[Computed] public function categories(): array { return DB::table('ticket_categories')->where('is_active', 1)->orderBy('name')->pluck('name', 'id')->all(); }
    #[Computed] public function priorities(): array { return DB::table('priorities')->where('is_active', 1)->orderByDesc('level')->pluck('name', 'id')->all(); }
    #[Computed] public function locations(): array  { return DB::table('locations')->where('is_active', 1)->orderBy('name')->pluck('name', 'id')->all(); }
    #[Computed] public function departments(): array { return DB::table('departments')->where('is_active', 1)->orderBy('name')->pluck('name', 'id')->all(); }

    private function openStatusId(): ?int
    {
        return DB::table('ticket_statuses')->where('is_active', 1)->orderBy('sort_order')->value('id');
    }

    /** Tiket milik user & masih di status awal (Open)? */
    private function ownEditable(int $id): ?object
    {
        $t = DB::table('tickets')->where('id', $id)->where('reported_by', auth()->id())->first();
        if (!$t) return null;
        if ((int) $t->status_id !== (int) $this->openStatusId()) return null;
        return $t;
    }

    #[On('my-tickets.openCreate')]
    public function openCreate(): void
    {
        $this->resetForm();
        $this->formMode   = 'create';
        $this->originalId = 0;
        $this->ticketNo   = '(otomatis saat simpan)';
        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'my-tickets-actions');
        $this->dispatch('focus-title');
    }

    #[On('my-tickets.openEdit')]
    public function openEdit(int $id): void
    {
        $t = $this->ownEditable($id);
        if (!$t) {
            $this->dispatch('toast', type: 'error', message: 'Tiket sudah diproses — tidak bisa diubah.');
            return;
        }

        $this->resetForm();
        $this->formMode   = 'edit';
        $this->originalId = $id;
        $this->ticketNo   = (string) $t->ticket_no;
        $this->form = [
            'title'         => (string) $t->title,
            'description'   => (string) ($t->description ?? ''),
            'category_id'   => $t->category_id ? (string) $t->category_id : '',
            'priority_id'   => $t->priority_id ? (string) $t->priority_id : '',
            'location_id'   => $t->location_id ? (string) $t->location_id : '',
            'department_id' => $t->department_id ? (string) $t->department_id : '',
        ];

        $this->incrementVersion('modal');
        $this->dispatch('open-modal', name: 'my-tickets-actions');
        $this->dispatch('focus-title');
    }

    #[On('my-tickets.requestDelete')]
    public function delete(int $id): void
    {
        if (!$this->ownEditable($id)) {
            $this->dispatch('toast', type: 'error', message: 'Tiket sudah diproses — tidak bisa dibatalkan.');
            return;
        }
        DB::table('tickets')->where('id', $id)->where('reported_by', auth()->id())->delete();
        $this->dispatch('toast', type: 'success', message: 'Tiket dibatalkan.');
        $this->dispatch('my-tickets.saved');
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
        ];

        $messages = [
            'form.title.required'       => 'Judul gangguan wajib diisi.',
            'form.category_id.required' => 'Kategori wajib dipilih.',
            'form.priority_id.required' => 'Prioritas wajib dipilih.',
        ];

        $attributes = [
            'form.title'         => 'Judul',
            'form.category_id'   => 'Kategori',
            'form.priority_id'   => 'Prioritas',
            'form.location_id'   => 'Lokasi',
            'form.department_id' => 'Departemen',
        ];

        $this->validate($rules, $messages, $attributes);

        $payload = [
            'title'         => trim($this->form['title']),
            'description'   => $this->form['description'] !== '' ? trim($this->form['description']) : null,
            'category_id'   => (int) $this->form['category_id'],
            'priority_id'   => (int) $this->form['priority_id'],
            'location_id'   => $this->form['location_id'] !== '' ? (int) $this->form['location_id'] : null,
            'department_id' => $this->form['department_id'] !== '' ? (int) $this->form['department_id'] : null,
            'updated_at'    => now(),
        ];

        if ($this->formMode === 'create') {
            $payload['ticket_no']   = $this->generateTicketNo();
            $payload['status_id']   = $this->openStatusId();
            $payload['reported_by'] = auth()->id();
            $payload['reported_at'] = now();
            $payload['part_cost']   = 0;
            $payload['created_at']  = now();
            $id = DB::table('tickets')->insertGetId($payload);
            TicketLog::log($id, 'created', null, null, 'Tiket dilaporkan');
        } else {
            // re-guard: masih milik user & status Open
            if (!$this->ownEditable($this->originalId)) {
                $this->dispatch('toast', type: 'error', message: 'Tiket sudah diproses — tidak bisa diubah.');
                return;
            }
            DB::table('tickets')->where('id', $this->originalId)->update($payload);
        }

        $this->dispatch('toast', type: 'success', message: 'Tiket berhasil disimpan.');
        $this->closeModal();
        $this->dispatch('my-tickets.saved');
    }

    public function closeModal(): void
    {
        $this->resetForm();
        $this->dispatch('close-modal', name: 'my-tickets-actions');
        $this->resetVersion();
    }

    private function resetForm(): void
    {
        $this->form = [
            'title' => '', 'description' => '', 'category_id' => '', 'priority_id' => '',
            'location_id' => '', 'department_id' => '',
        ];
        $this->ticketNo = '';
        $this->resetValidation();
    }
};
?>

<div>
    <x-modal name="my-tickets-actions" size="lg" height="auto" focusable>
        <x-dirty-modal-content
            name="my-tickets-actions"
            event="my-tickets.saved"
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
                                    {{ $formMode === 'edit' ? 'Ubah Laporan' : 'Lapor Gangguan' }}
                                </h2>
                                <p class="mt-0.5 text-sm text-muted dark:text-gray-400">
                                    No. Tiket: <span class="font-mono font-semibold">{{ $ticketNo ?: '-' }}</span>
                                </p>
                            </div>
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

                <x-border-form title="Detail Gangguan">
                    <div class="space-y-4">
                        <div>
                            <x-input-label value="Judul Gangguan *" />
                            <x-text-input wire:model.live="form.title" x-ref="inputTitle"
                                maxlength="255" placeholder="mis. PC saya tidak menyala"
                                :error="$errors->has('form.title')" class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('form.title')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label value="Deskripsi" />
                            <textarea wire:model.live="form.description" rows="3" maxlength="2000"
                                class="w-full mt-1 rounded-lg border-hairline bg-canvas text-body dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100 focus:border-brand-green focus:ring-brand-green"
                                placeholder="Jelaskan gangguan yang dialami..."></textarea>
                            <x-input-error :messages="$errors->get('form.description')" class="mt-1" />
                        </div>

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

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                                <x-input-label value="Departemen" />
                                <x-select-input wire:model.live="form.department_id" class="w-full mt-1">
                                    <option value="">— pilih —</option>
                                    @foreach ($this->departments as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </x-select-input>
                                <x-input-error :messages="$errors->get('form.department_id')" class="mt-1" />
                            </div>
                        </div>

                        <p class="text-xs text-muted dark:text-gray-400">
                            ℹ️ Status awal otomatis <strong>Open</strong>. Teknisi akan menindaklanjuti & menugaskan penanganan.
                            Anda bisa pantau perkembangannya lewat tombol <strong>Respon</strong>.
                        </p>
                    </div>
                </x-border-form>
            </div>

            {{-- FOOTER --}}
            <div class="sticky bottom-0 z-10 px-6 py-4 mt-auto bg-surface-soft border-t border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-end gap-2">
                    <x-secondary-button type="button" x-on:click="tryClose()">Batal</x-secondary-button>
                    <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled">
                        <span wire:loading.remove>{{ $formMode === 'edit' ? 'Perbarui' : 'Kirim Laporan' }}</span>
                        <span wire:loading>Menyimpan...</span>
                    </x-primary-button>
                </div>
            </div>

        </x-dirty-modal-content>
    </x-modal>
</div>
