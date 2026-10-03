<?php

namespace App\Support;

use App\Services\DokumenService;

/**
 * Bentuk baku naskah dinas sebagai JSON — Pedoman Tata Naskah Dinas RSUI Madinah BAB III.
 *
 * Isi naskah disimpan di DOK_DOKUMEN.DOK_JSON.isi.naskah (SPO tetap di isi.spo). Satu format per
 * keluarga naskah; PDF/DOCX dibangkitkan dari JSON ini (DokumenCetakController), tidak dari berkas.
 *
 *  peraturan  Peraturan Direktur (hal. 11–13): kepala · pembukaan · Menimbang (a.) · Mengingat (1.)
 *             · MEMUTUSKAN/Menetapkan · Pasal · kaki · tembusan
 *  keputusan  Keputusan/Kebijakan Direktur (hal. 29–31): sama, batang tubuh berupa diktum KESATU, KEDUA…
 *  bab        Pedoman / Panduan / Program Kerja (hal. 15–22): lampiran Perdir, BAB → sub A., B., …
 *  edaran     Surat Edaran (hal. 27–29): kepala · Yth · isi · kaki · tembusan
 *  undangan   Surat Undangan (hal. 40–41): nomor · sifat · lampiran · hal · Yth · pembuka · acara (hari, tanggal,
 *             waktu, tempat, acara) · penutup · kaki · tembusan
 *  spo        SPO (hal. 24–26) — isi.spo, penyusun tersendiri
 */
class TataNaskah
{
    public const FORMAT_JENIS = [
        'PERDIR' => 'peraturan',
        'SK' => 'keputusan',
        'KEB' => 'keputusan',
        'PED' => 'bab',
        'PAN' => 'bab',
        'PROG' => 'bab',
        'SE' => 'edaran',
        'UND' => 'undangan',
        'SPO' => 'spo',
    ];
    // Jenis lain (laporan, notulen, daftar hadir, sertifikat, surat, formulir, bukti, eksternal) → 'umum':
    // deretan blok html/gambar sesuai urutan halaman sumber (hasil konversi PDF / OCR / unggahan).

    public const JUDUL_JENIS = [
        'PERDIR' => 'PERATURAN DIREKTUR RSUI MADINAH',
        'SK' => 'KEPUTUSAN DIREKTUR RSUI MADINAH',
        'KEB' => 'KEBIJAKAN DIREKTUR RSUI MADINAH',
        'SE' => 'SURAT EDARAN',
    ];

    public const DIKTUM = ['KESATU', 'KEDUA', 'KETIGA', 'KEEMPAT', 'KELIMA', 'KEENAM', 'KETUJUH', 'KEDELAPAN',
        'KESEMBILAN', 'KESEPULUH', 'KESEBELAS', 'KEDUA BELAS', 'KETIGA BELAS', 'KEEMPAT BELAS', 'KELIMA BELAS'];

    /**
     * Sistematika baku Pedoman/Panduan/Program Kerja (Pedoman Tata Naskah hal. 15–22).
     * [kode => [nama, penomoran (BAB|romawi), [[judul bab, [sub…]], …], kepustakaan?]]
     */
    public const SISTEMATIKA = [
        'pedoman_pengorganisasian' => ['Pedoman Pengorganisasian Unit', 'BAB', [
            ['PENDAHULUAN', []], ['GAMBARAN UMUM RUMAH SAKIT', []], ['VISI, MISI, NILAI BUDAYA, MOTO RSUI MADINAH', []],
            ['STRUKTUR ORGANISASI RSUI MADINAH', []], ['STRUKTUR ORGANISASI UNIT KERJA', []], ['URAIAN JABATAN', []],
            ['TATA HUBUNGAN KERJA', []], ['POLA KETENAGAAN DAN KUALIFIKASI PERSONIL', []],
            ['PROGRAM ORIENTASI', ['Orientasi Rumah Sakit', 'Orientasi Unit (Orientasi Khusus)']],
            ['PERTEMUAN/RAPAT', []], ['PELAPORAN', ['Laporan Harian', 'Laporan Bulanan', 'Laporan Tahunan']],
        ], false],
        'pedoman_pelayanan' => ['Pedoman Pelayanan Unit', 'BAB', [
            ['PENDAHULUAN', ['Latar Belakang', 'Ruang Lingkup Pelayanan', 'Batasan Operasional']],
            ['STANDAR KETENAGAAN', ['Kualifikasi Sumber Daya Manusia', 'Distribusi Ketenagaan', 'Pengaturan Jaga/Dinas']],
            ['STANDAR FASILITAS', ['Denah Ruangan', 'Standar Fasilitas']], ['KEMAMPUAN PELAYANAN', []],
            ['TATA LAKSANA', []], ['LOGISTIK', []], ['KESELAMATAN PASIEN', []], ['KESELAMATAN KERJA', []],
            ['PENGENDALIAN MUTU', []], ['PENUTUP', []],
        ], false],
        'pedoman_kerja_unit' => ['Pedoman Kerja Unit', 'BAB', [
            ['PENDAHULUAN', ['Latar Belakang', 'Ruang Lingkup Pelayanan', 'Batasan Operasional']],
            ['STANDAR KETENAGAAN', ['Kualifikasi Sumber Daya Manusia', 'Distribusi Ketenagaan', 'Pengaturan Jaga/Dinas']],
            ['STANDAR FASILITAS', ['Denah Ruangan', 'Standar Fasilitas']], ['TATA LAKSANA', []], ['LOGISTIK', []],
            ['BUDAYA KESELAMATAN', []], ['KESELAMATAN KERJA', []], ['PENGENDALIAN MUTU', []], ['PENUTUP', []],
        ], false],
        'pedoman_kerja_komite' => ['Pedoman Kerja Komite/Tim', 'BAB', [
            ['PENDAHULUAN', ['Latar Belakang', 'Tujuan Pedoman']],
            ['PENGORGANISASIAN', ['Struktur Organisasi', 'Uraian Tugas dan Wewenang', 'Tata Hubungan Kerja']],
            ['ISI TENTANG PEDOMAN', []], ['MONITORING, EVALUASI, DAN PELAPORAN', []], ['PENUTUP', []],
        ], false],
        'panduan' => ['Panduan', 'BAB', [
            ['PENDAHULUAN', []], ['RUANG LINGKUP', []], ['TATA LAKSANA', []], ['DOKUMENTASI', []],
        ], true],
        'program_unit' => ['Program Kerja Unit Pelayanan / Unit Kerja', 'romawi', [
            ['PENDAHULUAN', []], ['LATAR BELAKANG', []], ['TUJUAN UMUM DAN TUJUAN KHUSUS', ['Tujuan Umum', 'Tujuan Khusus']],
            ['KEGIATAN POKOK DAN RINCIAN KEGIATAN', ['Sumber Daya Manusia (SDM)', 'Fasilitas', 'Peningkatan Mutu',
                'Keselamatan Pasien', 'Manajemen Risiko', 'Pencegahan dan Pengendalian Infeksi', 'Pengembangan Pelayanan',
                'Monitoring dan Evaluasi', 'Rapat', 'Laporan']],
            ['CARA MELAKSANAKAN KEGIATAN', []], ['SASARAN', []], ['JADWAL PELAKSANAAN KEGIATAN', []],
            ['EVALUASI PELAKSANAAN KEGIATAN DAN PELAPORAN', ['Evaluasi Pelaksanaan Kegiatan', 'Pelaporan Evaluasi']],
            ['PENCATATAN, PELAPORAN DAN EVALUASI KEGIATAN', []],
        ], false],
        'program_komite' => ['Program Kerja Komite/Tim', 'romawi', [
            ['PENDAHULUAN', []], ['LATAR BELAKANG', []], ['TUJUAN UMUM DAN TUJUAN KHUSUS', ['Tujuan Umum', 'Tujuan Khusus']],
            ['KEGIATAN POKOK DAN RINCIAN KEGIATAN', ['Monitoring dan Evaluasi', 'Rapat', 'Pelatihan', 'Supervisi',
                'Evaluasi dan Usulan Regulasi', 'Evaluasi Capaian Mutu', 'Sosialisasi / Diseminasi', 'Laporan']],
            ['CARA MELAKSANAKAN KEGIATAN', []], ['SASARAN', []], ['JADWAL PELAKSANAAN KEGIATAN', []],
            ['EVALUASI PELAKSANAAN KEGIATAN DAN PELAPORAN', ['Evaluasi Pelaksanaan Kegiatan', 'Pelaporan']],
            ['PENCATATAN, PELAPORAN, DAN EVALUASI KEGIATAN', []],
        ], false],
    ];

    public static function format(?string $jenisKode): ?string
    {
        return $jenisKode === null ? null : (self::FORMAT_JENIS[$jenisKode] ?? 'umum');
    }

    /** Path berkas gambar blok: "impor:<relatif akar arsip>" atau "unggah:<relatif disk local>"; null bila tak aman/tak ada. */
    public static function pathGambar(?string $src): ?string
    {
        if (! $src || ! str_contains($src, ':')) {
            return null;
        }
        [$lokasi, $rel] = explode(':', $src, 2);

        return DokumenService::pathBerkas(['lokasi' => $lokasi, 'path' => $rel]);
    }

    /** Sistematika bawaan menurut jenis (bisa diganti di penyusun). */
    public static function sistematikaBawaan(string $jenisKode): string
    {
        return match ($jenisKode) {
            'PAN' => 'panduan',
            'PROG' => 'program_unit',
            default => 'pedoman_pelayanan',
        };
    }

    /** Kerangka BAB dari sistematika baku — isi kosong, judul & sub sesuai Pedoman. */
    public static function kerangkaBab(string $sistematika): array
    {
        [, , $bab] = self::SISTEMATIKA[$sistematika] ?? self::SISTEMATIKA['pedoman_pelayanan'];

        return array_map(fn ($b) => [
            'judul' => $b[0], 'isi' => '',
            'sub' => array_map(fn ($s) => ['judul' => $s, 'isi' => ''], $b[1]),
        ], $bab);
    }

    /** Isi naskah kosong yang sudah memuat bagian wajib sesuai format. */
    public static function kosong(string $jenisKode, string $judul): array
    {
        $format = self::format($jenisKode);
        $direktur = config('dokumen.pejabat.direktur');
        $kaki = ['tempat' => 'Tulungagung', 'tanggal' => '', 'jabatan' => 'DIREKTUR RSUI MADINAH,',
            'nama' => $direktur['nama'] ?? '', 'nik' => ''];
        $judulBesar = mb_strtoupper($judul);

        return match ($format) {
            'peraturan', 'keputusan' => [
                'format' => $format,
                'judulJenis' => self::JUDUL_JENIS[$jenisKode] ?? 'KEPUTUSAN DIREKTUR RSUI MADINAH',
                'tentang' => $judulBesar,
                'pembuat' => 'DIREKTUR RSUI MADINAH TULUNGAGUNG,',
                'menimbang' => ['bahwa …;'],
                'mengingat' => ['…;'],
                'menetapkan' => '',
                ($format === 'peraturan' ? 'pasal' : 'diktum') => [['isi' => '']],
                'kaki' => $kaki,
                'tembusan' => [],
            ],
            'bab' => [
                'format' => 'bab',
                'sistematika' => $s = self::sistematikaBawaan($jenisKode),
                'judul' => $judulBesar,
                'bab' => self::kerangkaBab($s),
                'kepustakaan' => '',
                'kaki' => $jenisKode === 'PROG'
                    ? ['tempat' => 'Tulungagung', 'tanggal' => '', 'mengetahui' => ['jabatan' => 'Direktur RSUI MADINAH,', 'nama' => $direktur['nama'] ?? ''],
                        'penyusun' => ['jabatan' => 'Kepala Unit …,', 'nama' => '']]
                    : $kaki,
            ],
            'undangan' => [
                'format' => 'undangan', 'sifat' => 'Biasa', 'lampiran' => '-', 'hal' => 'Undangan ' . $judul,
                'yth' => ['Seluruh Kepala Unit/Instalasi RSUI Madinah', 'di Tempat'],
                'pembuka' => '<p>Dengan hormat, mengharap kehadiran Bapak/Ibu pada:</p>',
                'acara' => ['hari' => '', 'tanggal' => '', 'waktu' => '', 'tempat' => '', 'acara' => $judul],
                'penutup' => '<p>Demikian undangan ini kami sampaikan. Atas perhatian dan kehadirannya kami ucapkan terima kasih.</p>',
                'kaki' => $kaki, 'tembusan' => [],
            ],
            'edaran' => [
                'format' => 'edaran', 'judulJenis' => 'SURAT EDARAN', 'tentang' => $judulBesar,
                'yth' => ['Seluruh Kepala Unit/Instalasi RSUI Madinah'], 'isi' => '', 'kaki' => $kaki, 'tembusan' => [],
            ],
            default => [
                'format' => 'umum',
                'blok' => [['tipe' => 'html', 'html' => '<p style="text-align:center"><strong>' . e($judulBesar) . '</strong></p><p></p>']],
                'kaki' => ['tempat' => 'Tulungagung', 'tanggal' => '', 'jabatan' => '', 'nama' => ''],
            ],
        };
    }

    /** Baris isi surat undangan (Pedoman hal. 40): kunci JSON => label. */
    public const ACARA = ['hari' => 'Hari', 'tanggal' => 'Tanggal', 'waktu' => 'Waktu', 'tempat' => 'Tempat', 'acara' => 'Acara'];

    /** Diktum Menetapkan bawaan: "PERATURAN DIREKTUR … TENTANG …". */
    public static function menetapkan(array $n): string
    {
        return filled($n['menetapkan'] ?? '') ? $n['menetapkan']
            : rtrim(($n['judulJenis'] ?? '') . ' TENTANG ' . ($n['tentang'] ?? ''), '.') . '.';
    }

    public static function romawi(int $n): string
    {
        $peta = ['M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50,
            'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1];
        $h = '';
        foreach ($peta as $r => $v) {
            while ($n >= $v) {
                $h .= $r;
                $n -= $v;
            }
        }

        return $h;
    }

    /** Huruf urut a., b., … (Menimbang) / A., B., … (sub BAB). */
    public static function huruf(int $i, bool $besar = false): string
    {
        $s = '';
        $i++;
        while ($i > 0) {
            $m = ($i - 1) % 26;
            $s = chr(97 + $m) . $s;
            $i = intdiv($i - $m, 26);
        }

        return $besar ? strtoupper($s) : $s;
    }

    /** Data Lampiran (Pedoman hal. 13): diambil dari induk bila induknya Peraturan/Keputusan. */
    public static function lampiranDari(?int $indukId): ?array
    {
        if (! $indukId) {
            return null;
        }
        $data = DokumenService::baca($indukId);
        if (! $data) {
            return null;
        }
        [$row, $json] = $data;
        if (! in_array(self::format($row->jenis_kode), ['peraturan', 'keputusan'], true)) {
            return null;
        }
        $n = $json['isi']['naskah'] ?? [];

        return [
            'nama' => mb_strtoupper($n['judulJenis'] ?? (self::JUDUL_JENIS[$row->jenis_kode] ?? '')),
            'nomor' => $row->dok_no ?: '…',
            // v1 menyimpan "tentang", v2 menyimpan "judul" (teks biasa, dikapitalkan saat cetak)
            'tentang' => mb_strtoupper($n['tentang'] ?? $n['judul'] ?? DokumenService::judul($row, $json)),
        ];
    }
}
