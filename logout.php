<?php
// File logout untuk mengakhiri sesi user.
// Berguna saat admin atau pemilih menutup halaman dan ingin kembali ke login.
require_once 'config.php';

// Simpan role user sebelum session dibersihkan agar tahu ke halaman login mana harus kembali.
$role = $_SESSION['role'] ?? '';

// Kosongkan semua data session.
$_SESSION = [];

// Hapus cookie session agar session benar-benar benar-benar ditutup.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

// Hancurkan session di server setelah cookie session dihapus.
session_destroy();

// Redirect sesuai role pengguna.
$tujuan = $role === 'admin' ? 'login_admin.php' : 'login_pemilih.php';
header('Location: ' . $tujuan);
exit;
?>

