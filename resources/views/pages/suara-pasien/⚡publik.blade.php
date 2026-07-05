<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.public-form')] class extends Component {
    use WithFileUploads;

    public string $nama    = '';
    public int    $rating  = 0;
    public string $comment = '';
    public $photo = null; // foto opsional

    public bool $sent = false;

    /** Ringkasan: rata-rata bintang + jumlah ulasan. */
    #[Computed]
    public function summary(): array
    {
        $queryDasar  = DB::table('patient_reviews')->where('is_visible', 1);
        $count = (clone $queryDasar)->count();
        $avg   = $count ? round((float) (clone $queryDasar)->avg('rating'), 1) : 0.0;
        return ['count' => $count, 'avg' => $avg];
    }

    /** Ulasan terbaru yang tampil (moderasi is_visible). */
    #[Computed]
    public function reviews()
    {
        return DB::table('patient_reviews')->where('is_visible', 1)
            ->orderByDesc('id')->limit(30)
            ->get(['reviewer_name', 'rating', 'comment', 'photo_path',
                DB::raw("TO_CHAR(created_at, 'dd/mm/yyyy HH24:MI:SS') as tgl")]);
    }

    public function submit(): void
    {
        $this->validate(
            [
                'nama'    => 'required|string|max:150',
                'rating'  => 'required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:1000',
                'photo'   => 'nullable|image|max:5120',
            ],
            [
                'nama.required'   => 'Nama wajib diisi.',
                'rating.required' => 'Beri penilaian bintang dulu.',
                'rating.min'      => 'Beri penilaian bintang dulu.',
                'photo.image'     => 'Berkas harus berupa gambar.',
                'photo.max'       => 'Ukuran gambar maksimal 5 MB.',
            ],
            ['nama' => 'Nama', 'rating' => 'Penilaian', 'comment' => 'Ulasan'],
        );

        DB::table('patient_reviews')->insert([
            'reviewer_name' => trim($this->nama),
            'rating'        => $this->rating,
            'comment'       => $this->comment !== '' ? trim($this->comment) : null,
            'photo_path'    => $this->photo ? $this->photo->store('review-photos', 'public') : null,
            'is_visible'    => 1,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->sent = true;
        $this->reset(['nama', 'rating', 'comment', 'photo']);
        unset($this->summary, $this->reviews);
    }

    public function ulasLagi(): void
    {
        $this->sent = false;
        $this->resetValidation();
    }
};
?>

@php
    // Bintang render helper (dipakai di beberapa tempat)
    $starPath = 'M11.48 3.5a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.562.562 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z';
@endphp

<div class="w-full max-w-3xl mx-auto">

        {{-- Brand header --}}
        <div class="flex items-start gap-3 mb-6">
            <div class="flex items-center justify-center shrink-0 w-12 h-12 rounded-2xl bg-brand-green/10 dark:bg-brand-lime/15">
                <img src="{{ asset('images/Logogram black solid.png') }}" alt="Logo" class="block w-7 h-7 dark:hidden" />
                <img src="{{ asset('images/Logogram white solid.png') }}" alt="Logo" class="hidden w-7 h-7 dark:block" />
            </div>
            <div>
                <h1 class="text-2xl font-bold leading-tight text-ink dark:text-gray-100">Suara Pasien</h1>
                <p class="mt-1 text-sm leading-relaxed text-muted dark:text-gray-400">
                    Bagikan pengalaman Anda tentang pelayanan kami — beri bintang &amp; tulis ulasan.
                    <strong class="text-brand-green dark:text-brand-lime">Tanpa perlu login.</strong>
                    Masukan Anda membantu kami menjadi lebih baik.
                </p>
            </div>
        </div>

        {{-- Ringkasan rating (ala Google) --}}
        @php $ringkasan = $this->summary; @endphp
        <div class="flex items-center gap-5 p-5 mb-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
            <div class="text-center shrink-0">
                <div class="text-4xl font-bold text-ink dark:text-gray-100">{{ number_format($ringkasan['avg'], 1) }}</div>
                <div class="flex items-center gap-0.5 mt-1">
                    {{-- $i = angka bintang 1..5 yang sedang digambar (diwarnai bila <= rata-rata). --}}
                    @for ($i = 1; $i <= 5; $i++)
                        <svg class="w-4 h-4 {{ $i <= round($ringkasan['avg']) ? 'text-amber-400' : 'text-hairline dark:text-gray-600' }}" fill="currentColor" viewBox="0 0 24 24"><path d="{{ $starPath }}" /></svg>
                    @endfor
                </div>
            </div>
            <div class="pl-5 border-l border-hairline dark:border-gray-700">
                <p class="text-sm font-semibold text-ink dark:text-gray-100">{{ $ringkasan['count'] }} ulasan pasien</p>
                <p class="text-xs text-muted dark:text-gray-400">Rata-rata dari seluruh penilaian yang masuk.</p>
            </div>
        </div>

        @if ($sent)
            {{-- SUKSES --}}
            <div class="p-8 mb-5 text-center bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <div class="inline-flex items-center justify-center w-16 h-16 mb-4 rounded-full bg-success-tint text-success-deep dark:bg-green-900/30 dark:text-green-300">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <h2 class="text-lg font-bold text-ink dark:text-gray-100">Terima kasih atas ulasan Anda!</h2>
                <p class="mt-1 mb-5 text-sm text-muted dark:text-gray-400">Masukan Anda sangat berarti untuk peningkatan mutu pelayanan kami.</p>
                <x-primary-button type="button" wire:click="ulasLagi">Beri ulasan lagi</x-primary-button>
            </div>
        @else
            {{-- FORM --}}
            <form wire:submit="submit" class="p-6 mb-6 space-y-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <div>
                    <x-input-label value="Penilaian Anda *" class="mb-1" />
                    <div x-data="{ r: @entangle('rating'), h: 0 }" class="flex items-center gap-1">
                        @for ($i = 1; $i <= 5; $i++)
                            <button type="button" wire:key="star-{{ $i }}"
                                @click="r = {{ $i }}" @mouseenter="h = {{ $i }}" @mouseleave="h = 0"
                                class="transition-transform hover:scale-110 focus:outline-none">
                                <svg class="w-9 h-9" viewBox="0 0 24 24"
                                    :class="(h || r) >= {{ $i }} ? 'text-amber-400' : 'text-hairline dark:text-gray-600'"
                                    fill="currentColor"><path d="{{ $starPath }}" /></svg>
                            </button>
                        @endfor
                        <span class="ml-2 text-sm font-medium text-muted dark:text-gray-400"
                            x-text="['Pilih bintang','Sangat kurang','Kurang','Cukup','Baik','Sangat baik'][r] || 'Pilih bintang'"></span>
                    </div>
                    <x-input-error :messages="$errors->get('rating')" class="mt-1" />
                </div>

                <div>
                    <x-input-label value="Nama *" />
                    <x-text-input wire:model="nama" maxlength="150" placeholder="Nama Anda"
                        :error="$errors->has('nama')" class="w-full mt-1" />
                    <x-input-error :messages="$errors->get('nama')" class="mt-1" />
                </div>

                <div>
                    <x-input-label value="Ulasan" />
                    <textarea wire:model="comment" rows="3" maxlength="1000"
                        class="w-full mt-1 rounded-lg border-hairline bg-canvas text-body dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100 focus:border-brand-green focus:ring-brand-green"
                        placeholder="Ceritakan pengalaman Anda (opsional)..."></textarea>
                    <x-input-error :messages="$errors->get('comment')" class="mt-1" />
                </div>

                <div>
                    <x-input-label value="Foto (opsional)" />
                    <x-file-upload name="photo" accept="image/*" loadingText="Mengunggah foto..." />
                    @if ($photo)
                        <img src="{{ $photo->temporaryUrl() }}" alt="Pratinjau" class="object-cover w-full h-40 mt-2 border rounded-lg border-hairline dark:border-gray-700" />
                    @endif
                    <p class="mt-1 text-xs text-muted dark:text-gray-400">Lampirkan foto bila perlu (maks. 5 MB).</p>
                </div>

                <div class="flex justify-end">
                    <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="submit">
                        <span wire:loading.remove wire:target="submit">Kirim Ulasan</span>
                        <span wire:loading wire:target="submit">Mengirim...</span>
                    </x-primary-button>
                </div>
            </form>
        @endif

        {{-- Daftar ulasan --}}
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-muted dark:text-gray-400">Ulasan Terbaru</h2>
        <div class="space-y-3">
            {{-- $ulasan = satu baris ulasan pasien dari koleksi $this->reviews. --}}
            @forelse ($this->reviews as $ulasan)
                <div class="p-4 bg-canvas border border-hairline shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="grid w-8 h-8 text-sm font-bold rounded-full shrink-0 place-items-center bg-brand-green/10 text-brand-green dark:bg-brand-lime/15 dark:text-brand-lime">{{ mb_strtoupper(mb_substr($ulasan->reviewer_name, 0, 1)) }}</span>
                            <span class="font-semibold truncate text-ink dark:text-gray-100">{{ $ulasan->reviewer_name }}</span>
                        </div>
                        <span class="text-xs text-muted-soft shrink-0">{{ $ulasan->tgl }}</span>
                    </div>
                    <div class="flex items-center gap-0.5 mt-1.5">
                        @for ($i = 1; $i <= 5; $i++)
                            <svg class="w-4 h-4 {{ $i <= $ulasan->rating ? 'text-amber-400' : 'text-hairline dark:text-gray-600' }}" fill="currentColor" viewBox="0 0 24 24"><path d="{{ $starPath }}" /></svg>
                        @endfor
                    </div>
                    @if ($ulasan->comment)
                        <p class="mt-2 text-sm leading-relaxed text-body dark:text-gray-300 whitespace-pre-line">{{ $ulasan->comment }}</p>
                    @endif
                    @if (!empty($ulasan->photo_path))
                        <a href="{{ asset('storage/' . $ulasan->photo_path) }}" target="_blank" rel="noopener" class="inline-block mt-2">
                            <img src="{{ asset('storage/' . $ulasan->photo_path) }}" alt="Foto ulasan" class="object-cover w-24 h-24 border rounded-lg border-hairline dark:border-gray-700 hover:opacity-90 transition" />
                        </a>
                    @endif
                </div>
            @empty
                <p class="py-8 text-sm text-center text-muted dark:text-gray-400">Belum ada ulasan. Jadilah yang pertama memberi ulasan!</p>
            @endforelse
        </div>

        <p class="mt-6 text-xs text-center text-muted-soft">GPER-Desk · Suara Pasien</p>
    </div>

