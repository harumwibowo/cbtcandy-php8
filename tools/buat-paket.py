#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
BUAT PAKET ZIP CANDYCBT
=======================

Membuat berkas .zip siap dibagikan / di-install fresh oleh orang lain.

Cara pakai (dari mana saja):

    python tools/buat-paket.py
    python tools/buat-paket.py --output D:/bagi/candycbt.zip
    python tools/buat-paket.py --no-vendor          # tanpa folder vendor (untuk clone + composer install)
    python tools/buat-paket.py --dry-run            # lihat isi paket tanpa menulis zip

Yang selalu dikeluarkan dari paket:
  - config/config.local.php      (berisi kredensial database lokal)
  - dump/backup *.sql            (kecuali config/database.sql yang memang skema instalasi)
  - unggahan pengguna: files/, foto/fotosiswa/, tugas/, panel/files/, backup_soal/
  - sampah OS/editor: Thumbs.db, .DS_Store, .git/, __pycache__/, *.log
  - output zip itu sendiri (bila disimpan di dalam proyek)

Hanya memakai standard library Python 3.7+.
"""

import argparse
import datetime
import fnmatch
import hashlib
import os
import re
import sys
import zipfile

# ---------------------------------------------------------------------------
# Konfigurasi dasar
# ---------------------------------------------------------------------------

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
DEFAULT_SOURCE = os.path.dirname(SCRIPT_DIR)          # .../candycbt
NAMA_PAKET = 'candycbt'

# Folder yang tidak pernah masuk paket (dicocokkan per bagian path)
EXCLUDE_DIRS = {
    '.git', '.svn', '.hg', '.idea', '.vscode', '__pycache__', '.pytest_cache',
    'node_modules', '.sass-cache', '.cache',
    'backup_soal',            # panel/mod_setting/backup_soal: dump database
    'fotosiswa',              # foto siswa hasil upload
    'files',                  # file pendukung soal hasil upload
    'tugas',                  # lampiran tugas hasil upload
    'temp',                   # folder kerja sementara
    'dist-zip',               # output paket (bila disimpan di dalam proyek)
}

# Berkas di dalam folder yang dikecualikan tetapi tetap dibutuhkan (penjaga folder)
KEEP_FILES = {
    ('files', '.htaccess'),
    ('files', 'index.html'),
    ('foto', 'fotosiswa', '.htaccess'),
    ('foto', 'fotosiswa', 'index.html'),
    ('tugas', 'index.html'),
    ('tugas', '.htaccess'),
}

# Pola nama berkas yang tidak ikut (dicocokkan ke nama file saja)
EXCLUDE_NAMES = [
    'Thumbs.db', 'desktop.ini', '.DS_Store', 'ehthumbs.db',
    '*.log', '*.tmp', '*.bak', '*.orig', '*.rej', '*.swp', '*.swo',
    '*~', '*.pyc', '*.pyo',
    'config.local.php', 'config.local.sample.php.bak',
    '* - Shortcut.lnk', '*.lnk',
]

# Pola path relatif yang tidak ikut (dicocokkan ke path gaya POSIX)
EXCLUDE_PATHS = [
    'dist-zip/*',
    'dist/*.zip',
    '*.zip',                        # paket lain, backup soal, dsb.
    'config/*.sql',                 # dump lama; database.sql dikecualikan di bawah
    'config/*.sql.gz',
    'panel/mod_setting/backup_soal/*',
    'panel/pages/word_import/import/upload/*',   # sisa modul lama
    'panel/phpqrcode/cache/frame_*',             # cache QR yang dihasilkan otomatis
    'panel/phpqrcode/cache/*.png',
    'panel/phpqrcode/cache/*.dat',
]

# Berkas yang selalu dipertahankan walaupun cocok EXCLUDE_PATHS
KEEP_PATHS = {
    'config/database.sql',          # skema instalasi fresh (wajib)
    'panel/phpqrcode/cache/index.html',
}

# Berkas wajib: kalau tidak ada, paket ditolak
WAJIB = [
    'config/database.sql',
    'config/config.default.php',
    'config/config.database.php',
    'index.php',
    'panel/index.php',
    'INSTALASI.TXT',
]


# ---------------------------------------------------------------------------
# Utilitas
# ---------------------------------------------------------------------------

def baca_versi(source):
    """Ambil konstanta VERSI dari config/config.candy.php."""
    path = os.path.join(source, 'config', 'config.candy.php')
    try:
        with open(path, 'r', encoding='utf-8', errors='replace') as f:
            isi = f.read()
    except OSError:
        return 'unknown'
    m = re.search(r'define\s*\(\s*["\']VERSI["\']\s*,\s*["\']([^"\']+)["\']', isi)
    return m.group(1) if m else 'unknown'


def cocok(nama, pola):
    return any(fnmatch.fnmatch(nama, p) for p in pola)


def kandidat_berkas(source, sertakan_vendor=True, exclude_tambahan=()):
    """Telusuri proyek, hasilkan (path_absolut, path_relatif_posix)."""
    hasil, dilewati = [], []
    for akar, dirs, files in os.walk(source):
        rel_akar = os.path.relpath(akar, source).replace('\\', '/')
        rel_akar = '' if rel_akar == '.' else rel_akar

        # prune folder
        dirs_baru = []
        for d in sorted(dirs):
            rel = (rel_akar + '/' + d) if rel_akar else d
            if d in EXCLUDE_DIRS:
                dilewati.append(rel + '/')
                continue
            if d == 'vendor' and not sertakan_vendor:
                dilewati.append(rel + '/')
                continue
            if cocok(rel, exclude_tambahan):
                dilewati.append(rel + '/')
                continue
            dirs_baru.append(d)
        dirs[:] = dirs_baru

        for f in sorted(files):
            rel = (rel_akar + '/' + f) if rel_akar else f
            bagian = tuple(rel.split('/'))
            if bagian in KEEP_FILES:
                hasil.append((os.path.join(akar, f), rel))
                continue
            if cocok(f, EXCLUDE_NAMES) and rel not in KEEP_PATHS:
                dilewati.append(rel)
                continue
            if rel in KEEP_PATHS:
                hasil.append((os.path.join(akar, f), rel))
                continue
            if cocok(rel, EXCLUDE_PATHS) or cocok(rel, exclude_tambahan):
                dilewati.append(rel)
                continue
            hasil.append((os.path.join(akar, f), rel))

    # Folder yang dipangkas di atas (files/, foto/fotosiswa/, tugas/) tetap perlu ada
    # di paket supaya fitur upload punya tempat menulis; sisipkan berkas penjaganya.
    sudah = {rel for _, rel in hasil}
    for bagian in sorted(KEEP_FILES):
        rel = '/'.join(bagian)
        if rel in sudah:
            continue
        path = os.path.join(source, *bagian)
        if os.path.isfile(path):
            hasil.append((path, rel))
            sudah.add(rel)

    hasil.sort(key=lambda x: x[1].lower())
    return hasil, sorted(dilewati)


def info_paket(versi, jumlah, ukuran_asli, root):
    sekarang = datetime.datetime.now().strftime('%d %B %Y %H:%M')
    return (
        'PAKET CANDYCBT\r\n'
        '================\r\n'
        'Versi aplikasi : %s\r\n'
        'Dibuat         : %s\r\n'
        'Jumlah berkas  : %d\r\n'
        'Ukuran asli    : %.1f MB\r\n'
        'Folder di zip  : %s/\r\n'
        '\r\n'
        'CARA INSTALL\r\n'
        '------------\r\n'
        '1. Ekstrak zip, letakkan folder %s di web root (mis. C:/laragon/www atau public_html).\r\n'
        '2. Buat database MySQL kosong (utf8mb4), mis. nama: candycbt.\r\n'
        '3. Import berkas config/database.sql ke database tersebut\r\n'
        '   (phpMyAdmin: menu Import, atau: mysql -u root candy < config/database.sql).\r\n'
        '4. Buka config/config.database.php, sesuaikan host, nama database, user, dan password\r\n'
        '   (bisa juga membuat config/config.local.php - lihat contohnya di INSTALASI.TXT).\r\n'
        '5. Buka aplikasi di browser: domain/candycbt/\r\n'
        '   Login admin: admin / admin  -> segera ganti password.\r\n'
        '\r\n'
        'Panduan lengkap (Laragon, cPanel, VPS): INSTALASI.TXT\r\n'
        'Ringkasan fitur & catatan teknis: README.md\r\n'
        '\r\n'
        'Berkas sensitif (config/config.local.php) dan unggahan pengguna (files, foto siswa,\r\n'
        'lampiran tugas) sengaja TIDAK disertakan agar aman dibagikan ke umum.\r\n'
        % (versi, sekarang, jumlah, ukuran_asli, root, root)
    )


def buat_zip(source, output, root, sertakan_vendor, exclude_tambahan,
             tulis_info=True, checksum=False, dry_run=False):
    for wajib in WAJIB:
        if not os.path.exists(os.path.join(source, wajib)):
            print('GAGAL: berkas wajib tidak ditemukan: %s' % wajib)
            return 2

    if sertakan_vendor and not os.path.isdir(os.path.join(source, 'vendor')):
        print('PERINGATAN: folder vendor tidak ada; paket butuh "composer install".')

    berkas, dilewati = kandidat_berkas(source, sertakan_vendor, tuple(exclude_tambahan))
    if not berkas:
        print('GAGAL: tidak ada berkas yang bisa dipaketkan.')
        return 2

    ukuran_asli = sum(os.path.getsize(p) for p, _ in berkas)
    versi = baca_versi(source)

    print('Sumber      : %s' % source)
    print('Versi       : %s' % versi)
    print('Berkas      : %d  (%.1f MB)' % (len(berkas), ukuran_asli / 1048576.0))
    print('Dikecualikan: %d entri' % len(dilewati))

    sensitif = [r for r in dilewati if r.endswith('config.local.php') or
                (r.endswith('.sql') and r != 'config/database.sql')]
    if sensitif:
        print('Aman        : %d berkas sensitif/dump tidak disertakan' % len(sensitif))

    if dry_run:
        print('\n-- 20 berkas pertama --')
        for _, rel in berkas[:20]:
            print('   ' + rel)
        print('   ... (%d berkas lain)' % max(0, len(berkas) - 20))
        print('\n(dry-run: zip tidak dibuat)')
        return 0

    folder_output = os.path.dirname(os.path.abspath(output))
    if folder_output and not os.path.isdir(folder_output):
        os.makedirs(folder_output)

    if os.path.exists(output):
        os.remove(output)

    with zipfile.ZipFile(output, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as z:
        for path, rel in berkas:
            z.write(path, arcname=root + '/' + rel)
        if tulis_info:
            z.writestr(root + '/PAKET-INFO.txt', info_paket(versi, len(berkas), ukuran_asli / 1048576.0, root))

    ukuran_zip = os.path.getsize(output)
    print('\nSelesai     : %s' % output)
    print('Ukuran zip  : %.1f MB (rasio %.0f%%)' % (ukuran_zip / 1048576.0,
                                                  100.0 * ukuran_zip / max(1, ukuran_asli)))
    print('Root di zip : %s/' % root)

    if checksum:
        h = hashlib.sha256()
        with open(output, 'rb') as f:
            for blok in iter(lambda: f.read(1048576), b''):
                h.update(blok)
        sidecar = output + '.sha256'
        with open(sidecar, 'w', encoding='ascii') as f:
            f.write('%s  %s\n' % (h.hexdigest(), os.path.basename(output)))
        print('SHA-256     : %s  (%s)' % (h.hexdigest(), sidecar))

    return 0


def main():
    p = argparse.ArgumentParser(
        description='Membuat paket .zip proyek CandyCBT untuk dibagikan / install fresh.',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog='Contoh:\n'
               '  python tools/buat-paket.py\n'
               '  python tools/buat-paket.py --output D:/bagi/candycbt.zip --checksum\n'
               '  python tools/buat-paket.py --no-vendor\n')
    p.add_argument('--source', default=DEFAULT_SOURCE,
                   help='folder proyek (default: folder induk script ini)')
    p.add_argument('--output', default=None,
                   help='nama berkas .zip (default: <induk proyek>/candycbt-<versi>-<tanggal>.zip)')
    p.add_argument('--root', default=None,
                   help='nama folder di dalam zip (default: candycbt-<versi>)')
    p.add_argument('--no-vendor', action='store_true',
                   help='jangan sertakan folder vendor (penerima harus menjalankan composer install)')
    p.add_argument('--exclude', action='append', default=[],
                   help='pola tambahan yang dikecualikan, boleh diulang (mis. --exclude "*.apk")')
    p.add_argument('--no-info', action='store_true', help='jangan tulis PAKET-INFO.txt')
    p.add_argument('--checksum', action='store_true', help='tulis <zip>.sha256 di samping zip')
    p.add_argument('--dry-run', action='store_true', help='tampilkan rencana isi paket tanpa menulis zip')
    a = p.parse_args()

    source = os.path.abspath(a.source)
    if not os.path.isdir(source):
        print('GAGAL: folder sumber tidak ditemukan: %s' % source)
        return 2

    versi = baca_versi(source)
    tanggal = datetime.date.today().strftime('%Y%m%d')
    root = a.root or ('%s-%s' % (NAMA_PAKET, versi))
    output = a.output or os.path.join(os.path.dirname(source),
                                      '%s-%s-%s.zip' % (NAMA_PAKET, versi, tanggal))

    print('=== BUAT PAKET CANDYCBT ===')
    return buat_zip(source, output, root, not a.no_vendor, a.exclude,
                    tulis_info=not a.no_info, checksum=a.checksum, dry_run=a.dry_run)


if __name__ == '__main__':
    sys.exit(main())
