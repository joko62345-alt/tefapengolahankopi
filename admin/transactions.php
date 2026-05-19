<?php
require_once '../config/config.php';
checkRole('admin');

class TransactionsController {
    private $conn;
    
    //  Public properties untuk akses di view
    public $success = '';
    public $error = '';
    public $print_mode = false;
    
    // Data untuk view
    public $all_transactions = [];
    public $total_transaksi = 0;
    public $total_pendapatan = 0;
    public $total_lunas = 0;
    public $total_pending = 0;
    public $period_text = '';
    public $pengambilan_text = '';
    public $customer_text = '';
    public $stats_transaksi = [];
    
    // Filters
    public $filter_date_from = '';
    public $filter_date_to = '';
    public $filter_pengambilan = '';
    public $filter_customer = '';

    public function __construct($connection) {
        $this->conn = $connection;
        $this->print_mode = isset($_GET['print']) && $_GET['print'] == '1';
        $this->handleRequests();
        $this->loadFilters();
        $this->loadData();
    }

    
    private function handleRequests(): void {
        if ($this->print_mode) return;

        // Konfirmasi Pembayaran
        if (isset($_GET['confirm_payment'])) {
            $this->confirmPayment();
        }
        
        //  Konfirmasi Produk Sudah Diambil
        if (isset($_GET['confirm_pickup'])) {
            $this->confirmPickup();
        }
        
        //  Batal Konfirmasi Pengambilan (Undo)
        if (isset($_GET['undo_pickup'])) {
            $this->undoPickup();
        }
    }

    private function confirmPayment(): void {
        $id = (int) $_GET['confirm_payment'];
        if (mysqli_query($this->conn, "UPDATE transactions SET status_pembayaran='dikonfirmasi' WHERE id='$id'")) {
            $this->success = 'Pembayaran berhasil dikonfirmasi!';
        } else {
            $this->error = 'Gagal konfirmasi pembayaran!';
        }
    }

    private function confirmPickup(): void {
        $id = (int) $_GET['confirm_pickup'];
        $admin_name = $_SESSION['nama'];
        
        // Ambil kode_transaksi dulu untuk keterangan
        $trx = mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT kode_transaksi FROM transactions WHERE id = $id"));
        $kode_transaksi = $trx['kode_transaksi'];
        
        mysqli_begin_transaction($this->conn);
        try {
            $stmt = $this->conn->prepare("UPDATE transactions SET status_pengambilan='sudah_diambil', diambil_oleh=?, tanggal_diambil=NOW() WHERE id=?");
            $stmt->bind_param("si", $admin_name, $id);
            $stmt->execute();

            $details = $this->conn->prepare("SELECT td.product_id, td.quantity FROM transaction_details td WHERE td.transaction_id = ?");
            $details->bind_param("i", $id);
            $details->execute();
            $result = $details->get_result();
            
            while ($row = $result->fetch_assoc()) {
                // 1. Kurangi stok di products
                $update = $this->conn->prepare("UPDATE products SET stok = stok - ? WHERE id = ?");
                $update->bind_param("ii", $row['quantity'], $row['product_id']);
                $update->execute();
                
                //  2. TAMBAHAN: Catat ke stock_movements
                $keterangan = "Penjualan $kode_transaksi";
                $insert_movement = $this->conn->prepare("INSERT INTO stock_movements (product_id, jenis, jumlah, keterangan) VALUES (?, 'keluar', ?, ?)");
                $insert_movement->bind_param("iis", $row['product_id'], $row['quantity'], $keterangan);
                $insert_movement->execute();
            }
            mysqli_commit($this->conn);
            $_SESSION['success'] = "Stok diperbarui & riwayat tercatat!";
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            $_SESSION['error'] = "Gagal: " . $e->getMessage();
        }
        header("Location: transactions.php");
        exit;
    }

    private function undoPickup(): void {
        $id = (int) $_GET['undo_pickup'];
        if (mysqli_query($this->conn, "UPDATE transactions SET status_pengambilan='belum_diambil', diambil_oleh=NULL, tanggal_diambil=NULL WHERE id='$id'")) {
            $this->success = 'Status pengambilan dibatalkan!';
        } else {
            $this->error = 'Gagal batalkan status!';
        }
    }

    //Load filter parameters dari GET
    private function loadFilters(): void {
        $this->filter_date_from = $_GET['filter_date_from'] ?? '';
        $this->filter_date_to = $_GET['filter_date_to'] ?? '';
        $this->filter_pengambilan = $_GET['filter_pengambilan'] ?? '';
        $this->filter_customer = $_GET['filter_customer'] ?? '';
    }

    //Build WHERE clause untuk filter transactions
    private function buildTransactionsWhereClause(): string {
        $where = "1=1";
        
        if ($this->filter_date_from) {
            $where .= " AND DATE(t.tanggal_transaksi) >= '" . mysqli_real_escape_string($this->conn, $this->filter_date_from) . "'";
        }
        if ($this->filter_date_to) {
            $where .= " AND DATE(t.tanggal_transaksi) <= '" . mysqli_real_escape_string($this->conn, $this->filter_date_to) . "'";
        }
        if ($this->filter_pengambilan) {
            $where .= " AND t.status_pengambilan = '" . mysqli_real_escape_string($this->conn, $this->filter_pengambilan) . "'";
        }
        if ($this->filter_customer) {
            $escaped = mysqli_real_escape_string($this->conn, $this->filter_customer);
            $where .= " AND (u.nama_lengkap LIKE '%$escaped%' OR u.telepon LIKE '%$escaped%')";
        }
        
        return $where;
    }

    //Load semua data yang dibutuhkan view
    private function loadData(): void {
        //  Load transactions dengan filter
        $where = $this->buildTransactionsWhereClause();
        $transactions_query = mysqli_query($this->conn, "
            SELECT t.*, u.nama_lengkap, u.telepon, u.alamat
            FROM transactions t
            JOIN users u ON t.user_id = u.id
            WHERE $where
            ORDER BY t.tanggal_transaksi DESC
            LIMIT 500
        ");

        $this->all_transactions = [];
        while ($row = mysqli_fetch_assoc($transactions_query)) {
            $this->all_transactions[] = $row;
        }

        //  Calculate totals for print report
        $this->total_transaksi = count($this->all_transactions);
        foreach ($this->all_transactions as $t) {
            if (in_array($t['status_pembayaran'], ['lunas', 'dikonfirmasi'])) {
                $this->total_pendapatan += $t['total_harga'];
                $this->total_lunas++;
            } else {
                $this->total_pending++;
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

        //  Filter texts
        if ($this->filter_pengambilan) {
            $this->pengambilan_text = ' | Pengambilan: ' . ucfirst(str_replace('_', ' ', $this->filter_pengambilan));
        }
        if ($this->filter_customer) {
            $this->customer_text = ' | Customer: ' . ucfirst($this->filter_customer);
        }

        //  Stats for cards
        $this->stats_transaksi = [
            'total' => mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT COUNT(*) as total FROM transactions"))['total'] ?? 0,
            'pendapatan' => mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT SUM(total_harga) as total FROM transactions WHERE status_pembayaran IN ('lunas','dikonfirmasi')"))['total'] ?? 0,
            'belum_diambil' => mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT COUNT(*) as total FROM transactions WHERE status_pengambilan='belum_diambil' AND status_pembayaran IN ('lunas','dikonfirmasi')"))['total'] ?? 0,
            'sudah_diambil' => mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT COUNT(*) as total FROM transactions WHERE status_pengambilan='sudah_diambil'"))['total'] ?? 0,
        ];
    }

    //Helper: Get badge class for payment status
    public function getPaymentBadge(string $status): string {
        return match ($status) {
            'lunas' => 'badge-success',
            'dikonfirmasi' => 'badge-info',
            default => 'badge-warning'
        };
    }

    //Helper: Get payment status label
    public function getPaymentLabel(string $status): string {
        return match ($status) {
            'lunas', 'dikonfirmasi' => 'Lunas',
            default => 'Pending'
        };
    }

    //Helper: Get badge class for pickup status
    public function getPickupBadge(string $status): string {
        return $status == 'sudah_diambil' ? 'badge-success' : 'badge-warning';
    }

    //Helper: Get pickup status label
    public function getPickupLabel(string $status): string {
        return $status == 'sudah_diambil' ? 'Sudah' : 'Belum';
    }

    //Helper: Format tanggal
    public function formatDate(string $datetime, string $format = 'd/m/Y H:i'): string {
        return date($format, strtotime($datetime));
    }

    //Helper: Format tanggal untuk print (d M Y H:i)
    public function formatDatePrint(string $datetime): string {
        return date('d M Y H:i', strtotime($datetime));
    }

    // Helper: Format Rupiah
    public function formatRupiah(float $amount): string {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    //Helper: Format angka biasa
    public function formatNumber(float $amount): string {
        return number_format($amount, 0, ',', '.');
    }

    //Helper: Get current admin name for signature
    public function getCurrentAdminName(): string {
        return htmlspecialchars($_SESSION['nama'] ?? $_SESSION['username'] ?? 'Admin');
    }

    
    public function renderPrintView(): void {
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Laporan Transaksi - TEFA COFFEE</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: 'Arial', 'Times New Roman', serif; font-size: 11pt; line-height: 1.4; color: #000; background: #fff; padding: 25px; }
                .container { max-width: 210mm; margin: 0 auto; }
                .report-header { text-align: center; border-bottom: 3px double #000; padding-bottom: 12px; margin-bottom: 15px; }
                .report-header .logo { width: 55px; height: 55px; margin-bottom: 8px; }
                .report-header h1 { font-size: 16pt; font-weight: bold; margin: 8px 0 3px 0; text-transform: uppercase; }
                .report-header h2 { font-size: 13pt; font-weight: normal; margin: 3px 0; }
                .report-info { margin-top: 12px; text-align: left; font-size: 10pt; }
                .report-info table { width: 100%; border-collapse: collapse; }
                .report-info td { padding: 2px 0; }
                .report-info td:first-child { width: 130px; font-weight: bold; }
                .data-table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 10pt; }
                .data-table th, .data-table td { border: 1px solid #000; padding: 6px 8px; text-align: left; }
                .data-table th { background: #f5f5f5; font-weight: bold; text-transform: uppercase; font-size: 9pt; }
                .data-table td.text-center { text-align: center; }
                .data-table td.text-right { text-align: right; }
                .summary-box { border: 2px solid #000; padding: 12px; margin: 15px 0; background: #fafafa; }
                .summary-box h3 { font-size: 12pt; margin-bottom: 8px; text-transform: uppercase; }
                .summary-table { width: 100%; border-collapse: collapse; }
                .summary-table td { padding: 6px 8px; border-bottom: 1px solid #ccc; font-size: 10pt; }
                .summary-table td:last-child { text-align: right; font-weight: bold; }
                .summary-table tr.total-row { border-top: 2px solid #000; font-weight: bold; }
                .summary-table tr.total-row td { border-bottom: none; padding-top: 10px; }
                .report-footer { margin-top: 30px; display: flex; justify-content: space-between; page-break-inside: avoid; }
                .footer-section { width: 45%; }
                .footer-section h4 { font-size: 10pt; margin-bottom: 50px; text-align: center; }
                .footer-section p { text-align: center; font-size: 10pt; }
                .signature-line { border-top: 1px solid #000; margin-top: 40px; padding-top: 3px; text-align: center; }
                .print-date { text-align: right; font-size: 9pt; margin-top: 15px; font-style: italic; }
                .print-actions { text-align: center; margin-bottom: 20px; padding: 15px; background: #f5f5f5; border-radius: 6px; display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
                .btn-print { background: #3E2723; color: #fff; border: none; padding: 10px 24px; font-size: 12pt; border-radius: 4px; cursor: pointer; }
                .btn-print:hover { background: #2e1e1b; }
                .btn-close { background: #6b7280; color: #fff; border: none; padding: 10px 24px; font-size: 12pt; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
                .btn-close:hover { background: #4b5563; }
                .empty-state { text-align: center; padding: 40px 15px; color: #666; }
                .empty-state i { font-size: 40pt; margin-bottom: 10px; opacity: 0.3; }
                @media print {
                    .print-actions { display: none !important; }
                    body { padding: 0; }
                    .data-table tr:nth-child(even) { background: #fff !important; }
                    .summary-box { background: #fff !important; }
                    @page { margin: 1.2cm; size: A4; }
                }
            </style>
        </head>
        <body>
            <div class="print-actions">
                <button onclick="window.print()" class="btn-print"><i class="fas fa-print"></i> Cetak Laporan</button>
                <a href="transactions.php" class="btn-close"><i class="fas fa-times"></i> Kembali</a>
            </div>
            <div class="container">
                <div class="report-header">
                    <img src="../assets/images/logopolije.png" alt="Logo" class="logo" onerror="this.style.display='none'">
                    <h1>TEFA COFFEE</h1>
                    <h2>LAPORAN TRANSAKSI CUSTOMER</h2>
                    <div class="report-info">
                        <table>
                            <tr><td>Periode</td><td>: <?= htmlspecialchars($this->period_text) ?></td></tr>
                            <tr><td>Filter</td><td>: <?= htmlspecialchars($this->pengambilan_text . $this->customer_text) ?: 'Semua' ?></td></tr>
                            <tr><td>Total Data</td><td>: <?= $this->total_transaksi ?> transaksi</td></tr>
                        </table>
                    </div>
                </div>
                <?php if ($this->total_transaksi > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 5%;">No</th>
                                <th style="width: 12%;">Kode</th>
                                <th style="width: 22%;">Customer</th>
                                <th style="width: 12%;">Status</th>
                                <th style="width: 12%;">Diambil</th>
                                <th style="width: 17%;">Tanggal</th>
                                <th style="width: 20%;" class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1; foreach ($this->all_transactions as $t): ?>
                                <tr>
                                    <td class="text-center"><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($t['kode_transaksi']) ?></td>
                                    <td><?= htmlspecialchars($t['nama_lengkap']) ?><?php if (!empty($t['telepon'])): ?><br><small style="font-size: 9pt; color: #666;"><?= htmlspecialchars($t['telepon']) ?></small><?php endif; ?></td>
                                    <td class="text-center"><?= $this->getPaymentLabel($t['status_pembayaran']) ?></td>
                                    <td class="text-center"><?= $this->getPickupLabel($t['status_pengambilan']) ?></td>
                                    <td><?= $this->formatDatePrint($t['tanggal_transaksi']) ?></td>
                                    <td class="text-right"><?= $this->formatRupiah($t['total_harga']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="summary-box">
                        <h3>Ringkasan Transaksi</h3>
                        <table class="summary-table">
                            <tr><td>Total Transaksi</td><td><?= $this->total_transaksi ?> transaksi</td></tr>
                            <tr><td>Status Lunas</td><td><?= $this->total_lunas ?> transaksi</td></tr>
                            <tr><td>Status Pending</td><td><?= $this->total_pending ?> transaksi</td></tr>
                            <tr class="total-row"><td>Total Pendapatan</td><td><?= $this->formatRupiah($this->total_pendapatan) ?></td></tr>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>Tidak Ada Data</h3>
                        <p>Tidak ada transaksi yang sesuai dengan filter yang dipilih</p>
                    </div>
                <?php endif; ?>
                <div class="report-footer">
                    <div class="footer-section">
                        <h4>Mengetahui,</h4><p>Kepala TEFA Coffee</p>
                        <div class="signature-line"><strong>( ___________________ )</strong></div>
                    </div>
                    <div class="footer-section">
                        <h4>Dibuat Oleh,</h4><p>Administrator</p>
                        <div class="signature-line"><strong>(<?= $this->getCurrentAdminName() ?>)</strong></div>
                    </div>
                </div>
                <div class="print-date">
                    <i class="fas fa-clock"></i> Dicetak pada: <?= date('d M Y, H:i:s') ?> WIB
                </div>
            </div>
            <script>
                window.onload = function () { window.print(); setTimeout(function () { window.location.href = 'transactions.php'; }, 1000); };
                window.onafterprint = function () { window.location.href = 'transactions.php'; };
            </script>
        </body>
        </html>
        <?php
        exit;
    }
}

//  Inisialisasi Controller
$transactions = new TransactionsController($conn);

//  Render print view jika mode print
if ($transactions->print_mode) {
    $transactions->renderPrintView();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi Customer - TEFA Coffee</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/transactions.css">
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
                <button class="hamburger-btn" id="hamburgerBtn" aria-label="Toggle Menu"><i
                        class="fas fa-bars"></i></button>
            </div>
        </div>
    </div>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
            <li><a href="products.php"><i class="fas fa-box"></i><span>Kelola Produk</span></a></li>
            <li><a href="stock.php"><i class="fa-solid fa-pen-to-square"></i><span>Data Stok biji kopi</span></a></li>
            <li><a href="inventory.php"><i class="fas fa-clipboard-list"></i><span>Inventaris</span></a></li>
            <li><a href="transactions.php" class="active"><i class="fas fa-shopping-cart"></i><span>Transaksi</span></a>
            </li>
            <div class="sidebar-divider"></div>
            <li><a href="../logout.php" style="color: #ef9a9a;"><i
                        class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
        </ul>
    </div>

    <!-- Main Content Wrapper -->
    <div class="main-wrapper">
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">Verifikasi Transaksi Customer</h1>
            </div>

            <!-- Alert Messages -->
            <?php if ($transactions->success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?= $transactions->success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($transactions->error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?= $transactions->error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                    <div class="stat-content">
                        <div class="stat-number"><?= $transactions->stats_transaksi['total'] ?></div>
                        <div class="stat-label">Total Transaksi</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color:var(--leaf-dark);background:var(--leaf-pale)"><i
                            class="fas fa-dollar-sign"></i></div>
                    <div class="stat-content">
                        <div class="stat-number text-rupiah" style="color:var(--leaf-dark)">Rp
                            <?= $transactions->formatNumber($transactions->stats_transaksi['pendapatan']) ?></div>
                        <div class="stat-label">Total Pendapatan</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color:#d97706;background:#fef3c7"><i class="fas fa-box-open"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number" style="color:#d97706"><?= $transactions->stats_transaksi['belum_diambil'] ?></div>
                        <div class="stat-label">Belum Diambil</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color:var(--leaf-dark);background:var(--leaf-pale)"><i
                            class="fas fa-check-double"></i></div>
                    <div class="stat-content">
                        <div class="stat-number" style="color:var(--leaf-dark)"><?= $transactions->stats_transaksi['sudah_diambil'] ?>
                        </div>
                        <div class="stat-label">Sudah Diambil</div>
                    </div>
                </div>
            </div>

            <!-- Transactions Table -->
            <div class="card-custom">
                <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span>Daftar Transaksi</span>
                    <div class="d-flex gap-2">
                        <button class="btn-custom btn-coffee btn-sm" onclick="openPrintReport()"><i
                                class="fas fa-file-pdf"></i> Cetak Laporan</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div id="activeFilters" class="filter-active-indicator mx-3 mt-3 mb-0">
                        <i class="fas fa-filter text-primary"></i>
                        <span class="flex-grow-1" id="activeFiltersText"></span>
                        <button class="btn btn-sm btn-secondary" onclick="resetFilters()"><i class="fas fa-times"></i>
                            Reset</button>
                    </div>
                    <form id="filterForm" method="GET" class="filter-section mx-3 mt-3 mb-0">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label small"><i
                                        class="fas fa-calendar-alt me-1 text-muted"></i>Dari</label>
                                <input type="date" name="filter_date_from" id="filter_date_from"
                                    class="form-control form-control-sm"
                                    value="<?= htmlspecialchars($transactions->filter_date_from) ?>" onchange="applyFilter()">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small"><i
                                        class="fas fa-calendar-check me-1 text-muted"></i>Sampai</label>
                                <input type="date" name="filter_date_to" id="filter_date_to"
                                    class="form-control form-control-sm"
                                    value="<?= htmlspecialchars($transactions->filter_date_to) ?>" onchange="applyFilter()">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small"><i
                                        class="fas fa-box me-1 text-muted"></i>Pengambilan</label>
                                <select name="filter_pengambilan" id="filter_pengambilan"
                                    class="form-control form-control-sm" onchange="applyFilter()">
                                    <option value="">Semua</option>
                                    <option value="belum_diambil" <?= $transactions->filter_pengambilan == 'belum_diambil' ? 'selected' : '' ?>>Belum Diambil</option>
                                    <option value="sudah_diambil" <?= $transactions->filter_pengambilan == 'sudah_diambil' ? 'selected' : '' ?>>Sudah Diambil</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small"><i
                                        class="fas fa-user me-1 text-muted"></i>Customer</label>
                                <input type="text" name="filter_customer" id="filter_customer"
                                    class="form-control form-control-sm"
                                    value="<?= htmlspecialchars($transactions->filter_customer) ?>" placeholder="Nama/Telepon"
                                    onchange="applyFilter()">
                            </div>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-custom">
                            <thead>
                                <tr>
                                    <th>Kode</th>
                                    <th>Customer</th>
                                    <th>Total</th>
                                    <th>Status Bayar</th>
                                    <th>Status Ambil</th>
                                    <th>Tanggal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($transactions->all_transactions) > 0): ?>
                                    <?php foreach (array_slice($transactions->all_transactions, 0, 10) as $t): ?>
                                        <tr class="<?= $t['status_pengambilan'] == 'belum_diambil' && $t['status_pembayaran'] != 'pending' ? 'pending-pickup' : '' ?>">
                                            <td data-label="Kode"><strong><?= htmlspecialchars($t['kode_transaksi']) ?></strong></td>
                                            <td data-label="Customer"><span class="fw-semibold"><?= htmlspecialchars($t['nama_lengkap']) ?></span><br><small class="text-muted"><?= htmlspecialchars($t['telepon']) ?></small></td>
                                            <td data-label="Total" class="text-rupiah"><strong><?= $transactions->formatRupiah($t['total_harga']) ?></strong></td>
                                            <td data-label="Status Bayar">
                                                <span class="badge-custom <?= $transactions->getPaymentBadge($t['status_pembayaran']) ?>">
                                                    <i class="fas fa-<?= $t['status_pembayaran'] == 'pending' ? 'hourglass' : 'check' ?>"></i>
                                                    <?= $transactions->getPaymentLabel($t['status_pembayaran']) ?>
                                                </span>
                                            </td>
                                            <td data-label="Status Ambil">
                                                <?php if ($t['status_pengambilan'] == 'sudah_diambil'): ?>
                                                    <span class="badge-custom badge-success"><i class="fas fa-check-double"></i>Sudah</span><br><small class="text-muted"><?= $transactions->formatDate($t['tanggal_diambil']) ?></small>
                                                <?php else: ?>
                                                    <span class="badge-custom badge-warning"><i class="fas fa-clock"></i>Belum</span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Tanggal"><?= $transactions->formatDate($t['tanggal_transaksi']) ?></td>
                                            <td data-label="Aksi">
                                                <button class="action-btn receipt" onclick="viewReceipt('<?= htmlspecialchars($t['kode_transaksi']) ?>')" title="Lihat Struk"><i class="fas fa-receipt"></i></button>
                                                <button class="action-btn view" onclick="viewDetail(<?= $t['id'] ?>)" title="Detail"><i class="fas fa-eye"></i></button>
                                                <?php if ($t['status_pembayaran'] == 'pending'): ?>
                                                    <a href="?confirm_payment=<?= $t['id'] ?>" class="action-btn confirm" onclick="return confirm('Konfirmasi pembayaran sudah diterima?')" title="Konfirmasi Pembayaran"><i class="fas fa-money-check-alt"></i></a>
                                                <?php endif; ?>
                                                <?php if ($t['status_pengambilan'] == 'belum_diambil' && in_array($t['status_pembayaran'], ['lunas', 'dikonfirmasi'])): ?>
                                                    <a href="?confirm_pickup=<?= $t['id'] ?>" class="action-btn pickup" onclick="return confirm('Konfirmasi produk sudah diambil customer?')" title="Konfirmasi Diambil"><i class="fas fa-box-open"></i></a>
                                                <?php endif; ?>
                                                <?php if ($t['status_pengambilan'] == 'sudah_diambil'): ?>
                                                    <a href="?undo_pickup=<?= $t['id'] ?>" class="action-btn undo" onclick="return confirm('Batalkan status pengambilan?')" title="Undo"><i class="fas fa-undo"></i></a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" class="text-center py-4 text-muted"><i class="fas fa-inbox me-2"></i>Belum ada transaksi</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (count($transactions->all_transactions) > 10): ?>
                        <div class="text-center mt-3"><small class="text-muted">Menampilkan 10 dari <?= count($transactions->all_transactions) ?> data</small></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Detail Modal  -->
    <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-receipt"></i>
                        <span id="modalTitle">Detail Transaksi</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailContent">
                    <div class="modal-loading">
                        <i class="fas fa-circle-notch fa-spin"></i>
                        <p>Memuat detail...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!--  RECEIPT MODAL  -->
    <div class="modal fade" id="receiptModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content receipt-modal-content">
                <div class="modal-header receipt-modal-header">
                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                        style="position: absolute; right: 10px; top: 10px; z-index: 1000;"></button>
                </div>
                <div class="modal-body receipt-modal-body p-0">
                    <div style="text-align: center; margin-bottom: 15px; border-bottom: 1px dashed #000; padding-bottom: 15px;">
                        <img src="../assets/images/logopolije.png" alt="Polije" style="width: 60px; height: 60px; margin-bottom: 10px;" onerror="this.style.display='none'">
                        <div style="font-weight: bold; font-size: 14px; margin: 5px 0;">TEFA COFFEE</div>
                        <div style="font-size: 9px; line-height: 1.4;">
                            Politeknik Negeri Jember<br>
                            Jl. Mastrip, Kotak Pos 164<br>
                            Jember 68101, Jawa Timur<br>
                            <i class="fas fa-phone"></i> 0812-3456-7890
                        </div>
                    </div>
                    <div style="border: 1px solid #000; padding: 5px; margin-bottom: 15px; text-align: center; font-weight: bold; font-size: 10px; text-transform: uppercase;" id="receiptStatus">PENDING</div>
                    <div style="font-size: 10px; margin-bottom: 15px; line-height: 1.6;">
                        <div style="display: flex; justify-content: space-between;"><span style="font-weight: bold;">No:</span><span style="font-family: monospace;" id="receiptKode"></span></div>
                        <div style="display: flex; justify-content: space-between;"><span style="font-weight: bold;">Tgl:</span><span id="receiptTanggal"></span></div>
                        <div style="display: flex; justify-content: space-between;"><span style="font-weight: bold;">Nama:</span><span id="receiptNama"></span></div>
                        <div style="display: flex; justify-content: space-between;"><span style="font-weight: bold;">Telp:</span><span id="receiptTelepon"></span></div>
                    </div>
                    <div style="border: 1px solid #000; padding: 8px; margin-bottom: 15px; font-size: 9px;">
                        <div style="text-align: center; font-weight: bold; margin-bottom: 5px;">PENGAMBILAN PRODUK</div>
                        <div id="receiptPickupStatus" style="text-align: center; font-weight: bold;"></div>
                        <div style="text-align: center; margin-top: 3px; font-size: 8px;" id="receiptPickupInfo"></div>
                    </div>
                    <div style="border-top: 2px solid #000; border-bottom: 1px dashed #000; padding: 5px 0; text-align: center; font-weight: bold; font-size: 10px; margin-bottom: 10px;">ITEM PESANAN</div>
                    <div id="receiptItems" style="font-size: 9px; margin-bottom: 15px; line-height: 1.8;"></div>
                    <div style="border-top: 2px solid #000; padding-top: 8px; margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 12px;">
                            <span>TOTAL</span><span id="receiptTotal"></span></div>
                    </div>
                    <div style="text-align: center; border-top: 2px solid #000; padding-top: 10px; font-size: 9px; line-height: 1.6;">
                        <div style="font-weight: bold; margin-bottom: 5px;">*** TERIMA KASIH ***</div>
                        <div>Dukungan Anda membantu</div>
                        <div>mahasiswa Politeknik Jember</div>
                        <div style="font-weight: bold; margin-top: 5px;">Struk ini sah tanpa tanda tangan</div>
                        <div style="margin-top: 5px; font-size: 8px;" id="receiptTimestamp"></div>
                    </div>
                </div>
                <div class="modal-footer receipt-modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal" style="margin-right: 5px;"><i class="fas fa-times"></i> Tutup</button>
                    <button type="button" class="btn btn-sm" onclick="printModalReceipt()" style="background: #2C1810; color: #fff; border: none;"><i class="fas fa-print"></i> Cetak</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Hamburger Menu Toggle
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

        // View Detail Function
        function viewDetail(id) {
            const modalContent = document.getElementById('detailContent');
            modalContent.innerHTML = '<div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin fa-2x mb-2"></i><p>Memuat detail transaksi...</p></div>';
            const modal = new bootstrap.Modal(document.getElementById('detailModal'));
            modal.show();
            fetch('ajax_transaction_detail.php?id=' + id)
                .then(response => response.json())
                .then(data => { modalContent.innerHTML = data.html; })
                .catch(error => { modalContent.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Gagal memuat detail transaksi.</div>'; });
        }

        // View Receipt - SAMA PERSIS DENGAN DASHBOARD.PHP
        function viewReceipt(kode) {
            fetch(`receipt.php?kode=${encodeURIComponent(kode)}&format=json`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.error) { alert(data.error); return; }
                    populateReceiptModal(data);
                    const modal = new bootstrap.Modal(document.getElementById('receiptModal'));
                    modal.show();
                })
                .catch(error => { console.error('Error:', error); alert('Gagal memuat struk'); });
        }

        //  Populate Receipt Modal
        function populateReceiptModal(data) {
            document.getElementById('receiptKode').textContent = data.kode_transaksi;
            document.getElementById('receiptTanggal').textContent = formatDateIndonesia(data.tanggal_transaksi);
            document.getElementById('receiptNama').textContent = data.nama_lengkap;
            document.getElementById('receiptTelepon').textContent = data.telepon;

            const statusEl = document.getElementById('receiptStatus');
            statusEl.textContent = data.status_pembayaran.toUpperCase();
            statusEl.style.background = data.status_pembayaran === 'lunas' ? '#000' : '#fff';
            statusEl.style.color = data.status_pembayaran === 'lunas' ? '#fff' : '#000';

            const pickupStatusEl = document.getElementById('receiptPickupStatus');
            const pickupInfoEl = document.getElementById('receiptPickupInfo');
            if (data.status_pengambilan === 'sudah_diambil') {
                pickupStatusEl.innerHTML = '✓ SUDAH DIAMBIL';
                pickupInfoEl.innerHTML = formatDateIndonesia(data.tanggal_diambil) + '<br>Oleh: ' + data.diambil_oleh;
            } else {
                pickupStatusEl.innerHTML = ' BELUM DIAMBIL';
                pickupInfoEl.innerHTML = 'Tunjukkan struk ini ke admin saat ambil';
            }

            let itemsHtml = '';
            data.items.forEach(item => {
                const qty = item.qty || item.quantity || 1;
                itemsHtml += `<div class="receipt-item"><span class="item-name">${item.nama_produk}</span><span class="item-qty">${qty}x</span><span class="item-price">${formatRupiah(item.subtotal)}</span></div>`;
            });
            document.getElementById('receiptItems').innerHTML = itemsHtml;
            document.getElementById('receiptTotal').textContent = 'Rp ' + formatRupiah(data.total_harga);
            document.getElementById('receiptTimestamp').textContent = new Date().toLocaleString('id-ID');
        }

        function formatDateIndonesia(dateString) {
            const date = new Date(dateString);
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            return `${day}/${month}/${year} ${hours}:${minutes}`;
        }

        function formatRupiah(angka) {
            return parseInt(angka).toLocaleString('id-ID');
        }

        //  Print Modal Receipt
        function printModalReceipt() {
            const printWindow = window.open('', '_blank', 'width=400,height=700');
            const modalContent = document.querySelector('#receiptModal .modal-content');
            printWindow.document.write(`<!DOCTYPE html><html><head><title>Cetak Struk</title><style>body { font-family: 'Courier New', Courier, monospace; padding: 20px; background: white; margin: 0; } .modal-content { box-shadow: none !important; border: none !important; max-width: 100%; } .modal-footer, .btn-close { display: none !important; } @media print { body { padding: 0; } .no-print { display: none !important; } }</style></head><body>${modalContent.outerHTML}</body></html>`);
            printWindow.document.close();
            setTimeout(() => printWindow.print(), 500);
        }

        // Open Print Report
        function openPrintReport() {
            const dateFrom = document.getElementById('filter_date_from')?.value || '';
            const dateTo = document.getElementById('filter_date_to')?.value || '';
            const pengambilan = document.getElementById('filter_pengambilan')?.value || '';
            const customer = document.getElementById('filter_customer')?.value || '';
            const params = new URLSearchParams();
            params.append('print', '1');
            if (dateFrom) params.append('filter_date_from', dateFrom);
            if (dateTo) params.append('filter_date_to', dateTo);
            if (pengambilan) params.append('filter_pengambilan', pengambilan);
            if (customer) params.append('filter_customer', customer);
            window.location.href = 'transactions.php?' + params.toString();
        }

        // Apply Filter
        function applyFilter() {
            const dateFrom = document.getElementById('filter_date_from').value;
            const dateTo = document.getElementById('filter_date_to').value;
            const pengambilan = document.getElementById('filter_pengambilan').value;
            const customer = document.getElementById('filter_customer').value;
            updateActiveFilters(dateFrom, dateTo, pengambilan, customer);
            const params = new URLSearchParams();
            if (dateFrom) params.append('filter_date_from', dateFrom);
            if (dateTo) params.append('filter_date_to', dateTo);
            if (pengambilan) params.append('filter_pengambilan', pengambilan);
            if (customer) params.append('filter_customer', customer);
            window.location.href = 'transactions.php?' + params.toString();
        }

        // Reset Filters
        function resetFilters() {
            window.location.href = 'transactions.php';
        }

        // Update Active Filters Indicator
        function updateActiveFilters(dateFrom, dateTo, pengambilan, customer) {
            const indicator = document.getElementById('activeFilters');
            const text = document.getElementById('activeFiltersText');
            if (!indicator || !text) return;
            const filters = [];
            if (dateFrom) filters.push(`Dari: ${dateFrom}`);
            if (dateTo) filters.push(`Sampai: ${dateTo}`);
            if (pengambilan) filters.push(`Pengambilan: ${pengambilan.replace('_', ' ')}`);
            if (customer) filters.push(`Customer: ${customer}`);
            if (filters.length > 0) {
                text.textContent = filters.join(' • ');
                indicator.classList.add('show');
            } else {
                indicator.classList.remove('show');
            }
        }

        // Initialize on DOM ready
        document.addEventListener('DOMContentLoaded', function () {
            const dateFrom = document.getElementById('filter_date_from')?.value;
            const dateTo = document.getElementById('filter_date_to')?.value;
            const pengambilan = document.getElementById('filter_pengambilan')?.value;
            const customer = document.getElementById('filter_customer')?.value;
            if (dateFrom || dateTo || pengambilan || customer) {
                updateActiveFilters(dateFrom, dateTo, pengambilan, customer);
            }
        });
    </script>
</body>
</html>