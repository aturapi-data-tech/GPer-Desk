# Modul Struktur Organisasi

Pohon satuan organisasi RS + uraian tugas kepala & job description staf. Halaman `/master/organisasi`
(menu Dokumen Akreditasi → Struktur Organisasi; lihat: semua user login, ubah: admin/manager).

| Bagian | Berkas |
|---|---|
| Tabel (Oracle 10g, jalankan sebagai user aplikasi; drop + create + data awal) | `database/schema/organisasi.sql` |
| Halaman (tab Unit · Bagan · Jenis Unit) | `resources/views/pages/master/master-organisasi/⚡master-organisasi.blade.php` |
| Modal tambah/ubah unit (tab Unit · Uraian Tugas Kepala · Job Description) | `…/⚡master-organisasi-actions.blade.php` |
| Bagan (partial rekursif, CSS di halaman) | `…/bagan-node.blade.php`, `…/bagan-kotak.blade.php` |

## Tabel

- `ORG_JENIS (KODE, NAMA, URUT)` — master jenis unit. RS lain mengisi tingkatannya sendiri; `URUT` = tingkat & warna badge.
- `ORG_UNIT (UNIT_ID, INDUK_ID, NAMA, JENIS → ORG_JENIS, NAMA_KEPALA, URUT, ORG_JSON)` — `INDUK_ID` = atasan langsung.
  Unit baru dari aplikasi mulai `UNIT_ID` 1000 (sekuens `ORG_UNIT_SEQ`). Unit yang masih punya bawahan tidak bisa dihapus (FK).

Data awal: Perdir PT Al-Ikhlas Madinah 104/SK/PT-AIM/10/2025 (TKRS 3 EP a) + pejabat dari SK 096/SK/PT-AIM/7/2026,
SK komite, URGAS dan dokumen unit 2025–2026 (ditelusuri 3 Okt 2026).

## ORG_JSON

Mengikuti format **Uraian Tugas** RS (`URGAS_*.pdf`, TKRS 3 EP a): 1. dibebankan kepada · 2. nama jabatan · 3. pengertian ·
4. persyaratan · 5. tanggung jawab dan wewenang · 6. tugas pokok · 7. uraian tugas. Nama pejabat, unit kerja, atasan langsung,
bawahan dan kedudukan dalam struktur **tidak disimpan di JSON** — diambil dari pohon `ORG_UNIT`.

```json
{
  "versi": 1,
  "kepala": {"nik": "140.5121.21"},
  "urgas": {
    "namaJabatan": "Kepala Instalasi Gawat Darurat",
    "pengertian": "Seorang tenaga kesehatan yang …",
    "persyaratan": {"pendidikan": "…", "lain": ["Berakhlak baik …", "Memiliki kemampuan manajerial"]},
    "tanggungJawabWewenang": ["Menegur bawahan …", "…"],
    "tugasPokok": "Mengawasi dan mengendalikan …",
    "uraianTugas": ["Mengkoordinir seluruh kegiatan di IGD", "…"]
  },
  "jobdesc": [{"jabatan": "Pelaksana Instalasi Gawat Darurat", "uraian": ["Melakukan triase", "…"]}]
}
```

- Di form, daftar disunting sebagai teks **satu butir per baris** (baris kosong dibuang saat simpan).
- JSON ditulis **tanpa** `JSON_UNESCAPED_UNICODE` (basis data ber-charset Latin-1; escape `\uXXXX` menjaga em dash dsb.),
  dibaca dengan `App\Support\OracleLob::read()` — sama dengan `DOK_JSON` modul dokumen.
