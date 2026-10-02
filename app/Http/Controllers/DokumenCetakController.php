<?php

namespace App\Http\Controllers;

use App\Services\DokumenService;
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

    public function pdf(int $dokId): Response
    {
        $d = $this->dataSpo($dokId);
        $pdf = Pdf::loadView('pages.dokumen.cetak.spo-pdf', $d + ['kolomKop' => self::KOLOM_KOP, 'kolomIsi' => self::KOLOM_ISI])
            ->setPaper('A4', 'portrait');
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
            'Content-Disposition' => 'inline; filename="' . $this->namaBerkas($d['dok'], 'pdf') . '"',
        ]);
    }

    public function docx(int $dokId): Response
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
                $c->addTextBreak(3);
                $c->addText($d['penetap']['nama'] ?? '', $tebal, $tengah);
            }
            $header->addTextBreak(1, ['size' => 4]);
        }
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
