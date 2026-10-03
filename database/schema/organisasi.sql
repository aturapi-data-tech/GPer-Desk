-- ============================================================
-- SIGAP — Struktur Organisasi RSU Islam Madinah (model sederhana: pohon + kepala)
-- Dasar: Peraturan Direktur PT Al-Ikhlas Madinah Nomor 104/SK/PT-AIM/10/2025 tentang Struktur Organisasi
--        dan Tata Kerja RS Islam Madinah (Pasal 6, 16–32 + bagan Lampiran I) — akreditasi TKRS 3 EP a.
--        Pejabat: SK 096/SK/PT-AIM/7/2026 + SK komite, URGAS dan dokumen unit 2025–2026 di folder akreditasi
--        (ditelusuri 3 Okt 2026; konflik diputuskan user). Kosong = tidak ditemukan / belum dipastikan.
-- ORG_JENIS: master jenis unit (Direksi, Bagian, Instalasi, …) — RS lain cukup mengisi jenisnya sendiri.
-- ORG_UNIT: satu baris per satuan organisasi; INDUK_ID = atasan langsung (bagan Lampiran I).
--   Instalasi pelayanan (IGD … Jiwa) berinduk Bagian Pelayanan Medis; koordinasi keperawatan (Pasal 20) tidak dimodelkan.
--   Unit Penyelenggara SIMRS tidak tercantum di Perdir 104/2025 (dasar: Pedoman Pengorganisasian Unit SIMRS, DRAF).
-- Tanpa nama pemilik; aman dijalankan ulang (drop dulu → create lagi). Tiap blok PL/SQL diakhiri "/".
-- ============================================================
SET DEFINE OFF

BEGIN EXECUTE IMMEDIATE 'DROP TABLE ORG_KOORDINASI CASCADE CONSTRAINTS PURGE';
EXCEPTION WHEN OTHERS THEN IF SQLCODE != -942 THEN RAISE; END IF; END;
/
BEGIN EXECUTE IMMEDIATE 'DROP TABLE ORG_UNIT CASCADE CONSTRAINTS PURGE';
EXCEPTION WHEN OTHERS THEN IF SQLCODE != -942 THEN RAISE; END IF; END;
/
BEGIN EXECUTE IMMEDIATE 'DROP TABLE ORG_JENIS CASCADE CONSTRAINTS PURGE';
EXCEPTION WHEN OTHERS THEN IF SQLCODE != -942 THEN RAISE; END IF; END;
/
BEGIN EXECUTE IMMEDIATE 'DROP SEQUENCE ORG_UNIT_SEQ';
EXCEPTION WHEN OTHERS THEN IF SQLCODE != -2289 THEN RAISE; END IF; END;
/

CREATE TABLE ORG_JENIS (
    KODE         VARCHAR2(12)  NOT NULL,        -- dibentuk dari nama saat dibuat, tidak berubah
    NAMA         VARCHAR2(60)  NOT NULL,
    URUT         NUMBER(3)     DEFAULT 0 NOT NULL,   -- urutan tingkat (juga menentukan warna badge)
    CONSTRAINT org_jenis_pk PRIMARY KEY (KODE)
);
INSERT INTO ORG_JENIS (KODE, NAMA, URUT) VALUES ('DIREKSI', 'Direksi', 1);
INSERT INTO ORG_JENIS (KODE, NAMA, URUT) VALUES ('BAGIAN', 'Bagian', 2);
INSERT INTO ORG_JENIS (KODE, NAMA, URUT) VALUES ('INSTALASI', 'Instalasi', 3);
INSERT INTO ORG_JENIS (KODE, NAMA, URUT) VALUES ('UNIT', 'Unit', 4);
INSERT INTO ORG_JENIS (KODE, NAMA, URUT) VALUES ('KOMITE', 'Komite', 5);
INSERT INTO ORG_JENIS (KODE, NAMA, URUT) VALUES ('TIM', 'Tim', 6);

CREATE TABLE ORG_UNIT (
    UNIT_ID      NUMBER(10)    NOT NULL,
    INDUK_ID     NUMBER(10),                    -- atasan langsung; NULL = puncak
    NAMA         VARCHAR2(150) NOT NULL,
    JENIS        VARCHAR2(12)  NOT NULL,        -- ORG_JENIS.KODE
    NAMA_KEPALA  VARCHAR2(150),                 -- pejabat saat ini (NULL = belum dicatat)
    URUT         NUMBER(4)     DEFAULT 0 NOT NULL,   -- urutan tampil (tidak disunting; unit baru di akhir)
    ORG_JSON     CLOB,                          -- uraian tugas kepala (format URGAS RS) + job description staf — lihat docs/organisasi-modul.md
    CREATED_AT   DATE          DEFAULT SYSDATE,
    UPDATED_AT   DATE          DEFAULT SYSDATE,
    CONSTRAINT org_unit_pk PRIMARY KEY (UNIT_ID),
    CONSTRAINT org_unit_ind_fk FOREIGN KEY (INDUK_ID) REFERENCES ORG_UNIT (UNIT_ID),
    CONSTRAINT org_unit_jenis_fk FOREIGN KEY (JENIS) REFERENCES ORG_JENIS (KODE)
);
CREATE INDEX org_unit_induk_idx ON ORG_UNIT (INDUK_ID);

-- data awal memakai UNIT_ID eksplisit < 1000; unit baru dari aplikasi mulai 1000
CREATE SEQUENCE ORG_UNIT_SEQ MINVALUE 1 START WITH 1000 INCREMENT BY 1 NOCACHE;

CREATE OR REPLACE TRIGGER ORG_UNIT_ID_TR
BEFORE INSERT ON ORG_UNIT
FOR EACH ROW
BEGIN
    IF :new.UNIT_ID IS NULL THEN
        SELECT ORG_UNIT_SEQ.NEXTVAL INTO :new.UNIT_ID FROM dual;
    END IF;
END;
/

-- ── DATA AWAL ── (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT)
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (1, NULL, 'PT Al-Ikhlas Madinah', 'DIREKSI', 'Yayuk Sri Indiyah', 1);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (2, 1, 'Direktur Rumah Sakit', 'DIREKSI', 'dr. Fauziyah, M. Kes', 2);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (3, 2, 'Satuan Pengawas Internal', 'UNIT', 'Lilik Maesaroh, SE', 3);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (10, 2, 'Wakil Direktur Pelayanan', 'DIREKSI', 'dr. Yuniar Hisa Pratiwi', 10);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (11, 10, 'Bagian Pelayanan Medis', 'BAGIAN', 'dr. Helen Lioni', 11);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (12, 10, 'Bagian Pelayanan Keperawatan', 'BAGIAN', 'Indra Khesumah, S.Kep', 12);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (13, 10, 'Bagian Pelayanan Penunjang', 'BAGIAN', 'Anis Faridiana, Amd.Kes', 13);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (20, 2, 'Wakil Direktur Umum', 'DIREKSI', 'Sulistyo Budi, SE', 20);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (21, 20, 'Bagian Keuangan dan Logistik', 'BAGIAN', 'Rintani Sulistyowati, A.Pd.', 21);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (22, 20, 'Bagian Personalia dan Ketatausahaan', 'BAGIAN', 'Erik Kardina, A.Md.Kep', 22);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (23, 20, 'Bagian Perencanaan dan Data RS', 'BAGIAN', 'Nuur Wahid Anshary', 23);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (101, 11, 'Instalasi Gawat Darurat', 'INSTALASI', 'dr. Rizky Setia Firdaus, M.Imun', 101);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (102, 11, 'Instalasi Rawat Jalan', 'INSTALASI', 'Deana Isnaini, Amd.Kep', 102);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (103, 11, 'Instalasi Rawat Inap', 'INSTALASI', NULL, 103);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (104, 11, 'Instalasi Bedah', 'INSTALASI', 'Rosyadi Anjab, S.Kep, Ners', 104);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (105, 11, 'Instalasi Rawat Intensif', 'INSTALASI', 'Naning Khoirun Nisa'', S.Kep, Ners', 105);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (106, 11, 'Instalasi Bersalin', 'INSTALASI', 'Naning Herawati, A.Md.Keb', 106);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (107, 11, 'Instalasi Rawat Jiwa', 'INSTALASI', NULL, 107);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (131, 13, 'Instalasi Farmasi', 'INSTALASI', 'apt. Edo Kristanto, S.Farm', 131);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (132, 13, 'Instalasi Laboratorium', 'INSTALASI', 'Suciana, Amd.Kes', 132);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (133, 13, 'Instalasi Radiologi', 'INSTALASI', 'Rahmat Yuliansyah, S.Tr.Kes (Rad)', 133);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (134, 13, 'Instalasi Rekam Medis', 'INSTALASI', 'Ervintia Indriani, Amd.Keb', 134);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (135, 13, 'Instalasi Gizi', 'INSTALASI', 'Setiana Ike Prastiwi, Amd.Gz', 135);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (136, 13, 'Instalasi Sanitasi', 'INSTALASI', 'Eko Budi Prasetiyo, S.Kep, Ners', 136);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (137, 13, 'Instalasi CSSD dan Laundry', 'INSTALASI', 'Eko Budi Prasetiyo, S.Kep, Ners', 137);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (211, 21, 'Instalasi Logistik Obat dan Alat Kesehatan', 'INSTALASI', NULL, 211);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (212, 21, 'Instalasi Penjaminan', 'INSTALASI', NULL, 212);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (221, 22, 'Instalasi Keamanan, Ambulans dan Transportasi', 'INSTALASI', 'Denok Isabela, A.Md.Kes', 221);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (222, 22, 'Instalasi Pemeliharaan Sarana dan Pemulasaraan Jenazah', 'INSTALASI', 'Kukuh Rofii', 222);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (231, 23, 'Unit Penyelenggara SIMRS (Teknologi Informasi)', 'UNIT', 'Nuur Wahid Anshary', 231);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (301, 2, 'Komite Medik', 'KOMITE', 'dr. Adriyawan Widya Nugraha, Sp.JP(K)', 301);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (302, 2, 'Komite Keperawatan', 'KOMITE', 'Rosyadi Anjab, S.Kep, Ners', 302);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (303, 2, 'Komite Tenaga Kesehatan Lain', 'KOMITE', 'Metha Andikasari, A.Md.RMIK', 303);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (304, 2, 'Komite Etik dan Hukum', 'KOMITE', 'dr. M. Yogiyo Pranoto, Sp.B', 304);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (305, 2, 'Komite Peningkatan Mutu dan Keselamatan Pasien', 'KOMITE', 'dr. Jimmy Akbar, Sp.P', 305);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (306, 2, 'Komite Pencegahan dan Pengendalian Infeksi', 'KOMITE', 'Eko Budi Prasetiyo, S.Kep, Ners', 306);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (307, 2, 'Komite Keselamatan dan Kesehatan Kerja Rumah Sakit', 'KOMITE', 'Bayu Gumelar, S.Kep, Ns', 307);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (311, 301, 'Subkomite Kredensial', 'KOMITE', 'dr. M. Yogiyo Pranoto, Sp.B', 311);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (312, 301, 'Subkomite Mutu Profesi', 'KOMITE', 'dr. Jimmy Akbar, Sp.P', 312);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (313, 301, 'Subkomite Etika dan Disiplin Profesi', 'KOMITE', 'dr. Bambang Suhadi, Sp.KFR', 313);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (321, 302, 'Subkomite Kredensial Keperawatan', 'KOMITE', 'Eko Budi Prasetiyo, S.Kep, Ners', 321);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (322, 302, 'Subkomite Mutu Profesi Keperawatan', 'KOMITE', 'Naning Khoirun Nisa'', S.Kep, Ners', 322);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (323, 302, 'Subkomite Etika dan Disiplin Profesi Keperawatan', 'KOMITE', 'Erik Kardina, A.Md.Kep', 323);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (331, 303, 'Subkomite Kredensial Tenaga Kesehatan Lain', 'KOMITE', 'apt. Lilis Kurniawati, S.Farm', 331);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (332, 303, 'Subkomite Mutu Profesi Tenaga Kesehatan Lain', 'KOMITE', 'Anis Faridiana, Amd.Kes', 332);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (333, 303, 'Subkomite Etika dan Disiplin Profesi Tenaga Kesehatan Lain', 'KOMITE', 'Linda Pitasari, S.K.M', 333);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (401, 2, 'Tim Promosi Kesehatan Rumah Sakit', 'TIM', 'Anis Faridiana, Amd.Kes', 401);
INSERT INTO ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS, NAMA_KEPALA, URUT) VALUES (402, 2, 'Tim Akreditasi', 'TIM', NULL, 402);

COMMIT;
