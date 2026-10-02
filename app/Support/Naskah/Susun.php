<?php

namespace App\Support\Naskah;

use App\Services\DokumenService;
use App\Support\TataNaskah;
use Carbon\Carbon;

/**
 * Naskah versi 2 (JSON teks) → deret UNSUR tata letak yang sama untuk PDF dan DOCX.
 *
 * Unsur:
 *   ['teks',     peran, teks]                         satu paragraf bergaya peran
 *   ['jarak',    baris]                               baris kosong
 *   ['lajur',    [baris…]]                            tabel tanpa garis: label · ":" · nomor · isi
 *                baris = [label, labelPeran, titik, nomor, isi (string|simpul[]), isiPeran, konteks]
 *   ['isi',      simpul[], konteks, tingkatAwal]      batang tubuh (paragraf, butir bertingkat, tabel)
 *   ['identitas', [[kunci, nilai]…], kanan]           Nomor/Sifat/Lampiran/Hal + tanggal sebaris kanan
 *   ['rincian',  [[kunci, nilai]…]]                   rincian undangan (menjorok)
 *   ['kaki',     [baris…], ttd?]                      blok penanda tangan di kanan
 *                baris = [peran, teks] | ['ttd'] (ruang/gambar tanda tangan)
 *   ['tembusan', [teks…]]
 *   ['lampiran', [[kunci, nilai]…]]                   kepala lampiran kanan atas (hal. 13)
 *   ['halaman_baru']
 *
 * Simpul hasil normalisasi: ['p', teks] · ['daftar', urut, [[teks, simpul[]]…]] · ['tabel', kepala[], baris[][]]
 */
class Susun
{
    private const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', "Jum'at", 'Sabtu'];

    public static function unsur(object $dok, array $n, ?array $ttd = null): array
    {
        return match (Kerangka::keluarga($n['jenis'] ?? $dok->jenis_kode)) {
            'arahan' => self::arahan($dok, $n, $ttd),
            'undangan' => self::undangan($dok, $n, $ttd),
            default => throw new \InvalidArgumentException('Keluarga naskah tidak dikenal.'),
        };
    }

    // ─── Peraturan / Keputusan / Kebijakan (hal. 11–13, 29–31) ───────────────────────────────

    private static function arahan(object $dok, array $n, ?array $ttd): array
    {
        $jenis = $n['jenis'] ?? $dok->jenis_kode;
        $judulJenis = $n['judulJenis'] ?? Kerangka::JUDUL_JENIS[$jenis] ?? '';
        $u = [
            ['teks', 'judul_jenis', $judulJenis],
            ['teks', 'nomor', 'Nomor: ' . ($dok->dok_no ?: '…')],
            ['teks', 'tentang', 'Tentang'],
            ['teks', 'judul', self::tanpaTitik($n['judul'] ?? '')],
            ['jarak', max(0, Gaya::c('jarak_judul_isi', 2) - 1)],
            ['teks', 'pembuat', rtrim(self::tanpaTitik($n['pembuat'] ?? ''), ',') . ','],
        ];

        $baris = [];
        foreach (self::akhiri(self::isian($n['menimbang'] ?? []), ';', ';') as $i => $t) {
            $baris[] = [$i ? '' : 'Menimbang', 'label', $i ? '' : ':', TataNaskah::huruf($i) . '.', $t, 'konsiderans', null];
        }
        if ($baris && ! empty($n['mengingat'])) {
            $baris[] = ['', 'label', '', '', '', 'konsiderans', null];   // jeda antar-konsiderans
        }
        foreach (self::akhiri(self::isian($n['mengingat'] ?? []), ';', ';') as $i => $t) {
            $baris[] = [$i ? '' : 'Mengingat', 'label', $i ? '' : ':', ($i + 1) . '.', $t, 'konsiderans', null];
        }
        if ($baris) {
            $u[] = ['lajur', $baris];
        }

        $u[] = ['teks', 'memutuskan', 'Memutuskan:'];
        $menetapkan = filled($n['menetapkan'] ?? '') ? $n['menetapkan']
            : $judulJenis . ' tentang ' . ($n['judul'] ?? '');
        $u[] = ['lajur', [['Menetapkan', 'label', ':', null, self::tanpaTitik($menetapkan) . '.', 'menetapkan', null]]];

        if (! empty($n['pasal'])) {
            foreach ($n['pasal'] as $i => $p) {
                $u[] = ['teks', 'pasal', 'Pasal ' . ($i + 1)];
                $u[] = ['isi', self::simpul($p['isi'] ?? [], true), 'pasal', Gaya::mulai('pasal')];
            }
        } else {
            $baris = [];
            foreach ($n['diktum'] ?? [] as $i => $d) {
                $baris[] = [TataNaskah::DIKTUM[$i] ?? 'KE-' . ($i + 1), 'diktum', ':', null,
                    self::simpul($d['isi'] ?? [], true), 'isi', 'diktum'];
            }
            if ($baris) {
                $u[] = ['jarak', 0.5];
                $u[] = ['lajur', $baris];
            }
        }

        $u[] = self::kakiArahan($n['kaki'] ?? [], $ttd);
        if (! empty($n['tembusan'])) {
            $u[] = ['tembusan', array_values($n['tembusan'])];
        }

        foreach ($n['lampiran'] ?? [] as $i => $l) {
            $u[] = ['halaman_baru'];
            $u[] = ['lampiran', [
                [count($n['lampiran']) > 1 ? 'Lampiran ' . ($i + 1) : 'Lampiran', mb_strtoupper($judulJenis)],
                ['Nomor', $dok->dok_no ?: '…'],
                ['Tentang', mb_strtoupper(self::tanpaTitik($n['judul'] ?? ''))],
            ]];
            if (filled($l['judul'] ?? '')) {
                $u[] = ['teks', 'judul', $l['judul']];
                $u[] = ['jarak', max(0, Gaya::c('jarak_judul_isi', 2) - 1)];
            }
            $u[] = ['isi', self::simpul($l['isi'] ?? []), 'bab', Gaya::mulai('bab')];
            $u[] = self::kakiArahan($n['kaki'] ?? [], $ttd);   // hal. 13 7(b): halaman terakhir ditandatangani
        }

        return $u;
    }

    private static function kakiArahan(array $k, ?array $ttd): array
    {
        $baris = [
            ['kaki', 'Ditetapkan di ' . (($k['tempat'] ?? '') ?: 'Tulungagung')],
            ['kaki', 'pada tanggal ' . (DokumenService::tanggalIndonesia($k['tanggal'] ?? null) ?: '…')],
            ['jarak', 1],
            ['kaki_jabatan', self::jabatan($k, $ttd) . ','],
            ['ttd'],
            ['kaki_nama', $ttd['nama'] ?? ($k['nama'] ?? '')],
        ];
        if (filled($k['nik'] ?? '')) {
            $baris[] = ['kaki', 'NIK. ' . $k['nik']];
        }

        return ['kaki', $baris, $ttd];
    }

    // ─── Surat Undangan (hal. 40–41) ─────────────────────────────────────────────────────────

    private static function undangan(object $dok, array $n, ?array $ttd): array
    {
        $tgl = DokumenService::tanggalIndonesia(($n['tanggal'] ?? '') ?: $dok->tgl_terbit) ?: '…';
        $kanan = (filled($n['tempat'] ?? '') ? $n['tempat'] : 'Tulungagung') . ', ' . $tgl;

        $id = [['Nomor', $dok->dok_no ?: '…']];
        if (filled($n['sifat'] ?? '') && mb_strtolower(trim($n['sifat'])) !== 'biasa') {
            $id[] = ['Sifat', $n['sifat']];                      // hal. 39 e(2): sifat biasa tidak dicantumkan
        }
        if (filled($n['lampiran'] ?? '') && ! is_array($n['lampiran'])) {
            $id[] = ['Lampiran', $n['lampiran']];                // hal. 39 e(4)
        }
        $id[] = ['Hal', self::tanpaTitik($n['hal'] ?? '')];       // hal. 39 e(5): tanpa tanda baca akhir

        $u = [['identitas', $id, $kanan]];

        $yth = array_values(array_filter(array_map('trim', (array) ($n['yth'] ?? [])), 'strlen'));
        if (count($yth) <= 1) {
            $u[] = ['teks', 'tujuan', 'Yth. ' . ($yth[0] ?? '…')];
        } else {
            $u[] = ['teks', 'tujuan', 'Yth.'];
            $u[] = ['isi', [['daftar', true, array_map(fn ($y) => [$y, []], $yth)]], 'surat', 2];
        }
        if (filled($n['alamat'] ?? '')) {
            $u[] = ['teks', 'kaki', $n['alamat']];
        }
        $u[] = ['jarak', 1];

        $u[] = ['isi', self::simpul($n['pembuka'] ?? []), 'surat', Gaya::mulai('surat')];

        $a = $n['acara'] ?? [];
        $rinci = [];
        if (filled($a['tanggal'] ?? '')) {
            $c = Carbon::parse($a['tanggal']);
            $rinci[] = ['Hari', self::HARI[$c->dayOfWeek]];
            $rinci[] = ['Tanggal', DokumenService::tanggalIndonesia($c)];
        } else {
            $rinci[] = ['Hari', '…'];
            $rinci[] = ['Tanggal', '…'];
        }
        foreach (['waktu' => 'Waktu', 'tempat' => 'Tempat', 'acara' => 'Acara'] as $k => $label) {
            $rinci[] = [$label, ($a[$k] ?? '') ?: '…'];
        }
        foreach ($a['lain'] ?? [] as $l) {
            if (filled($l[0] ?? '')) {
                $rinci[] = [$l[0], $l[1] ?? ''];
            }
        }
        $u[] = ['rincian', $rinci];
        $u[] = ['isi', self::simpul($n['penutup'] ?? []), 'surat', Gaya::mulai('surat')];

        $k = $n['kaki'] ?? [];
        $u[] = ['kaki', [
            ['kaki_jabatan', self::jabatan($k, $ttd) . ','],
            ['ttd'],
            ['kaki_nama', $ttd['nama'] ?? ($k['nama'] ?? '')],
        ], $ttd];
        if (! empty($n['tembusan'])) {
            $u[] = ['tembusan', array_values($n['tembusan'])];
        }

        return $u;
    }

    // ─── Pembantu ────────────────────────────────────────────────────────────────────────────

    /** "Direktur" + "RSUI MADINAH": jabatan huruf awal kapital, instansi kapital (hal. 30 5(b), 41 c). */
    private static function jabatan(array $k, ?array $ttd): string
    {
        $jabatan = trim(($k['jabatan'] ?? '') ?: ($ttd['jabatan'] ?? ''));
        $instansi = mb_strtoupper(trim($k['instansi'] ?? ''));
        if ($instansi !== '' && ! str_contains(mb_strtoupper($jabatan), $instansi)) {
            $jabatan .= ' ' . $instansi;
        }

        return rtrim($jabatan, ', ');
    }

    private static function isian(array $butir): array
    {
        return array_values(array_filter($butir, fn ($t) => trim((string) $t) !== ''));
    }

    private static function tanpaTitik(string $t): string
    {
        return rtrim(trim($t), " .;:,");
    }

    /**
     * Tanda baca akhir butir (hal. 11–12): butir dipisah $antara, butir terakhir $akhir.
     * Butir yang berakhir ":" (pengantar rincian) atau "; dan"/"; atau" dibiarkan.
     */
    public static function akhiri(array $butir, string $antara, string $akhir, array $lewati = []): array
    {
        $butir = array_map(fn ($t) => trim((string) $t), $butir);
        $n = count($butir);

        return array_map(function ($t, $i) use ($n, $antara, $akhir, $lewati) {
            if ($t === '' || ! empty($lewati[$i]) || str_ends_with($t, ':') || preg_match('/[;,]\s*(dan|atau)$/u', $t)) {
                return $t;
            }

            return rtrim($t, ' .;,') . ($i === $n - 1 ? $akhir : $antara);
        }, $butir, array_keys($butir));
    }

    /**
     * Normalisasi simpul isi. $tandaButir: terapkan ";" / "." pada butir (isi pasal & diktum, hal. 12 3.b).
     */
    public static function simpul(mixed $isi, bool $tandaButir = false): array
    {
        if (is_string($isi)) {
            $isi = [$isi];
        }
        $hasil = [];
        foreach ((array) $isi as $s) {
            if (is_string($s)) {
                if (trim($s) !== '') {
                    $hasil[] = ['p', trim($s)];
                }
            } elseif (isset($s['daftar'])) {
                $teks = array_map(fn ($b) => is_string($b) ? $b : ($b['teks'] ?? ''), $s['daftar']);
                $anak = array_map(fn ($b) => is_string($b) ? [] : ($b['anak'] ?? []), $s['daftar']);
                if ($tandaButir && ($s['urut'] ?? true)) {
                    // butir beranak (pengantar rincian) tidak ikut diberi ";" — tandanya ":" di teks
                    $teks = self::akhiri($teks, ';', '.', array_map(fn ($a) => ! empty($a), $anak));
                }
                $hasil[] = ['daftar', (bool) ($s['urut'] ?? true), array_map(
                    fn ($t, $a) => [trim($t), self::simpul($a, $tandaButir)], $teks, $anak)];
            } elseif (isset($s['tabel'])) {
                $hasil[] = ['tabel', $s['tabel']['kepala'] ?? [], $s['tabel']['baris'] ?? []];
            } elseif (isset($s['p'])) {
                $hasil[] = ['p', trim((string) $s['p'])];
            }
        }

        return $hasil;
    }
}
