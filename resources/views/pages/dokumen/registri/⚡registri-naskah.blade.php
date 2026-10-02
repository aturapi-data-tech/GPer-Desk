<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;
use Illuminate\Support\Str;
use App\Services\DokumenService;
use App\Support\TataNaskah;

/**
 * Penyusun naskah dinas (JSON) — Peraturan · Keputusan/Kebijakan · Pedoman/Panduan/Program · Surat Edaran.
 * Bagian mengikuti bentuk baku Pedoman Tata Naskah BAB III (App\Support\TataNaskah). Isi disimpan ke
 * DOK_JSON.isi.naskah; cetak PDF/DOCX dari JSON. Satu editor TinyMCE dipakai bergantian per bagian.
 *
 * Kunci bagian ($pilih): kepala · menimbang · mengingat · menetapkan · pasal.N · diktum.N · bab.N ·
 * bab.N.sub.M · kepustakaan · yth · isi · kaki · tembusan
 */
new class extends Component {
    use WithFileUploads;

    public $gambarBaru = null;
    public ?int $dokId = null;
    public string $jenis = '';
    public string $nomor = '';
    public array $n = [];
    public string $pilih = 'kepala';
    public string $html = '';          // editor bersama untuk bagian ber-HTML
    public string $daftarTeks = '';    // bagian daftar: satu butir per baris
    public bool $perluDicek = false;

    private const DAFTAR = ['menimbang', 'mengingat', 'tembusan', 'yth'];

    #[On('dokumen.registri.openNaskah')]
    public function buka(int $dokId): void
    {
        $data = DokumenService::baca($dokId);
        if (! $data) return;
        [$row, $json] = $data;
        $this->dokId = $dokId;
        $this->jenis = (string) $row->jenis_kode;
        $this->nomor = (string) ($row->dok_no ?? '');
        $this->n = $json['isi']['naskah'] ?? TataNaskah::kosong($this->jenis, DokumenService::judul($row, $json));
        $this->perluDicek = (bool) ($json['isiSumber']['perluDicek'] ?? false);
        $this->pilih = 'kepala';
        $this->muatBagian();
        $this->dispatch('open-modal', name: 'dokumen-registri-naskah');
    }

    // ── nilai bagian aktif ↔ editor ─────────────────────────────────────────
    private function jenisBagian(string $k): string
    {
        if (in_array($k, self::DAFTAR, true)) return 'daftar';
        if (preg_match('/^blok\.(\d+)$/', $k, $m)) return (($this->n['blok'][(int) $m[1]]['tipe'] ?? 'html') === 'gambar') ? 'gambar' : 'html';
        if (preg_match('/^(pasal|diktum)\.\d+$|^bab\.\d+(\.sub\.\d+)?$|^(isi|kepustakaan)$/', $k)) return 'html';

        return 'isian';
    }

    private function jalurHtml(string $k): ?string
    {
        if (preg_match('/^(pasal|diktum)\.(\d+)$/', $k, $m)) return "{$m[1]}.{$m[2]}.isi";
        if (preg_match('/^bab\.(\d+)$/', $k, $m)) return "bab.{$m[1]}.isi";
        if (preg_match('/^bab\.(\d+)\.sub\.(\d+)$/', $k, $m)) return "bab.{$m[1]}.sub.{$m[2]}.isi";
        if (in_array($k, ['isi', 'kepustakaan'], true)) return $k;
        if (preg_match('/^blok\.(\d+)$/', $k, $m) && $this->jenisBagian($k) === 'html') return "blok.{$m[1]}.html";

        return null;
    }

    private function muatBagian(): void
    {
        $k = $this->pilih;
        if ($this->jenisBagian($k) === 'daftar') {
            $this->daftarTeks = implode("\n", $this->n[$k] ?? []);
        } elseif ($j = $this->jalurHtml($k)) {
            $this->html = (string) data_get($this->n, $j, '');
            $this->dispatch('naskah.reload');
        }
    }

    private function simpanBagian(): void
    {
        $k = $this->pilih;
        if ($this->jenisBagian($k) === 'daftar') {
            $this->n[$k] = array_values(array_filter(array_map('trim', preg_split('/\R/', $this->daftarTeks))));
        } elseif ($j = $this->jalurHtml($k)) {
            if (data_get($this->n, preg_replace('/\.isi$/', '', $j)) !== null || in_array($j, ['isi', 'kepustakaan'], true)) {
                data_set($this->n, $j, $this->html);
            }
        }
    }

    public function pilihBagian(string $k): void
    {
        $this->simpanBagian();
        $this->pilih = $k;
        $this->muatBagian();
    }

    // ── tambah / hapus / geser ──────────────────────────────────────────────
    public function tambah(string $daftar, ?int $bab = null): void
    {
        $this->simpanBagian();
        if ($daftar === 'blok') {
            $this->n['blok'][] = ['tipe' => 'html', 'html' => ''];
            $this->pilih = 'blok.' . (count($this->n['blok']) - 1);
        } elseif ($daftar === 'sub' && $bab !== null) {
            $this->n['bab'][$bab]['sub'][] = ['judul' => 'Subjudul baru', 'isi' => ''];
            $this->pilih = "bab.{$bab}.sub." . (count($this->n['bab'][$bab]['sub']) - 1);
        } elseif ($daftar === 'bab') {
            $this->n['bab'][] = ['judul' => 'JUDUL BAB', 'isi' => '', 'sub' => []];
            $this->pilih = 'bab.' . (count($this->n['bab']) - 1);
        } else {
            $this->n[$daftar][] = ['isi' => ''];
            $this->pilih = $daftar . '.' . (count($this->n[$daftar]) - 1);
        }
        $this->muatBagian();
    }

    public function hapus(): void
    {
        $k = $this->pilih;
        if (preg_match('/^(pasal|diktum|bab|blok)\.(\d+)$/', $k, $m)) {
            array_splice($this->n[$m[1]], (int) $m[2], 1);
        } elseif (preg_match('/^bab\.(\d+)\.sub\.(\d+)$/', $k, $m)) {
            array_splice($this->n['bab'][(int) $m[1]]['sub'], (int) $m[2], 1);
        } else {
            return;
        }
        $this->pilih = 'kepala';
        $this->muatBagian();
    }

    public function geser(int $arah): void
    {
        $this->simpanBagian();
        $k = $this->pilih;
        if (preg_match('/^(pasal|diktum|bab|blok)\.(\d+)$/', $k, $m)) {
            $arr = &$this->n[$m[1]];
            $i = (int) $m[2];
            $j = $i + $arah;
            if ($j < 0 || $j >= count($arr)) return;
            [$arr[$i], $arr[$j]] = [$arr[$j], $arr[$i]];
            $this->pilih = "{$m[1]}.{$j}";
        } elseif (preg_match('/^bab\.(\d+)\.sub\.(\d+)$/', $k, $m)) {
            $arr = &$this->n['bab'][(int) $m[1]]['sub'];
            $i = (int) $m[2];
            $j = $i + $arah;
            if ($j < 0 || $j >= count($arr)) return;
            [$arr[$i], $arr[$j]] = [$arr[$j], $arr[$i]];
            $this->pilih = "bab.{$m[1]}.sub.{$j}";
        }
        $this->muatBagian();
    }

    /** Unggah gambar sebagai blok baru (format umum) — disimpan di disk local, dirujuk "unggah:<path>". */
    public function unggahGambar(): void
    {
        $this->validate(['gambarBaru' => 'required|image|max:5120']);
        $this->simpanBagian();
        $path = $this->gambarBaru->storeAs(config('dokumen.folder_unggah') . '/gambar/' . $this->dokId,
            now()->format('YmdHis') . '-' . Str::random(4) . '.' . $this->gambarBaru->getClientOriginalExtension(), 'local');
        [$w, $h] = @getimagesize(\Illuminate\Support\Facades\Storage::disk('local')->path($path)) ?: [0, 0];
        $this->n['blok'][] = ['tipe' => 'gambar', 'src' => 'unggah:' . $path, 'lebar' => $w, 'tinggi' => $h, 'keterangan' => ''];
        $this->gambarBaru = null;
        $this->pilih = 'blok.' . (count($this->n['blok']) - 1);
        $this->muatBagian();
    }

    /** URL pratinjau gambar blok di layar (lewat route berkas dokumen). */
    public function urlGambar(?string $src): ?string
    {
        return $src ? route('dokumen.gambar', ['dokId' => $this->dokId, 'src' => base64_encode($src)]) : null;
    }

    /** Ganti kerangka BAB dengan sistematika baku Pedoman (isi lama dibuang). */
    public function terapkanSistematika(): void
    {
        $this->n['bab'] = TataNaskah::kerangkaBab($this->n['sistematika'] ?? 'pedoman_pelayanan');
        $this->pilih = 'kepala';
        $this->dispatch('toast', type: 'info', message: 'Kerangka BAB diganti sesuai sistematika baku.');
    }

    public function simpan(bool $diam = false): void
    {
        abort_unless(DokumenService::bolehUbah(), 403);
        try {
            DokumenService::pastikanTidakTerkunci((int) $this->dokId);
        } catch (\DomainException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
            return;
        }
        $this->simpanBagian();
        $naskah = $this->n;
        DokumenService::catat($this->dokId, 'SUSUN', 'isi naskah diperbarui', function ($json) use ($naskah) {
            $json['isi']['naskah'] = $naskah;
            if (isset($json['isiSumber'])) {
                $json['isiSumber']['perluDicek'] = false;
                $json['isiSumber']['diperiksa'] = now()->format('Y-m-d H:i:s');
            }
            return $json;
        });
        $this->perluDicek = false;
        if (! $diam) $this->dispatch('toast', type: 'success', message: 'Naskah tersimpan.');
        $this->dispatch('dokumen.registri.saved');
    }

    public function simpanLaluCetak(string $format): void
    {
        $this->simpan(true);
        $this->dispatch('dokumen-naskah.buka-url', url: $format === 'docx' ? route('dokumen.cetak.docx', $this->dokId) : route('dokumen.cetak.pdf', $this->dokId));
    }

    public function tutup(): void
    {
        $this->dispatch('close-modal', name: 'dokumen-registri-naskah');
    }

    /** Daftar bagian untuk panel kiri: [kunci, label, tingkat]. */
    public function getBagianProperty(): array
    {
        $f = $this->n['format'] ?? '';
        $b = [['kepala', 'Kepala', 0]];
        if (in_array($f, ['peraturan', 'keputusan'], true)) {
            $b[] = ['menimbang', 'Menimbang (' . count($this->n['menimbang'] ?? []) . ')', 0];
            $b[] = ['mengingat', 'Mengingat (' . count($this->n['mengingat'] ?? []) . ')', 0];
            $b[] = ['menetapkan', 'MEMUTUSKAN · Menetapkan', 0];
            $badan = ! empty($this->n['pasal']) || $f === 'peraturan' ? 'pasal' : 'diktum';
            foreach ($this->n[$badan] ?? [] as $i => $x) {
                $b[] = ["{$badan}.{$i}", $badan === 'pasal' ? 'Pasal ' . ($i + 1) : (TataNaskah::DIKTUM[$i] ?? 'KE-' . ($i + 1)), 1];
            }
            $b[] = ["+{$badan}", '+ ' . ($badan === 'pasal' ? 'Pasal' : 'Diktum'), 1];
        } elseif ($f === 'bab') {
            $romawi = (TataNaskah::SISTEMATIKA[$this->n['sistematika'] ?? ''][1] ?? 'BAB') === 'romawi';
            foreach ($this->n['bab'] ?? [] as $i => $x) {
                $b[] = ["bab.{$i}", ($romawi ? TataNaskah::romawi($i + 1) . '. ' : 'BAB ' . TataNaskah::romawi($i + 1) . ' · ') . ($x['judul'] ?? ''), 0];
                foreach ($x['sub'] ?? [] as $j => $s) {
                    $b[] = ["bab.{$i}.sub.{$j}", TataNaskah::huruf($j, true) . '. ' . ($s['judul'] ?? ''), 1];
                }
            }
            $b[] = ['+bab', '+ BAB', 0];
            $b[] = ['kepustakaan', 'Kepustakaan', 0];
        } elseif ($f === 'umum') {
            foreach ($this->n['blok'] ?? [] as $i => $x) {
                $label = ($x['tipe'] ?? '') === 'gambar'
                    ? '🖼 ' . (($x['keterangan'] ?? '') ?: 'gambar ' . ($i + 1))
                    : '¶ ' . Str::limit(trim(html_entity_decode(strip_tags($x['html'] ?? ''))) ?: '(kosong)', 38);
                $b[] = ["blok.{$i}", $label, 0];
            }
            $b[] = ['+blok', '+ Blok teks', 0];
        } elseif ($f === 'edaran') {
            $b[] = ['yth', 'Yth.', 0];
            $b[] = ['isi', 'Isi edaran', 0];
        }
        $b[] = ['kaki', $f === 'umum' ? 'Penandatangan (opsional)' : 'Kaki (penetapan)', 0];
        if (! in_array($f, ['bab', 'umum'], true)) $b[] = ['tembusan', 'Tembusan', 0];

        return $b;
    }
};
?>

<div x-data x-on:dokumen-naskah.buka-url.window="window.open($event.detail.url, '_blank')">
    <x-modal name="dokumen-registri-naskah" size="full" height="full">
        @php
            $f = $n['format'] ?? '';
            $jenisBagian = preg_match('/^blok\.(\d+)$/', $pilih, $mb)
                ? ((($n['blok'][(int) $mb[1]]['tipe'] ?? 'html') === 'gambar') ? 'gambar' : 'html')
                : (preg_match('/^(pasal|diktum)\.\d+$|^bab\.\d+(\.sub\.\d+)?$|^(isi|kepustakaan)$/', $pilih) ? 'html'
                : (in_array($pilih, ['menimbang', 'mengingat', 'tembusan', 'yth'], true) ? 'daftar' : 'isian'));
            $flush = "window.dispatchEvent(new Event('naskah.flush'));";
        @endphp
        <div class="flex flex-col h-full">
            <div class="px-6 py-4 border-b bg-surface-soft border-hairline dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex flex-wrap gap-2">
                            <x-badge variant="brand">Penyusun naskah · {{ ['peraturan' => 'Peraturan', 'keputusan' => 'Keputusan', 'bab' => 'Pedoman/Panduan/Program', 'edaran' => 'Surat Edaran', 'umum' => 'Dokumen (blok teks & gambar)'][$f] ?? $f }}</x-badge>
                            @if ($perluDicek)
                                <x-badge variant="warning">Hasil konversi otomatis — periksa lalu simpan</x-badge>
                            @endif
                        </div>
                        <p class="mt-2 font-mono text-sm text-muted">{{ $nomor ?: 'belum bernomor' }}</p>
                    </div>
                    <x-icon-button color="gray" type="button" wire:click="tutup">✕</x-icon-button>
                </div>
            </div>

            <div class="flex flex-1 min-h-0">
                {{-- PANEL KIRI: bagian baku --}}
                <nav class="w-72 p-3 space-y-0.5 overflow-y-auto border-r shrink-0 border-hairline dark:border-gray-700">
                    @foreach ($this->bagian as [$k, $label, $tk])
                        @if (str_starts_with($k, '+'))
                            <button type="button" x-on:click="{{ $flush }} $nextTick(() => $wire.tambah('{{ substr($k, 1) }}'))"
                                class="block w-full px-2 py-1 text-xs text-left rounded text-brand-green hover:bg-surface-soft" style="padding-left: {{ 0.5 + $tk }}rem">{{ $label }}</button>
                        @else
                            <button type="button" x-on:click="{{ $flush }} $nextTick(() => $wire.pilihBagian('{{ $k }}'))"
                                class="block w-full px-2 py-1.5 text-sm text-left rounded truncate {{ $pilih === $k ? 'bg-brand-green text-white' : 'hover:bg-surface-soft dark:hover:bg-gray-800' }}"
                                style="padding-left: {{ 0.5 + $tk * 1.2 }}rem" title="{{ $label }}">{{ $label }}</button>
                        @endif
                    @endforeach
                </nav>

                {{-- PANEL KANAN: penyunting bagian --}}
                <div class="flex-1 px-6 py-5 space-y-4 overflow-y-auto">
                    @if ($pilih === 'kepala')
                        @if (in_array($f, ['peraturan', 'keputusan', 'edaran'], true))
                            <div><x-input-label value="Kepala naskah" /><x-text-input wire:model="n.judulJenis" class="w-full mt-1 uppercase" /></div>
                            <div><x-input-label value="Tentang (judul, huruf kapital)" /><x-textarea wire:model="n.tentang" rows="2" class="w-full mt-1 uppercase" /></div>
                        @endif
                        @if (in_array($f, ['peraturan', 'keputusan'], true))
                            <div><x-input-label value="Jabatan pembentuk (diakhiri koma)" /><x-text-input wire:model="n.pembuat" class="w-full mt-1 uppercase" /></div>
                        @endif
                        @if ($f === 'umum')
                            <p class="text-sm text-muted">Dokumen tersusun dari blok berurutan (teks dan gambar) sesuai halaman sumbernya.
                                Pilih blok di kiri untuk menyunting; gambar (foto kegiatan, pindaian bertanda tangan basah) tetap gambar.</p>
                            <div class="flex items-end gap-2">
                                <div><x-input-label value="Tambah blok gambar (PNG/JPG, maks. 5 MB)" /><input type="file" wire:model="gambarBaru" accept="image/*" class="mt-1 text-sm" /></div>
                                <x-secondary-button type="button" wire:click="unggahGambar" wire:loading.attr="disabled">Tambahkan</x-secondary-button>
                            </div>
                            <x-input-error :messages="$errors->get('gambarBaru')" class="mt-1" />
                        @endif
                        @if ($f === 'bab')
                            <div><x-input-label value="Judul pedoman/panduan/program" /><x-textarea wire:model="n.judul" rows="2" class="w-full mt-1 uppercase" /></div>
                            <div class="flex items-end gap-2">
                                <div class="flex-1">
                                    <x-input-label value="Sistematika baku (Pedoman Tata Naskah hal. 15–22)" />
                                    <x-select-input wire:model.live="n.sistematika" class="w-full mt-1">
                                        @foreach (TataNaskah::SISTEMATIKA as $kode => [$nama])
                                            <option value="{{ $kode }}">{{ $nama }}</option>
                                        @endforeach
                                    </x-select-input>
                                </div>
                                <x-confirm-button variant="warning-soft" action="terapkanSistematika" title="Terapkan kerangka baku"
                                    message="Seluruh BAB diganti kerangka baku dan isinya dikosongkan. Lanjutkan?">Terapkan kerangka</x-confirm-button>
                            </div>
                            <p class="text-xs text-muted">Lampiran (nama, nomor, tentang Peraturan/Keputusan) diambil otomatis dari dokumen induk saat dicetak.</p>
                        @endif
                    @elseif ($pilih === 'menetapkan')
                        <x-input-label value="Menetapkan (kosongkan → otomatis: KEPALA NASKAH TENTANG JUDUL.)" />
                        <x-textarea wire:model="n.menetapkan" rows="3" class="w-full mt-1 uppercase" :placeholder="TataNaskah::menetapkan(array_merge($n, ['menetapkan' => '']))" />
                    @elseif ($pilih === 'kaki')
                        @if (isset($n['kaki']['mengetahui']))
                            <div class="grid grid-cols-2 gap-4">
                                <div><x-input-label value="Tempat" /><x-text-input wire:model="n.kaki.tempat" class="w-full mt-1" /></div>
                                <div><x-input-label value="Tanggal" /><x-text-input type="date" wire:model="n.kaki.tanggal" class="w-full mt-1" /></div>
                                <div><x-input-label value="Mengetahui — jabatan" /><x-text-input wire:model="n.kaki.mengetahui.jabatan" class="w-full mt-1" /></div>
                                <div><x-input-label value="Mengetahui — nama" /><x-text-input wire:model="n.kaki.mengetahui.nama" class="w-full mt-1" /></div>
                                <div><x-input-label value="Penyusun — jabatan" /><x-text-input wire:model="n.kaki.penyusun.jabatan" class="w-full mt-1" /></div>
                                <div><x-input-label value="Penyusun — nama" /><x-text-input wire:model="n.kaki.penyusun.nama" class="w-full mt-1" /></div>
                            </div>
                        @else
                            <div class="grid grid-cols-2 gap-4">
                                <div><x-input-label value="Ditetapkan di" /><x-text-input wire:model="n.kaki.tempat" class="w-full mt-1" /></div>
                                <div><x-input-label value="Pada tanggal (kosong → tanggal terbit)" /><x-text-input type="date" wire:model="n.kaki.tanggal" class="w-full mt-1" /></div>
                                <div><x-input-label value="Jabatan" /><x-text-input wire:model="n.kaki.jabatan" class="w-full mt-1 uppercase" /></div>
                                <div><x-input-label value="Nama lengkap & gelar" /><x-text-input wire:model="n.kaki.nama" class="w-full mt-1" /></div>
                                <div><x-input-label value="NIK (bila dicantumkan)" /><x-text-input wire:model="n.kaki.nik" class="w-full mt-1" /></div>
                            </div>
                        @endif
                    @elseif ($jenisBagian === 'daftar')
                        <x-input-label :value="['menimbang' => 'Menimbang — satu butir per baris (a., b., … otomatis)', 'mengingat' => 'Mengingat — satu dasar hukum per baris (1., 2., … otomatis), urut dari yang tertinggi', 'tembusan' => 'Tembusan — satu per baris', 'yth' => 'Yth. — satu penerima per baris'][$pilih]" />
                        <x-textarea wire:model="daftarTeks" rows="14" class="w-full mt-1 font-mono text-sm" />
                    @elseif ($jenisBagian === 'gambar')
                        @php $ib = (int) $mb[1]; @endphp
                        <div><x-input-label value="Keterangan gambar" /><x-text-input wire:model="n.blok.{{ $ib }}.keterangan" class="w-full mt-1" /></div>
                        <img src="{{ $this->urlGambar($n['blok'][$ib]['src'] ?? null) }}" class="max-w-full border rounded-xl border-hairline max-h-[60vh]" alt="gambar blok">
                        <div class="flex flex-wrap gap-2">
                            <x-secondary-button type="button" wire:click="geser(-1)">↑ Naik</x-secondary-button>
                            <x-secondary-button type="button" wire:click="geser(1)">↓ Turun</x-secondary-button>
                            <x-confirm-button variant="danger-soft" action="hapus" title="Hapus blok" message="Hapus gambar ini dari dokumen?">Hapus blok</x-confirm-button>
                        </div>
                    @elseif ($jenisBagian === 'html')
                        @if (preg_match('/^bab\.(\d+)$/', $pilih, $m))
                            <div><x-input-label value="Judul BAB" /><x-text-input wire:model="n.bab.{{ $m[1] }}.judul" class="w-full mt-1 uppercase" /></div>
                        @elseif (preg_match('/^bab\.(\d+)\.sub\.(\d+)$/', $pilih, $m))
                            <div><x-input-label value="Subjudul" /><x-text-input wire:model="n.bab.{{ $m[1] }}.sub.{{ $m[2] }}.judul" class="w-full mt-1" /></div>
                        @endif
                        <div>
                            <x-input-label value="Isi" />
                            {{-- tanpa modal-event: editor baru dirender saat bagian ber-HTML dipilih (sesudah modal terbuka),
                                 jadi harus menyala sendiri saat elemennya muncul, bukan menunggu event open-modal --}}
                            <x-tinymce-editor name="html" flush-event="naskah.flush" reload-event="naskah.reload" :height="460" class="mt-1" wire:key="editor-naskah" />
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <x-secondary-button type="button" x-on:click="{{ $flush }} $nextTick(() => $wire.geser(-1))">↑ Naik</x-secondary-button>
                            <x-secondary-button type="button" x-on:click="{{ $flush }} $nextTick(() => $wire.geser(1))">↓ Turun</x-secondary-button>
                            @if (preg_match('/^bab\.(\d+)/', $pilih, $m))
                                <x-secondary-button type="button" x-on:click="{{ $flush }} $nextTick(() => $wire.tambah('sub', {{ $m[1] }}))">+ Subjudul</x-secondary-button>
                            @endif
                            @if (! in_array($pilih, ['isi', 'kepustakaan'], true))
                                <x-confirm-button variant="danger-soft" action="hapus" title="Hapus bagian" message="Hapus bagian ini dari naskah?">Hapus bagian</x-confirm-button>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <div class="sticky bottom-0 z-10 px-6 py-4 border-t bg-surface-soft border-hairline shrink-0 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex justify-end gap-2">
                    <x-secondary-button type="button" wire:click="tutup">Tutup</x-secondary-button>
                    @if (DokumenService::bolehUbah())
                        <x-secondary-button type="button" x-on:click="{{ $flush }} $nextTick(() => $wire.simpanLaluCetak('docx'))">Simpan &amp; DOCX</x-secondary-button>
                        <x-cetak-button type="button" label="Simpan & PDF" x-on:click="{{ $flush }} $nextTick(() => $wire.simpanLaluCetak('pdf'))" />
                        <x-primary-button type="button" x-on:click="{{ $flush }} $nextTick(() => $wire.simpan())">Simpan</x-primary-button>
                    @endif
                </div>
            </div>
        </div>
    </x-modal>
</div>
