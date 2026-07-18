-- ============================================================
-- SIGAP — Tabel Insiden Keselamatan Pasien (IKP)
-- Schema/owner: GPER
-- Objek: PATIENT_SAFETY_INCIDENTS + sequence + trigger + index
-- Aman dijalankan ulang (drop dulu → create lagi).
-- Jalankan di SQL Developer / DBeaver (tiap blok PL/SQL diakhiri "/").
-- ============================================================

-- 1) DROP objek lama (diabaikan jika belum ada)
BEGIN EXECUTE IMMEDIATE 'DROP TABLE GPER."PATIENT_SAFETY_INCIDENTS" CASCADE CONSTRAINTS PURGE';
EXCEPTION WHEN OTHERS THEN IF SQLCODE != -942 THEN RAISE; END IF; END;
/
BEGIN EXECUTE IMMEDIATE 'DROP SEQUENCE GPER."PATIENT_SAFETY_INCIDENTS_ID_SE"';
EXCEPTION WHEN OTHERS THEN IF SQLCODE != -2289 THEN RAISE; END IF; END;
/

-- 2) TABEL
CREATE TABLE GPER."PATIENT_SAFETY_INCIDENTS" (
    "ID"               NUMBER(19,0)  NOT NULL,
    "REPORT_NO"        VARCHAR2(30)  NOT NULL,   -- nomor laporan (unik)
    "INCIDENT_TYPE"    VARCHAR2(20)  NOT NULL,   -- kpcs | knc | ktc | ktd | sentinel (StaRKes 2024 / PMKP 8)
    "TITLE"            VARCHAR2(255) NOT NULL,
    "CHRONOLOGY"       CLOB          NOT NULL,   -- kronologi kejadian
    "PATIENT_IMPACT"   CLOB,                      -- dampak ke pasien
    "IMMEDIATE_ACTION" CLOB,                      -- tindakan segera
    "GRADING"          VARCHAR2(10),              -- biru | hijau | kuning | merah
    "LOCATION"         VARCHAR2(255),
    "INCIDENT_DATE"    DATE,
    "INCIDENT_TIME"    VARCHAR2(5),               -- HH:MM
    "PATIENT_REF"      VARCHAR2(100),             -- no. RM / referensi pasien
    "REPORTER_NAME"    VARCHAR2(150),
    "REPORTER_ROLE"    VARCHAR2(100),
    "REPORTER_CONTACT" VARCHAR2(150),
    "IS_ANONYMOUS"     CHAR(1)       DEFAULT '0'    NOT NULL,  -- 0/1
    "STATUS"           VARCHAR2(30)  DEFAULT 'baru' NOT NULL,  -- baru | proses | selesai ...
    "SOURCE"           VARCHAR2(20)  DEFAULT 'web'  NOT NULL,  -- web | api
    "CREATED_AT"       TIMESTAMP,
    "UPDATED_AT"       TIMESTAMP,
    CONSTRAINT patient_safety_incidents_id_pk PRIMARY KEY ("ID")
);

-- 3) INDEX & UNIQUE
CREATE INDEX GPER.psi_status_idx ON GPER."PATIENT_SAFETY_INCIDENTS" ("STATUS");
CREATE INDEX GPER.psi_type_idx   ON GPER."PATIENT_SAFETY_INCIDENTS" ("INCIDENT_TYPE");
ALTER TABLE GPER."PATIENT_SAFETY_INCIDENTS" ADD CONSTRAINT psi_no_uq UNIQUE ("REPORT_NO");

-- 4) SEQUENCE + TRIGGER (auto-increment ID ala Oracle 10g)
CREATE SEQUENCE GPER."PATIENT_SAFETY_INCIDENTS_ID_SE" MINVALUE 1 START WITH 1 INCREMENT BY 1;

CREATE OR REPLACE TRIGGER GPER."PATIENT_SAFETY_INCIDENTS_ID_TR"
BEFORE INSERT ON GPER."PATIENT_SAFETY_INCIDENTS"
FOR EACH ROW
BEGIN
    IF :new."ID" IS NULL THEN
        SELECT GPER."PATIENT_SAFETY_INCIDENTS_ID_SE".NEXTVAL INTO :new."ID" FROM dual;
    END IF;
END;
/
