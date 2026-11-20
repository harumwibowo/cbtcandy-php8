# Changelog

Semua perubahan penting pada proyek ini dicatat di sini.
Format mengikuti [Keep a Changelog](https://keepachangelog.com/id/1.1.0/),
versi mengikuti nomor versi aplikasi (`VERSI` di `config/config.candy.php`).

## [2.9.4] - 2026-11-20

Rilis pertama yang berjalan di **PHP 8.1 - 8.3** dan **MySQL 8.0 / 8.4 / 9.x**
(sebelumnya hanya PHP 5.x / 7.x dengan MySQL versi lama).

### Ditambahkan

- `config/compat.php`: shim `cbt_fetch_array()`, `cbt_fetch_assoc()`, `cbt_fetch_row()`,
  `cbt_num_rows()`, `cbt_num_fields()`, `cbt_free_result()`, `cbt_unserialize_array()`,
  `cbt_str()` untuk menggantikan pola `mysqli_*`/`unserialize()` lama yang memicu
  `TypeError`/warning di PHP 8.
- `config/database.sql`: satu file SQL instalasi (32 tabel `utf8mb4`, tanpa data demo),
  berisi 1 baris `setting`, 1 akun admin (`admin`/`admin`), dan 3 jenis ujian.
- Deteksi **base URL otomatis** (`config/config.default.php`): aplikasi bisa dipasang
  di subfolder (`/candycbt`) maupun root domain tanpa mengubah konfigurasi.
- `config/docx_soal.php` + `panel/template/importdatasoal.docx`: import soal dari
  **Ms Word (tabel)** dengan kolom `NO | SOAL | A | B | C | D | E | KUNCI`,
  header dikenali otomatis (urutan kolom bebas), kolom `JENIS` opsional (1 = PG, 2 = esai),
  dukungan "hapus soal lama" dan penomoran otomatis saat menambah.
- `INSTALASI.TXT`: panduan instalasi untuk Laragon, shared hosting cPanel, dan VPS
  (termasuk contoh Nginx server block), plus daftar masalah umum.
- `config/.htaccess` (menolak akses web ke folder config), `files/.htaccess`,
  `tugas/.htaccess` (folder unggahan tidak bisa mengeksekusi PHP).
- `tools/buat-paket.py`: pembuat paket ZIP distribusi (mengecualikan kredensial,
  dump database, dan data unggahan pengguna).
- GitHub Actions: cek sintaks PHP (`.github/workflows/lint.yml`) dan pembuatan
  ZIP + GitHub Release otomatis saat tag versi di-push
  (`.github/workflows/release.yml`).

### Diubah

- `mysqli_report(MYSQLI_REPORT_OFF)` agar pola lama "query gagal = false" tetap aman.
- Semua `mysqli_fetch_*` / `mysqli_num_rows` / `unserialize()` dialihkan ke helper compat.
- Dependensi: **PhpSpreadsheet 2.x**, **PhpWord 1.x**, **Predis 2.x** (versi lama tidak
  mendukung PHP 8); `vendor/` disertakan dalam paket rilis.
- `VERSI_DB` disamakan dengan skema (`2.9.4`) sehingga popup "Kesalahan Versi Database"
  tidak muncul lagi di panel.
- Folder `panel/pages/word_import/import` (CodeIgniter 3) tidak dipakai lagi; endpoint
  import lama dinonaktifkan (403) karena hanya menulis ke tabel `savsoft_*`.

### Diperbaiki

- **Hapus bank soal** membentuk SQL rusak (`IN ("1')`) dan tidak menghapus relasi
  `jawaban`/`file_pendukung`/`soal`.
- **Token jadwal** selalu update ke `id_token=1` sehingga token hilang bila baris ≠ 1.
- **`selesai.php`** fatal `DivisionByZeroError` pada ujian esai saja (`tampil_pg=0`).
- **`aturan.php`** `mt_rand(0,17)` pada array 6 elemen (random soal di luar batas).
- **Laporan nilai** memakai `m=<id_ujian>` (seharusnya `id_mapel`); variabel
  `$kode_ujian`/`$id_kelas` tidak terdefinisi.
- **Berita acara** dengan `id` tidak valid membanjiri log dengan warning.
- Menu **Materi** memanggil berkas yang tidak ada → sekarang menampilkan pemberitahuan.
- Puluhan warning "undefined array key / array offset on null" pada `soal.php`,
  `nilaiujian2.php`, `report_excel.php`, `printjawab.php`, `cetaksoal.php`, `crud_setting.php`,
  dan lainnya (semua halaman kini bersih dari warning pada PHP 8.3).
- `ceklogin.php` (root & panel) tidak lagi memunculkan warning bila diakses langsung.

### Catatan kompatibilitas

- Ekstensi PHP wajib: `mysqli`, `zip`, `gd`, `mbstring`, `curl`, `openssl`, `intl`,
  `dom`, `xml`, `xmlwriter`, `fileinfo`, `json`.
- `sql_mode` legacy (`NO_ENGINE_SUBSTITUTION`) diterapkan per koneksi agar query
  dengan `GROUP BY` lama tetap jalan di MySQL 8.
