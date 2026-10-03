<?php

namespace App\Http\Controllers;

use App\Services\DokumenService;
use App\Support\Naskah;
use App\Support\TataNaskah;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cetak dokumen registri: SPO (PDF DomPDF / DOCX PhpWord) dan pembuka berkas tercatat.
 * Format SPO: Pedoman Tata Naskah hal. 25–26 + aturan rumah (A4, Arial 10, marjin SPO
 * 1,5 · 2,0 · 1,5 · 2,0 cm; heading 1 tiap halaman, heading 2 hanya halaman pertama).
 */
class DokumenCetakController extends Controller
{
    /** Lebar kolom kop SPO (cm) — sama dengan SPO 382 yang sudah terbit. */
    private const KOLOM_KOP = [3.5, 5.5, 4.0, 4.0];
    private const KOLOM_ISI = [3.6, 13.4];

    // ─── Titik masuk: pilih pencetak menurut format naskah (TataNaskah::FORMAT_JENIS) ─────────

    public function pdf(int $dokId): Response
    {
        return $this->formatDokumen($dokId) === 'spo' ? $this->spoPdf($dokId) : $this->naskahPdf($dokId);
    }

    public function docx(int $dokId): Response
    {
        return $this->formatDokumen($dokId) === 'spo' ? $this->spoDocx($dokId) : $this->naskahDocx($dokId);
    }

    /** Pencetak dipilih menurut ISI yang tersimpan: isi.spo → cetak SPO, isi.naskah → format naskahnya. */
    private function formatDokumen(int $dokId): ?string
    {
        $data = DokumenService::baca($dokId);
        abort_unless($data, 404);
        $json = $data[1];
        if (filled($json['isi']['spo'] ?? null)) {
            return 'spo';
        }
        abort_if(blank($json['isi']['naskah'] ?? null), 404, 'Isi dokumen belum disusun.');

        return $json['isi']['naskah']['format'] ?? 'umum';
    }

    /** DomPDF hanya membaca berkas di dalam chroot: aplikasi + akar arsip impor (gambar blok hasil konversi). */
    private function chroot(): array
    {
        return array_values(array_filter([realpath(base_path()), realpath((string) config('dokumen.arsip_impor'))]));
    }

    /** Label DRAF di kepala halaman DOCX (PhpWord tidak punya tanda air teks). */
    private function labelDraf($section, ?array $ttd, string $status): void
    {
        $label = ['TIDAK_BERLAKU' => 'TIDAK BERLAKU', 'DICABUT' => 'DICABUT'][$status] ?? ($ttd ? null : 'DRAF — belum ditandatangani');
        if ($label) {
            foreach ($section->getHeaders() as $h) {
                $h->addText($label, ['color' => 'B0B0B0', 'bold' => true, 'size' => 9], ['alignment' => 'left', 'spaceAfter' => 0]);
            }
        }
    }

    private function gambarDocx($wadah, string $path, float $lebarMaksCm, float $tinggiMaksCm): void
    {
        [$w, $h] = @getimagesize($path) ?: [0, 0];
        if (! $w || ! $h) {
            return;
        }
        $k = min($lebarMaksCm / ($w / 96 * 2.54), $tinggiMaksCm / ($h / 96 * 2.54), 1.6);
        $wadah->addImage($path, ['width' => Converter::cmToPoint($w / 96 * 2.54 * $k), 'height' => Converter::cmToPoint($h / 96 * 2.54 * $k), 'alignment' => 'center']);
    }

    private function dataNaskah(int $dokId): array
    {
        $data = DokumenService::baca($dokId);
        abort_unless($data, 404);
        [$row, $json] = $data;
        $n = $json['isi']['naskah'] ?? [];
        abort_if(blank($n['format'] ?? null) && ! Naskah\Kerangka::versi2($n), 404, 'Isi naskah belum disusun.');
        $this->periksaAkses($row);
        $row->dok_judul = DokumenService::judul($row, $json);
        $tgl = ($n['kaki']['tanggal'] ?? '') ?: $row->tgl_terbit;

        return [
            'dok' => $row, 'n' => $n, 'ttd' => $json['ttd'] ?? null,
            'lampiran' => ($n['format'] ?? null) === 'bab' ? TataNaskah::lampiranDari($row->induk_id ? (int) $row->induk_id : null) : null,
            'tanggal' => DokumenService::tanggalIndonesia($tgl),
        ];
    }

    /** PDF dibuka di tab (inline) atau diunduh (?unduh=1 — tombol "Simpan & PDF" di penyusun). */
    private static function sajian(): string
    {
        return request()->boolean('unduh') ? 'attachment' : 'inline';
    }

    /** Tanda air: belum ditandatangani = DRAF; dokumen usang tetap ditandai. */
    public static function tandaAir(string $status, ?array $ttd): ?string
    {
        return ['TIDAK_BERLAKU' => 'TIDAK BERLAKU', 'DICABUT' => 'DICABUT'][$status] ?? ($ttd ? null : 'DRAF');
    }

    /** Naskah versi 2 (isi teks + lembar gaya config/tata_naskah.php) — App\Support\Naskah. */
    public static function pdfVersi2(object $dok, array $n, ?array $ttd): string
    {
        $unsur = Naskah\Susun::unsur($dok, $n, $ttd);
        $html = (new Naskah\CetakPdf(self::tandaAir($dok->status, $ttd), public_path('images/kop/kop_portrait.png')))
            ->html($dok->dok_judul, $unsur);
        $pdf = Pdf::loadHTML($html)->setPaper('A4', 'portrait')
            ->setOption('chroot', array_values(array_filter([realpath(base_path()), realpath((string) config('dokumen.arsip_impor'))])));

        return $pdf->output();
    }

    public static function docxVersi2(object $dok, array $n, ?array $ttd): string
    {
        $tanda = self::tandaAir($dok->status, $ttd);

        return (new Naskah\CetakDocx($tanda === 'DRAF' ? 'DRAF — belum ditandatangani' : $tanda, public_path('images/kop/kop_portrait.png')))
            ->simpan(Naskah\Susun::unsur($dok, $n, $ttd));
    }

    private function naskahPdf(int $dokId): Response
    {
        $d = $this->dataNaskah($dokId);
        if (Naskah\Kerangka::versi2($d['n'])) {
            $isi = self::pdfVersi2($d['dok'], $d['n'], $d['ttd']);
            DokumenService::catat($dokId, 'CETAK', 'PDF');

            return response($isi, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => self::sajian() . '; filename="' . $this->namaBerkasNaskah($d['dok'], 'pdf') . '"',
                'Cache-Control' => 'no-store, max-age=0',   // PDF dibangkitkan dari JSON — jangan disajikan dari cache
            ]);
        }
        $pdf = Pdf::loadView('pages.dokumen.cetak.naskah-pdf', $d)->setPaper('A4', 'portrait')
            ->setOption('chroot', $this->chroot());
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        if ($d['n']['format'] === 'bab') {
            // nomor halaman di tengah bawah untuk naskah berbab (Pedoman/Panduan/Program)
            $font = $dompdf->getFontMetrics()->getFont('Arial', 'normal');
            $dompdf->getCanvas()->page_script(function ($nomor, $jumlah, $kanvas, $metrik) use ($font) {
                $t = (string) $nomor;
                $kanvas->text((595.28 - $metrik->getTextWidth($t, $font, 10)) / 2, 841.89 - 36, $t, $font, 10);
            });
        }
        DokumenService::catat($dokId, 'CETAK', 'PDF');

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => self::sajian() . '; filename="' . $this->namaBerkasNaskah($d['dok'], 'pdf') . '"',
                'Cache-Control' => 'no-store, max-age=0',   // PDF dibangkitkan dari JSON — jangan disajikan dari cache
        ]);
    }

    private function namaBerkasNaskah(object $dok, string $ext): string
    {
        $jenis = DokumenService::jenis()[$dok->jenis_kode]->nama ?? $dok->jenis_kode;
        $no = $dok->dok_no ? Str::before($dok->dok_no, '/') . ' - ' : '';

        return Str::limit(preg_replace('/[^\pL\pN ,.-]+/u', ' ', "{$jenis} {$no}{$dok->dok_judul}"), 110, '') . '.' . $ext;
    }

    /** DOCX naskah dinas: kop resmi sebagai gambar latar header (seperti pasang_kop.py), isi dari JSON. */
    private function naskahDocx(int $dokId): Response
    {
        $d = $this->dataNaskah($dokId);
        if (Naskah\Kerangka::versi2($d['n'])) {
            $tmp = self::docxVersi2($d['dok'], $d['n'], $d['ttd']);
            DokumenService::catat($dokId, 'CETAK', 'DOCX');

            return response()->download($tmp, $this->namaBerkasNaskah($d['dok'], 'docx'))->deleteFileAfterSend(true);
        }
        ['dok' => $dok, 'n' => $n, 'lampiran' => $lampiran, 'tanggal' => $tanggal] = $d;
        $word = new PhpWord();
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(11);
        $cm = fn (float $v) => (int) Converter::cmToTwip($v);
        $section = $word->addSection(['paperSize' => 'A4', 'marginTop' => $cm(7.37), 'marginLeft' => $cm(2.5),
            'marginRight' => $cm(2.5), 'marginBottom' => $cm(2.0), 'headerHeight' => 0]);
        $kop = public_path('images/kop/kop_portrait.png');
        if (is_file($kop)) {
            $section->addHeader()->addImage($kop, [
                'width' => Converter::cmToPoint(21.0), 'height' => Converter::cmToPoint(29.7),
                'positioning' => 'absolute', 'posHorizontalRel' => 'page', 'posVerticalRel' => 'page',
                'posHorizontal' => 'absolute', 'posVertical' => 'absolute', 'marginLeft' => 0, 'marginTop' => 0,
                'wrappingStyle' => 'behind',
            ]);
        }
        if ($n['format'] === 'bab') {
            $section->addFooter()->addPreserveText('{PAGE}', ['size' => 10], ['alignment' => 'center']);
        }
        $this->labelDraf($section, $d['ttd'], $dok->status);
        if ($n['format'] === 'umum') {
            foreach ($n['blok'] ?? [] as $b) {
                if (($b['tipe'] ?? '') === 'gambar') {
                    if ($g = TataNaskah::pathGambar($b['src'] ?? null)) {
                        $this->gambarDocx($section, $g, 16.0, 19.5);
                    }
                } elseif (filled(trim(strip_tags($b['html'] ?? '')))) {
                    $this->tulisHtml($word, $section, $this->rapikanHtml($b['html']));
                }
            }
        }
        $tengahTebal = ['bold' => true];
        $tengah = ['alignment' => 'center', 'spaceAfter' => 0];
        $rapat = ['spaceAfter' => 60];
        $tanpaGaris = ['borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMargin' => 0];

        $konsideran = function (array $baris) use ($section, $cm, $tanpaGaris) {
            $t = $section->addTable($tanpaGaris);
            foreach ($baris as [$label, $tanda, $nomor, $isi, $tebal]) {
                $t->addRow();
                $t->addCell($cm(2.6))->addText($label, [], ['spaceAfter' => 60]);
                $t->addCell($cm(0.5))->addText($tanda, [], ['spaceAfter' => 60]);
                if ($nomor !== null) {
                    $t->addCell($cm(0.7))->addText($nomor, [], ['spaceAfter' => 60]);
                    $t->addCell($cm(12.2))->addText(htmlspecialchars($isi), $tebal ? ['bold' => true] : [], ['spaceAfter' => 60, 'alignment' => 'both']);
                } else {
                    $t->addCell($cm(12.9), ['gridSpan' => 2])->addText(htmlspecialchars($isi), $tebal ? ['bold' => true] : [], ['spaceAfter' => 60, 'alignment' => $tebal ? 'left' : 'both']);
                }
            }
        };

        if (in_array($n['format'], ['peraturan', 'keputusan', 'edaran'], true)) {
            $section->addText(htmlspecialchars(mb_strtoupper($n['judulJenis'] ?? '')), $tengahTebal, $tengah);
            $section->addText(htmlspecialchars('NOMOR ' . ($dok->dok_no ?: '…')), $tengahTebal, $tengah);
            $section->addText('TENTANG', $tengahTebal, ['alignment' => 'center', 'spaceBefore' => 120, 'spaceAfter' => 0]);
            $section->addText(htmlspecialchars(mb_strtoupper($n['tentang'] ?? '')), $tengahTebal, ['alignment' => 'center', 'spaceAfter' => 240]);
        }
        if (in_array($n['format'], ['peraturan', 'keputusan'], true)) {
            $section->addText(htmlspecialchars(mb_strtoupper($n['pembuat'] ?? '')), $tengahTebal, ['alignment' => 'center', 'spaceAfter' => 200]);
            $baris = [];
            foreach ($n['menimbang'] ?? [] as $i => $b) {
                $baris[] = [$i ? '' : 'Menimbang', $i ? '' : ':', TataNaskah::huruf($i) . '.', $b, false];
            }
            foreach ($n['mengingat'] ?? [] as $i => $b) {
                $baris[] = [$i ? '' : 'Mengingat', $i ? '' : ':', ($i + 1) . '.', $b, false];
            }
            $konsideran($baris);
            $section->addText('MEMUTUSKAN:', $tengahTebal, ['alignment' => 'center', 'spaceBefore' => 120, 'spaceAfter' => 120]);
            $konsideran([['Menetapkan', ':', null, mb_strtoupper(TataNaskah::menetapkan($n)), true]]);
            if (! empty($n['pasal'])) {                 // Pasal bila ada, selain itu diktum
                foreach ($n['pasal'] as $i => $p) {
                    $section->addText('Pasal ' . ($i + 1), $tengahTebal, ['alignment' => 'center', 'spaceBefore' => 160, 'spaceAfter' => 60]);
                    $this->tulisHtml($word, $section, $this->rapikanHtml($p['isi'] ?: '<p>-</p>'));
                }
            } else {
                $t = $section->addTable($tanpaGaris);
                foreach ($n['diktum'] ?? [] as $i => $dk) {
                    $t->addRow();
                    $t->addCell($cm(2.6))->addText(TataNaskah::DIKTUM[$i] ?? 'KE-' . ($i + 1), ['bold' => true], $rapat);
                    $t->addCell($cm(0.5))->addText(':', [], $rapat);
                    $this->tulisHtml($word, $t->addCell($cm(12.9)), $this->rapikanHtml($dk['isi'] ?: '<p>-</p>'));
                }
            }
        }
        if ($n['format'] === 'edaran') {
            $section->addText('Yth.', [], $rapat);
            foreach ($n['yth'] ?? [] as $i => $y) {
                $section->addText(($i + 1) . '. ' . htmlspecialchars($y), [], ['indentation' => ['left' => 360], 'spaceAfter' => 40]);
            }
            $this->tulisHtml($word, $section, $this->rapikanHtml($n['isi'] ?: '<p>-</p>'));
        }
        if ($n['format'] === 'undangan') {
            $t = $section->addTable($tanpaGaris);
            foreach (['Nomor' => $dok->dok_no ?: '…', 'Sifat' => $n['sifat'] ?? '', 'Lampiran' => $n['lampiran'] ?? '', 'Hal' => $n['hal'] ?? ''] as $lb => $v) {
                $t->addRow();
                $t->addCell($cm(2.2))->addText($lb, [], ['spaceAfter' => 0]);
                $t->addCell($cm(0.4))->addText(':', [], ['spaceAfter' => 0]);
                $t->addCell($cm(8.4))->addText(htmlspecialchars((string) $v), [], ['spaceAfter' => 0]);
            }
            $section->addText('Yth.', [], ['spaceBefore' => 240, 'spaceAfter' => 0]);
            foreach ($n['yth'] ?? [] as $y) {
                $section->addText(htmlspecialchars($y), [], ['spaceAfter' => 0]);
            }
            $section->addTextBreak();
            $this->tulisHtml($word, $section, $this->rapikanHtml($n['pembuka'] ?: '<p>-</p>'));
            $t = $section->addTable($tanpaGaris + ['indent' => new \PhpOffice\PhpWord\ComplexType\TblWidth($cm(1.2))]);
            foreach (TataNaskah::ACARA as $ka => $la) {
                $t->addRow();
                $t->addCell($cm(2.4))->addText($la, [], ['spaceAfter' => 40]);
                $t->addCell($cm(0.4))->addText(':', [], ['spaceAfter' => 40]);
                $t->addCell($cm(10.0))->addText(htmlspecialchars((string) ($n['acara'][$ka] ?? '')), [], ['spaceAfter' => 40]);
            }
            $section->addTextBreak();
            $this->tulisHtml($word, $section, $this->rapikanHtml($n['penutup'] ?: '<p>-</p>'));
        }
        if ($n['format'] === 'bab') {
            $romawi = (TataNaskah::SISTEMATIKA[$n['sistematika'] ?? ''][1] ?? 'BAB') === 'romawi';
            if ($lampiran) {
                $t = $section->addTable(['borderSize' => 0, 'borderColor' => 'FFFFFF', 'layout' => 'fixed', 'cellMargin' => 30,
                    'indent' => new \PhpOffice\PhpWord\ComplexType\TblWidth($cm(7.0))]);
                foreach ([['LAMPIRAN', mb_strtoupper($lampiran['nama'])], ['NOMOR', $lampiran['nomor']], ['TENTANG', mb_strtoupper($lampiran['tentang'])]] as [$k, $v]) {
                    $t->addRow();
                    $t->addCell($cm(2.5))->addText($k, ['size' => 10], ['spaceAfter' => 0]);
                    $t->addCell($cm(0.5))->addText(':', ['size' => 10], ['spaceAfter' => 0]);
                    $t->addCell($cm(6.0))->addText(htmlspecialchars($v), ['size' => 10], ['spaceAfter' => 0]);
                }
                $section->addTextBreak();
            }
            $section->addText(htmlspecialchars(mb_strtoupper($n['judul'] ?? $dok->dok_judul)), $tengahTebal, ['alignment' => 'center', 'spaceAfter' => 240]);
            foreach ($n['bab'] ?? [] as $i => $b) {
                if ($romawi) {
                    $section->addText(TataNaskah::romawi($i + 1) . '. ' . htmlspecialchars(mb_strtoupper($b['judul'] ?? '')), ['bold' => true], ['spaceBefore' => 160, 'spaceAfter' => 60]);
                } else {
                    $section->addText('BAB ' . TataNaskah::romawi($i + 1), $tengahTebal, ['alignment' => 'center', 'spaceAfter' => 0, 'pageBreakBefore' => $i > 0]);
                    $section->addText(htmlspecialchars(mb_strtoupper($b['judul'] ?? '')), $tengahTebal, ['alignment' => 'center', 'spaceAfter' => 200]);
                }
                if (filled(trim(strip_tags($b['isi'] ?? '')))) {
                    $this->tulisHtml($word, $section, $this->rapikanHtml($b['isi']));
                }
                foreach ($b['sub'] ?? [] as $j => $sb) {
                    $section->addText(TataNaskah::huruf($j, true) . '. ' . htmlspecialchars($sb['judul'] ?? ''), ['bold' => true], ['spaceBefore' => 120, 'spaceAfter' => 40, 'indentation' => ['left' => $romawi ? 340 : 0]]);
                    if (filled(trim(strip_tags($sb['isi'] ?? '')))) {
                        $this->tulisHtml($word, $section, $this->rapikanHtml($sb['isi']));
                    }
                }
            }
            if (filled(trim(strip_tags($n['kepustakaan'] ?? '')))) {
                $section->addText('KEPUSTAKAAN', $tengahTebal, ['alignment' => 'center', 'pageBreakBefore' => true, 'spaceAfter' => 200]);
                $this->tulisHtml($word, $section, $this->rapikanHtml($n['kepustakaan']));
            }
        }

        // Kaki (tanda tangan diikat dengan baris sebelumnya: keepNext)
        $k = $n['kaki'] ?? [];
        $ttd = $d['ttd'];
        $ttdImg = $ttd ? DokumenService::gambarTtd($ttd['kode'] ?? null) : null;
        if ($n['format'] === 'umum' && ! $ttd && blank($k['jabatan'] ?? '')) {
            goto simpanDocx;                                    // dokumen umum tanpa penandatangan
        }
        $ikat = ['spaceAfter' => 0, 'keepNext' => true, 'keepLines' => true];
        $section->addTextBreak(1, [], $ikat);
        if (isset($k['mengetahui'])) {
            $t = $section->addTable($tanpaGaris);
            $t->addRow();
            $kiri = $t->addCell($cm(8));
            $kanan = $t->addCell($cm(8));
            foreach (['Mengetahui,', $k['mengetahui']['jabatan'] ?? ''] as $x) $kiri->addText(htmlspecialchars($x), [], $ikat);
            $kiri->addTextBreak(3);
            $kiri->addText(htmlspecialchars($k['mengetahui']['nama'] ?? ''), ['bold' => true], ['spaceAfter' => 0]);
            foreach (["{$k['tempat']}, " . ($tanggal ?: '…'), 'Penyusun,', $k['penyusun']['jabatan'] ?? ''] as $x) $kanan->addText(htmlspecialchars($x), [], $ikat);
            $kanan->addTextBreak(3);
            $kanan->addText(htmlspecialchars($k['penyusun']['nama'] ?? ''), ['bold' => true], ['spaceAfter' => 0]);
        } else {
            $kanan = ['indentation' => ['left' => $cm(8.5)]] + $ikat;
            if (! in_array($n['format'], ['edaran', 'undangan'], true)) {
                $section->addText('Ditetapkan di ' . htmlspecialchars($k['tempat'] ?? 'Tulungagung'), [], $kanan);
                $section->addText('pada tanggal ' . ($tanggal ?: '…'), [], $kanan);
                $section->addTextBreak(1, [], $kanan);
            } else {
                $section->addText(htmlspecialchars(($k['tempat'] ?? 'Tulungagung') . ', ' . ($tanggal ?: '…')), [], $kanan);
            }
            $section->addText(htmlspecialchars(mb_strtoupper(($k['jabatan'] ?? '') ?: ($ttd['jabatan'] ?? ''))), [], $kanan);
            if ($ttdImg) {
                // gambar di dalam paragraf berindentasi — PhpWord mengabaikan indentasi pada addImage langsung
                $section->addTextRun(['indentation' => ['left' => $cm(8.5)], 'spaceAfter' => 0, 'keepNext' => true])
                    ->addImage($ttdImg, ['height' => Converter::cmToPoint(2.0)]);
            } else {
                $section->addTextBreak(4, [], $kanan);
            }
            $section->addText(htmlspecialchars($ttd['nama'] ?? ($k['nama'] ?? '')), ['bold' => true], ['indentation' => ['left' => $cm(8.5)], 'spaceAfter' => 0]);
            if ($ttd) {
                $section->addText('Ditandatangani elektronik ' . \Carbon\Carbon::parse($ttd['waktu'])->format('d/m/Y H:i') . ' · kode ' . $ttd['verifikasi'],
                    ['size' => 7, 'color' => '555555'], ['indentation' => ['left' => $cm(8.5)]]);
            }
            if (filled($k['nik'] ?? '')) {
                $section->addText('NIK. ' . htmlspecialchars($k['nik']), [], ['indentation' => ['left' => $cm(8.5)]]);
            }
        }
        if (! empty($n['tembusan'])) {
            $section->addText('Tembusan:', ['size' => 10], ['spaceBefore' => 240, 'spaceAfter' => 0, 'keepNext' => true]);
            foreach ($n['tembusan'] as $i => $t) {
                $section->addText(($i + 1) . '. ' . htmlspecialchars($t), ['size' => 10], ['spaceAfter' => 0]);
            }
        }

        simpanDocx:
        $tmp = tempnam(sys_get_temp_dir(), 'naskah') . '.docx';
        $word->save($tmp, 'Word2007');
        DokumenService::catat($dokId, 'CETAK', 'DOCX');

        return response()->download($tmp, $this->namaBerkasNaskah($dok, 'docx'))->deleteFileAfterSend(true);
    }

    private function dataSpo(int $dokId): array
    {
        $data = DokumenService::baca($dokId);
        abort_unless($data, 404);
        [$row, $json] = $data;
        abort_if(blank($json['isi']['spo'] ?? null), 404, 'Isi SPO belum disusun.');
        $this->periksaAkses($row);
        $row->dok_judul = DokumenService::judul($row, $json);

        return [
            'dok' => $row,
            'ttd' => $json['ttd'] ?? null,
            'spo' => $json['isi']['spo'],
            'penetap' => $json['penandatangan']['ditetapkan'] ?? config('dokumen.pejabat.direktur'),
            'bagian' => DokumenService::BAGIAN_SPO,
            'tanggal' => DokumenService::tanggalIndonesia($row->tgl_terbit),
        ];
    }

    /**
     * Pecah HTML satu bagian SPO menjadi potongan per blok (paragraf / butir daftar) — DomPDF tidak
     * bisa memecah satu baris tabel melintasi halaman, jadi bagian panjang (PROSEDUR) dicetak sebagai
     * beberapa baris tanpa garis antara; butir daftar diberi atribut start supaya nomornya berlanjut.
     *
     * @return string[]
     */
    public static function potongBlok(string $html): array
    {
        $html = trim($html);
        if ($html === '') {
            return [];
        }
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div id="akar">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $akar = $dom->getElementById('akar') ?? $dom->documentElement;
        $hasil = [];
        foreach (iterator_to_array($akar->childNodes) as $node) {
            $nama = strtolower($node->nodeName);
            if (in_array($nama, ['ol', 'ul'], true)) {
                $no = (int) ($node->getAttribute('start') ?: 1);
                foreach (iterator_to_array($node->childNodes) as $li) {
                    if (strtolower($li->nodeName) !== 'li') {
                        continue;
                    }
                    $atribut = $nama === 'ol' ? ' start="' . $no . '"' . ($node->getAttribute('type') ? ' type="' . $node->getAttribute('type') . '"' : '') : '';
                    $hasil[] = "<{$nama}{$atribut}>" . $dom->saveHTML($li) . "</{$nama}>";
                    $no++;
                }
            } elseif ($node->nodeType === XML_TEXT_NODE) {
                if (trim($node->textContent) !== '') {
                    $hasil[] = '<p>' . e(trim($node->textContent)) . '</p>';
                }
            } else {
                $hasil[] = $dom->saveHTML($node);
            }
        }

        return $hasil ?: [$html];
    }

    /** BAB VI: naskah rahasia hanya untuk direktur & setingkat di bawahnya (admin/manager/direktur). */
    private function periksaAkses(object $row): void
    {
        if (in_array($row->klasifikasi, ['RAHASIA', 'SANGAT_RAHASIA', 'TERBATAS'], true)) {
            abort_unless(auth()->user()?->hasAnyRole(['admin', 'manager', 'direktur']), 403);
        }
    }

    private function namaBerkas(object $dok, string $ext): string
    {
        $no = $dok->dok_no ? Str::before($dok->dok_no, '/') . ' - ' : '';

        return 'SPO ' . $no . Str::limit(preg_replace('/[^\pL\pN ,.-]+/u', ' ', $dok->dok_judul), 90, '') . '.' . $ext;
    }

    private function spoPdf(int $dokId): Response
    {
        $d = $this->dataSpo($dokId);
        $pdf = Pdf::loadView('pages.dokumen.cetak.spo-pdf', $d + ['kolomKop' => self::KOLOM_KOP, 'kolomIsi' => self::KOLOM_ISI])
            ->setPaper('A4', 'portrait')->setOption('chroot', $this->chroot());
        // "Halaman n / N" di sel kop — counter(pages) CSS tidak didukung DomPDF, jadi digambar
        // lewat kanvas sesudah render. Koordinat (pt) dari marjin SPO + lebar kolom kop.
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();
        $pt = fn (float $cm) => $cm / 2.54 * 72;
        $xTengah = $pt(2.0 + self::KOLOM_KOP[0] + self::KOLOM_KOP[1] + self::KOLOM_KOP[2] + self::KOLOM_KOP[3] / 2);
        $y = $pt(1.5 + 1.35) + 23; // tepat di bawah label "Halaman" (diukur dari hasil render)
        $font = $dompdf->getFontMetrics()->getFont('Arial', 'bold');
        $dompdf->getCanvas()->page_script(function ($nomor, $jumlah, $kanvas, $metrik) use ($xTengah, $y, $font) {
            $teks = $nomor . ' / ' . $jumlah;
            $lebar = $metrik->getTextWidth($teks, $font, 10);
            $kanvas->text($xTengah - $lebar / 2, $y, $teks, $font, 10);
        });
        DokumenService::catat($dokId, 'CETAK', 'PDF');

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => self::sajian() . '; filename="' . $this->namaBerkas($d['dok'], 'pdf') . '"',
                'Cache-Control' => 'no-store, max-age=0',   // PDF dibangkitkan dari JSON — jangan disajikan dari cache
        ]);
    }

    private function spoDocx(int $dokId): Response
    {
        $d = $this->dataSpo($dokId);
        $dok = $d['dok'];
        $word = new PhpWord();
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(10);
        $word->getSettings()->setThemeFontLang(new \PhpOffice\PhpWord\Style\Language('id-ID'));

        $cm = fn (float $v) => (int) Converter::cmToTwip($v);
        $section = $word->addSection([
            'paperSize' => 'A4', 'marginTop' => $cm(1.5), 'marginRight' => $cm(2.0),
            'marginBottom' => $cm(1.5), 'marginLeft' => $cm(2.0), 'headerHeight' => $cm(0.8),
        ]);

        $garis = ['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 60];
        $tebal = ['bold' => true];
        $tengah = ['alignment' => 'center', 'spaceAfter' => 0, 'spaceBefore' => 0];
        $kiri = ['spaceAfter' => 0, 'spaceBefore' => 0];
        $logo = public_path('images/Logo Persegi.png');

        // Kop: halaman pertama 3 baris (heading 1 + heading 2), halaman berikut 2 baris (heading 1).
        foreach (['first' => true, 'default' => false] as $jenis => $pertama) {
            $header = $section->addHeader($jenis);
            $t = $header->addTable($garis);
            $t->addRow($cm(1.6));
            $c = $t->addCell($cm(self::KOLOM_KOP[0]), ['vMerge' => 'restart', 'valign' => 'center']);
            if (is_file($logo)) {
                $c->addImage($logo, ['width' => 70, 'alignment' => 'center']);
            }
            $t->addCell($cm(array_sum(array_slice(self::KOLOM_KOP, 1))), ['gridSpan' => 3, 'valign' => 'center'])
                ->addText(mb_strtoupper($dok->dok_judul), $tebal + ['size' => 11], $tengah);
            $t->addRow($cm(1.2));
            $t->addCell(null, ['vMerge' => 'continue']);
            $c = $t->addCell($cm(self::KOLOM_KOP[1]), ['valign' => 'center']);
            $c->addText('No. Dokumen', [], $kiri);
            $c->addText($dok->dok_no ?: '-', $tebal, $kiri);
            $c = $t->addCell($cm(self::KOLOM_KOP[2]), ['valign' => 'center']);
            $c->addText('No. Revisi', [], $tengah);
            $c->addText((string) $dok->no_revisi, $tebal, $tengah);
            $c = $t->addCell($cm(self::KOLOM_KOP[3]), ['valign' => 'center']);
            $c->addText('Halaman', [], $tengah);
            $c->addPreserveText('{PAGE} / {NUMPAGES}', $tebal, $tengah);
            if ($pertama) {
                $t->addRow($cm(2.4));
                $t->addCell($cm(self::KOLOM_KOP[0]), ['valign' => 'center'])
                    ->addText('STANDAR PROSEDUR OPERASIONAL', $tebal, $tengah);
                $c = $t->addCell($cm(self::KOLOM_KOP[1]), ['valign' => 'center']);
                $c->addText('Tanggal Terbit', [], $kiri);
                $c->addText($d['tanggal'] ?: '-', [], $kiri);
                $c = $t->addCell($cm(self::KOLOM_KOP[2] + self::KOLOM_KOP[3]), ['gridSpan' => 2, 'valign' => 'top']);
                $c->addText('Ditetapkan,', [], $tengah);
                $c->addText($d['penetap']['jabatan'] ?? 'Direktur', [], $tengah);
                $ttdImg = $d['ttd'] ? DokumenService::gambarTtd($d['ttd']['kode'] ?? null) : null;
                if ($ttdImg) {
                    $c->addImage($ttdImg, ['height' => Converter::cmToPoint(1.2), 'alignment' => 'center']);
                } else {
                    $c->addTextBreak(3);
                }
                $c->addText(htmlspecialchars($d['ttd']['nama'] ?? ($d['penetap']['nama'] ?? '')), $tebal, $tengah);
            }
            $header->addTextBreak(1, ['size' => 4]);
        }
        $this->labelDraf($section, $d['ttd'], $dok->status);
        // header bertipe 'first' → PhpWord menulis <w:titlePg/> (kop halaman pertama berbeda)
        $word->getSettings()->setUpdateFields(true);

        $isi = $section->addTable($garis);
        foreach (DokumenService::BAGIAN_SPO as $k => $label) {
            $html = trim((string) ($d['spo'][$k] ?? ''));
            if ($html === '' && $k === 'dokumenTerkait') {
                continue;
            }
            $isi->addRow(null, ['cantSplit' => false]);
            $isi->addCell($cm(self::KOLOM_ISI[0]))->addText($label, $tebal, $kiri);
            $sel = $isi->addCell($cm(self::KOLOM_ISI[1]));
            try {
                $this->tulisHtml($word, $sel, $this->rapikanHtml($html ?: '<p>-</p>'));
            } catch (\Throwable $e) {
                report($e);
                $sel->addText(strip_tags($html));
            }
        }

        $tmp = tempnam(sys_get_temp_dir(), 'spo') . '.docx';
        $word->save($tmp, 'Word2007');
        $this->betulkanKisiKop($tmp, array_map($cm, self::KOLOM_KOP));
        DokumenService::catat($dokId, 'CETAK', 'DOCX');

        return response()->download($tmp, $this->namaBerkas($dok, 'docx'))->deleteFileAfterSend(true);
    }

    /** tblGrid tabel kop di header*.xml ditulis ulang menjadi 4 kolom sesuai lebar kop. */
    private function betulkanKisiKop(string $docx, array $lebar): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($docx) !== true) {
            return;
        }
        $grid = '<w:tblGrid>' . implode('', array_map(fn ($w) => '<w:gridCol w:w="' . $w . '"/>', $lebar)) . '</w:tblGrid>';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nama = $zip->getNameIndex($i);
            if (preg_match('#^word/header\d+\.xml$#', $nama)) {
                $xml = preg_replace('#<w:tblGrid>.*?</w:tblGrid>#s', $grid, $zip->getFromName($nama), 1);
                $zip->addFromString($nama, $xml);
            }
        }
        $zip->close();
    }

    /**
     * Tulis HTML (p, ol/ul bersarang dengan type/start, li, strong/em/u/sup/sub, br) ke sel PhpWord.
     * Html::addHtml PhpWord hanya menerapkan type pada daftar terluar dan memaksa format bawaan
     * (1 → a → i) untuk tingkat bersarang, termasuk butir di dalam daftar bernomor — jadi tiap daftar
     * di sini diberi gaya penomoran sendiri dengan format & nomor awal yang benar.
     */
    private function tulisHtml(PhpWord $word, $sel, string $xhtml): void
    {
        $dom = new \DOMDocument();
        $dom->loadXML('<akar>' . $xhtml . '</akar>');
        $format = ['1' => 'decimal', 'A' => 'upperLetter', 'a' => 'lowerLetter', 'I' => 'upperRoman', 'i' => 'lowerRoman'];
        $kiri = fn (int $d) => 300 + $d * 300;            // twip — sel isi SPO sempit (13,4 cm)
        $paragraf = ['spaceAfter' => 30, 'spaceBefore' => 0, 'alignment' => 'both'];

        $sebaris = function (\DOMNode $n, $run, array $gaya) use (&$sebaris) {
            foreach ($n->childNodes as $c) {
                if ($c->nodeType === XML_TEXT_NODE) {
                    $t = preg_replace('/\s+/u', ' ', $c->textContent);
                    if ($t !== '' && $t !== ' ' || ($t === ' ' && $c->previousSibling)) {
                        $run->addText(htmlspecialchars($t, ENT_NOQUOTES), $gaya);
                    }
                    continue;
                }
                $nama = strtolower($c->nodeName);
                if (in_array($nama, ['ol', 'ul', 'p'], true)) {
                    continue;                                  // blok ditangani pemanggil
                }
                if ($nama === 'br') {
                    $run->addTextBreak();
                    continue;
                }
                $g = $gaya;
                match ($nama) {
                    'strong', 'b' => $g['bold'] = true,
                    'em', 'i' => $g['italic'] = true,
                    'u' => $g['underline'] = 'single',
                    'sup' => $g['superScript'] = true,
                    'sub' => $g['subScript'] = true,
                    default => null,
                };
                $sebaris($c, $run, $g);
            }
        };

        $blok = function (\DOMNode $induk, int $kedalaman, bool $hanyaBlok = false) use (&$blok, &$sebaris, $word, $sel, $format, $kiri, $paragraf) {
            foreach ($induk->childNodes as $n) {
                $nama = strtolower($n->nodeName);
                if ($hanyaBlok && ! in_array($nama, ['p', 'div', 'ol', 'ul'], true)) {
                    continue;                                  // isi sebaris butir sudah ditulis $sebaris
                }
                if ($n->nodeType === XML_TEXT_NODE) {
                    if (trim($n->textContent) !== '') {
                        $sel->addText(htmlspecialchars(trim($n->textContent), ENT_NOQUOTES), [], $paragraf + ['indentation' => ['left' => $kedalaman ? $kiri($kedalaman - 1) : 0]]);
                    }
                    continue;
                }
                if ($nama === 'p' || $nama === 'div') {
                    $run = $sel->addTextRun($paragraf + ['indentation' => ['left' => $kedalaman ? $kiri($kedalaman - 1) : 0]]);
                    $sebaris($n, $run, []);
                    continue;
                }
                if ($nama === 'ol' || $nama === 'ul') {
                    $gayaNama = 'daftar' . spl_object_id($n) . '_' . random_int(1000, 9999);
                    $fmt = $nama === 'ul' ? 'bullet' : ($format[$n->getAttribute('type') ?: '1'] ?? 'decimal');
                    $mulai = max(1, (int) ($n->getAttribute('start') ?: 1));
                    $tingkat = [];
                    for ($l = 0; $l < 9; $l++) {
                        $tingkat[] = ['format' => $fmt, 'text' => $fmt === 'bullet' ? ($kedalaman % 2 ? '–' : '•') : '%' . ($l + 1) . '.',
                            'start' => $mulai, 'alignment' => 'left', 'left' => $kiri($l), 'hanging' => 280, 'tabPos' => $kiri($l)];
                    }
                    $word->addNumberingStyle($gayaNama, ['type' => 'multilevel', 'levels' => $tingkat]);
                    foreach ($n->childNodes as $li) {
                        if (strtolower($li->nodeName) !== 'li') {
                            continue;
                        }
                        $run = $sel->addListItemRun($kedalaman, $gayaNama, ['spaceAfter' => 20, 'spaceBefore' => 0, 'alignment' => 'both']);
                        $sebaris($li, $run, []);
                        $blok($li, $kedalaman + 1, true);       // daftar/paragraf di dalam butir
                    }
                }
            }
        };
        $blok($dom->documentElement, 0);
    }

    /**
     * HTML TinyMCE/impor → XHTML rapi untuk PhpWord. Html::addHtml memakai pengurai XML yang ketat:
     * satu tag tak tertutup = sel kosong. Dirapikan dulu lewat DOMDocument (pengurai HTML longgar),
     * atribut gaya yang tak dikenal dibuang.
     */
    private function rapikanHtml(string $html): string
    {
        $html = preg_replace('/<(\/?)(span|font)[^>]*>/i', '', $html);
        $html = preg_replace('/\s(style|class|data-[a-z-]+)="[^"]*"/i', '', $html);
        $html = str_replace('&nbsp;', ' ', $html);
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div id="akar">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $akar = $dom->getElementById('akar');
        $keluar = '';
        foreach ($akar ? iterator_to_array($akar->childNodes) : [] as $n) {
            $keluar .= $dom->saveXML($n);
        }

        return $keluar !== '' ? $keluar : '<p>' . e(strip_tags($html)) . '</p>';
    }

    /** Gambar blok (format umum) untuk pratinjau penyusun — src base64 "impor:…"/"unggah:…", dibatasi akar yang diizinkan. */
    public function gambar(int $dokId, string $src): Response
    {
        $data = DokumenService::baca($dokId);
        abort_unless($data, 404);
        $this->periksaAkses($data[0]);
        $path = TataNaskah::pathGambar(base64_decode($src, true) ?: null);
        abort_unless($path, 404);

        return response()->file($path, ['Cache-Control' => 'private, max-age=3600']);
    }

    public function berkas(int $dokId, string $berkasId): Response
    {
        $data = DokumenService::baca($dokId);
        abort_unless($data, 404);
        [$row, $json] = $data;
        $this->periksaAkses($row);
        $b = collect($json['berkas'])->firstWhere('id', $berkasId);
        abort_unless($b, 404);
        $path = DokumenService::pathBerkas($b);
        abort_unless($path, 404, 'Berkas tidak ditemukan di arsip.');

        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="' . addslashes($b['nama']) . '"',
        ]);
    }
}
