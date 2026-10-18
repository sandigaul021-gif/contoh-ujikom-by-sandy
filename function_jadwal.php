<?php
// Fungsi bantu untuk jadwal voting.
// Dipakai di halaman admin (jadwal.php, dashboard_admin.php) dan nanti di halaman pemilih.

// Semua perbandingan waktu memakai WIB supaya jam di layar sama dengan jam yang disimpan.
date_default_timezone_set('Asia/Jakarta');

function pastikanTabelPengaturan(mysqli $conn): void
{
    // Buat tabel pengaturan saat belum tersedia agar aplikasi memiliki tempat menyimpan jadwal.
    mysqli_query($conn, 'CREATE TABLE IF NOT EXISTS tb_pengaturan (
        id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
        waktu_mulai DATETIME NULL,
        waktu_selesai DATETIME NULL,
        aktif TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $kolom_aktif = mysqli_query($conn, "SHOW COLUMNS FROM tb_pengaturan LIKE 'aktif'");
    if ($kolom_aktif && mysqli_num_rows($kolom_aktif) === 0) {
        mysqli_query($conn, 'ALTER TABLE tb_pengaturan ADD COLUMN aktif TINYINT(1) NOT NULL DEFAULT 1');
    }
}

/**
 * Ambil jadwal dari tb_pengaturan.
 * Kalau tabel/barisnya belum ada, hasilnya kosong (dianggap "belum diatur").
 */
function ambilJadwalVoting(mysqli $conn): array
{
    pastikanTabelPengaturan($conn);
    $jadwal = ['waktu_mulai' => null, 'waktu_selesai' => null, 'aktif' => 1];
    // Baris dengan id=1 menyimpan satu konfigurasi jadwal untuk seluruh pemilihan.
    $hasil = mysqli_query($conn, 'SELECT waktu_mulai, waktu_selesai, aktif FROM tb_pengaturan WHERE id = 1');
    if ($hasil && ($baris = mysqli_fetch_assoc($hasil))) {
        $jadwal = $baris;
    }
    return $jadwal;
}

/**
 * Status voting saat ini:
 * 'belum_diatur' | 'belum_dibuka' | 'berlangsung' | 'ditutup'
 */
function statusVoting(array $jadwal): string
{
    // Tentukan status dari sakelar aktif dan rentang waktu yang telah diatur.
    if (empty($jadwal['aktif'])) {
        return 'nonaktif';
    }
    if (empty($jadwal['waktu_mulai']) || empty($jadwal['waktu_selesai'])) {
        return 'belum_diatur';
    }
    $sekarang = time();
    if ($sekarang < strtotime($jadwal['waktu_mulai'])) {
        return 'belum_dibuka';
    }
    if ($sekarang >= strtotime($jadwal['waktu_selesai'])) {
        return 'ditutup';
    }
    return 'berlangsung';
}

/**
 * Tulisan status untuk ditampilkan ke pengguna.
 */
function labelStatusVoting(string $status): string
{
    // Ubah kode status internal menjadi kalimat yang mudah dibaca pengguna.
    switch ($status) {
        case 'belum_dibuka':
            return 'Belum dibuka';
        case 'berlangsung':
            return 'Sedang berlangsung';
        case 'ditutup':
            return 'Sudah ditutup';
        case 'nonaktif':
            return 'Dimatikan oleh admin';
        default:
            return 'Jadwal belum diatur';
    }
}

/**
 * Jalan pintas untuk penjagaan di halaman voting:
 * true hanya kalau sekarang tepat di dalam jadwal.
 */
function votingSedangBuka(mysqli $conn): bool
{
    // Nilai ini juga dapat dipakai halaman lain untuk membatasi aksi memilih.
    return statusVoting(ambilJadwalVoting($conn)) === 'berlangsung';
}

