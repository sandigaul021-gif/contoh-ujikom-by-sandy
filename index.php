<?php
// Halaman publik: menampilkan ringkasan suara sementara, panduan, dan tombol masuk.
require_once 'config.php';

// Hitung suara masuk untuk menghitung persentase perolehan tiap kandidat.
$total_suara_pemilihan = (int) mysqli_fetch_row(mysqli_query($conn, 'SELECT COUNT(*) FROM tb_vote'))[0];

// LEFT JOIN memastikan kandidat tetap tampil walaupun belum menerima suara.
$query_hasil_pemilihan = mysqli_query($conn, '
    SELECT k.id_kandidat, k.nm_kandidat, k.no_urut, k.foto_kandidat, COUNT(v.id_vote) AS jumlah_suara
    FROM tb_kandidat k
    LEFT JOIN tb_vote v ON v.id_kandidat = k.id_kandidat
    GROUP BY k.id_kandidat, k.nm_kandidat, k.no_urut
    ORDER BY jumlah_suara DESC, k.no_urut ASC
');

$daftar_hasil_pemilihan = [];
if ($query_hasil_pemilihan) {
    while ($baris_hasil = mysqli_fetch_assoc($query_hasil_pemilihan)) {
        $daftar_hasil_pemilihan[] = $baris_hasil;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistem pemilihan Ketua OSIS secara elektronik.">
    <title>PEMILIHAN KETUA OSIS | Pemilihan Ketua OSIS</title>
    <link rel="stylesheet" href="tema.css?v=ux-biru-emas-20261014">
</head>
<body>
    <!-- Header berisi identitas aplikasi dan tautan menuju panduan. -->
    <header class="topbar">
        <nav class="container" aria-label="Navigasi utama">
            <a class="brand" href="index.php">
                <span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg></span>
                PEMILIHAN KETUA OSIS
            </a>
            
         <a
          class="btn nav-link" style="background-color: #808080; border-color: #808080; color: #ffffff;" href="#panduan" role="button">Panduan Pemilihan
        </a>
    </header>

    <section class="hero">
        <div class="container">
            <h1>Pemilihan Ketua OSIS</h1>
            <p>Gunakan hak suara Anda secara langsung, umum, bebas, dan rahasia melalui sistem pemilihan elektronik.</p>
            <a class="btn" href="login_pemilih.php">Mulai Memilih</a>
        </div>
    </section>

    <div class="container">
        <section class="info-row" aria-label="Informasi pemilihan">
            <div class="card info-item"><strong>1 Pemilih, 1 Suara</strong><span>Setiap akun hanya dapat memilih satu kali.</span></div>
            <div class="card info-item"><strong>Rahasia dan Aman</strong><span>Pilihan Anda dijaga kerahasiaannya.</span></div>
            <div class="card info-item"><strong>Pilih dengan Bijak</strong><span>Kenali calon sebelum menentukan pilihan.</span></div>
        </section>

        <!-- Hasil real-time berupa agregat jumlah suara, bukan daftar pilihan per pemilih. -->
        <section class="section" aria-label="Hasil suara sementara">
            <div class="section-title">
                <h2>Hasil Suara Sementara</h2>
                <p>Diperbarui otomatis saat suara masuk.</p>
            </div>

            <?php if (empty($daftar_hasil_pemilihan)): ?>
                <div class="card muted" style="text-align:center;">Belum ada kandidat yang terdaftar.</div>
            <?php else: ?>
                <?php foreach ($daftar_hasil_pemilihan as $kandidat_pemilihan):
                    $jumlah_pemilihan = (int) $kandidat_pemilihan['jumlah_suara'];
                    $persen_pemilihan = $total_suara_pemilihan > 0 ? round(($jumlah_pemilihan / $total_suara_pemilihan) * 100, 1) : 0.0;
                ?>
                    <div class="card hasil-item">
                        <div class="hasil-head">
                            <div class="hasil-title">
                                <?php if (!empty($kandidat_pemilihan['foto_kandidat']) && file_exists(__DIR__ . '/' . $kandidat_pemilihan['foto_kandidat'])): ?>
                                    <img src="<?= htmlspecialchars($kandidat_pemilihan['foto_kandidat'], ENT_QUOTES, 'UTF-8') ?>" class="hasil-foto" alt="Foto <?= htmlspecialchars($kandidat_pemilihan['nm_kandidat'], ENT_QUOTES, 'UTF-8') ?>">
                                <?php else: ?>
                                    <span class="hasil-foto-kosong"></span>
                                <?php endif; ?>
                                <h3><span class="badge">No. <?= sprintf('%02d', (int) $kandidat_pemilihan['no_urut']) ?></span> <?= htmlspecialchars($kandidat_pemilihan['nm_kandidat'], ENT_QUOTES, 'UTF-8') ?></h3>
                            </div>
                            <span class="muted"><?= $jumlah_pemilihan ?> suara (<?= number_format($persen_pemilihan, 1) ?>%)</span>
                        </div>
                        <div class="bar"><span style="width: <?= $persen_pemilihan ?>%;"></span></div>
                    </div>
                <?php endforeach; ?>
                <p class="muted" style="text-align:center;">Total <?= $total_suara_pemilihan ?> suara sudah masuk sejauh ini.</p>
            <?php endif; ?>
        </section>

        <!-- Panduan tiga langkah membantu pemilih memahami alur sebelum login. -->
        <section class="section" id="panduan">
            <div class="section-title">
                <h2>Cara Menggunakan E-Voting</h2>
                <p>Ikuti tiga langkah berikut untuk menggunakan hak suara Anda.</p>
            </div>
            <div class="steps">
                <div class="card step"><div class="no">1</div><h3>Masuk ke Akun</h3><p>Gunakan ID pemilih dan password yang diberikan oleh panitia.</p></div>
                <div class="card step"><div class="no">2</div><h3>Pilih Kandidat</h3><p>Baca visi dan misi setiap kandidat, lalu tentukan pilihan Anda.</p></div>
                <div class="card step"><div class="no">3</div><h3>Konfirmasi Suara</h3><p>Periksa pilihan Anda dan konfirmasikan. Suara yang sudah masuk tidak dapat diubah.</p></div>
            </div>
            <div class="alert alert-warning" style="margin-top:24px;"><strong>Perhatian:</strong> Jangan memberikan ID pemilih atau password kepada siapa pun. Hubungi panitia jika Anda mengalami kendala saat masuk atau memilih.</div>
        </section>
    </div>

    <footer class="footer">
        <p>© <?= date('Y') ?> E-Voting OSIS</p>
    </footer>
</body>
</html>






