<?php
$filter_date = $_GET['filter_date'] ?? date('Y-m-d');
$filter_qty_kg = $_GET['filter_qty_kg'] ?? '';

// QUERY FINAL DENGAN PLACEHOLDER TANDA TANYA (?) YANG PALING STABIL
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

// Siapkan array parameter. Jumlahnya harus sama persis dengan jumlah tanda tanya (?) di query
$params = [
    $filter_date, // untuk opening_stock_kg (IN)
    $filter_date, // untuk opening_stock_kg (OUT)
    $filter_date, // untuk opening_stock_sak (IN)
    $filter_date, // untuk opening_stock_sak (OUT)
    $filter_date, // untuk incoming_kg_today
    $filter_date, // untuk incoming_sak_today
    $filter_date, // untuk outgoing_kg_today
    $filter_date, // untuk outgoing_sak_today
];

$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

$report_data = [];
foreach ($results as $row) {
    $closing_stock_kg = $row['opening_stock_kg'] + $row['incoming_kg_today'] - $row['outgoing_kg_today'];

    if (is_numeric($filter_qty_kg) && $closing_stock_kg < (float)$filter_qty_kg) {
        continue;
    }

    $closing_stock_sak = $row['opening_stock_sak'] + $row['incoming_sak_today'] - $row['outgoing_sak_today'];
    $average_qty = ($closing_stock_sak != 0) ? $closing_stock_kg / $closing_stock_sak : 0;

    $report_data[] = [
        'sku' => $row['sku'],
        'product_name' => $row['product_name'],
        'opening_stock_kg' => $row['opening_stock_kg'],
        'opening_stock_sak' => $row['opening_stock_sak'],
        'incoming_kg_today' => $row['incoming_kg_today'],
        'incoming_sak_today' => $row['incoming_sak_today'],
        'outgoing_kg_today' => $row['outgoing_kg_today'],
        'outgoing_sak_today' => $row['outgoing_sak_today'],
        'closing_stock_kg' => $closing_stock_kg,
        'closing_stock_sak' => $closing_stock_sak,
        'average_qty' => $average_qty,
    ];
}
?>
<div class="container-fluid">
    <div class="card">
        <div class="card-header bg-white py-3">
            <h2 class="h5 mb-3 fw-bold">Laporan Stok Harian</h2>
            <form action="index.php" method="GET" class="filter-form">
                <input type="hidden" name="page" value="laporan">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3"><label class="form-label fw-semibold small">Pilih Tanggal</label><input type="date" name="filter_date" class="form-control" value="<?= htmlspecialchars($filter_date) ?>"></div>
                    <div class="col-md-3"><label class="form-label fw-semibold small">Stok Akhir (Kg) >=</label><input type="number" step="any" name="filter_qty_kg" class="form-control" placeholder="Contoh: 100" value="<?= htmlspecialchars($filter_qty_kg) ?>"></div>
                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-filter"></i> Tampilkan</button>
                        <a href="export_laporan_harian.php?<?= http_build_query($_GET) ?>" class="btn btn-success" title="Export"><i class="bi bi-file-earmark-spreadsheet-fill"></i></a>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th rowspan="2">No</th>
                            <th rowspan="2" class="text-start">Nama Barang</th>
                            <th colspan="2">Stok Awal</th>
                            <th colspan="2">Barang Masuk</th>
                            <th colspan="2">Barang Keluar</th>
                            <th colspan="2">Stok Akhir</th>
                            <th rowspan="2">Rata-rata Qty</th>
                        </tr>
                        <tr class="sub-header">
                            <th>Kg</th>
                            <th>Sak</th>
                            <th>Kg</th>
                            <th>Sak</th>
                            <th>Kg</th>
                            <th>Sak</th>
                            <th>Kg</th>
                            <th>Sak</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($report_data)): ?>
                            <tr>
                                <td colspan="11" class="text-center text-muted p-4"><i class="bi bi-inbox fs-2 d-block"></i>Tidak ada data untuk ditampilkan.</td>
                            </tr>
                            <?php else: $nomor = 1;
                            foreach ($report_data as $data): ?>
                                <tr>
                                    <td><?= $nomor++ ?></td>
                                    <td class="text-start"><?= htmlspecialchars($data['product_name']) ?></td>
                                    <td><?= formatAngka($data['opening_stock_kg']) ?></td>
                                    <td><?= formatAngka($data['opening_stock_sak']) ?></td>
                                    <td><?= formatAngka($data['incoming_kg_today']) ?></td>
                                    <td><?= formatAngka($data['incoming_sak_today']) ?></td>
                                    <td><?= formatAngka($data['outgoing_kg_today']) ?></td>
                                    <td><?= formatAngka($data['outgoing_sak_today']) ?></td>
                                    <td class="fw-bold"><?= formatAngka($data['closing_stock_kg']) ?></td>
                                    <td class="fw-bold"><?= formatAngka($data['closing_stock_sak']) ?></td>
                                    <td><?= formatAngka($data['average_qty']) ?></td>
                                </tr>
                        <?php endforeach;
                        endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>