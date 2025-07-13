<?php
header('Content-Type: application/json');
include 'koneksi.php';

$doc_number = isset($_GET['doc_number']) ? $_GET['doc_number'] : '';

if ($doc_number === '' || $doc_number === null) {
    echo json_encode(['error' => 'Nomor dokumen kosong atau tidak valid. Transaksi tanpa nomor dokumen tidak bisa diedit.']);
    exit();
}

try {
    $stmt_main = $pdo->prepare("SELECT transaction_date, description, status FROM outgoing_transactions WHERE document_number = ? LIMIT 1");
    $stmt_main->execute([$doc_number]);
    $main_data = $stmt_main->fetch(PDO::FETCH_ASSOC);

    if (!$main_data) {
        echo json_encode(['error' => 'Transaksi dengan nomor dokumen ini tidak ditemukan.']);
        exit();
    }

    $stmt_items = $pdo->prepare("
        SELECT 
            t.product_id, p.product_name, p.sku, t.incoming_transaction_id as incoming_id, 
            i.batch_number, t.quantity_kg as qty_kg, t.quantity_sacks as qty_sak
        FROM outgoing_transactions t
        JOIN products p ON t.product_id = p.id
        JOIN incoming_transactions i ON t.incoming_transaction_id = i.id
        WHERE t.document_number = ?
    ");
    $stmt_items->execute([$doc_number]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

    $response = [
        'main' => $main_data,
        'items' => $items
    ];

    echo json_encode($response);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Gagal mengambil data: ' . $e->getMessage()]);
    exit();
}
