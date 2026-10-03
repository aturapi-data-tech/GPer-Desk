<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;

/**
 * Master Struktur Organisasi — tab Unit: pohon ORG_UNIT (nama, induk, jenis, kepala);
 * tab Jenis Unit: master ORG_JENIS (RS lain mengisi tingkatannya sendiri). Warna badge mengikuti urutan jenis.
 * Dasar: Perdir PT Al-Ikhlas Madinah 104/SK/PT-AIM/10/2025 (skrip database/schema/organisasi.sql).
 * Unit hanya puluhan → tanpa paginasi; tanpa pencarian tampil sebagai pohon (CONNECT BY), dengan pencarian tampil rata.
 */
new class extends Component {
    private const WARNA = ['brand', 'info', 'success', 'warning', 'purple', 'gray'];

    public string $tab = 'unit';            // unit | jenis
    public string $searchKeyword = '';
    public ?string $jenisKode = null;       // null = tambah
    public array $jenisForm = ['nama' => '', 'urut' => 0];

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword']);
    }

    public function openCreate(?int $indukId = null): void { $this->dispatch('master.organisasi.openCreate', indukId: $indukId); }
    public function openEdit(int $id): void { $this->dispatch('master.organisasi.openEdit', id: $id); }
    public function requestDelete(int $id): void { $this->dispatch('master.organisasi.requestDelete', id: $id); }

    #[On('master.organisasi.saved')]
    public function refreshAfterSaved(): void { unset($this->rows, $this->jenis); }

    /** Master jenis: kode => {nama, urut, warna, dipakai}. */
    #[Computed]
    public function jenis()
    {
        $dipakai = DB::table('org_unit')->selectRaw('jenis, COUNT(*) n')->groupBy('jenis')->pluck('n', 'jenis');

        return DB::table('org_jenis')->orderBy('urut')->orderBy('nama')->get()->values()
            ->map(fn ($j, $i) => (object) ['kode' => $j->kode, 'nama' => $j->nama, 'urut' => (int) $j->urut,
                'warna' => self::WARNA[$i % count(self::WARNA)], 'dipakai' => (int) ($dipakai[$j->kode] ?? 0)])
            ->keyBy('kode');
    }

    // ── master jenis unit ──
    public function bukaJenis(?string $kode = null): void
    {
        abort_unless(\App\Services\DokumenService::bolehUbah(), 403);
        $this->resetValidation();
        $j = $kode ? DB::table('org_jenis')->where('kode', $kode)->first() : null;
        $this->jenisKode = $j?->kode;
        $this->jenisForm = ['nama' => (string) ($j->nama ?? ''), 'urut' => (int) ($j->urut ?? (DB::table('org_jenis')->max('urut') + 1))];
        $this->dispatch('open-modal', name: 'master-organisasi-jenis');
    }

    public function simpanJenis(): void
    {
        abort_unless(\App\Services\DokumenService::bolehUbah(), 403);
        $this->validate(['jenisForm.nama' => 'required|string|max:60', 'jenisForm.urut' => 'required|integer|min:0|max:999'],
            ['jenisForm.nama.required' => 'Nama jenis wajib diisi.']);
        $nama = trim($this->jenisForm['nama']);
        if ($this->jenisKode) {
            DB::table('org_jenis')->where('kode', $this->jenisKode)->update(['nama' => $nama, 'urut' => (int) $this->jenisForm['urut']]);
        } else {
            // kode dibentuk dari nama (huruf kapital, maks. 12) — unik dengan akhiran angka bila bentrok
            $dasar = substr(preg_replace('/[^A-Z0-9]+/', '', strtoupper(\Illuminate\Support\Str::ascii($nama))) ?: 'JENIS', 0, 10);
            $kode = $dasar;
            for ($i = 2; DB::table('org_jenis')->where('kode', $kode)->exists(); $i++) $kode = $dasar . $i;
            DB::table('org_jenis')->insert(['kode' => $kode, 'nama' => $nama, 'urut' => (int) $this->jenisForm['urut']]);
        }
        $this->dispatch('close-modal', name: 'master-organisasi-jenis');
        $this->dispatch('toast', type: 'success', message: 'Jenis unit disimpan.');
        $this->refreshAfterSaved();
    }

    public function hapusJenis(string $kode): void
    {
        abort_unless(\App\Services\DokumenService::bolehUbah(), 403);
        if (DB::table('org_unit')->where('jenis', $kode)->exists()) {
            $this->dispatch('toast', type: 'error', message: 'Jenis masih dipakai unit — ganti jenis unit-unit itu dulu.');
            return;
        }
        DB::table('org_jenis')->where('kode', $kode)->delete();
        $this->dispatch('toast', type: 'success', message: 'Jenis unit dihapus.');
        $this->refreshAfterSaved();
    }

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
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <x-tabs variant="pill">
                            <x-tab :active="$tab === 'unit'" wire:click="$set('tab', 'unit')">Unit</x-tab>
                            <x-tab :active="$tab === 'jenis'" wire:click="$set('tab', 'jenis')">Jenis Unit</x-tab>
                        </x-tabs>
                        @if ($tab === 'unit')
                            <div class="w-full sm:w-80">
                                <x-text-input type="text" wire:model.live.debounce.300ms="searchKeyword"
                                    placeholder="Cari unit / nama kepala..." class="block w-full" />
                            </div>
                        @endif
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        @if (\App\Services\DokumenService::bolehUbah())
                            @if ($tab === 'unit')
                                <x-primary-button type="button" wire:click="openCreate">+ Tambah Unit</x-primary-button>
                            @else
                                <x-primary-button type="button" wire:click="bukaJenis">+ Tambah Jenis</x-primary-button>
                            @endif
                        @endif
                        <x-toolbar-refresh-reset :label="null" />
                    </div>
                </div>
            </div>

            {{-- TABEL --}}
            <div class="flex flex-col flex-1 min-h-0 mt-4 border shadow-sm bg-canvas border-hairline rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-auto rounded-2xl">
                    @if ($tab === 'unit')
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
                                            <x-badge :variant="$this->jenis[$r->jenis]->warna ?? 'gray'">{{ $this->jenis[$r->jenis]->nama ?? $r->jenis }}</x-badge>
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
                    @else
                    <table class="ds-table">
                        <thead class="sticky top-0 z-10">
                            <tr><th>Jenis unit</th><th class="ds-c">Urutan</th><th class="ds-c">Dipakai</th><th class="ds-c">Aksi</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($this->jenis as $j)
                                <tr wire:key="jenis-{{ $j->kode }}">
                                    <td><x-badge :variant="$j->warna">{{ $j->nama }}</x-badge></td>
                                    <td class="ds-c">{{ $j->urut }}</td>
                                    <td class="ds-c">{{ $j->dipakai }} unit</td>
                                    <td class="ds-c">
                                        @if (\App\Services\DokumenService::bolehUbah())
                                            <div class="flex justify-center gap-1">
                                                <x-action-edit wire:click="bukaJenis('{{ $j->kode }}')" />
                                                <x-action-delete :action="'hapusJenis(\'' . $j->kode . '\')'" title="Hapus Jenis"
                                                    message="Hapus jenis {{ $j->nama }}?" />
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-6 py-16 text-center text-muted dark:text-gray-400">Belum ada jenis unit.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    @endif
                </div>
                @if ($tab === 'jenis')
                    <div class="px-4 py-3 text-xs border-t border-hairline text-muted dark:border-gray-700 dark:text-gray-400">
                        Jenis unit menentukan pilihan "Jenis" di form unit. Urutan = tingkatan (juga warna badge). Sesuaikan dengan model organisasi RS masing-masing.
                    </div>
                @endif
            </div>

            {{-- MODAL: tambah / ubah jenis unit --}}
            <x-modal name="master-organisasi-jenis" size="xl" focusable>
                <div class="space-y-4">
                    <h2 class="ds-display-sm dark:text-gray-100">{{ $jenisKode ? 'Ubah Jenis Unit' : 'Tambah Jenis Unit' }}</h2>
                    <div>
                        <x-input-label value="Nama jenis" />
                        <x-text-input wire:model="jenisForm.nama" class="w-full mt-1" placeholder="mis. Bidang, Seksi, Ruangan" />
                        <x-input-error :messages="$errors->get('jenisForm.nama')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label value="Urutan (tingkat)" />
                        <x-text-input type="number" min="0" wire:model="jenisForm.urut" class="w-32 mt-1" />
                        <x-input-error :messages="$errors->get('jenisForm.urut')" class="mt-1" />
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'master-organisasi-jenis' })">Batal</x-secondary-button>
                        <x-primary-button type="button" wire:click="simpanJenis">Simpan</x-primary-button>
                    </div>
                </div>
            </x-modal>

            <livewire:pages::master.master-organisasi.master-organisasi-actions wire:key="master-organisasi-actions" />
        </div>
    </div>
</div>
