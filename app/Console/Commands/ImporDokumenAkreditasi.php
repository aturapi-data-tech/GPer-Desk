<?php

namespace App\Console\Commands;

use App\Services\DokumenService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Impor dokumen akreditasi (folder pokja + folder kerja MRMIK) ke Registri Dokumen.
 *
 * Manifest dibuat oleh perkakas akreditasi: `python3 perkakas/ekspor_registri_dokumen.py`
 * (bawaan: <DOK_ARSIP_IMPOR>/perkakas/.registri/manifest.json). Berkas tidak disalin —
 * DOK_JSON.berkas merujuk path relatif terhadap DOK_ARSIP_IMPOR.
 *
 * Tahap 1: masukkan/perbarui per kunci "nama|ukuran" (KUNCI_IMPOR) — aman dijalankan ulang.
 * Tahap 2: tautkan induk — (a) nomor naskah yang dirujuk isi dokumen, (b) satu-satunya regulasi
 *          di folder EP yang sama. Keduanya ditandai "dugaan impor" di JSON (indukSumber).
 * Tahap 3: sinkronkan penghitung nomor dengan nomor urut tertinggi yang sudah tercatat.
 */
class ImporDokumenAkreditasi extends Command
{
    protected $signature = 'dokumen:impor-akreditasi
        {manifest? : path manifest.json}
        {--perbarui : timpa data hasil impor pada dokumen yang sudah ada (isi, status manual & riwayat dipertahankan)}
        {--tanpa-induk : lewati penautan induk}';

    protected $description = 'Impor dokumen akreditasi (manifest perkakas) ke registri dokumen bertingkat';

    private const MIME = [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function handle(): int
    {
        $path = $this->argument('manifest') ?: rtrim(config('dokumen.arsip_impor'), '/') . '/perkakas/.registri/manifest.json';
        if (! is_file($path)) {
            $this->error("Manifest tidak ditemukan: {$path}");
            return self::FAILURE;
        }
        $manifest = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $jenis = DokumenService::jenis();
        $ada = DB::table('dok_dokumen')->whereNotNull('kunci_impor')->pluck('dok_id', 'kunci_impor')->all();

        $baru = $diperbarui = $dilewati = 0;
        $petaId = [];            // kunci impor → dok_id
        $bar = $this->output->createProgressBar(count($manifest['dokumen']));
        foreach ($manifest['dokumen'] as $d) {
            $bar->advance();
            if (! isset($jenis[$d['jenis']])) {
                $d['jenis'] = 'BUKTI';
            }
            // kolom VARCHAR2 ikut charset basis data (bisa Latin-1) — kunci dibandingkan dalam bentuk Latin-1
            $kunci = DokumenService::latin1($d['kunci']);
            $id = $ada[$kunci] ?? null;
            if ($id && ! $this->option('perbarui')) {
                $petaId[$d['kunci']] = (int) $id;
                $dilewati++;
                continue;
            }
            $akreditasi = collect($d['akreditasi'])->unique(fn ($a) => implode('|', $a))->values()->all();
            $impor = [
                'akreditasi' => $akreditasi,
                'berkas' => array_map(fn ($b) => [
                    'id' => substr(md5($d['kunci'] . '|' . $b['path']), 0, 16), 'peran' => $b['peran'], 'lokasi' => 'impor',
                    'path' => $b['path'], 'nama' => $b['nama'], 'ukuran' => (int) $b['ukuran'],
                    'mime' => self::MIME[strtolower(pathinfo($b['nama'], PATHINFO_EXTENSION))] ?? 'application/octet-stream',
                    'waktu' => now()->format('Y-m-d H:i:s'), 'oleh' => 'impor',
                ], $d['berkas']),
                'tataNaskah' => ['golongan' => $d['golongan'], 'butir' => $d['butir'] ?: [], 'halaman' => $d['halaman']],
                'rujukan' => array_map(fn ($k, $t) => ['kunci' => $k, 'teks' => $t], $d['rujukan'], $d['rujukan_teks']),
                'impor' => ['waktu' => now()->format('Y-m-d H:i:s'), 'manifest' => $path, 'nomorKunci' => $d['nomor_kunci']],
            ];
            $header = [
                'jenis_kode' => $d['jenis'],
                'dok_judul' => Str::limit($d['judul'], 990, ''),
                'dok_no' => $d['nomor'],
                'dok_no_urut' => $d['nomor'] ? $d['nomor_urut'] : null,
                'tgl_terbit' => $d['tgl_terbit'],
                'sumber' => 'IMPOR',
                'pokja' => implode(',', array_values(array_unique(array_column($akreditasi, 'bab')))) ?: null,
                'kunci_impor' => $kunci,
            ];
            if ($id) {
                [, $json] = DokumenService::baca((int) $id);
                $json = array_replace($json, $impor);
                DokumenService::simpan((int) $id, $header, $json, 'IMPOR', 'diperbarui dari manifest');
                $diperbarui++;
            } else {
                $json = array_replace(DokumenService::jsonKosong(), $impor);
                $id = DokumenService::simpan(null, $header + ['status' => 'BERLAKU', 'klasifikasi' => 'BIASA'], $json, 'IMPOR',
                    'dari ' . ($d['berkas'][0]['path'] ?? $d['nama']) . ' — status awal BERLAKU (unggahan bukti akreditasi), mohon verifikasi');
                $baru++;
            }
            $petaId[$d['kunci']] = (int) $id;
        }
        $bar->finish();
        $this->newLine();
        $this->info("Tahap 1: {$baru} baru, {$diperbarui} diperbarui, {$dilewati} sudah ada (dilewati).");

        $this->pasangIsiSpo(dirname($path) . '/isi_spo.json', $petaId);
        $this->pasangIsiNaskah(dirname($path) . '/isi_naskah.json', $petaId);
        $this->pasangIsiUmum(dirname($path) . '/umum', $petaId);

        if (! $this->option('tanpa-induk')) {
            $this->tautkanInduk($manifest['dokumen'], $petaId, $jenis);
        }

        $sinkron = DokumenService::sinkronPenghitung();
        $this->info('Tahap 3: penghitung nomor disinkronkan' . ($sinkron ? ' — ' . implode('; ', $sinkron) : ' (tidak ada perubahan).'));

        return self::SUCCESS;
    }

    /**
     * Isi terstruktur SPO buatan sendiri (perkakas/ekspor_isi_spo.py) → DOK_JSON.isi.spo, supaya SPO itu
     * langsung bisa disunting & dicetak ulang dari penyusun SPO. Isi yang sudah ada tidak ditimpa.
     */
    private function pasangIsiSpo(string $berkas, array $petaId): void
    {
        if (! is_file($berkas)) {
            return;
        }
        $isiSemua = json_decode(file_get_contents($berkas), true) ?: [];
        $dipasang = 0;
        foreach ($isiSemua as $kunci => $isi) {
            $id = $petaId[$kunci] ?? null;
            if (! $id) {
                continue;
            }
            [, $json] = DokumenService::baca($id);
            if (filled($json['isi']['spo'] ?? null)) {
                continue;
            }
            DokumenService::catat($id, 'SUSUN', 'isi SPO diambil dari .docx sumber (impor)', function ($json) use ($isi) {
                $json['isi']['spo'] = $isi;
                $json['penandatangan']['ditetapkan'] ??= config('dokumen.pejabat.direktur');
                return $json;
            });
            $dipasang++;
        }
        $this->info("Isi SPO terstruktur dipasang ke {$dipasang} dokumen.");
    }

    /**
     * Isi naskah terstruktur hasil konversi PDF (perkakas/ekspor_isi_naskah.py) → DOK_JSON.isi.naskah,
     * format menurut App\Support\TataNaskah. Ditandai perlu dicek; isi yang sudah ada tidak ditimpa.
     */
    private function pasangIsiNaskah(string $berkas, array $petaId): void
    {
        if (! is_file($berkas)) {
            return;
        }
        $semua = json_decode(file_get_contents($berkas), true) ?: [];
        $dipasang = 0;
        foreach ($semua as $kunci => $naskah) {
            $id = $petaId[$kunci] ?? null;
            if (! $id) {
                continue;
            }
            [, $json] = DokumenService::baca($id);
            if (filled($json['isi']['naskah'] ?? null)) {
                continue;
            }
            $sumber = $naskah['_sumber'] ?? null;
            unset($naskah['_sumber']);
            DokumenService::catat($id, 'SUSUN', 'isi naskah dikonversi otomatis dari PDF — perlu dicek', function ($json) use ($naskah, $sumber) {
                $json['isi']['naskah'] = $naskah;
                $json['isiSumber'] = $sumber;
                return $json;
            });
            $dipasang++;
        }
        $this->info("Isi naskah (JSON tata naskah) dipasang ke {$dipasang} dokumen — ditandai perlu dicek.");
    }

    /**
     * Isi format "umum" (perkakas/ekspor_isi_umum.py: blok html/gambar, OCR pindaian) → isi.naskah untuk
     * dokumen yang belum punya isi terstruktur. Satu berkas JSON per dokumen di folder umum/.
     */
    private function pasangIsiUmum(string $folder, array $petaId): void
    {
        if (! is_dir($folder)) {
            return;
        }
        $dipasang = 0;
        foreach (glob($folder . '/*.json') as $berkas) {
            $isi = json_decode(file_get_contents($berkas), true);
            $id = $petaId[$isi['kunci'] ?? ''] ?? null;
            if (! $id) {
                continue;
            }
            [, $json] = DokumenService::baca($id);
            if (filled($json['isi']['naskah'] ?? null) || filled($json['isi']['spo'] ?? null)) {
                continue;
            }
            $naskah = $isi['naskah'];
            $sumber = $naskah['_sumber'] ?? null;
            unset($naskah['_sumber']);
            $naskah['kaki'] ??= ['tempat' => 'Tulungagung', 'tanggal' => '', 'jabatan' => '', 'nama' => ''];
            DokumenService::catat($id, 'SUSUN', 'isi dikonversi otomatis (' . ($sumber['jenis'] ?? 'pdf') . ') — perlu dicek', function ($json) use ($naskah, $sumber) {
                $json['isi']['naskah'] = $naskah;
                $json['isiSumber'] = $sumber;
                return $json;
            });
            $dipasang++;
        }
        $this->info("Isi dokumen umum (blok teks/gambar, OCR) dipasang ke {$dipasang} dokumen — ditandai perlu dicek.");
    }

    private function tautkanInduk(array $dokumen, array $petaId, $jenis): void
    {
        $tingkat = fn (string $kode) => (int) ($jenis[$kode]->tingkat ?? 5);
        // nomor kunci (urut/KODE/bulan/tahun) → [dok_id, jenis]; hanya dokumen bernomor
        $perNomor = [];
        foreach ($dokumen as $d) {
            if ($d['nomor_kunci'] && isset($petaId[$d['kunci']])) {
                $perNomor[$d['nomor_kunci']][] = ['id' => $petaId[$d['kunci']], 'jenis' => $d['jenis']];
            }
        }
        // regulasi per folder EP (elemen R, tingkat ≤ 3)
        $regulasiEp = [];
        foreach ($dokumen as $d) {
            foreach ($d['lokasi'] as $lok) {
                $bag = explode('/', $lok);
                if (count($bag) >= 5 && str_starts_with($bag[3], 'R - ') && $tingkat($d['jenis']) <= 3 && isset($petaId[$d['kunci']])) {
                    $regulasiEp[implode('/', array_slice($bag, 0, 3))][$petaId[$d['kunci']]] = $d['jenis'];
                }
            }
        }

        $sudahBerinduk = DB::table('dok_dokumen')->whereNotNull('induk_id')->pluck('dok_id')->flip()->all();
        $nomor = $folder = 0;
        foreach ($dokumen as $d) {
            $id = $petaId[$d['kunci']] ?? null;
            if (! $id || isset($sudahBerinduk[$id])) {
                continue;
            }
            $tSaya = $tingkat($d['jenis']);
            $pilih = null;
            $sumber = null;
            // (a) rujukan nomor: induk = dokumen dirujuk yang tingkatnya lebih tinggi, paling dekat
            foreach ($d['rujukan'] as $i => $k) {
                foreach ($perNomor[$k] ?? [] as $cal) {
                    if ($cal['id'] !== $id && $tingkat($cal['jenis']) < $tSaya
                        && (! $pilih || $tingkat($cal['jenis']) > $tingkat($pilih['jenis']))) {
                        $pilih = $cal;
                        $sumber = 'rujukan nomor ' . ($d['rujukan_teks'][$i] ?? $k);
                    }
                }
            }
            // (b) satu-satunya regulasi di folder EP yang sama (untuk dokumen tingkat 4–5)
            if (! $pilih && $tSaya >= 4) {
                $bag = explode('/', $d['lokasi'][0]);
                $reg = $regulasiEp[implode('/', array_slice($bag, 0, 3))] ?? [];
                if (count($reg) === 1) {
                    $rid = (int) array_key_first($reg);
                    if ($rid !== $id) {
                        $pilih = ['id' => $rid, 'jenis' => reset($reg)];
                        $sumber = 'satu-satunya regulasi di ' . implode(' / ', array_slice($bag, 1, 2));
                    }
                }
            }
            if (! $pilih) {
                continue;
            }
            DB::table('dok_dokumen')->where('dok_id', $id)->update(['induk_id' => $pilih['id']]);
            DokumenService::catat($id, 'INDUK', "dugaan impor: {$sumber}", function ($json) use ($sumber, $pilih) {
                $json['indukSumber'] = ['dugaan' => true, 'alasan' => $sumber, 'indukId' => $pilih['id']];
                return $json;
            });
            str_starts_with($sumber, 'rujukan') ? $nomor++ : $folder++;
        }
        $this->info("Tahap 2: induk ditautkan — {$nomor} dari rujukan nomor, {$folder} dari folder EP (ditandai dugaan).");
    }
}
