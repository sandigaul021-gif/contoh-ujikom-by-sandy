<?php
// File: login_admin.php
// Deskripsi: Halaman login admin.
// Form wajib menyertakan CSRF token agar request login tidak bisa dipalsukan oleh situs lain.

require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin | E-Voting OSIS</title>
    <link rel="stylesheet" href="tema.css?v=ux-biru-emas-20261014">
</head>
<body class="auth-page">
    <main class="card auth-card">
        <a class="brand brand-dark" href="index.php">
            <span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg></span>
            Pemilihan Ketua OSIS
        </a>
        <h1>Login Admin</h1>
        <p class="muted">Masuk untuk mengelola kandidat, pemilih, dan jadwal pemilihan.</p>

        <!-- Pesan gagal login disimpan sementara di session lalu dihapus setelah ditampilkan. -->
        <?php if (isset($_SESSION['error_login_admin'])): ?>
            <div class="alert alert-error" role="alert"><?= htmlspecialchars($_SESSION['error_login_admin']) ?></div>
            <?php unset($_SESSION['error_login_admin']); ?>
        <?php endif; ?>

        <!-- Form mengirim kredensial ke proses_login_admin.php melalui POST. -->
        <!-- Form mengirim data login ke proses_login_admin.php melalui POST. -->
        <form class="form" action="proses_login_admin.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <div>
                <label for="username">Username</label>
                <input id="username" name="username" type="text" maxlength="30" autocomplete="username" required autofocus>
            </div>
            <div>
                <label for="password">Password</label>
                <div class="input-group">
                    <input id="password" name="password" type="password" maxlength="10" pattern="[A-Za-z0-9]{10}" autocomplete="current-password" title="Password harus terdiri dari tepat 10 huruf atau angka." required>
                    <button type="button" onclick="togglePassword('password', this)">Tampilkan</button>
                </div>
            </div>
            <button class="btn btn-block" type="submit">Masuk</button>
        </form>
    </main>

    <script>
        function togglePassword(id, tombol) {
            var input = document.getElementById(id);
            var buka = input.type === 'password';
            input.type = buka ? 'text' : 'password';
            tombol.textContent = buka ? 'Sembunyikan' : 'Tampilkan';
        }
    </script>
</body>
</html>





