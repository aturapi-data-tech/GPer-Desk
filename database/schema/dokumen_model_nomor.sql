-- ============================================================
-- SIGAP — Model penomoran per jenis naskah (acuan pembuatan dokumen baru)
-- DOK_JENIS.MODEL_NOMOR:
--   SENDIRI  deret nomor sendiri menurut POLA_NOMOR (Pedoman BAB IV.C hal. 63–66): Perdir/SK/Kebijakan (deret SK),
--            SPO, SE, surat korespondensi. Kode jenis polos tanpa klasifikasi (SK, bukan SK.Ak).
--   INDUK    memakai nomor SK/Perdir penetapnya: Pedoman, Panduan, Program Kerja (lampiran SK).
--   TURUNAN  nomor induk + akhiran urut per induk: Formulir → 382/SPO/RSUI-MDN/10/2026-F01.
--   TANPA    tidak dinomori aplikasi: regulasi eksternal (nomor asli) dan bukti/rekaman.
-- Token tambahan POLA_NOMOR: {INDUK} nomor dokumen induk · {NN} urut 2 digit per induk.
-- Tanpa nama pemilik; aman dijalankan ulang. Tiap blok PL/SQL diakhiri "/".
-- ============================================================
SET DEFINE OFF

BEGIN EXECUTE IMMEDIATE 'ALTER TABLE DOK_JENIS ADD (MODEL_NOMOR VARCHAR2(10) DEFAULT ''TANPA'' NOT NULL)';
EXCEPTION WHEN OTHERS THEN IF SQLCODE != -1430 THEN RAISE; END IF; END;
/
BEGIN EXECUTE IMMEDIATE 'ALTER TABLE DOK_JENIS ADD CONSTRAINT dok_jenis_model_ck CHECK (MODEL_NOMOR IN (''SENDIRI'',''INDUK'',''TURUNAN'',''TANPA''))';
EXCEPTION WHEN OTHERS THEN IF SQLCODE != -2264 THEN RAISE; END IF; END;
/

UPDATE DOK_JENIS SET MODEL_NOMOR = 'SENDIRI' WHERE KODE IN ('PERDIR','SK','KEB','SPO','SE','UND','STGS','SKET','BA');
UPDATE DOK_JENIS SET MODEL_NOMOR = 'INDUK',   POLA_NOMOR = '{INDUK}',       SERI = NULL, KODE_SURAT = NULL, RESET_TAHUNAN = 'N' WHERE KODE IN ('PED','PAN','PROG');
UPDATE DOK_JENIS SET MODEL_NOMOR = 'TURUNAN', POLA_NOMOR = '{INDUK}-F{NN}', SERI = NULL, KODE_SURAT = 'F',  RESET_TAHUNAN = 'N' WHERE KODE = 'FORM';
UPDATE DOK_JENIS SET MODEL_NOMOR = 'TANPA',   POLA_NOMOR = NULL,            SERI = NULL WHERE KODE IN ('EKS','LAP','NOT','DH','SERT','BUKTI');
COMMIT;
