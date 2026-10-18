<?php
// File: jadwal.php
// Deskripsi: Pengaturan jadwal voting oleh admin.
// Setiap form POST diberi token CSRF agar jadwal voting tidak bisa diubah dari situs luar.

require_once 'admin_guard.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    exit('Koneksi database tidak tersedia.');
}

require_once 'function_jadwal.php';
pastikanTabelPengaturan($conn);

$pesan = '';
$error = '';
$input_mulai = null;
$input_selesai = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form POST menangani perubahan status aktif atau rentang waktu pemilihan.
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Sesi form tidak valid. Silakan coba lagi.';
    } elseif (($_POST['aksi'] ?? '') === 'toggle') {
        $aktif = ($_POST['aktif'] ?? '') === '1' ? 1 : 0;
        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO tb_pengaturan (id, aktif) VALUES (1, ?)
             ON DUPLICATE KEY UPDATE aktif = VALUES(aktif)'
        );
        mysqli_stmt_bind_param($stmt, 'i', $aktif);
        if (mysqli_stmt_execute($stmt)) {
            $pesan = $aktif ? 'Voting berhasil diaktifkan.' : 'Voting berhasil dimatikan.';
        } else {
            $error = 'Status voting gagal diubah.';
        }
        mysqli_stmt_close($stmt);
    } else {
        $input_mulai = trim($_POST['waktu_mulai'] ?? '');
        $input_selesai = trim($_POST['waktu_selesai'] ?? '');

        // Validasi format datetime-local sebelum waktu disimpan ke database.
        $mulai = DateTime::createFromFormat('Y-m-d\\TH:i', $input_mulai);
        $selesai = DateTime::createFromFormat('Y-m-d\\TH:i', $input_selesai);

        if (!$mulai || !$selesai) {
            $error = 'Waktu mulai dan waktu selesai wajib diisi.';
        } elseif ($selesai <= $mulai) {
            $error = 'Waktu selesai harus setelah waktu mulai.';
        } else {
            $mulai_db = $mulai->format('Y-m-d H:i:s');
            $selesai_db = $selesai->format('Y-m-d H:i:s');

            $stmt = mysqli_prepare(
                $conn,
                'INSERT INTO tb_pengaturan (id, waktu_mulai, waktu_selesai) VALUES (1, ?, ?)
                 ON DUPLICATE KEY UPDATE waktu_mulai = VALUES(waktu_mulai), waktu_selesai = VALUES(waktu_selesai)'
            );
            mysqli_stmt_bind_param($stmt, 'ss', $mulai_db, $selesai_db);
            if (mysqli_stmt_execute($stmt)) {
                $pesan = 'Jadwal voting berhasil disimpan.';
                $input_mulai = null;
                $input_selesai = null;
            } else {
                $error = 'Jadwal voting gagal disimpan.';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

$jadwal = ambilJadwalVoting($conn);
$status = statusVoting($jadwal);

if ($input_mulai === null) {
    $input_mulai = !empty($jadwal['waktu_mulai']) ? date('Y-m-d\TH:i', strtotime($jadwal['waktu_mulai'])) : '';
}
if ($input_selesai === null) {
    $input_selesai = !empty($jadwal['waktu_selesai']) ? date('Y-m-d\TH:i', strtotime($jadwal['waktu_selesai'])) : '';
}

$target_ms = 0;
$teks_timer = '';
if ($status === 'belum_dibuka') {
    $target_ms = strtotime($jadwal['waktu_mulai']) * 1000;
    $teks_timer = 'Pemilihan dibuka dalam';
} elseif ($status === 'berlangsung') {
    $target_ms = strtotime($jadwal['waktu_selesai']) * 1000;
    $teks_timer = 'Pemilihan ditutup dalam';
}

function tampilWaktu(?string $waktu): string
{
    return $waktu ? date('d-m-Y H:i', strtotime($waktu)) . ' WIB' : '-';
}

$halaman_aktif = 'jadwal';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atur Jadwal Voting | E-Voting OSIS</title>
    <link rel="stylesheet" href="tema.css?v=ux-biru-emas-20261014">
</head>
<body>
    <?php require_once 'header_admin.php'; ?>

    <main class="container page" style="max-width:700px;">
        <div class="page-head"><h1>Atur Jadwal Voting</h1></div>

        <?php if ($pesan !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

        <div class="jadwal-box jadwal-<?= $status ?>">
            <strong class="judul"><?= htmlspecialchars(labelStatusVoting($status), ENT_QUOTES, 'UTF-8') ?></strong>
            <div class="jadwal-waktu">
                Mulai: <?= tampilWaktu($jadwal['waktu_mulai']) ?><br>
                Selesai: <?= tampilWaktu($jadwal['waktu_selesai']) ?>
            </div>
            <?php if ($target_ms > 0): ?>
                <div class="jadwal-timer">
                    <span><?= $teks_timer ?></span>
                    <strong id="timer">--:--:--</strong>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sakelar ini hanya mengaktifkan atau menonaktifkan pemungutan suara. -->
        <form method="post" style="margin-bottom:20px;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="aksi" value="toggle">
            <input type="hidden" name="aktif" value="<?= empty($jadwal['aktif']) ? '1' : '0' ?>">
            <button class="btn" type="submit"><?= empty($jadwal['aktif']) ? 'Aktifkan Voting' : 'Matikan Voting' ?></button>
        </form>

        <!-- Form terpisah menyimpan waktu mulai dan selesai. -->
        <form class="card form" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="aksi" value="simpan_jadwal">
            <h2>Ubah Jadwal</h2>
            <div>
                <label for="waktu_mulai">Waktu mulai</label>
                <input id="waktu_mulai" name="waktu_mulai" type="datetime-local" required
                       value="<?= htmlspecialchars($input_mulai, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div>
                <label for="waktu_selesai">Waktu selesai</label>
                <input id="waktu_selesai" name="waktu_selesai" type="datetime-local" required
                       value="<?= htmlspecialchars($input_selesai, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div><button class="btn" type="submit">Simpan Jadwal</button></div>
        </form>
    </main>

    <?php if ($target_ms > 0): ?>
    <script>
        var selisihServer = <?= time() * 1000 ?> - Date.now();
        var target = <?= $target_ms ?>;
        var elemen = document.getElementById('timer');

        function dua(n) { return n < 10 ? '0' + n : '' + n; }

        function tik() {
            var sisa = target - (Date.now() + selisihServer);
            if (sisa <= 0) {
                elemen.textContent = '00:00:00';
                setTimeout(function () { location.href = location.pathname; }, 1000);
                return;
            }
            var detik = Math.floor(sisa / 1000);
            var hari = Math.floor(detik / 86400);
            var jam = Math.floor((detik % 86400) / 3600);
            var menit = Math.floor((detik % 3600) / 60);
            elemen.textContent = (hari > 0 ? hari + ' hari ' : '') + dua(jam) + ':' + dua(menit) + ':' + dua(detik % 60);
            setTimeout(tik, 1000);
        }
        tik();
    </script>
    <?php endif; ?>
</body>
</html>





