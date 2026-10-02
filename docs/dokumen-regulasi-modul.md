# Modul Registri Dokumen Regulasi & Akreditasi

Daftar induk naskah dinas RS bertingkat, penomoran menurut **Pedoman Tata Naskah Dinas RSUI Madinah**
(Perdir 354/SK/RSI-MDN/08/2024), kaitan ke EP akreditasi (Std KEMKES 2024), dan penyusun SPO yang
mencetak PDF/DOCX berkop SPO resmi — pola yang sama dengan resume medis/CPPT sirus.

| Bagian | Berkas |
|---|---|
| Tabel (Oracle 10g, jalankan sebagai user aplikasi) | `database/schema/dokumen_regulasi.sql` |
| Konfigurasi (`DOK_KODE_RS`, `DOK_ARSIP_IMPOR`, …) | `config/dokumen.php` |
| Logika terpusat (JSON, penomoran, status, revisi, berkas) | `app/Services/DokumenService.php` |
| Halaman | `pages/dokumen/registri/⚡registri*.blade.php`, `pages/dokumen/pengaturan/⚡pengaturan.blade.php` |
| LOV induk | `livewire/lov/dokumen/⚡lov-dokumen.blade.php` |
| Cetak SPO PDF/DOCX + buka berkas | `app/Http/Controllers/DokumenCetakController.php`, `pages/dokumen/cetak/spo-pdf.blade.php` |
| Impor akreditasi | `php artisan dokumen:impor-akreditasi` (`app/Console/Commands/ImporDokumenAkreditasi.php`) |

## Tingkatan (BAB V Pedoman, hal. 70–71)

`DOK_JENIS.TINGKAT`: **0** eksternal · **1** Peraturan/Keputusan/Kebijakan Direktur · **2** Pedoman/Panduan/Program
· **3** SPO/Surat Edaran · **4** Formulir · **5** bukti (laporan, notulen, daftar hadir, sertifikat, surat).
`INDUK_ID` menunjuk dokumen di atasnya; `DokumenService::periksaInduk()` menolak induk yang tingkatnya lebih
rendah dan lingkaran induk. Tampilan **Pohon** di registri menelusuri hierarki ini.

## Penomoran (BAB IV.C, hal. 64–66)

- Pola per jenis di `DOK_JENIS.POLA_NOMOR`, token `{NNN}/{KODE}{UNIT}/{RS}/{MM}/{YYYY}` →
  `384/SPO/RSUI-MDN/10/2026`. `{RS}` dari `DOK_KODE_RS` (Pedoman menulis RSI-MDN; terbit RSUI-MDN — keputusan Sekretariat).
- `SERI`: jenis yang berbagi deret (PERDIR, SK, KEB → `SK`). `RESET_TAHUNAN`: SK/Und/BA per tahun, SPO menerus.
- Penghitung `DOK_PENOMORAN` dikunci `SELECT … FOR UPDATE` (`ambilNomor`) — bukan count+1.
- **Deret SPO diatur Sekretariat** (halaman Pengaturan Penomoran = buku catatan nomor SPO, hal. 66). Data RS memuat
  banyak skema sekaligus (/SPO/, /SPO.Ak/, /SOP.Ak/, 515/12/2024 di samping 383/01/2026), jadi sinkronisasi otomatis
  hanya menaikkan deret tahunan; untuk SPO hanya memberi usulan. Nilai awal 383 (SPO berikutnya 384).

## Model data — satu dokumen JSON per baris (pola EMR sirus)

Kolom header untuk cari/filter (`DOK_NO`, `JENIS_KODE`, `STATUS`, `POKJA`, …) + `DOK_JSON` (CLOB):

```json
{
  "dokId": 2951, "versiSkema": 1, "judulAsli": "…", "ringkasan": "",
  "akreditasi": [{"bab": "MRMIK", "standar": "MRMIK 13.1", "ep": "a", "elemen": "R"}],
  "rujukan": [{"kunci": "6/SK/1/2026", "teks": "006/SK/RSUI-MDN/01/2026"}],
  "berkas": [{"id": "…", "peran": "ASLI|PERBAIKAN_DOCX|PERBAIKAN_PDF|UNGGAH", "lokasi": "impor|unggah",
              "path": "…", "nama": "…", "ukuran": 0, "mime": "…", "waktu": "…", "oleh": "…"}],
  "isi": {"spo": {"pengertian": "<p>…</p>", "tujuan": "<ol type=\"1\">…</ol>", "kebijakan": "…",
                  "prosedur": "…", "unitTerkait": "…", "dokumenTerkait": "…"}},
  "penandatangan": {"ditetapkan": {"jabatan": "Direktur", "nama": "dr. Fauziyah, M. Kes"}},
  "tataNaskah": {"golongan": "SPO", "butir": {"kertas": ["tidak", "Letter"]}, "halaman": 2},
  "indukSumber": {"dugaan": true, "alasan": "rujukan nomor …", "indukId": 12},
  "riwayat": [{"waktu": "2026-10-02 19:00:00", "oleh": "…", "aksi": "BUAT|UBAH|NOMOR|STATUS|REVISI|SUSUN|CETAK|IMPOR|INDUK", "catatan": "…"}]
}
```

**Jebakan charset:** basis data lokal ber-charset `WE8ISO8859P1` — em dash, ≤, kutip lengkung rusak jadi "¿".
JSON ditulis **tanpa** `JSON_UNESCAPED_UNICODE` (escape `\uXXXX`, murni ASCII); kolom VARCHAR2 diberi padanan
Latin-1 (`DokumenService::latin1`) dan judul asli disimpan di `judulAsli`.

## Cetak SPO

Format hal. 25–26 & aturan rumah: A4, Arial (Liberation Sans di `resources/fonts`, metrik sama), marjin SPO
1,5 · 2,0 · 1,5 · 2,0 cm, kolom kop 3,5 | 5,5 | 4 | 4 cm, heading 1 tiap halaman, heading 2 hanya halaman pertama.

- **PDF (DomPDF):** heading 1 = elemen fixed; tabel bersarang + lebar persen (DomPDF mengabaikan `colgroup` dan
  tidak andal dengan rowspan); "Halaman n / N" digambar lewat `page_script` (CSS `counter(pages)` tidak didukung);
  bagian panjang dipecah per blok (`potongBlok`) karena DomPDF tidak memecah satu baris tabel melintasi halaman;
  `ol[type]` dipetakan ke `list-style-type` lewat CSS. Status selain BERLAKU diberi tanda air (DRAF/TIDAK BERLAKU).
- **DOCX (PhpWord):** header `first` + `default` (titlePg otomatis); `tblGrid` header ditulis ulang 4 kolom
  (PhpWord menghitung kisi dari baris pertama); daftar ditulis `tulisHtml()` sendiri — `Html::addHtml` hanya
  menerapkan `type` pada daftar terluar dan memaksa 1 → a → i untuk tingkat bersarang.

## Impor dokumen akreditasi

1. Di folder akreditasi: `python3 perkakas/ekspor_registri_dokumen.py` (manifest) dan
   `python3 perkakas/ekspor_isi_spo.py` (isi terstruktur SPO buatan sendiri dari .docx).
2. Di GPer: `php artisan dokumen:impor-akreditasi` — aman diulang (kunci `nama|ukuran`), `--perbarui` menimpa data
   hasil impor (isi, status manual & riwayat dipertahankan).
3. Induk ditautkan otomatis dan **ditandai dugaan**: (a) nomor naskah yang dirujuk isi dokumen, (b) satu-satunya
   regulasi di folder EP yang sama. Status awal dokumen impor BERLAKU — verifikasi di registri.
4. Berkas tidak disalin: dirujuk relatif terhadap `DOK_ARSIP_IMPOR`, dibuka lewat `DokumenCetakController::berkas`
   (dibatasi `realpath` di dalam akar arsip).

## Hak akses

Lihat registri: semua user login. Tambah/ubah/hapus, nomor, status, revisi, pengaturan: `admin`, `manager`
(`DokumenService::bolehUbah`). Klasifikasi TERBATAS/RAHASIA/SANGAT_RAHASIA (BAB VI) hanya bisa dicetak/dibuka
oleh `admin`, `manager`, `direktur`.
