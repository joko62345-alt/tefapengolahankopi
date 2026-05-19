<?php
require_once '../config/config.php';
checkRole('admin');

class DashboardController {
    private $conn;
    private $statusLunasClause = "IN ('lunas', 'dikonfirmasi')";
    
    // Properties untuk data view
    public $totalProduk = 0;
    public $totalTransaksi = 0;
    public $totalPendapatan = 0;
    public $stokRendah = 0;
    public $transaksiPending = 0;
    public $belumDiambil = 0;
    public $recentTransactions = [];
    public $lowStockProducts = [];

    public function __construct($connection) {
        $this->conn = $connection;
        $this->loadData();
    }

    // Load semua data dashboard dalam satu metode
     
    private function loadData(): void {
        $this->totalProduk = $this->getTotalProduk();
        $this->totalTransaksi = $this->getTotalTransaksi();
        $this->totalPendapatan = $this->getTotalPendapatan();
        $this->stokRendah = $this->getStokRendah();
        $this->transaksiPending = $this->getTransaksiPending();
        $this->belumDiambil = $this->getBelumDiambil();
        $this->recentTransactions = $this->getRecentTransactions(5);
        $this->lowStockProducts = $this->getLowStockProducts(20, 5);
    }

    private function getTotalProduk(): int {
        $result = mysqli_query($this->conn, "SELECT COUNT(*) as total FROM products");
        return (int) (mysqli_fetch_assoc($result)['total'] ?? 0);
    }

    private function getTotalTransaksi(): int {
        $result = mysqli_query($this->conn, "SELECT COUNT(*) as total FROM transactions");
        return (int) (mysqli_fetch_assoc($result)['total'] ?? 0);
    }

    private function getTotalPendapatan(): float {
        $clause = $this->statusLunasClause;
        $result = mysqli_query($this->conn, "
            SELECT SUM(total_harga) as total 
            FROM transactions 
            WHERE status_pembayaran $clause
        ");
        return (float) (mysqli_fetch_assoc($result)['total'] ?? 0);
    }

    private function getStokRendah(int $threshold = 20): int {
        $result = mysqli_query($this->conn, "
            SELECT COUNT(*) as total 
            FROM products 
            WHERE stok < $threshold
        ");
        return (int) (mysqli_fetch_assoc($result)['total'] ?? 0);
    }

    private function getTransaksiPending(): int {
        $result = mysqli_query($this->conn, "
            SELECT COUNT(*) as total 
            FROM transactions 
            WHERE status_pembayaran = 'pending'
        ");
        return (int) (mysqli_fetch_assoc($result)['total'] ?? 0);
    }

    private function getBelumDiambil(): int {
        $clause = $this->statusLunasClause;
        $result = mysqli_query($this->conn, "
            SELECT COUNT(*) as total 
            FROM transactions 
            WHERE status_pengambilan = 'belum_diambil' 
            AND status_pembayaran $clause
        ");
        return (int) (mysqli_fetch_assoc($result)['total'] ?? 0);
    }

    private function getRecentTransactions(int $limit = 5): array {
        $data = [];
        $query = mysqli_query($this->conn, "
            SELECT t.*, u.nama_lengkap, u.telepon 
            FROM transactions t 
            JOIN users u ON t.user_id = u.id 
            ORDER BY t.tanggal_transaksi DESC 
            LIMIT $limit
        ");
        while ($row = mysqli_fetch_assoc($query)) {
            $data[] = $row;
        }
        return $data;
    }

    private function getLowStockProducts(int $threshold = 20, int $limit = 5): array {
        $data = [];
        $query = mysqli_query($this->conn, "
            SELECT * FROM products 
            WHERE stok < $threshold 
            ORDER BY stok ASC 
            LIMIT $limit
        ");
        while ($row = mysqli_fetch_assoc($query)) {
            $data[] = $row;
        }
        return $data;
    }

    //Helper: Format status pembayaran untuk badge
     
    public function getStatusBadge(string $status): string {
        if (in_array($status, ['lunas', 'dikonfirmasi'])) {
            return '<span class="badge-custom badge-success"><i class="fas fa-check"></i> Lunas</span>';
        }
        return '<span class="badge-custom badge-warning"><i class="fas fa-hourglass"></i> Pending</span>';
    }

    //Format tanggal
     
    public function formatDate(string $datetime): string {
    return date('d/m/Y H:i', strtotime($datetime));
}

    // Format Rupiah
     
    public function formatRupiah(float $amount): string {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}

//  Inisialisasi Controller
$dashboard = new DashboardController($conn);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - TEFA Coffee</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/dashboard.css">
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
                <!-- Hamburger Menu - RIGHT SIDE -->
                <button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle Menu">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </div>
    <!-- Sidebar -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="sidebar" id="sidebar">
        <ul class="sidebar-menu">
            <li>
                <a href="dashboard.php" class="active">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="products.php">
                    <i class="fas fa-box"></i>
                    <span>Kelola Produk</span>
                </a>
            </li>
            <li>
                <a href="stock.php">
                    <i class="fa-solid fa-pen-to-square"></i>
                    <span>Data Stok biji kopi</span>
                </a>
            </li>
            <li>
                <a href="inventory.php">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Inventaris</span>
                </a>
            </li>
            <li>
                <a href="transactions.php">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Transaksi</span>
                </a>
            </li>
            <div class="sidebar-divider"></div>
            <li>
                <a href="../logout.php" style="color: #ef9a9a;">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>
    <!-- Main Content Wrapper -->
    <div class="main-wrapper">
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">
                    Panel Dashboard Admin
                </h1>
            </div>
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <!-- Total Produk -->
                <div class="stat-card" onclick="window.location.href='products.php'">
                    <div class="stat-icon"><i class="fas fa-box"></i></div>
                    <div class="stat-content">
                        <div class="stat-number"><?= (int) $dashboard->totalProduk ?></div>
                        <div class="stat-label">Total Produk</div>
                    </div>
                </div>
                <!-- Total Transaksi -->
                <div class="stat-card" onclick="window.location.href='transactions.php'">
                    <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                    <div class="stat-content">
                        <div class="stat-number"><?= (int) $dashboard->totalTransaksi ?></div>
                        <div class="stat-label">Total Transaksi</div>
                    </div>
                </div>
                <!-- Total Pendapatan -->
                <div class="stat-card" onclick="window.location.href='transactions.php'">
                    <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
                    <div class="stat-content">
                        <div class="stat-number text-rupiah"><?= $dashboard->formatRupiah($dashboard->totalPendapatan) ?>
                        </div>
                        <div class="stat-label">Total Pendapatan</div>
                    </div>
                </div>
                <!-- Stok Rendah -->
                <div class="stat-card" onclick="window.location.href='products.php'">
                    <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="stat-content">
                        <div class="stat-number" style="color: var(--danger-text);"><?= (int) $dashboard->stokRendah ?></div>
                        <div class="stat-label">Stok Rendah</div>
                    </div>
                </div>
                <!-- Transaksi Pending -->
                <div class="stat-card" onclick="window.location.href='transactions.php?filter_pembayaran=pending'">
                    <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                    <div class="stat-content">
                        <div class="stat-number" style="color: #c2410c;"><?= (int) $dashboard->transaksiPending ?></div>
                        <div class="stat-label">Pending Payment</div>
                    </div>
                </div>
                <!-- Belum Diambil -->
                <div class="stat-card"
                    onclick="window.location.href='transactions.php?filter_pengambilan=belum_diambil'">
                    <div class="stat-icon"><i class="fas fa-box-open"></i></div>
                    <div class="stat-content">
                        <div class="stat-number" style="color: #9333ea;"><?= (int) $dashboard->belumDiambil ?></div>
                        <div class="stat-label">Belum Diambil</div>
                    </div>
                </div>
            </div>
            <div class="row">
                <!-- Recent Transactions -->
                <div class="col-lg-8 mb-4">
                    <div class="card-custom">
                        <div class="card-header-custom">
                            <span>Transaksi Terbaru</span>
                            <a href="transactions.php" class="btn-custom btn-secondary btn-sm"
                                style="display:inline-flex;align-items:center;gap:0.5rem;padding:0.625rem 1.25rem;border-radius:8px;font-weight:500;font-size:0.9rem;cursor:pointer;transition:all 0.15s ease;border:none;text-decoration:none;background:#f3f4f6;color:var(--text-primary);">
                                Lihat Semua <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-custom">
                                    <thead>
                                        <tr>
                                            <th>Kode</th>
                                            <th>Customer</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                            <th>Tanggal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($dashboard->recentTransactions) > 0): ?>
                                            <?php foreach ($dashboard->recentTransactions as $t): ?>
                                                <tr>
                                                    <td data-label="Kode" class="fw-semibold">
                                                        <?= htmlspecialchars($t['kode_transaksi']) ?>
                                                    </td>
                                                    <td data-label="Customer">
                                                        <span
                                                            class="fw-semibold"><?= htmlspecialchars($t['nama_lengkap']) ?></span>
                                                        <?php if (!empty($t['telepon'])): ?>
                                                            <br><small class="text-muted"
                                                                style="font-size:0.78rem;"><?= htmlspecialchars($t['telepon']) ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td data-label="Total" class="text-rupiah">
                                                        <strong><?= $dashboard->formatRupiah((float) $t['total_harga']) ?></strong>
                                                    </td>
                                                    <td data-label="Status">
                                                        <?= $dashboard->getStatusBadge($t['status_pembayaran']) ?>
                                                    </td>
                                                    <td data-label="Tanggal">
                                                        <?= $dashboard->formatDate($t['tanggal_transaksi']) ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center py-4">
                                                    <div class="empty-state">
                                                        <i class="fas fa-inbox"></i>
                                                        <p class="mb-0">Belum ada transaksi</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Low Stock Alert -->
                <div class="col-lg-4">
                    <!-- Stok Menipis -->
                    <div class="card-custom">
                        <div class="card-header-custom">
                            <span>Stok Menipis</span>
                            <a href="products.php?filter=low_stock" class="btn-custom btn-secondary btn-sm"
                                style="display:inline-flex;align-items:center;gap:0.5rem;padding:0.625rem 1.25rem;border-radius:8px;font-weight:500;font-size:0.9rem;cursor:pointer;transition:all 0.15s ease;border:none;text-decoration:none;background:#f3f4f6;color:var(--text-primary);">
                                Kelola <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (count($dashboard->lowStockProducts) > 0): ?>
                                <?php foreach ($dashboard->lowStockProducts as $p): ?>
                                    <div class="stock-alert <?= $p['stok'] <= 5 ? 'critical' : '' ?>">
                                        <i class="fas fa-box <?= $p['stok'] <= 5 ? 'text-danger' : 'text-warning' ?>"></i>
                                        <div style="flex: 1;">
                                            <div class="product-name"><?= htmlspecialchars($p['nama_produk']) ?></div>
                                            <small class="text-muted"
                                                style="font-size:0.78rem;"><?= htmlspecialchars($p['kategori']) ?></small>
                                        </div>
                                        <div class="stock-value"><?= (int) $p['stok'] ?> unit</div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-check-circle"></i>
                                    <p class="mb-0">Semua stok aman </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- aksi cepat -->
                    <div class="quick-actions">
                        <div class="quick-actions-title">
                            <span>Aksi Cepat</span>
                        </div>
                        <div class="quick-actions-list">
                            <a href="products.php?action=add" class="quick-action-btn primary">
                                <i class="fas fa-plus-circle"></i>
                                <span>Tambah Produk Baru</span>
                            </a>
                            <a href="stock.php" class="quick-action-btn secondary">
                                <i class="fa-solid fa-pen-to-square"></i>
                                <span>Input Stok Masuk/keluar</span>
                            </a>
                            <a href="transactions.php?filter_pembayaran=pending" class="quick-action-btn warning">
                                <i class="fas fa-money-check-alt"></i>
                                <span>Konfirmasi pesanan</span>
                                <?php if ($dashboard->transaksiPending > 0): ?>
                                    <span class="badge"><?= $dashboard->transaksiPending ?></span>
                                <?php endif; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/dashboard.js"></script>
</body>
</html>