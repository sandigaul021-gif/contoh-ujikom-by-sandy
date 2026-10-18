<?php
// File: login_pemilih.php
// Deskripsi: Halaman login pemilih.
// CSRF token juga ditambahkan agar request login tidak dapat di-trigger dari situs luar.

require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Pemilih | E-Voting OSIS</title>
    <link rel="stylesheet" href="tema.css?v=ux-biru-emas-20261014">
</head>
<body class="auth-page">
    <main class="card auth-card">
        <a class="brand brand-dark" href="index.php">
            <span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg></span>
            E-Voting OSIS
        </a>
        <h1>Login Pemilih</h1>
        <p class="muted">Masukkan ID pemilih dan password Anda untuk melanjutkan ke halaman pemilihan Ketua OSIS.</p>

        <!-- Tampilkan lalu hapus pesan login agar tidak muncul berulang. -->
        <?php if (isset($_SESSION['error_login'])): ?>
            <div class="alert alert-error" role="alert"><?= htmlspecialchars($_SESSION['error_login']) ?></div>
        <?php
            unset($_SESSION['error_login']);
        endif;
        ?>

        <!-- Pemilih masuk dengan ID dan password yang dibagikan panitia. -->
        <!-- Pemilih masuk menggunakan ID dan password yang diberikan panitia. -->
        <form class="form" action="proses_login_pemilih.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <div>
                <label for="id_pemilih">ID Pemilih</label>
                <input id="id_pemilih" name="id_pemilih" type="text" maxlength="20" autocomplete="username" placeholder="Masukkan NIS atau kode guru" required autofocus>
            </div>
            <div>
                <label for="password">Password</label>
                <div class="input-group">
                    <input id="password" name="password" type="password" minlength="10" maxlength="10" pattern="[A-Za-z0-9]{10}" autocomplete="current-password" placeholder="Masukkan password" required>
                    <button type="button" onclick="togglePassword('password', this)">Tampilkan</button>
                </div>
            </div>
            <button class="btn btn-block" type="submit">Masuk dan Pilih</button>
        </form>
        <a class="btn btn-outline btn-block" href="index.php" style="margin-top:10px;">Kembali ke beranda</a>

        <p class="auth-help">Tidak bisa masuk? Silakan hubungi panitia pemilihan.</p>
    </main>

    <script>
        // Tombol ini hanya mengubah tampilan password, tidak mengirim data ke server.
        function togglePassword(id, tombol) {
            var input = document.getElementById(id);
            var buka = input.type === 'password';
            input.type = buka ? 'text' : 'password';
            tombol.textContent = buka ? 'Sembunyikan' : 'Tampilkan';
        }
    </script>
</body>
</html>





