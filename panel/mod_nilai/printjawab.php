<?php
require("../../config/config.default.php");
require("../../config/config.function.php");
require("../../config/functions.crud.php");
require("../../config/dis.php");
(isset($_SESSION['id_pengawas'])) ? $id_pengawas = $_SESSION['id_pengawas'] : $id_pengawas = 0;
($id_pengawas == 0) ? header('location:login.php') : null;
error_reporting(0);
$id_mapel = isset($_GET['m']) ? $_GET['m'] : 0;
$id_kelas = isset($_GET['k']) ? $_GET['k'] : '';
$id_siswa = isset($_GET['s']) ? $_GET['s'] : 0;
$nilai = cbt_fetch_array(mysqli_query($koneksi, "SELECT * FROM nilai WHERE id_siswa='$id_siswa' and id_mapel='$id_mapel' "));
$nilai = is_array($nilai) ? $nilai : array('id_mapel' => $id_mapel, 'total' => 0, 'jawaban' => '', 'jawaban_esai' => '', 'nilai_esai2' => '');
$mapel = cbt_fetch_array(mysqli_query($koneksi, "select * from mapel where id_mapel='$nilai[id_mapel]'"));
$mapel = is_array($mapel) ? $mapel : array('nama' => '');
$namamapel = cbt_fetch_array(mysqli_query($koneksi, "select * from mata_pelajaran where kode_mapel='$mapel[nama]'"));
$namamapel = is_array($namamapel) ? $namamapel : array('nama_mapel' => '');
$siswa = cbt_fetch_array(mysqli_query($koneksi, "SELECT * FROM siswa WHERE id_siswa='$id_siswa'"));
$siswa = is_array($siswa) ? $siswa : array('nis' => '', 'nama' => '', 'id_kelas' => '');
echo "
		<!DOCTYPE html>
		<html>
			<head>
				<title>Jawaban Siswa</title>
				<style>
					* { margin:auto; padding:0; line-height:100%; }
					body { max-width:793.700787402px; }
					td { padding:1px 3px 1px 3px; }
					.garis { border:1px solid #000; border-left:0px; border-right:0px; padding:1px; margin-top:5px; margin-bottom:5px; }
				</style>
				<link rel='stylesheet' href='$homeurl/dist/bootstrap/css/bootstrap.min.css'/>
				<link rel='stylesheet' href='$homeurl/plugins/font-awesome/css/font-awesome.min.css'/>
			</head>
			<body>
											<table class='table table-bordered table-striped table-condensed'> 
											<tr><th width='150'>No Induk</th><td width='10'>:</td><td>$siswa[nis]</td><td width='150' align='center'>Total Nilai</td></tr>
											<tr><th >Nama</th><td width='10'>:</td><td>$siswa[nama]</td><td rowspan='3' width='150' align='center' style='font-size:30px'>$nilai[total]</td></tr>
											<tr><th >Kelas</th><td width='10'>:</td><td>$siswa[id_kelas]</td></tr>
											<tr><th >Mata Pelajaran</th><td width='10'>:</td><td>$namamapel[nama_mapel]</td></tr>
											</table>
												<table  class='table table-bordered table-striped'>
													<thead>
														<tr><th width='5px'>#</th>
															
															<th>Soal PG</th>
															<th style='text-align:center'>Jawab</th>
															<th style='text-align:center'>Hasil</th>
															
														</tr>
													</thead>
													<tbody>";
$nilaix = mysqli_query($koneksi, "SELECT * FROM hasil_jawaban WHERE id_siswa='$id_siswa' and id_mapel='$id_mapel' and jenis='1' ");
while ($jawaban = cbt_fetch_array($nilaix)) {
	$no++;
	$soal = cbt_fetch_array(mysqli_query($koneksi, "select * from soal where id_soal='$jawaban[id_soal]'  "));
	if ($jawaban['jawaban'] == $soal['jawaban']) {
		$status = "<span class='text-green'><i class='fa fa-check'></i></span>";
	} else {
		$status = "<span class='text-red'><i class='fa fa-times'></i></span>";
	}
	echo "
																		<tr><td>$no</td>
																			
																			<td>$soal[soal]</td>
																		    <td style='text-align:center'>$jawaban[jawaban]</td>
																			<td style='text-align:center'>$status</td>	
																			
																			
																		</tr>";
}
echo "
																	</tbody>
																</table>
																</table>
												<table  class='table table-bordered table-striped'>
													<thead>
														<tr><th width='5px'>#</th>
															
															<th>Soal Esai</th>
															
															<th style='text-align:center'>Hasil</th>
															
														</tr>
													</thead>
													<tbody>";
$nilaix = mysqli_query($koneksi, "SELECT * FROM hasil_jawaban WHERE id_siswa='$id_siswa' and id_mapel='$id_mapel' and jenis='2' ");
while ($jawaban = cbt_fetch_array($nilaix)) {
	$soal = cbt_fetch_array(mysqli_query($koneksi, "select * from soal where id_soal='$jawabane[id_soal]'  "));
	$nox++;


	echo "
																		<tr><td>$nox</td>
																			
																			<td>$soal[soal]
																			<p><b>jawab : </b>$jawaban[esai]</p>
																			</td>
																		    
																			<td style='text-align:center'>$jawaban[nilai_esai]</td>	
																			
																			
																		</tr>";
}
echo "
																	</tbody>
																</table>
			</body>
		</html>
	";
