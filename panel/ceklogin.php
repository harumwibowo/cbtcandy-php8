<?php
require("../config/config.default.php");
require("../config/config.function.php");
require("../config/config.candy.php");

$username = isset($_POST['username']) ? mysqli_escape_string($koneksi, $_POST['username']) : '';
$password = isset($_POST['password']) ? mysqli_escape_string($koneksi, $_POST['password']) : '';
$query = mysqli_query($koneksi, "SELECT * FROM pengawas WHERE username='$username'");
$cek = cbt_num_rows($query);
$user = cbt_fetch_array($query);
if ($cek <> 0) {
    if ($user['level'] == 'admin') {
        if (!password_verify($password, $user['password'])) {
            echo "nopass";
        } else {
            $_SESSION['id_pengawas'] = $user['id_pengawas'];
            $_SESSION['level'] = 'admin';
            echo "ok";
        }
    } elseif ($user['level'] == 'guru') {
        if (!password_verify($password, $user['password'])) {
            echo "nopass";
        } else {
            $_SESSION['id_pengawas'] = $user['id_pengawas'];
            $_SESSION['level'] = 'guru';
            echo "ok";
        }
    } elseif ($user['level'] == 'pengawas') {
        if (!password_verify($password, $user['password'])) {
            echo "nopass";
        } else {
            $_SESSION['id_pengawas'] = $user['id_pengawas'];
            $_SESSION['level'] = 'pengawas';
            echo "ok";
        }
    }
} elseif ($cek == 0) {
    echo "<script>alert('Pengguna tidak terdaftar');</script>";
}
