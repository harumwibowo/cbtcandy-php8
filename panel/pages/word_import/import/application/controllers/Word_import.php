<?php
/**
 * Endpoint import Word lama (warisan aplikasi Savsoft/BEE).
 *
 * Sejak template import soal diganti ke format TABEL, import Word ditangani
 * langsung oleh aplikasi Candy CBT:
 *   panel/mod_banksoal/crud_soal.php?pg=import_word
 *   parser: config/docx_soal.php
 *
 * Controller ini ditutup karena: (1) menulis ke tabel savsoft_* yang tidak dipakai
 * ujian Candy CBT, dan (2) menerima upload + ekstrak zip tanpa pemeriksaan sesi.
 */
class Word_import extends CI_Controller
{
    function index($limit = '0', $cid = '0')
    {
        show_error(
            'Import soal Word format lama sudah tidak digunakan. '
                . 'Gunakan menu Bank Soal &rarr; Import &rarr; "Import Soal Ms Word (Tabel)" '
                . 'dengan template importdatasoal.docx.',
            403,
            'Import Word lama dinonaktifkan'
        );
    }
}
