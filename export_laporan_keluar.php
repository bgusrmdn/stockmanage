<?php
include 'koneksi.php';

// --- FUNGSI UNTUK FORMAT TANGGAL INDONESIA ---
function format_tanggal_indo_laporan($tanggal)
{
    if (empty($tanggal)) return '';
    $timestamp = strtotime($tanggal);
    return date('d/m/Y', $timestamp);
}

// --- LOGIKA FILTER ---
$filter_date = $_GET['filter_date'] ?? null;

// --- QUERY SQL BARU UNTUK AGREGRASI DATA ---
$sql = "
    SELECT
        t.transaction_date,
        p.product_name,
        p.sku,
        SUM(t.quantity_kg) as total_kg,
        SUM(t.quantity_sacks) as total_sacks,
        GROUP_CONCAT(DISTINCT t.document_number ORDER BY t.document_number SEPARATOR ', ') as all_docs,
        GROUP_CONCAT(DISTINCT t.batch_number ORDER BY t.batch_number SEPARATOR ', ') as all_batches
    FROM
        outgoing_transactions t
    JOIN
        products p ON t.product_id = p.id
";

$params = [];
if (!empty($filter_date)) {
    $sql .= " WHERE t.transaction_date = :filter_date";
    $params[':filter_date'] = $filter_date;
}

$sql .= " GROUP BY t.transaction_date, p.id, p.product_name, p.sku";
$sql .= " ORDER BY t.transaction_date, p.product_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- PROSES PEMBUATAN FILE CSV ---
$filename = "laporan_barang_keluar" . ($filter_date ? "_" . $filter_date : "_semua") . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$output = fopen('php://output', 'w');

// Header kolom sesuai permintaan
$header = ['Tanggal', 'Nama Barang', 'Kode Barang', 'Total Qty (Kg)', 'Total Qty (Sak)', 'No. Dokumen Terkait', 'Batch Terkait'];
fputcsv($output, $header, ';');

// Tulis setiap baris data
foreach ($results as $row) {
    $csv_row = [
        format_tanggal_indo_laporan($row['transaction_date']),
        $row['product_name'],
        $row['sku'],
        formatAngka($row['total_kg']),
        formatAngka($row['total_sacks']),
        $row['all_docs'],
        $row['all_batches']
    ];
    fputcsv($output, $csv_row, ';');
}

fclose($output);
exit();
