# Candy CBT

Aplikasi ujian berbasis komputer (CBT) untuk PTS / PAS / USBN / SIMULASI UNBK.

Web Candy - http://candycbt.id

**Versi ini sudah disesuaikan agar berjalan di PHP 8.3.x dan MySQL 8.0 / 8.4 / 9.x.**

> Panduan instalasi langkah-demi-langkah untuk **Laragon**, **shared hosting cPanel**,
dan **VPS Ubuntu (Nginx)** ada di file [`INSTALASI.TXT`](INSTALASI.TXT).
> Ringkasan singkat tetap tersedia di bagian 2 di bawah.

> **Repositori:** <https://github.com/harumwibowo/cbtcandy-php8>

### Mengunduh untuk instalasi fresh

Repositori ini sudah menyertakan folder `vendor/` (PhpSpreadsheet, PhpWord, Predis),
jadi penerima **tidak perlu menjalankan `composer install`**:

```bash
git clone https://github.com/harumwibowo/cbtcandy-php8.git candycbt
# atau tanpa git: buka repo -> tombol Code -> Download ZIP -> ekstrak
```

Setelah itu tetap ada 3 langkah wajib (detail di `INSTALASI.TXT`):

1. Buat database MySQL kosong, lalu import `config/database.sql`.
2. Sesuaikan host / nama database / user / password di `config/config.database.php`
   (disarankan lewat `config/config.local.php` yang tidak ikut di repositori).
3. Buka `http://localhost/candycbt/` dan login `admin` / `admin`, lalu ganti password.

Berkas `config/config.local.php`, dump database, foto siswa, dan berkas unggahan
sengaja **tidak** ada di repositori (lihat `.gitignore`) agar aman dibagikan publik.

---

## 1. Persyaratan

| Komponen | Versi yang didukung |
| --- | --- |
| PHP | 8.1 - 8.3 (diuji pada PHP 8.3.33 dan 8.3.35) |
| MySQL / MariaDB | MySQL 8.0.x, 8.4.x (diuji pada 8.4.10), 9.x; MariaDB 10.6+ |
| Ekstensi PHP | `mysqli`, `zip`, `gd`, `mbstring`, `curl`, `openssl`, `intl`, `dom`, `xml`, `xmlwriter`, `fileinfo`, `json` |
| Composer | 2.x (hanya untuk install dependency) |

Pastikan ekstensi `zip` aktif. Di Laragon: **Menu > PHP > Extensions > zip**, lalu restart Apache/nginx.
Tanpa `zip`, ekspor/impor Excel (PhpSpreadsheet) akan gagal.

## 2. Instalasi

1. **Siapkan database.** Buat database (nama default aplikasi: `candyold`), lalu import
   satu file SQL instalasi:

   ```bash
   mysql -u root -p -e "CREATE DATABASE candyold CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
   mysql -u root -p candyold < config/database.sql
   ```

   `config/database.sql` berisi 32 tabel **kosong** plus data awal minimal:

   - 1 baris `setting` (identitas aplikasi, wajib ada)
   - 1 akun admin: `admin` / `admin`
   - 3 jenis ujian standar (PTS, PAS, USBK) — bisa diubah di **Master > Jenis Ujian**

   Sisanya kosong: Anda mulai dari nol (kelas, mapel, jurusan, ruang, sesi, siswa, ujian,
   soal dibuat sendiri lewat panel). File ini aman dijalankan berulang: setiap tabel
   di-`DROP` lebih dulu, jadi **import ulang = reset total**.

2. **Install dependency PHP.**

   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. **Konfigurasi koneksi database.**

   Cara termudah, edit `config/config.database.php` bagian nilai default, atau buat
   `config/config.local.php` (tidak ikut ter-commit):

   ```php
   <?php
   $db_config = [
       'host' => '127.0.0.1',
       'port' => 3306,
       'user' => 'root',
       'pass' => '',
       'name' => 'candyold',
   ];
   ```

   Alternatif tanpa mengubah file: environment variable `DB_HOST`, `DB_PORT`, `DB_USER`,
   `DB_PASS`, `DB_NAME`.

4. **Akses aplikasi** dari browser. Dua bentuk instalasi didukung otomatis
   (base URL dideteksi dari request):

   - subfolder: `http://localhost/candycbt/` (panel: `/candycbt/panel/`)
   - root / virtual host: `http://candycbt.test/` (panel: `/candycbt.test/panel/`)

   Siswa membuka root aplikasi, admin/guru membuka folder `panel`.

5. **Login.** Akun awal yang dibuat oleh `config/database.sql`:

   | Username | Password | Level |
   | --- | --- | --- |
   | `admin` | `admin` | admin |

   > Segera ganti password admin setelah login pertama:
   >
   > ```bash
   > php -r "echo password_hash('password-baru', PASSWORD_DEFAULT), PHP_EOL;"
   > # lalu jalankan:
   > # UPDATE pengawas SET password='<hash di atas>' WHERE username='admin';
   > ```
   >
   > Akun guru/pengawas dibuat sendiri lewat menu **Guru** atau **Pengawas** di panel.
   > Akun siswa dibuat lewat menu **Siswa** (bisa import Excel).

## 3. Mode debug

Secara default error PHP disembunyikan (perilaku produksi). Untuk menampilkan error
saat pengembangan, set environment variable:

```bash
APP_DEBUG=1
```

## 4. Migrasi database lama

Jika Anda sudah punya data pada database lama (MySQL 5.6/5.7, MariaDB, atau Candy CBT
versi sebelumnya) dan **tidak** ingin mulai dari nol, jangan import `config/database.sql`.
Cukup samakan charset tabel ke `utf8mb4` (setelah backup). Daftar `ALTER TABLE` untuk
ke-32 tabel tersedia di bagian **OPSIONAL: MIGRASI DATABASE LAMA** pada akhir file
`config/database.sql` — hapus tanda komentar `--` lalu jalankan.

Catatan:

- `sql_mode` per-koneksi otomatis diatur ke `NO_ENGINE_SUBSTITUTION` supaya query
  `GROUP BY` gaya lama tetap berjalan. Matikan dengan `DB_LEGACY_SQL_MODE=0`
  jika Anda ingin memakai `sql_mode` default server.
- `config/database.sql` sudah memakai `utf8mb4` dan tanpa *display width* tipe
  integer (yang sudah deprecated di MySQL 8).

## 5. Catatan kompatibilitas PHP 8.3 / MySQL 8

Perubahan yang dilakukan pada kode aplikasi:

- **`mysqli_report(MYSQLI_REPORT_OFF)`** di `config/config.database.php`.
  Sejak PHP 8.1, error mysqli melempar exception sehingga halaman menjadi blank/fatal.
- **Helper kompatibilitas** di `config/compat.php`:
  `cbt_fetch_array()`, `cbt_fetch_assoc()`, `cbt_fetch_row()`, `cbt_num_rows()`,
  `cbt_num_fields()`, `cbt_free_result()`, `cbt_unserialize_array()`, `cbt_str()`.
  Semua pemanggilan `mysqli_fetch_*` / `mysqli_num_rows` / `unserialize()` di aplikasi
  dialihkan ke helper ini agar hasil `false`/`null` tidak memicu `TypeError` di PHP 8.
- **`unserialize()`** pada kolom `kelas`, `id_pk`, `idpk`, `jawaban` sekarang selalu
  mengembalikan array (data kosong/rusak tidak lagi membuat fatal error).
- **MySQL 8** tidak lagi mendukung `GROUP BY ... ASC/DESC`; sintaks tersebut dihapus.
- **`ONLY_FULL_GROUP_BY`** dinetralkan lewat `sql_mode` per-session.
- **PHP 4 style constructor** (`function OLERead()` / `Spreadsheet_Excel_Reader()`) diganti
  `__construct()`; `split()` diganti `explode()`; `=& new` diperbaiki; class pembaca Excel
  diberi `#[\AllowDynamicProperties]`.
- **PhpSpreadsheet 1.9 → 2.4**, **PHPWord 0.17 → 1.4**, **Predis 2.1 → 2.4**
  (versi lama tidak mendukung PHP 8). Gaya `applyFromArray()` disesuaikan
  (`fillType`, `startColor`).
- **`header('Location: ...')`** sekarang diikuti `exit()` supaya halaman tidak
  dilanjutkan tanpa session.
- Grafik dashboard memakai variabel terpisah (`$chart_*`) agar tidak bertabrakan dengan
  variabel baris data modul lain.
- **`panel/mod_siswa/importsiswa.php`** dan **`panel/mod_sinkron/hapusnilai.php`**
  memakai path `config` berbasis `__DIR__` sehingga bisa dipanggil langsung (sebelumnya
  `../config` menunjuk folder yang salah).
- Halaman `panel/pages/word_import/import` (modul CodeIgniter 3 lama) tidak lagi dipakai
  panel; import Word kini ditangani langsung oleh aplikasi (lihat bagian Import Soal).
- **Base URL otomatis** (`config/config.default.php`): `$homeurl`, `$pg`, `$ac`, `$id`
  dihitung dari `SCRIPT_NAME` + `REQUEST_URI`, jadi aplikasi jalan baik di subfolder
  (`/candycbt`) maupun di root domain/virtual host tanpa mengedit kode.
- `session_start()` tetap di `config/config.default.php`; cookie session mengikuti
  nama host, bukan path, sehingga login panel dan siswa tidak bentrok.

### Import soal dari Ms Word (format tabel)

Menu **Bank Soal → Import** menyediakan dua format yang polanya sama, sehingga pengguna
CBT tidak perlu mempelajari sintaks khusus:

| Format | Template | Kolom |
|---|---|---|
| Excel (.xls) | `panel/template/importdatasoal.xls` | `NO, SOAL, A, B, C, D, E, JAWABAN, JENIS` |
| Word (.docx) | `panel/template/importdatasoal.docx` | tabel `NO, SOAL, A, B, C, D, E, KUNCI` |

- Satu baris tabel = satu soal. Baris judul (header) dikenali otomatis, jadi urutan
  kolom boleh berbeda; bila header tidak ada, dipakai urutan tetap di atas.
- Kolom **JENIS** opsional: `1` = pilihan ganda, `2` = esai/uraian. Tanpa kolom ini,
  baris dengan KUNCI kosong dianggap esai.
- Saat import **menambah** soal (tanpa centang "Hapus soal lama"), nomor soal selalu
  dilanjutkan otomatis supaya tidak bertabrakan dengan soal yang sudah ada.
- Gambar/audio tidak diambil dari Word; unggah lewat kotak **File Pendukung Soal** (zip).
- Parser: `config/docx_soal.php` (memakai PhpWord `IOFactory`), handler:
  `panel/mod_banksoal/crud_soal.php?pg=import_word`, template dibuat ulang dengan
  `php tmp/make-docx-template.php` bila perlu diubah.

### Diketahui belum sempurna

- Menu **Materi** pada panel memanggil `panel/materi/materi.php` yang tidak ada pada
  paket ini; sekarang panel menampilkan pemberitahuan, bukan warning PHP.
- Modul `panel/pages/word_import` (CodeIgniter 3.0.6) masih ada di paket tetapi sudah
  tidak dipakai; hanya dipertahankan agar tidak menghapus berkas lama.

## 6. Struktur penting

```
config/config.database.php   koneksi DB (env / config.local.php)
config/config.default.php    bootstrap, base URL, mode debug
config/compat.php            shim kompatibilitas PHP 8
config/database.sql          satu-satunya file SQL: skema kosong + admin awal (utf8mb4)
config/docx_soal.php         pembaca template import soal Word (tabel)
vendor/                      dependency composer (PhpSpreadsheet, PHPWord, Predis)
panel/                       panel admin/guru/pengawas
panel/template/              template import: soal (.xls/.docx), siswa, guru, mapel
tools/buat-paket.py          skrip pembuat paket .zip untuk distribusi
```

## 7. Membuat paket distribusi (.zip)

Paket .zip siap dibagikan dibuat dengan skrip Python (tanpa dependency tambahan):

```bash
python tools/buat-paket.py                    # hasil: ../candycbt-2.9.4-YYYYMMDD.zip
python tools/buat-paket.py --checksum         # + berkas .sha256
python tools/buat-paket.py --output D:/bagi/candycbt.zip
python tools/buat-paket.py --no-vendor        # tanpa vendor/ (penerima: composer install)
python tools/buat-paket.py --dry-run          # lihat isi paket sebelum dibuat
python tools/buat-paket.py --exclude "*.apk"  # pola pengecualian tambahan
```

Yang **tidak** disertakan secara otomatis: `config/config.local.php` (kredensial),
semua dump `*.sql` selain `config/database.sql`, unggahan pengguna (`files/`,
`foto/fotosiswa/`, `tugas/`, backup soal), berkas log, dan sampah OS/editor.
`vendor/` (12 MB) tetap disertakan sehingga penerima tidak perlu `composer install`.
Di dalam zip juga ditulis `PAKET-INFO.txt` (versi, tanggal, ringkasan langkah instalasi).
