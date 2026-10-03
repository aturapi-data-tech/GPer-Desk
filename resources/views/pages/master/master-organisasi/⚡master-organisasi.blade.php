<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

/**
 * Master Struktur Organisasi — pohon ORG_UNIT (nama, induk, jenis, kepala).
 * Dasar: Perdir PT Al-Ikhlas Madinah 104/SK/PT-AIM/10/2025 (skrip database/schema/organisasi.sql).
 * Unit hanya puluhan → tanpa paginasi; tanpa pencarian tampil sebagai pohon (CONNECT BY), dengan pencarian tampil rata.
 */
new class extends Component {
    public const JENIS = ['DIREKSI' => 'Direksi', 'BAGIAN' => 'Bagian', 'INSTALASI' => 'Instalasi', 'UNIT' => 'Unit', 'KOMITE' => 'Komite', 'TIM' => 'Tim'];
    public const BADGE = ['DIREKSI' => 'brand', 'BAGIAN' => 'info', 'INSTALASI' => 'success', 'UNIT' => 'success', 'KOMITE' => 'warning', 'TIM' => 'gray'];

    public string $searchKeyword = '';

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword']);
    }

    public function openCreate(?int $indukId = null): void { $this->dispatch('master.organisasi.openCreate', indukId: $indukId); }
    public function openEdit(int $id): void { $this->dispatch('master.organisasi.openEdit', id: $id); }
    public function requestDelete(int $id): void { $this->dispatch('master.organisasi.requestDelete', id: $id); }

    #[On('master.organisasi.saved')]
    public function refreshAfterSaved(): void { unset($this->rows); }

    #[Computed]
    public function rows()
    {
        $rows = collect(DB::select('SELECT LEVEL AS lv, u.unit_id, u.nama, u.jenis, u.nama_kepala
                FROM org_unit u START WITH u.induk_id IS NULL CONNECT BY PRIOR u.unit_id = u.induk_id
                ORDER SIBLINGS BY u.urut, u.nama'));

        if (trim($this->searchKeyword) !== '') {
            $kw = mb_strtoupper(trim($this->searchKeyword));
            $rows = $rows->filter(fn ($r) => str_contains(mb_strtoupper($r->nama . ' ' . $r->nama_kepala), $kw));
        }

        return $rows->values();
    }
};
?>

<div>
    <x-page-title title="Struktur Organisasi" subtitle="Perdir PT Al-Ikhlas Madinah 104/SK/PT-AIM/10/2025" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 border-b bg-surface-soft border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="w-full lg:max-w-md">
                        <x-text-input type="text" wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Cari unit / nama kepala..." class="block w-full" />
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        @if (\App\Services\DokumenService::bolehUbah())
                            <x-primary-button type="button" wire:click="openCreate">+ Tambah Unit</x-primary-button>
                        @endif
                        <x-toolbar-refresh-reset :label="null" />
                    </div>
                </div>
            </div>

            {{-- TABEL --}}
            <div class="flex flex-col flex-1 min-h-0 mt-4 border shadow-sm bg-canvas border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-auto rounded-2xl">
                    <table class="ds-table">
                        <thead class="sticky top-0 z-10">
                            <tr>
                                <th>Unit</th>
                                <th>Kepala</th>
                                <th class="ds-c">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $bolehUbah = \App\Services\DokumenService::bolehUbah();
                                $rata = trim($searchKeyword) !== '';
                            @endphp
                            @forelse ($this->rows as $r)
                                <tr wire:key="org-{{ $r->unit_id }}">
                                    <td>
                                        <div class="flex items-center gap-2" style="padding-left: {{ $rata ? 0 : ($r->lv - 1) * 1.5 }}rem">
                                            <span class="ds-td-strong">{{ $r->nama }}</span>
                                            <x-badge :variant="$this::BADGE[$r->jenis] ?? 'gray'">{{ $this::JENIS[$r->jenis] ?? $r->jenis }}</x-badge>
                                        </div>
                                    </td>
                                    <td class="text-sm">
                                        {!! $r->nama_kepala ? e($r->nama_kepala) : '<span class="text-muted-soft">—</span>' !!}
                                    </td>
                                    <td class="ds-c">
                                        @if ($bolehUbah)
                                            <div class="flex justify-center gap-1">
                                                <x-icon-button color="green" type="button" wire:click="openCreate({{ $r->unit_id }})" title="Tambah unit di bawahnya">+</x-icon-button>
                                                <x-action-edit wire:click="openEdit({{ $r->unit_id }})" />
                                                <x-action-delete :action="'requestDelete(' . $r->unit_id . ')'" title="Hapus Unit"
                                                    message="Hapus {{ $r->nama }}?" />
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-6 py-16 text-center text-muted dark:text-gray-400">Tidak ada unit yang cocok.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <livewire:pages::master.master-organisasi.master-organisasi-actions wire:key="master-organisasi-actions" />
        </div>
    </div>
</div>
