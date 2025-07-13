<?php
// --- BAGIAN BARU UNTUK LOGIKA SORTIR ---
// Tentukan kolom dan urutan default
$sort_by = $_GET['sort_by'] ?? 'product_name';
$order = $_GET['order'] ?? 'ASC';

// Whitelist untuk kolom yang boleh di-sort untuk keamanan
$allowed_sort = ['product_name', 'sku'];
if (!in_array($sort_by, $allowed_sort)) {
    $sort_by = 'product_name';
}

// Pastikan urutan hanya ASC atau DESC
$order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

// Tentukan urutan untuk link berikutnya (jika diklik lagi)
$next_order = ($order === 'ASC') ? 'DESC' : 'ASC';
// --- AKHIR BAGIAN BARU ---

// Logika untuk menampilkan pesan status
$message = '';
$status_type = '';
if (isset($_GET['status'])) {
    $status_messages = ['sukses_tambah' => 'Produk baru berhasil ditambahkan.', 'sukses_edit' => 'Data produk berhasil diperbarui.', 'dihapus' => 'Produk berhasil dihapus.'];
    if (array_key_exists($_GET['status'], $status_messages)) {
        $message = $status_messages[$_GET['status']];
        $status_type = $_GET['status'] == 'dihapus' ? 'warning' : 'success';
    }
}

// --- PERUBAHAN UTAMA PADA QUERY SQL ---
// Query ini mengambil semua data produk beserta total stok dan total nilai 501 (lot_number)
// dari transaksi masuk dan keluar.
$sql = "
    SELECT
        p.id,
        p.sku,
        p.product_name,
        p.standard_qty,
        (SELECT COALESCE(SUM(quantity_kg), 0) FROM incoming_transactions WHERE product_id = p.id) AS total_masuk_kg,
        (SELECT COALESCE(SUM(quantity_sacks), 0) FROM incoming_transactions WHERE product_id = p.id) AS total_masuk_sak,
        (SELECT COALESCE(SUM(quantity_kg), 0) FROM outgoing_transactions WHERE product_id = p.id) AS total_keluar_kg,
        (SELECT COALESCE(SUM(quantity_sacks), 0) FROM outgoing_transactions WHERE product_id = p.id) AS total_keluar_sak,
        (SELECT COALESCE(SUM(lot_number), 0) FROM incoming_transactions WHERE product_id = p.id) AS total_501_masuk,
        (SELECT COALESCE(SUM(lot_number), 0) FROM outgoing_transactions WHERE product_id = p.id) AS total_501_keluar
    FROM
        products p
    ORDER BY
        p.{$sort_by} {$order}
";
$stmt = $pdo->query($sql);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<div class="container-fluid">
    <?php if ($message): ?>
        <div class="alert alert-<?= htmlspecialchars($status_type) ?> alert-dismissible fade show" role="alert">
            <strong><?= $message ?></strong>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold text-primary">Data Master Produk</h6>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#productModal">
                <i class="bi bi-plus-circle-fill me-2"></i>Tambah Produk
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-start">
                                <?php
                                $linkParams = $_GET; // Ambil parameter GET yang ada
                                $linkParams['sort_by'] = 'product_name';
                                $linkParams['order'] = $next_order;
                                ?>
                                <a href="index.php?<?= http_build_query($linkParams) ?>" class="text-decoration-none text-dark">
                                    Nama Barang
                                    <?php if ($sort_by === 'product_name'): ?>
                                        <i class="bi <?= $order === 'ASC' ? 'bi-sort-alpha-down' : 'bi-sort-alpha-up-alt' ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Kode Barang (SKU)</th>
                            <th>Standar Qty (Kg)</th>
                            <th>Rata-rata Qty</th>
                            <th>501 Masuk (Kg)</th>
                            <th>501 Keluar (Kg)</th>
                            <th class="fw-bold">Selisih 501</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="8" class="text-center p-4">Belum ada data produk.</td>
                            </tr>
                            <?php else: foreach ($products as $row): ?>
                                <?php
                                // Hitung Stok Akhir & Rata-rata Qty
                                $stok_akhir_kg = $row['total_masuk_kg'] - $row['total_keluar_kg'];
                                $stok_akhir_sak = $row['total_masuk_sak'] - $row['total_keluar_sak'];
                                $rata_rata_qty = ($stok_akhir_sak != 0) ? ($stok_akhir_kg / $stok_akhir_sak) : 0;

                                // Hitung Selisih 501
                                $selisih_501 = $row['total_501_masuk'] - $row['total_501_keluar'];
                                ?>
                                <tr>
                                    <td class="text-start"><?= htmlspecialchars($row['product_name']) ?></td>
                                    <td><?= htmlspecialchars($row['sku']) ?></td>
                                    <td><?= formatAngka($row['standard_qty']) ?></td>
                                    <td><?= formatAngka($rata_rata_qty) ?></td>
                                    <td><?= formatAngka($row['total_501_masuk']) ?></td>
                                    <td><?= formatAngka($row['total_501_keluar']) ?></td>
                                    <td class="fw-bold"><?= formatAngka($selisih_501) ?></td>
                                    <td class="text-center">
                                        <div class="btn-group">
                                            <button class="btn btn-warning btn-sm edit-btn" data-bs-toggle="modal" data-bs-target="#productModal"
                                                data-id="<?= $row['id'] ?>"
                                                data-sku="<?= htmlspecialchars($row['sku']) ?>"
                                                data-product_name="<?= htmlspecialchars($row['product_name']) ?>"
                                                data-standard_qty="<?= htmlspecialchars($row['standard_qty']) ?>" title="Edit">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <a href="index.php?page=daftar_produk&action=delete_produk&id=<?= $row['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus produk ini?');" title="Hapus">
                                                <i class="bi bi-trash3-fill"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                        <?php endforeach;
                        endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="index.php" method="POST" id="productForm">
                <input type="hidden" name="form_type" value="produk">
                <input type="hidden" name="product_id" id="product_id_input">
                <div class="modal-header">
                    <h1 class="modal-title fs-5 fw-bold" id="productModalLabel"></h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3"><label for="product_name" class="form-label">Nama Barang</label><input type="text" class="form-control" id="product_name" name="product_name" required></div>
                    <div class="mb-3"><label for="sku" class="form-label">Kode Barang (SKU)</label><input type="text" class="form-control" id="sku" name="sku"></div>
                    <div class="mb-3"><label for="standard_qty" class="form-label">Standar Qty (Kg)</label><input type="number" step="any" class="form-control" id="standard_qty" name="standard_qty" placeholder="Contoh: 25.5"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary" id="productSubmitButton"></button></div>
            </form>
        </div>
    </div>
</div>