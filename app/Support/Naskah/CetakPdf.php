<?php

namespace App\Support\Naskah;

use App\Services\DokumenService;

/**
 * Unsur (Susun) → HTML untuk DomPDF. Semua ukuran, jarak dan bentuk huruf dari Gaya (config/tata_naskah.php);
 * huruf kapital diterapkan pada teks (bukan CSS) supaya sama dengan DOCX. DomPDF tanpa flex/grid → tabel.
 */
class CetakPdf
{
    private float $lebar;

    public function __construct(private ?string $tanda = null, private ?string $kop = null)
    {
        $this->lebar = Gaya::lebarTulis();
    }

    public function html(string $judul, array $unsur): string
    {
        $isi = '';
        foreach ($unsur as $u) {
            $isi .= $this->unsur($u);
        }
        $kop = $this->kop && is_file($this->kop) ? '<img class="kop" src="' . e($this->kop) . '">' : '';
        $tanda = $this->tanda ? '<div class="tanda">' . e($this->tanda) . '</div>' : '';

        return '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>' . e($judul) . '</title>'
            . '<style>' . $this->css() . '</style></head><body>' . $kop . $tanda . $isi . '</body></html>';
    }

    private function css(): string
    {
        [$atas, $kanan, $bawah, $kiri] = Gaya::marjin();
        $font = '';
        foreach (['normal normal' => 'Regular', 'bold normal' => 'Bold', 'normal italic' => 'Italic', 'bold italic' => 'BoldItalic'] as $g => $f) {
            [$w, $s] = explode(' ', $g);
            $font .= "@font-face{font-family:'Arial';font-weight:{$w};font-style:{$s};src:url('"
                . resource_path("fonts/LiberationSans-{$f}.ttf") . "') format('truetype');}";
        }
        $css = $font
            . "@page{size:A4;margin:{$atas}cm {$kanan}cm {$bawah}cm {$kiri}cm;}"
            . 'body{margin:0;font-family:Arial,sans-serif;font-size:' . Gaya::ukuran() . 'pt;line-height:' . Gaya::spasi() . ';color:#000;}'
            . "p{margin:0;}table{border-collapse:collapse;}td{vertical-align:top;padding:0;}"
            . ".kop{position:fixed;top:-{$atas}cm;left:-{$kiri}cm;width:21cm;height:29.7cm;z-index:-2;}"
            . '.tanda{position:fixed;top:6cm;left:0;right:0;text-align:center;font-size:60pt;font-weight:bold;color:#e3e3e3;transform:rotate(-30deg);z-index:-1;}'
            . '.tabel td,.tabel th{border:1px solid #000;padding:1pt 3pt;font-size:' . Gaya::ukuranTabel() . 'pt;}'
            . '.tabel th{font-weight:bold;text-align:center;}'
            . '.kaki{page-break-inside:avoid;}';
        foreach (array_keys(config('tata_naskah.gaya', [])) as $peran) {
            $g = Gaya::peran($peran);
            $css .= ".p-{$peran}{text-align:" . ['tengah' => 'center', 'kiri' => 'left', 'kanan' => 'right', 'rata' => 'justify'][$g['rata']]
                . ';font-weight:' . ($g['tebal'] ? 'bold' : 'normal') . ';font-style:' . ($g['miring'] ? 'italic' : 'normal')
                . ";font-size:{$g['ukuran']}pt;margin-top:" . Gaya::barisPt($g['sebelum'], $g['ukuran'])
                . 'pt;margin-bottom:' . Gaya::barisPt($g['sesudah'], $g['ukuran']) . 'pt;}';
        }

        return $css;
    }

    private function p(string $peran, string $teks): string
    {
        return '<p class="p-' . $peran . '">' . nl2br(e(Gaya::teks($peran, $teks))) . '</p>';
    }

    private function unsur(array $u): string
    {
        return match ($u[0]) {
            'teks' => $this->p($u[1], $u[2]),
            'jarak' => '<div style="height:' . Gaya::barisPt($u[1]) . 'pt"></div>',
            'lajur' => $this->lajur($u[1]),
            'isi' => $this->simpul($u[1], $u[3], $this->lebar),
            'identitas' => $this->identitas($u[1], $u[2]),
            'rincian' => $this->rincian($u[1]),
            'kaki' => $this->kaki($u[1], $u[2]),
            'tembusan' => $this->tembusan($u[1]),
            'lampiran' => $this->lampiran($u[1]),
            'halaman_baru' => '<div style="page-break-before:always"></div>',
            default => '',
        };
    }

    private function lajur(array $baris): string
    {
        $k = Gaya::c('kolom');
        $sisa = $this->lebar - $k['label'] - $k['titik'];
        $h = '<table style="width:' . $this->lebar . 'cm">';
        foreach ($baris as [$label, $labelPeran, $titik, $nomor, $isi, $isiPeran, $konteks]) {
            if ($label === '' && $isi === '' && ! $nomor) {        // jeda antar-konsiderans: setengah baris
                $h .= '<tr><td colspan="4" style="height:' . Gaya::barisPt(0.5) . 'pt"></td></tr>';

                continue;
            }
            $h .= '<tr><td style="width:' . $k['label'] . 'cm">' . $this->p($labelPeran, $label) . '</td>'
                . '<td style="width:' . $k['titik'] . 'cm">' . $this->p('label', $titik) . '</td>';
            $sel = is_array($isi)
                ? $this->simpul($isi, Gaya::mulai($konteks ?? 'diktum'), $sisa)
                : $this->p($isiPeran, $isi);
            if ($nomor !== null) {
                $h .= '<td style="width:' . $k['nomor'] . 'cm">' . $this->p('label', $nomor) . '</td>'
                    . '<td style="width:' . ($sisa - $k['nomor']) . 'cm">' . $sel . '</td>';
            } else {
                $h .= '<td colspan="2" style="width:' . $sisa . 'cm">' . $sel . '</td>';
            }
            $h .= '</tr>';
        }

        return $h . '</table>';
    }

    /** Simpul isi: paragraf, butir bertingkat (kolom nomor menggantung), tabel. */
    private function simpul(array $simpul, int $tingkat, float $lebar): string
    {
        $h = '';
        $ind = Gaya::c('butir.indentasi', 0.7);
        foreach ($simpul as $s) {
            if ($s[0] === 'p') {
                $h .= $this->p('isi', $s[1]);
            } elseif ($s[0] === 'daftar') {
                $h .= '<table style="width:' . $lebar . 'cm">';
                foreach ($s[2] as $i => [$teks, $anak]) {
                    $nomor = $s[1] ? Gaya::nomor($tingkat, $i) : '•';
                    $h .= '<tr><td style="width:' . $ind . 'cm">' . $this->p('isi', $nomor) . '</td><td style="width:' . ($lebar - $ind) . 'cm">'
                        . ($teks !== '' ? $this->p('isi', $teks) : '')
                        . ($anak ? $this->simpul($anak, $tingkat + 1, $lebar - $ind) : '') . '</td></tr>';
                }
                $h .= '</table>';
            } elseif ($s[0] === 'tabel') {
                $h .= '<table class="tabel" style="width:' . $lebar . 'cm;margin:2pt 0">';
                if ($s[1]) {
                    $h .= '<tr>' . implode('', array_map(fn ($c) => '<th>' . e($c) . '</th>', $s[1])) . '</tr>';
                }
                foreach ($s[2] as $b) {
                    $h .= '<tr>' . implode('', array_map(fn ($c) => '<td>' . nl2br(e((string) $c)) . '</td>', $b)) . '</tr>';
                }
                $h .= '</table>';
            }
        }

        return $h;
    }

    private function identitas(array $baris, string $kanan): string
    {
        $h = '<table style="width:' . $this->lebar . 'cm">';
        foreach ($baris as $i => [$k, $v]) {
            $h .= '<tr><td style="width:2.3cm">' . $this->p('identitas', $k) . '</td><td style="width:0.4cm">' . $this->p('identitas', ':') . '</td>'
                . '<td>' . $this->p('identitas', $v) . '</td>'
                . ($i === 0 ? '<td style="width:6cm" rowspan="' . count($baris) . '">' . $this->p('tanggal_surat', $kanan) . '</td>' : '')
                . '</tr>';
        }

        return $h . '</table>';
    }

    private function rincian(array $baris): string
    {
        $h = '<table style="margin:' . Gaya::barisPt(0.5) . 'pt 0 ' . Gaya::barisPt(0.5) . 'pt 1.25cm;width:' . ($this->lebar - 1.25) . 'cm">';
        foreach ($baris as [$k, $v]) {
            $h .= '<tr><td style="width:2.6cm">' . $this->p('rincian', $k) . '</td><td style="width:0.4cm">' . $this->p('rincian', ':') . '</td>'
                . '<td>' . $this->p('rincian', $v) . '</td></tr>';
        }

        return $h . '</table>';
    }

    private function kaki(array $baris, ?array $ttd): string
    {
        $img = $ttd ? DokumenService::gambarTtd($ttd['kode'] ?? null) : null;
        $h = '<div class="kaki" style="margin:' . Gaya::barisPt(1) . 'pt 0 0 ' . Gaya::c('kaki_kiri') . 'cm">';
        foreach ($baris as $b) {
            if ($b[0] === 'ttd') {
                $h .= $img ? '<img src="' . e($img) . '" style="height:2.2cm;margin:2pt 0">'
                    : '<div style="height:' . Gaya::barisPt(Gaya::c('ruang_ttd', 4)) . 'pt"></div>';
            } elseif ($b[0] === 'jarak') {
                $h .= '<div style="height:' . Gaya::barisPt($b[1]) . 'pt"></div>';
            } else {
                $h .= $this->p($b[0], $b[1]);
            }
        }
        if ($ttd) {
            $h .= '<p style="font-size:' . Gaya::ukuranTabel() . 'pt;color:#555">Ditandatangani elektronik '
                . e(\Carbon\Carbon::parse($ttd['waktu'])->format('d/m/Y H:i')) . ' · kode ' . e($ttd['verifikasi']) . '</p>';
        }

        return $h . '</div>';
    }

    private function tembusan(array $daftar): string
    {
        $h = '<div class="kaki">' . $this->p('tembusan', 'Tembusan:');
        if (count($daftar) === 1) {
            return $h . $this->p('kaki', $daftar[0]) . '</div>';
        }

        return $h . $this->simpul([['daftar', true, array_map(fn ($t) => [$t, []], $daftar)]], 2, $this->lebar) . '</div>';
    }

    private function lampiran(array $baris): string
    {
        $kiri = Gaya::c('kaki_kiri');
        $h = '<table style="margin:0 0 ' . Gaya::barisPt(1) . 'pt ' . $kiri . 'cm;width:' . ($this->lebar - $kiri) . 'cm">';
        foreach ($baris as [$k, $v]) {
            $h .= '<tr><td style="width:2.4cm">' . $this->p('lampiran', $k) . '</td><td style="width:0.4cm">' . $this->p('lampiran', ':') . '</td>'
                . '<td>' . $this->p('lampiran', $v) . '</td></tr>';
        }

        return $h . '</table>';
    }
}
