<?php

namespace App\Console\Commands;

use App\Services\DokumenService;
use App\Support\Naskah\Kerangka;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pasang naskah versi 2 (perkakas/naskah_v2.py → .registri/isi_naskah_v2.json) ke DOK_JSON.isi.naskah.
 *
 * Aman untuk hasil kerja orang: isi v1 yang pernah disimpan dari penyusun ("isi naskah diperbarui" di riwayat)
 * TIDAK ditimpa. Isi v1 hasil impor otomatis diganti, dan salinannya disimpan di DOK_JSON.isiV1 supaya bisa
 * dikembalikan. Dokumen yang punya isi SPO dilewati.
 */
class ImporNaskahV2 extends Command
{
    protected $signature = 'dokumen:impor-naskah-v2 {berkas? : path isi_naskah_v2.json} {--coba : hanya laporan, tidak menulis}';

    protected $description = 'Pasang isi naskah tata naskah v2 (teks + lembar gaya) hasil konversi perkakas';

    public function handle(): int
    {
        $berkas = $this->argument('berkas') ?: rtrim(config('dokumen.arsip_impor'), '/') . '/perkakas/.registri/isi_naskah_v2.json';
        $semua = json_decode((string) @file_get_contents($berkas), true);
        if (! is_array($semua)) {
            $this->error("Berkas tidak terbaca: {$berkas}");

            return self::FAILURE;
        }
        $peta = DB::table('dok_dokumen')->whereNotNull('kunci_impor')->pluck('dok_id', 'kunci_impor')->all();
        $hasil = ['dipasang' => [], 'sudah_v2' => [], 'disunting' => [], 'spo' => [], 'tak_ada' => [], 'jenis_lain' => []];

        foreach ($semua as $kunci => $n) {
            $id = $peta[DokumenService::latin1($kunci)] ?? null;
            if (! $id) {
                $hasil['tak_ada'][] = $kunci;

                continue;
            }
            [$row, $json] = DokumenService::baca((int) $id);
            $lama = $json['isi']['naskah'] ?? null;
            if (filled($json['isi']['spo'] ?? null)) {
                $hasil['spo'][] = "{$id} {$kunci}";

                continue;
            }
            if ($lama && Kerangka::versi2($lama)) {
                $hasil['sudah_v2'][] = "{$id} {$kunci}";

                continue;
            }
            $disunting = collect($json['riwayat'] ?? [])->contains(fn ($r) => ($r['aksi'] ?? '') === 'SUSUN'
                && str_starts_with((string) ($r['catatan'] ?? ''), 'isi naskah diperbarui'));
            if ($lama && $disunting) {
                $hasil['disunting'][] = "{$id} {$kunci}";

                continue;
            }
            // jenis mengikuti registri; naskah arahan saja yang punya kerangka v2 ini
            if (Kerangka::keluarga($row->jenis_kode) !== 'arahan') {
                $hasil['jenis_lain'][] = "{$id} [{$row->jenis_kode}] {$kunci}";

                continue;
            }
            $n['jenis'] = $row->jenis_kode;
            $sumber = $n['_sumber'] ?? ($json['isiSumber'] ?? null);
            unset($n['_sumber']);
            $hasil['dipasang'][] = "{$id} [{$row->jenis_kode}] {$kunci}";
            if ($this->option('coba')) {
                continue;
            }
            DokumenService::catat((int) $id, 'SUSUN',
                'isi naskah diubah ke tata naskah v2 (isi teks + lembar gaya) — perlu dicek' . ($lama ? '; isi lama disimpan di isiV1' : ''),
                function ($json) use ($n, $lama, $sumber) {
                    if ($lama) {
                        $json['isiV1'] = $lama;
                    }
                    $json['isi']['naskah'] = $n;
                    $json['isiSumber'] = $sumber;

                    return $json;
                });
        }

        $label = ['dipasang' => $this->option('coba') ? 'akan dipasang' : 'dipasang', 'sudah_v2' => 'sudah v2',
            'disunting' => 'dilewati — v1 pernah disunting di penyusun', 'spo' => 'dilewati — punya isi SPO',
            'tak_ada' => 'tidak ada di registri', 'jenis_lain' => 'dilewati — jenis registri bukan PERDIR/SK/KEB'];
        foreach ($hasil as $k => $daftar) {
            $this->info(sprintf('%-46s %d', $label[$k], count($daftar)));
            if ($k !== 'dipasang' && $daftar) {
                foreach ($daftar as $d) {
                    $this->line('   · ' . $d);
                }
            }
        }

        return self::SUCCESS;
    }
}
