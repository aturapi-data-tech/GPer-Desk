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

    public string $tab = 'unit';            // unit | bagan | jenis
    public string $searchKeyword = '';
    public ?string $jenisKode = null;       // null = tambah
    public ?int $hapusId = null;            // konfirmasi hapus dari bagan
    public string $hapusNama = '';
    public array $jenisForm = ['nama' => '', 'urut' => 0];

    public function resetFilters(): void
    {
        $this->reset(['searchKeyword']);
    }

    public function openCreate(?int $indukId = null): void { $this->dispatch('master.organisasi.openCreate', indukId: $indukId); }
    public function openEdit(int $id): void { $this->dispatch('master.organisasi.openEdit', id: $id); }
    public function requestDelete(int $id): void { $this->dispatch('master.organisasi.requestDelete', id: $id); }

    public function tanyaHapus(int $id): void
    {
        $this->hapusId = $id;
        $this->hapusNama = (string) DB::table('org_unit')->where('unit_id', $id)->value('nama');
        $this->dispatch('open-modal', name: 'master-organisasi-hapus');
    }

    public function konfirmasiHapus(): void
    {
        if ($this->hapusId) {
            $this->requestDelete($this->hapusId);
        }
        $this->hapusId = null;
        $this->dispatch('close-modal', name: 'master-organisasi-hapus');
    }

    #[On('master.organisasi.saved')]
    public function refreshAfterSaved(): void { unset($this->rows, $this->jenis, $this->bagan); }

    /** Pohon bersarang untuk tab Bagan: [{id, nama, jenis, kepala, anak[]}]. */
    #[Computed]
    public function bagan(): array
    {
        $baris = DB::table('org_unit')->orderBy('urut')->orderBy('nama')->get(['unit_id', 'induk_id', 'nama', 'jenis', 'nama_kepala']);
        $perInduk = $baris->groupBy(fn ($r) => (string) ($r->induk_id ?? ''));
        $bangun = function (string $induk) use (&$bangun, $perInduk) {
            return ($perInduk[$induk] ?? collect())->map(fn ($r) => ['id' => (int) $r->unit_id, 'nama' => $r->nama,
                'jenis' => $r->jenis, 'kepala' => $r->nama_kepala, 'anak' => $bangun((string) $r->unit_id)])->values()->all();
        };

        return $bangun('');
    }

    /** Warna CSS per kode jenis (mengikuti warna badge). */
    public function getWarnaBaganProperty(): array
    {
        $css = ['brand' => 'var(--primary, #16a34a)', 'info' => '#2563eb', 'success' => '#059669', 'warning' => '#d97706', 'purple' => '#7c3aed', 'gray' => '#6b7280'];

        return $this->jenis->map(fn ($j) => $css[$j->warna] ?? '#6b7280')->all();
    }

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

        // nomor bertingkat (1, 1.2, 1.2.1): banyaknya angka = tingkat; awalan sama = satu induk
        $hitung = [];
        foreach ($rows as $r) {
            $hitung = array_slice($hitung, 0, $r->lv);
            $hitung[$r->lv - 1] = ($hitung[$r->lv - 1] ?? 0) + 1;
            $r->nomor = implode('.', $hitung);
        }

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
                            <x-tab :active="$tab === 'bagan'" wire:click="$set('tab', 'bagan')">Bagan</x-tab>
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
                            @elseif ($tab === 'jenis')
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
                                            <span class="font-mono text-xs text-muted dark:text-gray-400" title="Tingkat {{ $r->lv }}">{{ $r->nomor }}</span>
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
                    @elseif ($tab === 'bagan')
                    <div x-data="{ z: 1 }" class="relative">
                        <div class="sticky top-0 left-0 z-10 flex items-center gap-1 p-2">
                            @if (\App\Services\DokumenService::bolehUbah())
                                <span class="mr-2 text-xs text-muted dark:text-gray-400">Tombol di tiap kotak: <b>+</b> tambah unit di bawahnya · ubah · hapus</span>
                            @endif
                            <x-secondary-button type="button" x-on:click="z = Math.max(0.4, +(z - 0.1).toFixed(1))" title="Perkecil">−</x-secondary-button>
                            <x-secondary-button type="button" x-on:click="z = 1" title="Ukuran asli"><span x-text="Math.round(z * 100) + '%'"></span></x-secondary-button>
                            <x-secondary-button type="button" x-on:click="z = Math.min(1.5, +(z + 0.1).toFixed(1))" title="Perbesar">+</x-secondary-button>
                        </div>
                        <div class="org-bagan" x-bind:style="'zoom:' + z">
                            <ul>
                                @foreach ($this->bagan as $akar)
                                    @include('pages.master.master-organisasi.bagan-node', ['node' => $akar, 'warna' => $this->warnaBagan, 'boleh' => \App\Services\DokumenService::bolehUbah()])
                                @endforeach
                            </ul>
                        </div>
                    </div>
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

            {{-- MODAL: konfirmasi hapus unit dari bagan --}}
            <x-modal name="master-organisasi-hapus" size="md">
                <div class="space-y-4">
                    <h2 class="ds-display-sm dark:text-gray-100">Hapus unit?</h2>
                    <p class="text-sm text-muted dark:text-gray-400"><b>{{ $hapusNama }}</b> akan dihapus. Unit yang masih punya unit di bawahnya tidak bisa dihapus.</p>
                    <div class="flex justify-end gap-2">
                        <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'master-organisasi-hapus' })">Batal</x-secondary-button>
                        <x-danger-button type="button" wire:click="konfirmasiHapus">Ya, hapus</x-danger-button>
                    </div>
                </div>
            </x-modal>

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

            <style>
                /* Bagan organisasi: garis penghubung dengan ::before/::after (tanpa pustaka). */
                .org-bagan { padding: 8px 24px 32px; width: max-content; min-width: 100%; }
                .org-bagan ul { display: flex; justify-content: center; position: relative; padding: 20px 0 0; margin: 0; }
                .org-bagan > ul { padding-top: 0; }
                .org-bagan li { list-style: none; position: relative; padding: 20px 6px 0; display: flex; flex-direction: column; align-items: center; }
                .org-bagan li::before, .org-bagan li::after { content: ''; position: absolute; top: 0; right: 50%; width: 50%; height: 20px; border-top: 1px solid #9ca3af; }
                .org-bagan li::after { right: auto; left: 50%; border-left: 1px solid #9ca3af; }
                .org-bagan li:only-child::before, .org-bagan li:only-child::after { display: none; }
                .org-bagan li:only-child { padding-top: 0; }
                .org-bagan li:first-child::before, .org-bagan li:last-child::after { border: 0 none; }
                .org-bagan li:last-child::before { border-right: 1px solid #9ca3af; border-radius: 0 6px 0 0; }
                .org-bagan li:first-child::after { border-radius: 6px 0 0 0; }
                .org-bagan ul ul::before { content: ''; position: absolute; top: 0; left: 50%; height: 20px; border-left: 1px solid #9ca3af; }
                .org-bagan > ul > li:only-child { padding-top: 0; }
                /* unit setara tanpa bawahan: ditumpuk dengan garis siku di kiri + cabang per kotak (bukan rantai di tengah) */
                .org-tumpuk { position: relative; display: flex; flex-direction: column; align-items: flex-start; gap: 8px;
                              padding: 20px 0 0 24px; }
                .org-tumpuk::before { content: ''; position: absolute; top: 10px; bottom: 28px; left: 12px; border-left: 1px solid #9ca3af; }
                .org-tumpuk::after { content: ''; position: absolute; top: 0; left: 12px; width: calc(50% - 12px); height: 10px;
                                     border-right: 1px solid #9ca3af; border-bottom: 1px solid #9ca3af; }
                .org-tumpuk > .org-kotak::before { content: ''; position: absolute; top: 28px; left: -13px; width: 12px; border-top: 1px solid #9ca3af; }
                .org-kotak { position: relative; width: 170px; padding: 6px 8px; border: 1px solid #d1d5db; border-top-width: 3px; border-radius: 10px;
                             background: #fff; text-align: center; box-shadow: 0 1px 2px rgba(0,0,0,.05); }
                .org-nama { font-size: 11.5px; font-weight: 600; line-height: 1.25; color: #111827; }
                .org-kepala { margin-top: 3px; font-size: 10.5px; line-height: 1.2; color: #4b5563; }
                .org-kosong { font-style: italic; color: #9ca3af; }
                .org-aksi { display: flex; justify-content: center; gap: 4px; margin-top: 5px; padding-top: 5px; border-top: 1px dashed #e5e7eb; }
                .org-btn { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 22px; border-radius: 6px;
                           border: 1px solid #d1d5db; background: #f9fafb; color: #4b5563; cursor: pointer; }
                .org-btn svg { width: 13px; height: 13px; }
                .org-btn:hover { background: #e5e7eb; color: #111827; }
                .org-btn-tambah { color: var(--primary, #16a34a); border-color: var(--primary, #16a34a); }
                .org-btn-tambah:hover { background: var(--primary, #16a34a); color: #fff; }
                .org-btn-hapus { color: #dc2626; border-color: #fecaca; background: #fef2f2; }
                .org-btn-hapus:hover { background: #dc2626; color: #fff; }
                .dark .org-aksi { border-top-color: #374151; }
                .dark .org-btn { background: #1f2937; border-color: #4b5563; color: #d1d5db; }
                .dark .org-kotak { background: #111827; border-color: #374151; }
                .dark .org-nama { color: #f3f4f6; } .dark .org-kepala { color: #9ca3af; }
            </style>

            <livewire:pages::master.master-organisasi.master-organisasi-actions wire:key="master-organisasi-actions" />
        </div>
    </div>
</div>
