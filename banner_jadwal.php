<?php
// Kotak status jadwal voting + timer hitung mundur untuk halaman pemilih.
// Dipanggil dari pilih_kandidat.php, jadi butuh variabel $jadwal_voting dan $status_voting dari sana.
if (!isset($status_voting, $jadwal_voting)) {
    return; // file dibuka langsung, bukan lewat pilih_kandidat.php
}

$target_ms = 0;
$teks_timer = '';
// Timer menghitung mundur ke waktu mulai atau selesai sesuai status saat ini.
if ($status_voting === 'belum_dibuka') {
    $target_ms = strtotime($jadwal_voting['waktu_mulai']) * 1000;
    $teks_timer = 'Voting dibuka dalam';
} elseif ($status_voting === 'berlangsung') {
    $target_ms = strtotime($jadwal_voting['waktu_selesai']) * 1000;
    $teks_timer = 'Voting ditutup dalam';
}

$daftar_judul = [
    'belum_diatur' => 'Jadwal pemilihan belum diatur',
    'belum_dibuka' => 'Pemilihan belum dibuka',
    'berlangsung'  => 'Pemilihan sedang berlangsung',
    'ditutup'      => 'Pemilihan sudah ditutup',
    'nonaktif'     => 'Pemilihan dimatikan oleh panitia',
];
$judul_status = $daftar_judul[$status_voting] ?? 'Status pemilihan';

$ada_jadwal = !empty($jadwal_voting['waktu_mulai']) && !empty($jadwal_voting['waktu_selesai']);
?>

<!-- Status jadwal selalu ditampilkan; waktu dan timer muncul jika jadwal tersedia. -->
<div class="jadwal-box jadwal-<?= htmlspecialchars($status_voting, ENT_QUOTES, 'UTF-8') ?>">
    <strong class="judul"><?= htmlspecialchars($judul_status, ENT_QUOTES, 'UTF-8') ?></strong>
    <?php if ($ada_jadwal): ?>
        <div class="jadwal-waktu">
            Jadwal: <?= date('d-m-Y H:i', strtotime($jadwal_voting['waktu_mulai'])) ?>
            sampai <?= date('d-m-Y H:i', strtotime($jadwal_voting['waktu_selesai'])) ?> WIB
        </div>
    <?php endif; ?>
    <?php if ($target_ms > 0): ?>
        <div class="jadwal-timer">
            <span><?= $teks_timer ?></span>
            <strong id="timer-voting">--:--:--</strong>
        </div>
    <?php endif; ?>
</div>

<?php if ($target_ms > 0): ?>
<script>
    // Jam server dipakai sebagai patokan, jadi timer tetap akurat walau jam perangkat meleset.
    (function () {
        var selisihServer = <?= time() * 1000 ?> - Date.now();
        var target = <?= $target_ms ?>;
        var elemen = document.getElementById('timer-voting');

        function dua(n) { return n < 10 ? '0' + n : '' + n; }

        function tik() {
            var sisa = target - (Date.now() + selisihServer);
            if (sisa <= 0) {
                elemen.textContent = '00:00:00';
                // Waktunya berganti: muat ulang supaya status dan tombol ikut berubah.
                setTimeout(function () { location.href = location.pathname; }, 1000);
                return;
            }
            var detik = Math.floor(sisa / 1000);
            var hari = Math.floor(detik / 86400);
            var jam = Math.floor((detik % 86400) / 3600);
            var menit = Math.floor((detik % 3600) / 60);
            elemen.textContent = (hari > 0 ? hari + ' hari ' : '') + dua(jam) + ':' + dua(menit) + ':' + dua(detik % 60);
            setTimeout(tik, 1000);
        }
        tik();
    })();
</script>
<?php endif; ?>

