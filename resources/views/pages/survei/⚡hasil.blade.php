<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public int $surveyId = 0;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['admin', 'manager']), 403);
        $this->surveyId = (int) request()->query('id', 0);
    }

    #[Computed]
    public function survey()
    {
        return DB::table('surveys')->where('id', $this->surveyId)->first();
    }

    #[Computed]
    public function totalResponses(): int
    {
        return DB::table('survey_responses')->where('survey_id', $this->surveyId)->count();
    }

    /**
     * Agregat jawaban per pertanyaan untuk ditampilkan di halaman hasil.
     *
     * Nilai kembalian: array yang tiap elemennya adalah map dengan KEY DATA
     * (ini key array asosiatif, bukan variabel — dibiarkan pendek karena harus
     * konsisten antara sini/producer dan template/consumer):
     *   'q'      => objek baris pertanyaan (dipakai untuk teks & tipe soal)
     *   'type'   => jenis soal: 'rating' | 'choice' | 'text'
     *   'count'  => jumlah jawaban rating yang valid (bernilai 1..5)
     *   'avg'    => rata-rata nilai rating
     *   'dist'   => distribusi: jumlah jawaban per bintang, dipetakan index 1..5
     *   'counts' => jumlah suara per opsi (untuk soal 'choice')
     *   'total'  => total seluruh suara opsi (untuk soal 'choice')
     *   'texts'  => daftar jawaban teks bebas (untuk soal 'text')
     */
    #[Computed]
    public function evaluation(): array
    {
        $questions = DB::table('survey_questions')->where('survey_id', $this->surveyId)->orderBy('sort_order')->get();
        $out = [];
        foreach ($questions as $pertanyaan) {
            if ($pertanyaan->type === 'rating') {
                $daftarBintang  = DB::table('survey_answers')->where('question_id', $pertanyaan->id)->pluck('value')
                    ->map(fn ($v) => (int) $v)->filter(fn ($v) => $v >= 1 && $v <= 5)->values();
                $count = $daftarBintang->count();
                $distribusi  = [];
                for ($i = 5; $i >= 1; $i--) {
                    $distribusi[$i] = $daftarBintang->filter(fn ($v) => $v === $i)->count();
                }
                $out[] = ['q' => $pertanyaan, 'type' => 'rating', 'count' => $count, 'avg' => $count ? round($daftarBintang->avg(), 2) : 0.0, 'dist' => $distribusi];
            } elseif ($pertanyaan->type === 'choice') {
                $daftarOpsi   = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $pertanyaan->options ?? ''))));
                $vals   = DB::table('survey_answers')->where('question_id', $pertanyaan->id)->pluck('value'); // value = CLOB → hitung di PHP
                $hitunganOpsi = [];
                foreach ($daftarOpsi as $opsi) {
                    $hitunganOpsi[$opsi] = $vals->filter(fn ($v) => trim((string) $v) === $opsi)->count();
                }
                $out[] = ['q' => $pertanyaan, 'type' => 'choice', 'counts' => $hitunganOpsi, 'total' => array_sum($hitunganOpsi)];
            } else {
                $texts = DB::table('survey_answers')->where('question_id', $pertanyaan->id)->whereNotNull('value')
                    ->orderByDesc('id')->pluck('value')->filter(fn ($v) => trim((string) $v) !== '')->values();
                $out[] = ['q' => $pertanyaan, 'type' => 'text', 'texts' => $texts];
            }
        }
        return $out;
    }
};
?>

@php
    $starPath = 'M11.48 3.5a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.562.562 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z';
@endphp

<div>
    <x-page-title title="Hasil Survei" subtitle="Evaluasi & rekap jawaban responden" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex-1 min-h-0 px-6 pt-2 pb-6 overflow-y-auto">
            <div class="max-w-3xl mx-auto mt-2 space-y-4">

                @if (!$this->survey)
                    <p class="py-10 text-center text-muted dark:text-gray-400">Survei tidak ditemukan.</p>
                @else
                    {{-- Header survei --}}
                    <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="text-lg font-bold text-ink dark:text-gray-100">{{ $this->survey->title }}</h2>
                            @php $variantStatus = ['draft' => 'gray', 'active' => 'success', 'closed' => 'warning'][$this->survey->status] ?? 'gray'; @endphp
                            <x-badge :variant="$variantStatus">{{ ['draft' => 'Draft', 'active' => 'Aktif', 'closed' => 'Ditutup'][$this->survey->status] ?? $this->survey->status }}</x-badge>
                        </div>
                        @if ($this->survey->description)
                            <p class="mt-1 text-sm text-muted dark:text-gray-400">{{ $this->survey->description }}</p>
                        @endif
                        <p class="mt-3 text-sm"><span class="font-bold text-2xl text-brand-green dark:text-brand-lime">{{ $this->totalResponses }}</span> <span class="text-muted dark:text-gray-400">responden</span></p>
                    </div>

                    {{-- Per pertanyaan. $n = indeks pertanyaan mulai 0 (dipakai untuk penomoran "1.", "2.", … lewat $n + 1). --}}
                    @foreach ($this->evaluation as $n => $evaluasi)
                        <div class="p-5 bg-canvas border border-hairline shadow-sm rounded-2xl dark:bg-gray-800 dark:border-gray-700">
                            <p class="font-semibold text-ink dark:text-gray-100">{{ $n + 1 }}. {{ $evaluasi['q']->question }}</p>

                            @if ($evaluasi['type'] === 'rating')
                                @php $distMax = max(1, max($evaluasi['dist'])); @endphp
                                <div class="flex items-center gap-3 mt-3">
                                    <div class="text-center shrink-0">
                                        <div class="text-3xl font-bold text-ink dark:text-gray-100">{{ number_format($evaluasi['avg'], 1) }}</div>
                                        <div class="flex items-center gap-0.5 mt-1">
                                            {{-- $i = angka bintang 1..5 yang sedang digambar (diwarnai bila <= rata-rata). --}}
                                            @for ($i = 1; $i <= 5; $i++)
                                                <svg class="w-4 h-4 {{ $i <= round($evaluasi['avg']) ? 'text-amber-400' : 'text-hairline dark:text-gray-600' }}" fill="currentColor" viewBox="0 0 24 24"><path d="{{ $starPath }}" /></svg>
                                            @endfor
                                        </div>
                                        <p class="mt-1 text-xs text-muted-soft">{{ $evaluasi['count'] }} jawaban</p>
                                    </div>
                                    <div class="flex-1 pl-3 border-l border-hairline dark:border-gray-700">
                                        {{-- Bar distribusi per bintang. $i = tingkat bintang, dihitung mundur 5 → 1 agar bintang tertinggi di atas. --}}
                                        @for ($i = 5; $i >= 1; $i--)
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="w-3 text-xs font-semibold text-muted">{{ $i }}</span>
                                                <svg class="w-3.5 h-3.5 text-amber-400 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="{{ $starPath }}" /></svg>
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
                                    @foreach ($evaluasi['counts'] as $opsi => $cnt)
                                        <div class="flex items-center gap-2">
                                            <span class="w-32 text-sm truncate text-body dark:text-gray-300">{{ $opsi }}</span>
                                            <div class="flex-1 h-3 overflow-hidden rounded-full bg-surface-soft dark:bg-gray-700">
                                                <div class="h-full rounded-full bg-brand-green dark:bg-brand-lime" style="width: {{ round($cnt / $total * 100) }}%"></div>
                                            </div>
                                            <span class="w-20 text-xs text-right text-muted">{{ $cnt }} ({{ round($cnt / $total * 100) }}%)</span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="mt-3 space-y-2">
                                    @forelse ($evaluasi['texts'] as $teks)
                                        <p class="p-2 text-sm rounded-lg bg-surface-soft text-body dark:bg-gray-900 dark:text-gray-300">“{{ $teks }}”</p>
                                    @empty
                                        <p class="text-sm text-muted dark:text-gray-400">Belum ada jawaban.</p>
                                    @endforelse
                                </div>
                            @endif
                        </div>
                    @endforeach

                    <div class="pb-6">
                        <a href="{{ route('survei.kelola') }}" wire:navigate>
                            <x-secondary-button type="button">← Kembali ke daftar</x-secondary-button>
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
