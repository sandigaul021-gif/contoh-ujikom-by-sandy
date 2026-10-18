<?php
// File: admin_guard.php
// Deskripsi: Proteksi halaman admin.
// Jika user belum login sebagai admin, otomatis diarahkan ke login_admin.php.
// File ini dipanggil oleh halaman admin agar pemeriksaan hak akses terpusat.

require_once 'config.php';

// Role dan ID admin harus sama-sama ada di session untuk membuka halaman admin.
if (($_SESSION['role'] ?? '') !== 'admin' || empty($_SESSION['id_admin'])) {
    header('Location: login_admin.php');
    exit;
}
?>

