<?php

require_once '../config/config.php';
checkRole('manager');

class ManagerDashboardController {
    private $conn;
    
    // Public properties untuk akses di view
    public $total_transaksi = 0;
    public $total_pendapatan = 0;
    public $total_produk = 0;
    public $total_biji_kopi = 0;
    public $stok_kritis_count = 0;
    public $stok_kritis_products = [];
    public $beans_kritis = [];
    public $coffee_beans = [];
    public $sales_data = [];
    public $chart_labels = [];
    public $chart_data = [];

    public function __construct($connection) {
        $this->conn = $connection;
        $this->loadData();
    }

    private function loadData(): void {
        // Statistics - Transactions & Revenue (sama seperti admin)
        $this->total_transaksi = (int) (mysqli_fetch_assoc(mysqli_query($this->conn, 
            "SELECT COUNT(*) as total FROM transactions WHERE status_pembayaran IN ('lunas','dikonfirmasi')"))['total'] ?? 0);
        
        $this->total_pendapatan = (float) (mysqli_fetch_assoc(mysqli_query($this->conn, 
            "SELECT SUM(total_harga) as total FROM transactions WHERE status_pembayaran IN ('lunas','dikonfirmasi')"))['total'] ?? 0);
        
        $this->total_produk = (int) (mysqli_fetch_assoc(mysqli_query($this->conn, 
            "SELECT SUM(stok) as total FROM products"))['total'] ?? 0);

        //  Stok Kritis Products (< 20)
        $stok_kritis_query = mysqli_query($this->conn, "SELECT * FROM products WHERE stok < 20");
        while ($row = mysqli_fetch_assoc($stok_kritis_query)) {
            $this->stok_kritis_products[] = $row;
        }

        //  Coffee Beans Statistics (sumber data sama dengan admin/stock.php)
        $beans_query = mysqli_query($this->conn, "SELECT * FROM coffee_beans WHERE stok > 0 ORDER BY nama_biji_kopi ASC");
        while ($bean = mysqli_fetch_assoc($beans_query)) {
            $this->coffee_beans[] = $bean;
        }
        
        $this->total_biji_kopi = (float) (mysqli_fetch_assoc(mysqli_query($this->conn, 
            "SELECT SUM(stok) as total FROM coffee_beans WHERE stok > 0"))['total'] ?? 0);

        //  Beans Kritis (< 10 kg)
        $beans_kritis_query = mysqli_query($this->conn, "SELECT * FROM coffee_beans WHERE stok < 10 AND stok > 0");
        while ($bean = mysqli_fetch_assoc($beans_kritis_query)) {
            $this->beans_kritis[] = $bean;
        }

        //  Total stok kritis (products + beans)
        $this->stok_kritis_count = count($this->stok_kritis_products) + count($this->beans_kritis);

        //  Sales per product (sama seperti admin)
        $sales_query = mysqli_query($this->conn, "
            SELECT p.nama_produk, SUM(td.quantity) as total_terjual, SUM(td.subtotal) as total_pendapatan
            FROM transaction_details td
            JOIN products p ON td.product_id = p.id
            JOIN transactions t ON td.transaction_id = t.id
            WHERE t.status_pembayaran IN ('lunas','dikonfirmasi')
            GROUP BY p.id
            ORDER BY total_terjual DESC
        ");
        
        $this->sales_data = mysqli_fetch_all($sales_query, MYSQLI_ASSOC);
        
        //  Prepare chart data
        $this->chart_labels = array_column($this->sales_data, 'nama_produk');
        $this->chart_data = array_column($this->sales_data, 'total_terjual');
    }

    // Helper: Get badge class based on stock level for products
    public function getProductStockBadge(int $stok): string {
        return $stok < 20 ? 'badge-danger' : 'badge-success';
    }

    //Helper: Get badge class for coffee beans stock
    public function getBeanStockBadge(float $stok): string {
        return $stok < 10 ? 'stock-badge' : 'stock-badge success';
    }

    //Helper: Format Rupiah
    public function formatRupiah(float $amount): string {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    // Helper: Format number with 1 decimal for kg
    public function formatKg(float $amount): string {
        return number_format($amount, 1, ',', '.');
    }

    
    public function getChartColorsJson(): string {
        $colors = ['#2C1810', '#5D4037', '#A67C52', '#2E5D4F', '#A8D5BA', '#8D6E63', '#1B4D3E'];
        return json_encode($colors);
    }

    
    public function getChartLabelsJson(): string {
        return json_encode($this->chart_labels);
    }

    
    public function getChartDataJson(): string {
        return json_encode($this->chart_data);
    }

    
    public function hasSalesData(): bool {
        return !empty($this->sales_data);
    }

    
    public function hasCoffeeBeans(): bool {
        return !empty($this->coffee_beans);
    }
}

//  Inisialisasi Controller
$manager = new ManagerDashboardController($conn);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Manager - TEFA Coffee</title>
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
                <!--  Hamburger Menu - RIGHT SIDE -->
                <button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle Menu">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <ul class="sidebar-menu">
            <li>
                <a href="dashboard.php" class="active">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="reports.php">
                    <i class="fas fa-file-alt"></i>
                    <span>Laporan</span>
                </a>
            </li>
            <li>
                <a href="analytics.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Analisis</span>
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
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">Dashboard Manager </h1>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                    <div class="stat-content">
                        <div class="stat-number"><?= $manager->total_transaksi ?></div>
                        <div class="stat-label">Total Transaksi</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-rupiah-sign"></i></div>
                    <div class="stat-content">
                        <div class="stat-number text-rupiah"><?= $manager->formatRupiah($manager->total_pendapatan) ?></div>
                        <div class="stat-label">Pendapatan</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-boxes-stacked"></i></div>
                    <div class="stat-content">
                        <div class="stat-number"><?= $manager->total_produk ?></div>
                        <div class="stat-label">Stok Produk</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-seedling"></i></div>
                    <div class="stat-content">
                        <div class="stat-number"><?= $manager->formatKg($manager->total_biji_kopi) ?> kg</div>
                        <div class="stat-label">Stok Biji Kopi</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-triangle-exclamation"></i></div>
                    <div class="stat-content">
                        <div class="stat-number" style="color: var(--danger-text);">
                            <?= $manager->stok_kritis_count ?>
                        </div>
                        <div class="stat-label">Stok Kritis</div>
                    </div>
                </div>
            </div>

            <!-- Charts & Stock Row -->
            <div class="row">
                <!-- Sales Chart -->
                <div class="col-lg-6 mb-4">
                    <div class="card-custom">
                        <div class="card-header-custom">
                            <span> Penjualan per Produk</span>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="salesChart"></canvas>
                            </div>
                            <div class="chart-legend" id="chartLegend"></div>
                        </div>
                    </div>
                </div>

                <!-- Coffee Beans Stock -->
                <div class="col-lg-6 mb-4">
                    <div class="card-custom">
                        <div class="card-header-custom">
                            <span>Stok Biji Kopi</span>
                        </div>
                        <div class="card-body">
                            <?php if ($manager->hasCoffeeBeans()): ?>
                                <ul class="stock-list">
                                    <?php foreach ($manager->coffee_beans as $bean): ?>
                                        <li class="stock-item">
                                            <div>
                                                <span class="stock-name"><?= htmlspecialchars($bean['nama_biji_kopi']) ?></span>
                                                <?php if (!empty($bean['varietas']) || !empty($bean['asal'])): ?>
                                                    <small class="text-muted d-block" style="font-size: 0.78rem;">
                                                        <?= !empty($bean['varietas']) ? htmlspecialchars($bean['varietas']) : '' ?>
                                                        <?= !empty($bean['varietas']) && !empty($bean['asal']) ? ' • ' : '' ?>
                                                        <?= !empty($bean['asal']) ? htmlspecialchars($bean['asal']) : '' ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                            <span class="<?= $manager->getBeanStockBadge($bean['stok']) ?>">
                                                <i class="fas fa-circle" style="font-size: 5px;"></i>
                                                <?= $manager->formatKg($bean['stok']) ?> kg
                                            </span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-seedling"></i>
                                    <p>Belum ada data biji kopi</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sales Detail Table -->
            <div class="card-custom">
                <div class="card-header-custom">
                    <span> Detail Penjualan Produk</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-custom">
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th>Terjual</th>
                                    <th>Pendapatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($manager->sales_data)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-4">
                                            <div class="empty-state">
                                                <i class="fas fa-inbox"></i>
                                                <p>Belum ada data penjualan</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($manager->sales_data as $sales): ?>
                                        <tr>
                                            <td data-label="Produk" class="fw-semibold">
                                                <?= htmlspecialchars($sales['nama_produk']) ?>
                                            </td>
                                            <td data-label="Terjual"><?= (int) $sales['total_terjual'] ?> unit</td>
                                            <td data-label="Pendapatan" class="text-rupiah">
                                                <?= $manager->formatRupiah((float) $sales['total_pendapatan']) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // 🆕 Hamburger Menu Toggle
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    function toggleSidebar() {
        sidebar.classList.toggle('active');
        sidebarOverlay.classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
    }

    hamburgerBtn.addEventListener('click', toggleSidebar);
    sidebarOverlay.addEventListener('click', toggleSidebar);

    // Close sidebar when clicking a menu link on mobile
    document.querySelectorAll('.sidebar-menu a').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                toggleSidebar();
            }
        });
    });

    // Handle window resize
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    });

    // Chart data from PHP via helpers
    const chartLabels = <?= $manager->getChartLabelsJson() ?>;
    const chartData = <?= $manager->getChartDataJson() ?>;
    const chartColors = <?= $manager->getChartColorsJson() ?>;

    const ctx = document.getElementById('salesChart');
    if (ctx && chartLabels.length > 0) {
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: chartLabels,
                datasets: [{
                    data: chartData,
                    backgroundColor: chartColors.slice(0, chartLabels.length),
                    borderColor: '#ffffff',
                    borderWidth: 3,
                    hoverOffset: 12,
                    hoverBorderWidth: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(44, 24, 16, 0.95)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        padding: 14,
                        cornerRadius: 10,
                        displayColors: true,
                        callbacks: {
                            label: function (context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                return `${label}: ${value} unit`;
                            }
                        }
                    }
                },
                animation: {
                    animateScale: true,
                    animateRotate: true,
                    duration: 1200,
                    easing: 'easeOutQuart'
                }
            }
        });

        const legendContainer = document.getElementById('chartLegend');
        if (legendContainer && chartLabels.length > 0) {
            legendContainer.innerHTML = chartLabels.map((label, index) => `
                <div class="legend-item">
                    <div class="legend-color" style="background: ${chartColors[index % chartColors.length]}"></div>
                    <span>${label}</span>
                </div>
            `).join('');
        }
    } else if (ctx) {
        const canvas = ctx.getContext('2d');
        canvas.font = '14px Inter, sans-serif';
        canvas.fillStyle = '#5a5a5a';
        canvas.textAlign = 'center';
        canvas.fillText('Belum ada data penjualan', canvas.canvas.width / 2, canvas.canvas.height / 2);
    }

    // Print optimization
    window.addEventListener('beforeprint', () => {
        document.querySelectorAll('.card-custom').forEach(card => {
            card.style.boxShadow = 'none';
            card.style.transform = 'none';
        });
    });
</script>
</body>
</html>