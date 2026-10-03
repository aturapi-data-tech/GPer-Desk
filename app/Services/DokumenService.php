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
        'DIAJUKAN'      => 'Diajukan (menunggu TTD)',
        'BERLAKU'       => 'Berlaku',
        'TIDAK_BERLAKU' => 'Tidak Berlaku',
        'DICABUT'       => 'Dicabut',
    ];

    public const STATUS_BADGE = [
        'DRAF' => 'gray', 'DIAJUKAN' => 'info', 'BERLAKU' => 'success', 'TIDAK_BERLAKU' => 'warning', 'DICABUT' => 'danger',
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
        self::indeksAkreditasi($dokId, $json['akreditasi'] ?? []);
    }

    /** Salin DOK_JSON.akreditasi ke DOK_AKREDITASI (pohon akreditasi registri). Dilewati bila tabel belum dibuat. */
    public static function indeksAkreditasi(int $dokId, array $akreditasi): void
    {
        static $ada = null;
        $ada ??= \Illuminate\Support\Facades\Schema::hasTable('dok_akreditasi');
        if (! $ada) {
            return;
        }
        DB::table('dok_akreditasi')->where('dok_id', $dokId)->delete();
        $baris = [];
        foreach ($akreditasi as $a) {
            $bab = trim((string) ($a['bab'] ?? ''));
            $std = trim((string) ($a['standar'] ?? ''));
            $ep = trim((string) ($a['ep'] ?? ''));
            if ($bab === '' || $std === '' || $ep === '') {
                continue;
            }
            $baris["{$bab}|{$std}|{$ep}"] = ['dok_id' => $dokId, 'bab' => substr($bab, 0, 10), 'standar' => substr($std, 0, 20),
                'ep' => substr($ep, 0, 10), 'elemen' => substr((string) ($a['elemen'] ?? ''), 0, 20) ?: null];
        }
        if ($baris) {
            DB::table('dok_akreditasi')->insert(array_values($baris));
        }
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

    /** Model penomoran per jenis (DOK_JENIS.MODEL_NOMOR) — acuan saat membuat dokumen baru. */
    public const MODEL_NOMOR = [
        'SENDIRI' => 'Deret sendiri',
        'INDUK'   => 'Ikut nomor induk',
        'TURUNAN' => 'Turunan induk',
        'TANPA'   => 'Tanpa nomor',
    ];

    /** Jenis ini dinomori aplikasi (bukan diisi manual / tanpa nomor)? */
    public static function bisaDinomori(?object $jenis): bool
    {
        return $jenis && ($jenis->model_nomor ?? 'TANPA') !== 'TANPA' && filled($jenis->pola_nomor)
            && ($jenis->model_nomor !== 'SENDIRI' || filled($jenis->seri));
    }

    /**
     * Ambil nomor resmi untuk dokumen yang belum bernomor, menurut model penomoran jenisnya:
     *  - SENDIRI: deret sendiri; penghitung dikunci SELECT … FOR UPDATE sehingga dua pengambilan
     *    bersamaan tidak mendapat nomor sama.
     *  - INDUK:   nomor SK/Perdir penetapnya (Pedoman/Panduan/Program = lampiran SK).
     *  - TURUNAN: nomor induk + akhiran urut per induk ({INDUK}-F{NN}); baris induk dikunci.
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
            if (! self::bisaDinomori($jenis)) {
                throw new \DomainException(($jenis->nama ?? $dok->jenis_kode)
                    . ' tidak dinomori aplikasi (regulasi eksternal memakai nomor aslinya; bukti/rekaman tanpa nomor).');
            }

            if ($jenis->model_nomor === 'SENDIRI') {
                $tgl = $dok->tgl_terbit ? Carbon::parse($dok->tgl_terbit) : now();
                $unit = $jenis->pakai_unit === 'Y' ? (string) ($dok->unit_kode ?? '') : '';
                $tahun = $jenis->reset_tahunan === 'Y' ? (int) $tgl->year : 0;
                $no = self::naikkanPenghitung($jenis->seri, $unit !== '' ? $unit : '-', $tahun);
                $nomor = self::formatNomor($jenis->pola_nomor, $no, (string) $jenis->kode_surat, $unit, $tgl);
            } else {
                $induk = $dok->induk_id
                    ? DB::table('dok_dokumen')->where('dok_id', $dok->induk_id)->lockForUpdate()->first()
                    : null;
                if (! $induk || blank($induk->dok_no)) {
                    throw new \DomainException($jenis->nama . ' mengikuti nomor dokumen induknya — '
                        . ($induk ? 'induk belum bernomor, ambil nomor induknya dulu.' : 'tetapkan induknya dulu ('
                        . ($jenis->model_nomor === 'INDUK' ? 'SK / Peraturan Direktur penetapnya' : 'SPO / Pedoman / Panduan asalnya') . ').'));
                }
                $tgl = $dok->tgl_terbit ? Carbon::parse($dok->tgl_terbit)
                    : ($induk->tgl_terbit ? Carbon::parse($induk->tgl_terbit) : now());
                $no = 0;
                if ($jenis->model_nomor === 'TURUNAN') {
                    // urut per induk: akhiran tertinggi saudara yang sudah bernomor + 1
                    $awalan = self::formatNomor($jenis->pola_nomor, 0, (string) $jenis->kode_surat, '', $tgl, $induk->dok_no, null);
                    $no = 1 + (int) DB::table('dok_dokumen')->where('induk_id', $induk->dok_id)
                        ->where('jenis_kode', $dok->jenis_kode)->where('dok_no', 'like', $awalan . '%')->max('dok_no_urut');
                }
                $nomor = self::formatNomor($jenis->pola_nomor, $no, (string) $jenis->kode_surat, '', $tgl, $induk->dok_no);
            }

            DB::table('dok_dokumen')->where('dok_id', $dokId)->update([
                'dok_no' => $nomor, 'dok_no_urut' => $no ?: null,
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

    /**
     * {NNN}/{KODE}{UNIT}/{RS}/{MM}/{YYYY} → 382/SPO/RSUI-MDN/01/2026
     * {INDUK}-F{NN} → 382/SPO/RSUI-MDN/01/2026-F01. $nn = null → potong pola sebelum {NN} (awalan saudara).
     */
    public static function formatNomor(string $pola, int $no, string $kodeSurat, string $unit, Carbon $tgl,
        string $nomorInduk = '', ?int $nn = -1): string
    {
        if ($nn === null) {
            $pola = strstr($pola, '{NN}', true) ?: $pola;
        }

        return strtr($pola, [
            '{NNN}' => str_pad((string) $no, 3, '0', STR_PAD_LEFT),
            '{NN}' => str_pad((string) $no, 2, '0', STR_PAD_LEFT),
            '{INDUK}' => $nomorInduk,
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
        if (! self::bisaDinomori($jenis)) {
            return ($jenis->kode ?? '') === 'EKS' ? '— nomor asli peraturan —' : '— tanpa nomor —';
        }
        $contohSk = self::formatNomor('{NNN}/{KODE}/{RS}/{MM}/{YYYY}', 1, 'SK', '', now());
        $contohSpo = self::formatNomor('{NNN}/{KODE}/{RS}/{MM}/{YYYY}', 382, 'SPO', '', now());

        return match ($jenis->model_nomor) {
            'INDUK' => self::formatNomor($jenis->pola_nomor, 0, '', '', now(), $contohSk),
            'TURUNAN' => self::formatNomor($jenis->pola_nomor, 1, (string) $jenis->kode_surat, '', now(), $contohSpo),
            default => self::formatNomor($jenis->pola_nomor, 1, (string) $jenis->kode_surat,
                $jenis->pakai_unit === 'Y' ? 'kep' : '', now()),
        };
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
            if (($jenis->model_nomor ?? '') !== 'SENDIRI' || ! self::bisaDinomori($jenis)) {
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

    // ─── Tanda tangan & kunci (pola modul dokumen sirus) ─────────────────────
    //
    // Draf → (Ajukan) → DIAJUKAN → (petugas berwenang: TTD Saya) → terkunci, BERLAKU, bisa dilihat/dicetak
    // tanpa tanda air. Buka kunci hanya peran tertentu, wajib alasan, tercatat di riwayat.
    // Spesimen TTD = users.myuser_ttd_image (sama dengan sirus); hanya dibubuhkan oleh pemiliknya sendiri.

    public static function terkunci(array $json): bool
    {
        return filled($json['ttd']['kode'] ?? null);
    }

    public static function bolehTtd(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(config('dokumen.peran_ttd'));
    }

    public static function bolehBukaKunci(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(config('dokumen.peran_buka_kunci'));
    }

    /** Tolak perubahan isi pada dokumen yang sudah ditandatangani. */
    public static function pastikanTidakTerkunci(int $dokId): void
    {
        $data = self::baca($dokId);
        if ($data && self::terkunci($data[1])) {
            throw new \DomainException('Dokumen sudah ditandatangani dan terkunci — buka kunci dulu (wewenang khusus) untuk mengubah.');
        }
    }

    /** Event penyusun yang tepat: isi.naskah (format apa pun) → penyusun naskah; SPO tanpa naskah → penyusun SPO. */
    public static function penyusun(int $dokId): string
    {
        [$row, $json] = self::baca($dokId) ?? [null, []];
        if (filled($json['isi']['naskah'] ?? null)) {
            return 'dokumen.registri.openNaskah';
        }

        return \App\Support\TataNaskah::format($row?->jenis_kode) === 'spo' ? 'dokumen.registri.openSpo' : 'dokumen.registri.openNaskah';
    }

    public static function punyaIsi(array $json): bool
    {
        return filled($json['isi']['spo'] ?? null) || filled($json['isi']['naskah'] ?? null);
    }

    public static function ajukan(int $dokId, string $catatan = ''): void
    {
        [$row, $json] = self::baca($dokId) ?? throw new \DomainException('Dokumen tidak ditemukan.');
        if (self::terkunci($json)) {
            throw new \DomainException('Dokumen sudah ditandatangani.');
        }
        if (! self::punyaIsi($json)) {
            throw new \DomainException('Isi dokumen belum disusun — susun dulu sebelum diajukan.');
        }
        DB::table('dok_dokumen')->where('dok_id', $dokId)->update(['status' => 'DIAJUKAN']);
        self::catat($dokId, 'AJUKAN', $catatan !== '' ? $catatan : 'diajukan untuk ditandatangani', function ($json) use ($catatan) {
            $json['pengajuan'] = ['waktu' => now()->format('Y-m-d H:i:s'), 'oleh' => self::namaPengguna(), 'catatan' => $catatan];
            return $json;
        });
    }

    public static function tandatangani(int $dokId): array
    {
        if (! self::bolehTtd()) {
            throw new \DomainException('Anda tidak berwenang menandatangani dokumen.');
        }
        $u = auth()->user();
        $kode = (string) ($u->myuser_code ?? $u->id);
        if (! \App\Support\TtdUser::pathBerkasDariKode($kode)) {
            throw new \DomainException('Spesimen tanda tangan Anda belum ada — simpan dulu di menu "Tanda Tangan Saya".');
        }

        return DB::transaction(function () use ($dokId, $u, $kode) {
            DB::table('dok_dokumen')->where('dok_id', $dokId)->lockForUpdate()->first();
            [$row, $json] = self::baca($dokId) ?? throw new \DomainException('Dokumen tidak ditemukan.');
            if (self::terkunci($json)) {
                throw new \DomainException('Dokumen sudah ditandatangani ' . ($json['ttd']['nama'] ?? '') . '.');
            }
            if (! self::punyaIsi($json)) {
                throw new \DomainException('Isi dokumen belum disusun.');
            }
            $jenis = self::jenis()[$row->jenis_kode] ?? null;
            $nomor = $row->dok_no;
            // model induk/turunan hanya bila induknya sudah bernomor — jangan menggagalkan tanda tangan
            $indukBernomor = $row->induk_id && filled(DB::table('dok_dokumen')->where('dok_id', $row->induk_id)->value('dok_no'));
            if (blank($nomor) && self::bisaDinomori($jenis) && ($jenis->model_nomor === 'SENDIRI' || $indukBernomor)) {
                $nomor = self::ambilNomor($dokId);          // nomor resmi terbit saat ditetapkan
            }
            $waktu = now();
            $ttd = [
                'nama' => (string) $u->name,
                'jabatan' => (string) ($u->jabatan ?: ($json['isi']['naskah']['kaki']['jabatan'] ?? '')),
                'kode' => $kode,
                'waktu' => $waktu->format('Y-m-d H:i:s'),
                'verifikasi' => strtoupper(substr(sha1($dokId . '|' . $kode . '|' . $waktu->timestamp . '|' . $nomor . '|' . config('app.key')), 0, 12)),
            ];
            DB::table('dok_dokumen')->where('dok_id', $dokId)->update([
                'status' => 'BERLAKU',
                'tgl_terbit' => $row->tgl_terbit ?: $waktu->copy()->startOfDay(),
            ]);
            self::catat($dokId, 'TTD', "ditandatangani {$ttd['nama']} — kode verifikasi {$ttd['verifikasi']}", function ($json) use ($ttd) {
                $json['ttd'] = $ttd;
                return $json;
            });

            return $ttd;
        });
    }

    public static function bukaKunci(int $dokId, string $alasan): void
    {
        if (! self::bolehBukaKunci()) {
            throw new \DomainException('Buka kunci hanya untuk peran berwenang.');
        }
        if (trim($alasan) === '') {
            throw new \DomainException('Alasan buka kunci wajib diisi.');
        }
        DB::table('dok_dokumen')->where('dok_id', $dokId)->update(['status' => 'DRAF']);
        self::catat($dokId, 'BUKA_KUNCI', $alasan, function ($json) {
            $json['ttdLama'][] = ($json['ttd'] ?? []) + ['dibukaPada' => now()->format('Y-m-d H:i:s'), 'dibukaOleh' => self::namaPengguna()];
            unset($json['ttd']);
            return $json;
        });
    }

    /** Gambar TTD sebagai data URI (DomPDF/PhpWord) dari kode penanda-tangan; null bila tidak ada. */
    public static function gambarTtd(?string $kode): ?string
    {
        $path = \App\Support\TtdUser::pathBerkasDariKode($kode);

        return $path ? $path : null;
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
