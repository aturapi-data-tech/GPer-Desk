<?php

namespace App\Services;

use App\Support\OracleLob;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Registri Dokumen Regulasi & Akreditasi — logika terpusat (dipakai halaman registri,
 * penyusun SPO, cetak PDF/DOCX, dan perintah impor).
 *
 * Pola data mengikuti EMR sirus: kolom header untuk cari/filter + satu dokumen JSON (CLOB
 * DOK_DOKUMEN.DOK_JSON) untuk isi, akreditasi, berkas, penandatangan dan riwayat.
 * Skema JSON: docs/dokumen-regulasi-modul.md. Tabel: database/schema/dokumen_regulasi.sql.
 */
class DokumenService
{
    public const STATUS = [
        'DRAF'          => 'Draf',
        'BERLAKU'       => 'Berlaku',
        'TIDAK_BERLAKU' => 'Tidak Berlaku',
        'DICABUT'       => 'Dicabut',
    ];

    public const STATUS_BADGE = [
        'DRAF' => 'gray', 'BERLAKU' => 'success', 'TIDAK_BERLAKU' => 'warning', 'DICABUT' => 'danger',
    ];

    // BAB VI Pengamanan Naskah Dinas
    public const KLASIFIKASI = [
        'BIASA' => 'Biasa / Terbuka', 'TERBATAS' => 'Terbatas', 'RAHASIA' => 'Rahasia', 'SANGAT_RAHASIA' => 'Sangat Rahasia',
    ];

    public const TINGKAT = [
        0 => 'Eksternal', 1 => 'Peraturan / Keputusan', 2 => 'Pedoman / Panduan / Program',
        3 => 'SPO / Edaran', 4 => 'Formulir', 5 => 'Bukti / Rekaman',
    ];

    // Bagian isi SPO sesuai format Pedoman hal. 25–26 (urutan = urutan cetak)
    public const BAGIAN_SPO = [
        'pengertian'     => 'PENGERTIAN',
        'tujuan'         => 'TUJUAN',
        'kebijakan'      => 'KEBIJAKAN',
        'prosedur'       => 'PROSEDUR',
        'unitTerkait'    => 'UNIT TERKAIT',
        'dokumenTerkait' => 'DOKUMEN TERKAIT',
    ];

    public const BULAN = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli',
        'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    // ─── Master ───────────────────────────────────────────────────────────────

    /** @return Collection<string, object> jenis aktif, dikunci kode */
    public static function jenis(): Collection
    {
        return Cache::remember('dok.jenis', 300, fn () => DB::table('dok_jenis')
            ->where('aktif', 'Y')->orderBy('urut')->get()->keyBy('kode'));
    }

    /** @return Collection<string, object> */
    public static function unit(): Collection
    {
        return Cache::remember('dok.unit', 300, fn () => DB::table('dok_unit')
            ->where('aktif', 'Y')->orderBy('kode')->get()->keyBy('kode'));
    }

    public static function lupakanCacheMaster(): void
    {
        Cache::forget('dok.jenis');
        Cache::forget('dok.unit');
    }

    // ─── Baca / tulis ─────────────────────────────────────────────────────────

    public static function jsonKosong(?int $dokId = null): array
    {
        return [
            'dokId' => $dokId, 'versiSkema' => 1, 'ringkasan' => '',
            'akreditasi' => [], 'rujukan' => [], 'berkas' => [],
            'isi' => [], 'penandatangan' => [], 'tataNaskah' => null, 'riwayat' => [],
        ];
    }

    /** @return array{0: object, 1: array}|null  [baris header, json] */
    public static function baca(int $dokId): ?array
    {
        $row = DB::table('dok_dokumen')->where('dok_id', $dokId)->first();
        if (! $row) {
            return null;
        }
        $teks = OracleLob::read($row->dok_json ?? null, 'dok_dokumen', 'dok_id', $dokId, 'dok_json');
        $json = $teks !== '' ? (json_decode($teks, true) ?: []) : [];
        unset($row->dok_json);

        return [$row, array_replace(self::jsonKosong($dokId), $json, ['dokId' => $dokId])];
    }

    /**
     * Simpan header + JSON. $header memakai nama kolom (huruf kecil).
     * Induk wajib bertingkat lebih tinggi (angka TINGKAT lebih kecil) — hierarki
     * Peraturan → Pedoman → SPO → Formulir → Bukti dijaga di sini, bukan di UI saja.
     */
    public static function simpan(?int $dokId, array $header, array $json, string $aksi, string $catatan = ''): int
    {
        // kolom VARCHAR2 ikut charset basis data — simpan judul asli di JSON, kolom diberi padanan Latin-1
        if (isset($header['dok_judul'])) {
            $json['judulAsli'] = $header['dok_judul'];
            $header['dok_judul'] = self::latin1($header['dok_judul']);
        }
        self::periksaInduk($dokId, $header['jenis_kode'] ?? null, $header['induk_id'] ?? null);

        return DB::transaction(function () use ($dokId, $header, $json, $aksi, $catatan) {
            $oleh = self::namaPengguna();
            $kini = now();

            if ($dokId === null) {
                // ambil NEXTVAL dulu (trigger hanya mengisi bila DOK_ID kosong) — hindari insertGetId oci8
                $dokId = (int) DB::selectOne('SELECT dok_dokumen_seq.NEXTVAL AS id FROM dual')->id;
                DB::table('dok_dokumen')->insert([
                    ...$header,
                    'dok_id'     => $dokId,
                    'dok_json'   => '{}',
                    'created_by' => $oleh, 'created_at' => $kini,
                    'updated_by' => $oleh, 'updated_at' => $kini,
                ]);
            } else {
                DB::table('dok_dokumen')->where('dok_id', $dokId)->lockForUpdate()->first();
                DB::table('dok_dokumen')->where('dok_id', $dokId)
                    ->update([...$header, 'updated_by' => $oleh, 'updated_at' => $kini]);
            }

            $json['dokId'] = $dokId;
            $json['riwayat'][] = ['waktu' => $kini->format('Y-m-d H:i:s'), 'oleh' => $oleh, 'aksi' => $aksi, 'catatan' => $catatan];
            self::tulisJson($dokId, $json);

            return $dokId;
        });
    }

    public static function tulisJson(int $dokId, array $json): void
    {
        DB::table('dok_dokumen')->where('dok_id', $dokId)->update([
            // TANPA JSON_UNESCAPED_UNICODE: basis data bisa ber-charset Latin-1 (WE8ISO8859P1 di lokal) —
            // em dash, ≤, kutip lengkung akan rusak jadi "¿". Escape \uXXXX membuat JSON murni ASCII.
            'dok_json' => json_encode($json, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);
    }

    /** Tambah satu catatan riwayat tanpa mengubah header. */
    public static function catat(int $dokId, string $aksi, string $catatan, ?callable $ubahJson = null): void
    {
        DB::transaction(function () use ($dokId, $aksi, $catatan, $ubahJson) {
            DB::table('dok_dokumen')->where('dok_id', $dokId)->lockForUpdate()->first();
            [, $json] = self::baca($dokId);
            if ($ubahJson) {
                $json = $ubahJson($json);
            }
            $json['riwayat'][] = ['waktu' => now()->format('Y-m-d H:i:s'), 'oleh' => self::namaPengguna(), 'aksi' => $aksi, 'catatan' => $catatan];
            self::tulisJson($dokId, $json);
            DB::table('dok_dokumen')->where('dok_id', $dokId)->update(['updated_by' => self::namaPengguna(), 'updated_at' => now()]);
        });
    }

    public static function periksaInduk(?int $dokId, ?string $jenisKode, $indukId): void
    {
        if (! $indukId) {
            return;
        }
        $indukId = (int) $indukId;
        if ($dokId !== null && $indukId === $dokId) {
            throw new \DomainException('Dokumen tidak boleh menjadi induk dirinya sendiri.');
        }
        $induk = DB::table('dok_dokumen')->where('dok_id', $indukId)->first(['jenis_kode']);
        if (! $induk) {
            throw new \DomainException('Dokumen induk tidak ditemukan.');
        }
        $jenis = self::jenis();
        $tIni = (int) ($jenis[$jenisKode]->tingkat ?? 9);
        $tInduk = (int) ($jenis[$induk->jenis_kode]->tingkat ?? 9);
        if ($tInduk > $tIni) {
            throw new \DomainException('Induk harus setingkat atau lebih tinggi: ' . ($jenis[$induk->jenis_kode]->nama ?? $induk->jenis_kode)
                . ' tidak bisa menjadi induk ' . ($jenis[$jenisKode]->nama ?? $jenisKode) . '.');
        }
        // cegah lingkaran: induk tidak boleh keturunan dokumen ini
        if ($dokId !== null && in_array($dokId, self::jalurInduk($indukId), true)) {
            throw new \DomainException('Induk yang dipilih adalah turunan dokumen ini (lingkaran).');
        }
    }

    /** @return int[] id dari induk langsung sampai akar */
    public static function jalurInduk(int $dokId): array
    {
        $jalur = [];
        $id = $dokId;
        for ($i = 0; $i < 20 && $id; $i++) {
            $jalur[] = $id;
            $id = (int) DB::table('dok_dokumen')->where('dok_id', $id)->value('induk_id');
        }

        return $jalur;
    }

    // ─── Penomoran (Pedoman BAB IV.C) ─────────────────────────────────────────

    /**
     * Ambil nomor resmi untuk dokumen yang belum bernomor. Penghitung dikunci
     * SELECT … FOR UPDATE sehingga dua pengambilan bersamaan tidak mendapat nomor sama.
     */
    public static function ambilNomor(int $dokId): string
    {
        return DB::transaction(function () use ($dokId) {
            $dok = DB::table('dok_dokumen')->where('dok_id', $dokId)->lockForUpdate()->first();
            if (! $dok) {
                throw new \DomainException('Dokumen tidak ditemukan.');
            }
            if (filled($dok->dok_no)) {
                return $dok->dok_no;
            }
            $jenis = self::jenis()[$dok->jenis_kode] ?? null;
            if (! $jenis || blank($jenis->pola_nomor) || blank($jenis->seri)) {
                throw new \DomainException(($jenis->nama ?? $dok->jenis_kode)
                    . ' tidak memakai nomor registrasi sendiri (nomor diisi manual / mengikuti dokumen induk).');
            }
            $tgl = $dok->tgl_terbit ? Carbon::parse($dok->tgl_terbit) : now();
            $unit = $jenis->pakai_unit === 'Y' ? (string) ($dok->unit_kode ?? '') : '';
            $tahun = $jenis->reset_tahunan === 'Y' ? (int) $tgl->year : 0;
            $no = self::naikkanPenghitung($jenis->seri, $unit !== '' ? $unit : '-', $tahun);
            $nomor = self::formatNomor($jenis->pola_nomor, $no, (string) $jenis->kode_surat, $unit, $tgl);

            DB::table('dok_dokumen')->where('dok_id', $dokId)->update([
                'dok_no' => $nomor, 'dok_no_urut' => $no,
                'tgl_terbit' => $dok->tgl_terbit ?: $tgl->startOfDay(),
            ]);
            self::catat($dokId, 'NOMOR', "Nomor registrasi {$nomor}");

            return $nomor;
        });
    }

    private static function naikkanPenghitung(string $seri, string $unit, int $tahun): int
    {
        $kunci = ['seri' => $seri, 'unit_kode' => $unit, 'tahun' => $tahun];
        $baris = DB::table('dok_penomoran')->where($kunci)->lockForUpdate()->first();
        if (! $baris) {
            try {
                DB::table('dok_penomoran')->insert([...$kunci, 'no_terakhir' => 0]);
            } catch (QueryException $e) {
                if (! str_contains($e->getMessage(), 'ORA-00001')) {
                    throw $e;
                }
            }
            $baris = DB::table('dok_penomoran')->where($kunci)->lockForUpdate()->first();
        }
        $no = (int) $baris->no_terakhir + 1;
        DB::table('dok_penomoran')->where($kunci)->update(['no_terakhir' => $no]);

        return $no;
    }

    /** {NNN}/{KODE}{UNIT}/{RS}/{MM}/{YYYY} → 382/SPO/RSUI-MDN/01/2026 */
    public static function formatNomor(string $pola, int $no, string $kodeSurat, string $unit, Carbon $tgl): string
    {
        return strtr($pola, [
            '{NNN}' => str_pad((string) $no, 3, '0', STR_PAD_LEFT),
            '{KODE}' => $kodeSurat,
            '{UNIT}' => $unit,
            '{RS}' => (string) config('dokumen.kode_rs'),
            '{MM}' => $tgl->format('m'),
            '{YYYY}' => $tgl->format('Y'),
        ]);
    }

    /** Contoh nomor untuk pratinjau di master jenis. */
    public static function contohNomor(object $jenis): string
    {
        if (blank($jenis->pola_nomor)) {
            return '— manual / ikut induk —';
        }

        return self::formatNomor($jenis->pola_nomor, 1, (string) $jenis->kode_surat,
            $jenis->pakai_unit === 'Y' ? 'kep' : '', now());
    }

    /**
     * Setel penghitung dari nomor yang sudah tercatat (sesudah impor/isian manual) supaya nomor
     * berikutnya tidak bertabrakan. Tahun & bulan dibaca dari NOMORNYA sendiri (…/MM/YYYY), bukan
     * tgl_terbit (banyak dokumen impor tanpa tanggal).
     *  - deret direset tahunan (SK, Und, BA): nomor urut tertinggi per tahun — aman dari tabrakan;
     *  - deret menerus (SPO): TIDAK disetel otomatis. Data RS memuat banyak skema sekaligus
     *    (/SPO/, /SPO.Ak/, /SOP.Ak/, 515/12/2024 di samping 383/01/2026), sehingga "nomor berikutnya"
     *    tidak bisa disimpulkan dengan aman. Hanya dilaporkan sebagai usulan; nilai ditetapkan
     *    Sekretariat lewat halaman Pengaturan Penomoran (Pedoman hal. 66: buku catatan nomor SPO).
     * Penghitung hanya dinaikkan, tidak pernah diturunkan.
     */
    public static function sinkronPenghitung(): array
    {
        $hasil = [];
        foreach (self::jenis() as $jenis) {
            if (blank($jenis->seri) || blank($jenis->pola_nomor)) {
                continue;
            }
            $kelompok = [];
            $baris = DB::table('dok_dokumen')->where('jenis_kode', $jenis->kode)
                ->whereNotNull('dok_no')->whereNotNull('dok_no_urut')->get(['dok_no', 'dok_no_urut', 'unit_kode']);
            foreach ($baris as $b) {
                if (! preg_match('#/(\d{1,2}|[IVX]{1,4})/(20\d\d)\s*$#', $b->dok_no, $m)) {
                    continue;
                }
                $bulan = ctype_digit($m[1]) ? (int) $m[1] : (array_search($m[1], ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'], true) ?: 0);
                $tahunNo = (int) $m[2];
                $unit = $jenis->pakai_unit === 'Y' ? ($b->unit_kode ?: '-') : '-';
                $tahun = $jenis->reset_tahunan === 'Y' ? $tahunNo : 0;
                $urutan = (int) $b->dok_no_urut;
                $k = "{$unit}|{$tahun}";
                if (! isset($kelompok[$k]) || $urutan > $kelompok[$k][0]) {
                    $kelompok[$k] = [$urutan, (int) $b->dok_no_urut, $unit, $tahun];
                }
            }
            foreach ($kelompok as [, $no, $unit, $tahun]) {
                if ($jenis->reset_tahunan !== 'Y') {
                    $kini = (int) DB::table('dok_penomoran')->where(['seri' => $jenis->seri, 'unit_kode' => $unit, 'tahun' => 0])->value('no_terakhir');
                    $hasil[] = "{$jenis->seri}: usulan — nomor tertinggi tercatat {$no}, penghitung tetap {$kini} (deret menerus diatur Sekretariat)";
                    continue;
                }
                $kunci = ['seri' => $jenis->seri, 'unit_kode' => $unit, 'tahun' => $tahun];
                $kini = (int) DB::table('dok_penomoran')->where($kunci)->value('no_terakhir');
                if ($no > $kini) {
                    DB::table('dok_penomoran')->updateOrInsert($kunci, ['no_terakhir' => $no]);
                    $hasil[] = "{$jenis->seri}" . ($tahun ? " {$tahun}" : '') . ": {$kini} → {$no}";
                }
            }
        }

        return $hasil;
    }

    // ─── Status & revisi ──────────────────────────────────────────────────────

    public static function ubahStatus(int $dokId, string $status, ?int $penggantiId = null, string $catatan = ''): void
    {
        if (! array_key_exists($status, self::STATUS)) {
            throw new \DomainException('Status tidak dikenal.');
        }
        $data = ['status' => $status, 'pengganti_id' => $status === 'TIDAK_BERLAKU' ? $penggantiId : null];
        if ($status === 'BERLAKU' && config('dokumen.kaji_ulang_tahun')) {
            $dok = DB::table('dok_dokumen')->where('dok_id', $dokId)->first(['tgl_terbit', 'tgl_kaji_ulang']);
            if (! $dok?->tgl_kaji_ulang) {
                $data['tgl_kaji_ulang'] = Carbon::parse($dok->tgl_terbit ?? now())->addYears((int) config('dokumen.kaji_ulang_tahun'));
            }
        }
        DB::table('dok_dokumen')->where('dok_id', $dokId)->update($data);
        self::catat($dokId, 'STATUS', self::STATUS[$status] . ($catatan !== '' ? " — {$catatan}" : ''));
    }

    /** Naikkan nomor revisi (maks. Revisi 04 — Pedoman hal. 71). */
    public static function naikkanRevisi(int $dokId, string $alasan): int
    {
        $rev = (int) DB::table('dok_dokumen')->where('dok_id', $dokId)->value('no_revisi');
        if ($rev >= (int) config('dokumen.revisi_maks')) {
            throw new \DomainException('Sudah Revisi 0' . $rev . ' — revisi berikutnya wajib dituangkan dalam dokumen baru (Pedoman hal. 71).');
        }
        DB::table('dok_dokumen')->where('dok_id', $dokId)->update(['no_revisi' => $rev + 1, 'tgl_terbit' => now()->startOfDay()]);
        self::catat($dokId, 'REVISI', 'Revisi ' . ($rev + 1) . ($alasan !== '' ? " — {$alasan}" : ''));

        return $rev + 1;
    }

    // ─── Berkas ───────────────────────────────────────────────────────────────

    /** Path absolut berkas; null bila tidak ada / di luar akar yang diizinkan. */
    public static function pathBerkas(array $berkas): ?string
    {
        if (($berkas['lokasi'] ?? '') === 'impor') {
            $akar = realpath((string) config('dokumen.arsip_impor'));
            $path = $akar ? realpath($akar . '/' . ltrim((string) ($berkas['path'] ?? ''), '/')) : false;

            return ($path && str_starts_with($path, $akar . DIRECTORY_SEPARATOR) && is_file($path)) ? $path : null;
        }
        $rel = (string) ($berkas['path'] ?? '');

        return $rel !== '' && Storage::disk('local')->exists($rel) ? Storage::disk('local')->path($rel) : null;
    }

    // ─── Bantu ────────────────────────────────────────────────────────────────

    /** Padanan aman Latin-1 untuk kolom VARCHAR2 (charset basis data bisa WE8ISO8859P1). */
    public static function latin1(string $teks): string
    {
        $teks = strtr($teks, ['—' => '-', '–' => '-', '≤' => '<=', '≥' => '>=', '“' => '"', '”' => '"',
            '‘' => "'", '’' => "'", '…' => '...', '•' => '-', '≠' => '!=', '±' => '+/-']);

        return preg_replace('/[^\x{0000}-\x{00FF}]/u', '?', $teks);
    }

    /** Judul untuk tampilan/cetak: judul asli (JSON) bila ada. */
    public static function judul(object $row, array $json): string
    {
        return (string) ($json['judulAsli'] ?? $row->dok_judul);
    }

    public static function namaPengguna(): string
    {
        return (string) (auth()->user()?->name ?? 'sistem');
    }

    public static function tanggalIndonesia($tgl): string
    {
        if (! $tgl) {
            return '';
        }
        $c = Carbon::parse($tgl);

        return $c->day . ' ' . self::BULAN[$c->month] . ' ' . $c->year;
    }

    public static function bolehUbah(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['admin', 'manager']);
    }
}
