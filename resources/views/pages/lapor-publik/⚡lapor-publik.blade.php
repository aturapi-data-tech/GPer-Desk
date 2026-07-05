<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use App\Services\TicketLog;

new #[Layout('layouts.public-form')] class extends Component {
    use WithFileUploads;

    public $photo = null; // gambar gangguan (opsional)
    public string $nama            = '';
    public string $title           = '';
    public string $description     = '';
    public string $sector_id       = '';
    public string $category_id     = '';
    public string $priority_id     = '';
    public string $location_id     = '';
    public string $location_detail = '';

    public ?string $successNo = null; // no. tiket hasil kirim

    /** Ganti sektor → reset kategori (kategori terikat sektor). */
    public function updated($property): void
    {
        if ($property === 'sector_id') {
            $this->category_id = '';
        }
    }

    #[Computed] public function sectors(): array
    {
        return DB::table('departments')->where('is_active', 1)->where('is_sector', 1)
            ->orderBy('name')->pluck('name', 'id')->all();
    }

    #[Computed] public function categories(): array
    {
        if ($this->sector_id === '') {
            return [];
        }
        return DB::table('ticket_categories')->where('is_active', 1)
            ->where('sector_id', (int) $this->sector_id)->orderBy('name')->pluck('name', 'id')->all();
    }

    #[Computed] public function priorities(): array
    {
        return DB::table('priorities')->where('is_active', 1)->orderByDesc('level')->pluck('name', 'id')->all();
    }

    #[Computed] public function locations(): array
    {
        return DB::table('locations')->where('is_active', 1)->orderBy('name')->pluck('name', 'id')->all();
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

    public function submit(): void
    {
        $this->validate(
            [
                'nama'            => 'required|string|max:150',
                'title'           => 'required|string|max:255',
                'description'     => 'nullable|string|max:2000',
                'sector_id'       => 'required|integer|exists:departments,id',
                'category_id'     => 'required|integer|exists:ticket_categories,id',
                'priority_id'     => 'required|integer|exists:priorities,id',
                'location_id'     => 'required|integer|exists:locations,id',
                'location_detail' => 'nullable|string|max:500',
                'photo'           => 'nullable|image|max:5120',
            ],
            [
                'nama.required'        => 'Nama pelapor wajib diisi.',
                'photo.image'          => 'Berkas harus berupa gambar.',
                'photo.max'            => 'Ukuran gambar maksimal 5 MB.',
                'title.required'       => 'Judul gangguan wajib diisi.',
                'sector_id.required'   => 'Sektor tujuan wajib dipilih.',
                'category_id.required' => 'Kategori wajib dipilih.',
                'priority_id.required' => 'Prioritas wajib dipilih.',
                'location_id.required' => 'Lokasi wajib dipilih.',
            ],
            [
                'nama' => 'Nama Pelapor', 'title' => 'Judul', 'sector_id' => 'Sektor',
                'category_id' => 'Kategori', 'priority_id' => 'Prioritas', 'location_id' => 'Lokasi',
                'location_detail' => 'Detail Ruangan',
            ],
        );

        // Kategori harus milik sektor terpilih.
        $catSector = DB::table('ticket_categories')->where('id', (int) $this->category_id)->value('sector_id');
        if ((int) $catSector !== (int) $this->sector_id) {
            $this->addError('category_id', 'Kategori tidak sesuai sektor yang dipilih.');
            return;
        }

        $attachment = $this->photo ? $this->photo->store('ticket-attachments', 'public') : null;

        $no = $this->generateTicketNo();
        DB::table('tickets')->insert([
            'ticket_no'       => $no,
            'title'           => trim($this->title),
            'reporter_name'   => trim($this->nama),
            'attachment_path' => $attachment,
            'description'     => $this->description !== '' ? trim($this->description) : null,
            'sector_id'       => (int) $this->sector_id,
            'category_id'     => (int) $this->category_id,
            'priority_id'     => (int) $this->priority_id,
            'location_id'     => (int) $this->location_id,
            'location_detail' => $this->location_detail !== '' ? trim($this->location_detail) : null,
            'status_id'       => DB::table('ticket_statuses')->where('is_active', 1)->orderBy('sort_order')->value('id'),
            'reported_by'     => null,
            'part_cost'       => 0,
            'reported_at'     => now(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        $id = (int) DB::table('tickets')->where('ticket_no', $no)->value('id');

        TicketLog::log($id, 'created', null, null, 'Tiket dilaporkan (form publik) oleh ' . trim($this->nama));

        $this->successNo = $no;
        $this->reset(['nama', 'title', 'description', 'sector_id', 'category_id', 'priority_id', 'location_id', 'location_detail', 'photo']);
    }

    public function laporLagi(): void
    {
        $this->successNo = null;
        $this->resetValidation();
    }
};
?>

<div class="w-full max-w-4xl mx-auto">

        {{-- Brand header --}}
        <div class="flex items-start gap-3 mb-6">
            <div class="flex items-center justify-center shrink-0 w-12 h-12 rounded-2xl bg-brand-green/10 dark:bg-brand-lime/15">
                <img src="{{ asset('images/Logogram black solid.png') }}" alt="Logo" class="block w-7 h-7 dark:hidden" />
                <img src="{{ asset('images/Logogram white solid.png') }}" alt="Logo" class="hidden w-7 h-7 dark:block" />
            </div>
            <div>
                <h1 class="text-2xl font-bold leading-tight text-ink dark:text-gray-100">Lapor Gangguan</h1>
                <p class="mt-1 text-sm leading-relaxed text-muted dark:text-gray-400">
                    Ada kerusakan atau gangguan (IT, listrik/AC, atau alat)? Laporkan di sini —
                    <strong class="text-brand-green dark:text-brand-lime">tanpa perlu login</strong>.
                    Isi form, kirim, lalu catat nomor tiketnya. Tim teknisi akan menindaklanjuti.
                </p>
            </div>
        </div>

        @if ($successNo)
            {{-- SUKSES --}}
            <div class="p-8 text-center bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <div class="inline-flex items-center justify-center w-16 h-16 mb-4 rounded-full bg-success-tint text-success-deep dark:bg-green-900/30 dark:text-green-300">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <h2 class="text-lg font-bold text-ink dark:text-gray-100">Terima kasih, laporan Anda terkirim!</h2>
                <p class="mt-1 text-sm text-muted dark:text-gray-400">Simpan nomor tiket ini untuk <strong>mengecek progress perbaikan</strong>:</p>
                <p class="my-4 font-mono text-2xl font-bold tracking-wide text-brand-green dark:text-brand-lime">{{ $successNo }}</p>
                <p class="mb-5 text-xs text-muted-soft">Tim teknisi akan segera menindaklanjuti laporan Anda.</p>
                <div class="flex flex-wrap items-center justify-center gap-2">
                    <a href="{{ route('cek-tiket', ['no' => $successNo]) }}" wire:navigate>
                        <x-primary-button type="button">Cek Progress</x-primary-button>
                    </a>
                    <x-secondary-button type="button" wire:click="laporLagi">Buat laporan lain</x-secondary-button>
                </div>
            </div>
        @else
            {{-- FORM — gaya seksi kartu (samain dengan form login) --}}
            <form wire:submit="submit">
                <div class="grid items-start grid-cols-1 gap-4 lg:grid-cols-2">

                    <x-border-form title="Detail Gangguan">
                        <div class="space-y-4">
                            <div>
                                <x-input-label value="Nama Pelapor *" />
                                <x-text-input wire:model="nama" maxlength="150" placeholder="Nama lengkap Anda"
                                    :error="$errors->has('nama')" class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('nama')" class="mt-1" />
                            </div>

                            <div>
                                <x-input-label value="Judul Gangguan *" />
                                <x-text-input wire:model="title" maxlength="255" placeholder="mis. AC ruang server mati"
                                    :error="$errors->has('title')" class="w-full mt-1" />
                                <x-input-error :messages="$errors->get('title')" class="mt-1" />
                            </div>

                            <div>
                                <x-input-label value="Deskripsi" />
                                <textarea wire:model="description" rows="3" maxlength="2000"
                                    class="w-full mt-1 rounded-lg border-hairline bg-canvas text-body dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100 focus:border-brand-green focus:ring-brand-green"
                                    placeholder="Jelaskan gangguan yang dialami..."></textarea>
                                <x-input-error :messages="$errors->get('description')" class="mt-1" />
                            </div>

                            <div>
                                <x-input-label value="Sektor Tujuan *" />
                                <x-select-input wire:model.live="sector_id" class="w-full mt-1" :error="$errors->has('sector_id')">
                                    <option value="">— pilih sektor —</option>
                                    @foreach ($this->sectors as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </x-select-input>
                                <x-input-error :messages="$errors->get('sector_id')" class="mt-1" />
                                <p class="mt-1 text-xs text-muted dark:text-gray-400">Unit yang menangani: <strong>IT</strong>, <strong>CSSD</strong>, atau <strong>IPS</strong>. Pilihan kategori menyesuaikan sektor.</p>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <x-input-label value="Kategori *" />
                                    <x-select-input wire:model.live="category_id" class="w-full mt-1" :error="$errors->has('category_id')" :disabled="$sector_id === ''">
                                        <option value="">{{ $sector_id === '' ? '— pilih sektor dulu —' : '— pilih —' }}</option>
                                        @foreach ($this->categories as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('category_id')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label value="Prioritas *" />
                                    <x-select-input wire:model="priority_id" class="w-full mt-1" :error="$errors->has('priority_id')">
                                        <option value="">— pilih —</option>
                                        @foreach ($this->priorities as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </x-select-input>
                                    <x-input-error :messages="$errors->get('priority_id')" class="mt-1" />
                                </div>
                            </div>
                        </div>
                    </x-border-form>

                    <x-border-form title="Lokasi">
                        <div class="space-y-4">
                            <div>
                                <x-input-label value="Lokasi *" />
                                <x-select-input wire:model="location_id" class="w-full mt-1" :error="$errors->has('location_id')">
                                    <option value="">— pilih —</option>
                                    @foreach ($this->locations as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </x-select-input>
                                <x-input-error :messages="$errors->get('location_id')" class="mt-1" />
                            </div>

                            <div>
                                <x-input-label value="Detail Ruangan (opsional)" />
                                <textarea wire:model="location_detail" rows="2" maxlength="500"
                                    class="w-full mt-1 rounded-lg border-hairline bg-canvas text-body dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100 focus:border-brand-green focus:ring-brand-green"
                                    placeholder="mis. Lantai 3, ruang server, dekat rak B"></textarea>
                                <x-input-error :messages="$errors->get('location_detail')" class="mt-1" />
                                <p class="mt-1 text-xs text-muted dark:text-gray-400">Jelaskan ruangan / titik detail kejadian agar teknisi mudah menemukan.</p>
                            </div>

                            <div>
                                <x-input-label value="Foto Gangguan (opsional)" />
                                <x-file-upload name="photo" accept="image/*" loadingText="Mengunggah foto..." />
                                @if ($photo)
                                    <img src="{{ $photo->temporaryUrl() }}" alt="Pratinjau" class="object-cover w-full h-40 mt-2 border rounded-lg border-hairline dark:border-gray-700" />
                                @endif
                                <p class="mt-1 text-xs text-muted dark:text-gray-400">Lampirkan foto kondisi gangguan bila ada (maks. 5 MB).</p>
                            </div>

                            <p class="text-xs text-muted dark:text-gray-400">
                                ℹ️ Status awal otomatis <strong>Open</strong>. Setelah kirim, catat <strong>nomor tiket</strong> untuk menanyakan perkembangan.
                            </p>
                        </div>
                    </x-border-form>
                </div>

                <div class="flex justify-end pt-5 mt-5 border-t border-hairline dark:border-gray-700">
                    <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="submit">
                        <span wire:loading.remove wire:target="submit">Kirim Laporan</span>
                        <span wire:loading wire:target="submit">Mengirim...</span>
                    </x-primary-button>
                </div>
            </form>
        @endif

        <p class="mt-4 text-xs text-center text-muted-soft">GPER-Desk · Sistem Pelaporan Gangguan</p>
    </div>

