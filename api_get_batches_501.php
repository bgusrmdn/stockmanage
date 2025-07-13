<?php
header('Content-Type: application/json');
include 'koneksi.php';

$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
if ($product_id === 0) {
    echo json_encode([]);
    exit();
}

try {
    // Query ini akan mengambil batch yang masih memiliki sisa lot_number (501) lebih dari 0
    // Menghitung sisa 501 = lot_number awal - total yang sudah dikeluarkan via 501
    $sql = "
        SELECT
            t_in.id,
            t_in.transaction_date,
            t_in.batch_number,
            t_in.lot_number as original_lot_number,
            COALESCE(keluar_501.total_keluar_501, 0) as total_keluar_501,
            (t_in.lot_number - COALESCE(keluar_501.total_keluar_501, 0)) AS sisa_lot_number,
            (t_in.lot_number - COALESCE(keluar_501.total_keluar_501, 0)) AS remaining_501
        FROM
            incoming_transactions t_in
        LEFT JOIN (
            SELECT 
                incoming_transaction_id,
                SUM(lot_number) as total_keluar_501
            FROM outgoing_transactions 
            WHERE lot_number > 0 
            AND description LIKE '%501%'
            GROUP BY incoming_transaction_id
        ) keluar_501 ON t_in.id = keluar_501.incoming_transaction_id
        WHERE
            t_in.product_id = ?
            AND t_in.lot_number > 0
        HAVING 
            sisa_lot_number > 0
        ORDER BY
            t_in.transaction_date ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$product_id]);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format hasil untuk memastikan angka dalam format yang benar
    foreach ($batches as &$batch) {
        $batch['sisa_lot_number'] = (float)$batch['sisa_lot_number'];
        $batch['remaining_501'] = (float)$batch['remaining_501'];
        $batch['original_lot_number'] = (float)$batch['original_lot_number'];
        $batch['total_keluar_501'] = (float)$batch['total_keluar_501'];
    }

    echo json_encode($batches);
} catch (PDOException $e) {
    error_log("Error in api_get_batches_501.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Gagal mengambil data dari database.', 'debug' => $e->getMessage()]);
    exit();
}
