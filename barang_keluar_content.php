<?php
// AMBIL DATA PRODUK UNTUK DROPDOWN
$stmt_products = $pdo->query("SELECT id, sku, product_name, standard_qty FROM products ORDER BY product_name ASC");
$products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);

// Logika untuk menampilkan pesan status
$message = '';
$status_type = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] == 'sukses_parsial' || $_GET['status'] == 'sukses_parsial_501') {
        $dikeluarkan = formatAngka($_GET['dikeluarkan'] ?? 0);
        $kurang = formatAngka($_GET['kurang'] ?? 0);
        $pesan_item = ($_GET['status'] == 'sukses_parsial_501') ? "dari sisa 501" : "";
        $message = "Hanya <strong>{$dikeluarkan} Kg</strong> {$pesan_item} yang berhasil dikeluarkan. Kekurangan <strong>{$kurang} Kg</strong>.";
        $status_type = 'info';
    } elseif ($_GET['status'] == 'gagal_edit_stok') {
        $sisa = formatAngka($_GET['sisa'] ?? 0);
        $message = "Gagal! Stok tidak cukup. Sisa stok maksimum untuk transaksi ini adalah <strong>{$sisa} Kg</strong>.";
        $status_type = 'danger';
    } elseif ($_GET['status'] == 'gagal_501_stok') {
        $sisa = formatAngka($_GET['sisa'] ?? 0);
        $message = "Gagal! Jumlah 501 yang dikeluarkan melebihi sisa. Sisa 501: <strong>{$sisa} Kg</strong>.";
        $status_type = 'danger';
    } else {
        $status_messages = [
            'sukses_tambah' => 'Data transaksi barang keluar berhasil disimpan.',
            'sukses_edit' => 'Data transaksi berhasil diperbarui.',
            'dihapus' => 'Data berhasil dihapus.',
            'stok_habis' => 'Gagal! Stok atau sisa 501 untuk batch yang dipilih sudah habis.',
            'sukses_501' => 'Sisa 501 berhasil dikeluarkan.'
        ];
        if (array_key_exists($_GET['status'], $status_messages)) {
            $message = $status_messages[$_GET['status']];
            $status_type = 'success';
            if (in_array($_GET['status'], ['dihapus'])) {
                $status_type = 'warning';
            } elseif ($_GET['status'] == 'stok_habis') {
                $status_type = 'danger';
            }
        }
    }
}

// Logika untuk filter dan menampilkan data transaksi
$filter_date = $_GET['filter_date'] ?? '';
$search_query = $_GET['s'] ?? '';
$doc_query = $_GET['doc'] ?? '';
$filter_status = $_GET['status_filter'] ?? '';
$batch_query = $_GET['batch'] ?? '';

// --- LOGIKA PAGINASI BARU ---
$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int)$_GET['limit'] : 25;
$page_num = isset($_GET['page_num']) && is_numeric($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$offset = ($page_num - 1) * $limit;

// Membangun query SQL dasar dengan filter
$sql_base = "FROM outgoing_transactions t JOIN products p ON t.product_id = p.id WHERE 1=1";
$params = [];

if (!empty($filter_date)) {
    $sql_base .= " AND t.transaction_date = :filter_date";
    $params[':filter_date'] = $filter_date;
}
if (!empty($search_query)) {
    $sql_base .= " AND (p.product_name LIKE :search_name OR p.sku LIKE :search_sku)";
    $params[':search_name'] = '%' . $search_query . '%';
    $params[':search_sku'] = '%' . $search_query . '%';
}
if (!empty($doc_query)) {
    $sql_base .= " AND t.document_number LIKE :document_number";
    $params[':document_number'] = '%' . $doc_query . '%';
}
if (!empty($filter_status)) {
    $sql_base .= " AND t.status = :status_filter";
    $params[':status_filter'] = $filter_status;
}
if (!empty($batch_query)) {
    $sql_base .= " AND t.batch_number LIKE :batch_number";
    $params[':batch_number'] = '%' . $batch_query . '%';
}

// Query untuk menghitung total baris
$sql_count = "SELECT COUNT(t.id) " . $sql_base;
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_rows = $stmt_count->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Query untuk mengambil data transaksi dengan paginasi
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
        <div class="alert alert-<?= htmlspecialchars($status_type) ?> alert-dismissible fade show fade-in shadow-sm" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-<?= $status_type == 'success' ? 'check-circle-fill' : ($status_type == 'danger' ? 'exclamation-triangle-fill' : 'info-circle-fill') ?> me-3 fs-4"></i>
                <div class="flex-grow-1"><?= $message ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    <?php endif; ?>

    <div class="card shadow-lg border-0 overflow-hidden">
        <div class="card-header bg-gradient-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <div class="icon-circle bg-gradient bg-opacity-20 me-2">
                        <i class="bi bi-box-arrow-up"></i>
                    </div>
                    <div>
                        <h2 class="h5 mb-0 fw-bold">Manajemen Barang Keluar</h2>
                        <small class="opacity-75 d-none d-md-block">Kelola transaksi pengeluaran barang</small>
                    </div>
                </div>
                <div class="d-flex gap-1">
                    <button type="button" class="btn btn-light btn-sm text-primary fw-semibold" data-bs-toggle="modal" data-bs-target="#keluarkan501Modal">
                        <i class="bi bi-box-arrow-up me-1"></i>501
                    </button>
                    <button type="button" class="btn btn-warning btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#outgoingTransactionModal">
                        <i class="bi bi-plus-circle-fill me-1"></i>Tambah
                    </button>
                </div>
            </div>

            <!-- Compact Filter Form -->
            <form action="index.php" method="GET" class="filter-form">
                <input type="hidden" name="page" value="barang_keluar">
                <div class="row g-2">
                    <div class="col-6 col-md-2">
                        <label class="form-label text-white fw-semibold small">Tanggal</label>
                        <input type="date" name="filter_date" class="form-control form-control-sm bg-white border-0 shadow-sm" value="<?= htmlspecialchars($filter_date) ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label text-white fw-semibold small">Status</label>
                        <select name="status_filter" class="form-select form-select-sm bg-white border-0 shadow-sm">
                            <option value="">Semua</option>
                            <option value="Pending" <?= ($filter_status ?? '') == 'Pending' ? 'selected' : '' ?>>🟡 Pending</option>
                            <option value="Closed" <?= ($filter_status ?? '') == 'Closed' ? 'selected' : '' ?>>🟢 Closed</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label text-white fw-semibold small">Nama/Kode</label>
                        <input type="text" name="s" class="form-control form-control-sm bg-white border-0 shadow-sm" placeholder="Cari..." value="<?= htmlspecialchars($search_query ?? '') ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label text-white fw-semibold small">Dokumen</label>
                        <input type="text" name="doc" class="form-control form-control-sm bg-white border-0 shadow-sm" placeholder="Cari..." value="<?= htmlspecialchars($doc_query ?? '') ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label text-white fw-semibold small">Batch</label>
                        <input type="text" name="batch" class="form-control form-control-sm bg-white border-0 shadow-sm" placeholder="Cari..." value="<?= htmlspecialchars($batch_query) ?>">
                    </div>
                    <div class="row mt-2">
                        <div class="col-12">
                            <div class="d-flex gap-2 flex-wrap">
                                <button type="submit" class="btn btn-success btn-sm fw-semibold shadow-sm">
                                    <i class="bi bi-funnel-fill me-1"></i>Filter
                                </button>
                                <a href="export_laporan_keluar.php?<?= http_build_query($_GET) ?>" class="btn btn-info btn-sm fw-semibold shadow-sm">
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
                    <thead class="table-dark sticky-top">
                        <tr>
                            <th class="text-nowrap fw-bold">Tanggal</th>
                            <th class="text-start text-nowrap fw-bold">Nama Barang</th>
                            <th class="text-nowrap fw-bold">Kode</th>
                            <th class="text-nowrap fw-bold">Qty (Kg)</th>
                            <th class="text-nowrap fw-bold">Qty (Sak)</th>
                            <th class="text-nowrap fw-bold">No. Dokumen</th>
                            <th class="text-nowrap fw-bold">501 (Lot)</th>
                            <th class="text-nowrap fw-bold">Batch</th>
                            <th class="text-nowrap fw-bold">Status</th>
                            <th class="text-center text-nowrap fw-bold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted p-5">
                                    <div class="empty-state">
                                        <i class="bi bi-inbox display-1 text-muted opacity-50"></i>
                                        <h5 class="mt-3 text-muted">Belum Ada Data Transaksi</h5>
                                        <p class="text-muted">Mulai tambahkan transaksi barang keluar untuk melihat data di sini</p>
                                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#outgoingTransactionModal">
                                            <i class="bi bi-plus-circle me-1"></i>Tambah Transaksi Pertama
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php else: foreach ($transactions as $tx): ?>
                                <tr class="transaction-row">
                                    <td class="text-nowrap">
                                        <span class="badge bg-light text-dark border">
                                            <?= date('d/m/Y', strtotime($tx['transaction_date'])) ?>
                                        </span>
                                    </td>
                                    <td class="text-start">
                                        <div class="product-info">
                                            <div class="fw-semibold text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($tx['product_name']) ?>">
                                                <?= htmlspecialchars($tx['product_name']) ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-nowrap">
                                        <code class="bg-light px-2 py-1 rounded"><?= htmlspecialchars($tx['sku']) ?></code>
                                    </td>
                                    <td class="text-nowrap">
                                        <span class="badge bg-primary fs-6"><?= formatAngka($tx['quantity_kg']) ?></span>
                                    </td>
                                    <td class="text-nowrap">
                                        <span class="badge bg-secondary fs-6"><?= formatAngka($tx['quantity_sacks']) ?></span>
                                    </td>
                                    <td class="text-truncate" style="max-width: 120px;">
                                        <span class="text-primary fw-semibold" title="<?= htmlspecialchars($tx['document_number']) ?>">
                                            <?= htmlspecialchars($tx['document_number']) ?>
                                        </span>
                                    </td>
                                    <td class="text-nowrap">
                                        <span class="badge bg-warning text-dark fs-6"><?= formatAngka($tx['lot_number']) ?></span>
                                    </td>
                                    <td class="text-truncate" style="max-width: 100px;">
                                        <span class="badge bg-info text-white" title="<?= htmlspecialchars($tx['batch_number']) ?>">
                                            <?= htmlspecialchars($tx['batch_number']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($tx['status'] == 'Closed'): ?>
                                            <span class="badge bg-success rounded-pill px-3">
                                                <i class="bi bi-check-circle me-1"></i>Closed
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark rounded-pill px-3">
                                                <i class="bi bi-clock me-1"></i>Pending
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-outline-warning edit-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#outgoingTransactionModal"
                                                data-doc-number="<?= htmlspecialchars($tx['document_number']) ?>"
                                                title="Edit Transaksi">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <a href="/barang_keluar?action=delete_outgoing&id=<?= $tx['id'] ?>"
                                                class="btn btn-outline-danger"
                                                onclick="return confirm('⚠️ Yakin ingin menghapus item ini?\n\nData yang dihapus tidak dapat dikembalikan!')"
                                                title="Hapus Item">
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

            <!-- Enhanced Pagination -->
            <div class="d-flex justify-content-between align-items-center p-3 bg-light border-top">
                <div class="d-flex align-items-center gap-3">
                    <form action="index.php" method="GET" class="d-flex align-items-center gap-2">
                        <input type="hidden" name="page" value="barang_keluar">
                        <?php
                        foreach ($query_params as $key => $value) {
                            if ($key != 'limit' && $key != 'page_num') {
                                echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
                            }
                        }
                        ?>
                        <label for="limit" class="form-label small text-nowrap mb-0 fw-semibold">Tampilkan:</label>
                        <select name="limit" id="limit" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                            <option value="25" <?= ($limit == 25 ? 'selected' : '') ?>>25 baris</option>
                            <option value="50" <?= ($limit == 50 ? 'selected' : '') ?>>50 baris</option>
                            <option value="100" <?= ($limit == 100 ? 'selected' : '') ?>>100 baris</option>
                        </select>
                    </form>

                    <div class="text-muted small">
                        <i class="bi bi-info-circle me-1"></i>
                        Menampilkan <?= min($offset + 1, $total_rows) ?>-<?= min($offset + $limit, $total_rows) ?> dari <?= $total_rows ?> data
                    </div>
                </div>

                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Navigasi Halaman">
                        <ul class="pagination pagination-sm mb-0">
                            <?php
                            unset($query_params['page_num']);
                            $prev_page = $page_num - 1;
                            $link_params = $query_params;
                            $link_params['page_num'] = $prev_page;
                            ?>
                            <li class="page-item <?= ($page_num <= 1 ? 'disabled' : '') ?>">
                                <a class="page-link" href="?<?= http_build_query($link_params) ?>" title="Halaman Sebelumnya">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>

                            <?php
                            $start = max(1, $page_num - 2);
                            $end = min($total_pages, $page_num + 2);

                            if ($start > 1) {
                                $link_params['page_num'] = 1;
                                echo '<li class="page-item"><a class="page-link" href="?' . http_build_query($link_params) . '">1</a></li>';
                                if ($start > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }

                            for ($i = $start; $i <= $end; $i++) {
                                $link_params['page_num'] = $i;
                                $active_class = ($i == $page_num) ? 'active' : '';
                                echo '<li class="page-item ' . $active_class . '"><a class="page-link" href="?' . http_build_query($link_params) . '">' . $i . '</a></li>';
                            }

                            if ($end < $total_pages) {
                                if ($end < $total_pages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                $link_params['page_num'] = $total_pages;
                                echo '<li class="page-item"><a class="page-link" href="?' . http_build_query($link_params) . '">' . $total_pages . '</a></li>';
                            }

                            $next_page = $page_num + 1;
                            $link_params['page_num'] = $next_page;
                            ?>
                            <li class="page-item <?= ($page_num >= $total_pages ? 'disabled' : '') ?>">
                                <a class="page-link" href="?<?= http_build_query($link_params) ?>" title="Halaman Selanjutnya">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced Modal for Outgoing Transaction -->
<div class="modal fade" id="outgoingTransactionModal" tabindex="-1" aria-labelledby="outgoingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form action="index.php" method="POST" id="outgoingTransactionForm">
                <input type="hidden" name="form_type" value="barang_keluar">
                <input type="hidden" name="items_json" id="items_json">
                <input type="hidden" name="original_document_number" id="original_document_number">

                <div class="modal-header bg-gradient-warning text-dark border-0">
                    <div class="d-flex align-items-center">
                        <div class="icon-circle bg-white bg-opacity-20 me-3">
                            <i class="bi bi-plus-circle-fill fs-4"></i>
                        </div>
                        <div>
                            <h1 class="modal-title fs-5 fw-bold mb-0" id="outgoingModalLabel">Tambah Transaksi Barang Keluar</h1>
                            <small class="opacity-75">Formulir input transaksi pengeluaran barang</small>
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
                                    <input type="date" class="form-control border-0 shadow-sm" name="transaction_date" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-file-text me-1 text-primary"></i>No. Dokumen
                                    </label>
                                    <input type="text" class="form-control border-0 shadow-sm" name="document_number" placeholder="Kosongkan untuk nomor otomatis">
                                    <small class="form-text text-muted">Biarkan kosong untuk generate otomatis</small>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-flag me-1 text-primary"></i>Status Transaksi
                                    </label>
                                    <select class="form-select border-0 shadow-sm" name="status" required>
                                        <option value="Pending">🟡 Pending</option>
                                        <option value="Closed" selected>🟢 Closed</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-chat-text me-1 text-primary"></i>Keterangan
                                    </label>
                                    <textarea class="form-control border-0 shadow-sm" name="description" rows="2" placeholder="Tambahkan keterangan transaksi (opsional)"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Add Item Form -->
                    <div class="card border-primary mb-4">
                        <div class="card-header bg-primary text-white">
                            <h6 class="card-title fw-bold mb-0">
                                <i class="bi bi-plus-circle me-2"></i>Tambah Item Barang
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-search me-1 text-primary"></i>Nama Barang
                                    </label>
                                    <input class="form-control border-0 shadow-sm" list="datalistProductsOutgoing" id="item_product_name_outgoing" placeholder="🔍 Ketik untuk mencari produk...">
                                    <datalist id="datalistProductsOutgoing">
                                        <?php foreach ($products as $p): ?>
                                            <option value="<?= htmlspecialchars($p['product_name']) ?>" data-id="<?= $p['id'] ?>" data-sku="<?= htmlspecialchars($p['sku']) ?>" data-stdqty="<?= htmlspecialchars($p['standard_qty']) ?>">
                                            <?php endforeach; ?>
                                    </datalist>
                                    <input type="hidden" id="item_product_id_hidden">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-tag me-1 text-primary"></i>Batch Masuk (Sisa Stok)
                                    </label>
                                    <select class="form-select border-0 shadow-sm" id="item_incoming_id" disabled>
                                        <option value="">-- Pilih Barang Terlebih Dahulu --</option>
                                    </select>
                                    <small class="form-text text-muted">Pilih batch dengan stok tersedia</small>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-weight me-1 text-primary"></i>Qty Keluar (Kg)
                                    </label>
                                    <div class="input-group shadow-sm">
                                        <div class="input-group-text bg-light border-0">
                                            <input class="form-check-input mt-0" type="checkbox" id="outgoing_calc_kg_check" title="Auto-hitung dari Qty Sak">
                                        </div>
                                        <input type="number" step="any" class="form-control border-0" id="item_quantity_kg" placeholder="0.00">
                                    </div>
                                    <small class="form-text text-muted">Centang untuk auto-hitung</small>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">
                                        <i class="bi bi-bag me-1 text-primary"></i>Qty Keluar (Sak)
                                    </label>
                                    <div class="input-group shadow-sm">
                                        <div class="input-group-text bg-light border-0">
                                            <input class="form-check-input mt-0" type="checkbox" id="outgoing_calc_sak_check" title="Auto-hitung dari Qty Kg">
                                        </div>
                                        <input type="number" step="any" class="form-control border-0" id="item_quantity_sacks" placeholder="0">
                                    </div>
                                    <small class="form-text text-muted">Centang untuk auto-hitung</small>
                                </div>
                                <div class="col-md-6 d-flex align-items-end">
                                    <button type="button" class="btn btn-success w-100 fw-semibold shadow-sm" id="addItemBtn">
                                        <i class="bi bi-plus-lg me-2"></i>Tambahkan ke Daftar Item
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Items List -->
                    <div class="card border-success">
                        <div class="card-header bg-success text-white">
                            <h6 class="card-title fw-bold mb-0">
                                <i class="bi bi-list-ul me-2"></i>Daftar Barang yang Akan Dikeluarkan
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 5%;" class="fw-bold">#</th>
                                            <th class="text-start fw-bold">Nama Barang</th>
                                            <th class="fw-bold">Batch</th>
                                            <th class="fw-bold">Qty (Kg)</th>
                                            <th class="fw-bold">Qty (Sak)</th>
                                            <th style="width: 10%;" class="text-center fw-bold">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="outgoing_items_list">
                                        <tr>
                                            <td colspan="6" class="text-center text-muted p-4">
                                                <i class="bi bi-inbox display-6 d-block mb-2 opacity-50"></i>
                                                <span>Belum ada item yang ditambahkan</span>
                                                <br>
                                                <small>Gunakan form di atas untuk menambah item</small>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary fw-semibold" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Batal
                    </button>
                    <button type="submit" class="btn btn-primary fw-semibold shadow-sm" id="saveTransactionBtn">
                        <i class="bi bi-save-fill me-1"></i>Simpan Transaksi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Enhanced Modal for 501 -->
<div class="modal fade" id="keluarkan501Modal" tabindex="-1" aria-labelledby="keluarkan501ModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form action="index.php" method="POST" id="formKeluarkan501">
                <input type="hidden" name="form_type" value="keluarkan_501">

                <div class="modal-header bg-gradient-warning text-dark border-0">
                    <div class="d-flex align-items-center">
                        <div class="icon-circle bg-white bg-opacity-20 me-3">
                            <i class="bi bi-box-arrow-up fs-4"></i>
                        </div>
                        <div>
                            <h1 class="modal-title fs-5 fw-bold mb-0" id="keluarkan501ModalLabel">Pengeluaran Sisa 501</h1>
                            <small class="opacity-75">Kelola sisa timbangan dari batch tertentu</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="alert alert-info border-0 shadow-sm">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-info-circle fs-4 me-3"></i>
                            <div>
                                <strong>Informasi:</strong> Fitur ini digunakan untuk mengeluarkan sisa timbangan (501) dari batch tertentu tanpa mengurangi stok utama.
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-calendar3 me-1 text-primary"></i>Tanggal Transaksi
                            </label>
                            <input type="date" class="form-control border-0 shadow-sm" id="keluar501_transaction_date" name="transaction_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-file-text me-1 text-primary"></i>No. Dokumen
                            </label>
                            <input type="text" class="form-control border-0 shadow-sm" id="keluar501_document_number" name="document_number" placeholder="Nomor dokumen" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-box me-1 text-primary"></i>1. Pilih Produk
                            </label>
                            <select class="form-select border-0 shadow-sm" id="product_id_501" name="product_id" required>
                                <option value="">🔍 -- Pilih Produk --</option>
                                <?php foreach ($products as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['product_name']) ?> (<?= htmlspecialchars($p['sku']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-tag me-1 text-primary"></i>2. Pilih Batch dengan Sisa 501
                            </label>
                            <select class="form-select border-0 shadow-sm" id="batch_id_501" name="incoming_transaction_id" required disabled>
                                <option value="">-- Pilih produk terlebih dahulu --</option>
                            </select>
                            <small class="form-text text-muted">
                                <i class="bi bi-info-circle me-1"></i>Hanya batch dengan sisa 501 > 0 yang akan ditampilkan
                            </small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-calculator me-1 text-success"></i>Sisa 501 Tersedia
                            </label>
                            <div class="input-group">
                                <input type="text" class="form-control bg-light border-0 shadow-sm fw-bold text-success" id="keluar501_sisa_display" readonly placeholder="0.00">
                                <span class="input-group-text bg-light border-0">Kg</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-box-arrow-up me-1 text-danger"></i>3. Jumlah yang Dikeluarkan
                            </label>
                            <div class="input-group">
                                <input type="number" step="any" class="form-control border-0 shadow-sm" id="quantity_501" name="quantity_501" placeholder="0.00" required>
                                <span class="input-group-text bg-light border-0">Kg</span>
                            </div>
                            <small class="form-text text-muted">
                                <i class="bi bi-info-circle me-1"></i>Masukkan jumlah 501 yang akan dikeluarkan
                            </small>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-flag me-1 text-primary"></i>Status Transaksi
                            </label>
                            <select class="form-select border-0 shadow-sm" id="keluar501_status" name="status" required>
                                <option value="Pending">🟡 Pending</option>
                                <option value="Closed" selected>🟢 Closed</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-chat-text me-1 text-primary"></i>Keterangan
                            </label>
                            <textarea class="form-control border-0 shadow-sm" name="description" rows="2" placeholder="Pengeluaran Sisa 501">Pengeluaran Sisa 501</textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary fw-semibold" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Batal
                    </button>
                    <button type="submit" class="btn btn-warning fw-semibold shadow-sm text-dark" id="keluar501SubmitButton">
                        <i class="bi bi-save-fill me-1"></i>Keluarkan Sisa 501
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const productSelect501 = document.getElementById("product_id_501");
        const batchSelect501 = document.getElementById("batch_id_501");
        const quantityInput501 = document.getElementById("quantity_501");

        if (productSelect501) {
            productSelect501.addEventListener("change", function() {
                const productId = this.value;
                batchSelect501.innerHTML = '<option value="">Memuat batch...</option>';
                batchSelect501.disabled = true;
                quantityInput501.value = "";

                if (!productId) {
                    batchSelect501.innerHTML = '<option value="">-- Pilih Nama Barang di atas --</option>';
                    batchSelect501.disabled = false;
                    return;
                }

                fetch(`api_get_batches_501.php?product_id=${productId}`)
                    .then((response) => response.json())
                    .then((data) => {
                        batchSelect501.innerHTML = '<option value="" selected disabled>-- Pilih Batch --</option>';
                        if (data && data.length > 0) {
                            data.forEach((batch) => {
                                const sisa_501 = parseFloat(batch.sisa_lot_number || batch.remaining_501 || 0);
                                const optionText = `Tgl: ${batch.transaction_date} - Batch: ${batch.batch_number || "N/A"} (Sisa 501: ${sisa_501.toFixed(2)} Kg)`;
                                const option = document.createElement('option');
                                option.value = batch.id;
                                option.textContent = optionText;
                                option.dataset.sisa = sisa_501;
                                option.dataset.remaining501 = sisa_501;
                                batchSelect501.appendChild(option);
                            });
                        } else {
                            batchSelect501.innerHTML = '<option value="">-- Tidak ada batch dengan sisa 501 --</option>';
                        }
                        batchSelect501.disabled = false;
                    })
                    .catch((error) => {
                        console.error('Error loading batches:', error);
                        batchSelect501.innerHTML = '<option value="">Error loading batches</option>';
                    });
            });
        }

        // Auto-fill quantity when batch is selected
        if (batchSelect501) {
            batchSelect501.addEventListener("change", function() {
                const selectedOption = this.options[this.selectedIndex];
                const sisaDisplay = document.getElementById("keluar501_sisa_display");

                if (selectedOption && (selectedOption.dataset.sisa || selectedOption.dataset.remaining501)) {
                    const sisa = parseFloat(selectedOption.dataset.sisa || selectedOption.dataset.remaining501);
                    sisaDisplay.value = sisa.toFixed(2);
                    quantityInput501.value = sisa.toFixed(2);
                    quantityInput501.max = sisa;
                } else {
                    sisaDisplay.value = "0.00";
                    quantityInput501.value = "";
                    quantityInput501.max = "";
                }
            });
        }

        // Validate quantity input
        if (quantityInput501) {
            quantityInput501.addEventListener("input", function() {
                const max = parseFloat(this.max) || 0;
                const value = parseFloat(this.value) || 0;

                if (value > max && max > 0) {
                    this.value = max;
                    showAlert(`Jumlah tidak boleh melebihi sisa 501: ${max.toFixed(2)} Kg`, "warning");
                }
            });
        }
    });
</script>