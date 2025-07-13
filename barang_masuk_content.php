<?php
// AMBIL DATA PRODUK UNTUK DROPDOWN
$stmt_products = $pdo->query("SELECT id, sku, product_name, standard_qty FROM products ORDER BY product_name ASC");
$products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);

// Logika untuk menampilkan pesan status
$message = '';
$status_type = '';
if (isset($_GET['status'])) {
    $status_messages = ['sukses_tambah' => 'Data berhasil disimpan.', 'sukses_edit' => 'Data berhasil diperbarui.', 'dihapus' => 'Data berhasil dihapus.'];
    if (array_key_exists($_GET['status'], $status_messages)) {
        $message = $status_messages[$_GET['status']];
        $status_type = $_GET['status'] == 'dihapus' ? 'warning' : 'success';
    }
}

$filter_date = $_GET['filter_date'] ?? '';
$filter_status = $_GET['status_filter'] ?? '';
$search_query = $_GET['s'] ?? '';
$po_query = $_GET['po'] ?? '';
$doc_query = $_GET['doc'] ?? '';
$batch_query = $_GET['batch'] ?? '';

$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : 25;
$page_num = isset($_GET['page_num']) && is_numeric($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$offset = ($page_num - 1) * $limit;

$sql_base = "FROM incoming_transactions t JOIN products p ON t.product_id = p.id WHERE 1=1";
$params = [];

if (!empty($filter_date)) {
    $sql_base .= " AND t.transaction_date = :filter_date";
    $params[':filter_date'] = $filter_date;
}
if (!empty($filter_status)) {
    $sql_base .= " AND t.status = :status_filter";
    $params[':status_filter'] = $filter_status;
}
if (!empty($search_query)) {
    $sql_base .= " AND (p.product_name LIKE :search_name OR p.sku LIKE :search_sku)";
    $params[':search_name'] = '%' . $search_query . '%';
    $params[':search_sku'] = '%' . $search_query . '%';
}
if (!empty($po_query)) {
    $sql_base .= " AND t.po_number LIKE :po_number";
    $params[':po_number'] = '%' . $po_query . '%';
}
if (!empty($doc_query)) {
    $sql_base .= " AND t.document_number LIKE :document_number";
    $params[':document_number'] = '%' . $doc_query . '%';
}
if (!empty($batch_query)) {
    $sql_base .= " AND t.batch_number LIKE :batch_number";
    $params[':batch_number'] = '%' . $batch_query . '%';
}

$sql_count = "SELECT COUNT(t.id) " . $sql_base;
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_rows = $stmt_count->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$sql_transactions = "SELECT t.*, p.product_name, p.sku " . $sql_base . " ORDER BY t.created_at DESC LIMIT :limit OFFSET :offset";
$stmt_transactions = $pdo->prepare($sql_transactions);
foreach ($params as $key => &$val) {
    $stmt_transactions->bindParam($key, $val);
}
$stmt_transactions->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt_transactions->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt_transactions->execute();
$transactions = $stmt_transactions->fetchAll(PDO::FETCH_ASSOC);

$query_params = $_GET;
?>
<div class="container-fluid">
    <?php if ($message): ?>
        <div class="alert alert-<?= htmlspecialchars($status_type) ?> alert-dismissible fade show fade-in" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <strong><?= $message ?></strong>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header bg-gradient-primary text-white"">
            <div class=" d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <div class="icon-circle bg-gradient bg-opacity-20 me-2">
                    <i class="bi bi-box-arrow-in-down"></i>
                </div>
                <div>
                    <h2 class="h5 mb-0 fw-bold text-white">Daftar Barang Masuk</h2>
                    <small class="text-white-50 d-none d-md-block">
                        <?= !empty($filter_date) ? 'Tanggal: ' . date('d F Y', strtotime($filter_date)) : 'Semua Tanggal' ?>
                    </small>
                </div>
            </div>
            <button type="button" class="btn btn-warning btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#incomingTransactionModal">
                <i class="bi bi-plus-circle-fill me-1"></i>Tambah
            </button>
        </div>

        <!-- Compact Filter Form -->
        <form action="index.php" method="GET" class="filter-form">
            <input type="hidden" name="page" value="barang_masuk">
            <div class="row g-2">
                <div class="col-6 col-md-2">
                    <label class="form-label text-white fw-semibold small">Tanggal</label>
                    <input type="date" name="filter_date" class="form-control form-control-sm bg-white border-0 shadow-sm" value="<?= htmlspecialchars($filter_date) ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label text-white fw-semibold small">Status</label>
                    <select name="status_filter" class="form-select form-select-sm bg-white border-0 shadow-sm">
                        <option value="">Semua</option>
                        <option value="Pending" <?= ($filter_status ?? '') == 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="Closed" <?= ($filter_status ?? '') == 'Closed' ? 'selected' : '' ?>>Closed</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label text-white fw-semibold small">Nama/Kode</label>
                    <input type="text" name="s" class="form-control form-control-sm bg-white border-0 shadow-sm" placeholder="Cari..." value="<?= htmlspecialchars($search_query ?? '') ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label text-white fw-semibold small">No. PO</label>
                    <input type="text" name="po" class="form-control form-control-sm bg-white border-0 shadow-sm" placeholder="Cari..." value="<?= htmlspecialchars($po_query ?? '') ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label text-white fw-semibold small">Dokumen</label>
                    <input type="text" name="doc" class="form-control form-control-sm bg-white border-0 shadow-sm" placeholder="Cari..." value="<?= htmlspecialchars($doc_query ?? '') ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label text-white fw-semibold small">Batch</label>
                    <input type="text" name="batch" class="form-control form-control-sm bg-white border-0 shadow-sm" placeholder="Cari..." value="<?= htmlspecialchars($batch_query) ?>">
                </div>
            </div>
            <div class="row mt-2">
                <div class="col-12">
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-success btn-sm fw-semibold shadow-sm">
                            <i class="bi bi-funnel-fill me-1"></i>Filter
                        </button>
                        <a href="export_csv.php?<?= http_build_query($_GET) ?>" class="btn btn-info btn-sm fw-semibold shadow-sm">
                            <i class="bi bi-download me-1"></i>Export
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0">
                <thead class="table-light sticky-top">
                    <tr>
                        <th class="text-nowrap">Waktu Input</th>
                        <th class="text-nowrap">Tgl. Transaksi</th>
                        <th class="text-nowrap">Nomor PO</th>
                        <th class="text-start text-nowrap">Supplier</th>
                        <th class="text-nowrap">No. Polisi</th>
                        <th class="text-start text-nowrap">Nama Barang</th>
                        <th class="text-nowrap">Kode Barang</th>
                        <th class="text-nowrap">Qty (Kg)</th>
                        <th class="text-nowrap">Qty (Sak)</th>
                        <th class="text-nowrap">No. Dokumen</th>
                        <th class="text-nowrap">501 (Lot)</th>
                        <th class="text-nowrap">Batch</th>
                        <th class="text-nowrap">Status</th>
                        <th class="text-center text-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="14" class="text-center text-muted p-5">
                                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                <span>Tidak ada data transaksi</span>
                            </td>
                        </tr>
                        <?php else: foreach ($transactions as $tx): ?>
                            <tr>
                                <td class="text-nowrap small"><?= date('d/m/y H:i', strtotime($tx['created_at'])) ?></td>
                                <td class="text-nowrap"><?= date('d/m/Y', strtotime($tx['transaction_date'])) ?></td>
                                <td class="text-truncate" style="max-width: 100px;"><?= htmlspecialchars($tx['po_number']) ?></td>
                                <td class="text-start text-truncate" style="max-width: 120px;"><?= htmlspecialchars($tx['supplier']) ?></td>
                                <td class="text-nowrap"><?= htmlspecialchars($tx['license_plate']) ?></td>
                                <td class="text-start text-truncate" style="max-width: 150px;"><?= htmlspecialchars($tx['product_name']) ?></td>
                                <td class="text-nowrap"><?= htmlspecialchars($tx['sku']) ?></td>
                                <td class="text-nowrap fw-semibold"><?= formatAngka($tx['quantity_kg']) ?></td>
                                <td class="text-nowrap"><?= formatAngka($tx['quantity_sacks']) ?></td>
                                <td class="text-truncate" style="max-width: 100px;"><?= htmlspecialchars($tx['document_number']) ?></td>
                                <td class="text-nowrap"><?= formatAngka($tx['lot_number']) ?></td>
                                <td class="text-truncate" style="max-width: 100px;"><?= htmlspecialchars($tx['batch_number']) ?></td>
                                <td>
                                    <span class="badge <?= $tx['status'] == 'Closed' ? 'bg-success' : 'bg-warning text-dark' ?> rounded-pill">
                                        <?= htmlspecialchars($tx['status']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-warning edit-btn"
                                            data-bs-toggle="modal"
                                            data-bs-target="#incomingTransactionModal"
                                            data-id="<?= $tx['id'] ?>"
                                            data-product_id="<?= $tx['product_id'] ?>"
                                            data-product_name="<?= htmlspecialchars($tx['product_name']) ?>"
                                            data-po_number="<?= htmlspecialchars($tx['po_number']) ?>"
                                            data-supplier="<?= htmlspecialchars($tx['supplier']) ?>"
                                            data-produsen="<?= htmlspecialchars($tx['produsen']) ?>"
                                            data-license_plate="<?= htmlspecialchars($tx['license_plate']) ?>"
                                            data-quantity_kg="<?= htmlspecialchars($tx['quantity_kg']) ?>"
                                            data-quantity_sacks="<?= htmlspecialchars($tx['quantity_sacks']) ?>"
                                            data-document_number="<?= htmlspecialchars($tx['document_number']) ?>"
                                            data-batch_number="<?= htmlspecialchars($tx['batch_number']) ?>"
                                            data-lot_number="<?= htmlspecialchars($tx['lot_number']) ?>"
                                            data-transaction_date="<?= htmlspecialchars($tx['transaction_date']) ?>"
                                            data-status="<?= htmlspecialchars($tx['status']) ?>"
                                            title="Edit">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <a href="/barang_masuk?action=delete_incoming&id=<?= $tx['id'] ?>"
                                            class="btn btn-outline-danger"
                                            onclick="return confirm('Yakin ingin menghapus data ini?')"
                                            title="Hapus">
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

        <!-- Pagination -->
        <div class="d-flex justify-content-between align-items-center p-3 border-top">
            <form action="index.php" method="GET" class="d-flex align-items-center gap-2">
                <input type="hidden" name="page" value="barang_masuk">
                <?php
                foreach ($query_params as $key => $value) {
                    if ($key != 'limit' && $key != 'page_num') {
                        echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
                    }
                }
                ?>
                <label for="limit" class="form-label small text-nowrap mb-0">Baris:</label>
                <select name="limit" id="limit" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                    <option value="25" <?= ($limit == 25 ? 'selected' : '') ?>>25</option>
                    <option value="50" <?= ($limit == 50 ? 'selected' : '') ?>>50</option>
                    <option value="100" <?= ($limit == 100 ? 'selected' : '') ?>>100</option>
                </select>
            </form>

            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination pagination-sm mb-0">
                        <?php
                        unset($query_params['page_num']);
                        $prev_page = $page_num - 1;
                        $link_params = $query_params;
                        $link_params['page_num'] = $prev_page;
                        echo '<li class="page-item ' . ($page_num <= 1 ? 'disabled' : '') . '"><a class="page-link" href="?' . http_build_query($link_params) . '">‹</a></li>';

                        $start = max(1, $page_num - 2);
                        $end = min($total_pages, $page_num + 2);

                        for ($i = $start; $i <= $end; $i++) {
                            $link_params['page_num'] = $i;
                            $active_class = ($i == $page_num) ? 'active' : '';
                            echo '<li class="page-item ' . $active_class . '"><a class="page-link" href="?' . http_build_query($link_params) . '">' . $i . '</a></li>';
                        }

                        $next_page = $page_num + 1;
                        $link_params['page_num'] = $next_page;
                        echo '<li class="page-item ' . ($page_num >= $total_pages ? 'disabled' : '') . '"><a class="page-link" href="?' . http_build_query($link_params) . '">›</a></li>';
                        ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>

<!-- Enhanced Modal for Incoming Transaction -->
<div class="modal fade" id="incomingTransactionModal" tabindex="-1" aria-labelledby="incomingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form action="index.php" method="POST" id="incomingTransactionForm">
                <input type="hidden" name="form_type" value="barang_masuk">
                <input type="hidden" name="transaction_id" id="incoming_transaction_id">
                <input type="hidden" name="product_id" id="incoming_product_id_hidden">

                <div class="modal-header bg-gradient-warning text-dark border-1">
                    <div class="d-flex align-items-center">
                        <div class="icon-circle bg-white bg-opacity-20 me-3">
                            <i class="bi bi-plus-circle-fill fs-4"></i>
                        </div>
                        <div>
                            <h1 class="modal-title fs-5 fw-bold mb-0" id="incomingModalLabel">Tambah Transaksi Barang Masuk</h1>
                            <small class="opacity-75">Formulir input transaksi penerimaan barang</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                    <!-- Header Info -->
                    <div class="card bg-light border-0 mb-4">
                        <div class="card-body">
                            <h6 class="card-title fw-bold text-primary mb-3">
                                <i class="bi bi-info-circle me-2"></i>Informasi Transaksi
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-calendar3 me-1 text-primary"></i>Tanggal Transaksi
                                    </label>
                                    <input type="date" class="form-control border-0 shadow-sm" id="incoming_transaction_date" name="transaction_date" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-receipt me-1 text-primary"></i>Nomor PO
                                    </label>
                                    <input type="text" class="form-control border-0 shadow-sm" id="incoming_po_number" name="po_number" placeholder="Masukkan nomor PO">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-flag me-1 text-primary"></i>Status Transaksi
                                    </label>
                                    <select class="form-select border-0 shadow-sm" id="incoming_status" name="status" required>
                                        <option value="Pending">🟡 Pending</option>
                                        <option value="Closed" selected>🟢 Closed</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Product Information -->
                    <div class="card border-success mb-4">
                        <div class="card-header bg-success text-white">
                            <h6 class="card-title fw-bold mb-0">
                                <i class="bi bi-box me-2"></i>Informasi Produk
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-search me-1 text-primary"></i>Nama Barang
                                    </label>
                                    <input class="form-control border-0 shadow-sm" list="datalistProducts" id="incoming_product_name" name="product_name" placeholder="🔍 Ketik untuk mencari produk..." required>
                                    <datalist id="datalistProducts">
                                        <?php foreach ($products as $p): ?>
                                            <option value="<?= htmlspecialchars($p['product_name']) ?>" data-id="<?= $p['id'] ?>" data-sku="<?= htmlspecialchars($p['sku']) ?>" data-stdqty="<?= htmlspecialchars($p['standard_qty']) ?>">
                                            <?php endforeach; ?>
                                    </datalist>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-building me-1 text-primary"></i>Supplier
                                    </label>
                                    <input type="text" class="form-control border-0 shadow-sm" id="incoming_supplier" name="supplier" placeholder="Nama supplier">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-factory me-1 text-primary"></i>Produsen
                                    </label>
                                    <input type="text" class="form-control border-0 shadow-sm" id="incoming_produsen" name="produsen" placeholder="Nama produsen">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quantity Information -->
                    <div class="card border-warning mb-4">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="card-title fw-bold mb-0">
                                <i class="bi bi-calculator me-2"></i>Informasi Kuantitas & Timbangan
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-weight me-1 text-primary"></i>Qty/Net (Kg)
                                    </label>
                                    <div class="input-group shadow-sm">
                                        <div class="input-group-text bg-light border-0">
                                            <input class="form-check-input mt-0" type="checkbox" id="incoming_calc_kg_check" title="Auto-hitung dari Qty Sak">
                                        </div>
                                        <input type="number" step="any" class="form-control border-0" id="incoming_quantity_kg" name="quantity_kg" placeholder="0.00" required>
                                    </div>
                                    <small class="form-text text-muted">Centang untuk auto-hitung</small>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-stack me-1 text-primary"></i>Qty Sak
                                    </label>
                                    <div class="input-group shadow-sm">
                                        <div class="input-group-text bg-light border-0">
                                            <input class="form-check-input mt-0" type="checkbox" id="incoming_calc_sak_check" title="Auto-hitung dari Qty Kg">
                                        </div>
                                        <input type="number" step="any" class="form-control border-0" id="incoming_quantity_sacks" name="quantity_sacks" placeholder="0.00">
                                    </div>
                                    <small class="form-text text-muted">Centang untuk auto-hitung</small>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-speedometer2 me-1 text-primary"></i>Gross Weight
                                    </label>
                                    <input type="number" step="any" class="form-control border-0 shadow-sm" id="incoming_gross_weight" name="gross_weight" placeholder="0.00">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-calculator me-1 text-success"></i>Selisih/Tare (501)
                                    </label>
                                    <input type="number" class="form-control bg-light border-0 shadow-sm fw-bold text-success" id="incoming_lot_number_display" readonly placeholder="Auto calculate">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Document Information -->
                    <div class="card border-info">
                        <div class="card-header bg-info text-white">
                            <h6 class="card-title fw-bold mb-0">
                                <i class="bi bi-file-text me-2"></i>Informasi Dokumen & Pengiriman
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-file-text me-1 text-primary"></i>No. Dokumen
                                    </label>
                                    <input type="text" class="form-control border-0 shadow-sm" id="incoming_document_number" name="document_number" placeholder="Nomor dokumen">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-tag me-1 text-primary"></i>Batch Number
                                    </label>
                                    <input type="text" class="form-control border-0 shadow-sm" id="incoming_batch_number" name="batch_number" placeholder="Nomor batch">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-truck me-1 text-primary"></i>No. Polisi
                                    </label>
                                    <input type="text" class="form-control border-0 shadow-sm" id="incoming_license_plate" name="license_plate" placeholder="B 1234 ABC">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary fw-semibold" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Batal
                    </button>
                    <button type="submit" class="btn btn-primary fw-semibold shadow-sm" id="incomingSubmitButton">
                        <i class="bi bi-save-fill me-1"></i>Simpan Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const incomingCalcKgCheck = document.getElementById('incoming_calc_kg_check');
        const incomingCalcSakCheck = document.getElementById('incoming_calc_sak_check');
        const incomingQuantityKg = document.getElementById('incoming_quantity_kg');
        const incomingQuantitySacks = document.getElementById('incoming_quantity_sacks');

        function updateReadonlyState() {
            if (incomingCalcKgCheck.checked) {
                incomingQuantityKg.readOnly = true;
                incomingQuantityKg.classList.add('bg-light');
                incomingQuantitySacks.readOnly = false;
                incomingQuantitySacks.classList.remove('bg-light');
            } else if (incomingCalcSakCheck.checked) {
                incomingQuantitySacks.readOnly = true;
                incomingQuantitySacks.classList.add('bg-light');
                incomingQuantityKg.readOnly = false;
                incomingQuantityKg.classList.remove('bg-light');
            } else {
                incomingQuantityKg.readOnly = false;
                incomingQuantitySacks.readOnly = false;
                incomingQuantityKg.classList.remove('bg-light');
                incomingQuantitySacks.classList.remove('bg-light');
            }
        }

        if (incomingCalcKgCheck && incomingCalcSakCheck && incomingQuantityKg && incomingQuantitySacks) {
            incomingCalcKgCheck.addEventListener('change', function() {
                if (this.checked) {
                    incomingCalcSakCheck.checked = false;
                    incomingQuantityKg.value = '';
                }
                updateReadonlyState();
            });

            incomingCalcSakCheck.addEventListener('change', function() {
                if (this.checked) {
                    incomingCalcKgCheck.checked = false;
                    incomingQuantitySacks.value = '';
                }
                updateReadonlyState();
            });

            // Initialize readonly state on page load
            updateReadonlyState();
        }
    });
</script>