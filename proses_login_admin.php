<?php
// File: proses_login_admin.php
// Deskripsi: Proses login admin dengan validasi CSRF dan session login yang aman.
// Setelah login berhasil, role admin akan diset dan redirect ke dashboard_admin.php.

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login_admin.php');
    exit;
}

// Validasi token CSRF agar request login berasal dari form yang benar-benar dibuka user.
if (!validate_csrf($_POST['csrf_token'] ?? '')) {
    $_SESSION['error_login_admin'] = 'Sesi login tidak valid. Silakan coba lagi.';
    header('Location: login_admin.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    $_SESSION['error_login_admin'] = 'Username dan password wajib diisi.';
    header('Location: login_admin.php');
    exit;
}

// Prepared statement menjaga input username tetap diperlakukan sebagai data.
$stmt = mysqli_prepare($conn, 'SELECT id_admin, username, password FROM tb_admin WHERE username = ? LIMIT 1');

if (!$stmt) {
    $_SESSION['error_login_admin'] = 'Terjadi kesalahan sistem. Silakan coba lagi.';
    header('Location: login_admin.php');
    exit;
}

mysqli_stmt_bind_param($stmt, 's', $username);
mysqli_stmt_execute($stmt);
$db_password = '';
mysqli_stmt_bind_result($stmt, $id_admin, $username_admin, $db_password);

if (mysqli_stmt_fetch($stmt)) {
    // Untuk kompatibilitas migrasi, kita menerima password bcrypt atau string lama.
    $password_ok = password_verify($password, $db_password) || hash_equals((string) $db_password, $password);

    if ($password_ok) {
        // Ganti ID session setelah login agar session lama tidak dapat dipakai ulang.
        session_regenerate_id(true);
        $_SESSION['id_admin'] = $id_admin;
        $_SESSION['username_admin'] = $username_admin;
        $_SESSION['role'] = 'admin';

        mysqli_stmt_close($stmt);
        header('Location: dashboard_admin.php');
        exit;
    }
}

mysqli_stmt_close($stmt);
$_SESSION['error_login_admin'] = 'Username atau password tidak sesuai.';
header('Location: login_admin.php');
exit;
?>

