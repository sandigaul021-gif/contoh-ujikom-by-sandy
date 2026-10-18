<?php
// File: unduh_template_pemilih.php
// Deskripsi: Mengunduh template CSV dengan contoh data pemilih.

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="template_pemilih.csv"');

// Baris pertama menjadi nama kolom yang diharapkan oleh fitur impor pemilih.
$csv = "id_pemilih,username,kategori";

echo $csv;
exit;
?>

