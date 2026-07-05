<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public int $itemsPerPage = 10;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['admin', 'manager']), 403);
    }

    #[Computed]
    public function rows()
    {
        return DB::table('surveys as s')
            ->select('s.id', 's.title', 's.status',
                DB::raw('(SELECT COUNT(*) FROM survey_questions q WHERE q.survey_id = s.id) as q_count'),
                DB::raw('(SELECT COUNT(*) FROM survey_responses r WHERE r.survey_id = s.id) as resp_count'),
                DB::raw("TO_CHAR(s.created_at, 'dd/mm/yyyy HH24:MI') as tgl"))
            ->orderByDesc('s.id')
            ->paginate($this->itemsPerPage);
    }

    public function activate(int $id): void
    {
        // Hanya 1 survei aktif — tutup yang lain dulu.
        DB::table('surveys')->where('status', 'active')->update(['status' => 'closed', 'closed_at' => now(), 'updated_at' => now()]);
        DB::table('surveys')->where('id', $id)->update(['status' => 'active', 'opened_at' => now(), 'closed_at' => null, 'updated_at' => now()]);
        $this->dispatch('toast', type: 'success', message: 'Survei diaktifkan & tampil ke publik (survei lain otomatis ditutup).');
    }

    public function close(int $id): void
    {
        DB::table('surveys')->where('id', $id)->update(['status' => 'closed', 'closed_at' => now(), 'updated_at' => now()]);
        $this->dispatch('toast', type: 'success', message: 'Survei ditutup. Data & hasil tetap tersimpan.');
    }

    public function requestDelete(int $id): void
    {
        DB::table('surveys')->where('id', $id)->delete(); // cascade: questions, responses, answers
        $this->dispatch('toast', type: 'success', message: 'Survei dihapus.');
        $this->resetPage();
    }
};
?>

<div>
    <x-page-title title="Kelola Survei" subtitle="Buat survei, aktifkan (publik), tutup & lihat hasil" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            @if (session('ok'))
                <div class="px-4 py-3 mt-2 text-sm rounded-xl bg-success-tint text-success-deep dark:bg-green-900/30 dark:text-green-200">{{ session('ok') }}</div>
            @endif
            @if (session('error'))
                <div class="px-4 py-3 mt-2 text-sm rounded-xl bg-error-tint text-error-deep dark:bg-red-900/30 dark:text-red-200">{{ session('error') }}</div>
            @endif

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 mt-2 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between gap-2">
                    <a href="{{ route('survei.publik') }}" target="_blank" class="text-sm font-medium text-brand-green hover:underline dark:text-brand-lime">Buka halaman survei publik ↗</a>
                    <a href="{{ route('survei.builder') }}" wire:navigate>
                        <x-primary-button type="button">+ Buat Survei</x-primary-button>
                    </a>
                </div>
            </div>

            {{-- TABEL --}}
            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="ds-table">
                        <thead class="sticky top-0 z-10">
                            <tr>
                                <th>Judul</th>
                                <th class="ds-c">Status</th>
                                <th class="ds-c">Pertanyaan</th>
                                <th class="ds-c">Responden</th>
                                <th>Dibuat</th>
                                <th class="ds-c">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $row)
                                <tr wire:key="srv-{{ $row->id }}">
                                    <td class="ds-td-strong max-w-xs truncate">{{ $row->title }}</td>
                                    <td class="ds-c">
                                        @php $variantStatus = ['draft' => 'gray', 'active' => 'success', 'closed' => 'warning'][$row->status] ?? 'gray'; @endphp
                                        <x-badge :variant="$variantStatus">{{ ['draft' => 'Draft', 'active' => 'Aktif', 'closed' => 'Ditutup'][$row->status] ?? $row->status }}</x-badge>
                                    </td>
                                    <td class="ds-c">{{ $row->q_count }}</td>
                                    <td class="ds-c">{{ $row->resp_count }}</td>
                                    <td class="text-xs text-muted">{{ $row->tgl }}</td>
                                    <td class="ds-c">
                                        <div class="flex flex-wrap items-center justify-center gap-2">
                                            <a href="{{ route('survei.hasil', ['id' => $row->id]) }}" wire:navigate>
                                                <x-outline-button type="button">Hasil</x-outline-button>
                                            </a>
                                            @if ($row->status === 'draft')
                                                <a href="{{ route('survei.builder', ['id' => $row->id]) }}" wire:navigate>
                                                    <x-action-edit />
                                                </a>
                                                <x-success-button type="button" wire:click="activate({{ $row->id }})" class="px-2.5 py-1.5 text-sm">Aktifkan</x-success-button>
                                            @elseif ($row->status === 'active')
                                                <x-warning-button type="button" wire:click="close({{ $row->id }})" wire:confirm="Tutup survei ini? Tidak lagi tampil ke publik." class="px-2.5 py-1.5 text-sm">Tutup</x-warning-button>
                                            @else
                                                <x-success-button type="button" wire:click="activate({{ $row->id }})" wire:confirm="Aktifkan ulang survei ini? Survei aktif lain akan ditutup." class="px-2.5 py-1.5 text-sm">Aktifkan</x-success-button>
                                            @endif
                                            <x-action-delete :action="'requestDelete(' . $row->id . ')'"
                                                title="Hapus Survei" message="Hapus survei &quot;{{ $row->title }}&quot; beserta semua jawaban responden?" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center text-muted dark:text-gray-400">Belum ada survei. Klik "Buat Survei".</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

        </div>
    </div>
</div>
