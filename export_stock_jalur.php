<?php
require_once 'koneksi.php';

// Get parameters
$selected_date = $_GET['date'] ?? '';
$selected_product_id = $_GET['product_id'] ?? '';

if (empty($selected_date)) {
    die('Tanggal harus dipilih untuk export.');
}

// Get all outgoing transactions for the selected date to find which batches were used
$sql_outgoing_by_date = "SELECT DISTINCT o.incoming_transaction_id 
                        FROM outgoing_transactions o 
                        WHERE DATE(o.transaction_date) = ?";
$params_date = [$selected_date];

if (!empty($selected_product_id)) {
    $sql_outgoing_by_date .= " AND o.product_id = ?";
    $params_date[] = $selected_product_id;
}

$stmt_outgoing_date = $pdo->prepare($sql_outgoing_by_date);
$stmt_outgoing_date->execute($params_date);
$outgoing_batch_ids = $stmt_outgoing_date->fetchAll(PDO::FETCH_COLUMN);

if (empty($outgoing_batch_ids)) {
    die('Tidak ada pengeluaran pada tanggal tersebut.');
}

// Get incoming transaction details for each batch that had outgoing transactions on the selected date
$placeholders = str_repeat('?,', count($outgoing_batch_ids) - 1) . '?';
$sql_incoming_for_outgoing = "SELECT t.*, p.product_name, p.sku 
                             FROM incoming_transactions t 
                             JOIN products p ON t.product_id = p.id 
                             WHERE t.id IN ($placeholders)
                             ORDER BY p.product_name ASC, t.created_at ASC";

$stmt_incoming_for_outgoing = $pdo->prepare($sql_incoming_for_outgoing);
$stmt_incoming_for_outgoing->execute($outgoing_batch_ids);
$incoming_for_outgoing = $stmt_incoming_for_outgoing->fetchAll(PDO::FETCH_ASSOC);

// Set headers for Excel download
$filename = 'Stock_Jalur_' . date('Y-m-d', strtotime($selected_date)) . '.xls';
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Start HTML output for Excel
echo '<!DOCTYPE html>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta charset="UTF-8">
    <style>
        table { 
            border-collapse: collapse; 
            width: 100%; 
            margin-bottom: 30px; 
            font-family: Arial, sans-serif;
        }
        th, td { 
            border: 1px solid #000; 
            padding: 5px; 
            text-align: center; 
            vertical-align: middle; 
            font-size: 10px;
        }
        .header { 
            background-color: #d9d9d9; 
            font-weight: bold; 
            font-size: 10px;
        }
        .left-align { text-align: left; }
        .data-cell { font-size: 9px; }
        .product-title {
            background-color: #f0f0f0;
            font-weight: bold;
            font-size: 12px;
            text-align: left;
            padding: 8px;
        }
    </style>
</head>
<body>';

// Process each incoming transaction
foreach ($incoming_for_outgoing as $incoming) {
    // Get outgoing transactions for this incoming
    $sql_outgoing = "SELECT * FROM outgoing_transactions WHERE incoming_transaction_id = ? ORDER BY transaction_date ASC, created_at ASC";
    $stmt_outgoing = $pdo->prepare($sql_outgoing);
    $stmt_outgoing->execute([$incoming['id']]);
    $outgoing_data = $stmt_outgoing->fetchAll(PDO::FETCH_ASSOC);

    // Calculate total columns needed
    $total_cols = 5 + count($outgoing_data);

    // Product title
    echo '<table>';
    echo '<tr><td colspan="' . $total_cols . '" class="product-title">' . strtoupper(htmlspecialchars($incoming['product_name'])) . ' - Batch: ' . htmlspecialchars($incoming['batch_number'] ?: 'N/A') . '</td></tr>';
    echo '</table>';

    echo '<table>';

    // Header row
    echo '<tr>';
    echo '<th class="header">Tanggal<br>Kedatangan</th>';
    echo '<th class="header">No. PO / Exp. Date</th>';
    echo '<th class="header">Produsen</th>';
    echo '<th class="header">Supplier</th>';
    echo '<th class="header">Jumlah</th>';
    echo '<th class="header" colspan="' . count($outgoing_data) . '">Pengiriman</th>';
    echo '</tr>';

    // Data row with vertical sub-headers in Pengiriman section
    echo '<tr>';

    // Main data cells (spanning 3 rows)
    echo '<td rowspan="3" class="data-cell">' . date('d.m.y', strtotime($incoming['transaction_date'])) . '</td>';
    echo '<td rowspan="3" class="data-cell left-align">' . htmlspecialchars($incoming['po_number'] ?: '-') . '</td>';
    echo '<td rowspan="3" class="data-cell left-align">' . htmlspecialchars($incoming['produsen'] ?: '-') . '</td>';
    echo '<td rowspan="3" class="data-cell left-align">' . htmlspecialchars($incoming['supplier'] ?: '-') . '</td>';

    // Format quantity (Kg/Sak)
    $qty_display = number_format($incoming['quantity_kg'], 0) . '/' . number_format($incoming['quantity_sacks'], 0);
    echo '<td rowspan="3" class="data-cell">' . $qty_display . '</td>';

    // First sub-row: "Tgl."
    foreach ($outgoing_data as $out) {
        echo '<td class="header">Tgl.</td>';
    }
    echo '</tr>';

    // Second sub-row: Actual dates
    echo '<tr>';
    foreach ($outgoing_data as $out) {
        echo '<td class="data-cell">' . date('d/m/Y', strtotime($out['transaction_date'])) . '</td>';
    }
    echo '</tr>';

    // Third sub-row: "Jumlah" with values
    echo '<tr>';
    foreach ($outgoing_data as $out) {
        echo '<td class="data-cell">' . number_format($out['quantity_sacks'], 0) . '</td>';
    }
    echo '</tr>';

    // Fourth row: "Sisa Stock" with calculated values
    echo '<tr>';
    echo '<td colspan="5" class="header">Sisa Stock</td>';
    $sisa_stok = $incoming['quantity_sacks'];
    foreach ($outgoing_data as $out) {
        $sisa_stok -= $out['quantity_sacks'];
        echo '<td class="data-cell">' . number_format($sisa_stok, 0) . '</td>';
    }
    echo '</tr>';

    echo '</table>';

    // Add spacing between products
    echo '<br><br>';
}

// Add footer with export info
echo '<table style="margin-top: 20px;">';
echo '<tr><td colspan="8" style="text-align: left; font-size: 10px; border: none; padding: 10px;">
    <strong>Laporan Stock Jalur</strong><br>
    Tanggal Export: ' . date('d/m/Y H:i:s') . '<br>
    Tanggal Data: ' . date('d/m/Y', strtotime($selected_date)) . '
</td></tr>';
echo '</table>';

echo '</body></html>';
