<?php

namespace App\Console\Commands;

use App\Services\DokumenService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bangun indeks akreditasi registri: DOK_AKREDITASI (dari DOK_JSON.akreditasi tiap dokumen) dan
 * DOK_AKR_JUDUL (judul standar & EP dari nama folder arsip akreditasi). Tabel dibuat bila belum ada
 * (DDL sama dengan database/schema/dokumen_regulasi.sql bagian 7). Aman dijalankan ulang.
 */
class IndeksAkreditasi extends Command
{
    protected $signature = 'dokumen:indeks-akreditasi {--tanpa-judul : lewati pembacaan judul dari folder}';

    protected $description = 'Bangun indeks Pokja → Standar → EP untuk tampilan pohon akreditasi registri';

    public function handle(): int
    {
        $this->buatTabel();

        $ids = DB::table('dok_dokumen')->orderBy('dok_id')->pluck('dok_id');
        $bar = $this->output->createProgressBar($ids->count());
        foreach ($ids as $id) {
            [, $json] = DokumenService::baca((int) $id);
            DokumenService::indeksAkreditasi((int) $id, $json['akreditasi'] ?? []);
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info('Indeks: ' . DB::table('dok_akreditasi')->count() . ' pasangan dokumen–EP.');

        if (! $this->option('tanpa-judul')) {
            $this->info('Judul standar/EP dari folder: ' . $this->judulDariFolder());
        }

        return self::SUCCESS;
    }

    private function buatTabel(): void
    {
        if (! Schema::hasTable('dok_akreditasi')) {
            DB::statement('CREATE TABLE DOK_AKREDITASI (DOK_ID NUMBER(12) NOT NULL, BAB VARCHAR2(10) NOT NULL,
                STANDAR VARCHAR2(20) NOT NULL, EP VARCHAR2(10) NOT NULL, ELEMEN VARCHAR2(20),
                CONSTRAINT dok_akr_pk PRIMARY KEY (DOK_ID, BAB, STANDAR, EP),
                CONSTRAINT dok_akr_fk FOREIGN KEY (DOK_ID) REFERENCES DOK_DOKUMEN (DOK_ID) ON DELETE CASCADE)');
            DB::statement('CREATE INDEX dok_akr_ep_idx ON DOK_AKREDITASI (BAB, STANDAR, EP)');
            $this->info('Tabel DOK_AKREDITASI dibuat.');
        }
        if (! Schema::hasTable('dok_akr_judul')) {
            DB::statement('CREATE TABLE DOK_AKR_JUDUL (STANDAR VARCHAR2(20) NOT NULL, EP VARCHAR2(10) NOT NULL,
                JUDUL VARCHAR2(400) NOT NULL, ELEMEN VARCHAR2(20), CONSTRAINT dok_akr_judul_pk PRIMARY KEY (STANDAR, EP))');
            $this->info('Tabel DOK_AKR_JUDUL dibuat.');
        }
    }

    /** "<BAB - nama>/<STANDAR - nama>/EP x (elemen) - nama" → DOK_AKR_JUDUL. Folder MRMIK tanpa " - nama". */
    private function judulDariFolder(): int
    {
        $akar = rtrim((string) config('dokumen.arsip_impor'), '/');
        $pokja = array_keys(config('dokumen.pokja'));
        $judul = [];
        foreach (glob($akar . '/*', GLOB_ONLYDIR) as $dirBab) {
            $nama = basename($dirBab);
            $bab = in_array($nama, $pokja, true) ? $nama : (preg_match('/^([A-Z]+) - /', $nama, $m) ? $m[1] : null);
            if (! $bab || ! in_array($bab, $pokja, true)) {
                continue;
            }
            foreach (glob($dirBab . '/*', GLOB_ONLYDIR) as $dirStd) {
                if (! preg_match('/^(' . $bab . ' \d+(?:\.\d+)*)\s*-\s*(.+)$/u', basename($dirStd), $s)) {
                    continue;
                }
                $judul[$s[1] . '|-'] = [$s[1], '-', trim($s[2]), null];
                foreach (glob($dirStd . '/*', GLOB_ONLYDIR) as $dirEp) {
                    if (preg_match('/^EP\s+([a-z])\s*\(([^)]*)\)\s*-\s*(.+)$/iu', basename($dirEp), $e)) {
                        $judul[$s[1] . '|' . strtolower($e[1])] = [$s[1], strtolower($e[1]), trim($e[3]), trim($e[2])];
                    }
                }
            }
        }
        DB::table('dok_akr_judul')->delete();
        foreach (array_chunk(array_values($judul), 200) as $potong) {
            DB::table('dok_akr_judul')->insert(array_map(fn ($j) => [
                'standar' => $j[0], 'ep' => $j[1],
                'judul' => mb_substr(DokumenService::latin1($j[2]), 0, 400), 'elemen' => $j[3] ? mb_substr($j[3], 0, 20) : null,
            ], $potong));
        }

        return count($judul);
    }
}
