<?php
/**
 * Konfigurasi koneksi database Candy CBT.
 *
 * Kompatibel dengan PHP 8.1 - 8.3 dan MySQL 8.0 / 8.4 / 9.x.
 *
 * Nilai default bisa dioverride tanpa mengubah file ini:
 *   1. Environment variable: DB_HOST, DB_PORT, DB_USER, DB_PASS, DB_NAME
 *   2. File opsional config/config.local.php (tidak ikut ter-commit). Dua bentuk
 *      sama-sama didukung:
 *      a) mengembalikan array:
 *         <?php
 *         return [
 *             'host' => '127.0.0.1',
 *             'port' => 3306,
 *             'user' => 'root',
 *             'pass' => '',
 *             'name' => 'candyold',
 *         ];
 *      b) mengisi variabel $db_config:
 *         <?php
 *         $db_config = [ 'host' => '...', 'user' => '...', ... ];
 */

// Shim kompatibilitas PHP 8 (cbt_fetch_array, cbt_num_rows, dll)
require_once __DIR__ . '/compat.php';

// Nilai default (Laragon / XAMPP lokal)
$db_config = [
	'host' => getenv('DB_HOST') !== false ? getenv('DB_HOST') : '127.0.0.1',
	'port' => getenv('DB_PORT') !== false ? (int) getenv('DB_PORT') : 3306,
	'user' => getenv('DB_USER') !== false ? getenv('DB_USER') : 'root',
	'pass' => getenv('DB_PASS') !== false ? getenv('DB_PASS') : '',
	'name' => getenv('DB_NAME') !== false ? getenv('DB_NAME') : 'candyold',
];

// Override lokal (opsional, tidak ikut ter-commit)
if (is_file(__DIR__ . '/config.local.php')) {
	$default = $db_config;
	$local = include __DIR__ . '/config.local.php';
	// config.local.php boleh `return [...]` atau mengisi variabel $db_config langsung.
	if (!is_array($local) && is_array($db_config)) {
		$local = $db_config;
	}
	if (is_array($local)) {
		$db_config = array_merge($default, array_intersect_key($local, $default));
	}
}

$host = $db_config['host'];
$user = $db_config['user'];
$pass = $db_config['pass'];
$debe = $db_config['name'];
$port = (int) $db_config['port'];

/*
 * PHP 8.1+ mengubah mode error mysqli menjadi MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT,
 * sehingga query yang gagal langsung melempar exception (fatal error, halaman blank).
 * Aplikasi ini ditulis dengan perilaku lama (mysqli_query() mengembalikan false),
 * jadi mode lama dikembalikan di sini.
 */
if (function_exists('mysqli_report')) {
	mysqli_report(MYSQLI_REPORT_OFF);
}

$koneksi = @mysqli_connect($host, $user, $pass, '', $port);

if (!$koneksi) {
	// Jangan tampilkan password / detail internal ke pengguna.
	error_log('CandyCBT: koneksi database gagal - ' . mysqli_connect_error());
	http_response_code(500);
	echo '<div style="font-family:sans-serif;padding:20px">';
	echo '<h3>Koneksi database gagal</h3>';
	echo '<p>Aplikasi tidak dapat terhubung ke server MySQL. Periksa konfigurasi database '
		. 'pada <code>config/config.database.php</code> atau environment variable DB_HOST, DB_USER, DB_PASS, DB_NAME.</p>';
	echo '</div>';
	exit();
}

$pilihdb = @mysqli_select_db($koneksi, $debe);
if (!$pilihdb) {
	error_log('CandyCBT: database "' . $debe . '" tidak ditemukan / tidak bisa diakses - ' . mysqli_error($koneksi));
	http_response_code(500);
	echo '<div style="font-family:sans-serif;padding:20px">';
	echo '<h3>Database belum tersedia</h3>';
	echo '<p>Database <code>' . htmlspecialchars($debe, ENT_QUOTES, 'UTF-8') . '</code> tidak ditemukan. '
		. 'Import file <code>config/database.sql</code> terlebih dahulu (lihat README).</p>';
	echo '</div>';
	exit();
}

// Charset koneksi: utf8mb4 agar mendukung emoji / karakter 4 byte pada MySQL 8+.
if (!@mysqli_set_charset($koneksi, 'utf8mb4')) {
	@mysqli_set_charset($koneksi, 'utf8');
}

/*
 * MySQL 5.7+ / 8.x mengaktifkan ONLY_FULL_GROUP_BY secara default sehingga query
 * GROUP BY model lama (SELECT * ... GROUP BY satu kolom) menjadi error.
 * sql_mode di-set per-session agar tidak perlu mengubah my.ini server.
 * Set DB_LEGACY_SQL_MODE=0 (environment) untuk memakai sql_mode default server.
 */
if (getenv('DB_LEGACY_SQL_MODE') === false || filter_var(getenv('DB_LEGACY_SQL_MODE'), FILTER_VALIDATE_BOOLEAN)) {
	@mysqli_query($koneksi, "SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");
}

$query = @mysqli_query($koneksi, "SELECT * FROM setting WHERE id_setting='1'");
if ($query) {
	$setting = cbt_fetch_array($query);
	cbt_free_result($query);
	if (!empty($setting['waktu'])) {
		@date_default_timezone_set($setting['waktu']);
	}
}

$querySession = @mysqli_query($koneksi, "SELECT * FROM session WHERE id='1'");
if ($querySession) {
	$sess = cbt_fetch_array($querySession);
	cbt_free_result($querySession);
}

// Nilai default supaya kode lama tidak error saat tabel setting/session kosong.
if (!isset($setting) || !is_array($setting)) {
	$setting = array();
}
if (!isset($sess) || !is_array($sess)) {
	$sess = array();
}
