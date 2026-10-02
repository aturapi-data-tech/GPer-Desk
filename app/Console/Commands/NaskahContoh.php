<?php

namespace App\Console\Commands;

use App\Http\Controllers\DokumenCetakController as Cetak;
use Illuminate\Console\Command;

/**
 * Cetak naskah versi 2 dari berkas JSON ke PDF + DOCX tanpa menyentuh database — untuk memeriksa
 * hasil lembar gaya (config/tata_naskah.php) sebelum naskah diimpor.
 *
 * Berkas JSON: satu naskah ({"versi":2, …}) atau peta {kunci: naskah}. Data registri (nomor, status)
 * diambil dari "_dok" di dalam naskah bila ada, selain itu dari opsi.
 */
class NaskahContoh extends Command
{
    protected $signature = 'naskah:contoh {berkas : JSON naskah versi 2} {--kunci= : kunci naskah bila berkas berupa peta}
        {--nomor= : nomor naskah} {--status=DRAF} {--keluar= : folder keluaran (bawaan storage/app/naskah-contoh)}';

    protected $description = 'Cetak naskah versi 2 dari JSON ke PDF dan DOCX (pratinjau lembar gaya)';

    public function handle(): int
    {
        $data = json_decode((string) file_get_contents($this->argument('berkas')), true);
        if (! is_array($data)) {
            $this->error('Berkas bukan JSON.');

            return self::FAILURE;
        }
        $daftar = isset($data['versi']) ? ['naskah' => $data]
            : ($this->option('kunci') ? [$this->option('kunci') => $data[$this->option('kunci')] ?? null] : $data);
        $keluar = $this->option('keluar') ?: storage_path('app/naskah-contoh');
        @mkdir($keluar, 0775, true);

        foreach ($daftar as $kunci => $n) {
            if (! is_array($n)) {
                $this->warn("{$kunci}: tidak ada.");

                continue;
            }
            $d = $n['_dok'] ?? [];
            $dok = (object) [
                'dok_no' => $d['nomor'] ?? $this->option('nomor'),
                'dok_judul' => $d['judul'] ?? ($n['judul'] ?? $n['hal'] ?? $kunci),
                'jenis_kode' => $n['jenis'],
                'status' => $d['status'] ?? $this->option('status'),
                'tgl_terbit' => $d['tgl_terbit'] ?? null,
            ];
            $nama = preg_replace('/[^\pL\pN ,.-]+/u', ' ', pathinfo(explode('|', (string) $kunci)[0], PATHINFO_FILENAME));
            file_put_contents("{$keluar}/{$nama}.pdf", Cetak::pdfVersi2($dok, $n, null));
            rename(Cetak::docxVersi2($dok, $n, null), "{$keluar}/{$nama}.docx");
            $this->line("✓ {$keluar}/{$nama}.pdf + .docx");
        }

        return self::SUCCESS;
    }
}
