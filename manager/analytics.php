<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

abstract class BaseAnalytics
{
    protected mysqli $conn;
    protected string $dateFrom;
    protected string $dateTo;
    protected array $rawData = [];
    protected array $chartData = [];
    protected array $labels = [];
    protected string $emptyMessage = 'Tidak ada data pada periode ini';
    protected string $chartType = 'line';
    protected string $title = '';
    
    public function __construct(mysqli $conn, string $dateFrom, string $dateTo)
    {
        $this->conn = $conn;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->loadData();
        $this->processData();
    }
    
    abstract protected function buildQuery(): string;
    abstract protected function mapRow(array $row): void;
    abstract protected function prepareChartData(): void;
    
    final public function loadData(): void
    {
        $query = $this->buildQuery();
        $result = $this->conn->query($query);
        
        $this->rawData = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $this->mapRow($row);
            }
        }
    }
    
    final protected function processData(): void
    {
        $this->prepareChartData();
        $this->prepareLabels();
    }
    
    protected function prepareLabels(): void
    {
        $this->labels = array_keys($this->chartData);
    }
    
    public function getChartData(): array { return $this->chartData; }
    public function getLabels(): array { return $this->labels; }
    public function hasData(): bool { return !empty($this->rawData); }
    public function getEmptyMessage(): string { return $this->emptyMessage; }
    public function getChartType(): string { return $this->chartType; }
    public function getTitle(): string { return $this->title; }
    
    protected function escape(string $value): string
    {
        return $this->conn->real_escape_string($value);
    }
    
    protected function formatDate(string $date, string $format = 'd/m'): string
    {
        return date($format, strtotime($date));
    }
}


// CONCRETE ANALYTICS CLASSES

class SalesAnalytics extends BaseAnalytics
{
    protected array $transaksi = [];
    protected array $penjualan = [];
    
    public function __construct(mysqli $conn, string $dateFrom, string $dateTo)
    {
        $this->title = ' Penjualan & Pendapatan';
        $this->chartType = 'line';
        $this->emptyMessage = 'Belum ada data penjualan';
        parent::__construct($conn, $dateFrom, $dateTo);
    }
    
    protected function buildQuery(): string
    {
        $from = $this->escape($this->dateFrom);
        $to = $this->escape($this->dateTo);
        
        return "
            SELECT 
                DATE(tanggal_transaksi) as tanggal,
                COUNT(*) as total_transaksi,
                SUM(total_harga) as total_penjualan
            FROM transactions 
            WHERE status_pembayaran IN ('lunas', 'dikonfirmasi')
            AND DATE(tanggal_transaksi) BETWEEN '$from' AND '$to'
            GROUP BY DATE(tanggal_transaksi)
            ORDER BY tanggal ASC
        ";
    }
    
    protected function mapRow(array $row): void
    {
        $tanggal = $row['tanggal'];
        $this->rawData[] = $row;
        $this->transaksi[$tanggal] = (int)$row['total_transaksi'];
        $this->penjualan[$tanggal] = (float)$row['total_penjualan'];
    }
    
    protected function prepareChartData(): void
    {
        $this->chartData = [
            'transaksi' => array_values($this->transaksi),
            'penjualan' => array_values($this->penjualan)
        ];
    }
    
    protected function prepareLabels(): void
    {
        $this->labels = array_map(
            fn($date) => $this->formatDate($date),
            array_keys($this->transaksi)
        );
    }
    
    public function getTransaksiData(): array { return $this->transaksi; }
    public function getPenjualanData(): array { return $this->penjualan; }
}

class ProductStockAnalytics extends BaseAnalytics
{
    protected array $produkMasuk = [];
    protected array $produkKeluar = [];
    
    public function __construct(mysqli $conn, string $dateFrom, string $dateTo)
    {
        $this->title = ' Stok Produk';
        $this->chartType = 'bar';
        $this->emptyMessage = 'Tidak ada data stok produk';
        parent::__construct($conn, $dateFrom, $dateTo);
    }
    
    protected function buildQuery(): string
    {
        $from = $this->escape($this->dateFrom);
        $to = $this->escape($this->dateTo);
        
        return "
            SELECT 
                p.nama_produk,
                SUM(CASE WHEN sm.jenis = 'masuk' THEN sm.jumlah ELSE 0 END) as stok_masuk,
                SUM(CASE WHEN sm.jenis = 'keluar' THEN sm.jumlah ELSE 0 END) as stok_keluar
            FROM stock_movements sm
            JOIN products p ON sm.product_id = p.id
            WHERE DATE(sm.tanggal) BETWEEN '$from' AND '$to'
            GROUP BY p.id, p.nama_produk
            ORDER BY p.nama_produk
        ";
    }
    
    protected function mapRow(array $row): void
    {
        $nama = $row['nama_produk'];
        $this->rawData[] = $row;
        $this->produkMasuk[$nama] = (int)$row['stok_masuk'];
        $this->produkKeluar[$nama] = (int)$row['stok_keluar'];
    }
    
    protected function prepareChartData(): void
    {
        $this->chartData = [
            'masuk' => array_values($this->produkMasuk),
            'keluar' => array_values($this->produkKeluar)
        ];
    }
    
    protected function prepareLabels(): void
    {
        $this->labels = array_keys($this->produkMasuk);
    }
    
    public function getProdukMasuk(): array { return $this->produkMasuk; }
    public function getProdukKeluar(): array { return $this->produkKeluar; }
}

class BeansStockAnalytics extends BaseAnalytics
{
    protected array $beansMasuk = [];
    protected array $beansKeluar = [];
    
    public function __construct(mysqli $conn, string $dateFrom, string $dateTo)
    {
        $this->title = ' Stok Biji Kopi';
        $this->chartType = 'bar';
        $this->emptyMessage = 'Tidak ada data stok biji kopi';
        parent::__construct($conn, $dateFrom, $dateTo);
    }
    
    protected function buildQuery(): string
    {
        $from = $this->escape($this->dateFrom);
        $to = $this->escape($this->dateTo);
        
        return "
            SELECT 
                cb.nama_biji_kopi,
                SUM(CASE WHEN smb.jenis = 'masuk' THEN smb.jumlah ELSE 0 END) as stok_masuk,
                SUM(CASE WHEN smb.jenis = 'keluar' THEN smb.jumlah ELSE 0 END) as stok_keluar
            FROM stock_movements_beans smb
            JOIN coffee_beans cb ON smb.bean_id = cb.id
            WHERE DATE(smb.tanggal) BETWEEN '$from' AND '$to'
            GROUP BY cb.id, cb.nama_biji_kopi
            ORDER BY cb.nama_biji_kopi
        ";
    }
    
    protected function mapRow(array $row): void
    {
        $nama = $row['nama_biji_kopi'];
        $this->rawData[] = $row;
        $this->beansMasuk[$nama] = (float)$row['stok_masuk'];
        $this->beansKeluar[$nama] = (float)$row['stok_keluar'];
    }
    
    protected function prepareChartData(): void
    {
        $this->chartData = [
            'masuk' => array_values($this->beansMasuk),
            'keluar' => array_values($this->beansKeluar)
        ];
    }
    
    protected function prepareLabels(): void
    {
        $this->labels = array_keys($this->beansMasuk);
    }
    
    public function getBeansMasuk(): array { return $this->beansMasuk; }
    public function getBeansKeluar(): array { return $this->beansKeluar; }
}

// ============================================================================
// ANALYTICS MANAGER
// ============================================================================

class AnalyticsManager
{
    private mysqli $conn;
    private string $dateFrom;
    private string $dateTo;
    
    private ?SalesAnalytics $sales = null;
    private ?ProductStockAnalytics $products = null;
    private ?BeansStockAnalytics $beans = null;
    
    public function __construct(mysqli $conn, string $dateFrom, string $dateTo)
    {
        $this->conn = $conn;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
    }
    
    public function getSales(): SalesAnalytics
    {
        if ($this->sales === null) {
            $this->sales = new SalesAnalytics($this->conn, $this->dateFrom, $this->dateTo);
        }
        return $this->sales;
    }
    
    public function getProducts(): ProductStockAnalytics
    {
        if ($this->products === null) {
            $this->products = new ProductStockAnalytics($this->conn, $this->dateFrom, $this->dateTo);
        }
        return $this->products;
    }
    
    public function getBeans(): BeansStockAnalytics
    {
        if ($this->beans === null) {
            $this->beans = new BeansStockAnalytics($this->conn, $this->dateFrom, $this->dateTo);
        }
        return $this->beans;
    }
    
    public function getPeriodLabel(): string
    {
        if ($this->dateFrom === $this->dateTo) {
            return date('d M Y', strtotime($this->dateFrom));
        }
        return date('d M Y', strtotime($this->dateFrom)) . ' - ' . date('d M Y', strtotime($this->dateTo));
    }
    
    public function getDateFrom(): string { return $this->dateFrom; }
    public function getDateTo(): string { return $this->dateTo; }
}


require_once '../config/config.php';
checkRole('manager');

$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

$analytics = new AnalyticsManager($conn, $dateFrom, $dateTo);

$salesAnalytics = $analytics->getSales();
$productAnalytics = $analytics->getProducts();
$beansAnalytics = $analytics->getBeans();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - TEFA Coffee</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/dashboard.css">
    
    <style>
        :root {
            --bg-page: #f8fafc;
            --bg-card: #ffffff;
            --text-primary: #1e293b;
            --text-muted: #64748b;
            --border-light: #e2e8f0;
            --primary: #0f172a;
            --accent: #b4975a;
            --accent-soft: rgba(180, 151, 90, 0.12);
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.04);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 24px rgba(0,0,0,0.08);
            --radius: 14px;
            --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .filter-bar { 
            background: var(--bg-card); border-radius: var(--radius); padding: 1rem 1.25rem; 
            box-shadow: var(--shadow-sm); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
            border: 1px solid var(--border-light);
        }
        .filter-group { display: flex; align-items: center; gap: 0.6rem; }
        .filter-label { font-size: 0.875rem; color: var(--text-muted); font-weight: 500; white-space: nowrap; }
        .filter-input { 
            padding: 0.6rem 0.9rem; border: 1px solid var(--border-light); border-radius: 8px; 
            font-size: 0.9rem; background: #fff; min-width: 150px; transition: var(--transition); color: var(--text-primary);
        }
        .filter-input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
        .filter-separator { color: var(--text-muted); font-size: 0.85rem; }
        .btn-reset { 
            background: transparent; color: var(--text-muted); border: 1px solid var(--border-light); 
            padding: 0.55rem 1rem; border-radius: 8px; font-weight: 500; cursor: pointer; 
            transition: var(--transition); font-size: 0.875rem; display: flex; align-items: center; gap: 0.4rem;
        }
        .btn-reset:hover { background: #f1f5f9; color: var(--text-primary); border-color: #cbd5e1; }

        .card-custom { 
            background: var(--bg-card); border-radius: var(--radius); box-shadow: var(--shadow-md); 
            border: 1px solid var(--border-light); overflow: hidden; transition: var(--transition);
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .card-custom:hover { transform: translateY(-3px); box-shadow: var(--shadow-lg); }
        .card-header-custom { 
            padding: 1.15rem 1.5rem; border-bottom: 1px solid var(--border-light); 
            display: flex; align-items: center; justify-content: space-between; 
        }
        .card-header-custom span { font-weight: 600; color: var(--primary); display: flex; align-items: center; gap: 0.5rem; font-size: 1.05rem; }
        .card-body { 
            padding: 1.5rem; 
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .chart-container { position: relative; height: 300px; width: 100%; flex: 1; }
        .chart-container-lg { height: 350px; width: 100%; flex: 1; }

        .empty-state { 
            text-align: center; 
            padding: 3rem 1rem; 
            color: var(--text-muted);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 300px;
            background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-page) 100%);
            border-radius: 12px;
            flex: 1;
        }
        .empty-state i { 
            font-size: 4rem; 
            margin-bottom: 1.25rem; 
            opacity: 0.4;
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        .empty-state p { 
            margin: 0 0 0.5rem 0; 
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        .empty-state small {
            font-size: 0.875rem;
            opacity: 0.7;
            color: var(--text-muted);
        }
        .empty-state .fa-chart-line { color: #3b82f6; }
        .empty-state .fa-box-open { color: #10b981; }
        .empty-state .fa-seedling { color: #f59e0b; }

        .page-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.75rem; }
        .page-title { font-size: 1.65rem; font-weight: 700; color: var(--primary); display: flex; align-items: center; gap: 0.6rem; }
        .badge-period { 
            padding: 0.5rem 1rem; border-radius: 50px; font-size: 0.85rem; font-weight: 500; 
            background: var(--accent-soft); color: var(--primary); border: 1px solid rgba(180,151,90,0.2);
        }

        /* Equal height columns */
        .row-equal-height {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -0.75rem;
        }
        .row-equal-height > [class*="col-"] {
            display: flex;
            flex-direction: column;
            padding: 0 0.75rem;
            margin-bottom: 1.5rem;
        }
        .row-equal-height > [class*="col-"] .card-custom {
            flex: 1;
        }
        
        @media (max-width: 992px) {
            .chart-container, .chart-container-lg {
                height: 250px;
            }
            .empty-state {
                min-height: 250px;
            }
        }
    </style>
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
            <li><a href="reports.php"><i class="fas fa-file-alt"></i><span>Laporan</span></a></li>
            <li><a href="analytics.php" class="active"><i class="fas fa-chart-bar"></i><span>Analisis</span></a></li>
            <div class="sidebar-divider"></div>
            <li><a href="../logout.php" style="color: #ef9a9a;"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
        </ul>
    </div>

    <div class="main-wrapper">
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Monitoring dan Analisis</h1>
                <span class="badge-period"><i class="fas fa-calendar-alt me-1"></i><?= htmlspecialchars($analytics->getPeriodLabel()) ?></span>
            </div>

            <div class="filter-bar">
                <div class="filter-group">
                    <span class="filter-label"><i class="fas fa-calendar me-1"></i>Periode:</span>
                    <input type="date" class="filter-input" id="dateFrom" value="<?= htmlspecialchars($analytics->getDateFrom()) ?>" onchange="autoApplyFilter()">
                    <span class="filter-separator">s/d</span>
                    <input type="date" class="filter-input" id="dateTo" value="<?= htmlspecialchars($analytics->getDateTo()) ?>" onchange="autoApplyFilter()">
                </div>
                <div class="filter-group ms-auto">
                    <button class="btn-reset" onclick="resetFilter()"> Reset</button>
                </div>
            </div>

            <div class="card-custom mb-4">
                <div class="card-header-custom">
                    <span><?= $salesAnalytics->getTitle() ?></span>
                </div>
                <div class="card-body">
                    <?php if($salesAnalytics->hasData()): ?>
                        <div class="chart-container-lg">
                            <canvas id="salesChart"></canvas>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">     
                            <p><?= $salesAnalytics->getEmptyMessage() ?></p>
                            <small>Tidak ada transaksi pada periode <?= htmlspecialchars($analytics->getPeriodLabel()) ?></small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row-equal-height">
                <div class="col-lg-6">
                    <div class="card-custom">
                        <div class="card-header-custom">
                            <span><?= $productAnalytics->getTitle() ?></span>
                        </div>
                        <div class="card-body">
                            <?php if($productAnalytics->hasData()): ?>
                                <div class="chart-container">
                                    <canvas id="productStockChart"></canvas>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    
                                    <p><?= $productAnalytics->getEmptyMessage() ?></p>
                                    <small>Tidak ada  stok data produk pada periode ini</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card-custom">
                        <div class="card-header-custom">
                            <span><?= $beansAnalytics->getTitle() ?></span>
                        </div>
                        <div class="card-body">
                            <?php if($beansAnalytics->hasData()): ?>
                                <div class="chart-container">
                                    <canvas id="beansStockChart"></canvas>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    
                                    <p><?= $beansAnalytics->getEmptyMessage() ?></p>
                                    <small>Tidak ada data stok biji kopi pada periode ini</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    const salesLabels = <?= json_encode($salesAnalytics->getLabels()) ?>;
    const salesTransaksi = <?= json_encode($salesAnalytics->getChartData()['transaksi']) ?>;
    const salesPenjualan = <?= json_encode($salesAnalytics->getChartData()['penjualan']) ?>;
    
    const productLabels = <?= json_encode($productAnalytics->getLabels()) ?>;
    const productMasuk = <?= json_encode($productAnalytics->getChartData()['masuk']) ?>;
    const productKeluar = <?= json_encode($productAnalytics->getChartData()['keluar']) ?>;
    
    const beansLabels = <?= json_encode($beansAnalytics->getLabels()) ?>;
    const beansMasuk = <?= json_encode($beansAnalytics->getChartData()['masuk']) ?>;
    const beansKeluar = <?= json_encode($beansAnalytics->getChartData()['keluar']) ?>;

    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    function toggleSidebar() {
        sidebar.classList.toggle('active');
        sidebarOverlay.classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
    }
    hamburgerBtn?.addEventListener('click', toggleSidebar);
    sidebarOverlay?.addEventListener('click', toggleSidebar);
    document.querySelectorAll('.sidebar-menu a').forEach(link => {
        link.addEventListener('click', () => { if (window.innerWidth <= 768) toggleSidebar(); });
    });
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    });

    function autoApplyFilter() {
        const from = document.getElementById('dateFrom').value;
        const to = document.getElementById('dateTo').value;
        if(from && to && from <= to) {
            window.location.href = `analytics.php?date_from=${from}&date_to=${to}`;
        }
    }
    function resetFilter() {
        window.location.href = 'analytics.php';
    }

    function initSalesChart() {
        const ctx = document.getElementById('salesChart');
        if(!ctx || salesLabels.length === 0) return;
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: salesLabels,
                datasets: [{
                    label: 'Transaksi',
                    data: salesTransaksi,
                    borderColor: '#64748b',
                    backgroundColor: 'rgba(100,116,139,0.12)',
                    tension: 0.4, borderWidth: 2.5, pointRadius: 3, pointBackgroundColor: '#64748b',
                    yAxisID: 'y'
                }, {
                    label: 'Pendapatan',
                    data: salesPenjualan,
                    borderColor: '#b4975a',
                    backgroundColor: 'rgba(180,151,90,0.1)',
                    tension: 0.4, borderWidth: 2.5, pointRadius: 3, pointBackgroundColor: '#b4975a',
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: { 
                        type: 'linear', display: true, position: 'left', 
                        title: {display:true, text:'Jumlah', color:'#64748b'}, 
                        grid: {color:'#f1f5f9'}, ticks: {color:'#64748b'} 
                    },
                    y1: { 
                        type: 'linear', display: true, position: 'right', 
                        title: {display:true, text:'Pendapatan (Rp)', color:'#64748b'}, 
                        grid: {drawOnChartArea:false}, 
                        ticks: {color:'#64748b', callback: v => 'Rp '+(v/1e6).toFixed(1)+'jt'} 
                    },
                    x: { grid: {display:false}, ticks: {color:'#64748b'} }
                },
                plugins: {
                    legend: { position: 'top', labels: {usePointStyle:true, color:'#1e293b', padding:20} },
                    tooltip: {
                        backgroundColor: '#0f172a', titleColor: '#fff', bodyColor: '#e2e8f0',
                        padding: 12, cornerRadius: 8, displayColors: false,
                        callbacks: {
                            label: ctx => {
                                let l = ctx.dataset.label + ': ';
                                return ctx.datasetIndex === 1 
                                    ? l + 'Rp ' + new Intl.NumberFormat('id-ID').format(ctx.parsed.y) 
                                    : l + ctx.parsed.y;
                            }
                        }
                    }
                }
            }
        });
    }

    function initProductStockChart() {
        const ctx = document.getElementById('productStockChart');
        if(!ctx || productLabels.length === 0) return;
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: productLabels,
                datasets: [{
                    label: 'Masuk', data: productMasuk,
                    backgroundColor: '#10b981', borderColor: '#059669', borderWidth: 1, borderRadius: 6
                }, {
                    label: 'Keluar', data: productKeluar,
                    backgroundColor: '#ef4444', borderColor: '#dc2626', borderWidth: 1, borderRadius: 6
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, title: {display:true, text:'Jumlah', color:'#64748b'}, grid: {color:'#f1f5f9'}, ticks: {color:'#64748b'} },
                    x: { grid: {display:false}, ticks: {color:'#64748b'} }
                },
                plugins: { legend: {position:'top', labels: {usePointStyle:true, color:'#1e293b', padding:20}} }
            }
        });
    }

    function initBeansStockChart() {
        const ctx = document.getElementById('beansStockChart');
        if(!ctx || beansLabels.length === 0) return;
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: beansLabels,
                datasets: [{
                    label: 'Masuk', data: beansMasuk,
                    backgroundColor: '#10b981', borderColor: '#059669', borderWidth: 1, borderRadius: 6
                }, {
                    label: 'Keluar', data: beansKeluar,
                    backgroundColor: '#ef4444', borderColor: '#dc2626', borderWidth: 1, borderRadius: 6
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, title: {display:true, text:'Jumlah (kg)', color:'#64748b'}, grid: {color:'#f1f5f9'}, ticks: {color:'#64748b'} },
                    x: { grid: {display:false}, ticks: {color:'#64748b'} }
                },
                plugins: { legend: {position:'top', labels: {usePointStyle:true, color:'#1e293b', padding:20}} }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initSalesChart();
        initProductStockChart();
        initBeansStockChart();
    });

    window.addEventListener('beforeprint', () => {
        document.querySelectorAll('.card-custom').forEach(c => {
            c.style.boxShadow = 'none'; c.style.transform = 'none';
        });
    });
    </script>
</body>
</html>