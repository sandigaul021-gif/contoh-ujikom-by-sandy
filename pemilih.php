<?php
// File: pemilih.php
// Deskripsi: Manajemen data pemilih admin.
// Form import, tambah, edit, dan hapus diberi token CSRF untuk mencegah request dari situs asing.

require_once 'admin_guard.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    exit('Koneksi database tidak tersedia.');
}

function buatPasswordAcak(int $panjang = 10): string
{
    // Gunakan sumber acak kriptografis untuk membentuk password awal.
    $karakter = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $hasil = '';
    for ($i = 0; $i < $panjang; $i++) {
        $hasil .= $karakter[random_int(0, strlen($karakter) - 1)];
    }
    return $hasil;
}

function buatIdGuruOtomatis(mysqli $conn): string
{
    // Bentuk kode guru acak dan ulangi jika kode tersebut sudah terdaftar.
    $karakter = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $kode = 'GR';
        for ($i = 0; $i < 4; $i++) {
            $kode .= $karakter[random_int(0, strlen($karakter) - 1)];
        }
        $stmt = mysqli_prepare($conn, 'SELECT 1 FROM tb_pemilih WHERE id_pemilih = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 's', $kode);
        mysqli_stmt_execute($stmt);
        $sudah_ada = mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0;
        mysqli_stmt_close($stmt);
    } while ($sudah_ada);

    return $kode;
}

$pesan = '';
$error = '';
$edit_pemilih = null;
$password_form = buatPasswordAcak(10);
$id_guru_form = buatIdGuruOtomatis($conn);
$hasil_impor = [];
$gagal_impor = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Semua aksi perubahan data memerlukan token CSRF yang valid.
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Sesi form tidak valid. Silakan coba lagi.';
    } else {
        $aksi = $_POST['aksi'] ?? '';
        $id_pemilih = trim($_POST['id_pemilih'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_form = $password;
        $kategori = ($_POST['kategori'] ?? 'siswa') === 'guru' ? 'guru' : 'siswa';

        if ($aksi === 'hapus') {
            // Foreign key menolak penghapusan bila pemilih sudah memiliki suara.
            $stmt = mysqli_prepare($conn, 'DELETE FROM tb_pemilih WHERE id_pemilih = ?');
            mysqli_stmt_bind_param($stmt, 's', $id_pemilih);
            if (mysqli_stmt_execute($stmt)) {
                $pesan = 'Pemilih berhasil dihapus.';
            } else {
                $error = 'Pemilih tidak dapat dihapus karena sudah melakukan pemilihan.';
            }
            mysqli_stmt_close($stmt);
        } elseif ($aksi === 'hapus_massal' || $aksi === 'hapus_semua') {
            // Pemilih terpilih dihapus satu per satu; data suara terkait tetap dipertahankan.
            if ($aksi === 'hapus_semua') {
                $terpilih = [];
                $hasil_id = mysqli_query($conn, 'SELECT id_pemilih FROM tb_pemilih');
                while ($baris = mysqli_fetch_assoc($hasil_id)) {
                    $terpilih[] = $baris['id_pemilih'];
                }
            } else {
                $terpilih = $_POST['pilih'] ?? [];
                if (!is_array($terpilih)) {
                    $terpilih = [];
                }
            }

            if (empty($terpilih)) {
                $error = 'Tidak ada pemilih yang dipilih untuk dihapus.';
            } else {
                $stmt = mysqli_prepare($conn, 'DELETE FROM tb_pemilih WHERE id_pemilih = ?');
                $jumlah_terhapus = 0;
                $jumlah_gagal = 0;

                foreach ($terpilih as $id_hapus) {
                    $id_hapus = trim((string) $id_hapus);
                    if ($id_hapus === '') {
                        continue;
                    }
                    mysqli_stmt_bind_param($stmt, 's', $id_hapus);
                    if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1) {
                        $jumlah_terhapus++;
                    } else {
                        $jumlah_gagal++;
                    }
                }
                mysqli_stmt_close($stmt);

                if ($jumlah_terhapus > 0) {
                    $pesan = $jumlah_terhapus . ' pemilih berhasil dihapus.';
                    if ($jumlah_gagal > 0) {
                        $pesan .= ' ' . $jumlah_gagal . ' pemilih dilewati karena sudah menggunakan hak suaranya.';
                    }
                } else {
                    $error = 'Tidak ada pemilih yang dihapus. ' . ($jumlah_gagal > 0 ? 'Semua pemilih yang dipilih sudah menggunakan hak suaranya.' : '');
                }
            }
        } elseif ($aksi === 'simpan' || $aksi === 'ubah') {
            // Validasi format ID dan password sebelum menjalankan INSERT atau UPDATE.
            $password_valid = $password === '' || preg_match('/^[A-Za-z0-9]{10}$/', $password);

            if ($username === '' || ($aksi === 'simpan' && ($id_pemilih === '' || $password === ''))) {
                $error = 'ID pemilih, username, dan password wajib diisi saat menambah pemilih.';
            } elseif ($aksi === 'simpan' && !preg_match('/^[A-Za-z0-9]{1,20}$/', $id_pemilih)) {
                $error = 'ID pemilih hanya boleh berisi huruf dan angka, maksimal 20 karakter.';
            } elseif (!$password_valid) {
                $error = 'Password harus persis 10 karakter huruf/angka (sesuai aturan login).';
            } elseif ($aksi === 'simpan') {
                $stmt = mysqli_prepare($conn, 'INSERT INTO tb_pemilih (id_pemilih, username, password, kategori) VALUES (?, ?, ?, ?)');
                mysqli_stmt_bind_param($stmt, 'ssss', $id_pemilih, $username, $password, $kategori);
                if (mysqli_stmt_execute($stmt)) {
                    $pesan = 'Pemilih berhasil ditambahkan.';
                } elseif (mysqli_errno($conn) === 1062) {
                    $error = 'ID pemilih tersebut sudah digunakan.';
                } else {
                    $error = 'Pemilih gagal ditambahkan.';
                }
                mysqli_stmt_close($stmt);
            } else {
                if ($password !== '') {
                    $stmt = mysqli_prepare($conn, 'UPDATE tb_pemilih SET username = ?, password = ?, kategori = ? WHERE id_pemilih = ?');
                    mysqli_stmt_bind_param($stmt, 'ssss', $username, $password, $kategori, $id_pemilih);
                } else {
                    $stmt = mysqli_prepare($conn, 'UPDATE tb_pemilih SET username = ?, kategori = ? WHERE id_pemilih = ?');
                    mysqli_stmt_bind_param($stmt, 'sss', $username, $kategori, $id_pemilih);
                }
                $pesan = mysqli_stmt_execute($stmt) ? 'Pemilih berhasil diubah.' : 'Pemilih gagal diubah.';
                if ($pesan === 'Pemilih gagal diubah.') {
                    $error = $pesan;
                    $pesan = '';
                }
                mysqli_stmt_close($stmt);
            }
        } elseif ($aksi === 'impor') {
            // CSV dibaca baris demi baris; tiap baris divalidasi dan diberi password acak.
            if (empty($_FILES['file_csv']['name']) || $_FILES['file_csv']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Pilih file CSV terlebih dahulu.';
            } elseif (strtolower(pathinfo($_FILES['file_csv']['name'], PATHINFO_EXTENSION)) !== 'csv') {
                $error = 'File harus berformat .csv (bisa disimpan dari Excel: Save As > CSV).';
            } else {
                $handle = fopen($_FILES['file_csv']['tmp_name'], 'r');
                if ($handle === false) {
                    $error = 'File CSV tidak dapat dibaca.';
                } else {
                    $baris_ke = 0;
                    $stmt = mysqli_prepare($conn, 'INSERT INTO tb_pemilih (id_pemilih, username, password, kategori) VALUES (?, ?, ?, ?)');

                    while (($kolom = fgetcsv($handle)) !== false) {
                        $baris_ke++;

                        if ($baris_ke === 1 && isset($kolom[0]) && stripos($kolom[0], 'id_pemilih') !== false) {
                            continue;
                        }

                        $csv_id = trim($kolom[0] ?? '');
                        $csv_username = trim($kolom[1] ?? '');
                        $csv_kategori = strtolower(trim($kolom[2] ?? ''));

                        if ($csv_id === '' && $csv_username === '') {
                            continue;
                        }

                        if (!in_array($csv_kategori, ['siswa', 'guru'], true)) {
                            $gagal_impor[] = ['baris' => $baris_ke, 'alasan' => $csv_kategori === '' ? 'Kategori kosong (isi "siswa" atau "guru")' : 'Kategori "' . $csv_kategori . '" tidak valid (harus "siswa" atau "guru")'];
                            continue;
                        }

                        if ($csv_id === '' && $csv_kategori === 'guru') {
                            $csv_id = buatIdGuruOtomatis($conn);
                        } elseif (!preg_match('/^[A-Za-z0-9]{1,20}$/', $csv_id)) {
                            $gagal_impor[] = ['baris' => $baris_ke, 'alasan' => 'ID pemilih kosong/tidak valid (hanya boleh huruf & angka)'];
                            continue;
                        }

                        if ($csv_username === '') {
                            $gagal_impor[] = ['baris' => $baris_ke, 'alasan' => 'Nama/username kosong'];
                            continue;
                        }

                        $csv_password = buatPasswordAcak(10);

                        mysqli_stmt_bind_param($stmt, 'ssss', $csv_id, $csv_username, $csv_password, $csv_kategori);

                        if (mysqli_stmt_execute($stmt)) {
                            $hasil_impor[] = ['id_pemilih' => $csv_id, 'username' => $csv_username, 'password' => $csv_password, 'kategori' => $csv_kategori];
                        } elseif (mysqli_errno($conn) === 1062) {
                            $gagal_impor[] = ['baris' => $baris_ke, 'alasan' => "ID \"$csv_id\" sudah terdaftar"];
                        } else {
                            $gagal_impor[] = ['baris' => $baris_ke, 'alasan' => 'Gagal disimpan ke database'];
                        }
                    }

                    mysqli_stmt_close($stmt);
                    fclose($handle);

                    if (count($hasil_impor) > 0) {
                        $pesan = count($hasil_impor) . ' pemilih berhasil diimpor.';
                        if (count($gagal_impor) > 0) {
                            $pesan .= ' (' . count($gagal_impor) . ' baris dilewati, lihat rincian di bawah.)';
                        }
                    } elseif (count($gagal_impor) > 0) {
                        $error = 'Tidak ada data yang berhasil diimpor. Periksa format file CSV kamu.';
                    } else {
                        $error = 'File CSV kosong atau tidak berisi data yang valid.';
                    }
                }
            }
        }
    }
}

if (isset($_GET['edit'])) {
    // Ambil data terpilih untuk mengisi ulang form dalam mode edit.
    $id_edit = trim($_GET['edit']);
    $stmt = mysqli_prepare($conn, 'SELECT id_pemilih, username, kategori FROM tb_pemilih WHERE id_pemilih = ?');
    mysqli_stmt_bind_param($stmt, 's', $id_edit);
    mysqli_stmt_execute($stmt);
    $edit_pemilih = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

$kata_cari = trim($_GET['cari'] ?? '');
$filter_kategori = ($_GET['kategori'] ?? '') === 'guru' ? 'guru' : (($_GET['kategori'] ?? '') === 'siswa' ? 'siswa' : '');
$filter_status = ($_GET['status'] ?? '') === 'sudah' ? 'sudah' : (($_GET['status'] ?? '') === 'belum' ? 'belum' : '');

$kondisi = [];
$tipe_parameter = '';
$nilai_parameter = [];

if ($kata_cari !== '') {
    // Cocokkan sebagian kata dengan ID maupun nama pemilih.
    $kondisi[] = '(p.id_pemilih LIKE ? OR p.username LIKE ?)';
    $kata_cari_like = '%' . $kata_cari . '%';
    $nilai_parameter[] = $kata_cari_like;
    $nilai_parameter[] = $kata_cari_like;
    $tipe_parameter .= 'ss';
}

if ($filter_kategori !== '') {
    $kondisi[] = 'p.kategori = ?';
    $nilai_parameter[] = $filter_kategori;
    $tipe_parameter .= 's';
}

$cek_sudah_memilih = 'EXISTS (SELECT 1 FROM tb_vote v WHERE v.id_pemilih = p.id_pemilih)';

if ($filter_status === 'sudah') {
    // EXISTS memeriksa ada tidaknya suara tanpa mengambil seluruh tabel suara.
    $kondisi[] = $cek_sudah_memilih;
} elseif ($filter_status === 'belum') {
    $kondisi[] = 'NOT ' . $cek_sudah_memilih;
}

$klausa_where = '';
if (!empty($kondisi)) {
    $klausa_where = ' WHERE ' . implode(' AND ', $kondisi);
}

// Batasi jumlah baris yang dirender agar halaman tetap ringan saat data bertambah.
// Query hitung terpisah menjaga jumlah hasil dan jumlah halaman tetap akurat.
$jumlah_per_halaman = 25;
$halaman = max(1, (int) ($_GET['halaman'] ?? 1));
$sql_jumlah = 'SELECT COUNT(*) FROM tb_pemilih p' . $klausa_where;
$sql_dasar = 'SELECT p.id_pemilih, p.username, p.kategori, ' . $cek_sudah_memilih . ' AS sudah_memilih FROM tb_pemilih p'
    . $klausa_where . ' ORDER BY p.username ASC LIMIT ? OFFSET ?';

function jalankanQueryPemilih(mysqli $conn, string $sql, string $types, array $params)
{
    // Helper untuk prepared statement dengan parameter filter dan pagination.
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }

    if ($types !== '') {
        $referensi = [$types];
        foreach ($params as $kunci => $nilai) {
            $referensi[] = &$params[$kunci];
        }
        call_user_func_array([$stmt, 'bind_param'], $referensi);
    }

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return false;
    }

    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

if (!empty($nilai_parameter)) {
    $hasil_jumlah = jalankanQueryPemilih($conn, $sql_jumlah, $tipe_parameter, $nilai_parameter);
} else {
    $hasil_jumlah = mysqli_query($conn, $sql_jumlah);
}

if (!$hasil_jumlah) {
    exit('Data pemilih tidak dapat dimuat.');
}

$baris_jumlah = mysqli_fetch_row($hasil_jumlah);
$jumlah_hasil_total = (int) ($baris_jumlah[0] ?? 0);
$jumlah_halaman = max(1, (int) ceil($jumlah_hasil_total / $jumlah_per_halaman));
if ($halaman > $jumlah_halaman) {
    $halaman = $jumlah_halaman;
}

$parameter_halaman = array_merge($nilai_parameter, [$jumlah_per_halaman, ($halaman - 1) * $jumlah_per_halaman]);
$result = jalankanQueryPemilih($conn, $sql_dasar, $tipe_parameter . 'ii', $parameter_halaman);
if (!$result) {
    exit('Data pemilih tidak dapat dimuat.');
}

$ada_filter = ($kata_cari !== '' || $filter_kategori !== '' || $filter_status !== '');
$halaman_aktif = 'pemilih';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pemilih | E-Voting OSIS</title>
    <link rel="stylesheet" href="tema.css?v=ux-biru-emas-20261014">
</head>
<body>
    <?php include 'header_admin.php'; ?>

    <main class="container page">
        <div class="page-head"><h1>Kelola Pemilih</h1></div>

        <?php if ($pesan !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

        <!-- Kredensial hasil impor hanya ditampilkan pada ringkasan hasil impor ini. -->
        <?php if (!empty($hasil_impor)): ?>
            <div class="card" style="margin-bottom:16px;">
                <div class="page-head" style="margin-bottom:14px;">
                    <h2 style="margin:0; font-size:18px;">Hasil Impor (<?= count($hasil_impor) ?> pemilih baru)</h2>
                    <div style="display:flex; gap:8px;">
                        <button type="button" class="btn btn-outline" onclick="window.print()">Cetak / Simpan PDF</button>
                    </div>
                </div>
                <p class="muted">Simpan atau cetak daftar ini sekarang untuk dibagikan ke siswa — password ini tidak ditampilkan lagi setelah halaman ini ditutup.</p>
                <table class="hasil-tabel">
                    <thead><tr><th>ID Pemilih</th><th>Kategori</th><th>Nama</th><th>Password</th></tr></thead>
                    <tbody>
                        <?php foreach ($hasil_impor as $baris): ?>
                            <tr>
                                <td><?= htmlspecialchars($baris['id_pemilih'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="badge"><?= ucfirst($baris['kategori']) ?></span></td>
                                <td><?= htmlspecialchars($baris['username'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="pw"><?= htmlspecialchars($baris['password'], ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if (!empty($gagal_impor)): ?>
                    <div class="gagal-impor">
                        <strong><?= count($gagal_impor) ?> baris dilewati:</strong>
                        <ul>
                            <?php foreach ($gagal_impor as $g): ?>
                                <li>Baris <?= (int) $g['baris'] ?>: <?= htmlspecialchars($g['alasan'], ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Form yang sama menangani tambah dan edit sesuai nilai $edit_pemilih. -->
        <form class="card form" method="post" style="margin-bottom:16px;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <h2><?= $edit_pemilih ? 'Ubah Pemilih' : 'Tambah Pemilih' ?></h2>
            <input type="hidden" name="aksi" value="<?= $edit_pemilih ? 'ubah' : 'simpan' ?>">
            <div>
                <label for="id_pemilih">ID Pemilih (NIS / kode guru)</label>
                <input id="id_pemilih" name="id_pemilih" maxlength="10" pattern="[A-Za-z0-9]{1,10}"
                    value="<?= htmlspecialchars($edit_pemilih['id_pemilih'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    <?= $edit_pemilih ? 'readonly' : 'required' ?>>
                <?php if ($edit_pemilih): ?>
                    <p class="muted" style="margin-top:6px;">ID pemilih tidak dapat diubah karena dipakai untuk login.</p>
                <?php else: ?>
                    <p style="margin-top:8px;">
                        <button class="btn btn-outline" type="button"
                            onclick="document.getElementById('id_pemilih').value = '<?= $id_guru_form ?>'; document.getElementById('kategori').value = 'guru';">
                            Buat ID Guru Otomatis
                        </button>
                    </p>
                <?php endif; ?>
            </div>
            <div><label for="username">Username</label><input id="username" name="username" maxlength="30" required value="<?= htmlspecialchars($edit_pemilih['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
            <div>
                <label for="kategori">Kategori</label>
                <select id="kategori" name="kategori">
                    <option value="siswa" <?= ($edit_pemilih['kategori'] ?? 'siswa') === 'siswa' ? 'selected' : '' ?>>Siswa</option>
                    <option value="guru" <?= ($edit_pemilih['kategori'] ?? '') === 'guru' ? 'selected' : '' ?>>Guru</option>
                </select>
            </div>
            <div>
                <label for="password">Password <?= $edit_pemilih ? '(kosongkan jika tidak diubah)' : '' ?></label>
                <input id="password" name="password" type="text" minlength="10" maxlength="10" pattern="[A-Za-z0-9]{10}"
                    value="<?= $edit_pemilih ? '' : htmlspecialchars($password_form, ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="masukan password"
                    title="Persis 10 karakter huruf/angka." <?= $edit_pemilih ? '' : 'required' ?>>
                <?php if (!$edit_pemilih): ?>
                    <p style="margin-top:8px;">
                        <button class="btn btn-outline" type="button" onclick="window.location.href='pemilih.php?buat_password=1'">Buat Password Baru</button>
                    </p>
                <?php endif; ?>
            </div>
            <div class="actions"><button class="btn" type="submit"><?= $edit_pemilih ? 'Simpan Perubahan' : 'Tambah Pemilih' ?></button><?php if ($edit_pemilih): ?><a class="btn btn-outline" href="pemilih.php">Batal</a><?php endif; ?></div>
        </form>

        <!-- CSV menyediakan ID, nama, dan kategori; password dibuat oleh aplikasi. -->
        <form class="card form impor-box" method="post" enctype="multipart/form-data" style="margin-bottom:16px;">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="aksi" value="impor">
            <div>
                <label for="file_csv">Pilih file pemilih (berbentuk CSV)</label>
                <input id="file_csv" name="file_csv" type="file" accept=".csv" required>
            </div>
            <div class="actions">
                <button class="btn" type="submit">Impor Sekarang</button>
               <a class="btn btn-outline" href="unduh_template_pemilih.php">Unduh Template CSV</a>
            </div>
        </form>

        <!-- GET mempertahankan kriteria pencarian saat admin berpindah halaman. -->
        <form class="toolbar" method="get">
            <input type="text" name="cari" value="<?= htmlspecialchars($kata_cari, ENT_QUOTES, 'UTF-8') ?>" placeholder="Cari berdasarkan ID pemilih atau nama...">
            <select name="kategori" onchange="this.form.submit()">
                <option value="">Semua Kategori</option>
                <option value="siswa" <?= $filter_kategori === 'siswa' ? 'selected' : '' ?>>Siswa</option>
                <option value="guru" <?= $filter_kategori === 'guru' ? 'selected' : '' ?>>Guru</option>
            </select>
            <select name="status" onchange="this.form.submit()">
                <option value="">Semua Status</option>
                <option value="sudah" <?= $filter_status === 'sudah' ? 'selected' : '' ?>>Sudah Memilih</option>
                <option value="belum" <?= $filter_status === 'belum' ? 'selected' : '' ?>>Belum Memilih</option>
            </select>
            <button class="btn" type="submit">Cari</button>
            <?php if ($ada_filter): ?><a class="btn btn-outline" href="pemilih.php">Reset</a><?php endif; ?>
        </form>

        <?php if ($ada_filter): ?>
            <p class="hint-cari">Ditemukan <?= $jumlah_hasil_total ?> hasil<?= $kata_cari !== '' ? ' untuk "' . htmlspecialchars($kata_cari, ENT_QUOTES, 'UTF-8') . '"' : '' ?><?= $filter_kategori !== '' ? ' (' . ucfirst($filter_kategori) . ')' : '' ?><?= $filter_status !== '' ? ' (' . ($filter_status === 'sudah' ? 'Sudah Memilih' : 'Belum Memilih') . ')' : '' ?>. Halaman <?= $halaman ?> dari <?= $jumlah_halaman ?>.</p>
        <?php endif; ?>

        <?php if (mysqli_num_rows($result) === 0): ?>
            <div class="card empty-state"><?= $ada_filter ? 'Tidak ada pemilih yang cocok dengan pencarian.' : 'Belum ada data pemilih.' ?></div>
        <?php else: ?>
            <form id="form-hapus-satu" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="aksi" value="hapus">
            </form>

            <!-- Pilih semua hanya memilih pemilih yang tampil di halaman aktif. -->
            <form id="form-hapus-massal" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="aksi" value="hapus_massal">

                <div class="card bulk-bar" style="margin-bottom:12px;">
                    <label class="pilih-semua">
                        <input type="checkbox" id="centang-semua" onclick="centangSemua(this)">
                        Pilih semua di halaman ini
                    </label>
                    <span class="jumlah-terpilih" id="jumlah-terpilih">0 dipilih</span>
                    <button class="btn btn-danger" type="submit" id="tombol-hapus-terpilih" disabled
                        onclick="return confirm('Hapus semua pemilih yang dicentang? Tindakan ini tidak dapat dibatalkan.');">
                        Hapus yang Dipilih
                    </button>
                </div>

                <?php while ($pemilih = mysqli_fetch_assoc($result)): ?>
                    <?php $sudah_memilih = (int) $pemilih['sudah_memilih'] === 1; ?>
                    <div class="card data-row" style="margin-bottom:10px;">
                        <span class="row-info">
                            <input type="checkbox" class="centang-baris" name="pilih[]"
                                value="<?= htmlspecialchars($pemilih['id_pemilih'], ENT_QUOTES, 'UTF-8') ?>"
                                onclick="perbaruiJumlah()">
                            <span class="badge"><?= htmlspecialchars($pemilih['id_pemilih'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="badge badge-muted"><?= ucfirst($pemilih['kategori']) ?></span>
                            <strong><?= htmlspecialchars($pemilih['username'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <span class="badge <?= $sudah_memilih ? 'badge-success' : 'badge-muted' ?>">
                                <?= $sudah_memilih ? '✓ Sudah Memilih' : 'Belum Memilih' ?>
                            </span>
                        </span>
                        <div class="row-actions">
                            <a class="btn btn-outline" href="pemilih.php?edit=<?= urlencode($pemilih['id_pemilih']) ?>">Ubah</a>
                            <button class="btn btn-danger" type="submit" form="form-hapus-satu"
                                name="id_pemilih" value="<?= htmlspecialchars($pemilih['id_pemilih'], ENT_QUOTES, 'UTF-8') ?>"
                                onclick="return confirm('Hapus pemilih ini?');">Hapus</button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </form>

            <?php if ($jumlah_halaman > 1): ?>
                <?php
                $parameter_halaman = $_GET;
                ?>
                <nav class="bulk-bar" aria-label="Navigasi halaman pemilih" style="justify-content:center; margin:18px 0;">
                    <?php if ($halaman > 1): ?>
                        <?php $parameter_halaman['halaman'] = $halaman - 1; ?>
                        <a class="btn btn-outline" href="pemilih.php?<?= htmlspecialchars(http_build_query($parameter_halaman), ENT_QUOTES, 'UTF-8') ?>">Sebelumnya</a>
                    <?php endif; ?>
                    <span class="muted">Halaman <?= $halaman ?> dari <?= $jumlah_halaman ?> (<?= $jumlah_hasil_total ?> pemilih)</span>
                    <?php if ($halaman < $jumlah_halaman): ?>
                        <?php $parameter_halaman['halaman'] = $halaman + 1; ?>
                        <a class="btn btn-outline" href="pemilih.php?<?= htmlspecialchars(http_build_query($parameter_halaman), ENT_QUOTES, 'UTF-8') ?>">Berikutnya</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>

            <form class="zona-bahaya" method="post"
                onsubmit="return confirm('PERHATIAN: seluruh data pemilih akan dihapus permanen. Pemilih yang sudah memberikan suara akan tetap dipertahankan. Lanjutkan?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="aksi" value="hapus_semua">
                <div>
                    <strong>Hapus seluruh data pemilih</strong>
                    <p>Berguna saat ingin memulai ulang pendataan (misal ganti tahun ajaran). Pemilih yang sudah memberikan suara tidak akan terhapus demi menjaga keabsahan hasil voting.</p>
                </div>
                <button class="btn btn-danger" type="submit">Hapus Semua</button>
            </form>
        <?php endif; ?>
    </main>
    <script>
        // Sinkronkan checkbox seluruh baris pada halaman yang sedang dibuka.
        function centangSemua(sumber) {
            document.querySelectorAll('.centang-baris').forEach(function(kotak) {
                kotak.checked = sumber.checked;
            });
            perbaruiJumlah();
        }

        function perbaruiJumlah() {
            var semua = document.querySelectorAll('.centang-baris');
            var terpilih = document.querySelectorAll('.centang-baris:checked').length;

            document.getElementById('jumlah-terpilih').textContent = terpilih + ' dipilih';
            document.getElementById('tombol-hapus-terpilih').disabled = terpilih === 0;

            var induk = document.getElementById('centang-semua');
            induk.checked = semua.length > 0 && terpilih === semua.length;
            induk.indeterminate = terpilih > 0 && terpilih < semua.length;
        }
    </script>
</body>
</html>





