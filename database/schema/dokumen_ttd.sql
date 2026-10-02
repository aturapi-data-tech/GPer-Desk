-- ============================================================
-- SIGAP — Tanda tangan dokumen (pola modul dokumen sirus)
-- 1) USERS: spesimen tanda tangan + kode penanda-tangan, nama kolom SAMA dengan sirus
--    (myuser_code, myuser_ttd_image) supaya App\Support\TtdUser & x-signature.* dipakai apa adanya.
-- 2) DOK_DOKUMEN.STATUS: tambah DIAJUKAN (draf yang menunggu tanda tangan petugas berwenang).
-- Tanpa nama pemilik; aman dijalankan ulang. Tiap blok PL/SQL diakhiri "/".
-- ============================================================
SET DEFINE OFF

BEGIN EXECUTE IMMEDIATE 'ALTER TABLE USERS ADD (MYUSER_CODE VARCHAR2(30))';
EXCEPTION WHEN OTHERS THEN IF SQLCODE != -1430 THEN RAISE; END IF; END;
/
BEGIN EXECUTE IMMEDIATE 'ALTER TABLE USERS ADD (MYUSER_TTD_IMAGE VARCHAR2(255))';
EXCEPTION WHEN OTHERS THEN IF SQLCODE != -1430 THEN RAISE; END IF; END;
/
-- kode penanda-tangan = id user (unik, tidak berubah walau nama/email berganti)
UPDATE USERS SET MYUSER_CODE = TO_CHAR(ID) WHERE MYUSER_CODE IS NULL;

BEGIN EXECUTE IMMEDIATE 'ALTER TABLE DOK_DOKUMEN DROP CONSTRAINT dok_dokumen_sts_ck';
EXCEPTION WHEN OTHERS THEN IF SQLCODE != -2443 THEN RAISE; END IF; END;
/
ALTER TABLE DOK_DOKUMEN ADD CONSTRAINT dok_dokumen_sts_ck
    CHECK (STATUS IN ('DRAF','DIAJUKAN','BERLAKU','TIDAK_BERLAKU','DICABUT'));

COMMIT;
