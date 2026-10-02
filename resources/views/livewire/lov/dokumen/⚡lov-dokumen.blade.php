<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Reactive;
use App\Services\DokumenService;

/**
 * LOV Dokumen Regulasi — pola LOV sirus (lov-dokter): cari ≥ 2 huruf, panah/Enter/Esc,
 * dispatch `lov.selected.<target>` / `lov.cleared.<target>`.
 *
 * Dipakai untuk memilih dokumen INDUK: `maksTingkat` membatasi pilihan ke dokumen yang
 * setingkat atau lebih tinggi (angka tingkat ≤ maksTingkat) — Peraturan → Pedoman → SPO.
 */
new class extends Component {
    public string $target = 'default';
    public string $label = 'Cari Dokumen';
    public string $placeholder = 'Ketik nomor / judul dokumen...';

    public string $search = '';
    public array $options = [];
    public bool $isOpen = false;
    public int $selectedIndex = 0;
    public ?array $selected = null;

    #[Reactive]
    public ?int $initialDokId = null;

    /** Tingkat terendah yang boleh dipilih (null = semua). */
    #[Reactive]
    public ?int $maksTingkat = null;

    /** Dokumen yang dikecualikan (dokumen yang sedang diubah). */
    #[Reactive]
    public ?int $kecualiDokId = null;

    public bool $disabled = false;

    public function mount(): void
    {
        $this->muatAwal($this->initialDokId);
    }

    public function updatedInitialDokId($value): void
    {
        $this->selected = null;
        $this->resetLov();
        $this->muatAwal($value ? (int) $value : null);
    }

    private function muatAwal(?int $dokId): void
    {
        if (! $dokId) {
            return;
        }
        $row = $this->query()->where('dk.dok_id', $dokId)->first();
        if ($row) {
            $this->selected = $this->map($row);
        }
    }

    private function query()
    {
        return DB::table('dok_dokumen as dk')
            ->join('dok_jenis as dj', 'dj.kode', '=', 'dk.jenis_kode')
            ->select('dk.dok_id', 'dk.dok_no', 'dk.dok_judul', 'dk.status', 'dk.no_revisi', 'dj.nama as jenis_nama', 'dj.tingkat');
    }

    public function updatedSearch(): void
    {
        if ($this->selected !== null) {
            return;
        }
        $kw = trim($this->search);
        if (mb_strlen($kw) < 2) {
            $this->tutupDaftar();
            return;
        }
        $up = mb_strtoupper($kw);
        $q = $this->query()
            ->where(fn ($w) => $w->whereRaw('UPPER(dk.dok_judul) LIKE ?', ["%{$up}%"])
                ->orWhereRaw('UPPER(dk.dok_no) LIKE ?', ["%{$up}%"]))
            ->whereIn('dk.status', ['DRAF', 'BERLAKU'])
            ->orderBy('dj.tingkat')->orderBy('dk.dok_judul');
        if ($this->maksTingkat !== null) {
            $q->where('dj.tingkat', '<=', (int) $this->maksTingkat);
        }
        if ($this->kecualiDokId) {
            $q->where('dk.dok_id', '<>', (int) $this->kecualiDokId);
        }
        $this->options = $q->limit(50)->get()->map(fn ($r) => $this->map($r))->toArray();
        $this->isOpen = count($this->options) > 0;
        $this->selectedIndex = 0;
        if ($this->isOpen) {
            $this->dispatch('lov-scroll', id: $this->getId(), index: $this->selectedIndex);
        }
    }

    private function map($r): array
    {
        return [
            'dok_id' => (int) $r->dok_id,
            'dok_no' => (string) ($r->dok_no ?? ''),
            'dok_judul' => (string) $r->dok_judul,
            'jenis_nama' => (string) $r->jenis_nama,
            'tingkat' => (int) $r->tingkat,
            'status' => (string) $r->status,
            'label' => (string) $r->dok_judul,
            'hint' => trim($r->jenis_nama . ($r->dok_no ? ' • ' . $r->dok_no : '') . ' • Rev.' . (int) $r->no_revisi),
        ];
    }

    public function clearSelected(): void
    {
        if ($this->disabled) {
            return;
        }
        $this->selected = null;
        $this->resetLov();
        $this->dispatch('lov.cleared.' . $this->target, target: $this->target);
    }

    public function close(): void { $this->isOpen = false; }

    public function resetLov(): void
    {
        $this->reset(['search', 'options', 'isOpen', 'selectedIndex']);
    }

    public function selectNext(): void
    {
        if (! $this->isOpen || ! $this->options) return;
        $this->selectedIndex = ($this->selectedIndex + 1) % count($this->options);
        $this->dispatch('lov-scroll', id: $this->getId(), index: $this->selectedIndex);
    }

    public function selectPrevious(): void
    {
        if (! $this->isOpen || ! $this->options) return;
        $this->selectedIndex = ($this->selectedIndex - 1 + count($this->options)) % count($this->options);
        $this->dispatch('lov-scroll', id: $this->getId(), index: $this->selectedIndex);
    }

    public function choose(int $index): void
    {
        if (! isset($this->options[$index])) return;
        $payload = $this->options[$index];
        $this->selected = $payload;
        $this->tutupDaftar();
        $this->search = '';
        $this->dispatch('lov.selected.' . $this->target, target: $this->target, payload: $payload);
    }

    public function chooseHighlighted(): void { $this->choose($this->selectedIndex); }

    private function tutupDaftar(): void
    {
        $this->options = [];
        $this->isOpen = false;
        $this->selectedIndex = 0;
    }
};
?>

<x-lov.dropdown :id="$this->getId()" :isOpen="$isOpen" :selectedIndex="$selectedIndex" close="close">
    <x-input-label :value="$label" />

    <div class="relative mt-1">
        @if ($selected === null)
            @if (! $disabled)
                <x-text-input type="text" class="block w-full" :placeholder="$placeholder" wire:model.live.debounce.250ms="search"
                    wire:keydown.escape.prevent="resetLov" wire:keydown.arrow-down.prevent="selectNext"
                    wire:keydown.arrow-up.prevent="selectPrevious" wire:keydown.enter.prevent="chooseHighlighted"
                    autocomplete="off" />
            @else
                <x-text-input type="text" class="block w-full bg-gray-100 cursor-not-allowed dark:bg-gray-800" :placeholder="$placeholder" disabled />
            @endif
        @else
            <div class="flex items-center gap-2">
                <div class="flex-1 min-w-0">
                    <x-text-input type="text" class="block w-full bg-gray-50 dark:bg-gray-800" :value="$selected['label']" disabled />
                    <p class="mt-1 text-xs truncate text-muted dark:text-gray-400">{{ $selected['hint'] }}</p>
                </div>
                @if (! $disabled)
                    <x-secondary-button type="button" wire:click="clearSelected" class="px-4 whitespace-nowrap">Ubah</x-secondary-button>
                @endif
            </div>
        @endif

        @if ($isOpen && $selected === null && ! $disabled)
            <div class="absolute z-50 w-full mt-2 overflow-hidden bg-white border border-gray-200 shadow-lg rounded-xl dark:bg-gray-900 dark:border-gray-700">
                <ul class="overflow-y-auto divide-y divide-gray-100 max-h-72 dark:divide-gray-800">
                    @foreach ($options as $index => $option)
                        <li wire:key="lov-dok-{{ $option['dok_id'] }}-{{ $index }}" x-ref="lovItem{{ $index }}">
                            <x-lov.item wire:click="choose({{ $index }})" :active="$index === $selectedIndex">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="font-semibold text-gray-900 dark:text-gray-100">{{ $option['label'] }}</div>
                                    <x-badge :variant="DokumenService::STATUS_BADGE[$option['status']] ?? 'gray'">{{ DokumenService::STATUS[$option['status']] ?? $option['status'] }}</x-badge>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $option['hint'] }}</div>
                            </x-lov.item>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-lov.dropdown>
