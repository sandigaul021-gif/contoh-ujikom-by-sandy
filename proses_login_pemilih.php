<?php
// File: proses_login_pemilih.php
// Deskripsi: Proses login pemilih dengan validasi CSRF dan pengecekan password yang aman.
// Password pemilih pada sistem ini dibuat acak dan disimpan plain text sesuai kebutuhan project.

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login_pemilih.php');
    exit;
}

if (!validate_csrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['error_login'] = 'Sesi login tidak valid. Silakan coba lagi.';
    header('Location: login_pemilih.php');
    exit;
}

$id_pemilih = trim($_POST['id_pemilih'] ?? '');
$password = $_POST['password'] ?? '';

if ($id_pemilih === '' || $password === '') {
    $_SESSION['error_login'] = 'ID pemilih dan password wajib diisi.';
    header('Location: login_pemilih.php');
    exit;
}

// Ambil satu akun berdasarkan ID; prepared statement mencegah input membentuk SQL baru.
$query = 'SELECT id_pemilih, username, password FROM tb_pemilih WHERE id_pemilih = ? LIMIT 1';
$stmt = mysqli_prepare($conn, $query);

if (!$stmt) {
    $_SESSION['error_login'] = 'Terjadi kesalahan sistem. Silakan coba lagi.';
    header('Location: login_pemilih.php');
    exit;
}

mysqli_stmt_bind_param($stmt, 's', $id_pemilih);
mysqli_stmt_execute($stmt);
mysqli_stmt_bind_result($stmt, $db_id_pemilih, $db_username, $db_password);

if (mysqli_stmt_fetch($stmt) && hash_equals((string) $db_password, $password)) {
    // Perbarui ID session dan simpan identitas/peran untuk pemeriksaan halaman berikutnya.
    session_regenerate_id(true);
    $_SESSION['id_pemilih'] = $db_id_pemilih;
    $_SESSION['username_pemilih'] = $db_username;
    $_SESSION['role'] = 'pemilih';

    mysqli_stmt_close($stmt);
    header('Location: pilih_kandidat.php');
    exit;
}

mysqli_stmt_close($stmt);
$_SESSION['error_login'] = 'ID pemilih atau password tidak sesuai.';
header('Location: login_pemilih.php');
exit;
?>

