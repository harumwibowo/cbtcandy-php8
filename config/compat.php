<?php
/**
 * Shim kompatibilitas PHP 8 untuk kode gaya PHP 5.
 *
 * Di PHP 8, mysqli_* melempar exception (lihat mysqli_report di config.database.php)
 * dan mysqli_fetch_*() dengan argumen false/null berakibat TypeError yang mematikan
 * halaman. Fungsi cbt_*() di bawah mengembalikan hasil kosong seperti perilaku
 * PHP 5 sehingga aplikasi tetap berjalan walaupun sebuah query gagal.
 */

if (!function_exists('cbt_fetch_array')) {
	function cbt_fetch_array($result, $mode = MYSQLI_BOTH)
	{
		if (!($result instanceof mysqli_result)) {
			return null;
		}
		return mysqli_fetch_array($result, $mode);
	}
}

if (!function_exists('cbt_fetch_assoc')) {
	function cbt_fetch_assoc($result)
	{
		if (!($result instanceof mysqli_result)) {
			return null;
		}
		return mysqli_fetch_assoc($result);
	}
}

if (!function_exists('cbt_fetch_row')) {
	function cbt_fetch_row($result)
	{
		if (!($result instanceof mysqli_result)) {
			return null;
		}
		return mysqli_fetch_row($result);
	}
}

if (!function_exists('cbt_num_rows')) {
	function cbt_num_rows($result)
	{
		if (!($result instanceof mysqli_result)) {
			return 0;
		}
		return mysqli_num_rows($result);
	}
}

if (!function_exists('cbt_num_fields')) {
	function cbt_num_fields($result)
	{
		if (!($result instanceof mysqli_result)) {
			return 0;
		}
		return mysqli_num_fields($result);
	}
}

if (!function_exists('cbt_free_result')) {
	function cbt_free_result($result)
	{
		if ($result instanceof mysqli_result) {
			mysqli_free_result($result);
		}
	}
}

if (!function_exists('cbt_unserialize_array')) {
	/**
	 * unserialize() yang selalu mengembalikan array.
	 *
	 * Data lama/kosong/rusak pada kolom seperti kelas, id_pk, idpk, atau jawaban
	 * sebelumnya menghasilkan false. Di PHP 8 nilai false itu memicu TypeError
	 * ketika dipakai in_array()/implode()/foreach, jadi dikembalikan sebagai array kosong.
	 */
	function cbt_unserialize_array($data)
	{
		if (!is_string($data) || $data === '') {
			return array();
		}
		$result = @unserialize($data, array('allowed_classes' => false));
		return is_array($result) ? $result : array();
	}
}

if (!function_exists('cbt_str')) {
	/**
	 * Cast aman ke string untuk parameter fungsi PHP 8 yang tidak menerima null.
	 */
	function cbt_str($value)
	{
		if (is_array($value) || is_object($value)) {
			return '';
		}
		return (string) $value;
	}
}
