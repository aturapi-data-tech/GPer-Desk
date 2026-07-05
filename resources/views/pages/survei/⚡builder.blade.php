<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public ?int $surveyId = null;
    public string $title = '';
    public string $description = '';
    public array $questions = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['admin', 'manager']), 403);

        $id = (int) request()->query('id', 0);
        if ($id) {
            $survei = DB::table('surveys')->where('id', $id)->first();
            if ($survei) {
                // Hanya survei DRAFT yang boleh diubah pertanyaannya (jaga integritas jawaban).
                if ($survei->status !== 'draft') {
                    session()->flash('error', 'Survei yang sudah dipublikasikan tidak bisa diubah pertanyaannya.');
                    $this->redirect(route('survei.kelola'), navigate: true);
                    return;
                }
                $this->surveyId   = $id;
                $this->title      = (string) $survei->title;
                $this->description = (string) ($survei->description ?? '');
                $this->questions  = DB::table('survey_questions')->where('survey_id', $id)
                    ->orderBy('sort_order')->get()
                    ->map(fn ($pertanyaan) => ['question' => $pertanyaan->question, 'type' => $pertanyaan->type, 'options' => (string) ($pertanyaan->options ?? '')])->all();
            }
        }
        if (empty($this->questions)) {
            $this->addQuestion();
        }
    }

    public function addQuestion(): void
    {
        $this->questions[] = ['question' => '', 'type' => 'rating', 'options' => ''];
    }

    public function removeQuestion(int $i): void
    {
        unset($this->questions[$i]);
        $this->questions = array_values($this->questions);
        if (empty($this->questions)) {
            $this->addQuestion();
        }
    }

    public function save(): void
    {
        $this->validate(
            [
                'title'                 => 'required|string|max:200',
                'description'           => 'nullable|string|max:1000',
                'questions'             => 'required|array|min:1',
                'questions.*.question'  => 'required|string|max:500',
                'questions.*.type'      => 'required|in:rating,text,choice',
            ],
            [
                'title.required'                 => 'Judul survei wajib diisi.',
                'questions.min'                  => 'Minimal 1 pertanyaan.',
                'questions.*.question.required'  => 'Pertanyaan tidak boleh kosong.',
            ],
        );

        // Pertanyaan tipe "choice" wajib punya minimal 2 opsi (1 opsi per baris).
        foreach ($this->questions as $i => $pertanyaan) {
            if (($pertanyaan['type'] ?? '') === 'choice') {
                $daftarOpsi = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $pertanyaan['options'] ?? ''))));
                if (count($daftarOpsi) < 2) {
                    $this->addError("questions.{$i}.options", 'Isi minimal 2 pilihan (satu per baris).');
                    return;
                }
            }
        }

        if ($this->surveyId) {
            DB::table('surveys')->where('id', $this->surveyId)->update([
                'title'       => trim($this->title),
                'description' => $this->description !== '' ? trim($this->description) : null,
                'updated_at'  => now(),
            ]);
            DB::table('survey_questions')->where('survey_id', $this->surveyId)->delete();
            $idSurvei = $this->surveyId;
        } else {
            DB::table('surveys')->insert([
                'title'       => trim($this->title),
                'description' => $this->description !== '' ? trim($this->description) : null,
                'status'      => 'draft',
                'created_by'  => auth()->id(),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
            $idSurvei = (int) DB::table('surveys')->max('id'); // hindari insertGetId (oci8 HTTP)
        }

        foreach (array_values($this->questions) as $i => $pertanyaan) {
            $daftarOpsi = null;
            if (($pertanyaan['type'] ?? '') === 'choice') {
                $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $pertanyaan['options'] ?? ''))));
                $daftarOpsi  = implode("\n", $lines);
            }
            DB::table('survey_questions')->insert([
                'survey_id'  => $idSurvei,
                'question'   => trim($pertanyaan['question']),
                'type'       => $pertanyaan['type'],
                'options'    => $daftarOpsi,
                'sort_order' => $i + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        session()->flash('ok', 'Survei berhasil disimpan (status: draft). Aktifkan dari daftar agar tampil ke publik.');
        $this->redirect(route('survei.kelola'), navigate: true);
    }
};
?>

<div>
    <x-page-title title="Builder Survei" subtitle="Susun pertanyaan survei (rating / teks)" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex-1 min-h-0 px-6 pt-2 pb-6 overflow-y-auto">
            <div class="max-w-3xl mx-auto mt-2 space-y-4">

                <x-border-form :title="$surveyId ? 'Ubah Survei' : 'Survei Baru'">
                    <div class="space-y-4">
                        <div>
                            <x-input-label value="Judul Survei *" />
                            <x-text-input wire:model="title" maxlength="200" class="w-full mt-1"
                                :error="$errors->has('title')" placeholder="mis. Survei Kepuasan Layanan Juli 2026" />
                            <x-input-error :messages="$errors->get('title')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Deskripsi" />
                            <textarea wire:model="description" rows="2" maxlength="1000"
                                class="w-full mt-1 rounded-lg border-hairline bg-canvas text-body dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100 focus:border-brand-green focus:ring-brand-green"
                                placeholder="Penjelasan singkat survei..."></textarea>
                        </div>
                    </div>
                </x-border-form>

                <x-border-form title="Pertanyaan">
                    <div class="space-y-3">
                        @error('questions') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                        @foreach ($questions as $i => $pertanyaan)
                            <div class="flex items-start gap-2 p-3 border rounded-xl border-hairline bg-surface-soft/50 dark:border-gray-700 dark:bg-gray-900/40" wire:key="q-{{ $i }}">
                                <span class="grid mt-1 text-xs font-bold rounded-full shrink-0 w-6 h-6 place-items-center bg-brand-green/10 text-brand-green dark:bg-brand-lime/15 dark:text-brand-lime">{{ $i + 1 }}</span>
                                <div class="flex-1 space-y-2">
                                    <x-text-input wire:model="questions.{{ $i }}.question" maxlength="500"
                                        class="w-full" :error="$errors->has('questions.' . $i . '.question')"
                                        placeholder="Tulis pertanyaan..." />
                                    <x-input-error :messages="$errors->get('questions.' . $i . '.question')" />
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-muted dark:text-gray-400">Jenis jawaban:</span>
                                        <div class="w-56">
                                            <x-select-input wire:model.live="questions.{{ $i }}.type">
                                                <option value="rating">Rating (1–5 bintang)</option>
                                                <option value="choice">Pilihan (voting, mis. Setuju/Tidak)</option>
                                                <option value="text">Teks / isian</option>
                                            </x-select-input>
                                        </div>
                                    </div>
                                    @if (($questions[$i]['type'] ?? '') === 'choice')
                                        <div>
                                            <x-input-label value="Pilihan (satu per baris)" class="text-xs" />
                                            <textarea wire:model="questions.{{ $i }}.options" rows="3"
                                                class="w-full mt-1 text-sm rounded-lg border-hairline bg-canvas text-body dark:bg-gray-900 dark:border-gray-700 dark:text-gray-100 focus:border-brand-green focus:ring-brand-green"
                                                placeholder="Setuju&#10;Tidak Setuju"></textarea>
                                            <x-input-error :messages="$errors->get('questions.' . $i . '.options')" class="mt-1" />
                                        </div>
                                    @endif
                                </div>
                                <x-icon-button color="red" type="button" wire:click="removeQuestion({{ $i }})" title="Hapus pertanyaan">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </x-icon-button>
                            </div>
                        @endforeach

                        <x-outline-button type="button" wire:click="addQuestion">+ Tambah Pertanyaan</x-outline-button>
                    </div>
                </x-border-form>

                <div class="flex items-center justify-end gap-2 pb-6">
                    <a href="{{ route('survei.kelola') }}" wire:navigate>
                        <x-secondary-button type="button">Batal</x-secondary-button>
                    </a>
                    <x-primary-button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">Simpan Survei</span>
                        <span wire:loading wire:target="save">Menyimpan...</span>
                    </x-primary-button>
                </div>
            </div>
        </div>
    </div>
</div>
