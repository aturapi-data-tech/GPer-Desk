<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use App\Services\DokumenService;

/**
 * Pengaturan Penomoran — master jenis naskah (pola nomor, kode surat, reset tahunan), penghitung
 * nomor (buku catatan nomor — Pedoman hal. 66, dikelola Sekretariat), dan singkatan bagian (hal. 65).
 */
new class extends Component {
    public string $tab = 'jenis';    // jenis | penghitung | unit
    public ?string $editKode = null;
    public array $jenisForm = [];
    public array $penghitung = [];   // "seri|unit|tahun" => no_terakhir
    public array $unitBaru = ['kode' => '', 'nama' => ''];
    public array $sinkron = [];

    public function mount(): void
    {
        abort_unless(DokumenService::bolehUbah(), 403);
        $this->muatPenghitung();
    }

    private function muatPenghitung(): void
    {
        $this->penghitung = DB::table('dok_penomoran')->orderBy('seri')->orderBy('tahun')->get()
            ->mapWithKeys(fn ($r) => ["{$r->seri}|{$r->unit_kode}|{$r->tahun}" => (int) $r->no_terakhir])->all();
    }

    #[Computed]
    public function jenisSemua()
    {
        return DB::table('dok_jenis')->orderBy('urut')->get();
    }

    #[Computed]
    public function unitSemua()
    {
        return DB::table('dok_unit')->orderBy('kode')->get();
    }

    public function ubahJenis(string $kode): void
    {
        $r = DB::table('dok_jenis')->where('kode', $kode)->first();
        $this->resetValidation();
        $this->editKode = $kode;
        $this->jenisForm = (array) $r;
        $this->dispatch('open-modal', name: 'dokumen-pengaturan-jenis');
    }

    public function simpanJenis(): void
    {
        $this->validate([
            'jenisForm.nama' => 'required|string|max:100',
            'jenisForm.tingkat' => 'required|integer|min:0|max:5',
            'jenisForm.kode_surat' => 'nullable|string|max:10',
            'jenisForm.model_nomor' => 'required|in:' . implode(',', array_keys(DokumenService::MODEL_NOMOR)),
            'jenisForm.pola_nomor' => ['required_unless:jenisForm.model_nomor,TANPA', 'nullable', 'string', 'max:100', function ($a, $v, $fail) {
                $model = $this->jenisForm['model_nomor'] ?? 'TANPA';
                $wajib = ['SENDIRI' => ['{NNN}'], 'INDUK' => ['{INDUK}'], 'TURUNAN' => ['{INDUK}', '{NN}']][$model] ?? [];
                foreach ($wajib as $t) if (! str_contains($v, $t)) $fail('Pola model ' . DokumenService::MODEL_NOMOR[$model] . " wajib memuat {$t}.");
            }],
            'jenisForm.seri' => 'required_if:jenisForm.model_nomor,SENDIRI|nullable|string|max:10',
        ], [
            'jenisForm.pola_nomor.required_unless' => 'Pola nomor wajib diisi kecuali model Tanpa nomor.',
            'jenisForm.seri.required_if' => 'Deret sendiri wajib punya seri penghitung.',
        ]);
        $f = $this->jenisForm;
        DB::table('dok_jenis')->where('kode', $this->editKode)->update([
            'nama' => $f['nama'], 'tingkat' => (int) $f['tingkat'], 'model_nomor' => $f['model_nomor'], 'kode_surat' => $f['kode_surat'] ?: null,
            'seri' => $f['seri'] ?: null, 'pola_nomor' => $f['pola_nomor'] ?: null,
            'reset_tahunan' => $f['reset_tahunan'] === 'Y' ? 'Y' : 'N', 'pakai_unit' => $f['pakai_unit'] === 'Y' ? 'Y' : 'N',
            'disahkan_oleh' => $f['disahkan_oleh'] ?: null, 'bisa_disusun' => $f['bisa_disusun'] === 'Y' ? 'Y' : 'N',
            'aktif' => $f['aktif'] === 'Y' ? 'Y' : 'N',
        ]);
        DokumenService::lupakanCacheMaster();
        $this->editKode = null;
        unset($this->jenisSemua);
        $this->dispatch('close-modal', name: 'dokumen-pengaturan-jenis');
        $this->dispatch('toast', type: 'success', message: 'Jenis naskah disimpan.');
    }

    public function simpanPenghitung(string $kunci): void
    {
        [$seri, $unit, $tahun] = explode('|', $kunci);
        $no = max(0, (int) ($this->penghitung[$kunci] ?? 0));
        DB::table('dok_penomoran')->where(['seri' => $seri, 'unit_kode' => $unit, 'tahun' => (int) $tahun])->update(['no_terakhir' => $no]);
        $this->dispatch('toast', type: 'success', message: "Penghitung {$seri}" . ($tahun ? " {$tahun}" : '') . " = {$no}; nomor berikutnya " . ($no + 1) . '.');
    }

    public function tambahPenghitung(string $seri, int $tahun): void
    {
        DB::table('dok_penomoran')->updateOrInsert(['seri' => strtoupper($seri), 'unit_kode' => '-', 'tahun' => $tahun], ['no_terakhir' => 0]);
        $this->muatPenghitung();
    }

    public function jalankanSinkron(): void
    {
        $this->sinkron = DokumenService::sinkronPenghitung();
        $this->muatPenghitung();
        $this->dispatch('toast', type: 'success', message: 'Sinkronisasi selesai.');
    }

    public function bukaUnit(?string $kode = null): void
    {
        $this->resetValidation();
        $u = $kode ? DB::table('dok_unit')->where('kode', $kode)->first() : null;
        $this->unitBaru = ['kode' => $u->kode ?? '', 'nama' => $u->nama ?? ''];
        $this->dispatch('open-modal', name: 'dokumen-pengaturan-unit');
    }

    public function simpanUnit(): void
    {
        $this->validate(['unitBaru.kode' => 'required|string|max:10|regex:/^[a-z0-9]+$/', 'unitBaru.nama' => 'required|string|max:150'],
            ['unitBaru.kode.regex' => 'Singkatan huruf kecil tanpa spasi (Pedoman hal. 65).']);
        DB::table('dok_unit')->updateOrInsert(['kode' => $this->unitBaru['kode']], ['nama' => $this->unitBaru['nama'], 'aktif' => 'Y']);
        DokumenService::lupakanCacheMaster();
        $this->unitBaru = ['kode' => '', 'nama' => ''];
        unset($this->unitSemua);
        $this->dispatch('close-modal', name: 'dokumen-pengaturan-unit');
        $this->dispatch('toast', type: 'success', message: 'Singkatan bagian disimpan.');
    }

    public function aktifUnit(string $kode): void
    {
        DB::table('dok_unit')->where('kode', $kode)->update(['aktif' => DB::raw("CASE aktif WHEN 'Y' THEN 'N' ELSE 'Y' END")]);
        DokumenService::lupakanCacheMaster();
        unset($this->unitSemua);
    }
};
?>

<div>
    <x-page-title title="Pengaturan Penomoran" subtitle="Jenis naskah, pola nomor & buku catatan nomor — Pedoman Tata Naskah BAB IV.C" />

    <div class="w-full h-[calc(100vh-5rem)] flex flex-col bg-surface-soft dark:bg-gray-900">
        <div class="flex flex-col flex-1 min-h-0 px-6 pt-2 pb-6">

            {{-- TOOLBAR --}}
            <div class="sticky z-30 px-4 py-3 bg-surface-soft border-b border-hairline top-20 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <x-tabs variant="pill">
                        <x-tab :active="$tab === 'jenis'" wire:click="$set('tab', 'jenis')">Jenis naskah &amp; pola nomor</x-tab>
                        <x-tab :active="$tab === 'penghitung'" wire:click="$set('tab', 'penghitung')">Buku catatan nomor</x-tab>
                        <x-tab :active="$tab === 'unit'" wire:click="$set('tab', 'unit')">Singkatan bagian</x-tab>
                    </x-tabs>
                    <div class="flex items-center justify-end gap-2">
                        @if ($tab === 'penghitung')
                            <x-secondary-button type="button" wire:click="tambahPenghitung('SK', {{ now()->year }})">+ SK {{ now()->year }}</x-secondary-button>
                            <x-confirm-button variant="secondary" action="jalankanSinkron" title="Sinkronkan penghitung"
                                message="Naikkan penghitung deret tahunan ke nomor tertinggi yang tercatat? (Penghitung tidak pernah diturunkan; deret SPO hanya diberi usulan.)">Sinkronkan dari registri</x-confirm-button>
                        @elseif ($tab === 'unit')
                            <x-primary-button type="button" wire:click="bukaUnit">+ Tambah Singkatan</x-primary-button>
                        @endif
                    </div>
                </div>
            </div>

            {{-- TABLE WRAPPER --}}
            <div class="mt-4 flex flex-col flex-1 min-h-0 bg-canvas border border-hairline shadow-sm rounded-2xl dark:border-gray-700 dark:bg-gray-900">
                <div class="flex-1 min-h-0 overflow-x-auto overflow-y-auto rounded-t-2xl">
                    @if ($tab === 'jenis')
                        <table class="ds-table">
                            <thead class="sticky top-0 z-10">
                                <tr><th>Jenis naskah</th><th>Penomoran</th><th>Disahkan</th><th class="ds-c">Status</th><th class="ds-c">Aksi</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($this->jenisSemua as $j)
                                    <tr wire:key="j-{{ $j->kode }}">
                                        <td>
                                            <div class="ds-td-strong">{{ $j->nama }}</div>
                                            <div class="text-xs text-muted"><span class="font-mono">{{ $j->kode }}</span> · Tingkat {{ $j->tingkat }} {{ DokumenService::TINGKAT[$j->tingkat] ?? '' }}</div>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <x-badge :variant="['SENDIRI' => 'success', 'INDUK' => 'info', 'TURUNAN' => 'warning'][$j->model_nomor] ?? 'gray'">{{ DokumenService::MODEL_NOMOR[$j->model_nomor] ?? $j->model_nomor }}</x-badge>
                                                <span class="font-mono text-sm">{{ $j->pola_nomor ?: '' }}</span>
                                            </div>
                                            <div class="text-xs text-muted">
                                                <span class="font-mono">{{ DokumenService::contohNomor($j) }}</span>
                                                @if ($j->model_nomor === 'SENDIRI')
                                                    · seri {{ $j->seri ?: '—' }} · {{ $j->reset_tahunan === 'Y' ? 'reset tahunan' : 'menerus' }}
                                                @endif
                                            </div>
                                        </td>
                                        <td class="text-sm">{{ $j->disahkan_oleh ?: '—' }}</td>
                                        <td class="ds-c"><x-badge :variant="$j->aktif === 'Y' ? 'success' : 'gray'">{{ $j->aktif === 'Y' ? 'Aktif' : 'Nonaktif' }}</x-badge></td>
                                        <td class="ds-c"><x-action-edit wire:click="ubahJenis('{{ $j->kode }}')" /></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-6 py-16 text-center text-muted">Belum ada jenis naskah.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    @elseif ($tab === 'penghitung')
                        <table class="ds-table">
                            <thead class="sticky top-0 z-10">
                                <tr><th>Seri</th><th>Bagian</th><th>Tahun</th><th>Nomor terakhir</th><th>Berikutnya</th><th class="ds-c">Aksi</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($penghitung as $kunci => $no)
                                    @php [$seri, $unit, $tahun] = explode('|', $kunci); @endphp
                                    <tr wire:key="p-{{ $kunci }}">
                                        <td class="ds-td-token">{{ $seri }}</td>
                                        <td>{{ $unit }}</td>
                                        <td>{{ $tahun ?: 'menerus' }}</td>
                                        <td><x-text-input type="number" min="0" wire:model="penghitung.{{ $kunci }}" class="w-28" /></td>
                                        <td class="font-mono">{{ str_pad((string) ((int) $no + 1), 3, '0', STR_PAD_LEFT) }}</td>
                                        <td class="ds-c"><x-confirm-button variant="primary" action="simpanPenghitung('{{ $kunci }}')" title="Setel penghitung"
                                            message="Setel nomor terakhir {{ $seri }}? Nomor yang sudah terbit tidak berubah.">Setel</x-confirm-button></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-6 py-16 text-center text-muted">Belum ada penghitung — dibuat otomatis saat nomor pertama diambil.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    @else
                        <table class="ds-table">
                            <thead class="sticky top-0 z-10">
                                <tr><th>Singkatan</th><th>Nama bagian / komite / tim</th><th class="ds-c">Status</th><th class="ds-c">Aksi</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($this->unitSemua as $u)
                                    <tr wire:key="u-{{ $u->kode }}">
                                        <td class="ds-td-token">{{ $u->kode }}</td>
                                        <td class="ds-td-strong">{{ $u->nama }}</td>
                                        <td class="ds-c">
                                            <button type="button" wire:click="aktifUnit('{{ $u->kode }}')" title="Klik untuk (non)aktifkan">
                                                <x-badge :variant="$u->aktif === 'Y' ? 'success' : 'gray'">{{ $u->aktif === 'Y' ? 'Aktif' : 'Nonaktif' }}</x-badge>
                                            </button>
                                        </td>
                                        <td class="ds-c"><x-action-edit wire:click="bukaUnit('{{ $u->kode }}')" /></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-6 py-16 text-center text-muted">Belum ada singkatan bagian.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    @endif
                </div>

                {{-- FOOTER: keterangan per tab --}}
                <div class="sticky bottom-0 z-10 px-4 py-3 text-xs bg-canvas border-t border-hairline rounded-b-2xl text-muted dark:bg-gray-900 dark:border-gray-700 dark:text-gray-400">
                    @if ($tab === 'jenis')
                        Model penomoran: <b>Deret sendiri</b> (Perdir/SK/Kebijakan berbagi deret SK, SPO, surat) ·
                        <b>Ikut nomor induk</b> (Pedoman/Panduan/Program = nomor SK penetapnya) ·
                        <b>Turunan induk</b> (Formulir = nomor SPO + -F01) · <b>Tanpa nomor</b> (eksternal, bukti).
                        Kode RS: <b class="font-mono">{{ config('dokumen.kode_rs') }}</b> (<code>DOK_KODE_RS</code> di .env).
                    @elseif ($tab === 'penghitung')
                        Nomor berikutnya = nomor terakhir + 1. Deret SPO menerus (tidak direset) dan dikelola Sekretariat; deret tahunan bisa disinkronkan dari registri.
                        @if ($sinkron)
                            <ul class="mt-1 list-disc list-inside">@foreach ($sinkron as $s)<li>{{ $s }}</li>@endforeach</ul>
                        @endif
                    @else
                        Singkatan huruf kecil tanpa spasi (Pedoman hal. 65). Klik status untuk mengaktifkan / menonaktifkan.
                    @endif
                </div>
            </div>

        </div>
    </div>

    {{-- MODAL: ubah jenis naskah --}}
    <x-modal name="dokumen-pengaturan-jenis" size="full" height="full" focusable>
        <div class="flex flex-col h-full">
            <div class="px-6 py-4 border-b bg-surface-soft border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <x-badge variant="brand">Jenis Naskah</x-badge>
                        <h2 class="mt-2 ds-display-sm dark:text-gray-100">Ubah Jenis Naskah</h2>
                        <p class="font-mono text-sm text-muted">{{ $editKode }} · {{ $jenisForm['nama'] ?? '' }}</p>
                    </div>
                    <x-icon-button color="gray" type="button" x-on:click="$dispatch('close-modal', { name: 'dokumen-pengaturan-jenis' })">✕</x-icon-button>
                </div>
            </div>

            <div class="grid content-start flex-1 grid-cols-1 gap-5 px-6 py-5 overflow-y-auto lg:grid-cols-12">
                <x-border-form title="Identitas" class="h-full lg:col-span-4">
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <x-input-label value="Nama" />
                            <x-text-input wire:model="jenisForm.nama" class="w-full mt-1" />
                            <x-input-error :messages="$errors->get('jenisForm.nama')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Tingkat" />
                            <x-select-input wire:model="jenisForm.tingkat" class="w-full mt-1">
                                @foreach (DokumenService::TINGKAT as $t => $nm)<option value="{{ $t }}">{{ $t }} · {{ $nm }}</option>@endforeach
                            </x-select-input>
                        </div>
                        <div>
                            <x-input-label value="Disahkan oleh" />
                            <x-text-input wire:model="jenisForm.disahkan_oleh" class="w-full mt-1" />
                        </div>
                    </div>
                </x-border-form>

                <x-border-form title="Penomoran (Pedoman Tata Naskah BAB IV.C)" class="h-full lg:col-span-5">
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <x-input-label value="Model penomoran" />
                            <x-select-input wire:model.live="jenisForm.model_nomor" class="w-full mt-1">
                                @foreach (DokumenService::MODEL_NOMOR as $m => $nm)<option value="{{ $m }}">{{ $nm }}</option>@endforeach
                            </x-select-input>
                            <x-input-error :messages="$errors->get('jenisForm.model_nomor')" class="mt-1" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label value="Kode surat" />
                            <x-text-input wire:model="jenisForm.kode_surat" class="w-full mt-1 font-mono" />
                        </div>
                        <div>
                            <x-input-label value="Seri penghitung" />
                            <x-text-input wire:model="jenisForm.seri" class="w-full mt-1 font-mono" />
                            <x-input-error :messages="$errors->get('jenisForm.seri')" class="mt-1" />
                        </div>
                        </div>
                        <div>
                            <x-input-label value="Pola nomor" />
                            <x-text-input wire:model="jenisForm.pola_nomor" class="w-full mt-1 font-mono" />
                            <x-input-error :messages="$errors->get('jenisForm.pola_nomor')" class="mt-1" />
                            <p class="mt-1 text-xs text-muted dark:text-gray-400">
                                Token: <code>{NNN}</code> urut 3 digit · <code>{KODE}</code> kode surat · <code>{UNIT}</code> singkatan bagian ·
                                <code>{RS}</code> = <b class="font-mono">{{ config('dokumen.kode_rs') }}</b> · <code>{MM}</code> · <code>{YYYY}</code> ·
                                <code>{INDUK}</code> nomor dokumen induk · <code>{NN}</code> urut 2 digit per induk.
                                Wajib: deret sendiri <code>{NNN}</code> · ikut induk <code>{INDUK}</code> · turunan <code>{INDUK}</code> + <code>{NN}</code>.
                            </p>
                        </div>
                    </div>
                </x-border-form>

                <x-border-form title="Pengaturan" class="h-full lg:col-span-3">
                    <div class="grid grid-cols-1 gap-4">
                        @foreach (['reset_tahunan' => 'Reset tahunan', 'pakai_unit' => 'Pakai {UNIT}', 'bisa_disusun' => 'Penyusun isi', 'aktif' => 'Aktif'] as $f => $lbl)
                            <div>
                                <x-input-label :value="$lbl" />
                                <x-select-input wire:model="jenisForm.{{ $f }}" class="w-full mt-1"><option value="Y">Ya</option><option value="N">Tidak</option></x-select-input>
                            </div>
                        @endforeach
                    </div>
                </x-border-form>
            </div>

            <div class="sticky bottom-0 z-10 px-6 py-4 border-t bg-surface-soft border-hairline shrink-0 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-2">
                    <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'dokumen-pengaturan-jenis' })">Tutup</x-secondary-button>
                    <x-primary-button type="button" wire:click="simpanJenis">Simpan</x-primary-button>
                </div>
            </div>
        </div>
    </x-modal>

    {{-- MODAL: tambah / ubah singkatan bagian --}}
    <x-modal name="dokumen-pengaturan-unit" size="xl" focusable>
        <div class="space-y-4">
            <h2 class="ds-display-sm dark:text-gray-100">Singkatan Bagian</h2>
            <div>
                <x-input-label value="Singkatan" />
                <x-text-input wire:model="unitBaru.kode" class="w-40 mt-1 font-mono" placeholder="mis. it" />
                <x-input-error :messages="$errors->get('unitBaru.kode')" class="mt-1" />
            </div>
            <div>
                <x-input-label value="Nama bagian / komite / tim" />
                <x-text-input wire:model="unitBaru.nama" class="w-full mt-1" />
                <x-input-error :messages="$errors->get('unitBaru.nama')" class="mt-1" />
            </div>
            <div class="flex justify-end gap-2">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'dokumen-pengaturan-unit' })">Batal</x-secondary-button>
                <x-primary-button type="button" wire:click="simpanUnit">Simpan</x-primary-button>
            </div>
        </div>
    </x-modal>
</div>
