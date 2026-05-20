<?php
require_once '../config/config.php';
checkRole('admin');

class StockController {
    private $conn;
    //  Public properties untuk akses di view
    public $success = '';
    public $error = '';  
    public $print_mode = false;
    
    
    
    // Data untuk view
    public $all_movements = [];
    public $total_masuk = 0;
    public $total_keluar = 0;
    public $bean_name = 'Semua Biji Kopi';
    public $period_text = '';
    public $jenis_text = '';
    public $coffee_beans = [];
    public $coffee_beans_list = [];
    
    // Filters
    public $filter_bean = '';
    public $filter_jenis = '';
    public $filter_date_from = '';
    public $filter_date_to = '';

    public function __construct($connection) {
        $this->conn = $connection;
        $this->print_mode = isset($_GET['print']) && $_GET['print'] == '1';
        $this->handleRequests();
        $this->loadFilters();
        $this->loadData();
    }

    // Handle POST requests (Add Stock Movement)
     
    private function handleRequests(): void {
        if ($this->print_mode) return;

        if (isset($_POST['add_stock'])) {
            $this->addStockMovement();
        }
    }

    // Load filter parameters dari GET
     
    private function loadFilters(): void {
        $this->filter_bean = $_GET['filter_bean'] ?? '';
        $this->filter_jenis = $_GET['filter_jenis'] ?? '';
        $this->filter_date_from = $_GET['filter_date_from'] ?? '';
        $this->filter_date_to = $_GET['filter_date_to'] ?? '';
    }
    private function addStockMovement(): void {
    $nama_biji_kopi = mysqli_real_escape_string($this->conn, trim($_POST['nama_biji_kopi']));
    $jenis = $_POST['jenis'];
    $jumlah = (float) $_POST['jumlah'];
    $keterangan = mysqli_real_escape_string($this->conn, trim($_POST['keterangan']));

    // Validasi input dasar
    if (empty($nama_biji_kopi) || $jumlah <= 0) {
        $this->error = "Nama biji kopi dan jumlah harus diisi!";
        return;
    }

    // Cari coffee bean berdasarkan nama
    $check_bean = mysqli_query($this->conn, 
        "SELECT id, stok FROM coffee_beans WHERE LOWER(nama_biji_kopi) = LOWER('$nama_biji_kopi')");
    
    if (mysqli_num_rows($check_bean) > 0) {
        $bean = mysqli_fetch_assoc($check_bean);
        $bean_id = $bean['id'];
        $current_stock = (float) $bean['stok'];
    } else {
        // Jika bean baru
        $insert_bean = mysqli_query($this->conn, 
            "INSERT INTO coffee_beans (nama_biji_kopi, stok) VALUES ('$nama_biji_kopi', 0)");
        
        if (!$insert_bean) {
            $this->error = 'Gagal membuat data biji kopi.';
            return;
        }
        $bean_id = mysqli_insert_id($this->conn);
        $current_stock = 0;
    }

    //  VALIDASI: Cek stok keluar
    if ($jenis == 'keluar' && $jumlah > $current_stock) {
        $this->error = "
            <div class='alert-content'>
                <div class='alert-title'>
                    
                    Stok Tidak Mencukupi!
                </div>
                <div class='alert-message'>
                    <div class='alert-stock-info'>
                        <div class='alert-stock-item'>
                            <span class='alert-stock-label'>Stok Tersedia:</span>
                            <span class='alert-stock-value'>" . $this->formatNumber($current_stock) . " kg</span>
                        </div>
                        <div class='alert-stock-item'>
                            <span class='alert-stock-label'>Anda Mencoba Mengurangi:</span>
                            <span class='alert-stock-value'>" . $this->formatNumber($jumlah) . " kg</span>
                        </div>
                    </div>
                    <div class='alert-suggestion'>
                        <span>Silakan kurangi jumlah atau lakukan restok terlebih dahulu.</span>
                    </div>
                </div>
            </div>
        ";
        return;
    }

    // Insert movement
    $query = "INSERT INTO stock_movements_beans (bean_id, jenis, jumlah, keterangan, tanggal)
              VALUES ('$bean_id', '$jenis', '$jumlah', '$keterangan', NOW())";
    
    if (!mysqli_query($this->conn, $query)) {
        $this->error = 'Gagal menyimpan movement.';
        return;
    }

    // Update stok
    $new_stock = ($jenis == 'masuk') ? $current_stock + $jumlah : $current_stock - $jumlah;
    
    if (!mysqli_query($this->conn, "UPDATE coffee_beans SET stok='$new_stock' WHERE id='$bean_id'")) {
        $this->error = 'Gagal update stok.';
        return;
    }

    //  SUCCESS
    $action_text = ($jenis == 'masuk') ? 'ditambahkan' : 'dikurangi';
    $this->success = "
        <div class='alert-content'>
            <div class='alert-title'>
                Stok Berhasil Diupdate!
            </div>
            <div class='alert-message'>
                Stok <strong>" . htmlspecialchars($nama_biji_kopi) . "</strong> berhasil $action_text.<br>
                <div class='alert-stock-info'>
                    <div class='alert-stock-item'>
                        <span class='alert-stock-label'>Stok Sebelumnya:</span>
                        <span class='alert-stock-value'>" . $this->formatNumber($current_stock) . " kg</span>
                    </div>
                    <div class='alert-stock-item'>
                        <span class='alert-stock-label'>Stok Sekarang:</span>
                        <span class='alert-stock-value'>" . $this->formatNumber($new_stock) . " kg</span>
                    </div>
                </div>
            </div>
        </div>
    ";
}

     // Build WHERE clause untuk filter movements
    
    private function buildMovementsWhereClause(): string {
        $where = "1=1";
        
        if ($this->filter_bean) {
            $where .= " AND sm.bean_id = " . (int) $this->filter_bean;
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

    //Load semua data yang dibutuhkan view
     
    private function loadData(): void {
        //  Load movements dengan filter
        $where = $this->buildMovementsWhereClause();
        $movements_query = mysqli_query($this->conn, "
            SELECT sm.*, cb.nama_biji_kopi
            FROM stock_movements_beans sm
            JOIN coffee_beans cb ON sm.bean_id = cb.id
            WHERE $where
            ORDER BY sm.tanggal DESC
            LIMIT 500
        ");

        $this->all_movements = [];
        while ($row = mysqli_fetch_assoc($movements_query)) {
            $this->all_movements[] = $row;
        }

        //  Calculate totals for print report
        foreach ($this->all_movements as $m) {
            if ($m['jenis'] == 'masuk') {
                $this->total_masuk += $m['jumlah'];
            } else {
                $this->total_keluar += $m['jumlah'];
            }
        }

        //  Bean name for report
        if ($this->filter_bean) {
            $b = mysqli_fetch_assoc(mysqli_query($this->conn, 
                "SELECT nama_biji_kopi FROM coffee_beans WHERE id = " . (int) $this->filter_bean));
            if ($b) {
                $this->bean_name = $b['nama_biji_kopi'];
            }
        }

        //  Period text for report
        if ($this->filter_date_from && $this->filter_date_to) {
            $this->period_text = date('d M Y', strtotime($this->filter_date_from)) . ' - ' . date('d M Y', strtotime($this->filter_date_to));
        } elseif ($this->filter_date_from) {
            $this->period_text = 'Dari ' . date('d M Y', strtotime($this->filter_date_from));
        } elseif ($this->filter_date_to) {
            $this->period_text = 'Sampai ' . date('d M Y', strtotime($this->filter_date_to));
        } else {
            $this->period_text = 'Semua Periode';
        }

        //  Jenis text
        if ($this->filter_jenis) {
            $this->jenis_text = ' | Jenis: ' . ucfirst($this->filter_jenis);
        }

        //  Coffee beans for dropdowns and info list
        $beans_query = mysqli_query($this->conn, "SELECT * FROM coffee_beans ORDER BY nama_biji_kopi ASC");
        while ($bean = mysqli_fetch_assoc($beans_query)) {
            $this->coffee_beans[] = $bean;
        }

        $beans_list_query = mysqli_query($this->conn, "SELECT id, nama_biji_kopi FROM coffee_beans ORDER BY nama_biji_kopi ASC");
        while ($bean = mysqli_fetch_assoc($beans_list_query)) {
            $this->coffee_beans_list[] = $bean;
        }
    }

    //Helper: Get badge class based on movement type
     
    public function getMovementBadge(string $jenis): string {
        return $jenis == 'masuk' ? 'badge-success' : 'badge-danger';
    }

    //Helper: Get icon for movement type
    public function getMovementIcon(string $jenis): string {
        return $jenis == 'masuk' ? 'arrow-down' : 'arrow-up';
    }

    //Helper: Get sign for movement
    public function getMovementSign(string $jenis): string {
        return $jenis == 'masuk' ? '+' : '-';
    }

    //Helper: Get text color for movement
    public function getMovementTextColor(string $jenis): string {
        return $jenis == 'masuk' ? 'text-success' : 'text-danger';
    }

    //Helper: Get badge class based on stock level
    public function getStockBadge(float $stok): string {
        return $stok < 5 ? 'badge-danger' : 'badge-success';
    }

    //Helper: Format tanggal
    public function formatDate(string $datetime, string $format = 'd/m/Y H:i'): string {
        return date($format, strtotime($datetime));
    }

    
    public function formatDatePrint(string $datetime): string {
        return date('d M Y H:i', strtotime($datetime));
    }

    //Helper: Format angka dengan 1 desimal
    public function formatNumber(float $number): string {
        return number_format($number, 1, ',', '.');
    }

    //Helper: Get current admin name for signature
    public function getCurrentAdminName(): string {
        return htmlspecialchars($_SESSION['nama'] ?? $_SESSION['username'] ?? 'Admin');
    }

    //Render print view
    public function renderPrintView(): void {
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Laporan Stok Biji Kopi - TEFA COFFEE</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <link rel="stylesheet" href="css/receipt.css">
        </head>
        <body>
            <div class="print-actions">
                <button onclick="window.print()" class="btn-print">
                    <i class="fas fa-print"></i> Cetak Laporan
                </button>
                <a href="stock.php" class="btn-close">
                    <i class="fas fa-times"></i> Kembali
                </a>
            </div>
            <div class="container">
                <div class="report-header">
                    <img src="../assets/images/logopolije.png" alt="Logo" class="logo" onerror="this.style.display='none'">
                    <h1>TEFA COFFEE</h1>
                    <h2>LAPORAN STOK BIJI KOPI</h2>
                    <div class="report-info">
                        <table>
                            <tr>
                                <td>Biji Kopi</td>
                                <td>: <?= htmlspecialchars($this->bean_name) ?></td>
                            </tr>
                            <tr>
                                <td>Periode</td>
                                <td>: <?= htmlspecialchars($this->period_text) ?><?= htmlspecialchars($this->jenis_text) ?></td>
                            </tr>
                            <tr>
                                <td>Total Data</td>
                                <td>: <?= count($this->all_movements) ?> movement</td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php if (count($this->all_movements) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 5%;">No</th>
                                <th style="width: 18%;">Tanggal</th>
                                <th style="width: 30%;">Biji Kopi</th>
                                <th style="width: 12%;">Jenis</th>
                                <th style="width: 15%;">Jumlah</th>
                                <th style="width: 20%;">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1;
                            foreach ($this->all_movements as $m): ?>
                                <tr>
                                    <td class="text-center"><?= $no++ ?></td>
                                    <td><?= $this->formatDatePrint($m['tanggal']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($m['nama_biji_kopi']) ?>
                                        <?php if (!empty($m['varietas']) || !empty($m['asal'])): ?>
                                            <br><small style="font-size: 9pt; color: #666;">
                                                <?= !empty($m['varietas']) ? htmlspecialchars($m['varietas']) : '' ?>
                                                <?= !empty($m['asal']) ? ' • ' . htmlspecialchars($m['asal']) : '' ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?= ucfirst($m['jenis']) ?></td>
                                    <td class="text-right">
                                        <?= $this->getMovementSign($m['jenis']) ?> <?= $this->formatNumber($m['jumlah']) ?> kg
                                    </td>
                                    <td><?= htmlspecialchars($m['keterangan'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="summary-box">
                        <h3><i class="fas fa-chart-bar"></i> Ringkasan Stok Biji Kopi</h3>
                        <table class="summary-table">
                            <tr>
                                <td>Total Stok Masuk</td>
                                <td>+<?= $this->formatNumber($this->total_masuk) ?> kg</td>
                            </tr>
                            <tr>
                                <td>Total Stok Keluar</td>
                                <td>-<?= $this->formatNumber($this->total_keluar) ?> kg</td>
                            </tr>
                            <tr class="total-row">
                                <td>Netto Perubahan</td>
                                <td><?= ($this->total_masuk - $this->total_keluar) >= 0 ? '+' : '' ?><?= $this->formatNumber($this->total_masuk - $this->total_keluar) ?> kg</td>
                            </tr>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <h3>Tidak Ada Data</h3>
                        <p>Tidak ada riwayat movement yang sesuai dengan filter yang dipilih</p>
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
                    setTimeout(function () { window.location.href = 'stock.php'; }, 1000);
                };
                window.onafterprint = function () { window.location.href = 'stock.php'; };
            </script>
        </body>
        </html>
        <?php
        exit;
    }
}

//  Inisialisasi Controller
$stock = new StockController($conn);

// Render print view jika mode print
if ($stock->print_mode) {
    $stock->renderPrintView();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stok Biji Kopi - TEFA Coffee</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/stock.css">
</head>
<body>
    <!-- Top Header -->
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
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
            <li><a href="products.php"><i class="fas fa-box"></i><span>Kelola Produk</span></a></li>
            <li><a href="stock.php" class="active"><i class="fa-solid fa-pen-to-square"></i><span>Data Stok biji kopi</span></a>
            </li>
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
                <h1 class="page-title">Manajemen Stok Biji Kopi</h1>
            </div>

            <!-- Alert -->
            <?php if ($stock->success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i>
                    <?= $stock->success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($stock->error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $stock->error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <div class="row">
                <!-- Form Input Stok Biji Kopi -->
                <div class="col-md-4 mb-4">
                    <div class="card-custom">
                        <div class="card-header-custom">
                            <span>Input Stok Biji Kopi</span>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Jenis Biji Kopi</label>
                                    <input type="text" name="nama_biji_kopi" class="form-control" required
                                        placeholder="Contoh: Arabica Gayo">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Jenis Movement</label>
                                    <select name="jenis" class="form-control" required>
                                        <option value="masuk">Stok Masuk</option>
                                        <option value="keluar">Stok Keluar</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Jumlah (kg)</label>
                                    <input type="number" name="jumlah" class="form-control" step="0.1" min="0.1"
                                        required placeholder="0.0">
                                    <small class="form-text">Masukkan jumlah dalam kilogram (kg)</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Keterangan</label>
                                    <textarea name="keterangan" class="form-control" rows="3"
                                        placeholder="Keterangan tambahan..."></textarea>
                                </div>
                                <button type="submit" name="add_stock" class="btn-custom btn-primary w-100">
                                    <i class="fas fa-save"></i>Simpan Movement Stok
                                </button>
                            </form>
                        </div>
                    </div>
                    <!-- Info Stok Saat Ini -->
                    <div class="card-custom mt-3">
                        <div class="card-header-custom">
                            <span>Info Stok Tersedia</span>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled mb-0" style="max-height: 200px; overflow-y: auto;">
                                <?php if (!empty($stock->coffee_beans)): ?>
                                    <?php foreach ($stock->coffee_beans as $bean): ?>
                                        <li class="d-flex justify-content-between py-2 border-bottom">
                                            <span class="fw-semibold"><?= htmlspecialchars($bean['nama_biji_kopi']) ?></span>
                                            <span class="badge-custom <?= $stock->getStockBadge($bean['stok']) ?>">
                                                <?= $stock->formatNumber($bean['stok']) ?> kg
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li class="text-muted py-2">Belum ada data biji kopi</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Riwayat Stok Biji Kopi -->
                <div class="col-md-8">
                    <div class="card-custom">
                        <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <span>Riwayat Stok Biji Kopi</span>
                            <div class="d-flex gap-2">
                                <button class="btn-custom btn-coffee btn-sm" onclick="openPrintReport()">
                                    <i class="fas fa-file-pdf"></i> Cetak Laporan
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <!-- Active Filters Indicator -->
                            <div id="activeFilters" class="filter-active-indicator mx-3 mt-3 mb-0">
                                <i class="fas fa-filter text-primary"></i>
                                <span class="flex-grow-1" id="activeFiltersText"></span>
                                <button class="btn btn-sm btn-secondary" onclick="resetFilters()">
                                    <i class="fas fa-times"></i> Reset
                                </button>
                            </div>
                            <!-- Filter Form -->
                            <form id="filterForm" method="GET" class="filter-section mx-3 mt-3 mb-0">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label small"><i class="fas fa-seedling me-1 text-muted"></i>Filter Biji Kopi</label>
                                        <select name="filter_bean" id="filter_bean" class="form-control form-control-sm" onchange="applyFilter()">
                                            <option value="">Semua Biji Kopi</option>
                                            <?php foreach ($stock->coffee_beans_list as $b): ?>
                                                <option value="<?= $b['id'] ?>" <?= $stock->filter_bean == $b['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($b['nama_biji_kopi']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small"><i class="fas fa-exchange-alt me-1 text-muted"></i>Jenis</label>
                                        <select name="filter_jenis" id="filter_jenis" class="form-control form-control-sm" onchange="applyFilter()">
                                            <option value="">Semua</option>
                                            <option value="masuk" <?= $stock->filter_jenis == 'masuk' ? 'selected' : '' ?>>Masuk</option>
                                            <option value="keluar" <?= $stock->filter_jenis == 'keluar' ? 'selected' : '' ?>>Keluar</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small"><i class="fas fa-calendar-alt me-1 text-muted"></i>Dari</label>
                                        <input type="date" name="filter_date_from" id="filter_date_from" class="form-control form-control-sm"
                                            value="<?= htmlspecialchars($stock->filter_date_from) ?>" onchange="applyFilter()">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small"><i class="fas fa-calendar-check me-1 text-muted"></i>Sampai</label>
                                        <input type="date" name="filter_date_to" id="filter_date_to" class="form-control form-control-sm"
                                            value="<?= htmlspecialchars($stock->filter_date_to) ?>" onchange="applyFilter()">
                                    </div>
                                </div>
                            </form>
                            <div class="table-responsive">
                                <table class="table table-custom">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Biji Kopi</th>
                                            <th>Jenis</th>
                                            <th>Jumlah</th>
                                            <th>Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody id="historyTableBody">
                                        <?php if (count($stock->all_movements) > 0): ?>
                                            <?php foreach (array_slice($stock->all_movements, 0, 10) as $m): ?>
                                                <tr>
                                                    <td data-label="Tanggal"><?= $stock->formatDate($m['tanggal']) ?></td>
                                                    <td data-label="Biji Kopi">
                                                        <div class="fw-semibold"><?= htmlspecialchars($m['nama_biji_kopi']) ?></div>
                                                        <?php if (!empty($m['varietas']) || !empty($m['asal'])): ?>
                                                            <small class="bean-info">
                                                                <?= !empty($m['varietas']) ? htmlspecialchars($m['varietas']) : '' ?>
                                                                <?= !empty($m['asal']) ? '• ' . htmlspecialchars($m['asal']) : '' ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td data-label="Jenis">
                                                        <span class="badge-custom <?= $stock->getMovementBadge($m['jenis']) ?>">
                                                            <i class="fas fa-<?= $stock->getMovementIcon($m['jenis']) ?>"></i>
                                                            <?= strtoupper($m['jenis']) ?>
                                                        </span>
                                                    </td>
                                                    <td data-label="Jumlah" class="fw-semibold">
                                                        <?= $stock->formatNumber($m['jumlah']) ?> kg</td>
                                                    <td data-label="Keterangan" class="text-muted">
                                                        <?= htmlspecialchars($m['keterangan']) ?: '-' ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-4 text-muted">
                                                    <i class="fas fa-inbox"></i>Belum ada riwayat movement stok
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if (count($stock->all_movements) > 10): ?>
                                <div class="text-center mt-3">
                                    <small class="text-muted">Menampilkan 10 dari <?= count($stock->all_movements) ?> data</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/stock.js"></script>
</body>
</html>