<?php
// Halaman admin untuk menampilkan dan mencetak kartu identitas/kredensial pemilih.
require_once 'config.php';
require_once 'admin_guard.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    exit('Koneksi database tidak tersedia.');
}

// Admin dapat memfilter kartu berdasarkan kategori sebelum mencetaknya.
$filter_kategori = $_GET['kategori'] ?? '';
$filter_kategori = in_array($filter_kategori, ['siswa', 'guru'], true) ? $filter_kategori : '';

$where = '';
$params = [];
$types = '';

if ($filter_kategori !== '') {
    $where = ' WHERE kategori = ?';
    $params[] = $filter_kategori;
    $types = 's';
}

// Data akun diambil untuk dicetak pada kartu; akses halaman ini dibatasi untuk admin.
$sql = 'SELECT id_pemilih, username, password, kategori FROM tb_pemilih' . $where . ' ORDER BY username ASC';
$stmt = mysqli_prepare($conn, $sql);

if ($stmt && $where !== '') {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $sql);
}

if (!$result) {
    exit('Data pemilih tidak dapat dimuat.');
}

$daftar_pemilih = [];
// Hasil query disiapkan sebagai daftar agar setiap pemilih dapat dirender menjadi kartu.
while ($row = mysqli_fetch_assoc($result)) {
    $daftar_pemilih[] = $row;
}
if (isset($stmt) && $stmt) {
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Kartu Pemilih | E-Voting OSIS</title>
    <style>
        /* CSS khusus halaman kartu, termasuk aturan tampilan saat dicetak. */
        /* CSS lokal mengatur pratinjau kartu dan menyembunyikan kontrol saat dicetak. */
        * { box-sizing: border-box; }

        body {
            font-family: Arial, sans-serif;
            background: #f3f4f6;
            margin: 0;
            padding: 20px;
        }

        .page {
            max-width: 1200px;
            margin: 0 auto;
        }

        .topbar {
            background: #fff;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .topbar h2 {
            margin: 0;
            font-size: 24px;
            color: #111827;
        }

        .topbar .actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter select {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }

        .btn {
            display: inline-block;
            background: #1d4ed8;
            color: white;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 700;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 8px;
            display: inline-block;
            font-weight: 700;
        }

        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 18px;
        }

        .kartu {
            width: 100%;
            max-width: 320px;
            min-height: 220px;
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid #111827;
            box-shadow: 0 4px 10px rgba(0,0,0,0.08);
            background: #1d4ed8;
            color: white;
        }

        .kartu-header {
            text-align: center;
            padding: 12px 10px 8px;
            border-bottom: 1px solid rgba(255,255,255,0.4);
        }

        .kartu-header h3 {
            margin: 0;
            font-size: 18px;
            letter-spacing: 1px;
        }

        .kartu-header small {
            display: block;
            margin-top: 5px;
            font-size: 11px;
            opacity: 0.9;
        }

        .kartu-body {
            padding: 16px 18px;
        }

        .label {
            font-size: 10px;
            letter-spacing: 1px;
            text-transform: uppercase;
            opacity: 0.8;
            margin-bottom: 4px;
        }

        .value {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 12px;
            word-break: break-word;
        }

        .password-box {
            display: inline-block;
            margin-top: 8px;
            padding: 8px 12px;
            border-radius: 8px;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.3);
            font-family: 'Courier New', monospace;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .kartu-footer {
            padding: 8px 10px;
            text-align: center;
            font-size: 10px;
            border-top: 1px solid rgba(255,255,255,0.4);
            opacity: 0.9;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .topbar {
                display: none;
            }

            .kartu {
                page-break-inside: avoid;
                break-inside: avoid;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                background: #1d4ed8 !important;
            }

            .kartu-header,
            .kartu-footer {
                border-color: rgba(255,255,255,0.4) !important;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <!-- Bilah kontrol untuk filter dan print; disembunyikan oleh aturan media print. -->
        <div class="topbar">
            <h2>Kartu Pemilih</h2>
            <div class="actions">
                <form class="filter" method="get">
                    <label for="kategori">Kategori:</label>
                    <select id="kategori" name="kategori" onchange="this.form.submit()">
                        <option value="">Semua</option>
                        <option value="siswa" <?= $filter_kategori === 'siswa' ? 'selected' : '' ?>>Siswa</option>
                        <option value="guru" <?= $filter_kategori === 'guru' ? 'selected' : '' ?>>Guru</option>
                    </select>
                </form>
                <button class="btn" onclick="window.print()">Cetak Kartu</button>
                <a href="dashboard_admin.php" class="btn-secondary">Kembali</a>
            </div>
        </div>

        <div class="card-grid">
            <?php foreach ($daftar_pemilih as $pemilih): ?>
                <div class="kartu">
                    <div class="kartu-header">
                        <h3>KARTU PEMILIH</h3>
                        <small>Pemilihan Ketua OSIS</small>
                    </div>

                    <div class="kartu-body">
                        <div class="label">ID Pemilih</div>
                        <div class="value"><?= htmlspecialchars($pemilih['id_pemilih'], ENT_QUOTES, 'UTF-8') ?></div>

                        <div class="label">Nama</div>
                        <div class="value"><?= htmlspecialchars($pemilih['username'], ENT_QUOTES, 'UTF-8') ?></div>

                        <div class="label">Kategori</div>
                        <div class="value"><?= htmlspecialchars($pemilih['kategori'], ENT_QUOTES, 'UTF-8') ?></div>

                        <div class="label">Password</div>
                        <div class="password-box"><?= htmlspecialchars($pemilih['password'], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>

                    <div class="kartu-footer">
                        Gunakan kartu ini saat login ke sistem voting
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>

