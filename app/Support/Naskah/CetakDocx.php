<?php

namespace App\Support\Naskah;

use App\Services\DokumenService;
use PhpOffice\PhpWord\ComplexType\TblWidth;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;

/**
 * Unsur (Susun) → DOCX PhpWord. Pasangan CetakPdf: gaya, jarak dan penomoran dari Gaya yang sama.
 * Butir bertingkat = paragraf berindentasi gantung "a.<tab>teks" (Word menaruh tab di posisi gantung),
 * bukan tabel — supaya tetap bisa disunting wajar di Word.
 */
class CetakDocx
{
    private PhpWord $word;
    private $section;
    private float $lebar;
    private const TANPA_GARIS = ['borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMargin' => 0, 'layout' => 'fixed'];

    public function __construct(private ?string $tanda = null, private ?string $kop = null)
    {
        $this->lebar = Gaya::lebarTulis();
    }

    /** Bangun DOCX, kembalikan path berkas sementara. */
    public function simpan(array $unsur): string
    {
        $this->word = new PhpWord();
        $this->word->setDefaultFontName(Gaya::c('huruf.nama', 'Arial'));
        $this->word->setDefaultFontSize(Gaya::ukuran());
        $this->word->setDefaultParagraphStyle(['spaceAfter' => 0, 'spaceBefore' => 0, 'spacing' => 0,
            'lineHeight' => Gaya::spasi()]);
        [$atas, $kanan, $bawah, $kiri] = Gaya::marjin();
        $this->section = $this->word->addSection(['paperSize' => 'A4', 'marginTop' => self::cm($atas),
            'marginRight' => self::cm($kanan), 'marginBottom' => self::cm($bawah), 'marginLeft' => self::cm($kiri), 'headerHeight' => 0]);
        $header = $this->section->addHeader();
        if ($this->kop && is_file($this->kop)) {
            $header->addImage($this->kop, [
                'width' => Converter::cmToPoint(21.0), 'height' => Converter::cmToPoint(29.7),
                'positioning' => 'absolute', 'posHorizontalRel' => 'page', 'posVerticalRel' => 'page',
                'posHorizontal' => 'absolute', 'posVertical' => 'absolute', 'marginLeft' => 0, 'marginTop' => 0,
                'wrappingStyle' => 'behind',
            ]);
        }
        if ($this->tanda) {   // PhpWord tidak punya tanda air teks → label di kepala halaman
            $header->addText($this->tanda, ['color' => 'B0B0B0', 'bold' => true, 'size' => 10], ['alignment' => 'left']);
        }

        foreach ($unsur as $u) {
            $this->unsur($u);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'naskah') . '.docx';
        $this->word->save($tmp, 'Word2007');
        $this->betulkanGantung($tmp);

        return $tmp;
    }

    /** PhpWord menulis firstLine="0" di samping hanging; LibreOffice lalu mengabaikan hanging. */
    private function betulkanGantung(string $docx): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($docx) === true) {
            $xml = $zip->getFromName('word/document.xml');
            $zip->addFromString('word/document.xml', str_replace(' w:firstLine="0" w:hanging=', ' w:hanging=', $xml));
            $zip->close();
        }
    }

    private static function cm(float $v): int
    {
        return (int) round(Converter::cmToTwip($v));
    }

    private static function twipPt(float $pt): int
    {
        return (int) round($pt * 20);
    }

    /** [gaya huruf, gaya paragraf] untuk peran. */
    private function gaya(string $peran, array $tambahan = []): array
    {
        $g = Gaya::peran($peran);
        $huruf = ['size' => $g['ukuran'], 'bold' => $g['tebal'], 'italic' => $g['miring']];
        $par = ['alignment' => ['tengah' => 'center', 'kiri' => 'left', 'kanan' => 'right', 'rata' => 'both'][$g['rata']],
            'spaceBefore' => self::twipPt(Gaya::barisPt($g['sebelum'], $g['ukuran'])),
            'spaceAfter' => self::twipPt(Gaya::barisPt($g['sesudah'], $g['ukuran'])),
            'lineHeight' => Gaya::spasi()];

        return [$huruf, $tambahan + $par];
    }

    private function p($wadah, string $peran, string $teks, array $par = []): void
    {
        [$h, $p] = $this->gaya($peran, $par);
        $wadah->addText(htmlspecialchars(Gaya::teks($peran, $teks)), $h, $p);
    }

    private function jarak($wadah, float $baris, array $par = []): void
    {
        // tinggi pasti (lineRule exact) — paragraf kosong biasa ikut setinggi huruf bawaan
        $wadah->addText('', ['size' => 1], $par + ['spaceAfter' => 0, 'spacing' => self::twipPt(Gaya::barisPt($baris)),
            'spacingLineRule' => 'exact']);
    }

    private function unsur(array $u): void
    {
        $s = $this->section;
        match ($u[0]) {
            'teks' => $this->p($s, $u[1], $u[2]),
            'jarak' => $u[1] > 0 ? $this->jarak($s, $u[1]) : null,
            'lajur' => $this->lajur($u[1]),
            'isi' => $this->simpul($s, $u[1], $u[3], 0.0),
            'identitas' => $this->identitas($u[1], $u[2]),
            'rincian' => $this->rincian($u[1]),
            'kaki' => $this->kaki($u[1], $u[2]),
            'tembusan' => $this->tembusan($u[1]),
            'lampiran' => $this->lampiran($u[1]),
            'halaman_baru' => $s->addPageBreak(),
            default => null,
        };
    }

    private function lajur(array $baris): void
    {
        $k = Gaya::c('kolom');
        $sisa = $this->lebar - $k['label'] - $k['titik'];
        $t = $this->section->addTable(self::TANPA_GARIS + ['width' => self::cm($this->lebar), 'unit' => 'dxa']);
        foreach ($baris as [$label, $labelPeran, $titik, $nomor, $isi, $isiPeran, $konteks]) {
            if ($label === '' && $isi === '' && ! $nomor) {        // jeda antar-konsiderans: setengah baris
                $t->addRow(self::twipPt(Gaya::barisPt(0.5)), ['exactHeight' => true]);
                $t->addCell(self::cm($this->lebar), ['gridSpan' => 4])->addText('', ['size' => 1], ['spacing' => 20, 'spacingLineRule' => 'exact']);

                continue;
            }
            $t->addRow();
            $this->p($t->addCell(self::cm($k['label'])), $labelPeran, $label);
            $this->p($t->addCell(self::cm($k['titik'])), 'label', $titik);
            if ($nomor !== null) {
                $this->p($t->addCell(self::cm($k['nomor'])), 'label', $nomor);
                $sel = $t->addCell(self::cm($sisa - $k['nomor']));
            } else {
                $sel = $t->addCell(self::cm($sisa), ['gridSpan' => 2]);
            }
            if (is_array($isi)) {
                $this->simpul($sel, $isi, Gaya::mulai($konteks ?? 'diktum'), 0.0);
                if (! $isi) {
                    $sel->addText('');
                }
            } else {
                $this->p($sel, $isiPeran, $isi);
            }
        }
    }

    /** $kiri = indentasi awal (cm) di dalam wadah. */
    private function simpul($wadah, array $simpul, int $tingkat, float $kiri): void
    {
        $ind = Gaya::c('butir.indentasi', 0.7);
        foreach ($simpul as $s) {
            if ($s[0] === 'p') {
                $this->p($wadah, 'isi', $s[1], $kiri > 0 ? ['indentation' => ['left' => self::cm($kiri)]] : []);
            } elseif ($s[0] === 'daftar') {
                foreach ($s[2] as $i => [$teks, $anak]) {
                    $nomor = $s[1] ? Gaya::nomor($tingkat, $i) : '•';
                    [$h, $p] = $this->gaya('isi', ['indentation' => ['left' => self::cm($kiri + $ind), 'hanging' => self::cm($ind)]]);
                    $wadah->addText(htmlspecialchars($nomor) . "\t" . htmlspecialchars($teks), $h, $p);
                    if ($anak) {
                        $this->simpul($wadah, $anak, $tingkat + 1, $kiri + $ind);
                    }
                }
            } elseif ($s[0] === 'tabel') {
                $lebar = ($this->lebar - $kiri);
                $kolom = max(count($s[1]), count($s[2][0] ?? []), 1);
                $t = $wadah->addTable(['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 40, 'layout' => 'fixed',
                    'indent' => new TblWidth(self::cm($kiri))]);
                $hf = ['size' => Gaya::ukuranTabel()];
                $hp = ['spaceAfter' => 0, 'lineHeight' => 1.0];
                if ($s[1]) {
                    $t->addRow();
                    foreach ($s[1] as $c) {
                        $t->addCell(self::cm($lebar / $kolom))->addText(htmlspecialchars($c), $hf + ['bold' => true], $hp + ['alignment' => 'center']);
                    }
                }
                foreach ($s[2] as $b) {
                    $t->addRow();
                    foreach ($b as $c) {
                        $t->addCell(self::cm($lebar / $kolom))->addText(htmlspecialchars((string) $c), $hf, $hp);
                    }
                }
            }
        }
    }

    private function identitas(array $baris, string $kanan): void
    {
        $sisa = $this->lebar - 2.3 - 0.4 - 6.0;
        $t = $this->section->addTable(self::TANPA_GARIS);
        foreach ($baris as $i => [$k, $v]) {
            $t->addRow();
            $this->p($t->addCell(self::cm(2.3)), 'identitas', $k);
            $this->p($t->addCell(self::cm(0.4)), 'identitas', ':');
            $this->p($t->addCell(self::cm($sisa)), 'identitas', $v);
            $sel = $t->addCell(self::cm(6.0), ['vMerge' => $i === 0 ? 'restart' : 'continue']);
            if ($i === 0) {
                $this->p($sel, 'tanggal_surat', $kanan);
            }
        }
    }

    private function rincian(array $baris): void
    {
        $this->jarak($this->section, 0.5);
        $t = $this->section->addTable(self::TANPA_GARIS + ['indent' => new TblWidth(self::cm(1.25))]);
        foreach ($baris as [$k, $v]) {
            $t->addRow();
            $this->p($t->addCell(self::cm(2.6)), 'rincian', $k);
            $this->p($t->addCell(self::cm(0.4)), 'rincian', ':');
            $this->p($t->addCell(self::cm($this->lebar - 1.25 - 3.0)), 'rincian', $v);
        }
        $this->jarak($this->section, 0.5);
    }

    private function kaki(array $baris, ?array $ttd): void
    {
        $img = $ttd ? DokumenService::gambarTtd($ttd['kode'] ?? null) : null;
        $kiri = ['indentation' => ['left' => self::cm(Gaya::c('kaki_kiri'))], 'keepNext' => true, 'keepLines' => true];
        $this->jarak($this->section, 1, $kiri);
        foreach ($baris as $b) {
            if ($b[0] === 'ttd') {
                if ($img) {
                    $this->section->addTextRun($kiri)->addImage($img, ['height' => Converter::cmToPoint(2.0)]);
                } else {
                    $this->jarak($this->section, Gaya::c('ruang_ttd', 4), $kiri);
                }
            } elseif ($b[0] === 'jarak') {
                $this->jarak($this->section, $b[1], $kiri);
            } else {
                $this->p($this->section, $b[0], $b[1], $kiri);
            }
        }
        if ($ttd) {
            $this->section->addText('Ditandatangani elektronik ' . \Carbon\Carbon::parse($ttd['waktu'])->format('d/m/Y H:i') . ' · kode ' . $ttd['verifikasi'],
                ['size' => Gaya::ukuranTabel(), 'color' => '555555'], ['indentation' => $kiri['indentation']]);
        }
    }

    private function tembusan(array $daftar): void
    {
        $this->p($this->section, 'tembusan', 'Tembusan:', ['keepNext' => true]);
        if (count($daftar) === 1) {
            $this->p($this->section, 'kaki', $daftar[0]);

            return;
        }
        $this->simpul($this->section, [['daftar', true, array_map(fn ($t) => [$t, []], $daftar)]], 2, 0.0);
    }

    private function lampiran(array $baris): void
    {
        $kiri = Gaya::c('kaki_kiri');
        $t = $this->section->addTable(self::TANPA_GARIS + ['indent' => new TblWidth(self::cm($kiri))]);
        foreach ($baris as [$k, $v]) {
            $t->addRow();
            $this->p($t->addCell(self::cm(2.4)), 'lampiran', $k);
            $this->p($t->addCell(self::cm(0.4)), 'lampiran', ':');
            $this->p($t->addCell(self::cm($this->lebar - $kiri - 2.8)), 'lampiran', $v);
        }
        $this->jarak($this->section, 1);
    }
}
