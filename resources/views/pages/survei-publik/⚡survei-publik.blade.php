<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.public-form')] class extends Component {
    public array  $answers        = []; // [question_id => value]
    public string $respondentName = '';
    public bool   $sent           = false;
    public int    $submittedId    = 0;

    /** Survei yang sedang aktif (tampil ke publik). */
    #[Computed]
    public function survey()
    {
        return DB::table('surveys')->where('status', 'active')->orderByDesc('id')->first();
    }

    #[Computed]
    public function questions()
    {
        if (!$this->survey) {
            return collect();
        }
        return DB::table('survey_questions')->where('survey_id', $this->survey->id)->orderBy('sort_order')->get();
    }

    public function submit(): void
    {
        $survey = $this->survey;
        if (!$survey) {
            return;
        }

        $rules = ['respondentName' => 'nullable|string|max:150'];
        $messages = [];
        foreach ($this->questions as $pertanyaan) {
            if ($pertanyaan->type === 'rating') {
                $rules["answers.{$pertanyaan->id}"] = 'required|integer|min:1|max:5';
                $messages["answers.{$pertanyaan->id}.required"] = 'Beri penilaian bintang.';
                $messages["answers.{$pertanyaan->id}.min"]      = 'Beri penilaian bintang.';
            } elseif ($pertanyaan->type === 'choice') {
                $rules["answers.{$pertanyaan->id}"] = 'required|string|max:255';
                $messages["answers.{$pertanyaan->id}.required"] = 'Pilih salah satu.';
            } else {
                $rules["answers.{$pertanyaan->id}"] = 'nullable|string|max:2000';
            }
        }
        $this->validate($rules, $messages);

        // Hindari insertGetId (oci8 error di HTTP) — insert lalu ambil id via max.
        DB::table('survey_responses')->insert([
            'survey_id'       => $survey->id,
            'respondent_name' => $this->respondentName !== '' ? trim($this->respondentName) : null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
        $responseId = (int) DB::table('survey_responses')->max('id');

        foreach ($this->questions as $pertanyaan) {
            $nilai = $this->answers[$pertanyaan->id] ?? null;
            DB::table('survey_answers')->insert([
                'response_id' => $responseId,
                'question_id' => $pertanyaan->id,
                'value'       => ($nilai !== null && $nilai !== '') ? (string) $nilai : null,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        $this->submittedId = $survey->id;
        $this->sent = true;
        $this->reset(['answers', 'respondentName']);
    }

    /** Hasil agregat survei yang baru diisi (ditampilkan setelah submit). */
    #[Computed]
    public function evaluation(): array
    {
        if (!$this->submittedId) {
            return [];
        }
        $questions = DB::table('survey_questions')->where('survey_id', $this->submittedId)->orderBy('sort_order')->get();
        $hasilList = [];
        foreach ($questions as $pertanyaan) {
            if ($pertanyaan->type === 'rating') {
                $daftarBintang  = DB::table('survey_answers')->where('question_id', $pertanyaan->id)->pluck('value')
                    ->map(fn ($nilai) => (int) $nilai)->filter(fn ($nilai) => $nilai >= 1 && $nilai <= 5)->values();
                $jumlahJawaban = $daftarBintang->count();
                $distribusi  = [];
                for ($i = 5; $i >= 1; $i--) {
                    $distribusi[$i] = $daftarBintang->filter(fn ($nilai) => $nilai === $i)->count();
                }
                $hasilList[] = ['q' => $pertanyaan, 'type' => 'rating', 'count' => $jumlahJawaban, 'avg' => $jumlahJawaban ? round($daftarBintang->avg(), 2) : 0.0, 'dist' => $distribusi];
            } elseif ($pertanyaan->type === 'choice') {
                $daftarOpsi   = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $pertanyaan->options ?? ''))));
                $daftarJawaban   = DB::table('survey_answers')->where('question_id', $pertanyaan->id)->pluck('value'); // value = CLOB → hitung di PHP
                $hitunganOpsi = [];
                foreach ($daftarOpsi as $opsi) {
                    $hitunganOpsi[$opsi] = $daftarJawaban->filter(fn ($nilai) => trim((string) $nilai) === $opsi)->count();
                }
                $hasilList[] = ['q' => $pertanyaan, 'type' => 'choice', 'counts' => $hitunganOpsi, 'total' => array_sum($hitunganOpsi)];
            } else {
                $daftarTeks = DB::table('survey_answers')->where('question_id', $pertanyaan->id)->whereNotNull('value')
                    ->orderByDesc('id')->limit(30)->pluck('value')->filter(fn ($nilai) => trim((string) $nilai) !== '')->values();
                $hasilList[] = ['q' => $pertanyaan, 'type' => 'text', 'texts' => $daftarTeks];
            }
        }
        return $hasilList;
    }
};
?>

@php
    $starPath = 'M11.48 3.5a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.562.562 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z';
@endphp

<div class="w-full max-w-2xl mx-auto">

        {{-- Brand header --}}
        <div class="flex items-start gap-3 mb-6">
            <div class="flex items-center justify-center shrink-0 w-12 h-12 rounded-2xl bg-brand-green/10 dark:bg-brand-lime/15">
                <img src="{{ asset('images/Logogram black solid.png') }}" alt="Logo" class="block w-7 h-7 dark:hidden" />
                <img src="{{ asset('images/Logogram white solid.png') }}" alt="Logo" class="hidden w-7 h-7 dark:block" />
            </div>
            <div>
                <h1 class="text-2xl font-bold leading-tight text-ink dark:text-gray-100">Survei</h1>
                <p class="mt-1 text-sm leading-relaxed text-muted dark:text-gray-400">Bantu kami dengan mengisi survei singkat berikut — tanpa perlu login.</p>
            </div>
        </div>

        @if (!$this->survey)
            <div class="p-8 text-center bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <p class="font-semibold text-ink dark:text-gray-100">Belum ada survei aktif saat ini.</p>
                <p class="mt-1 text-sm text-muted dark:text-gray-400">Silakan cek kembali lain waktu. Terima kasih.</p>
            </div>
        @elseif ($sent)
            {{-- HASIL setelah submit --}}
            <div class="p-6 mb-4 text-center bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <div class="inline-flex items-center justify-center w-14 h-14 mb-3 rounded-full bg-success-tint text-success-deep dark:bg-green-900/30 dark:text-green-300">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
                <h2 class="text-lg font-bold text-ink dark:text-gray-100">Terima kasih! Jawaban Anda tersimpan.</h2>
                <p class="mt-1 text-sm text-muted dark:text-gray-400">Berikut hasil sementara dari seluruh responden:</p>
            </div>

            @foreach ($this->evaluation as $n => $evaluasi)
                <div class="p-5 mb-3 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                    <p class="font-semibold text-ink dark:text-gray-100">{{ $n + 1 }}. {{ $evaluasi['q']->question }}</p>
                    @if ($evaluasi['type'] === 'rating')
                        @php $distMax = max(1, max($evaluasi['dist'])); @endphp
                        <div class="flex items-center gap-3 mt-3">
                            <div class="text-center shrink-0">
                                <div class="text-3xl font-bold text-ink dark:text-gray-100">{{ number_format($evaluasi['avg'], 1) }}</div>
                                <div class="flex items-center gap-0.5 mt-1">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <svg class="w-4 h-4 {{ $i <= round($evaluasi['avg']) ? 'text-amber-400' : 'text-hairline dark:text-gray-600' }}" fill="currentColor" viewBox="0 0 24 24"><path d="{{ $starPath }}" /></svg>
                                    @endfor
                                </div>
                                <p class="mt-1 text-xs text-muted-soft">{{ $evaluasi['count'] }} jawaban</p>
                            </div>
                            <div class="flex-1 pl-3 border-l border-hairline dark:border-gray-700">
                                @for ($i = 5; $i >= 1; $i--)
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="w-3 text-xs font-semibold text-muted">{{ $i }}</span>
                                        <div class="flex-1 h-2 overflow-hidden rounded-full bg-surface-soft dark:bg-gray-700">
                                            <div class="h-full rounded-full bg-amber-400" style="width: {{ round($evaluasi['dist'][$i] / $distMax * 100) }}%"></div>
                                        </div>
                                        <span class="w-8 text-xs text-right text-muted">{{ $evaluasi['dist'][$i] }}</span>
                                    </div>
                                @endfor
                            </div>
                        </div>
                    @elseif ($evaluasi['type'] === 'choice')
                        @php $total = max(1, $evaluasi['total']); @endphp
                        <div class="mt-3 space-y-2">
                            @foreach ($evaluasi['counts'] as $opsi => $jumlah)
                                <div class="flex items-center gap-2">
                                    <span class="w-28 text-sm truncate text-body dark:text-gray-300">{{ $opsi }}</span>
                                    <div class="flex-1 h-3 overflow-hidden rounded-full bg-surface-soft dark:bg-gray-700">
                                        <div class="h-full rounded-full bg-brand-green dark:bg-brand-lime" style="width: {{ round($jumlah / $total * 100) }}%"></div>
                                    </div>
                                    <span class="w-16 text-xs text-right text-muted">{{ $jumlah }} ({{ round($jumlah / $total * 100) }}%)</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="mt-3 space-y-2">
                            @forelse ($evaluasi['texts'] as $teks)
                                <p class="p-2 text-sm rounded-lg bg-surface-soft text-body dark:bg-gray-900 dark:text-gray-300">“{{ $teks }}”</p>
                            @empty
                                <p class="text-sm text-muted dark:text-gray-400">Belum ada jawaban teks.</p>
                            @endforelse
                        </div>
                    @endif
                </div>
            @endforeach
        @else
            {{-- FORM ISI SURVEI --}}
            <div class="p-5 mb-4 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                <h2 class="text-lg font-bold text-ink dark:text-gray-100">{{ $this->survey->title }}</h2>
                @if ($this->survey->description)
                    <p class="mt-1 text-sm text-muted dark:text-gray-400">{{ $this->survey->description }}</p>
                @endif
            </div>

            <form wire:submit="submit" class="space-y-4">
                @foreach ($this->questions as $n => $pertanyaan)
                    <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                        <p class="font-semibold text-ink dark:text-gray-100">{{ $n + 1 }}. {{ $pertanyaan->question }} @if (in_array($pertanyaan->type, ['rating', 'choice']))<span class="text-red-500">*</span>@endif</p>
                        @if ($pertanyaan->type === 'rating')
                            <div x-data="{ r: @entangle('answers.' . $pertanyaan->id), h: 0 }" class="flex items-center gap-1 mt-2">
                                @for ($i = 1; $i <= 5; $i++)
                                    <button type="button" @click="r = {{ $i }}" @mouseenter="h = {{ $i }}" @mouseleave="h = 0" class="transition-transform hover:scale-110 focus:outline-none">
                                        <svg class="w-9 h-9" viewBox="0 0 24 24" :class="(h || r) >= {{ $i }} ? 'text-amber-400' : 'text-hairline dark:text-gray-600'" fill="currentColor"><path d="{{ $starPath }}" /></svg>
                                    </button>
                                @endfor
                            </div>
                        @elseif ($pertanyaan->type === 'choice')
                            @php $daftarOpsi = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $pertanyaan->options ?? '')))); @endphp
                            <div class="mt-2 space-y-2">
                                @foreach ($daftarOpsi as $opsi)
                                    <label class="flex items-center gap-2 px-3 py-2 border rounded-lg cursor-pointer border-hairline hover:border-brand-green/50 dark:border-gray-700">
                                        <input type="radio" wire:model="answers.{{ $pertanyaan->id }}" value="{{ $opsi }}" class="text-brand-green border-hairline focus:ring-brand-green dark:bg-gray-900" />
                                        <span class="text-sm text-body dark:text-gray-200">{{ $opsi }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <textarea wire:model="answers.{{ $pertanyaan->id }}" rows="3" maxlength="2000"
                                class="w-full mt-2 rounded-lg border-hairline bg-canvas text-body dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100 focus:border-brand-green focus:ring-brand-green"
                                placeholder="Tulis jawaban Anda..."></textarea>
                        @endif
                        <x-input-error :messages="$errors->get('answers.' . $pertanyaan->id)" class="mt-1" />
                    </div>
                @endforeach

                <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                    <x-input-label value="Nama (opsional)" />
                    <x-text-input wire:model="respondentName" maxlength="150" placeholder="Nama Anda" class="w-full mt-1" />
                </div>

                <div class="flex justify-end pb-6">
                    <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="submit">
                        <span wire:loading.remove wire:target="submit">Kirim Jawaban</span>
                        <span wire:loading wire:target="submit">Mengirim...</span>
                    </x-primary-button>
                </div>
            </form>
        @endif

        <p class="mt-2 text-xs text-center text-muted-soft">GPER-Desk · Survei</p>
    </div>

