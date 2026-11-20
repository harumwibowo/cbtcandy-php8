<?php
require("config/config.default.php");
require("config/config.function.php");
//cek_session_admin();
$token = isset($_GET['token']) ? $_GET['token'] : 'false';
$querys = mysqli_query($koneksi, "select token_api from setting where token_api='$token'");
$cektoken = cbt_num_rows($querys);
if ($cektoken <> 0) {
  $querybank = mysqli_query($koneksi, "select * from mapel ");
  $array_bank = array();
  while ($bank = cbt_fetch_assoc($querybank)) {
    $array_bank[] = $bank;
  }
  $querysoal = mysqli_query($koneksi, "select * from soal ");
  $array_soal = array();
  while ($soalx = cbt_fetch_assoc($querysoal)) {
    $array_soal[] = $soalx;
  }
  $queryjadwal = mysqli_query($koneksi, "select * from ujian");
  $array_jadwal = array();
  while ($jadwal = cbt_fetch_assoc($queryjadwal)) {
    $array_jadwal[] = $jadwal;
  }
  $queryfile = mysqli_query($koneksi, "select * from file_pendukung");
  $array_file = array();
  while ($file = cbt_fetch_assoc($queryfile)) {
    $array_file[] = $file;
  }

  echo json_encode(
    [
      "bank" => $array_bank,
      "soal" => $array_soal,
      "jadwal" => $array_jadwal,
      "file" => $array_file
    ]
  );
} else {
  echo "<script>location.href='.'</script>";
}
