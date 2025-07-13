<?php
// ===================================================================================
// SESSION CHECK
// ===================================================================================
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ===================================================================================
// KONTROLER UTAMA
// ===================================================================================
include 'koneksi.php';

// --- MENANGANI AKSI DARI URL (METHOD GET) ---
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = $_GET['id'] ?? 0;

    if ($action === 'delete_produk' && $id > 0) {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: index.php?page=daftar_produk&status=dihapus");
        exit();
    }
    if ($action === 'delete_incoming' && $id > 0) {
        $stmt = $pdo->prepare("DELETE FROM incoming_transactions WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: index.php?page=barang_masuk&status=dihapus");
        exit();
    }
    if ($action === 'delete_outgoing' && $id > 0) {
        $stmt = $pdo->prepare("DELETE FROM outgoing_transactions WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: index.php?page=barang_keluar&status=dihapus");
        exit();
    }
}

// --- MENANGANI PENGIRIMAN FORM (METHOD POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type'])) {

    switch ($_POST['form_type']) {
        case 'produk':
            try {
                $is_edit = !empty($_POST['product_id']);
                $params = [':sku' => $_POST['sku'], ':product_name' => $_POST['product_name'], ':standard_qty' => $_POST['standard_qty'] ?: null];
                if ($is_edit) {
                    $sql = "UPDATE products SET sku=:sku, product_name=:product_name, standard_qty=:standard_qty WHERE id=:id";
                    $params[':id'] = $_POST['product_id'];
                } else {
                    $sql = "INSERT INTO products (sku, product_name, standard_qty) VALUES (:sku, :product_name, :standard_qty)";
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                header("Location: index.php?page=daftar_produk&status=" . ($is_edit ? 'sukses_edit' : 'sukses_tambah'));
                exit();
            } catch (PDOException $e) {
                die('Error Produk: ' . $e->getMessage());
            }
            break;

        case 'barang_masuk':
            try {
                $is_edit = !empty($_POST['transaction_id']);

                // Ambil standard_qty produk terkait
                $stmt_std = $pdo->prepare("SELECT standard_qty FROM products WHERE id = ?");
                $stmt_std->execute([$_POST['product_id']]);
                $product = $stmt_std->fetch(PDO::FETCH_ASSOC);
                $standard_qty = ($product && !empty($product['standard_qty'])) ? (float)$product['standard_qty'] : 0;

                $quantity_kg = (float)$_POST['quantity_kg'];
                $quantity_sacks = (float)$_POST['quantity_sacks'];

                // Logika kalkulasi berdasarkan checkbox
                if (isset($_POST['calc_sak']) && $standard_qty > 0) {
                    $quantity_sacks = $quantity_kg / $standard_qty;
                } elseif (isset($_POST['calc_kg']) && $standard_qty > 0) {
                    $quantity_kg = $quantity_sacks * $standard_qty;
                }

                $gross_weight = !empty($_POST['gross_weight']) ? (float)$_POST['gross_weight'] : 0;
                $lot_number_calc = ($gross_weight > 0) ? $gross_weight - $quantity_kg : 0;

                $params = [
                    ':product_id' => $_POST['product_id'],
                    ':po_number' => $_POST['po_number'],
                    ':supplier' => $_POST['supplier'],
                    ':produsen' => $_POST['produsen'],
                    ':license_plate' => $_POST['license_plate'],
                    ':quantity_kg' => $quantity_kg,
                    ':quantity_sacks' => $quantity_sacks,
                    ':document_number' => $_POST['document_number'],
                    ':batch_number' => $_POST['batch_number'],
                    ':lot_number' => $lot_number_calc,
                    ':status' => $_POST['status'],
                    ':transaction_date' => $_POST['transaction_date']
                ];

                if ($is_edit) {
                    $sql = "UPDATE incoming_transactions SET product_id=:product_id, po_number=:po_number, supplier=:supplier, produsen=:produsen, license_plate=:license_plate, quantity_kg=:quantity_kg, quantity_sacks=:quantity_sacks, document_number=:document_number, batch_number=:batch_number, lot_number=:lot_number, status=:status, transaction_date=:transaction_date WHERE id=:id";
                    $params[':id'] = $_POST['transaction_id'];
                } else {
                    $sql = "INSERT INTO incoming_transactions (product_id, po_number, supplier, produsen, license_plate, quantity_kg, quantity_sacks, document_number, batch_number, lot_number, status, transaction_date) VALUES (:product_id, :po_number, :supplier, :produsen, :license_plate, :quantity_kg, :quantity_sacks, :document_number, :batch_number, :lot_number, :status, :transaction_date)";
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $redirect_status = $is_edit ? 'sukses_edit' : 'sukses_tambah';
                header("Location: index.php?page=barang_masuk&status={$redirect_status}");
                exit();
            } catch (PDOException $e) {
                die('Error Barang Masuk: ' . $e->getMessage());
            }
            break;

        case 'barang_keluar':
            try {
                $is_edit = !empty($_POST['original_document_number']);
                $items = json_decode($_POST['items_json'], true);

                if (!is_array($items) || empty($items)) {
                    header("Location: index.php?page=barang_keluar&status=gagal_no_item");
                    exit();
                }

                $pdo->beginTransaction();

                $document_number = $_POST['document_number'];
                if (empty($document_number)) {
                    $document_number = 'OUT-' . date('Ymd-His');
                }

                if ($is_edit) {
                    $original_doc_number = $_POST['original_document_number'];
                    $stmt_delete = $pdo->prepare("DELETE FROM outgoing_transactions WHERE document_number = ?");
                    $stmt_delete->execute([$original_doc_number]);
                }

                $description = $_POST['description'];
                $transaction_date = $_POST['transaction_date'];
                $status = $_POST['status'];

                foreach ($items as $item) {
                    $sql = "INSERT INTO outgoing_transactions 
                                    (product_id, incoming_transaction_id, quantity_kg, quantity_sacks, 
                                    description, document_number, batch_number, lot_number, status, transaction_date) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?)";

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $item['product_id'],
                        $item['incoming_id'],
                        $item['qty_kg'],
                        $item['qty_sak'],
                        $description,
                        $document_number,
                        $item['batch_number'],
                        $status,
                        $transaction_date
                    ]);
                }

                $pdo->commit();
                $redirect_status = $is_edit ? 'sukses_edit' : 'sukses_tambah';
                header("Location: index.php?page=barang_keluar&status={$redirect_status}");
                exit();
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                die('Error Barang Keluar: ' . $e->getMessage());
            }
            break;

        case 'keluarkan_501':
            try {
                $incoming_id = $_POST['incoming_transaction_id'];
                $product_id = $_POST['product_id'];
                $qty_501_diminta = (float)$_POST['quantity_501'];

                $stmt_sisa = $pdo->prepare("
                        SELECT (t_in.lot_number - COALESCE(SUM(t_out.lot_number), 0)) AS sisa
                        FROM incoming_transactions t_in
                        LEFT JOIN outgoing_transactions t_out ON t_in.id = t_out.incoming_transaction_id
                        WHERE t_in.id = ? GROUP BY t_in.id, t_in.lot_number
                    ");
                $stmt_sisa->execute([$incoming_id]);
                $sisa_lot = (float)$stmt_sisa->fetchColumn();

                $qty_disimpan_501 = 0;
                $status_redirect = '';
                $params_redirect = '';

                if ($sisa_lot <= 0) {
                    $status_redirect = 'stok_habis';
                } elseif ($qty_501_diminta > $sisa_lot) {
                    $qty_disimpan_501 = $sisa_lot;
                    $kekurangan = $qty_501_diminta - $sisa_lot;
                    $status_redirect = 'sukses_parsial_501';
                    $params_redirect = "&kurang={$kekurangan}&dikeluarkan={$qty_disimpan_501}";
                } else {
                    $qty_disimpan_501 = $qty_501_diminta;
                    $status_redirect = 'sukses_501';
                }

                if ($qty_disimpan_501 > 0) {
                    $stmt_batch = $pdo->prepare("SELECT batch_number FROM incoming_transactions WHERE id = ?");
                    $stmt_batch->execute([$incoming_id]);
                    $batch_number = $stmt_batch->fetchColumn();

                    $sql = "INSERT INTO outgoing_transactions 
                                    (product_id, incoming_transaction_id, quantity_kg, quantity_sacks, description, lot_number, batch_number, status, transaction_date) 
                                VALUES (?, ?, 0, 0, ?, ?, ?, 'Closed', ?)";
                    $stmt_insert = $pdo->prepare($sql);
                    $stmt_insert->execute([
                        $product_id,
                        $incoming_id,
                        $_POST['description'],
                        $qty_disimpan_501,
                        $batch_number,
                        $_POST['transaction_date']
                    ]);
                }

                header("Location: index.php?page=barang_keluar&status={$status_redirect}{$params_redirect}");
                exit();
            } catch (PDOException $e) {
                die('Error Pengeluaran 501: ' . $e->getMessage());
            }
            break;
    }
}

$page = $_GET['page'] ?? 'beranda';
$active_page = $page;
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Stok - <?= ucfirst(str_replace('_', ' ', $page)) ?></title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body class="app-body">
    <!-- SINGLE TOP NAVIGATION - NO DUPLICATES -->
    <nav class="navbar navbar-expand-lg top-navbar fixed-top">
        <div class="container-fluid px-3">
            <!-- SINGLE MENU TOGGLE - ALWAYS VISIBLE -->
            <button class="btn btn-link navbar-toggler d-block p-1 me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" style="z-index: 1100;">
                <i class="bi bi-list fs-4 text-white"></i>
            </button>

            <!-- BRAND -->
            <div class="navbar-brand d-flex align-items-center me-auto">
                <i class="bi bi-box-seam-fill me-2 text-white fs-5"></i>
                <span class="fw-bold text-white d-none d-md-inline fs-5">Manajemen Stok</span>
            </div>

            <!-- USER INFO & LOGOUT -->
            <div class="d-flex align-items-center">
                <span class="navbar-text me-3 d-none d-sm-inline text-white">
                    Halo, <strong><?= htmlspecialchars($_SESSION['user_nama'] ?? 'Pengguna') ?></strong>!
                </span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i>
                    <span class="d-none d-sm-inline">Logout</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- MAIN LAYOUT -->
    <div class="app-container">
        <!-- SIDEBAR -->
        <?php include 'sidebar.php'; ?>

        <!-- MAIN CONTENT WITH CONSISTENT BACKGROUND -->
        <main class="main-content">
            <div class="content-wrapper">
                <?php
                $allowed_pages = ['beranda', 'daftar_produk', 'barang_masuk', 'barang_keluar', 'laporan', 'stock_jalur'];
                if (in_array($page, $allowed_pages) && file_exists($page . '_content.php')) {
                    include $page . '_content.php';
                } else {
                    include 'beranda_content.php';
                }
                ?>
            </div>
        </main>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>

</html>