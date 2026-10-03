{{-- Cetak naskah dinas dari JSON (DomPDF) — Pedoman Tata Naskah BAB III.
     Format: peraturan · keputusan · bab (Pedoman/Panduan/Program) · edaran · undangan. SPO: spo-pdf.blade.php.
     Kop resmi = latar satu halaman penuh tiap halaman (sama dengan pasang_kop.py perkakas akreditasi);
     marjin aturan rumah: atas 7,37 cm (tinggi blok logo kop), kiri/kanan 2,5, bawah 2,0 cm.
     DomPDF tanpa flex/grid → tabel. Variabel: $dok, $n (isi.naskah), $lampiran, $tanggal. --}}
@php
    use App\Support\TataNaskah;
    use App\Http\Controllers\DokumenCetakController as Cetak;
    $izin = '<p><br><b><strong><i><em><u><ol><ul><li><table><thead><tbody><tr><td><th><sup><sub>';
    $kop = public_path('images/kop/kop_portrait.png');
    $format = $n['format'];
    $kaki = $n['kaki'] ?? [];
    $html = fn ($h) => filled(trim((string) $h)) ? strip_tags((string) $h, $izin) : '';
    // tanda air: belum ditandatangani = DRAF (termasuk dokumen hasil impor); dokumen usang tetap ditandai
    $tanda = ['TIDAK_BERLAKU' => 'TIDAK BERLAKU', 'DICABUT' => 'DICABUT'][$dok->status] ?? ($ttd ? null : 'DRAF');
    $ttdImg = $ttd ? \App\Services\DokumenService::gambarTtd($ttd['kode'] ?? null) : null;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $dok->dok_judul }}</title>
    <style>
        @foreach (['normal normal' => 'Regular', 'bold normal' => 'Bold', 'normal italic' => 'Italic', 'bold italic' => 'BoldItalic'] as $gaya => $berkas)
            @php [$tebalF, $miringF] = explode(' ', $gaya); @endphp
            @font-face { font-family: 'Arial'; font-weight: {{ $tebalF }}; font-style: {{ $miringF }};
                src: url('{{ resource_path('fonts/LiberationSans-' . $berkas . '.ttf') }}') format('truetype'); }
        @endforeach
        @page { size: A4; margin: 7.37cm 2.5cm 2cm 2.5cm; }
        body { margin: 0; font-family: 'Arial', sans-serif; font-size: 11pt; line-height: 1.3; color: #000; }
        .kop { position: fixed; top: -7.37cm; left: -2.5cm; width: 21cm; height: 29.7cm; z-index: -2; }
        .tanda { position: fixed; top: 6cm; left: 0; right: 0; text-align: center; font-size: 60pt; font-weight: bold;
                 color: #e3e3e3; transform: rotate(-30deg); z-index: -1; }
        .tengah { text-align: center; }
        .tebal { font-weight: bold; }
        .kapital { text-transform: uppercase; }
        .rata { text-align: justify; }
        p { margin: 0 0 4px 0; }
        ol, ul { margin: 0 0 4px 0; padding-left: 18px; }
        li { margin-bottom: 2px; }
        ol[type="1"] { list-style-type: decimal; } ol[type="A"] { list-style-type: upper-alpha; }
        ol[type="a"] { list-style-type: lower-alpha; } ol[type="I"] { list-style-type: upper-roman; }
        ol[type="i"] { list-style-type: lower-roman; }
        table.konsideran { width: 100%; border-collapse: collapse; }
        table.konsideran td { vertical-align: top; padding: 0 0 4px 0; text-align: justify; }
        table.isi-tabel td, table.isi-tabel th { border: 1px solid #000; padding: 2px 4px; }
        .kaki { page-break-inside: avoid; margin-top: 18px; }
        .kaki-kanan { margin-left: 8.5cm; }
        .bab-judul { text-align: center; font-weight: bold; margin: 0 0 10px 0; }
        .bab-baru { page-break-before: always; }
        .sub-judul { font-weight: bold; margin: 8px 0 3px 0; }
        .lampiran { margin-left: 8cm; width: 8cm; font-size: 10pt; margin-bottom: 16px; border-collapse: collapse; }
        .lampiran td { vertical-align: top; padding: 0 2px; }
        .blok-umum table { width: 100%; border-collapse: collapse; margin: 4px 0; font-size: 9.5pt; }
        .blok-umum td, .blok-umum th { border: 1px solid #000; padding: 2px 4px; vertical-align: top; }
        .lampiran td.kunci { width: 2.1cm; } .lampiran td.titik { width: 0.3cm; }
    </style>
</head>
<body>
    @if (is_file($kop))
        <img class="kop" src="{{ $kop }}">
    @endif
    @if ($tanda)
        <div class="tanda">{{ $tanda }}</div>
    @endif

    {{-- ══ PERATURAN · KEPUTUSAN · EDARAN: kepala simetris (hal. 11, 29–30) ══ --}}
    @if (in_array($format, ['peraturan', 'keputusan', 'edaran'], true))
        <div class="tengah tebal kapital">{{ $n['judulJenis'] ?? '' }}</div>
        <div class="tengah tebal kapital">NOMOR {{ $dok->dok_no ?: '…' }}</div>
        <div class="tengah tebal" style="margin-top: 6px">TENTANG</div>
        <div class="tengah tebal kapital" style="margin-bottom: 14px">{{ $n['tentang'] ?? '' }}</div>
    @endif

    @if (in_array($format, ['peraturan', 'keputusan'], true))
        <div class="tengah tebal kapital" style="margin-bottom: 12px">{{ $n['pembuat'] ?? '' }}</div>

        {{-- Konsiderans: Menimbang a., b. · Mengingat 1., 2. --}}
        <table class="konsideran">
            @foreach ([['Menimbang', $n['menimbang'] ?? [], 'huruf'], ['Mengingat', $n['mengingat'] ?? [], 'angka']] as [$label, $butir, $gaya])
                @foreach ($butir as $i => $b)
                    <tr>
                        <td style="width: 2.6cm">{{ $i === 0 ? $label : '' }}</td>
                        <td style="width: 0.5cm">{{ $i === 0 ? ':' : '' }}</td>
                        <td style="width: 0.7cm">{{ $gaya === 'huruf' ? TataNaskah::huruf($i) : $i + 1 }}.</td>
                        <td>{{ $b }}</td>
                    </tr>
                @endforeach
                <tr><td colspan="4" style="height: 6px"></td></tr>
            @endforeach
        </table>

        <div class="tengah tebal" style="margin: 6px 0 8px 0">MEMUTUSKAN:</div>
        <table class="konsideran">
            <tr>
                <td style="width: 2.6cm">Menetapkan</td>
                <td style="width: 0.5cm">:</td>
                <td class="tebal kapital" style="text-align: left">{{ TataNaskah::menetapkan($n) }}</td>
            </tr>
        </table>

        {{-- badan: Pasal bila ada, selain itu diktum KESATU, KEDUA… (RS memakai keduanya) --}}
        @if (! empty($n['pasal']))
            @foreach ($n['pasal'] as $i => $p)
                <div class="tengah tebal" style="margin: 10px 0 4px 0">Pasal {{ $i + 1 }}</div>
                <div class="rata">{!! $html($p['isi'] ?? '') !!}</div>
            @endforeach
        @else
            <table class="konsideran" style="margin-top: 8px">
                @foreach ($n['diktum'] ?? [] as $i => $d)
                    @foreach (Cetak::potongBlok($html($d['isi'] ?? '')) ?: ['-'] as $j => $blok)
                        <tr>
                            <td class="tebal" style="width: 2.6cm">{{ $j === 0 ? (TataNaskah::DIKTUM[$i] ?? 'KE-' . ($i + 1)) : '' }}</td>
                            <td style="width: 0.5cm">{{ $j === 0 ? ':' : '' }}</td>
                            <td>{!! $blok !!}</td>
                        </tr>
                    @endforeach
                @endforeach
            </table>
        @endif
    @endif

    {{-- ══ SURAT EDARAN: Yth · isi (hal. 27–29) ══ --}}
    @if ($format === 'edaran')
        <div style="margin-bottom: 8px">Yth.</div>
        <ol style="margin-bottom: 10px">@foreach ($n['yth'] ?? [] as $y)<li>{{ $y }}</li>@endforeach</ol>
        <div class="rata">{!! $html($n['isi'] ?? '') !!}</div>
    @endif

    {{-- ══ SURAT UNDANGAN: nomor · sifat · lampiran · hal · Yth · pembuka · acara · penutup (hal. 40–41) ══ --}}
    @if ($format === 'undangan')
        <table class="konsideran" style="width: 11cm; margin-bottom: 14px">
            @foreach (['Nomor' => $dok->dok_no ?: '…', 'Sifat' => $n['sifat'] ?? '', 'Lampiran' => $n['lampiran'] ?? '', 'Hal' => $n['hal'] ?? ''] as $lb => $v)
                <tr><td style="width: 2.2cm">{{ $lb }}</td><td style="width: 0.4cm">:</td><td style="text-align: left">{{ $v }}</td></tr>
            @endforeach
        </table>
        <div>Yth.</div>
        <div style="margin-bottom: 12px">@foreach ($n['yth'] ?? [] as $y)<div>{{ $y }}</div>@endforeach</div>
        <div class="rata">{!! $html($n['pembuka'] ?? '') !!}</div>
        <table class="konsideran" style="margin: 6px 0 8px 1.2cm; width: auto">
            @foreach (TataNaskah::ACARA as $ka => $la)
                <tr><td style="width: 2.4cm">{{ $la }}</td><td style="width: 0.4cm">:</td><td style="text-align: left">{{ $n['acara'][$ka] ?? '' }}</td></tr>
            @endforeach
        </table>
        <div class="rata">{!! $html($n['penutup'] ?? '') !!}</div>
    @endif

    {{-- ══ PEDOMAN · PANDUAN · PROGRAM KERJA: lampiran Perdir + BAB (hal. 13, 15–22) ══ --}}
    @if ($format === 'bab')
        @php $romawi = (TataNaskah::SISTEMATIKA[$n['sistematika'] ?? '']['1'] ?? 'BAB') === 'romawi'; @endphp
        @if ($lampiran)
            <table class="lampiran">
                <tr><td class="kunci">LAMPIRAN</td><td class="titik">:</td><td class="kapital">{{ $lampiran['nama'] }}</td></tr>
                <tr><td>NOMOR</td><td>:</td><td>{{ $lampiran['nomor'] }}</td></tr>
                <tr><td>TENTANG</td><td>:</td><td class="kapital">{{ $lampiran['tentang'] }}</td></tr>
            </table>
        @endif
        <div class="tengah tebal kapital" style="margin-bottom: 16px">{{ $n['judul'] ?? $dok->dok_judul }}</div>

        @foreach ($n['bab'] ?? [] as $i => $b)
            @if ($romawi)
                <div class="tebal" style="margin: 10px 0 4px 0">{{ TataNaskah::romawi($i + 1) }}. {{ mb_strtoupper($b['judul'] ?? '') }}</div>
            @else
                <div class="bab-judul {{ $i > 0 ? 'bab-baru' : '' }}">BAB {{ TataNaskah::romawi($i + 1) }}<br>{{ mb_strtoupper($b['judul'] ?? '') }}</div>
            @endif
            @if (filled($html($b['isi'] ?? '')))
                <div class="rata">{!! $html($b['isi']) !!}</div>
            @endif
            @foreach ($b['sub'] ?? [] as $j => $s)
                <div class="sub-judul" style="{{ $romawi ? 'margin-left: 0.6cm' : '' }}">{{ TataNaskah::huruf($j, true) }}. {{ $s['judul'] ?? '' }}</div>
                <div class="rata" style="margin-left: 0.6cm">{!! $html($s['isi'] ?? '') !!}</div>
            @endforeach
        @endforeach

        @if (filled($html($n['kepustakaan'] ?? '')))
            <div class="bab-judul bab-baru">KEPUSTAKAAN</div>
            <div>{!! $html($n['kepustakaan']) !!}</div>
        @endif
    @endif

    {{-- ══ UMUM: blok html / gambar berurutan (laporan, notulen, surat, bukti, hasil OCR) ══ --}}
    @if ($format === 'umum')
        @foreach ($n['blok'] ?? [] as $b)
            @if (($b['tipe'] ?? '') === 'gambar')
                @php $g = TataNaskah::pathGambar($b['src'] ?? null); @endphp
                @if ($g)
                    <div class="tengah" style="margin: 6px 0; page-break-inside: avoid">
                        <img src="{{ $g }}" style="max-width: 16cm; max-height: 19.5cm">
                    </div>
                @endif
            @else
                <div class="rata blok-umum">{!! $html($b['html'] ?? '') !!}</div>
            @endif
        @endforeach
    @endif

    {{-- ══ KAKI ══ --}}
    @if ($format !== 'umum' || $ttd || filled($kaki['jabatan'] ?? ''))
    <div class="kaki">
        @if (isset($kaki['mengetahui']))
            {{-- Program Kerja: Mengetahui (kiri) · Penyusun (kanan) — hal. 21–22 --}}
            <table style="width: 100%"><tr>
                <td style="width: 50%; vertical-align: top">Mengetahui,<br>{{ $kaki['mengetahui']['jabatan'] ?? '' }}<br><br><br><br><br>
                    <span class="tebal">{{ $kaki['mengetahui']['nama'] ?? '' }}</span></td>
                <td style="width: 50%; vertical-align: top">{{ $kaki['tempat'] ?? 'Tulungagung' }}, {{ $tanggal ?: '…' }}<br>Penyusun,<br>
                    {{ $kaki['penyusun']['jabatan'] ?? '' }}<br><br><br><br><span class="tebal">{{ $kaki['penyusun']['nama'] ?? '' }}</span></td>
            </tr></table>
        @else
            <div class="kaki-kanan">
                @if (in_array($format, ['peraturan', 'keputusan', 'bab'], true))
                    Ditetapkan di {{ $kaki['tempat'] ?? 'Tulungagung' }}<br>pada tanggal {{ $tanggal ?: '…' }}<br><br>
                @else
                    {{ $kaki['tempat'] ?? 'Tulungagung' }}, {{ $tanggal ?: '…' }}<br>
                @endif
                <span class="kapital">{{ ($kaki['jabatan'] ?? '') ?: ($ttd['jabatan'] ?? '') }}</span>
                @if ($ttdImg)
                    <br><img src="{{ $ttdImg }}" style="height: 2.2cm; margin: 2px 0"><br>
                @else
                    <br><br><br><br><br>
                @endif
                <span class="tebal">{{ $ttd['nama'] ?? ($kaki['nama'] ?? '') }}</span>
                @if (filled($kaki['nik'] ?? ''))<br>NIK. {{ $kaki['nik'] }}@endif
                @if ($ttd)
                    <div style="font-size: 7.5pt; color: #555; margin-top: 3px">Ditandatangani elektronik {{ \Carbon\Carbon::parse($ttd['waktu'])->format('d/m/Y H:i') }} · kode {{ $ttd['verifikasi'] }}</div>
                @endif
            </div>
        @endif
    </div>
    @endif

    @if (! empty($n['tembusan']))
        <div style="margin-top: 14px; font-size: 10pt; page-break-inside: avoid">Tembusan:
            <ol>@foreach ($n['tembusan'] as $t)<li>{{ $t }}</li>@endforeach</ol>
        </div>
    @endif
</body>
</html>
