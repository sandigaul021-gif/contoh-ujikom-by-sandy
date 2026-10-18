<?php
// File: config.php
// Deskripsi: Konfigurasi inti aplikasi.
// Fungsi utama:
// 1. Koneksi database
// 2. Session login untuk admin/pemilih
// 3. CSRF token agar request form aman dari serangan cross-site request forgery

mysqli_report(MYSQLI_REPORT_OFF);

// Gunakan zona waktu sekolah agar jadwal dan waktu suara konsisten.
date_default_timezone_set('Asia/Jakarta');

// Pengaturan koneksi MySQL lokal (sesuaikan dengan konfigurasi server).
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'vote_osis';

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    exit('Koneksi database gagal.');
}

mysqli_set_charset($conn, 'utf8mb4');

// Session menyimpan identitas dan peran pengguna setelah login.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 1800,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Token CSRF acak disimpan di session lalu disertakan dalam form yang mengubah data.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_token(): string
{
    // Buat token jika session belum memilikinya, lalu kembalikan untuk form.
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function validate_csrf(string $token): bool
{
    // Bandingkan token form dengan token session secara aman.
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}
?>

