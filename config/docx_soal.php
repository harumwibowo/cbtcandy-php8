<?php
/**
 * Pembaca template import soal dari Ms Word (.docx) berbentuk TABEL.
 *
 * Format yang didukung (familiar untuk pengguna CBT, sama seperti template Excel):
 *
 *   | NO | SOAL | A | B | C | D | E | KUNCI |
 *
 * - Baris pertama dianggap header bila nama kolomnya dikenali
 *   (NO/NOMOR, SOAL/PERTANYAAN, A../PILIHAN A.., KUNCI/JAWABAN, JENIS).
 * - Bila header tidak dikenali, dipakai urutan tetap: NO, SOAL, A, B, C, D, E, KUNCI.
 * - Soal esai: kolom pilihan & kunci dibiarkan kosong (atau kolom JENIS diisi 2).
 *
 * Hasil fungsi berupa array record siap insert ke tabel `soal`.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Element\Row;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextBreak;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;

/**
 * Ambil seluruh teks dari satu cell (mendukung beberapa paragraf / text run).
 */
function docx_cell_text($cell)
{
    if (!($cell instanceof Cell)) {
        return '';
    }
    return trim(docx_elements_text($cell->getElements()));
}

/**
 * Gabungkan teks dari daftar element PhpWord.
 */
function docx_elements_text($elements)
{
    $lines = array();
    foreach ($elements as $element) {
        if ($element instanceof Text) {
            $lines[] = array($element->getText());
        } elseif ($element instanceof TextBreak) {
            $lines[] = array('');
        } elseif ($element instanceof TextRun) {
            $lines[] = array(docx_elements_text($element->getElements()));
        } elseif (method_exists($element, 'getText')) {
            $lines[] = array($element->getText());
        } elseif (method_exists($element, 'getElements')) {
            $lines[] = array(docx_elements_text($element->getElements()));
        }
    }
    $out = array();
    foreach ($lines as $parts) {
        $out[] = implode('', $parts);
    }
    return implode("\n", $out);
}

/**
 * Bersihkan teks cell: nbsp -> spasi, rapikan spasi/newline.
 */
function docx_clean_text($text)
{
    $text = str_replace(array("\xc2\xa0", "\r\n", "\r"), array(' ', "\n", "\n"), (string) $text);
    $text = preg_replace('/[ \t]+/u', ' ', $text);
    $text = preg_replace('/ *\n */u', "\n", $text);
    $text = preg_replace('/\n{3,}/u', "\n\n", $text);
    return trim($text);
}

/**
 * Baca semua tabel pada dokumen .docx menjadi array baris (array of string).
 * Hanya tabel yang terlihat seperti tabel soal yang dikembalikan.
 */
function docx_soal_tables($path)
{
    $reader = IOFactory::createReader('Word2007');
    $document = $reader->load($path);
    $tables = array();
    foreach ($document->getSections() as $section) {
        foreach ($section->getElements() as $element) {
            if ($element instanceof Table) {
                $rows = array();
                foreach ($element->getRows() as $row) {
                    if (!($row instanceof Row)) {
                        continue;
                    }
                    $cells = array();
                    foreach ($row->getCells() as $cell) {
                        $cells[] = docx_clean_text(docx_cell_text($cell));
                    }
                    if (implode('', $cells) !== '') {
                        $rows[] = $cells;
                    }
                }
                if (count($rows) > 0) {
                    $tables[] = $rows;
                }
            }
        }
    }
    return $tables;
}

/**
 * Kenali kolom dari teks header. Mengembalikan map index => nama kolom.
 */
function docx_soal_header_map($row)
{
    $map = array();
    foreach ($row as $idx => $label) {
        $key = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $label));
        if ($key === '') {
            continue;
        }
        if (in_array($key, array('NO', 'NOMOR', 'NOURUT', 'NOSOAL', 'NOMORSOAL'), true)) {
            $map[$idx] = 'nomor';
        } elseif (in_array($key, array('SOAL', 'PERTANYAAN', 'BUTIRSOAL', 'ISISOAL', 'SOALPERTANYAAN'), true)) {
            $map[$idx] = 'soal';
        } elseif (in_array($key, array('KUNCI', 'KUNCIJAWABAN', 'JAWABAN', 'JAWABANBENAR', 'KEY'), true)) {
            $map[$idx] = 'jawaban';
        } elseif (in_array($key, array('JENIS', 'TIPE', 'TYPE', 'JENISSOAL'), true)) {
            $map[$idx] = 'jenis';
        } else {
            $opt = null;
            if (preg_match('/^(PILIHAN|PIL|OPSI|OPTION|JAWABAN)?([A-E])$/', $key, $m)) {
                $opt = $m[2];
            } elseif (preg_match('/^([A-E])(PILIHAN|OPSI|OPTION)?$/', $key, $m)) {
                $opt = $m[1];
            }
            if ($opt !== null) {
                $map[$idx] = 'pil' . $opt;
            }
        }
    }
    return $map;
}

/**
 * Normalisasi isi tabel menjadi record soal.
 *
 * @return array daftar record: nomor, soal, pilA..pilE, jawaban, jenis
 */
function docx_soal_records($path)
{
    $tables = docx_soal_tables($path);
    if (empty($tables)) {
        return array();
    }

    $fixed = array('nomor', 'soal', 'pilA', 'pilB', 'pilC', 'pilD', 'pilE', 'jawaban');
    $records = array();
    foreach ($tables as $rows) {
        $map = docx_soal_header_map($rows[0]);
        $hasSoal = in_array('soal', $map, true);
        $startIndex = 0;
        if ($hasSoal) {
            $startIndex = 1; // baris pertama = header
        } else {
            // tanpa header: pakai urutan tetap bila jumlah kolom mencukupi
            if (count($rows[0]) < 3) {
                continue;
            }
            $map = array();
            foreach ($fixed as $i => $name) {
                $map[$i] = $name;
            }
        }

        $jumlah = count($rows);
        for ($r = $startIndex; $r < $jumlah; $r++) {
            $row = $rows[$r];
            $rec = array(
                'nomor' => '', 'soal' => '',
                'pilA' => '', 'pilB' => '', 'pilC' => '', 'pilD' => '', 'pilE' => '',
                'jawaban' => '', 'jenis' => ''
            );
            foreach ($map as $idx => $name) {
                if (isset($row[$idx])) {
                    $rec[$name] = $row[$idx];
                }
            }
            if ($rec['soal'] === '' && $rec['jawaban'] === '' && $rec['pilA'] === '') {
                continue; // baris kosong
            }
            $records[] = $rec;
        }
    }
    return $records;
}
