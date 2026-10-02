<?php

namespace App\Support\Naskah;

/**
 * Kerangka naskah versi 2: bagian wajib per jenis menurut Pedoman Tata Naskah BAB III.
 * Isi hanya teks — tanpa HTML, tanpa kapital ketikan, tanpa nomor butir ketikan, tanpa tanda baca akhir
 * butir; semua itu diterapkan saat cetak (Susun + config/tata_naskah.php).
 *
 * Simpul isi batang tubuh (pasal, diktum, pembuka, penutup, lampiran):
 *   "teks"                                  paragraf
 *   {"daftar": [butir…], "urut": true}      butir bertingkat; butir = "teks" | {"teks": "…", "anak": [simpul…]}
 *   {"tabel": {"kepala": […], "baris": [[…]]}}
 *
 * Pasal boleh membuka BAB: {"bab": "Ketentuan Umum", "isi": […]} — nomor BAB I, II … dihitung saat cetak.
 * Lampiran: "lampiran": [{"judul": "…", "isi": [simpul…]}] — kepala LAMPIRAN/NOMOR/TENTANG dibuat otomatis.
 */
class Kerangka
{
    /** Keluarga cetak per jenis yang sudah memakai versi 2. */
    public const KELUARGA = [
        'PERDIR' => 'arahan',
        'SK' => 'arahan',
        'KEB' => 'arahan',
        'UND' => 'undangan',
    ];

    public const JUDUL_JENIS = [
        'PERDIR' => 'Peraturan Direktur RSUI MADINAH',
        'SK' => 'Keputusan Direktur RSUI MADINAH',
        'KEB' => 'Kebijakan Direktur RSUI MADINAH',
    ];

    public static function keluarga(?string $jenis): ?string
    {
        return self::KELUARGA[$jenis] ?? null;
    }

    public static function versi2(array $n): bool
    {
        return (int) ($n['versi'] ?? 1) >= 2;
    }

    /** Isi kosong yang sudah memuat bagian wajib. */
    public static function kosong(string $jenis, string $judul = ''): array
    {
        $direktur = config('dokumen.pejabat.direktur');
        $kaki = ['tempat' => 'Tulungagung', 'tanggal' => '', 'jabatan' => $direktur['jabatan'] ?? 'Direktur',
            'instansi' => 'RSUI MADINAH', 'nama' => $direktur['nama'] ?? '', 'nik' => ''];

        return match (self::keluarga($jenis)) {
            'arahan' => [
                'versi' => 2, 'jenis' => $jenis,
                'judulJenis' => self::JUDUL_JENIS[$jenis],
                'judul' => $judul,
                'pembuat' => 'Direktur RSUI MADINAH Tulungagung',
                'menimbang' => ['bahwa …'],
                'mengingat' => ['…'],
                'menetapkan' => '',
                ($jenis === 'PERDIR' ? 'pasal' : 'diktum') => [['isi' => ['…']]],
                'kaki' => $kaki,
                'tembusan' => [],
                'lampiran' => [],
            ],
            'undangan' => [
                'versi' => 2, 'jenis' => $jenis,
                'sifat' => '', 'lampiran' => '', 'hal' => $judul ?: 'Undangan',
                'tanggal' => '',
                'yth' => [''], 'alamat' => 'di Tempat',
                'pembuka' => ['Dengan hormat, kami mengundang Bapak/Ibu untuk hadir pada:'],
                'acara' => ['tanggal' => '', 'waktu' => '', 'tempat' => '', 'acara' => '', 'lain' => []],
                'penutup' => ['Mengingat pentingnya acara tersebut, kami mohon Bapak/Ibu hadir tepat waktu. Atas perhatian dan kehadirannya kami ucapkan terima kasih.'],
                'kaki' => $kaki,
                'tembusan' => [],
            ],
            default => throw new \InvalidArgumentException("Jenis {$jenis} belum memakai naskah versi 2."),
        };
    }
}
