{{-- Cetak SPO (DomPDF) — format Pedoman Tata Naskah hal. 25–26, sama dengan SPO 382 yang terbit.
     Heading 1 (judul · No. Dokumen · No. Revisi · Halaman) = elemen fixed → tercetak tiap halaman.
     Heading 2 (STANDAR PROSEDUR OPERASIONAL · Tanggal Terbit · Ditetapkan) = baris di awal isi → hanya
     halaman pertama. DomPDF tanpa flex/grid: semua tata letak memakai tabel (docs/ttd-pattern-pdf-print.md).
     Variabel: $dok, $spo, $penetap, $bagian, $tanggal, $kolomKop (cm), $kolomIsi (cm). --}}
@php
    $izin = '<p><br><b><strong><i><em><u><ol><ul><li><table><thead><tbody><tr><td><th><sup><sub>';
    $logo = public_path('images/Logo Persegi.png');
    $tanda = ['DRAF' => 'DRAF', 'TIDAK_BERLAKU' => 'TIDAK BERLAKU', 'DICABUT' => 'DICABUT'][$dok->status] ?? null;
    $tinggiH1 = 2.76; // cm — tinggi terukur kop (judul 1,35 + nomor 0,85 + padding & garis)
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
        @page { size: A4; margin: {{ 1.5 + $tinggiH1 }}cm 2cm 1.5cm 2cm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Arial', sans-serif; font-size: 10pt; line-height: 1.15; color: #000; }
        table.kop, table.isi { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.dalam { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.dalam td { border: 1px solid #000; padding: 3px 4px; line-height: 1.15; }
        table.kop td, table.isi td { border: 1px solid #000; padding: 3px 4px; vertical-align: middle; }
        table.isi td { padding: 4px 6px; }
        .h1 { position: fixed; top: -{{ $tinggiH1 }}cm; left: 0; right: 0; }
        .judul { font-size: 11pt; font-weight: bold; text-align: center; text-transform: uppercase; }
        .kecil { font-size: 9pt; }
        .tebal { font-weight: bold; }
        .tengah { text-align: center; }
        table.isi td.label { font-weight: bold; vertical-align: top; }
        table.isi td.konten { vertical-align: top; text-align: justify; }
        table.isi td.konten p { margin: 0 0 2px 0; }
        table.isi td.konten ol, table.isi td.konten ul { margin: 0 0 1px 0; padding-left: 16px; }
        table.isi td.konten li { margin-bottom: 1px; }
        /* DomPDF mengabaikan atribut type pada <ol> — dipetakan lewat CSS */
        ol[type="1"] { list-style-type: decimal; }
        ol[type="A"] { list-style-type: upper-alpha; }
        ol[type="a"] { list-style-type: lower-alpha; }
        ol[type="I"] { list-style-type: upper-roman; }
        ol[type="i"] { list-style-type: lower-roman; }
        table.isi td.konten table td { border: 1px solid #555; padding: 2px 4px; }
        tr { page-break-inside: auto; }
        .tanda { position: fixed; top: 9cm; left: 0; right: 0; text-align: center; font-size: 64pt; font-weight: bold;
                 color: #d9d9d9; transform: rotate(-30deg); z-index: -1; }
    </style>
</head>
<body>
    @if ($tanda)
        <div class="tanda">{{ $tanda }}</div>
    @endif

    {{-- HEADING 1 — setiap halaman. Tabel bersarang + lebar persen: DomPDF tidak andal dengan
         rowspan berlebar tetap (kolom kop bergeser). Persen dihitung dari lebar kop (cm). --}}
    @php
        $total = array_sum($kolomKop);
        $pct = fn ($cm) => round($cm / $total * 100, 3) . '%';
        [$k1, $k2, $k3, $k4] = $kolomKop;
        $kanan = $k2 + $k3 + $k4;
        $pctKanan = fn ($cm) => round($cm / $kanan * 100, 3) . '%';
    @endphp
    <div class="h1">
        <table class="kop">
            <tr>
                <td class="tengah" style="width: {{ $pct($k1) }}; padding: 2px">
                    @if (is_file($logo))
                        <img src="{{ $logo }}" style="width: 1.9cm; height: auto;">
                    @endif
                </td>
                <td style="width: {{ $pct($kanan) }}; padding: 0; vertical-align: top">
                    <table class="dalam">
                        <tr>
                            <td colspan="3" class="judul" style="height: 1.35cm; border-top: 0; border-left: 0; border-right: 0">{{ $dok->dok_judul }}</td>
                        </tr>
                        <tr>
                            <td style="width: {{ $pctKanan($k2) }}; height: 0.85cm; vertical-align: top; border-left: 0; border-bottom: 0">
                                <span class="kecil">No. Dokumen</span><br><span class="tebal" style="font-size: 9.5pt; white-space: nowrap">{{ $dok->dok_no ?: '-' }}</span></td>
                            <td class="tengah" style="width: {{ $pctKanan($k3) }}; vertical-align: top; border-bottom: 0">
                                <span class="kecil">No. Revisi</span><br><span class="tebal">{{ $dok->no_revisi }}</span></td>
                            <td class="tengah" style="width: {{ $pctKanan($k4) }}; vertical-align: top; border-right: 0; border-bottom: 0">
                                <span class="kecil">Halaman</span></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

    {{-- HEADING 2 — hanya halaman pertama; struktur bersarang yang sama dengan heading 1 supaya
         garis kolom segaris. --}}
    <table class="kop">
        <tr>
            <td class="tengah tebal" style="width: {{ $pct($k1) }}; height: 2.4cm; border-top: 0">STANDAR PROSEDUR OPERASIONAL</td>
            <td style="width: {{ $pct($kanan) }}; padding: 0; vertical-align: top; border-top: 0">
                <table class="dalam">
                    <tr>
                        <td style="width: {{ $pctKanan($k2) }}; height: 2.4cm; vertical-align: top; border: 0; border-right: 1px solid #000">
                            <span class="kecil">Tanggal Terbit</span><br><br>{{ $tanggal ?: '-' }}</td>
                        <td class="tengah" style="width: {{ $pctKanan($k3 + $k4) }}; vertical-align: top; border: 0">
                            Ditetapkan,<br>{{ $penetap['jabatan'] ?? 'Direktur' }}
                            <br><br><br><br>
                            <span class="tebal">{{ $penetap['nama'] ?? '' }}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ISI --}}
    <table class="isi" style="margin-top: 0.35cm">
        @foreach ($bagian as $k => $label)
            @php
                $html = trim((string) ($spo[$k] ?? ''));
                $potong = \App\Http\Controllers\DokumenCetakController::potongBlok(strip_tags($html, $izin)) ?: ['-'];
                $n = count($potong);
            @endphp
            @continue($html === '' && $k === 'dokumenTerkait')
            @foreach ($potong as $i => $blok)
                @php
                    $atas = $i === 0 ? '1px solid #000' : '0';
                    $bawah = $i === $n - 1 ? '1px solid #000' : '0';
                @endphp
                <tr>
                    <td class="label" style="width: {{ round($kolomIsi[0] / array_sum($kolomIsi) * 100, 3) }}%; border-top: {{ $atas }}; border-bottom: {{ $bawah }}">{{ $i === 0 ? $label : '' }}</td>
                    <td class="konten" style="width: {{ round($kolomIsi[1] / array_sum($kolomIsi) * 100, 3) }}%; border-top: {{ $atas }}; border-bottom: {{ $bawah }}; padding-top: {{ $i ? 0 : 4 }}px; padding-bottom: {{ $i === $n - 1 ? 4 : 0 }}px">{!! $blok !!}</td>
                </tr>
            @endforeach
        @endforeach
    </table>
</body>
</html>
