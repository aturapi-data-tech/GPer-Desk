<?php

/*
|--------------------------------------------------------------------------
| Registri Dokumen Regulasi & Akreditasi
|--------------------------------------------------------------------------
| Acuan: Pedoman Tata Naskah Dinas RSUI Madinah (Perdir 354/SK/RSI-MDN/08/2024).
| Lihat docs/dokumen-regulasi-modul.md.
*/

return [

    // Kode identitas RS pada nomor naskah. Pedoman menulis "RSI-MDN" (hal. 64–66),
    // dokumen yang sudah terbit memakai "RSUI-MDN" — keputusan akhir di Sekretariat.
    'kode_rs' => env('DOK_KODE_RS', 'RSUI-MDN'),

    // Folder berkas yang diunggah lewat aplikasi (disk local, di luar public).
    'folder_unggah' => env('DOK_FOLDER_UNGGAH', 'dokumen'),

    // Akar arsip berkas hasil impor (folder akreditasi). Berkas impor dirujuk dengan path
    // relatif terhadap akar ini, tidak disalin; dibaca lewat DokumenCetakController::berkas().
    'arsip_impor' => env('DOK_ARSIP_IMPOR', '/home/avro/Desktop/akreditasi2026'),

    // Batas revisi dokumen internal (Pedoman hal. 71): revisi ke-5 wajib dokumen baru.
    'revisi_maks' => 4,

    // Masa kaji ulang bawaan (tahun) saat dokumen diberlakukan. Pedoman BAB VIII.K tidak menetapkan
    // periode ("bila diperlukan", jadwal ditentukan sendiri) — null = tanggal kaji ulang diisi manual.
    'kaji_ulang_tahun' => env('DOK_KAJI_ULANG_TAHUN') ? (int) env('DOK_KAJI_ULANG_TAHUN') : null,

    // Pejabat penanda tangan bawaan (BAB VII; aturan rumah akreditasi).
    'pejabat' => [
        'direktur'   => ['jabatan' => 'Direktur',                 'nama' => 'dr. Fauziyah, M. Kes'],
        'wadir_umum' => ['jabatan' => 'Wakil Direktur Umum',      'nama' => 'Sulistyo Budi, SE'],
    ],

    // Peran yang boleh menandatangani dokumen (spesimen TTD milik sendiri) & membuka kunci.
    'peran_ttd' => ['direktur', 'manager', 'admin'],
    'peran_buka_kunci' => ['admin'],

    // Bab akreditasi (Std KEMKES 2024) — urutan sesuai instrumen.
    'pokja' => [
        'TKRS'  => 'Tata Kelola Rumah Sakit',
        'KPS'   => 'Kualifikasi dan Pendidikan Staf',
        'MFK'   => 'Manajemen Fasilitas dan Keselamatan',
        'PMKP'  => 'Peningkatan Mutu dan Keselamatan Pasien',
        'MRMIK' => 'Manajemen Rekam Medik dan Informasi Kesehatan',
        'PPI'   => 'Pencegahan dan Pengendalian Infeksi',
        'PPK'   => 'Pendidikan dalam Pelayanan Kesehatan',
        'AKP'   => 'Akses dan Kontinuitas Pelayanan',
        'HPK'   => 'Hak Pasien dan Keluarga',
        'PP'    => 'Pengkajian Pasien',
        'PAP'   => 'Pelayanan dan Asuhan Pasien',
        'PAB'   => 'Pelayanan Anestesi dan Bedah',
        'PKPO'  => 'Pelayanan Kefarmasian dan Penggunaan Obat',
        'KE'    => 'Komunikasi dan Edukasi',
        'SKP'   => 'Sasaran Keselamatan Pasien',
        'PN'    => 'Program Nasional',
    ],
];
