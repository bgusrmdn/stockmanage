<?php
include 'koneksi.php';

// --- LOGIKA FILTER (Tidak ada perubahan) ---
$filter_date = $_GET['filter_date'] ?? null;
$filter_status = $_GET['status_filter'] ?? '';
$search_query = $_GET['s'] ?? '';
$po_query = $_GET['po'] ?? '';
$doc_query = $_GET['doc'] ?? '';
$batch_query = $_GET['batch'] ?? '';

// --- REVISI QUERY SQL SESUAI PERMINTAAN ---
$sql = "SELECT 
            t.po_number,
            t.supplier,
            t.license_plate,
            p.product_name,
            p.sku,
            t.quantity_kg,
            t.quantity_sacks,
            t.document_number,
            t.lot_number
        FROM incoming_transactions t
        JOIN products p ON t.product_id = p.id
        WHERE 1=1";

$params = [];
if (!empty($filter_date)) {
    $sql .= " AND t.transaction_date = :filter_date";
    $params[':filter_date'] = $filter_date;
}
if (!empty($filter_status)) {
    $sql .= " AND t.status = :status_filter";
    $params[':status_filter'] = $filter_status;
}
if (!empty($search_query)) {
    $sql .= " AND (p.product_name LIKE :search OR p.sku LIKE :search)";
    $params[':search'] = '%' . $search_query . '%';
}
if (!empty($po_query)) {
    $sql .= " AND t.po_number LIKE :po_number";
    $params[':po_number'] = '%' . $po_query . '%';
}
if (!empty($doc_query)) {
    $sql .= " AND t.document_number LIKE :document_number";
    $params[':document_number'] = '%' . $doc_query . '%';
}
if (!empty($batch_query)) {
    $sql .= " AND t.batch_number LIKE :batch_number";
    $params[':batch_number'] = '%' . $batch_query . '%';
}

$sql .= " ORDER BY t.created_at ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- PROSES PEMBUATAN FILE CSV ---
$filename = "laporan_barang_masuk" . ($filter_date ? "_" . $filter_date : "") . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$output = fopen('php://output', 'w');

// REVISI: Header kolom baru sesuai permintaan
$header = ['Nomor PO', 'Supplier', 'No Polisi', 'Nama Barang', 'Kode Barang', 'Qty (Kg)', 'Qty (Sak)', 'Nomor Dokumen', '501'];
fputcsv($output, $header, ';');

// REVISI: Tulis setiap baris data sesuai urutan baru
foreach ($transactions as $row) {
    $csv_row = [
        $row['po_number'],
        $row['supplier'],
        $row['license_plate'],
        $row['product_name'],
        $row['sku'],
        formatAngka($row['quantity_kg']),
        formatAngka($row['quantity_sacks']),
        $row['document_number'],
        formatAngka($row['lot_number'])
    ];
    fputcsv($output, $csv_row, ';');
}

fclose($output);
exit();
