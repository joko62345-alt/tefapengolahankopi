<?php
require_once '../config/config.php';
checkRole('admin');
class ProductsController {
    private $conn;
    private $uploadDir = '../assets/images/products/';
    
    // Public properties untuk akses di view
    public $success = '';
    public $error = '';
    public $print_mode = false;
    
    // Data untuk view
    public $all_stock_history = [];
    public $total_masuk = 0;
    public $total_keluar = 0;
    public $product_name = 'Semua Produk';
    public $period_text = '';
    public $jenis_text = '';
    public $products_list = [];
    public $products = [];
    
    // Filters
    public $filter_product = '';
    public $filter_jenis = '';
    public $filter_date_from = '';
    public $filter_date_to = '';

    public function __construct($connection) {
        $this->conn = $connection;
        $this->print_mode = isset($_GET['print']) && $_GET['print'] == '1';
        $this->ensureUploadDir();
        $this->handleRequests();
        $this->loadFilters();
        $this->loadData();
    }

    private function ensureUploadDir(): void {
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
    }

    private function handleRequests(): void {
        if ($this->print_mode) return;

        if (isset($_POST['add_product'])) {
            $this->addProduct();
        }
        
        if (isset($_POST['update_product'])) {
            $this->updateProduct();
        }
        
        if (isset($_GET['delete'])) {
            $this->deleteProduct();
        }
    }

    private function addProduct(): void {
        $nama       = mysqli_real_escape_string($this->conn, trim($_POST['nama_produk']));
        $deskripsi  = mysqli_real_escape_string($this->conn, trim($_POST['deskripsi']));
        $harga      = (float) $_POST['harga'];
        $stok       = (int) $_POST['stok'];
        
        $kategori   = mysqli_real_escape_string($this->conn, trim($_POST['kategori']));
        if (empty($kategori)) {
            $kategori = 'lainnya';
        }

        $gambar_name = $this->handleFileUpload('gambar');

        $query = "INSERT INTO products (nama_produk, deskripsi, harga, stok, kategori, gambar)
                  VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "ssdiss", $nama, $deskripsi, $harga, $stok, $kategori, $gambar_name);
        
        if (mysqli_stmt_execute($stmt)) {
            $this->success = 'Produk berhasil ditambahkan!';
        } else {
            $this->error = 'Gagal menambah produk: ' . mysqli_error($this->conn);
            error_log("Insert product failed: " . mysqli_error($this->conn));
        }
        mysqli_stmt_close($stmt);
    }

    private function updateProduct(): void {
        $id = (int) $_POST['product_id'];
        $nama = mysqli_real_escape_string($this->conn, trim($_POST['nama_produk']));
        $deskripsi = mysqli_real_escape_string($this->conn, trim($_POST['deskripsi']));
        $harga = (float) $_POST['harga'];
        
        $kategori = mysqli_real_escape_string($this->conn, trim($_POST['kategori']));
        if (empty($kategori)) {
            $kategori = 'lainnya';
        }
        
        $tambah_stok = isset($_POST['tambah_stok']) ? (int) $_POST['tambah_stok'] : 0;
        $kurangi_stok = isset($_POST['kurangi_stok']) ? (int) $_POST['kurangi_stok'] : 0;
        $stock_keterangan = mysqli_real_escape_string($this->conn, $_POST['stock_keterangan'] ?? '');

        $current = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT stok FROM products WHERE id=$id"));
        $old_stock = $current['stok'];
        
        $available_stock = $old_stock + $tambah_stok;
        if ($kurangi_stok > $available_stock) {
            $this->error = "Gagal update produk: Tidak dapat mengurangi stok sebanyak {$kurangi_stok} pcs. "
                         . "Stok tersedia hanya {$available_stock} pcs (Stok awal: {$old_stock} + Restok: {$tambah_stok})";
            return;
        }
        
        $new_stock = $available_stock - $kurangi_stok;

        if ($tambah_stok > 0) {
            $ket = !empty($stock_keterangan) ? "Restok: $stock_keterangan" : "Restok manual";
            mysqli_query($this->conn, "INSERT INTO stock_movements (product_id, jenis, jumlah, keterangan)
                                        VALUES ($id, 'masuk', $tambah_stok, '$ket')");
        }

        if ($kurangi_stok > 0) {
            $ket = !empty($stock_keterangan) ? "Pengurangan: $stock_keterangan" : "Pengurangan manual";
            mysqli_query($this->conn, "INSERT INTO stock_movements (product_id, jenis, jumlah, keterangan)
                                        VALUES ($id, 'keluar', $kurangi_stok, '$ket')");
        }

        $gambar_name = $_POST['gambar_lama'];

        if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
            $new_image = $this->handleFileUpload('gambar');
            if ($new_image) {
                if ($gambar_name && file_exists($this->uploadDir . $gambar_name)) {
                    unlink($this->uploadDir . $gambar_name);
                }
                $gambar_name = $new_image;
            }
        }

        $query = "UPDATE products SET
                  nama_produk=?,
                  deskripsi=?,
                  harga=?,
                  stok=?,
                  kategori=?,
                  gambar=?
                  WHERE id=?";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "ssdissi", $nama, $deskripsi, $harga, $new_stock, $kategori, $gambar_name, $id);

        // ✅ PERBAIKAN: Pesan sukses hanya menampilkan info stok jika stok benar-benar berubah
        if (mysqli_stmt_execute($stmt)) {
            if ($new_stock != $old_stock) {
                $this->success = 'Produk berhasil diupdate! Stok: ' . $old_stock . ' → ' . $new_stock;
            } else {
                $this->success = 'Produk berhasil diupdate!';
            }
        } else {
            $this->error = 'Gagal update produk: ' . mysqli_error($this->conn);
            error_log("Update product failed: " . mysqli_error($this->conn));
        }
        mysqli_stmt_close($stmt);
    }

    private function deleteProduct(): void {
        $id = (int) $_GET['delete'];
        $prod = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT gambar FROM products WHERE id='$id'"));

        if ($prod) {
            if ($prod['gambar'] && file_exists($this->uploadDir . $prod['gambar'])) {
                unlink($this->uploadDir . $prod['gambar']);
            }

            mysqli_query($this->conn, "DELETE FROM stock_movements WHERE product_id = $id");
            mysqli_query($this->conn, "DELETE FROM transaction_details WHERE product_id = $id");

            if (mysqli_query($this->conn, "DELETE FROM products WHERE id='$id'")) {
                header("Location: products.php?success=deleted");
                exit;
            }
        }
    }

    private function handleFileUpload(string $fieldName): string {
        if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] != 0) {
            return '';
        }

        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($_FILES[$fieldName]['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            return '';
        }

        $new_filename = uniqid('prod_') . '.' . $ext;
        $upload_path = $this->uploadDir . $new_filename;

        if (move_uploaded_file($_FILES[$fieldName]['tmp_name'], $upload_path)) {
            return $new_filename;
        }
        return '';
    }

    private function loadFilters(): void {
        $this->filter_product = $_GET['filter_product'] ?? '';
        $this->filter_jenis = $_GET['filter_jenis'] ?? '';
        $this->filter_date_from = $_GET['filter_date_from'] ?? '';
        $this->filter_date_to = $_GET['filter_date_to'] ?? '';
    }

    private function buildHistoryWhereClause(): string {
        $where = "1=1";
        
        if ($this->filter_product) {
            $where .= " AND sm.product_id = " . (int) $this->filter_product;
        }
        if ($this->filter_jenis) {
            $where .= " AND sm.jenis = '" . mysqli_real_escape_string($this->conn, $this->filter_jenis) . "'";
        }
        if ($this->filter_date_from) {
            $where .= " AND DATE(sm.tanggal) >= '" . mysqli_real_escape_string($this->conn, $this->filter_date_from) . "'";
        }
        if ($this->filter_date_to) {
            $where .= " AND DATE(sm.tanggal) <= '" . mysqli_real_escape_string($this->conn, $this->filter_date_to) . "'";
        }
        
        return $where;
    }

    private function loadData(): void {
        $where = $this->buildHistoryWhereClause();
        $history_query = mysqli_query($this->conn, "
            SELECT sm.*, p.nama_produk, p.kategori
            FROM stock_movements sm
            JOIN products p ON sm.product_id = p.id
            WHERE $where
            ORDER BY sm.tanggal DESC
            LIMIT 500
        ");

        $this->all_stock_history = [];
        while ($row = mysqli_fetch_assoc($history_query)) {
            $this->all_stock_history[] = $row;
        }

        foreach ($this->all_stock_history as $hist) {
            if ($hist['jenis'] == 'masuk') {
                $this->total_masuk += $hist['jumlah'];
            } else {
                $this->total_keluar += $hist['jumlah'];
            }
        }

        if ($this->filter_product) {
            $prod = mysqli_fetch_assoc(mysqli_query($this->conn, 
                "SELECT nama_produk FROM products WHERE id = " . (int) $this->filter_product));
            if ($prod) {
                $this->product_name = $prod['nama_produk'];
            }
        }

        if ($this->filter_date_from && $this->filter_date_to) {
            $this->period_text = date('d M Y', strtotime($this->filter_date_from)) . ' - ' . date('d M Y', strtotime($this->filter_date_to));
        } elseif ($this->filter_date_from) {
            $this->period_text = 'Dari ' . date('d M Y', strtotime($this->filter_date_from));
        } elseif ($this->filter_date_to) {
            $this->period_text = 'Sampai ' . date('d M Y', strtotime($this->filter_date_to));
        } else {
            $this->period_text = 'Semua Periode';
        }

        if ($this->filter_jenis) {
            $this->jenis_text = ' | Jenis: ' . ucfirst($this->filter_jenis);
        }

        $products_list_query = mysqli_query($this->conn, "SELECT id, nama_produk FROM products ORDER BY nama_produk");
        while ($p = mysqli_fetch_assoc($products_list_query)) {
            $this->products_list[] = $p;
        }

        $products_query = mysqli_query($this->conn, "SELECT * FROM products ORDER BY created_at DESC");
        while ($p = mysqli_fetch_assoc($products_query)) {
            $this->products[] = $p;
        }
    }

    public function getStockBadge(string $stok): string {
        return $stok < 20 ? 'badge-danger' : 'badge-success';
    }

    public function getHistoryBadge(string $jenis): string {
        return $jenis == 'masuk' ? 'badge-success' : 'badge-danger';
    }

    public function getHistoryIcon(string $jenis): string {
        return $jenis == 'masuk' ? 'arrow-down' : 'arrow-up';
    }

    public function getStockSign(string $jenis): string {
        return $jenis == 'masuk' ? '+' : '-';
    }

    public function getStockTextColor(string $jenis): string {
        return $jenis == 'masuk' ? 'text-success' : 'text-danger';
    }

    public function formatDate(string $datetime, string $format = 'd M Y H:i'): string {
        return date($format, strtotime($datetime));
    }

    public function formatRupiah(float $amount): string {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    public function imageExists(string $filename): bool {
        return $filename && file_exists($this->uploadDir . $filename);
    }

    public function getImagePath(string $filename): string {
        return '../assets/images/products/' . $filename;
    }

    public function getCurrentAdminName(): string {
        return htmlspecialchars($_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'Admin');
    }

    public function renderPrintView(): void {
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Laporan Riwayat Stok Produk - TEFA COFFEE</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <link rel="stylesheet" href="css/receipt.css">
        </head>
        <body>
            <div class="print-actions">
                <button onclick="window.print()" class="btn-print">
                    <i class="fas fa-print"></i> Cetak Laporan
                </button>
                <a href="products.php" class="btn-close">
                    <i class="fas fa-times"></i> Kembali
                </a>
            </div>
            <div class="container">
                <div class="report-header">
                    <img src="../assets/images/logopolije.png" alt="Logo" class="logo" onerror="this.style.display='none'">
                    <h1>TEFA COFFEE</h1>
                    <h2>LAPORAN RIWAYAT STOK</h2>
                    <div class="report-info">
                        <table>
                            <tr>
                                <td>Produk</td>
                                <td>: <?= htmlspecialchars($this->product_name) ?></td>
                            </tr>
                            <tr>
                                <td>Periode</td>
                                <td>: <?= htmlspecialchars($this->period_text) ?><?= htmlspecialchars($this->jenis_text) ?></td>
                            </tr>
                            <tr>
                                <td>Total Data</td>
                                <td>: <?= count($this->all_stock_history) ?> riwayat</td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php if (count($this->all_stock_history) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 5%;">No</th>
                                <th style="width: 15%;">Tanggal</th>
                                <th style="width: 25%;">Nama Produk</th>
                                <th style="width: 15%;">Kategori</th>
                                <th style="width: 12%;">Jenis</th>
                                <th style="width: 13%;">Jumlah</th>
                                <th style="width: 15%;">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1;
                            foreach ($this->all_stock_history as $hist): ?>
                                <tr>
                                    <td class="text-center"><?= $no++ ?></td>
                                    <td><?= $this->formatDate($hist['tanggal']) ?></td>
                                    <td><?= htmlspecialchars($hist['nama_produk']) ?></td>
                                    <td><?= ucfirst($hist['kategori']) ?></td>
                                    <td class="text-center"><?= ucfirst($hist['jenis']) ?></td>
                                    <td class="text-right">
                                        <?= $this->getStockSign($hist['jenis']) ?> <?= number_format($hist['jumlah'], 0, ',', '.') ?>
                                    </td>
                                    <td><?= htmlspecialchars($hist['keterangan'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="summary-box">
                        <h3>Ringkasan Stok</h3>
                        <table class="summary-table">
                            <tr>
                                <td>Total Stok Masuk</td>
                                <td>+<?= number_format($this->total_masuk, 0, ',', '.') ?> pcs</td>
                            </tr>
                            <tr>
                                <td>Total Stok Keluar</td>
                                <td>-<?= number_format($this->total_keluar, 0, ',', '.') ?> pcs</td>
                            </tr>
                            <tr class="total-row">
                                <td>Netto Perubahan</td>
                                <td><?= ($this->total_masuk - $this->total_keluar) >= 0 ? '+' : '' ?><?= number_format($this->total_masuk - $this->total_keluar, 0, ',', '.') ?> pcs</td>
                            </tr>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <h3>Tidak Ada Data</h3>
                        <p>Tidak ada riwayat stok yang sesuai dengan filter yang dipilih</p>
                    </div>
                <?php endif; ?>
                <div class="report-footer">
                    <div class="footer-section">
                        <h4>Mengetahui,</h4>
                        <p>Kepala TEFA Coffee</p>
                        <div class="signature-line"><strong>( ___________________ )</strong></div>
                    </div>
                    <div class="footer-section">
                        <h4>Dibuat Oleh,</h4>
                        <p>Administrator</p>
                        <div class="signature-line"><strong>(<?= $this->getCurrentAdminName() ?>)</strong></div>
                    </div>
                </div>
                <div class="print-date">
                    Dicetak pada: <?= date('d M Y, H:i:s') ?> WIB
                </div>
            </div>
            <script>
                window.onload = function () {
                    window.print();
                    setTimeout(function () { window.location.href = 'products.php'; }, 1000);
                };
                window.onafterprint = function () { window.location.href = 'products.php'; };
            </script>
        </body>
        </html>
        <?php
        exit;
    }
}

$products = new ProductsController($conn);

if ($products->print_mode) {
    $products->renderPrintView();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Produk - TEFA Coffee</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/products.css">
</head>
<body>
    <div class="top-header">
        <div class="container">
            <div class="header-content">
                <div class="logo-container">
                    <img src="../assets/images/logopolije.png" alt="Polije" class="logo-polije"
                        onerror="this.src='https://via.placeholder.com/42x42/2C1810/FFFFFF?text=P'">
                    <div class="logo-divider"></div>
                    <img src="../assets/images/sip.png" alt="TEFA" class="logo-tefa"
                        onerror="this.src='https://via.placeholder.com/42x42/A67C52/FFFFFF?text=T'">
                    <span class="brand-text">TEFA COFFEE</span>
                </div>
                <button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle Menu">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </div>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="sidebar" id="sidebar">
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
            <li><a href="products.php" class="active"><i class="fas fa-box"></i><span>Kelola Produk</span></a></li>
            <li><a href="stock.php"><i class="fa-solid fa-pen-to-square"></i><span>Data Stok biji kopi</span></a></li>
            <li><a href="inventory.php"><i class="fas fa-clipboard-list"></i><span>Inventaris</span></a></li>
            <li><a href="transactions.php"><i class="fas fa-shopping-cart"></i><span>Transaksi</span></a></li>
            <div class="sidebar-divider"></div>
            <li><a href="../logout.php" style="color: #ef9a9a;"><i
                        class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
        </ul>
    </div>

    <div class="main-wrapper">
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Kelola Produk</h1>
            </div>

            <?php if ($products->success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?= $products->success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($products->error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?= $products->error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card-custom">
                <div class="card-header-custom">
                    <span>Tambah Produk Baru</span>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Nama Produk <span
                                        class="text-danger">*</span></label>
                                <input type="text" name="nama_produk" class="form-control" placeholder="masukkan nama produk" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Harga (Rp) <span
                                        class="text-danger">*</span></label>
                                <input type="number" name="harga" class="form-control" min="0" step="100" placeholder="masukkan harga" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Stok Awal <span
                                        class="text-danger">*</span></label>
                                <input type="number" name="stok" class="form-control" min="0" placeholder="masukkan stok awal" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Kategori <span
                                        class="text-danger">*</span></label>
                                <input type="text" name="kategori" class="form-control"
                                    placeholder="Contoh: Robusta, Arabica" required>
                                <small class="form-text text-muted">Isi kategori produk secara manual</small>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Deskripsi</label>
                                <textarea name="deskripsi" class="form-control" rows="2"
                                    placeholder="Deskripsi produk..."></textarea>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Foto Produk</label>
                                <input type="file" name="gambar" class="form-control" accept="image/*"
                                    onchange="previewImage(this, 'previewAdd')">
                                <small class="form-text text-muted">
                                    <i class="fas fa-info-circle me-1"></i>Format: JPG, PNG, GIF, WebP (Max 2MB)
                                </small>
                                <img id="previewAdd" class="preview-img mt-2">
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" name="add_product" class="btn-custom btn-primary">
                                <i class="fas fa-save"></i>Simpan Produk
                            </button>
                            <button type="reset" class="btn-custom btn-secondary">
                                <i class="fas fa-undo"></i>Reset
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card-custom">
                <div class="card-header-custom">
                    <span>Riwayat Stok Semua Produk</span>
                    <div class="ms-auto d-flex gap-2">
                        <button class="btn btn-sm btn-coffee" onclick="openPrintReport()">
                            <i class="fas fa-file-pdf"></i> Cetak Laporan
                        </button>
                        <button class="btn btn-sm btn-coffee-light" onclick="openHistoryModal()">
                            <i class="fas fa-eye"></i> Lihat Semua
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="activeFilters" class="filter-active-indicator">
                        <i class="fas fa-filter text-primary"></i>
                        <span class="flex-grow-1">Filter aktif: <strong id="activeFiltersText"></strong></span>
                        <button class="btn btn-sm btn-secondary" onclick="resetFilters()">
                            <i class="fas fa-times"></i> Reset
                        </button>
                    </div>
                    <form id="filterForm" method="GET" class="filter-section mb-3">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label"><i class="fas fa-box me-1 text-muted"></i>Filter
                                    Produk</label>
                                <select name="filter_product" id="filter_product" class="form-control"
                                    onchange="applyFilter()">
                                    <option value="">Semua Produk</option>
                                    <?php foreach ($products->products_list as $p): ?>
                                        <option value="<?= $p['id'] ?>" <?= $products->filter_product == $p['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['nama_produk']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"><i
                                        class="fas fa-exchange-alt me-1 text-muted"></i>Jenis</label>
                                <select name="filter_jenis" id="filter_jenis" class="form-control"
                                    onchange="applyFilter()">
                                    <option value="">Semua</option>
                                    <option value="masuk" <?= $products->filter_jenis == 'masuk' ? 'selected' : '' ?>>Masuk</option>
                                    <option value="keluar" <?= $products->filter_jenis == 'keluar' ? 'selected' : '' ?>>Keluar
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"><i class="fas fa-calendar-alt me-1 text-muted"></i>Dari
                                    Tanggal</label>
                                <input type="date" name="filter_date_from" id="filter_date_from" class="form-control"
                                    value="<?= htmlspecialchars($products->filter_date_from) ?>" onchange="applyFilter()">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"><i class="fas fa-calendar-check me-1 text-muted"></i>Sampai
                                    Tanggal</label>
                                <input type="date" name="filter_date_to" id="filter_date_to" class="form-control"
                                    value="<?= htmlspecialchars($products->filter_date_to) ?>" onchange="applyFilter()">
                            </div>
                        </div>
                    </form>
                    <div class="table-container" id="historyTableContainer">
                        <div class="table-responsive">
                            <table class="table table-custom history-table">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-clock me-1"></i>Tanggal</th>
                                        <th><i class="fas fa-box me-1"></i>Produk</th>
                                        <th><i class="fas fa-tag me-1"></i>Kategori</th>
                                        <th><i class="fas fa-exchange-alt me-1"></i>Jenis</th>
                                        <th><i class="fas fa-hashtag me-1"></i>Jumlah</th>
                                        <th><i class="fas fa-comment me-1"></i>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody id="historyTableBody">
                                    <?php if (count($products->all_stock_history) > 0): ?>
                                        <?php foreach (array_slice($products->all_stock_history, 0, 10) as $hist): ?>
                                            <tr>
                                                <td><?= $products->formatDate($hist['tanggal']) ?></td>
                                                <td class="fw-semibold"><?= htmlspecialchars($hist['nama_produk']) ?></td>
                                                <td><?= ucfirst($hist['kategori']) ?></td>
                                                <td>
                                                    <span class="badge history-badge <?= $products->getHistoryBadge($hist['jenis']) ?>">
                                                        <i class="fas fa-<?= $products->getHistoryIcon($hist['jenis']) ?>"></i>
                                                        <?= ucfirst($hist['jenis']) ?>
                                                    </span>
                                                </td>
                                                <td class="fw-bold <?= $products->getStockTextColor($hist['jenis']) ?>">
                                                    <?= $products->getStockSign($hist['jenis']) ?> <?= $hist['jumlah'] ?>
                                                </td>
                                                <td><?= htmlspecialchars($hist['keterangan'] ?? '-') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6">
                                                <div class="no-results">
                                                    <i class="fas fa-inbox"></i>
                                                    <p class="mb-0">Belum ada riwayat stok</p>
                                                    <small>Data akan muncul setelah ada transaksi atau restok</small>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php if (count($products->all_stock_history) > 10): ?>
                        <div class="text-center mt-3">
                            <button class="btn-custom btn-coffee" onclick="openHistoryModal()">
                                <i class="fas fa-list"></i> Lihat Semua Riwayat (<span
                                    id="totalHistoryCount"><?= count($products->all_stock_history) ?></span> data)
                            </button>
                        </div>
                    <?php endif; ?>
                    <div class="mt-3 text-end">
                        <small class="text-muted">
                            <i class="fas fa-database me-1"></i>
                            Menampilkan <strong id="displayedCount"><?= count($products->all_stock_history) ?></strong> dari
                            <strong id="totalCount"><?= count($products->all_stock_history) ?></strong> riwayat
                        </small>
                    </div>
                </div>
            </div>

            <div class="card-custom">
                <div class="card-header-custom">
                    <span>Daftar Produk</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-custom">
                            <thead>
                                <tr>
                                    <th>Foto</th>
                                    <th>Nama Produk</th>
                                    <th>Kategori</th>
                                    <th>Harga</th>
                                    <th>Stok</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products->products as $product): ?>
                                    <tr>
                                        <td data-label="Foto">
                                            <?php if ($products->imageExists($product['gambar'])): ?>
                                                <img src="<?= $products->getImagePath($product['gambar']) ?>"
                                                    alt="<?= htmlspecialchars($product['nama_produk']) ?>"
                                                    class="product-thumb">
                                            <?php else: ?>
                                                <div class="product-thumb-placeholder"><i class="fas fa-coffee"></i></div>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Nama" class="fw-semibold">
                                            <?= htmlspecialchars($product['nama_produk']) ?></td>
                                        <td data-label="Kategori"><?= ucfirst($product['kategori']) ?></td>
                                        <td data-label="Harga" class="text-rupiah"><?= $products->formatRupiah($product['harga']) ?></td>
                                        <td data-label="Stok">
                                            <span class="badge <?= $products->getStockBadge($product['stok']) ?>">
                                                <?= $product['stok'] ?>
                                            </span>
                                        </td>
                                        <td data-label="Aksi">
                                            <button class="btn btn-sm btn-warning"
                                                onclick='editProduct(
                                                    <?= json_encode($product['id']) ?>,
                                                    <?= json_encode($product['nama_produk']) ?>,
                                                    <?= json_encode($product['deskripsi'] ?? '') ?>,
                                                    <?= json_encode($product['harga']) ?>,
                                                    <?= json_encode($product['stok']) ?>,
                                                    <?= json_encode($product['kategori']) ?>,
                                                    <?= json_encode($product['gambar']) ?>
                                                )' title="Edit Produk">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <!-- PERBAIKAN: Tombol Hapus dengan escaping yang benar -->
                                            <a href="?delete=<?= (int) $product['id'] ?>" 
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Yakin hapus produk ini?\\n\\nPeringatan:\\n- Riwayat stok akan dihapus\\n- Detail transaksi akan dihapus\\n\\nProduk: <?= addslashes($product['nama_produk']) ?>')">
                                                <i class="fas fa-trash"></i> Hapus
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel">Edit Produk</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="product_id" id="edit_id">
                        <input type="hidden" name="gambar_lama" id="edit_gambar_lama">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Nama Produk *</label>
                                <input type="text" name="nama_produk" id="edit_nama" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Harga (Rp) *</label>
                                <input type="number" name="harga" id="edit_harga" class="form-control" min="0"
                                    step="100" required>
                            </div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-4">
                                <label class="form-label">Stok Saat Ini</label>
                                <input type="number" id="edit_stok_display" class="form-control" readonly
                                    style="background: #f8f9fa;">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tambah Stok</label>
                                <input type="number" name="tambah_stok" id="edit_tambah_stok" class="form-control"
                                    min="0" placeholder="0" onchange="calculateNewStock()">
                                <small class="text-muted" style="font-size: 0.75rem;">Kosongkan jika tidak ada
                                    restok</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Kurangi Stok</label>
                                <input type="number" name="kurangi_stok" id="edit_kurangi_stok" class="form-control"
                                    min="0" placeholder="0" onchange="calculateNewStock()">
                                <small class="text-muted" style="font-size: 0.75rem;">Kosongkan jika tidak ada
                                    pengurangan</small>
                            </div>
                        </div>
                        <div class="alert alert-info mt-3 d-flex align-items-center" role="alert">
                            <i class="fas fa-calculator me-2"></i>
                            <div><strong>Stok Baru:</strong> <span id="new_stock_value" class="fw-bold"
                                    style="font-size: 1.1rem;">0</span> pcs</div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label class="form-label">Kategori *</label>
                                <input type="text" name="kategori" id="edit_kategori" class="form-control" 
                                    placeholder="Contoh: Robusta, Arabica, Lainnya" required>
                                <small class="form-text text-muted">Ketik kategori produk secara manual</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Keterangan Perubahan Stok</label>
                                <input type="text" name="stock_keterangan" id="edit_stock_keterangan"
                                    class="form-control" placeholder="Contoh: Restok mingguan, Penjualan, dll">
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">Deskripsi</label>
                            <textarea name="deskripsi" id="edit_deskripsi" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mt-4">
                            <label class="form-label">Foto Produk</label>
                            <div class="d-flex align-items-center gap-4 flex-wrap">
                                <div>
                                    <small class="text-muted d-block mb-2">Foto Saat Ini:</small>
                                    <img id="edit_current_img" src="" alt="Current" class="current-image"
                                        style="display: none;">
                                    <div id="edit_no_img" class="current-image-placeholder" style="display: none;"><i
                                            class="fas fa-coffee"></i></div>
                                </div>
                                <div style="flex:1; min-width:200px;">
                                    <small class="text-muted d-block mb-2">Upload Foto Baru:</small>
                                    <input type="file" name="gambar" class="form-control" accept="image/*"
                                        onchange="previewImage(this, 'previewEdit')">
                                </div>
                            </div>
                            <img id="previewEdit" class="preview-img">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-custom btn-secondary" data-bs-dismiss="modal"><i
                                class="fas fa-times"></i>Batal</button>
                        <button type="submit" name="update_product" class="btn-custom btn-primary"><i
                                class="fas fa-save"></i>Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- History Modal -->
    <div class="modal fade" id="historyModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background:var(--coffee-dark);color:white;">
                    <h5 class="modal-title">Riwayat Stok - Semua Produk</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                        style="filter:brightness(0) invert(1);"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-custom history-table mb-0">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Produk</th>
                                    <th>Kategori</th>
                                    <th>Jenis</th>
                                    <th>Jumlah</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody id="historyModalBody">
                                <?php if (count($products->all_stock_history) > 0): ?>
                                    <?php foreach ($products->all_stock_history as $hist): ?>
                                        <tr>
                                            <td><?= $products->formatDate($hist['tanggal']) ?></td>
                                            <td class="fw-semibold"><?= htmlspecialchars($hist['nama_produk']) ?></td>
                                            <td><?= ucfirst($hist['kategori']) ?></td>
                                            <td>
                                                <span class="badge history-badge <?= $products->getHistoryBadge($hist['jenis']) ?>">
                                                    <i class="fas fa-<?= $products->getHistoryIcon($hist['jenis']) ?>"></i>
                                                    <?= ucfirst($hist['jenis']) ?>
                                                </span>
                                            </td>
                                            <td class="fw-bold <?= $products->getStockTextColor($hist['jenis']) ?>">
                                                <?= $products->getStockSign($hist['jenis']) ?> <?= $hist['jumlah'] ?>
                                            </td>
                                            <td><?= htmlspecialchars($hist['keterangan'] ?? '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">Belum ada riwayat stok</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 text-end"><small class="text-muted">Menampilkan <?= count($products->all_stock_history) ?>
                            riwayat terbaru</small></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-custom btn-secondary" data-bs-dismiss="modal"><i
                            class="fas fa-times"></i> Tutup</button>
                    <button type="button" class="btn-custom btn-coffee" onclick="openPrintReport()"><i
                            class="fas fa-file-pdf"></i> Cetak Laporan</button>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/products.js?v=1.1"></script>
</body>
</html>