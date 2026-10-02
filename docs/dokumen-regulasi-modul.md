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

## Isi naskah sebagai JSON (Pedoman Tata Naskah BAB III)

Naskah regulasi tidak lagi bergantung pada berkas: isinya disimpan sebagai JSON di `DOK_JSON.isi.naskah`
(SPO di `isi.spo`) dan PDF/DOCX **dibangkitkan dari JSON**. Definisi format & kerangka baku: `app/Support/TataNaskah.php`.

| Format | Jenis | Bagian (bentuk baku) |
|---|---|---|
| `peraturan` | PERDIR | judulJenis, tentang, pembuat, menimbang[] (a.), mengingat[] (1.), menetapkan, pasal[] — hal. 11–13 |
| `keputusan` | SK, KEB | sama; badan diktum[] KESATU, KEDUA … (Pasal juga diterima — banyak SK RS ber-Pasal) — hal. 29–31 |
| `bab` | PED, PAN, PROG | judul, sistematika (7 sistematika baku hal. 15–22), bab[{judul, isi, sub[{judul, isi}]}], kepustakaan, kaki; LAMPIRAN diambil dari induk Peraturan/Keputusan |
| `edaran` | SE | judulJenis, tentang, yth[], isi, kaki, tembusan[] — hal. 27–29 |
| `spo` | SPO | isi.spo — hal. 24–26 |

```json
{"format": "keputusan", "judulJenis": "KEPUTUSAN DIREKTUR RSUI MADINAH", "tentang": "PENETAPAN …",
 "pembuat": "DIREKTUR RSUI MADINAH TULUNGAGUNG,", "menimbang": ["bahwa …;"], "mengingat": ["Undang-Undang …;"],
 "menetapkan": "", "pasal": [{"isi": "<p>…</p>"}],
 "kaki": {"tempat": "Tulungagung", "tanggal": "2026-01-05", "jabatan": "DIREKTUR RSUI MADINAH,", "nama": "dr. Fauziyah, M. Kes", "nik": ""},
 "tembusan": []}
```

- **Penyusun naskah** (`⚡registri-naskah`): panel kiri bagian baku, kanan penyunting; satu TinyMCE bergantian,
  daftar (Menimbang/Mengingat/Tembusan/Yth) satu butir per baris, tambah/geser/hapus Pasal/Diktum/BAB/Sub,
  "Terapkan kerangka" mengisi BAB dari sistematika baku. Dokumen baru langsung berisi bagian wajib (`TataNaskah::kosong`).
- **Cetak naskah**: kop resmi sebagai latar satu halaman penuh (gambar sama dengan `perkakas/pasang_kop.py`,
  `public/images/kop/`), marjin aturan rumah atas 7,37 · kiri/kanan 2,5 · bawah 2,0 cm; BAB baru di halaman baru,
  nomor halaman untuk naskah berbab; kaki tidak tertinggal sendirian (page-break-inside avoid / keepNext).
- **Konversi dokumen lama** (`perkakas/ekspor_isi_naskah.py`): teks PDF → JSON per bentuk baku (judul bagian
  diseragamkan, butir dipecah menurut urutan a.→b. / 1.→2., daftar isi dibuang, BAB I berulang = reset).
  Hanya hasil berstruktur lengkap yang dipasang; semuanya `isiSumber.perluDicek = true` sampai disimpan dari penyusun.
  Alasan penolakan: `perkakas/.registri/isi_naskah_laporan.json` (pindaian, lampiran yang salah golongan, dll.).
  Bila nama penanda tangan di PDF berupa gambar, diisi Direktur yang tercatat dan ditandai `kaki.namaDiisiBawaan`.

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

1. Di folder akreditasi: `python3 perkakas/ekspor_registri_dokumen.py` (manifest),
   `python3 perkakas/ekspor_isi_spo.py` (isi SPO buatan sendiri dari .docx) dan
   `python3 perkakas/ekspor_isi_naskah.py` (isi Peraturan/Keputusan/Pedoman/Panduan/Program dari PDF).
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

## Naskah versi 2 — isi teks + lembar gaya (2 Okt 2026)

Isi naskah dipisah dari tampilannya. `DOK_JSON.isi.naskah` dengan `"versi": 2` hanya memuat **teks** per bagian;
kapital, tebal, rata, ukuran huruf, jarak, nomor butir (a., 1), …) dan tanda baca akhir butir diterapkan saat cetak.

| Berkas | Isi |
|---|---|
| `config/tata_naskah.php` | lembar gaya per peran (judul_jenis, nomor, konsiderans, diktum, kaki_jabatan, …) + rujukan halaman Pedoman. Satu sumber untuk PDF dan DOCX |
| `app/Support/Naskah/Kerangka.php` | bagian wajib per jenis + isi kosong. Sudah: PERDIR, SK, KEB (arahan), UND (undangan) |
| `app/Support/Naskah/Susun.php` | JSON → deret unsur tata letak (teks, lajur, isi, identitas, rincian, kaki, tembusan, lampiran) |
| `app/Support/Naskah/CetakPdf.php` · `CetakDocx.php` | unsur → DomPDF / PhpWord |
| `php artisan naskah:contoh berkas.json` | pratinjau PDF+DOCX dari JSON tanpa database (`storage/app/naskah-contoh`) |

Pilihan rumah (disetujui user 2 Okt 2026): teks 12 pt, tabel 10 pt, spasi 1,15 (Pedoman hanya memberi rentang 10–12 pt
dan 1–1,5). Naskah versi 1 tetap dicetak dengan jalur lama sampai dikonversi
(`perkakas/naskah_v2.py` → `.registri/isi_naskah_v2.json`, 82 naskah arahan).
