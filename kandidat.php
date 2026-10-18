<?php
// File: kandidat.php
// Deskripsi: CRUD kandidat admin.
// Semua form POST dilengkapi token CSRF agar form tambah/edit/hapus kandidat tidak bisa dipalsukan.

require_once 'config.php';
require_once 'admin_guard.php';

$pesan = '';
$error = '';
$edit_kandidat = null;

$upload_dir = __DIR__ . '/uploads/kandidat/';
$upload_url = 'uploads/kandidat/';

// Pastikan folder foto tersedia sebelum proses unggah dijalankan.
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Semua perubahan data kandidat hanya diproses jika token form cocok.
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Sesi form tidak valid. Silakan coba lagi.';
    } else {
        $aksi = $_POST['aksi'] ?? '';
        $id_kandidat = (int) ($_POST['id_kandidat'] ?? 0);
        $nm_kandidat = trim($_POST['nm_kandidat'] ?? '');
        $no_urut = (int) ($_POST['no_urut'] ?? 0);
        $visi = trim($_POST['visi'] ?? '');
        $misi = trim($_POST['misi'] ?? '');
        $foto = '';

        if ($aksi === 'hapus') {
            // Ambil nama foto agar file lokalnya dapat dibersihkan setelah kandidat dihapus.
            $stmt = mysqli_prepare($conn, 'SELECT foto_kandidat FROM tb_kandidat WHERE id_kandidat = ?');
            mysqli_stmt_bind_param($stmt, 'i', $id_kandidat);
            mysqli_stmt_execute($stmt);
            $foto_lama = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['foto_kandidat'] ?? '';
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($conn, 'DELETE FROM tb_kandidat WHERE id_kandidat = ?');
            mysqli_stmt_bind_param($stmt, 'i', $id_kandidat);
            if (mysqli_stmt_execute($stmt)) {
                if ($foto_lama !== '' && is_file(__DIR__ . '/' . $foto_lama)) {
                    unlink(__DIR__ . '/' . $foto_lama);
                }
                $pesan = 'Kandidat berhasil dihapus.';
            } else {
                $error = 'Kandidat gagal dihapus.';
            }
            mysqli_stmt_close($stmt);
        } elseif ($aksi === 'simpan' || $aksi === 'ubah') {
            // Validasi data wajib dilakukan sebelum menyimpan kandidat.
            if ($nm_kandidat === '' || $no_urut < 1 || $visi === '' || $misi === '') {
                $error = 'Nama, nomor urut, visi, dan misi wajib diisi.';
            } elseif (!empty($_FILES['foto_kandidat']['name']) && $_FILES['foto_kandidat']['error'] === UPLOAD_ERR_OK) {
                // Hanya ekstensi gambar yang diizinkan; nama file dibuat acak agar tidak bentrok.
                $ekstensi = strtolower(pathinfo($_FILES['foto_kandidat']['name'], PATHINFO_EXTENSION));
                $ekstensi_valid = ['jpg', 'jpeg', 'png', 'webp'];
                if (!in_array($ekstensi, $ekstensi_valid, true)) {
                    $error = 'Foto harus berformat JPG, JPEG, PNG, atau WEBP.';
                } else {
                    $nama_file = bin2hex(random_bytes(8)) . '.' . $ekstensi;
                    if (move_uploaded_file($_FILES['foto_kandidat']['tmp_name'], $upload_dir . $nama_file)) {
                        $foto = $upload_url . $nama_file;
                    } else {
                        $error = 'Foto kandidat gagal diunggah.';
                    }
                }
            }

            if ($error === '') {
                if ($aksi === 'simpan') {
                    $stmt = mysqli_prepare($conn, 'INSERT INTO tb_kandidat (nm_kandidat, no_urut, foto_kandidat, visi, misi) VALUES (?, ?, ?, ?, ?)');
                    mysqli_stmt_bind_param($stmt, 'sisss', $nm_kandidat, $no_urut, $foto, $visi, $misi);
                } else {
                    if ($foto !== '') {
                        $stmt = mysqli_prepare($conn, 'UPDATE tb_kandidat SET nm_kandidat = ?, no_urut = ?, foto_kandidat = ?, visi = ?, misi = ? WHERE id_kandidat = ?');
                        mysqli_stmt_bind_param($stmt, 'sisssi', $nm_kandidat, $no_urut, $foto, $visi, $misi, $id_kandidat);
                    } else {
                        $stmt = mysqli_prepare($conn, 'UPDATE tb_kandidat SET nm_kandidat = ?, no_urut = ?, visi = ?, misi = ? WHERE id_kandidat = ?');
                        mysqli_stmt_bind_param($stmt, 'sissi', $nm_kandidat, $no_urut, $visi, $misi, $id_kandidat);
                    }
                }

                if (mysqli_stmt_execute($stmt)) {
                    $pesan = $aksi === 'simpan' ? 'Kandidat berhasil ditambahkan.' : 'Kandidat berhasil diubah.';
                } else {
                    $error = 'Data kandidat gagal disimpan.';
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

if (isset($_GET['edit'])) {
    // Isi form dengan data kandidat yang dipilih untuk diubah.
    $id_edit = (int) $_GET['edit'];
    $stmt = mysqli_prepare($conn, 'SELECT id_kandidat, nm_kandidat, no_urut, foto_kandidat, visi, misi FROM tb_kandidat WHERE id_kandidat = ?');
    mysqli_stmt_bind_param($stmt, 'i', $id_edit);
    mysqli_stmt_execute($stmt);
    $edit_kandidat = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

// Daftar kandidat diurutkan menurut nomor urut untuk tampilan admin.
$query = mysqli_query($conn, 'SELECT id_kandidat, nm_kandidat, no_urut, foto_kandidat, visi, misi FROM tb_kandidat ORDER BY no_urut ASC');
if (!$query) {
    exit('Data kandidat tidak dapat dimuat.');
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$halaman_aktif = 'kandidat';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kandidat | E-Voting OSIS</title>
    <link rel="stylesheet" href="tema.css?v=ux-biru-emas-20261014">
</head>
<body>
    <?php require_once 'header_admin.php'; ?>

    <main class="container page">
        <div class="page-head"><h1>Daftar Calon Ketua OSIS</h1><a class="btn" href="#form-kandidat">+ Tambah Kandidat Baru</a></div>

        <?php if (!empty($pesan)): ?><div class="alert alert-success"><?= e($pesan) ?></div><?php endif; ?>
        <?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

        <!-- Form yang sama dipakai untuk tambah dan edit; nilai aksi menentukan prosesnya. -->
        <form id="form-kandidat" class="card form form-grid" method="post" enctype="multipart/form-data" style="margin-bottom:20px;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <h2><?= $edit_kandidat ? 'Ubah Kandidat' : 'Tambah Kandidat' ?></h2>

            <input type="hidden" name="aksi" value="<?= $edit_kandidat ? 'ubah' : 'simpan' ?>">
            <?php if ($edit_kandidat): ?>
                <input type="hidden" name="id_kandidat" value="<?= (int) $edit_kandidat['id_kandidat'] ?>">
            <?php endif; ?>

            <div>
                <label for="nm_kandidat">Nama kandidat</label>
                <input id="nm_kandidat" name="nm_kandidat" maxlength="50" required value="<?= e($edit_kandidat['nm_kandidat'] ?? '') ?>">
            </div>

            <div>
                <label for="no_urut">Nomor urut</label>
                <input id="no_urut" name="no_urut" type="number" min="1" required value="<?= e($edit_kandidat['no_urut'] ?? '') ?>">
            </div>

            <div>
                <label for="foto_kandidat">Foto kandidat</label>
                <input id="foto_kandidat" name="foto_kandidat" type="file" accept=".jpg,.jpeg,.png,.webp" <?= $edit_kandidat ? '' : 'required' ?>>
            </div>

            <div>
                <label for="visi">Visi</label>
                <textarea id="visi" name="visi" required><?= e($edit_kandidat['visi'] ?? '') ?></textarea>
            </div>

            <div>
                <label for="misi">Misi</label>
                <textarea id="misi" name="misi" required><?= e($edit_kandidat['misi'] ?? '') ?></textarea>
            </div>

            <div class="actions">
                <button type="submit" class="btn"><?= $edit_kandidat ? 'Simpan Perubahan' : 'Tambah Kandidat' ?></button>
                <?php if ($edit_kandidat): ?><a href="kandidat.php" class="btn btn-outline">Batal</a><?php endif; ?>
            </div>
        </form>

        <div class="card" style="overflow-x:auto; padding:0;">
            <table class="kandidat-tabel">
                <thead>
                    <tr><th>No. Urut</th><th>Foto</th><th>Kandidat</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                    <?php
                    $ada_data = false;
                    while ($row = mysqli_fetch_assoc($query)):
                        $ada_data = true;
                        $id = (int) $row['id_kandidat'];
                    ?>
                    <tr>
                        <td class="no-urut-cell"><?= sprintf('%02d', (int) $row['no_urut']) ?></td>
                        <td>
                            <?php if (!empty($row['foto_kandidat']) && file_exists(__DIR__ . '/' . $row['foto_kandidat'])): ?>
                                <img src="<?= e($row['foto_kandidat']) ?>" class="foto-cell" alt="Foto <?= e($row['nm_kandidat']) ?>">
                            <?php else: ?>
                                <span class="no-foto-cell">Tanpa Foto</span>
                            <?php endif; ?>
                        </td>
                        <td class="nama-cell"><?= e($row['nm_kandidat']) ?></td>
                        <td>
                            <a href="kandidat.php?edit=<?= $id ?>#form-kandidat" class="btn btn-outline" style="padding:6px 12px; font-size:13px;">Edit</a>
                            <form method="post" style="display:inline" onsubmit="return confirm('Yakin hapus kandidat ini?')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="aksi" value="hapus">
                                <input type="hidden" name="id_kandidat" value="<?= $id ?>">
                                <button type="submit" class="btn btn-danger" style="padding:6px 12px; font-size:13px;">Hapus</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>

                    <?php if (!$ada_data): ?>
                    <tr><td colspan="4" class="empty-state">Belum ada kandidat yang terdaftar.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>





