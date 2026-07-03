<?php

use Livewire\Component;

// Landing page "Proposal SIKLIK" — rekonstruksi dokumen proposal Aturapi Data Technology
// (Sistem Informasi Klinik / kesiapan piloting rujukan Tulungagung) ke halaman web.
// Memakai bahasa desain & kelas .ds-* yang sama dengan halaman Standarisasi UI
// (sumber kelas: resources/css/app.css, token: tailwind.config.cjs).
new class extends Component {
    // Halaman statis (read-only). Konten proposal disusun via array di view.
};
?>

<div>
    {{-- v2: full sans (Source Sans 3) + mono untuk angka/kode --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=source-sans-3:400,500,600,700|jetbrains-mono:400,500&display=swap" rel="stylesheet" />

    <div class="ds">
        <div class="ds-section">

            {{-- ============ HERO ============ --}}
            <header class="ds-band">
                <div class="flex items-center justify-between gap-2 mb-6">
                    <div class="flex items-center gap-2">
                        <span class="ds-spike"></span>
                        <span class="ds-title-sm" style="color:var(--ink)">Aturapi&nbsp;Data&nbsp;Technology</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="ds-body-sm" style="color:var(--muted-soft)">SIKLIK · Proposal Internal</span>
                        <x-theme-toggle />
                    </div>
                </div>

                <div class="ds-eyebrow mb-4">Proposal Internal</div>
                <h1 class="ds-display-xl" style="max-width:18ch">
                    Siapkan Klinik untuk <span style="color:var(--primary)">Piloting Rujukan Berbasis Kemampuan Pelayanan</span> di Tulungagung
                </h1>
                <p class="ds-body-md mt-6" style="max-width:64ch; color:var(--body-strong)">
                    Pada <strong>13 Mei 2026</strong>, Kementerian Kesehatan mengirim surat himbauan ke seluruh
                    fasilitas kesehatan di empat wilayah piloting — termasuk klinik kita di Tulungagung. Pesannya
                    jelas: modul rujukan harus terhubung ke <strong>BPJS</strong> dan <strong>SATUSEHAT</strong>
                    sebelum piloting berjalan. Tenggatnya dekat, dan persiapannya dimulai sekarang.
                </p>

                {{-- Wilayah piloting --}}
                <div class="flex flex-wrap items-center gap-2 mt-8">
                    <span class="ds-caption-up mr-1" style="color:var(--muted)">Wilayah Piloting</span>
                    <span class="ds-btn ds-btn-primary" style="pointer-events:none">Kabupaten Tulungagung</span>
                    @foreach (['Kota Bandung', 'Kabupaten Muara Enim', 'Kota Makassar'] as $w)
                        <span class="ds-btn ds-btn-secondary" style="pointer-events:none">{{ $w }}</span>
                    @endforeach
                </div>

                {{-- Ringkasan eksekutif --}}
                <div class="ds-card mt-10">
                    <div class="ds-caption-up mb-4" style="color:var(--primary)">Ringkasan Eksekutif</div>
                    <p class="ds-body-md" style="color:var(--body-strong)">
                        Sebagai bagian dari piloting di Tulungagung, klinik kita perlu menyiapkan sistem yang
                        terhubung ke <strong>SATUSEHAT</strong> dan <strong>BPJS</strong> sesuai model rujukan baru.
                        Klinik yang masih mencatat secara manual perlu beralih ke sistem digital — dan
                        <strong>klinik yang sudah memiliki sistem pun perlu menyesuaikannya dengan ketentuan terbaru</strong>.
                    </p>
                    <p class="ds-body-md mt-4">
                        Untuk memenuhi ketentuan ini, kami mengusulkan <strong>sprint pengembangan</strong> pada
                        <strong>SIKLIK</strong> — Sistem Informasi Klinik dari Aturapi Data Technology — guna membangun
                        modul rujukan dan <em>bridging</em> yang diwajibkan, sehingga klinik siap mengikuti piloting tepat waktu.
                    </p>
                </div>
            </header>

            {{-- ============ 01 — LATAR BELAKANG ============ --}}
            <section id="latar" class="ds-band">
                <div class="ds-eyebrow mb-3">01 — Latar Belakang &amp; Permasalahan</div>
                <h2 class="ds-display-lg mb-2">Dasar regulasi dan kesenjangan saat ini</h2>
                <p class="ds-body-md mb-8" style="max-width:64ch">
                    Kewajiban ini berlandaskan <strong>Permenkes No. 16 Tahun 2024</strong> tentang Sistem Rujukan
                    Pelayanan Kesehatan Perseorangan, yang ditindaklanjuti dengan surat himbauan resmi Kemenkes:
                </p>

                {{-- Dasar kebijakan --}}
                <div class="ds-card-outline mb-10" style="border-left:4px solid var(--primary)">
                    <div class="ds-caption-up mb-2" style="color:var(--primary)">Dasar Kebijakan</div>
                    <p class="ds-body-md">
                        Surat Himbauan Kemenkes No. <strong>IR.01.01/A.VIII/932/2026</strong> (13 Mei 2026) tentang
                        Pengembangan Modul Rujukan dalam SIM Fasyankes sebelum pelaksanaan piloting.
                    </p>
                </div>

                <h3 class="ds-display-sm mb-3">Perubahan Rujukan Berbasis Kemampuan Pelayanan — terhubung langsung ke BPJS &amp; SATUSEHAT</h3>
                <p class="ds-body-md mb-8" style="max-width:64ch">
                    Ini bukan sekadar pembaruan kecil. Dulu merujuk pasien cukup memilih rumah sakit lalu mencetak
                    surat. Dalam model baru, sistem klinik harus <strong>berkomunikasi langsung dengan BPJS dan
                    SATUSEHAT secara real-time</strong> — mulai dari mengecek kriteria rujukan, menerima rekomendasi
                    rumah sakit, hingga menerbitkan nomor rujukan. Alurnya pun berbeda antara rawat jalan dan rawat inap/IGD.
                </p>

                {{-- Alur rujukan model baru --}}
                <div class="ds-card-outline mb-8">
                    <div class="ds-caption-up mb-5" style="color:var(--muted)">Alur Rujukan Model Baru</div>

                    <div class="ds-title-sm mb-3" style="color:var(--primary)">Rawat Jalan</div>
                    <div class="flex flex-wrap items-center gap-2 mb-6">
                        @foreach (['Klinik', 'BPJS (PCare / VClaim)', 'SATUSEHAT Rujukan'] as $step)
                            <span style="display:inline-block;padding:8px 14px;border:1px solid var(--hairline);border-radius:8px;background:var(--canvas);font-size:14px;font-weight:500;color:var(--body-strong)">{{ $step }}</span>
                            <span style="color:var(--primary)">&rarr;</span>
                        @endforeach
                        <span style="display:inline-block;padding:8px 14px;border:1px solid var(--hairline);border-radius:8px;font-size:14px;font-weight:500;background:var(--surface-cream-strong);border-color:var(--primary);color:var(--primary);font-weight:600">Nomor Rujukan BPJS + SATUSEHAT</span>
                    </div>

                    <div class="ds-title-sm mb-3" style="color:var(--primary)">Rawat Inap &amp; IGD</div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span style="display:inline-block;padding:8px 14px;border:1px solid var(--hairline);border-radius:8px;background:var(--canvas);font-size:14px;font-weight:500;color:var(--body-strong)">Klinik</span>
                        <span style="color:var(--primary)">&rarr;</span>
                        <span style="display:inline-block;padding:8px 14px;border:1px solid var(--hairline);border-radius:8px;background:var(--canvas);font-size:14px;font-weight:500;color:var(--body-strong)">SATUSEHAT Rujukan (langsung)</span>
                        <span style="color:var(--primary)">&rarr;</span>
                        <span style="display:inline-block;padding:8px 14px;border:1px solid var(--hairline);border-radius:8px;font-size:14px;font-weight:500;background:var(--surface-cream-strong);border-color:var(--primary);color:var(--primary);font-weight:600">Nomor Rujukan SATUSEHAT</span>
                    </div>
                </div>

                <p class="ds-body-md mb-6" style="max-width:64ch">
                    Karena alur dan datanya berubah, modul rujukan di sistem klinik harus
                    <strong>dibangun ulang, bukan sekadar ditambah.</strong> Tiga hal baru yang wajib disiapkan:
                </p>
                @php
                    $tigaBaru = [
                        ['01', 'Tiga proses baru', 'Cek kriteria rujukan, terima rekomendasi RS (berdasar kompetensi, kapasitas, jarak, jadwal spesialis), lalu kirim bundle rujukan.'],
                        ['02', 'Data lebih lengkap', 'Kode tenaga medis & pasien SATUSEHAT, data rekam medis, kriteria rujukan, dan jejaring wilayah.'],
                        ['03', 'Koneksi & keamanan baru', 'Pendaftaran Trustmark, Consumer ID/Secret, request bridging ke Kantor Cabang BPJS, dan UAT sebelum produksi.'],
                    ];
                @endphp
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    @foreach ($tigaBaru as [$no, $judul, $desc])
                        <div class="ds-card-outline">
                            <div class="flex items-baseline gap-2 mb-2">
                                <span class="ds-title-md" style="color:var(--primary)">{{ $no }}</span>
                                <span class="ds-title-md">{{ $judul }}</span>
                            </div>
                            <p class="ds-body-md">{{ $desc }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- ============ FONDASI BRIDGING ============ --}}
            <section class="ds-band">
                <h3 class="ds-display-sm mb-3">Ada fondasi yang perlu disiapkan oleh Klinik lebih dulu untuk Bridging dengan BPJS</h3>
                <p class="ds-body-md mb-4" style="max-width:64ch">
                    Tidak semua klinik berangkat dari titik yang sama. Yang sudah punya sistem tinggal menambah
                    modul rujukan. Tetapi klinik yang <strong>belum pernah terhubung ke BPJS</strong> harus membangun
                    fondasinya lebih dulu — dan ini menyentuh sisi yang sering terlupakan: <strong>infrastruktur</strong>.
                </p>
                <p class="ds-body-md mb-8" style="max-width:64ch">
                    Layanan Antrean bekerja <strong>dua arah</strong>. Sistem klinik memang memanggil BPJS, tetapi
                    <strong>BPJS — lewat Mobile JKN — juga memanggil balik ke sistem klinik</strong> untuk mengambil
                    dan memperbarui antrean. Artinya, sistem klinik harus bisa "dihubungi" dari internet secara aman.
                    Sebelum bicara endpoint, tiga hal ini harus berdiri lebih dulu:
                </p>

                @php
                    $fondasi = [
                        ['Domain &amp; Server Publik', 'Tempat web service Antrean berjalan dan dapat diakses BPJS.'],
                        ['Sertifikat SSL', 'Mengamankan dan mengenkripsi koneksi (HTTPS).'],
                        ['IP Publik &amp; Port Terbuka', 'Agar layanan dapat di-whitelist dan diakses oleh BPJS.'],
                        ['Jaringan &amp; Hardware Internal', 'Router/firewall, switch, server, dan koneksi internet stabil agar layanan selalu online.'],
                    ];
                @endphp
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    @foreach ($fondasi as [$judul, $desc])
                        <div class="ds-card-outline">
                            <div class="ds-title-md mb-2">{!! $judul !!}</div>
                            <p class="ds-body-md">{{ $desc }}</p>
                        </div>
                    @endforeach
                </div>

                <p class="ds-body-md mt-8" style="max-width:64ch">
                    Setelah fondasi itu siap, klinik menyambungkan <strong>Antrean dua arah</strong>, lalu
                    <strong>bridging ke PCare</strong> sebagai dasar rujukan rawat jalan — barulah modul
                    <strong>Rujukan Berbasis Kemampuan Pelayanan</strong> dapat diaktifkan.
                </p>
            </section>

            {{-- ============ KONDISI SAAT INI vs WAJIB ============ --}}
            <section class="ds-band">
                <h3 class="ds-display-sm mb-6">Dari kondisi saat ini menuju yang wajib disiapkan</h3>
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div class="ds-card-outline">
                        <div class="ds-title-md mb-4">Kondisi saat ini</div>
                        <ul class="space-y-3">
                            @foreach ([
                                'Pencatatan masih manual / berbasis kertas — atau sistem lama yang belum sesuai ketentuan terbaru.',
                                'Rujukan dan klaim BPJS dikerjakan terpisah, rawan kesalahan.',
                                'Belum ada bridging ke SATUSEHAT.',
                                'Pelaporan menyita waktu dan sulit diaudit.',
                            ] as $i)
                                <li class="flex items-start gap-2 ds-body-md">
                                    <span style="color:var(--muted-soft)">&bull;</span><span>{{ $i }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="ds-card-outline" style="border-left:4px solid var(--primary)">
                        <div class="ds-title-md mb-4" style="color:var(--primary)">Yang wajib disiapkan</div>
                        <ul class="space-y-3">
                            @foreach ([
                                'Modul rujukan rawat jalan, inap & darurat.',
                                'Bridging ke PCare/VClaim & SATUSEHAT.',
                                'UAT bersama BPJS & sosialisasi staf.',
                                'Penerapan ke server produksi sebelum piloting.',
                            ] as $i)
                                <li class="flex items-start gap-2 ds-body-md">
                                    <svg class="w-4 h-4 mt-1 shrink-0" style="color:var(--primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                    <span>{{ $i }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                {{-- Catatan urgensi --}}
                <div class="ds-card-outline mt-8" style="border-left:4px solid var(--error)">
                    <div class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold mb-3"
                         style="background:var(--error-tint);color:var(--error-deep)">CATATAN URGENSI</div>
                    <p class="ds-body-md">
                        <strong>Surat Kemenkes menetapkan pengembangan modul rujukan selesai paling lambat 31 Mei 2026.</strong>
                        Klinik yang belum siap perlu segera mempercepat agar tetap dapat mengikuti piloting dan tidak
                        terhambat dalam alur rujukan maupun klaim BPJS.
                    </p>
                </div>
            </section>

            {{-- ============ 02 — SOLUSI ============ --}}
            <section id="solusi" class="ds-band">
                <div class="ds-eyebrow mb-3">02 — Solusi yang Diajukan</div>
                <h2 class="ds-display-lg mb-2">SIKLIK — mempermudah layanan kepada pasien dan meringankan kerja manajemen</h2>
                <p class="ds-body-md mb-8" style="max-width:64ch">
                    <strong>SIKLIK</strong> menjalankan seluruh alur layanan klinik — dari pendaftaran hingga rujukan —
                    dalam satu sistem yang sudah <strong>ter-bridging ke SATUSEHAT dan BPJS</strong>, sehingga klinik
                    langsung selaras dengan ketentuan piloting. Tiga prinsip yang menjadikan SIKLIK tepat untuk kebutuhan saat ini:
                </p>

                @php
                    $prinsip = [
                        ['Siap Bridging', 'Integrasi SatuSehat & BPJS (PCare) bawaan, bukan tambahan.'],
                        ['Cepat Diterapkan', 'Berbasis cloud, implementasi dan pelatihan dalam hitungan minggu.'],
                        ['Sesuai Alur Klinik', 'Dirancang mengikuti operasional harian, mudah dipakai staf.'],
                    ];
                @endphp
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                    @foreach ($prinsip as [$judul, $desc])
                        <div style="border-top:2px solid var(--primary); padding-top:16px">
                            <div class="ds-title-md mb-2">{{ $judul }}</div>
                            <p class="ds-body-md">{{ $desc }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="ds-card mt-10" style="border-left:4px solid var(--primary)">
                    <div class="ds-title-md mb-2">Menyesuaikan kebutuhan klinik</div>
                    <p class="ds-body-md">
                        Karena dikembangkan langsung, SIKLIK dapat mengikuti cara kerja dan kebutuhan khusus klinik —
                        sekaligus tetap memenuhi ketentuan piloting. Tidak kaku seperti program siap-pakai, sehingga
                        lebih nyaman dipakai sehari-hari.
                    </p>
                </div>
            </section>

            {{-- ============ 03 — MODUL SISTEM ============ --}}
            <section id="modul" class="ds-band">
                <div class="ds-eyebrow mb-3">03 — Modul Sistem</div>
                <h2 class="ds-display-lg mb-8">Satu sistem untuk semua kebutuhan klinik</h2>

                @php
                    // [no, judul, desc, badge?, full?]
                    $modul = [
                        ['01', 'Rekam Medis Elektronik (RME)', 'Pencatatan klinis terstandar, riwayat pasien terpusat, dan siap dikirim ke SATUSEHAT — fondasi seluruh layanan, rujukan, dan pelaporan.', null, true],
                        ['02', 'Pendaftaran &amp; Antrian', 'Pendaftaran daring maupun di tempat, nomor antrian otomatis, dan tampilan ruang tunggu.', null, false],
                        ['03', 'Kasir &amp; Billing', 'Tarif layanan, tagihan, metode pembayaran, dan rekonsiliasi klaim dalam satu alur.', null, false],
                        ['04', 'Apotek &amp; Inventori', 'Resep elektronik, stok obat real-time, peringatan kedaluwarsa, dan kartu stok.', null, false],
                        ['05', 'Rujukan Terintegrasi', 'Rujukan berbasis kemampuan pelayanan, terhubung langsung ke <strong>BPJS &amp; SATUSEHAT</strong> — jantung kesiapan piloting Tulungagung.', 'INTI PILOTING', true],
                        ['06', 'Dashboard &amp; Pelaporan', 'Laporan wajib, kunjungan, dan kinerja layanan tersaji otomatis dan siap audit.', null, true],
                    ];
                @endphp

                {{-- Modul utama (full width) + grid 3 di tengah --}}
                <div class="space-y-6">
                    {{-- 01 RME --}}
                    @foreach (array_slice($modul, 0, 1) as [$no, $judul, $desc, $badge, $full])
                        <div class="ds-card-outline" style="position:relative;overflow:hidden">
                            <div style="position:absolute;top:-10px;right:16px;font-weight:700;font-size:84px;line-height:1;color:var(--surface-cream-strong)">{{ $no }}</div>
                            <div class="ds-title-md mb-2" style="position:relative">{!! $judul !!}</div>
                            <p class="ds-body-md" style="position:relative;max-width:62ch">{!! $desc !!}</p>
                        </div>
                    @endforeach

                    {{-- 02-04 --}}
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                        @foreach (array_slice($modul, 1, 3) as [$no, $judul, $desc, $badge, $full])
                            <div class="ds-card-outline" style="position:relative;overflow:hidden">
                                <div style="position:absolute;top:-6px;right:12px;font-weight:700;font-size:60px;line-height:1;color:var(--surface-cream-strong)">{{ $no }}</div>
                                <div class="ds-title-md mb-2" style="position:relative">{!! $judul !!}</div>
                                <p class="ds-body-md" style="position:relative">{!! $desc !!}</p>
                            </div>
                        @endforeach
                    </div>

                    {{-- 05 Rujukan (inti) --}}
                    @foreach (array_slice($modul, 4, 1) as [$no, $judul, $desc, $badge, $full])
                        <div class="ds-card-outline" style="position:relative;overflow:hidden;border-color:var(--primary)">
                            <div style="position:absolute;top:-10px;right:16px;font-weight:700;font-size:84px;line-height:1;color:var(--surface-cream-strong)">{{ $no }}</div>
                            <div class="flex items-center gap-3 mb-2" style="position:relative">
                                <span class="ds-title-md">{!! $judul !!}</span>
                                <x-badge variant="warning">{{ $badge }}</x-badge>
                            </div>
                            <p class="ds-body-md" style="position:relative;max-width:62ch">{!! $desc !!}</p>
                        </div>
                    @endforeach

                    {{-- 06 Dashboard --}}
                    @foreach (array_slice($modul, 5, 1) as [$no, $judul, $desc, $badge, $full])
                        <div class="ds-card-outline" style="position:relative;overflow:hidden">
                            <div class="flex items-start gap-4" style="position:relative">
                                <span style="font-weight:700;font-size:48px;line-height:1;color:var(--surface-cream-strong)">{{ $no }}</span>
                                <div>
                                    <div class="ds-title-md mb-1">{!! $judul !!}</div>
                                    <p class="ds-body-md">{!! $desc !!}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- ============ 04 — ESTIMASI BIAYA ============ --}}
            <section id="biaya" class="ds-band">
                <div class="ds-eyebrow mb-3">04 — Estimasi Biaya</div>
                <h2 class="ds-display-lg mb-1">Rincian pengadaan &amp; kebutuhan klinik</h2>
                <p class="ds-title-md mb-8" style="color:var(--muted)">Pengajuan Klinik Madinah · Juni 2026</p>
                <p class="ds-body-md mb-8" style="max-width:64ch">
                    Daftar ini disusun setelah kami melakukan screening dan meninjau langsung fasilitas klinik, untuk
                    melengkapi perangkat dan infrastruktur yang dibutuhkan agar bridging dapat berjalan. Angka bersifat estimasi.
                </p>

                {{-- Tabel pengadaan --}}
                @php
                    $pengadaan = [
                        ['Laptop poli gigi / umum', 'Lenovo (branded)', 1, '8.650.000', '8.650.000'],
                        ['Mini PC (server)', '1 set', 1, '7.350.000', '7.350.000'],
                        ['UPS server', '—', 1, '1.620.000', '1.620.000'],
                        ['Adaptor UPS', '—', 2, '195.000', '390.000'],
                        ['Wifi Router (depan)', 'Dual Band', 1, '1.170.000', '1.170.000'],
                        ['Keyboard', '1 set', 1, '220.000', '220.000'],
                        ['Mouse', '1 set', 1, '125.000', '125.000'],
                    ];
                @endphp
                <div class="ds-card-outline mb-3" style="padding:0;overflow:hidden">
                    <div class="overflow-x-auto">
                        <table class="ds-table">
                            <thead>
                                <tr>
                                    <th>Pengadaan Perangkat (Sekali Bayar)</th>
                                    <th>Spesifikasi</th>
                                    <th class="ds-c">Jml</th>
                                    <th class="ds-c">Harga Satuan</th>
                                    <th style="text-align:right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pengadaan as [$nama, $spec, $jml, $satuan, $total])
                                    <tr>
                                        <td class="ds-td-strong">{{ $nama }}</td>
                                        <td class="ds-td-meta">{{ $spec }}</td>
                                        <td class="ds-c">{{ $jml }}</td>
                                        <td class="ds-c" style="font-variant-numeric:tabular-nums">Rp {{ $satuan }}</td>
                                        <td class="ds-td-strong" style="text-align:right;font-variant-numeric:tabular-nums">Rp {{ $total }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="ds-table-foot" style="display:flex;align-items:center;justify-content:space-between;background:var(--surface-cream-strong)">
                        <span class="ds-title-sm" style="color:var(--primary)">Subtotal pengadaan</span>
                        <span class="ds-title-md" style="color:var(--primary);font-variant-numeric:tabular-nums">Rp 19.525.000</span>
                    </div>
                </div>
                <p class="ds-body-sm mb-10" style="color:var(--muted-soft)">*Jumlah unit dapat disesuaikan dengan kebutuhan klinik.</p>

                {{-- Biaya berjalan --}}
                <div class="ds-card-outline" style="padding:0;overflow:hidden">
                    <div class="ds-table-foot" style="background:transparent;border-bottom:1px solid var(--hairline)">
                        <span class="ds-caption-up" style="color:var(--muted)">Biaya Berjalan (Langganan)</span>
                    </div>
                    <table class="ds-table">
                        <tbody>
                            <tr>
                                <td class="ds-td-strong">Token AI · 2 Wifi Router IoT</td>
                                <td style="text-align:right">Rp 2.500.000 untuk 4 bulan pertama, selanjutnya <strong>Rp 562.500</strong>/bln</td>
                            </tr>
                            <tr>
                                <td class="ds-td-strong">Web domain</td>
                                <td style="text-align:right;font-variant-numeric:tabular-nums">Rp 187.500 / bln</td>
                            </tr>
                            <tr>
                                <td class="ds-td-strong">Sertifikat SSL</td>
                                <td style="text-align:right;font-variant-numeric:tabular-nums">Rp 437.500 / bln</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="ds-table-foot" style="display:flex;align-items:center;justify-content:space-between;background:var(--surface-cream-strong)">
                        <span class="ds-title-sm" style="color:var(--primary)">Estimasi rutin bulanan (mulai bulan ke-5)</span>
                        <span class="ds-title-md" style="color:var(--primary);font-variant-numeric:tabular-nums">± Rp 1.187.500 / bln</span>
                    </div>
                </div>
                <p class="ds-body-sm mt-3" style="color:var(--muted-soft)">
                    Belum termasuk pajak yang berlaku. Biaya langganan Token AI lebih tinggi pada 4 bulan pertama (masa pemasangan).
                </p>
            </section>

            {{-- ============ RINGKASAN INVESTASI ============ --}}
            <section class="ds-band">
                <div class="ds-card-outline" style="border:1.5px solid var(--primary)">
                    <div class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold mb-5"
                         style="background:var(--surface-cream-strong);color:var(--primary)">RINGKASAN INVESTASI</div>
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <div class="ds-body-sm mb-1" style="color:var(--muted)">Pengadaan perangkat (sekali bayar)</div>
                            <div class="ds-display-md" style="font-variant-numeric:tabular-nums">Rp 19.525.000</div>
                        </div>
                        <div>
                            <div class="ds-body-sm mb-1" style="color:var(--muted)">Biaya rutin bulanan (mulai bln ke-5)</div>
                            <div class="ds-display-md" style="color:var(--primary);font-variant-numeric:tabular-nums">± Rp 1.187.500 <span class="ds-body-sm" style="color:var(--muted-soft)">/ bln</span></div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- ============ TENTANG PENYEDIA ============ --}}
            <section id="penyedia" class="ds-band">
                <div class="ds-eyebrow mb-3">Tentang Penyedia</div>
                <h2 class="ds-display-lg mb-4">Aturapi Data Technology</h2>
                <p class="ds-body-md mb-6" style="max-width:64ch">
                    Aturapi Data Technology membantu fasilitas kesehatan menghadirkan sistem yang mudah dipakai, sesuai
                    ketentuan, dan terhubung dengan layanan nasional seperti SATUSEHAT dan BPJS. SIKLIK kami kembangkan
                    agar klinik dapat melayani pasien sehari-hari dengan lancar, sekaligus siap mengikuti kebijakan rujukan terbaru.
                </p>
                <p class="ds-body-sm" style="color:var(--muted)">
                    Kontak: <strong style="color:var(--ink)">085 755 081 858</strong> · Alamat:
                    <strong style="color:var(--ink)">Jl Raya Pulosari RT 003 RW 010, Ds. Pulosari, Kec. Ngunut — Tulungagung 66292</strong>
                </p>
            </section>

            {{-- ============ 05 — PENUTUP ============ --}}
            <section id="penutup" class="ds-band">
                <div class="ds-eyebrow mb-3">05 — Penutup &amp; Langkah Selanjutnya</div>
                <h2 class="ds-display-lg mb-4">Siapkan secepatnya</h2>
                <p class="ds-body-md mb-8" style="max-width:64ch">
                    Agar klinik punya cukup waktu untuk pengembangan, uji coba, dan pelatihan sebelum piloting di
                    Tulungagung dimulai, kami mengusulkan untuk memulai pengembangan <strong>SIKLIK</strong> sesegera mungkin.
                </p>

                {{-- Callout CTA --}}
                <div class="ds-card-dark mb-10">
                    <h3 class="ds-display-sm" style="color:var(--on-dark)">Mulai persiapannya</h3>
                    <p class="ds-body-md mt-2 mb-6" style="color:var(--on-dark-soft);max-width:58ch">
                        Mari jadwalkan diskusi singkat untuk menyusun rencana pengembangan SIKLIK di klinik kita —
                        agar persiapan piloting berjalan tepat waktu dan tidak terburu-buru.
                    </p>
                    <div class="flex flex-wrap items-center gap-5">
                        <a href="tel:085755081858" class="ds-btn ds-btn-oncream">Hubungi tim Aturapi</a>
                        <div>
                            <div class="ds-title-sm" style="color:var(--on-dark)">Nuur Wahid Anshary</div>
                            <div class="ds-title-md" style="color:var(--on-dark)">085 755 081 858</div>
                        </div>
                    </div>
                </div>

                {{-- Langkah berikutnya --}}
                <div class="ds-card-outline" style="border-left:4px solid var(--primary)">
                    <div class="ds-caption-up mb-4" style="color:var(--primary)">Langkah Berikutnya</div>
                    <ol class="space-y-2.5">
                        @foreach ([
                            ['Menyetujui rencana', '& anggaran pengembangan.'],
                            ['Menjadwalkan', 'pengembangan & migrasi data.'],
                            ['Pelatihan staf', '& uji coba bridging ke SATUSEHAT / BPJS.'],
                            ['Klinik beroperasi penuh', 'sesuai model rujukan baru.'],
                        ] as $idx => [$kuat, $sisa])
                            <li class="flex items-start gap-3 ds-body-md">
                                <span class="ds-title-sm" style="color:var(--primary)">{{ $idx + 1 }}.</span>
                                <span><strong>{{ $kuat }}</strong> {{ $sisa }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>

                {{-- Tanda tangan / meta --}}
                <div class="grid grid-cols-1 gap-10 mt-12 sm:grid-cols-2">
                    <div>
                        <p class="ds-body-sm mb-12" style="color:var(--muted)">Disusun oleh,</p>
                        <div style="border-top:1px solid var(--hairline);padding-top:8px">
                            <span class="ds-body-md">Aturapi Data Technology</span>
                        </div>
                    </div>
                    <div>
                        <p class="ds-body-sm mb-12" style="color:var(--muted)">Disetujui oleh,</p>
                        <div style="border-top:1px solid var(--hairline);padding-top:8px">
                            <span class="ds-body-md">Manajemen Klinik</span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-6 mt-10 sm:grid-cols-4" style="border-top:1px solid var(--hairline);padding-top:24px">
                    @foreach ([
                        ['Diajukan Oleh', 'Aturapi Data Technology'],
                        ['Ditujukan Kepada', 'Manajemen Klinik'],
                        ['Tanggal', 'Juni 2026'],
                        ['Versi', '1.0'],
                    ] as [$label, $val])
                        <div>
                            <div class="ds-caption-up mb-1" style="color:var(--muted-soft)">{{ $label }}</div>
                            <div class="ds-body-md" style="font-weight:600">{{ $val }}</div>
                        </div>
                    @endforeach
                </div>
            </section>

        </div>
    </div>
</div>
