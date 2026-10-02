<?php

/*
|--------------------------------------------------------------------------
| Lembar gaya naskah dinas — satu sumber untuk PDF (DomPDF) dan DOCX (PhpWord)
|--------------------------------------------------------------------------
| Acuan: Pedoman Tata Naskah Dinas RSUI Madinah (Lampiran I Perdir 354/SK/RSI-MDN/08/2024).
| Isi naskah (DOK_JSON.isi.naskah, versi 2) hanya memuat TEKS; kapital, tebal, rata, ukuran, jarak dan
| penomoran diterapkan dari sini menurut PERAN tiap unsur. Ubah satu baris di sini → semua naskah ikut.
|
| Satuan: ukuran huruf pt · jarak (sebelum/sesudah) dalam BARIS (1 baris = ukuran × spasi) · lebar cm.
| Pedoman hanya memberi rentang (Arial/Calibri 10–12 pt, spasi 1–1,5 — hal. 67); angka tunggal di bawah
| adalah pilihan rumah yang disetujui 2 Okt 2026: teks 12 pt, tabel 10 pt.
*/

return [

    'huruf' => [
        'nama' => 'Arial',      // hal. 67 butir 6 (DomPDF memakai Liberation Sans, metriknya sama)
        'ukuran' => 12,         // teks — pilihan rumah dalam rentang 10–12
        'tabel' => 10,          // isi tabel — batas bawah rentang
        'spasi' => 1.15,        // hal. 67 butir 7: 1–1,5
    ],

    // Marjin cm [atas, kanan, bawah, kiri]. Atas = tinggi blok logo kop resmi (aturan rumah, bukan 5 cm
    // BAB IV E.2.a yang bertentangan dengan gambar kop Pedoman sendiri).
    'halaman' => [
        'naskah' => [7.37, 2.5, 2.0, 2.5],
    ],

    // Jarak antara judul dan isi: dua spasi (hal. 63 butir d.1).
    'jarak_judul_isi' => 2,

    // Lebar kolom konsiderans/diktum (cm): label · titik dua · nomor. Sisa lebar untuk isi.
    'kolom' => ['label' => 2.8, 'titik' => 0.5, 'nomor' => 0.7],

    // Kaki penanda tangan mulai dari sini (cm dari marjin kiri).
    'kaki_kiri' => 8.5,
    // Ruang tanda tangan bila belum ada spesimen (baris).
    'ruang_ttd' => 4,

    // Indentasi per tingkat butir (cm) dan lebar kolom nomornya.
    'butir' => ['indentasi' => 0.7],

    // Urutan penomoran bertingkat (hal. 67 butir 9.b). Tiap konteks mulai dari tingkat tertentu:
    // isi pasal/diktum mulai 'a.' (hal. 12 butir 3.b), isi bab mulai 'A.'.
    'penomoran' => ['I.', 'A.', '1.', 'a.', '1)', 'a)', '(1)', '(a)'],
    'mulai_penomoran' => ['pasal' => 3, 'diktum' => 3, 'surat' => 2, 'bab' => 1],

    /*
     * Gaya per peran. Kunci yang dikenal: rata (tengah|kiri|kanan|rata), kapital, tebal, miring,
     * ukuran (pt; bawaan huruf.ukuran), sebelum, sesudah (baris), sumber (halaman Pedoman).
     */
    'gaya' => [
        // ── Kepala naskah arahan (Peraturan hal. 11, Keputusan hal. 30–31) ──
        'judul_jenis' => ['rata' => 'tengah', 'kapital' => true, 'tebal' => true, 'sumber' => 'hal. 11 b, 30 b'],
        'nomor'       => ['rata' => 'tengah', 'kapital' => true, 'tebal' => true, 'sumber' => 'hal. 11 c, 30 c'],
        'tentang'     => ['rata' => 'tengah', 'kapital' => true, 'tebal' => true, 'sebelum' => 1, 'sumber' => 'hal. 11 d, 30 d'],
        'judul'       => ['rata' => 'tengah', 'kapital' => true, 'tebal' => true, 'sumber' => 'hal. 11 e, 31 (judul dicetak tebal)'],
        'pembuat'     => ['rata' => 'tengah', 'kapital' => true, 'tebal' => true, 'sesudah' => 1, 'sumber' => 'hal. 11 pembukaan, 30 f'],

        // ── Konsiderans & diktum ──
        'label'       => ['rata' => 'kiri', 'sumber' => 'hal. 11–12: Menimbang/Mengingat/Menetapkan huruf awal kapital + titik dua'],
        'konsiderans' => ['rata' => 'rata', 'sumber' => 'hal. 11 a.(1), 12'],
        'memutuskan'  => ['rata' => 'tengah', 'kapital' => true, 'tebal' => true, 'sebelum' => 1, 'sesudah' => 1, 'sumber' => 'hal. 12 b.(1)'],
        'menetapkan'  => ['rata' => 'kiri', 'kapital' => true, 'tebal' => true, 'sumber' => 'hal. 12 b.(3)'],
        'diktum'      => ['rata' => 'kiri', 'kapital' => true, 'sumber' => 'hal. 30 butir 4: KESATU, KEDUA … huruf kapital'],
        'pasal'       => ['rata' => 'tengah', 'sebelum' => 1, 'sumber' => 'hal. 12 butir 3'],

        // ── Batang tubuh (semua jenis) ──
        'isi'         => ['rata' => 'rata', 'sesudah' => 0.25, 'sumber' => 'hal. 67 butir 3: rata kiri kanan'],

        // ── Korespondensi (Surat Dinas hal. 38–39, Undangan hal. 40–41) ──
        'identitas'   => ['rata' => 'kiri', 'sumber' => 'hal. 40 (2): Nomor, Sifat, Lampiran, Hal — huruf awal kapital, kiri'],
        'tanggal_surat' => ['rata' => 'kanan', 'sumber' => 'hal. 40 (3): sebelah kanan, sebaris dengan nomor'],
        'tujuan'      => ['rata' => 'kiri', 'sebelum' => 1, 'sumber' => 'hal. 41 (4): Yth. di bawah hal'],
        'rincian'     => ['rata' => 'kiri', 'sumber' => 'hal. 41 b.(2): hari, tanggal, waktu, tempat, acara'],

        // ── Kaki ──
        'kaki'        => ['rata' => 'kiri', 'sumber' => 'hal. 30 butir 5, 41 c'],
        // Jabatan: huruf awal kapital diikuti nama instansi kapital ("Direktur RSUI MADINAH") — hal. 30 (b), 39 (1), 41 c.
        'kaki_jabatan' => ['rata' => 'kiri', 'sumber' => 'hal. 30 5(b), 41 c'],
        // Nama: huruf proporsional (tidak dikapitalkan) — hal. 12 butir 4, 30 5(d).
        'kaki_nama'   => ['rata' => 'kiri', 'sumber' => 'hal. 12 butir 4, 30 5(d)'],
        'lampiran'    => ['rata' => 'kiri', 'kapital' => true, 'sumber' => 'hal. 13 7(a): kanan atas, huruf kapital'],
        'tembusan'    => ['rata' => 'kiri', 'sebelum' => 1, 'sumber' => 'hal. 63 B.2: kiri bawah; hal. 13 butir 8: bernomor bila lebih dari satu'],
    ],
];
