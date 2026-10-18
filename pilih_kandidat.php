<?php
// Halaman pemilihan kandidat bagi pemilih yang sudah login.
// Berisi pengecekan sesi dan jadwal, validasi suara, dan form konfirmasi sebelum data disimpan.
require_once 'config.php';
require_once 'function_jadwal.php';

// ===============================
// CEK LOGIN PEMILIH
// ===============================
if (($_SESSION['role'] ?? '') !== 'pemilih' || empty($_SESSION['id_pemilih'])) {
    header('Location: login_pemilih.php');
    exit;
}

$id_pemilih = (string) $_SESSION['id_pemilih'];
$nama_pemilih = htmlspecialchars($_SESSION['username_pemilih'] ?? '', ENT_QUOTES, 'UTF-8');

// Status jadwal voting (diatur admin di halaman Atur Jadwal Voting)
$jadwal_voting = ambilJadwalVoting($conn);
$status_voting = statusVoting($jadwal_voting);
$pesan_voting_tutup = [
    'belum_diatur' => 'Jadwal voting belum diatur oleh panitia.',
    'belum_dibuka' => 'Voting belum dibuka.',
    'ditutup'      => 'Voting sudah ditutup.',
    'nonaktif'     => 'Voting dimatikan oleh panitia.',
];

$pesan = '';
$error = '';

// ===============================
// PROSES VOTING
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Server memeriksa jadwal dan pilihan; tombol browser saja tidak cukup untuk menerima suara.
    $id_kandidat = (int) ($_POST['id_kandidat'] ?? 0);
    if ($status_voting !== 'berlangsung') {
        // Penjagaan di sisi server: suara di luar jadwal ditolak
        $error = $pesan_voting_tutup[$status_voting] ?? 'Voting sedang tidak dibuka.';
    } elseif ($id_kandidat < 1) {
        $error = 'Kandidat yang dipilih tidak valid.';
    } else {
        // Cegah pemilih mengirim suara kedua kali; indeks unik di database menjadi perlindungan tambahan.
        $stmt = mysqli_prepare($conn, 'SELECT id_vote FROM tb_vote WHERE id_pemilih = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 's', $id_pemilih);
        mysqli_stmt_execute($stmt);
        $sudah_memilih = mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0;
        mysqli_stmt_close($stmt);

        if ($sudah_memilih) {
            $error = 'Anda sudah menggunakan hak suara.';
        } else {
            // Simpan ID pemilih, kandidat, dan waktu sebagai satu catatan suara.
            $stmt = mysqli_prepare(
                $conn,
                'INSERT INTO tb_vote (id_pemilih, id_kandidat, tgl_vote)
                 SELECT ?, id_kandidat, NOW() FROM tb_kandidat WHERE id_kandidat = ?'
            );
            mysqli_stmt_bind_param($stmt, 'si', $id_pemilih, $id_kandidat);
            if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1) {
                $pesan = 'Suara Anda berhasil disimpan. Terima kasih sudah memilih.';
            } elseif (mysqli_errno($conn) === 1062) {
                $error = 'Anda sudah menggunakan hak suara.';
            } else {
                $error = 'Suara gagal disimpan. Silakan coba lagi.';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// ===============================
// CEK STATUS SUDAH MEMILIH
// ===============================
$stmt = mysqli_prepare($conn, 'SELECT id_vote FROM tb_vote WHERE id_pemilih = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 's', $id_pemilih);
mysqli_stmt_execute($stmt);
$sudah_memilih = mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0;
mysqli_stmt_close($stmt);

// ===============================
// AMBIL DATA KANDIDAT UNTUK DITAMPILKAN
// ===============================
$result = mysqli_query(
    $conn,
    'SELECT id_kandidat, nm_kandidat, no_urut, foto_kandidat, visi, misi
     FROM tb_kandidat
     ORDER BY no_urut ASC'
);
if (!$result) {
    exit('Data kandidat tidak dapat dimuat.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Kandidat | Pemilihan ketua osis</title>
    <link rel="stylesheet" href="tema.css?v=ux-biru-emas-20261014">
</head>
<body>
    <header class="topbar">
        <nav class="container" aria-label="Navigasi utama">
            <a class="brand" href="index.php">
                <span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg></span>
                PEMILIHAN KETUA OSIS
            </a>
            <a class="nav-out" href="index.php">Keluar</a>
        </nav>
    </header>

    <main class="container page">
        <h1 class="page-title">Pilih Kandidat Ketua OSIS</h1>
        <p class="page-sub">Selamat datang, <strong><?= $nama_pemilih ?></strong>. Pilih satu kandidat dengan teliti.</p>

        <?php if ($pesan !== ''): ?>
            <div class="alert alert-success"><?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <!-- Status jadwal voting + timer hitung mundur -->
        <?php include 'banner_jadwal.php'; ?>

        <?php if ($sudah_memilih): ?>
            <div class="alert">Anda sudah menggunakan hak suara. Pilihan tidak dapat diulang.</div>
        <?php endif; ?>

        <!-- Daftar kandidat: foto, nama, visi, misi, serta tombol pilih sesuai status. -->
        <section class="kandidat-grid">
            <?php if (mysqli_num_rows($result) === 0): ?>
                <div class="card">Belum ada kandidat yang tersedia.</div>
            <?php else: ?>
                <?php while ($kandidat = mysqli_fetch_assoc($result)): ?>
                    <?php
                    $id_k = (int) $kandidat['id_kandidat'];
                    $foto = trim($kandidat['foto_kandidat'] ?? '');
                    $nama_k = htmlspecialchars($kandidat['nm_kandidat'], ENT_QUOTES, 'UTF-8');
                    ?>
                    <article class="card kandidat-card">
                        <span class="badge" style="align-self:flex-start;">Nomor <?= (int) $kandidat['no_urut'] ?></span>

                        <?php if ($foto !== ''): ?>
                            <img class="kandidat-foto" src="<?= htmlspecialchars($foto, ENT_QUOTES, 'UTF-8') ?>" alt="Foto <?= $nama_k ?>"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="no-foto" style="display:none;">Foto tidak ditemukan</div>
                        <?php else: ?>
                            <div class="no-foto">Foto belum tersedia</div>
                        <?php endif; ?>

                        <h2><?= $nama_k ?></h2>

                        <h3>Visi &amp; Misi</h3>
                        <div class="teks-kandidat" id="visimisi-<?= $id_k ?>">
                            <p><strong>Visi:</strong> <?= nl2br(htmlspecialchars($kandidat['visi'] ?? '', ENT_QUOTES, 'UTF-8')) ?></p>
                            <p><strong>Misi:</strong> <?= nl2br(htmlspecialchars($kandidat['misi'] ?? '', ENT_QUOTES, 'UTF-8')) ?></p>
                        </div>
                        <button type="button" class="toggle-teks" onclick="toggleTeks('visimisi-<?= $id_k ?>', this)">Baca selengkapnya</button>

                        <div class="kandidat-aksi">
                            <?php if ($sudah_memilih): ?>
                                <div class="status-selesai">Voting selesai</div>
                            <?php elseif ($status_voting !== 'berlangsung'): ?>
                                <div class="status-selesai"><?= htmlspecialchars(labelStatusVoting($status_voting), ENT_QUOTES, 'UTF-8') ?></div>
                            <?php else: ?>
                                <form method="post" id="form-vote-<?= $id_k ?>">
                                    <input type="hidden" name="id_kandidat" value="<?= $id_k ?>">
                                    <button type="button" class="btn btn-block"
                                            data-form-id="form-vote-<?= $id_k ?>"
                                            data-nama="<?= $nama_k ?>"
                                            data-no-urut="<?= (int) $kandidat['no_urut'] ?>"
                                            data-foto="<?= htmlspecialchars($foto, ENT_QUOTES, 'UTF-8') ?>"
                                            onclick="bukaKonfirmasi(this)">
                                        Pilih Kandidat Ini
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endwhile; ?>
            <?php endif; ?>
        </section>

        <!-- Modal memastikan pemilih memeriksa kandidat sebelum suara dikirim. -->
        <div class="modal-overlay" id="modal-konfirmasi">
            <div class="modal-box">
                <h2>Konfirmasi Pilihan Anda</h2>
                <div class="modal-kandidat">
                    <img id="modal-foto" class="modal-foto" src="" alt="" style="display:none;"
                         onerror="this.style.display='none'; document.getElementById('modal-no-foto').style.display='flex';">
                    <div class="modal-no-foto" id="modal-no-foto" style="display:none;">Tanpa foto</div>
                    <div>
                        <span class="badge" id="modal-nomor"></span>
                        <h3 id="modal-nama"></h3>
                    </div>
                </div>
                <div class="alert alert-warning" style="margin-bottom:0;">
                    Pastikan kandidat di atas sudah sesuai pilihan Anda. Suara yang sudah dikonfirmasi <strong>tidak dapat diubah</strong>.
                </div>
                <div class="modal-aksi">
                    <button type="button" class="btn btn-outline" onclick="tutupKonfirmasi()">Batal</button>
                    <button type="button" class="btn" onclick="kirimSuara()">Ya, Kirim Suara</button>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Tombol "Baca selengkapnya" hanya muncul kalau teks visi/misi memang terpotong.
        // Tampilkan kontrol baca-lanjut hanya jika visi/misi kandidat memang terpotong.
        document.querySelectorAll('.teks-kandidat').forEach(function (teks) {
            if (teks.scrollHeight > teks.clientHeight + 1) {
                var tombol = teks.nextElementSibling;
                if (tombol && tombol.classList.contains('toggle-teks')) {
                    tombol.style.display = 'inline-block';
                }
            }
        });

        function toggleTeks(id, tombol) {
            var terbuka = document.getElementById(id).classList.toggle('expanded');
            tombol.textContent = terbuka ? 'Sembunyikan' : 'Baca selengkapnya';
        }

        // Simpan ID form yang dipilih agar tombol konfirmasi mengirim pilihan yang benar.
        var formTerpilih = null;

        function bukaKonfirmasi(tombol) {
            formTerpilih = tombol.getAttribute('data-form-id');
            document.getElementById('modal-nama').textContent = tombol.getAttribute('data-nama');
            document.getElementById('modal-nomor').textContent = 'Nomor ' + tombol.getAttribute('data-no-urut');

            var foto = tombol.getAttribute('data-foto');
            var elFoto = document.getElementById('modal-foto');
            var elNoFoto = document.getElementById('modal-no-foto');
            if (foto) {
                elFoto.src = foto;
                elFoto.style.display = 'block';
                elNoFoto.style.display = 'none';
            } else {
                elFoto.style.display = 'none';
                elNoFoto.style.display = 'flex';
            }
            document.getElementById('modal-konfirmasi').classList.add('tampil');
        }

        function tutupKonfirmasi() {
            document.getElementById('modal-konfirmasi').classList.remove('tampil');
            formTerpilih = null;
        }

        function kirimSuara() {
            if (formTerpilih) {
                document.getElementById(formTerpilih).submit();
            }
        }

        // Klik area gelap di luar kotak modal untuk membatalkan
        document.getElementById('modal-konfirmasi').addEventListener('click', function (e) {
            if (e.target === this) {
                tutupKonfirmasi();
            }
        });
    </script>
</body>
</html>





