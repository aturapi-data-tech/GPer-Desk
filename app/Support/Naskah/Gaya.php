<?php

namespace App\Support\Naskah;

/**
 * Pembaca lembar gaya config/tata_naskah.php — dipakai bersama oleh CetakPdf dan CetakDocx supaya
 * kedua keluaran memakai ukuran, jarak dan bentuk huruf yang sama persis.
 */
class Gaya
{
    public static function c(string $kunci, mixed $bawaan = null): mixed
    {
        return config('tata_naskah.' . $kunci, $bawaan);
    }

    /** Gaya lengkap satu peran, dengan bawaan terisi. */
    public static function peran(string $peran): array
    {
        $g = self::c("gaya.{$peran}", []);

        return [
            'rata' => $g['rata'] ?? 'kiri',
            'kapital' => (bool) ($g['kapital'] ?? false),
            'tebal' => (bool) ($g['tebal'] ?? false),
            'miring' => (bool) ($g['miring'] ?? false),
            'ukuran' => (float) ($g['ukuran'] ?? self::ukuran()),
            'sebelum' => (float) ($g['sebelum'] ?? 0),
            'sesudah' => (float) ($g['sesudah'] ?? 0),
            'ikut' => (bool) ($g['ikut'] ?? false),
        ];
    }

    public static function ukuran(): float
    {
        return (float) self::c('huruf.ukuran', 12);
    }

    public static function ukuranTabel(): float
    {
        return (float) self::c('huruf.tabel', 10);
    }

    public static function spasi(): float
    {
        return (float) self::c('huruf.spasi', 1.15);
    }

    /** Tinggi n baris dalam pt (1 baris = ukuran × spasi). */
    public static function barisPt(float $n, ?float $ukuran = null): float
    {
        return round($n * ($ukuran ?? self::ukuran()) * self::spasi(), 2);
    }

    /** Teks sesuai bentuk huruf peran (kapital). */
    public static function teks(string $peran, ?string $teks): string
    {
        $teks = (string) $teks;

        return self::peran($peran)['kapital'] ? mb_strtoupper($teks) : $teks;
    }

    /** Nomor butir tingkat $tingkat (0 = 'I.') urutan ke-$i (0-based). */
    public static function nomor(int $tingkat, int $i): string
    {
        $pola = self::c('penomoran', ['I.', 'A.', '1.', 'a.', '1)', 'a)', '(1)', '(a)']);
        $p = $pola[min($tingkat, count($pola) - 1)];

        return preg_replace_callback('/I|A|a|1/', fn ($m) => match ($m[0]) {
            'I' => \App\Support\TataNaskah::romawi($i + 1),
            'A' => \App\Support\TataNaskah::huruf($i, true),
            'a' => \App\Support\TataNaskah::huruf($i),
            default => (string) ($i + 1),
        }, $p, 1);
    }

    public static function mulai(string $konteks): int
    {
        return (int) self::c("mulai_penomoran.{$konteks}", 3);
    }

    /** [atas, kanan, bawah, kiri] cm. */
    public static function marjin(string $jenis = 'naskah'): array
    {
        return self::c("halaman.{$jenis}", [7.37, 2.5, 2.0, 2.5]);
    }

    /** Lebar area tulis A4 (cm). */
    public static function lebarTulis(string $jenis = 'naskah'): float
    {
        $m = self::marjin($jenis);

        return 21.0 - $m[1] - $m[3];
    }
}
