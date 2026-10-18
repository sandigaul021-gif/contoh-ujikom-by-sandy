<?php
// Halaman hasil voting untuk admin: total suara, partisipasi, dan ranking kandidat.
// Semua angka di halaman ini adalah ringkasan agregat dari suara yang tercatat.
require_once 'admin_guard.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    exit('Koneksi database tidak tersedia.');
}

// Jumlah pemilih, suara masuk, sisa pemilih, dan persentase partisipasi.
$total_pemilih = (int) mysqli_fetch_row(mysqli_query($conn, 'SELECT COUNT(*) FROM tb_pemilih'))[0];
$total_suara = (int) mysqli_fetch_row(mysqli_query($conn, 'SELECT COUNT(*) FROM tb_vote'))[0];
$belum_memilih = max(0, $total_pemilih - $total_suara);
$partisipasi = $total_pemilih > 0 ? round(($total_suara / $total_pemilih) * 100, 1) : 0.0;

// Gabungkan kandidat dengan suara; LEFT JOIN menampilkan kandidat dengan nol suara juga.
$query = mysqli_query($conn, '
    SELECT k.id_kandidat, k.nm_kandidat, k.no_urut, k.foto_kandidat, COUNT(v.id_vote) AS jumlah_suara
    FROM tb_kandidat k
    LEFT JOIN tb_vote v ON v.id_kandidat = k.id_kandidat
    GROUP BY k.id_kandidat, k.nm_kandidat, k.no_urut
    ORDER BY jumlah_suara DESC, k.no_urut ASC
');

if (!$query) {
    exit('Data hasil voting tidak dapat dimuat.');
}

$kandidat_list = [];
while ($row = mysqli_fetch_assoc($query)) {
    $kandidat_list[] = $row;
}

// Ringkas jumlah yang sudah/belum memilih secara terpisah untuk siswa dan guru.
$statistik_kategori = [
    'siswa' => ['total' => 0, 'sudah' => 0],
    'guru'  => ['total' => 0, 'sudah' => 0],
];
$query_kategori = mysqli_query($conn, '
    SELECT p.kategori,
           COUNT(DISTINCT p.id_pemilih) AS total,
           COUNT(DISTINCT v.id_pemilih) AS sudah_memilih
    FROM tb_pemilih p
    LEFT JOIN tb_vote v ON v.id_pemilih = p.id_pemilih
    GROUP BY p.kategori
');
if ($query_kategori) {
    while ($baris = mysqli_fetch_assoc($query_kategori)) {
        $kat = $baris['kategori'] === 'guru' ? 'guru' : 'siswa';
        $statistik_kategori[$kat]['total'] = (int) $baris['total'];
        $statistik_kategori[$kat]['sudah'] = (int) $baris['sudah_memilih'];
    }
}

$halaman_aktif = 'hasil';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Pemilihan | PEMILIHAN KETUA OSIS</title>
    <link rel="stylesheet" href="tema.css?v=ux-biru-emas-20261014">
</head>
<body>
    <?php require_once 'header_admin.php'; ?>

    <main class="container page">
        <div class="page-head"><h1>Hasil Pemilihan</h1></div>

        <!-- Ringkasan partisipasi -->
        <section class="stat-grid" aria-label="Ringkasan partisipasi">
            <div class="card stat"><strong><?= $total_pemilih ?></strong><span>Total Pemilih</span></div>
            <div class="card stat"><strong><?= $total_suara ?></strong><span>Sudah Memilih</span></div>
            <div class="card stat"><strong><?= $belum_memilih ?></strong><span>Belum Memilih</span></div>
            <div class="card stat"><strong><?= number_format($partisipasi, 1) ?>%</strong><span>Partisipasi</span></div>
        </section>

        <!-- Partisipasi per kategori -->
        <section class="kat-grid" aria-label="Partisipasi per kategori">
            <?php foreach ($statistik_kategori as $kat => $data):
                $persen_kat = $data['total'] > 0 ? round(($data['sudah'] / $data['total']) * 100, 1) : 0.0;
            ?>
                <div class="card">
                    <div class="hasil-head">
                        <span class="badge"><?= ucfirst($kat) ?></span>
                        <span class="muted"><?= $data['sudah'] ?>/<?= $data['total'] ?> memilih (<?= number_format($persen_kat, 1) ?>%)</span>
                    </div>
                    <div class="bar"><span style="width: <?= $persen_kat ?>%;"></span></div>
                </div>
            <?php endforeach; ?>
        </section>

        <!-- Hasil suara tiap kandidat -->
        <?php if (empty($kandidat_list)): ?>
            <div class="card muted" style="text-align:center;">Belum ada kandidat yang terdaftar.</div>
        <?php else: ?>
            <?php foreach ($kandidat_list as $kandidat):
                $jumlah = (int) $kandidat['jumlah_suara'];
                $persen = $total_suara > 0 ? round(($jumlah / $total_suara) * 100, 1) : 0.0;
            ?>
                <div class="card hasil-item">
                    <div class="hasil-head">
                        <div class="hasil-title">
                            <?php if (!empty($kandidat['foto_kandidat']) && file_exists(__DIR__ . '/' . $kandidat['foto_kandidat'])): ?>
                                <img src="<?= htmlspecialchars($kandidat['foto_kandidat'], ENT_QUOTES, 'UTF-8') ?>" class="hasil-foto" alt="Foto <?= htmlspecialchars($kandidat['nm_kandidat'], ENT_QUOTES, 'UTF-8') ?>">
                            <?php else: ?>
                                <span class="hasil-foto-kosong"></span>
                            <?php endif; ?>
                            <h3><span class="badge">No. <?= sprintf('%02d', (int) $kandidat['no_urut']) ?></span> <?= htmlspecialchars($kandidat['nm_kandidat'], ENT_QUOTES, 'UTF-8') ?></h3>
                        </div>
                        <span class="muted"><?= $jumlah ?> suara (<?= number_format($persen, 1) ?>%)</span>
                    </div>
                    <div class="bar"><span style="width: <?= $persen ?>%;"></span></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
</body>
</html>





