<?php
include 'koneksi.php';

// --- LOGIKA FILTER (Sama seperti di halaman laporan) ---
$filter_date = $_GET['filter_date'] ?? date('Y-m-d');
$filter_qty_kg = $_GET['filter_qty_kg'] ?? '';

// --- QUERY SQL (Sama persis seperti di halaman laporan) ---
$sql = "
    SELECT
        p.id, p.sku, p.product_name,
        
        (SUM(CASE WHEN t.type = 'IN' AND t.transaction_date < ? THEN t.quantity_kg ELSE 0 END) -
         SUM(CASE WHEN t.type = 'OUT' AND t.transaction_date < ? THEN t.quantity_kg ELSE 0 END))
        AS opening_stock_kg,
        
        (SUM(CASE WHEN t.type = 'IN' AND t.transaction_date < ? THEN t.quantity_sacks ELSE 0 END) -
         SUM(CASE WHEN t.type = 'OUT' AND t.transaction_date < ? THEN t.quantity_sacks ELSE 0 END))
        AS opening_stock_sak,

        SUM(CASE WHEN t.type = 'IN' AND t.transaction_date = ? THEN t.quantity_kg ELSE 0 END) AS incoming_kg_today,
        SUM(CASE WHEN t.type = 'IN' AND t.transaction_date = ? THEN t.quantity_sacks ELSE 0 END) AS incoming_sak_today,
        
        SUM(CASE WHEN t.type = 'OUT' AND t.transaction_date = ? THEN t.quantity_kg ELSE 0 END) AS outgoing_kg_today,
        SUM(CASE WHEN t.type = 'OUT' AND t.transaction_date = ? THEN t.quantity_sacks ELSE 0 END) AS outgoing_sak_today

    FROM
        products p
    LEFT JOIN (
        SELECT product_id, transaction_date, quantity_kg, quantity_sacks, 'IN' as type FROM incoming_transactions
        UNION ALL
        SELECT product_id, transaction_date, quantity_kg, quantity_sacks, 'OUT' as type FROM outgoing_transactions
    ) as t ON p.id = t.product_id
    GROUP BY
        p.id, p.sku, p.product_name
    ORDER BY
        p.product_name ASC
";

$stmt = $pdo->prepare($sql);
$params = [
    $filter_date,
    $filter_date,
    $filter_date,
    $filter_date,
    $filter_date,
    $filter_date,
    $filter_date,
    $filter_date,
];
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- PROSES PEMBUATAN FILE CSV ---
$filename = "laporan_harian_" . $filter_date . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$output = fopen('php://output', 'w');

// Header kolom sesuai dengan tampilan di halaman
$header = ['No', 'Nama Barang', 'Stok Awal (Kg)', 'Stok Awal (Sak)', 'Masuk (Kg)', 'Masuk (Sak)', 'Keluar (Kg)', 'Keluar (Sak)', 'Stok Akhir (Kg)', 'Stok Akhir (Sak)', 'Rata-rata Qty'];
fputcsv($output, $header, ';');

// Tulis setiap baris data
$nomor = 1;
foreach ($results as $row) {
    $closing_stock_kg = $row['opening_stock_kg'] + $row['incoming_kg_today'] - $row['outgoing_kg_today'];

    // Terapkan filter yang sama seperti di halaman
    if (is_numeric($filter_qty_kg) && $closing_stock_kg < (float)$filter_qty_kg) {
        continue;
    }

    $closing_stock_sak = $row['opening_stock_sak'] + $row['incoming_sak_today'] - $row['outgoing_sak_today'];
    $average_qty = ($closing_stock_sak != 0) ? $closing_stock_kg / $closing_stock_sak : 0;

    $csv_row = [
        $nomor++,
        $row['product_name'],
        formatAngka($row['opening_stock_kg']),
        formatAngka($row['opening_stock_sak']),
        formatAngka($row['incoming_kg_today']),
        formatAngka($row['incoming_sak_today']),
        formatAngka($row['outgoing_kg_today']),
        formatAngka($row['outgoing_sak_today']),
        formatAngka($closing_stock_kg),
        formatAngka($closing_stock_sak),
        formatAngka($average_qty),
    ];
    fputcsv($output, $csv_row, ';');
}

fclose($output);
exit();
