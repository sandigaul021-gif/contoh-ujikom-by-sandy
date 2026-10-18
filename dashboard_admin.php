<?php
// File: dashboard_admin.php
// Deskripsi: Dashboard admin untuk melihat ringkasan dan tombol cetak kartu pemilih.
// Halaman ini menjadi titik awal admin setelah berhasil login.

require_once 'admin_guard.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    exit('Koneksi database tidak tersedia.');
}

require_once 'function_jadwal.php';

$nama_admin = htmlspecialchars($_SESSION['username_admin'], ENT_QUOTES, 'UTF-8');

$total_kandidat = (int) mysqli_fetch_row(mysqli_query($conn, 'SELECT COUNT(*) FROM tb_kandidat'))[0];
$total_pemilih = (int) mysqli_fetch_row(mysqli_query($conn, 'SELECT COUNT(*) FROM tb_pemilih'))[0];
$total_suara = (int) mysqli_fetch_row(mysqli_query($conn, 'SELECT COUNT(*) FROM tb_vote'))[0];
$partisipasi = $total_pemilih > 0 ? round(($total_suara / $total_pemilih) * 100, 1) : 0.0;

// Status jadwal menentukan lencana kondisi pemilihan pada dashboard.
$status_voting = statusVoting(ambilJadwalVoting($conn));
$daftar_kelas = [
    'berlangsung'  => 'badge-success',
    'belum_dibuka' => 'badge-warning',
    'ditutup'      => 'badge-danger',
    'nonaktif'     => 'badge-danger',
    'belum_diatur' => 'badge-muted',
];
$kelas_status = $daftar_kelas[$status_voting] ?? 'badge-muted';

$halaman_aktif = 'dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin | PEMILIHAN KETUA OSIS</title>
    <link rel="stylesheet" href="tema.css?v=ux-biru-emas-20261014">
</head>
<body>
    <?php require_once 'header_admin.php'; ?>

    <main class="container page">
        <div class="page-head">
            <div>
                <h1>Selamat datang, <?= $nama_admin ?></h1>
                <p class="muted" style="margin:0;">Ringkasan pemilihan Ketua OSIS saat ini.</p>
            </div>
            <span class="badge <?= $kelas_status ?>">Pemilihan: <?= htmlspecialchars(labelStatusVoting($status_voting), ENT_QUOTES, 'UTF-8') ?></span>
        </div>


        <!-- Angka ringkasan dihitung langsung dari tabel utama database. -->
        <section class="stat-grid" aria-label="Ringkasan pemilihan">
            <div class="card stat"><strong><?= $total_kandidat ?></strong><span>Kandidat Terdaftar</span></div>
            <div class="card stat"><strong><?= $total_pemilih ?></strong><span>Pemilih Terdaftar</span></div>
            <div class="card stat"><strong><?= $total_suara ?></strong><span>Sudah Memilih</span></div>
            <div class="card stat"><strong><?= number_format($partisipasi, 1) ?>%</strong><span>Tingkat Partisipasi</span></div>
        </section>

        <!-- Pintasan ke fungsi CRUD, pengaturan jadwal, hasil, dan kartu pemilih. -->
        <section class="menu-grid" aria-label="Menu pengelolaan">
            <a class="card menu-card" href="kandidat.php"><strong>Kelola Kandidat</strong><span></span></a>
            <a class="card menu-card" href="pemilih.php"><strong>Kelola Pemilih</strong><span></span></a>
            <a class="card menu-card" href="jadwal.php"><strong>Jadwal Pemilihan</strong><span></span></a>
            <a class="card menu-card" href="hasil_voting.php"><strong>Hasil Pemilihan</strong><span></span></a>
            <a class="card menu-card" href="cetak_kartu_pemilih.php"><strong>Cetak Kartu Pemilih</strong></a>
    </main>
</body>
</html>





