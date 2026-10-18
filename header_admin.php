<?php
// Header bersama halaman admin, berisi menu navigasi.
// Sebelum include, isi $halaman_aktif (dashboard, kandidat, pemilih, jadwal, atau hasil).
$halaman_aktif = $halaman_aktif ?? '';
$menu_admin = [
    'dashboard' => ['dashboard_admin.php', 'Dashboard'],
    'kandidat'  => ['kandidat.php', 'Kandidat'],
    'pemilih'   => ['pemilih.php', 'Pemilih'],
    'jadwal'    => ['jadwal.php', 'Jadwal'],
    'hasil'     => ['hasil_voting.php', 'Hasil'],
];
?>
<!-- Navigasi bersama; menu aktif ditentukan halaman yang meng-include file ini. -->
<header class="topbar">
    <nav class="container" aria-label="Navigasi admin">
        <a class="brand" href="dashboard_admin.php">
            <span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg></span>
            PEMILIHAN KETUA OSIS <small>Admin</small>
        </a>
        <div class="nav-menu">
            <?php foreach ($menu_admin as $kunci => $item): ?>
                <a class="nav-link<?= $kunci === $halaman_aktif ? ' aktif' : '' ?>" href="<?= $item[0] ?>"><?= $item[1] ?></a>
            <?php endforeach; ?>
            <a class="nav-out" href="logout.php">Logout</a>
        </div>
    </nav>
</header>

