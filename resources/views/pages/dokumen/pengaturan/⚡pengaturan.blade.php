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
        $this->editKode = $kode;
        $this->jenisForm = (array) $r;
    }

    public function simpanJenis(): void
    {
        $this->validate([
            'jenisForm.nama' => 'required|string|max:100',
            'jenisForm.tingkat' => 'required|integer|min:0|max:5',
            'jenisForm.kode_surat' => 'nullable|string|max:10',
            'jenisForm.seri' => 'nullable|string|max:10',
            'jenisForm.pola_nomor' => ['nullable', 'string', 'max:100', function ($a, $v, $fail) {
                if (filled($v) && ! str_contains($v, '{NNN}')) $fail('Pola wajib memuat {NNN}.');
            }],
        ]);
        $f = $this->jenisForm;
        DB::table('dok_jenis')->where('kode', $this->editKode)->update([
            'nama' => $f['nama'], 'tingkat' => (int) $f['tingkat'], 'kode_surat' => $f['kode_surat'] ?: null,
            'seri' => $f['seri'] ?: null, 'pola_nomor' => $f['pola_nomor'] ?: null,
            'reset_tahunan' => $f['reset_tahunan'] === 'Y' ? 'Y' : 'N', 'pakai_unit' => $f['pakai_unit'] === 'Y' ? 'Y' : 'N',
            'disahkan_oleh' => $f['disahkan_oleh'] ?: null, 'bisa_disusun' => $f['bisa_disusun'] === 'Y' ? 'Y' : 'N',
            'aktif' => $f['aktif'] === 'Y' ? 'Y' : 'N',
        ]);
        DokumenService::lupakanCacheMaster();
        $this->editKode = null;
        unset($this->jenisSemua);
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

    public function simpanUnit(): void
    {
        $this->validate(['unitBaru.kode' => 'required|string|max:10|regex:/^[a-z0-9]+$/', 'unitBaru.nama' => 'required|string|max:150'],
            ['unitBaru.kode.regex' => 'Singkatan huruf kecil tanpa spasi (Pedoman hal. 65).']);
        DB::table('dok_unit')->updateOrInsert(['kode' => $this->unitBaru['kode']], ['nama' => $this->unitBaru['nama'], 'aktif' => 'Y']);
        DokumenService::lupakanCacheMaster();
        $this->unitBaru = ['kode' => '', 'nama' => ''];
        unset($this->unitSemua);
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

    <div class="px-6 pt-2 pb-10 space-y-5 bg-surface-soft dark:bg-gray-900">
        <p class="mt-2 text-sm text-muted dark:text-gray-400">
            Kode identitas RS pada nomor: <b class="font-mono">{{ config('dokumen.kode_rs') }}</b> (ubah lewat <code>DOK_KODE_RS</code> di .env).
            Token pola: <code>{NNN}</code> nomor urut 3 digit · <code>{KODE}</code> kode surat · <code>{UNIT}</code> singkatan bagian ·
            <code>{RS}</code> · <code>{MM}</code> · <code>{YYYY}</code>.
        </p>

        <x-border-form title="Jenis naskah & pola nomor">
            <div class="overflow-x-auto">
                <table class="ds-table">
                    <thead><tr><th>Kode</th><th>Nama</th><th class="ds-c">Tingkat</th><th>Kode surat · seri</th><th>Pola nomor</th><th>Contoh</th><th class="ds-c">Reset tahunan</th><th>Disahkan</th><th class="ds-c">Aksi</th></tr></thead>
                    <tbody>
                        @foreach ($this->jenisSemua as $j)
                            @if ($editKode === $j->kode)
                                <tr wire:key="je-{{ $j->kode }}">
                                    <td class="ds-td-token">{{ $j->kode }}</td>
                                    <td><x-text-input wire:model="jenisForm.nama" class="w-48" /></td>
                                    <td><x-select-input wire:model="jenisForm.tingkat">@foreach (DokumenService::TINGKAT as $t => $nm)<option value="{{ $t }}">{{ $t }}</option>@endforeach</x-select-input></td>
                                    <td class="space-y-1"><x-text-input wire:model="jenisForm.kode_surat" class="w-20" placeholder="kode" /><x-text-input wire:model="jenisForm.seri" class="w-20" placeholder="seri" /></td>
                                    <td><x-text-input wire:model="jenisForm.pola_nomor" class="font-mono w-72" /><x-input-error :messages="$errors->get('jenisForm.pola_nomor')" /></td>
                                    <td class="space-y-1 text-xs">
                                        @foreach (['pakai_unit' => 'pakai {UNIT}', 'bisa_disusun' => 'penyusun isi', 'aktif' => 'aktif'] as $f => $lbl)
                                            <div class="flex items-center gap-1">
                                                <x-select-input wire:model="jenisForm.{{ $f }}" class="!py-1 w-20"><option value="Y">Ya</option><option value="N">Tidak</option></x-select-input>
                                                <span>{{ $lbl }}</span>
                                            </div>
                                        @endforeach
                                    </td>
                                    <td class="ds-c"><x-select-input wire:model="jenisForm.reset_tahunan"><option value="Y">Ya</option><option value="N">Tidak</option></x-select-input></td>
                                    <td><x-text-input wire:model="jenisForm.disahkan_oleh" class="w-28" /></td>
                                    <td class="ds-c"><div class="flex gap-1"><x-primary-button type="button" wire:click="simpanJenis">Simpan</x-primary-button><x-secondary-button type="button" wire:click="$set('editKode', null)">Batal</x-secondary-button></div></td>
                                </tr>
                            @else
                                <tr wire:key="j-{{ $j->kode }}" class="{{ $j->aktif === 'Y' ? '' : 'opacity-50' }}">
                                    <td class="ds-td-token">{{ $j->kode }}</td>
                                    <td class="ds-td-strong">{{ $j->nama }}</td>
                                    <td class="ds-c">{{ $j->tingkat }}</td>
                                    <td class="font-mono text-sm">{{ $j->kode_surat ?: '—' }} · {{ $j->seri ?: '—' }}</td>
                                    <td class="font-mono text-sm">{{ $j->pola_nomor ?: '—' }}</td>
                                    <td class="font-mono text-xs text-muted">{{ DokumenService::contohNomor($j) }}</td>
                                    <td class="ds-c">{{ $j->reset_tahunan === 'Y' ? 'Ya' : 'Tidak' }}</td>
                                    <td class="text-sm">{{ $j->disahkan_oleh ?: '—' }}</td>
                                    <td class="ds-c"><x-action-edit wire:click="ubahJenis('{{ $j->kode }}')" /></td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-border-form>

        <x-border-form title="Buku catatan nomor (penghitung)">
            <p class="mb-3 text-sm text-muted dark:text-gray-400">
                Nomor berikutnya = nomor terakhir + 1. Deret SPO menerus (tidak direset) dan dikelola Sekretariat;
                deret tahunan bisa disinkronkan dari nomor yang sudah tercatat di registri.
            </p>
            <table class="ds-table">
                <thead><tr><th>Seri</th><th>Bagian</th><th>Tahun</th><th>Nomor terakhir</th><th>Berikutnya</th><th class="ds-c">Aksi</th></tr></thead>
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
                        <tr><td colspan="6" class="text-muted">Belum ada penghitung — dibuat otomatis saat nomor pertama diambil.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="flex flex-wrap items-center gap-2 mt-3">
                <x-secondary-button type="button" wire:click="tambahPenghitung('SK', {{ now()->year }})">+ SK {{ now()->year }}</x-secondary-button>
                <x-confirm-button variant="secondary" action="jalankanSinkron" title="Sinkronkan penghitung"
                    message="Naikkan penghitung deret tahunan ke nomor tertinggi yang tercatat? (Penghitung tidak pernah diturunkan; deret SPO hanya diberi usulan.)">Sinkronkan dari registri</x-confirm-button>
            </div>
            @if ($sinkron)
                <ul class="mt-2 text-xs list-disc list-inside text-muted">@foreach ($sinkron as $s)<li>{{ $s }}</li>@endforeach</ul>
            @endif
        </x-border-form>

        <x-border-form title="Singkatan bagian / komite / tim (Pedoman hal. 65)">
            <div class="flex flex-wrap gap-2 mb-3">
                @foreach ($this->unitSemua as $u)
                    <button type="button" wire:click="aktifUnit('{{ $u->kode }}')" title="Klik untuk (non)aktifkan"
                        class="{{ $u->aktif === 'Y' ? '' : 'opacity-40 line-through' }}"><x-badge variant="gray">{{ $u->kode }} · {{ $u->nama }}</x-badge></button>
                @endforeach
            </div>
            <div class="flex items-end gap-2">
                <div><x-input-label value="Singkatan" /><x-text-input wire:model="unitBaru.kode" class="w-28 mt-1 font-mono" placeholder="mis. it" /></div>
                <div class="flex-1"><x-input-label value="Nama bagian" /><x-text-input wire:model="unitBaru.nama" class="w-full mt-1" /></div>
                <x-primary-button type="button" wire:click="simpanUnit">Simpan</x-primary-button>
            </div>
            <x-input-error :messages="$errors->get('unitBaru.kode')" class="mt-1" />
        </x-border-form>
    </div>
</div>
