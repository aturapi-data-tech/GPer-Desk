-- ============================================================
-- SIGAP — Registri Dokumen Regulasi & Akreditasi
-- Dasar: Pedoman Tata Naskah Dinas RSUI Madinah (Perdir 354/SK/RSI-MDN/08/2024)
--   BAB III jenis naskah · BAB IV.C kodifikasi (hal. 64–66) · BAB V tingkatan regulasi
--   (hal. 70–71) · BAB VI klasifikasi keamanan · BAB VII kewenangan tanda tangan.
--
-- Objek:
--   DOK_JENIS        master jenis naskah: tingkat, kode surat, pola nomor, seri penomoran
--   DOK_UNIT         singkatan bagian/komite/tim (hal. 65) untuk kode klasifikasi nomor
--   DOK_PENOMORAN    penghitung nomor urut per seri (+unit, +tahun bila reset tahunan)
--   DOK_DOKUMEN      header dokumen (kolom kunci untuk cari/filter) + DOK_JSON (CLOB)
--                    berisi isi, akreditasi (bab/standar/EP), berkas, penandatangan, riwayat
--                    — pola satu dokumen JSON per baris seperti EMR sirus (datadaftarri_json).
--
-- Tanpa nama pemilik: jalankan sebagai user aplikasi (GPER di server, GPERDESK di lokal).
-- Aman dijalankan ulang (drop dulu → create lagi). Tiap blok PL/SQL diakhiri "/".
-- ============================================================

SET DEFINE OFF

-- 1) DROP objek lama (diabaikan bila belum ada)
BEGIN EXECUTE IMMEDIATE 'DROP TABLE DOK_DOKUMEN CASCADE CONSTRAINTS PURGE';
EXCEPTION WHEN OTHERS THEN IF SQLCODE != -942 THEN RAISE; END IF; END;
/
BEGIN EXECUTE IMMEDIATE 'DROP TABLE DOK_PENOMORAN CASCADE CONSTRAINTS PURGE';
EXCEPTION WHEN OTHERS THEN IF SQLCODE != -942 THEN RAISE; END IF; END;
/
BEGIN EXECUTE IMMEDIATE 'DROP TABLE DOK_UNIT CASCADE CONSTRAINTS PURGE';
EXCEPTION WHEN OTHERS THEN IF SQLCODE != -942 THEN RAISE; END IF; END;
/
BEGIN EXECUTE IMMEDIATE 'DROP TABLE DOK_JENIS CASCADE CONSTRAINTS PURGE';
EXCEPTION WHEN OTHERS THEN IF SQLCODE != -942 THEN RAISE; END IF; END;
/
BEGIN EXECUTE IMMEDIATE 'DROP SEQUENCE DOK_DOKUMEN_SEQ';
EXCEPTION WHEN OTHERS THEN IF SQLCODE != -2289 THEN RAISE; END IF; END;
/

-- 2) MASTER JENIS NASKAH
CREATE TABLE DOK_JENIS (
    KODE           VARCHAR2(10)  NOT NULL,     -- PERDIR, SK, SPO, PED, ...
    NAMA           VARCHAR2(100) NOT NULL,
    TINGKAT        NUMBER(1)     NOT NULL,     -- 0 eksternal · 1 peraturan/keputusan · 2 pedoman/panduan/program · 3 SPO · 4 formulir · 5 bukti
    KELOMPOK       VARCHAR2(20)  NOT NULL,     -- REGULASI | PELAKSANAAN | KORESPONDENSI | BUKTI | EKSTERNAL
    KODE_SURAT     VARCHAR2(10),               -- kode jenis pada nomor: SK, SPO, Und, BA ...
    SERI           VARCHAR2(10),               -- jenis yang berbagi deret nomor (PERDIR & SK → 'SK')
    POLA_NOMOR     VARCHAR2(100),              -- {NNN}/{KODE}{UNIT}/{RS}/{MM}/{YYYY}; null = nomor manual / ikut induk
    RESET_TAHUNAN  CHAR(1)       DEFAULT 'N' NOT NULL,
    PAKAI_UNIT     CHAR(1)       DEFAULT 'N' NOT NULL,  -- sisipkan singkatan bagian setelah kode (SPOkep)
    DISAHKAN_OLEH  VARCHAR2(100),              -- hal. 71/73
    BISA_DISUSUN   CHAR(1)       DEFAULT 'N' NOT NULL,  -- punya penyusun isi terstruktur di aplikasi
    URUT           NUMBER(3)     DEFAULT 0   NOT NULL,
    AKTIF          CHAR(1)       DEFAULT 'Y' NOT NULL,
    CONSTRAINT dok_jenis_pk PRIMARY KEY (KODE)
);

-- 3) SINGKATAN BAGIAN (Pedoman hal. 65)
CREATE TABLE DOK_UNIT (
    KODE   VARCHAR2(10)  NOT NULL,
    NAMA   VARCHAR2(150) NOT NULL,
    AKTIF  CHAR(1) DEFAULT 'Y' NOT NULL,
    CONSTRAINT dok_unit_pk PRIMARY KEY (KODE)
);

-- 4) PENGHITUNG NOMOR — dikunci SELECT ... FOR UPDATE di DokumenService::ambilNomor()
--    (bukan count+1 seperti nomor tiket, supaya aman bila dua orang mengambil bersamaan)
CREATE TABLE DOK_PENOMORAN (
    SERI         VARCHAR2(10) NOT NULL,
    UNIT_KODE    VARCHAR2(10) DEFAULT '-' NOT NULL,
    TAHUN        NUMBER(4)    DEFAULT 0   NOT NULL,  -- 0 = deret tidak direset tiap tahun
    NO_TERAKHIR  NUMBER(6)    DEFAULT 0   NOT NULL,
    CONSTRAINT dok_penomoran_pk PRIMARY KEY (SERI, UNIT_KODE, TAHUN)
);

-- 5) REGISTRI DOKUMEN
CREATE TABLE DOK_DOKUMEN (
    DOK_ID          NUMBER(12)     NOT NULL,
    JENIS_KODE      VARCHAR2(10)   NOT NULL,
    INDUK_ID        NUMBER(12),                 -- dokumen di atasnya (SPO → Pedoman → Peraturan)
    DOK_NO          VARCHAR2(150),              -- nomor resmi lengkap
    DOK_NO_URUT     NUMBER(6),                  -- nomor urut registrasi (untuk sinkron penghitung)
    DOK_JUDUL       VARCHAR2(1000) NOT NULL,
    UNIT_KODE       VARCHAR2(10),
    TGL_TERBIT      DATE,
    TGL_KAJI_ULANG  DATE,                       -- jatuh tempo review (BAB VIII.J)
    NO_REVISI       NUMBER(2)      DEFAULT 0      NOT NULL,   -- maks. 4 (hal. 71)
    STATUS          VARCHAR2(20)   DEFAULT 'DRAF' NOT NULL,   -- DRAF | BERLAKU | TIDAK_BERLAKU | DICABUT
    KLASIFIKASI     VARCHAR2(15)   DEFAULT 'BIASA' NOT NULL,  -- BIASA | TERBATAS | RAHASIA | SANGAT_RAHASIA
    SUMBER          VARCHAR2(15)   DEFAULT 'INTERNAL' NOT NULL, -- INTERNAL | EKSTERNAL | IMPOR
    POKJA           VARCHAR2(200),              -- daftar bab akreditasi, dipisah koma (cari cepat); rincian EP di JSON
    PENGGANTI_ID    NUMBER(12),                 -- dokumen yang menggantikan (bila TIDAK_BERLAKU)
    KUNCI_IMPOR     VARCHAR2(300),              -- nama|ukuran berkas asal impor — mencegah impor ganda
    DOK_JSON        CLOB,
    CREATED_BY      VARCHAR2(150),
    UPDATED_BY      VARCHAR2(150),
    CREATED_AT      TIMESTAMP,
    UPDATED_AT      TIMESTAMP,
    CONSTRAINT dok_dokumen_pk     PRIMARY KEY (DOK_ID),
    CONSTRAINT dok_dokumen_jns_fk FOREIGN KEY (JENIS_KODE) REFERENCES DOK_JENIS (KODE),
    CONSTRAINT dok_dokumen_ind_fk FOREIGN KEY (INDUK_ID) REFERENCES DOK_DOKUMEN (DOK_ID),
    CONSTRAINT dok_dokumen_pgt_fk FOREIGN KEY (PENGGANTI_ID) REFERENCES DOK_DOKUMEN (DOK_ID),
    CONSTRAINT dok_dokumen_sts_ck CHECK (STATUS IN ('DRAF','BERLAKU','TIDAK_BERLAKU','DICABUT')),
    CONSTRAINT dok_dokumen_rev_ck CHECK (NO_REVISI BETWEEN 0 AND 4)
);

CREATE INDEX dok_dokumen_jenis_idx  ON DOK_DOKUMEN (JENIS_KODE);
CREATE INDEX dok_dokumen_induk_idx  ON DOK_DOKUMEN (INDUK_ID);
CREATE INDEX dok_dokumen_status_idx ON DOK_DOKUMEN (STATUS);
CREATE INDEX dok_dokumen_no_idx     ON DOK_DOKUMEN (DOK_NO);
CREATE INDEX dok_dokumen_impor_idx  ON DOK_DOKUMEN (KUNCI_IMPOR);

CREATE SEQUENCE DOK_DOKUMEN_SEQ MINVALUE 1 START WITH 1 INCREMENT BY 1 NOCACHE;

CREATE OR REPLACE TRIGGER DOK_DOKUMEN_ID_TR
BEFORE INSERT ON DOK_DOKUMEN
FOR EACH ROW
BEGIN
    IF :new.DOK_ID IS NULL THEN
        SELECT DOK_DOKUMEN_SEQ.NEXTVAL INTO :new.DOK_ID FROM dual;
    END IF;
END;
/

-- 6) DATA AWAL — jenis naskah
--    Pola nomor mengikuti praktik yang sudah terbit (SPO 370–383: NNN/SPO/RSUI-MDN/MM/YYYY,
--    bulan dua angka, deret SPO tidak direset tahunan; SK direset tiap tahun).
--    {RS} diambil dari config('dokumen.kode_rs') — bawaan RSUI-MDN, bisa diganti di .env.
INSERT INTO DOK_JENIS VALUES ('EKS',   'Regulasi Eksternal (Perundang-undangan)', 0, 'EKSTERNAL',     NULL,  NULL,  NULL,                            'N','N', NULL,       'N',  1,'Y');
INSERT INTO DOK_JENIS VALUES ('PERDIR','Peraturan Direktur',                      1, 'REGULASI',      'SK',  'SK',  '{NNN}/{KODE}/{RS}/{MM}/{YYYY}',   'Y','N','Direktur',  'Y', 10,'Y');
INSERT INTO DOK_JENIS VALUES ('SK',    'Keputusan Direktur',                      1, 'REGULASI',      'SK',  'SK',  '{NNN}/{KODE}/{RS}/{MM}/{YYYY}',   'Y','N','Direktur',  'Y', 11,'Y');
INSERT INTO DOK_JENIS VALUES ('KEB',   'Kebijakan',                               1, 'REGULASI',      'SK',  'SK',  '{NNN}/{KODE}/{RS}/{MM}/{YYYY}',   'Y','N','Direktur',  'Y', 12,'Y');
INSERT INTO DOK_JENIS VALUES ('PED',   'Pedoman',                                 2, 'REGULASI',      NULL,  NULL,  NULL,                            'N','N','Direktur',  'Y', 20,'Y');
INSERT INTO DOK_JENIS VALUES ('PAN',   'Panduan',                                 2, 'REGULASI',      NULL,  NULL,  NULL,                            'N','N','Direktur',  'Y', 21,'Y');
INSERT INTO DOK_JENIS VALUES ('PROG',  'Program Kerja',                           2, 'REGULASI',      NULL,  NULL,  NULL,                            'N','N','Direktur',  'Y', 22,'Y');
INSERT INTO DOK_JENIS VALUES ('SPO',   'Standar Prosedur Operasional',            3, 'REGULASI',      'SPO', 'SPO', '{NNN}/{KODE}{UNIT}/{RS}/{MM}/{YYYY}','N','N','Direktur','Y', 30,'Y');
INSERT INTO DOK_JENIS VALUES ('SE',    'Surat Edaran',                            3, 'REGULASI',      'SE',  'SE',  '{NNN}/{KODE}/{RS}/{MM}/{YYYY}',   'Y','N','Direktur',  'Y', 31,'Y');
INSERT INTO DOK_JENIS VALUES ('FORM',  'Formulir',                                4, 'PELAKSANAAN',   NULL,  NULL,  NULL,                            'N','N', NULL,       'N', 40,'Y');
INSERT INTO DOK_JENIS VALUES ('UND',   'Surat Undangan',                          5, 'KORESPONDENSI', 'Und', 'UND', '{NNN}/{KODE}/{RS}/{MM}/{YYYY}',   'Y','N', NULL,       'N', 50,'Y');
INSERT INTO DOK_JENIS VALUES ('STGS',  'Surat Tugas',                             5, 'KORESPONDENSI', 'STgs','STGS','{NNN}/{KODE}/{RS}/{MM}/{YYYY}',   'Y','N','Direktur',  'N', 51,'Y');
INSERT INTO DOK_JENIS VALUES ('SKET',  'Surat Keterangan',                        5, 'KORESPONDENSI', 'SKet','SKET','{NNN}/{KODE}/{RS}/{MM}/{YYYY}',   'Y','N','Direktur',  'N', 52,'Y');
INSERT INTO DOK_JENIS VALUES ('BA',    'Berita Acara',                            5, 'KORESPONDENSI', 'BA',  'BA',  '{NNN}/{KODE}/{RS}/{MM}/{YYYY}',   'Y','N', NULL,       'N', 53,'Y');
INSERT INTO DOK_JENIS VALUES ('LAP',   'Laporan',                                 5, 'BUKTI',         NULL,  NULL,  NULL,                            'N','N', NULL,       'N', 60,'Y');
INSERT INTO DOK_JENIS VALUES ('NOT',   'Notulen',                                 5, 'BUKTI',         NULL,  NULL,  NULL,                            'N','N', NULL,       'N', 61,'Y');
INSERT INTO DOK_JENIS VALUES ('DH',    'Daftar Hadir',                            5, 'BUKTI',         NULL,  NULL,  NULL,                            'N','N', NULL,       'N', 62,'Y');
INSERT INTO DOK_JENIS VALUES ('SERT',  'Sertifikat / Kredensial',                 5, 'BUKTI',         NULL,  NULL,  NULL,                            'N','N', NULL,       'N', 63,'Y');
INSERT INTO DOK_JENIS VALUES ('BUKTI', 'Bukti Lain (rekaman, dokumentasi, isian)', 5, 'BUKTI',        NULL,  NULL,  NULL,                            'N','N', NULL,       'N', 69,'Y');

-- 7) DATA AWAL — singkatan bagian (Pedoman hal. 65)
INSERT INTO DOK_UNIT (KODE, NAMA) VALUES ('adm',   'Administrasi Umum');
INSERT INTO DOK_UNIT (KODE, NAMA) VALUES ('keu',   'Keuangan');
INSERT INTO DOK_UNIT (KODE, NAMA) VALUES ('sdm',   'Pengembangan Sumber Daya Manusia');
INSERT INTO DOK_UNIT (KODE, NAMA) VALUES ('kep',   'Keperawatan');
INSERT INTO DOK_UNIT (KODE, NAMA) VALUES ('jmd',   'Penunjang Medis');
INSERT INTO DOK_UNIT (KODE, NAMA) VALUES ('um',    'Penunjang Umum');
INSERT INTO DOK_UNIT (KODE, NAMA) VALUES ('med',   'Pelayanan Medis');
INSERT INTO DOK_UNIT (KODE, NAMA) VALUES ('spi',   'Satuan Pemeriksa Internal');
INSERT INTO DOK_UNIT (KODE, NAMA) VALUES ('mtu',   'Komite Mutu');
INSERT INTO DOK_UNIT (KODE, NAMA) VALUES ('kdik',  'Komite Medik');
INSERT INTO DOK_UNIT (KODE, NAMA) VALUES ('kkep',  'Komite Keperawatan');
INSERT INTO DOK_UNIT (KODE, NAMA) VALUES ('kkes',  'Komite Tenaga Kesehatan Lain');
INSERT INTO DOK_UNIT (KODE, NAMA) VALUES ('k3',    'Keselamatan & Kesehatan Kerja');
INSERT INTO DOK_UNIT (KODE, NAMA) VALUES ('ppi',   'Pencegahan dan Pengendalian Infeksi');
INSERT INTO DOK_UNIT (KODE, NAMA) VALUES ('pkrs',  'Promosi Kesehatan RS');
INSERT INTO DOK_UNIT (KODE, NAMA) VALUES ('tkrs',  'Tata Kelola RS');
INSERT INTO DOK_UNIT (KODE, NAMA) VALUES ('akred', 'Tim Akreditasi');
INSERT INTO DOK_UNIT (KODE, NAMA) VALUES ('pnas',  'Program Nasional');

-- 8) PENGHITUNG AWAL — keputusan yang sudah berlaku (aturan rumah akreditasi, 1 Sep 2026 & SPO 382/383):
--    deret SPO terakhir 383 (Januari 2026) → SPO baru berikutnya 384. Deret tahunan (SK dll.)
--    disetel dari data oleh `php artisan dokumen:impor-akreditasi` (nomor tertinggi per tahun).
INSERT INTO DOK_PENOMORAN (SERI, UNIT_KODE, TAHUN, NO_TERAKHIR) VALUES ('SPO', '-', 0, 383);

COMMIT;
