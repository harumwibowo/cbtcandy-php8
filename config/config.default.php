<?php
session_start();

/*
 * Mode debug: set APP_DEBUG=1 di environment (atau Laragon > Environment) untuk
 * menampilkan error PHP. Default mati supaya produksi tidak membocorkan path/query.
 */
$appDebug = filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
error_reporting($appDebug ? E_ALL : 0);
ini_set('display_errors', $appDebug ? '1' : '0');

(isset($_SESSION['id_user'])) ? $id_user = $_SESSION['id_user'] : $id_user = 0;

/*
 * Penentuan base URL dan segmen URL.
 *
 * Mendukung dua bentuk instalasi sekaligus:
 *   - subfolder : http://host/candycbt/          (/candycbt/jadwal)
 *   - root/vhost: http://candycbt.test/          (/jadwal)
 *
 * $homeurl selalu menunjuk root aplikasi, termasuk saat dipanggil dari /panel.
 */
$uriPath = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
$dir = str_replace('\\', '/', dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/index.php'));
$panelPos = strpos($dir, '/panel');
if ($panelPos !== false) {
	$dir = substr($dir, 0, $panelPos);
}
$base = rtrim($dir, '/');
if ($base === '.' || $base === '/') {
	$base = '';
}
$scheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
$homeurl = $scheme . "://" . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost') . $base;

$path = $uriPath;
if ($base !== '' && strpos($path, $base) === 0) {
	$path = substr($path, strlen($base));
}
$segments = array_values(array_filter(explode('/', $path), 'strlen'));
$pg = isset($segments[0]) ? $segments[0] : '';
$ac = isset($segments[1]) ? $segments[1] : '';
$id = isset($segments[2]) ? $segments[2] : 0;
$uri = $uriPath;
$pageurl = explode('/', $uriPath);

/* Base URL ($homeurl), $pg, $ac, dan $id sudah dihitung otomatis di atas,
   baik untuk instalasi subfolder maupun root domain / virtual host. */

require "config.database.php";

$no = $jam = $mnt = $dtk = 0;
$info = '';
$waktu = date('H:i:s');
$tanggal = date('Y-m-d');
$datetime = date('Y-m-d H:i:s');

define("KEY", "76310EEFF2B5D3C887F238976A421B638CFEB0942AB8249CD0A29B125C91B3E5");
