<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public string $searchKeyword = '';
    public int    $itemsPerPage  = 10;

    public function updatedSearchKeyword(): void { $this->resetPage(); }
    public function updatedItemsPerPage(): void  { $this->resetPage(); }

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword']);
        $this->itemsPerPage = 10;
        $this->resetPage();
    }

    public function openCreate(): void  { $this->dispatch('master.departments.openCreate'); }
    public function openEdit(int $id): void { $this->dispatch('master.departments.openEdit', id: $id); }
    public function requestDelete(int $id): void { $this->dispatch('master.departments.requestDelete', id: $id); }

    #[On('master.departments.saved')]
    public function refreshAfterSaved(): void { $this->resetPage(); }

    #[Computed]
    public function rows()
    {
        $q = DB::table('departments')
            ->select('id', 'name', 'code', 'head_name', 'is_active')
            ->orderBy('name');

        if (trim($this->searchKeyword) !== '') {
            $kw = mb_strtoupper(trim($this->searchKeyword));
            $q->where(function ($w) use ($kw) {
                $w->whereRaw('UPPER(name) LIKE ?', ["%{$kw}%"])
                  ->orWhereRaw('UPPER(code) LIKE ?', ["%{$kw}%"])
                  ->orWhereRaw('UPPER(head_name) LIKE ?', ["%{$kw}%"]);
            });
        }

        return $q->paginate($this->itemsPerPage);
    }
};
?>

<div>

    <x-page-title
        title="Master Departemen"
        subtitle="Kelola unit kerja / departemen pelapor tiket" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div class="w-full lg:max-w-md">
                        <x-input-label for="searchKeyword" value="Cari Departemen" class="sr-only" />
                        <x-text-input id="searchKeyword" type="text"
                            wire:model.live.debounce.300ms="searchKeyword"
                            placeholder="Cari departemen / kode / kepala..."
                            class="block w-full" />
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <div class="w-28">
                            <x-input-label for="itemsPerPage" value="Per halaman" class="sr-only" />
                            <x-select-input id="itemsPerPage" wire:model.live="itemsPerPage">
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="20">20</option>
                                <option value="100">100</option>
                            </x-select-input>
                        </div>
                        <x-primary-button type="button" wire:click="openCreate">
                            + Tambah Departemen
                        </x-primary-button>
                        <x-toolbar-refresh-reset :label="null" />
                    </div>
                </div>
            </div>

            {{-- TABLE WRAPPER --}}
            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    <table class="ds-table">
                        <thead class="sticky top-0 z-10">
                            <tr>
                                <th>ID</th>
                                <th>Nama Departemen</th>
                                <th>Kode</th>
                                <th>Kepala Unit</th>
                                <th class="ds-c">Status</th>
                                <th class="ds-c">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->rows as $row)
                                <tr wire:key="dept-{{ $row->id }}">
                                    <td class="ds-td-token">{{ $row->id }}</td>
                                    <td class="ds-td-strong">{{ $row->name }}</td>
                                    <td>{{ $row->code ?: '—' }}</td>
                                    <td>{{ $row->head_name ?: '—' }}</td>
                                    <td class="ds-c">
                                        <x-badge :variant="$row->is_active ? 'success' : 'gray'">
                                            {{ $row->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </x-badge>
                                    </td>
                                    <td class="ds-c">
                                        <div class="flex justify-center gap-2">
                                            <x-action-edit wire:click="openEdit({{ $row->id }})" />
                                            <x-action-delete :action="'requestDelete(' . $row->id . ')'"
                                                title="Hapus Departemen" message="Yakin hapus departemen {{ $row->name }}?" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-10">
                                        <div class="flex flex-col items-center justify-center gap-3">
                                            <svg class="w-12 h-12 text-muted-soft" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                                            <p class="text-base font-medium text-muted dark:text-gray-400">Data departemen tidak ditemukan.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="sticky bottom-0 z-10 px-4 py-3 bg-canvas border-t border-hairline rounded-b-2xl dark:bg-gray-900 dark:border-gray-700">
                    {{ $this->rows->links() }}
                </div>
            </div>

            <livewire:pages::master.master-departments.master-departments-actions wire:key="master-departments-actions" />

        </div>
    </div>
</div>
