<?php

require_once '../config/config.php';
checkRole('admin');

class InventoryController {
    private $conn;
    
    //  Public properties untuk akses di view 
    public $success = '';
    public $error = '';
    public $print_mode = false;
    
    // Data untuk view
    public $all_usage_history = [];
    public $total_penggunaan = 0;
    public $total_perbaikan = 0;
    public $total_maintenance = 0;
    public $total_pemakaian = 0;
    public $barang_name = 'Semua Barang';
    public $period_text = '';
    public $jenis_text = '';
    public $pengguna_text = '';
    public $stats_kondisi = [];
    public $stats_kategori_array = [];
    public $inventory_list = [];
    public $unique_users = [];
    
    // Filters
    public $filter_barang = '';
    public $filter_jenis = '';
    public $filter_pengguna = '';
    public $filter_date_from = '';
    public $filter_date_to = '';

    public function __construct($connection) {
        $this->conn = $connection;
        $this->print_mode = isset($_GET['print']) && $_GET['print'] == '1';
        $this->handleRequests();
        $this->loadFilters();
        $this->loadData();
    }

    //Handle semua POST/GET requests (CRUD Operations)
    private function handleRequests(): void {
        if ($this->print_mode) return;

        //  ADD INVENTORY
        if (isset($_POST['add_inventory'])) {
            $this->addInventory();
        }
        
        //  UPDATE INVENTORY
        if (isset($_POST['update_inventory'])) {
            $this->updateInventory();
        }
        
        //  DELETE INVENTORY
        if (isset($_GET['delete'])) {
            $this->deleteInventory();
        }
        
        //  ADD USAGE HISTORY
        if (isset($_POST['add_usage'])) {
            $this->addUsageHistory();
        }
    }

    private function addInventory(): void {
        $nama_barang = mysqli_real_escape_string($this->conn, $_POST['nama_barang']);
        $kategori = mysqli_real_escape_string($this->conn, $_POST['kategori']);
        $jumlah = (int) $_POST['jumlah'];
        $kondisi = mysqli_real_escape_string($this->conn, $_POST['kondisi']);
        $tanggal_pembelian = $_POST['tanggal_pembelian'];
        $keterangan = mysqli_real_escape_string($this->conn, $_POST['keterangan']);

        $query = "INSERT INTO inventory (nama_barang, kategori, jumlah, kondisi, tanggal_pembelian, keterangan)
                  VALUES ('$nama_barang', '$kategori', '$jumlah', '$kondisi', '$tanggal_pembelian', '$keterangan')";

        if (mysqli_query($this->conn, $query)) {
            $this->success = 'Data inventaris berhasil ditambahkan!';
        } else {
            $this->error = 'Gagal: ' . mysqli_error($this->conn);
        }
    }

    private function updateInventory(): void {
        $id = (int) $_POST['inventory_id'];
        $nama_barang = mysqli_real_escape_string($this->conn, $_POST['nama_barang']);
        $kategori = mysqli_real_escape_string($this->conn, $_POST['kategori']);
        $jumlah = (int) $_POST['jumlah'];
        $kondisi = mysqli_real_escape_string($this->conn, $_POST['kondisi']);
        $keterangan = mysqli_real_escape_string($this->conn, $_POST['keterangan']);

        $query = "UPDATE inventory SET
                  nama_barang='$nama_barang',
                  kategori='$kategori',
                  jumlah='$jumlah',
                  kondisi='$kondisi',
                  keterangan='$keterangan'
                  WHERE id='$id'";

        if (mysqli_query($this->conn, $query)) {
            $this->success = 'Data inventaris berhasil diupdate!';
        } else {
            $this->error = 'Gagal: ' . mysqli_error($this->conn);
        }
    }

    private function deleteInventory(): void {
        $id = (int) $_GET['delete'];
        if (mysqli_query($this->conn, "DELETE FROM inventory WHERE id='$id'")) {
            $this->success = 'Data inventaris berhasil dihapus!';
        } else {
            $this->error = 'Gagal menghapus!';
        }
    }

    private function addUsageHistory(): void {
        $inventory_id = (int) $_POST['inventory_id'];
        $jenis_penggunaan = mysqli_real_escape_string($this->conn, $_POST['jenis_penggunaan']);
        $keterangan = mysqli_real_escape_string($this->conn, $_POST['keterangan_penggunaan']);
        $kondisi_setelah = mysqli_real_escape_string($this->conn, $_POST['kondisi_setelah']);
        $pengguna = mysqli_real_escape_string($this->conn, $_POST['pengguna']);

        $query = "INSERT INTO inventory_usage (inventory_id, jenis_penggunaan, keterangan, kondisi_setelah, pengguna)
                  VALUES ('$inventory_id', '$jenis_penggunaan', '$keterangan', '$kondisi_setelah', '$pengguna')";

        if (mysqli_query($this->conn, $query)) {
            mysqli_query($this->conn, "UPDATE inventory SET kondisi='$kondisi_setelah' WHERE id='$inventory_id'");
            $this->success = 'Riwayat penggunaan berhasil ditambahkan!';
        } else {
            $this->error = 'Gagal: ' . mysqli_error($this->conn);
        }
    }

    // Load filter parameters dari GET
    private function loadFilters(): void {
        $this->filter_barang = $_GET['filter_barang'] ?? '';
        $this->filter_jenis = $_GET['filter_jenis'] ?? '';
        $this->filter_pengguna = $_GET['filter_pengguna'] ?? '';
        $this->filter_date_from = $_GET['filter_date_from'] ?? '';
        $this->filter_date_to = $_GET['filter_date_to'] ?? '';
    }

    // Build WHERE clause untuk filter usage history
    
    private function buildUsageWhereClause(): string {
        $where = "1=1";
        
        if ($this->filter_barang) {
            $where .= " AND iu.inventory_id = " . (int) $this->filter_barang;
        }
        if ($this->filter_jenis) {
            $where .= " AND iu.jenis_penggunaan = '" . mysqli_real_escape_string($this->conn, $this->filter_jenis) . "'";
        }
        if ($this->filter_pengguna) {
            $where .= " AND iu.pengguna LIKE '%" . mysqli_real_escape_string($this->conn, $this->filter_pengguna) . "%'";
        }
        if ($this->filter_date_from) {
            $where .= " AND DATE(iu.tanggal) >= '" . mysqli_real_escape_string($this->conn, $this->filter_date_from) . "'";
        }
        if ($this->filter_date_to) {
            $where .= " AND DATE(iu.tanggal) <= '" . mysqli_real_escape_string($this->conn, $this->filter_date_to) . "'";
        }
        
        return $where;
    }

    // Load semua data yang dibutuhkan view
     
    private function loadData(): void {
        //  Load usage history dengan filter
        $where_usage = $this->buildUsageWhereClause();
        $usage_history_query = mysqli_query($this->conn, "
            SELECT iu.*, inv.nama_barang, inv.kategori, inv.kondisi as kondisi_awal
            FROM inventory_usage iu
            JOIN inventory inv ON iu.inventory_id = inv.id
            WHERE $where_usage
            ORDER BY iu.tanggal DESC
            LIMIT 500
        ");

        $this->all_usage_history = [];
        while ($row = mysqli_fetch_assoc($usage_history_query)) {
            $this->all_usage_history[] = $row;
        }

        //  Calculate totals for print report
        $this->total_penggunaan = count($this->all_usage_history);
        foreach ($this->all_usage_history as $u) {
            if ($u['jenis_penggunaan'] == 'Perbaikan') $this->total_perbaikan++;
            elseif ($u['jenis_penggunaan'] == 'Maintenance') $this->total_maintenance++;
            elseif ($u['jenis_penggunaan'] == 'Pemakaian Harian') $this->total_pemakaian++;
        }

        //  Barang name for report
        if ($this->filter_barang) {
            $b = mysqli_fetch_assoc(mysqli_query($this->conn, 
                "SELECT nama_barang FROM inventory WHERE id = " . (int) $this->filter_barang));
            if ($b) {
                $this->barang_name = $b['nama_barang'];
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

        //  Jenis & Pengguna text
        if ($this->filter_jenis) {
            $this->jenis_text = ' | Jenis: ' . ucfirst($this->filter_jenis);
        }
        if ($this->filter_pengguna) {
            $this->pengguna_text = ' | Pengguna: ' . ucfirst($this->filter_pengguna);
        }

        //  Stats Kondisi
        $this->stats_kondisi = [
            'total' => mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT COUNT(*) as total FROM inventory"))['total'] ?? 0,
            'baik' => mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT COUNT(*) as total FROM inventory WHERE kondisi='Baik'"))['total'] ?? 0,
            'perbaikan' => mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT COUNT(*) as total FROM inventory WHERE kondisi='Dalam Perbaikan'"))['total'] ?? 0,
            'rusak' => mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT COUNT(*) as total FROM inventory WHERE kondisi IN ('Rusak Ringan','Rusak Berat')"))['total'] ?? 0,
        ];

        //  Stats Kategori
        $stats_kategori = mysqli_query($this->conn, "
            SELECT 
            CASE 
                WHEN kategori LIKE '%Roasting%' THEN ' Roasting'
                WHEN kategori IN ('Peralatan Pendinginan','Penyimpanan','Packaging') THEN ' Storage'
                WHEN kategori LIKE '%Grinding%' THEN ' Grinding'
                WHEN kategori IN ('Alat Ukur','Alat Takar','Alat Kebersihan','Safety','Perlengkapan','Consumable') THEN ' Aksesoris'
                WHEN kategori = 'Quality Control' THEN ' QC'
                ELSE '🪑 Lainnya'
            END as kategori_group,
            COUNT(*) as total_item,
            SUM(jumlah) as total_qty
            FROM inventory GROUP BY kategori_group ORDER BY total_item DESC
        ");
        $this->stats_kategori_array = mysqli_fetch_all($stats_kategori, MYSQLI_ASSOC);

        //  Inventory list for dropdowns
        $inventory_list_query = mysqli_query($this->conn, "SELECT id, nama_barang FROM inventory ORDER BY nama_barang ASC");
        while ($b = mysqli_fetch_assoc($inventory_list_query)) {
            $this->inventory_list[] = $b;
        }

        //  Unique users for filter
        $unique_users_query = mysqli_query($this->conn, "SELECT DISTINCT pengguna FROM inventory_usage WHERE pengguna IS NOT NULL AND pengguna != '' ORDER BY pengguna ASC");
        while ($u = mysqli_fetch_assoc($unique_users_query)) {
            $this->unique_users[] = $u;
        }
    }

    //Helper: Get badge class based on kondisi
    
    public function getKondisiBadge(string $kondisi): string {
        return match ($kondisi) {
            'Baik' => 'badge-success',
            'Cukup Baik' => 'badge-info',
            'Dalam Perbaikan' => 'badge-warning',
            default => 'badge-danger'
        };
    }

    // Format tanggal
     
    public function formatDate(string $datetime, string $format = 'd/m/Y H:i'): string {
        return date($format, strtotime($datetime));
    }

    //Format tanggal pendek untuk print
     
    public function formatDateShort(string $datetime): string {
        return date('d M Y', strtotime($datetime));
    }

    // Get kondisi label untuk print report
     
    public function getKondisiLabel(string $kondisi): string {
        return match ($kondisi) {
            'Baik' => 'Baik',
            'Cukup Baik' => 'Cukup Baik',
            'Rusak Ringan' => 'Rusak Ringan',
            'Rusak Berat' => 'Rusak Berat',
            'Dalam Perbaikan' => 'Dalam Perbaikan',
            default => $kondisi
        };
    }

    // Get current admin name for signature
     
    public function getCurrentAdminName(): string {
        return htmlspecialchars($_SESSION['nama'] ?? $_SESSION['username'] ?? 'Admin');
    }

    // print view
    
    public function renderPrintView(): void {
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Laporan Penggunaan Barang - TEFA COFFEE</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <link rel="stylesheet" href="css/receipt.css">  
        </head>
        <body>
            <div class="print-actions">
                <button onclick="window.print()" class="btn-print">
                    <i class="fas fa-print"></i> Cetak Laporan
                </button>
                <a href="inventory.php" class="btn-close">
                    <i class="fas fa-times"></i> Kembali
                </a>
            </div>
            <div class="container">
                <div class="report-header">
                    <img src="../assets/images/logopolije.png" alt="Logo" class="logo" onerror="this.style.display='none'">
                    <h1>TEFA COFFEE</h1>
                    <h2>LAPORAN PENGGUNAAN BARANG/PERALATAN</h2>
                    <div class="report-info">
                        <table>
                            <tr>
                                <td>Barang</td>
                                <td>: <?= htmlspecialchars($this->barang_name) ?></td>
                            </tr>
                            <tr>
                                <td>Periode</td>
                                <td>:
                                    <?= htmlspecialchars($this->period_text) ?>    <?= htmlspecialchars($this->jenis_text) ?>    <?= htmlspecialchars($this->pengguna_text) ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Total Data</td>
                                <td>: <?= $this->total_penggunaan ?> riwayat penggunaan</td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php if ($this->total_penggunaan > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 5%;">No</th>
                                <th style="width: 15%;">Tanggal</th>
                                <th style="width: 22%;">Barang</th>
                                <th style="width: 15%;">Kategori</th>
                                <th style="width: 15%;">Jenis</th>
                                <th style="width: 13%;">Pengguna</th>
                                <th style="width: 15%;">Kondisi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $no = 1;
                            foreach ($this->all_usage_history as $u): ?>
                                <tr>
                                    <td class="text-center"><?= $no++ ?></td>
                                    <td><?= $this->formatDate($u['tanggal']) ?></td>
                                    <td><?= htmlspecialchars($u['nama_barang']) ?></td>
                                    <td><?= htmlspecialchars($u['kategori']) ?></td>
                                    <td class="text-center"><?= htmlspecialchars($u['jenis_penggunaan']) ?></td>
                                    <td><?= htmlspecialchars($u['pengguna']) ?></td>
                                    <td class="text-center">
                                        <?= $this->getKondisiLabel($u['kondisi_setelah']) ?>
                                    </td>
                                </tr>
                                <?php if (!empty($u['keterangan'])): ?>
                                    <tr>
                                        <td colspan="7" style="font-size: 9pt; font-style: italic; background: #fafafa;">
                                            <strong>Keterangan:</strong> <?= htmlspecialchars($u['keterangan']) ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="summary-box">
                        <h3>Ringkasan Penggunaan</h3>
                        <table class="summary-table">
                            <tr>
                                <td>Total Riwayat</td>
                                <td><?= $this->total_penggunaan ?> kali</td>
                            </tr>
                            <tr>
                                <td>Pemakaian Harian</td>
                                <td><?= $this->total_pemakaian ?> kali</td>
                            </tr>
                            <tr>
                                <td>Maintenance</td>
                                <td><?= $this->total_maintenance ?> kali</td>
                            </tr>
                            <tr>
                                <td>Perbaikan</td>
                                <td><?= $this->total_perbaikan ?> kali</td>
                            </tr>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <h3>Tidak Ada Data</h3>
                        <p>Tidak ada riwayat penggunaan yang sesuai dengan filter yang dipilih</p>
                    </div>
                <?php endif; ?>
                <div class="report-footer">
                    <div class="footer-section">
                        <h4>Mengetahui,</h4>
                        <p>Manager TEFA Coffee</p>
                        <div class="signature-line"><strong>( ___________________ )</strong></div>
                    </div>
                    <div class="footer-section">
                        <h4>Dibuat Oleh,</h4>
                        <p>Administrator</p>
                        <div class="signature-line"><strong>(<?= $this->getCurrentAdminName() ?> )</strong></div>
                    </div>
                </div>
                <div class="print-date">
                    Dicetak pada: <?= date('d M Y, H:i:s') ?> WIB
                </div>
            </div>
            <script>
                window.onload = function () {
                    window.print();
                    setTimeout(function () { window.location.href = 'inventory.php'; }, 1000);
                };
                window.onafterprint = function () { window.location.href = 'inventory.php'; };
            </script>
        </body>
        </html>
        <?php
        exit;
    }
}

//  Inisialisasi Controller
$inventory = new InventoryController($conn);

//  Render print view jika mode print
if ($inventory->print_mode) {
    $inventory->renderPrintView();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventaris Peralatan - TEFA Coffee</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/inventory.css">
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
            <li><a href="stock.php"><i class="fa-solid fa-pen-to-square"></i><span>Data Stok biji kopi</span></a></li>
            <li><a href="inventory.php" class="active"><i class="fas fa-clipboard-list"></i><span>Inventaris</span></a>
            </li>
            <li><a href="transactions.php"><i class="fas fa-shopping-cart"></i><span>Transaksi</span></a></li>
            <div class="sidebar-divider"></div>
            <li><a href="../logout.php" style="color: #ef9a9a;"><i
                        class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
        </ul>
    </div>
    <div class="main-wrapper">
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Inventaris Peralatan
                </h1>
            </div>

            <!-- Alert Messages -->
            <?php if ($inventory->success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?= $inventory->success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($inventory->error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?= $inventory->error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards - Kondisi -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-boxes"></i></div>
                    <div class="stat-content">
                        <div class="stat-number"><?= $inventory->stats_kondisi['total'] ?></div>
                        <div class="stat-label">Total Item</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color:var(--leaf-dark);background:var(--leaf-pale)">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number" style="color:var(--leaf-dark)"><?= $inventory->stats_kondisi['baik'] ?></div>
                        <div class="stat-label">Kondisi Baik</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color:#d97706;background:#fef3c7">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number" style="color:#d97706"><?= $inventory->stats_kondisi['perbaikan'] ?></div>
                        <div class="stat-label">Dalam Perbaikan</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="color:#dc2626;background:#fee2e2">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number" style="color:#dc2626"><?= $inventory->stats_kondisi['rusak'] ?></div>
                        <div class="stat-label">Rusak</div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards - Kategori -->
            <?php if (!empty($inventory->stats_kategori_array)): ?>
                <div class="category-stats">
                    <?php foreach ($inventory->stats_kategori_array as $stat): ?>
                        <div class="category-stat">
                            <div class="label"><?= htmlspecialchars($stat['kategori_group']) ?></div>
                            <div class="count"><?= $stat['total_item'] ?></div>
                            <div class="qty"><?= $stat['total_qty'] ?> unit</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

                <!--  FORM TAMBAH INVENTARIS (Full Width) -->
            <div class="card-custom mb-4">
                <div class="card-header-custom">
                    <span>Tambah Inventaris Baru</span>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nama Barang/Peralatan</label>
                                <input type="text" name="nama_barang" class="form-control" required placeholder="Contoh: Mesin Roaster">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Kategori</label>
                                <select name="kategori" class="form-control" required>
                                    <option value="">-- Pilih --</option>
                                    <option value="Mesin Roasting">Mesin Roasting</option>
                                    <option value="Mesin Grinding">Mesin Grinding</option>
                                    <option value="Alat Ukur">Alat Ukur</option>
                                    <option value="Penyimpanan">Penyimpanan</option>
                                    <option value="Lainnya">Lainnya</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Jumlah</label>
                                <input type="number" name="jumlah" class="form-control" required min="1" value="1">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Kondisi Awal</label>
                                <select name="kondisi" class="form-control" required>
                                    <option value="Baik">Baik</option>
                                    <option value="Cukup Baik">Cukup Baik</option>
                                    <option value="Rusak Ringan">Rusak Ringan</option>
                                    <option value="Rusak Berat">Rusak Berat</option>
                                    <option value="Dalam Perbaikan">Dalam Perbaikan</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tanggal Pembelian</label>
                                <input type="date" name="tanggal_pembelian" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Keterangan</label>
                                <textarea name="keterangan" class="form-control" rows="2" placeholder="Merk, spesifikasi, dll"></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" name="add_inventory" class="btn-custom btn-primary">
                                    <i class="fas fa-save"></i> Simpan Inventaris
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!--  DAFTAR INVENTARIS  -->
            <div class="card-custom mb-4">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <span>Daftar Inventaris</span>
                    <span class="badge bg-secondary"><?= mysqli_num_rows(mysqli_query($conn, "SELECT * FROM inventory")) ?> Item</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-custom inventory-table">
                            <thead>
                                <tr>
                                    <th style="width: 5%;" class="text-center">NO</th>
                                    <th style="width: 25%;">NAMA BARANG</th>
                                    <th style="width: 20%;">KATEGORI</th>
                                    <th style="width: 10%;" class="text-center">JUMLAH</th>
                                    <th style="width: 15%;">KONDISI</th>
                                    <th style="width: 15%;">TANGGAL BELI</th>
                                    <th style="width: 10%;" class="text-end">AKSI</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $inventory_query = mysqli_query($conn, "SELECT * FROM inventory ORDER BY created_at DESC");
                                $no = 1;
                                while ($item = mysqli_fetch_assoc($inventory_query)):
                                    $badge = $inventory->getKondisiBadge($item['kondisi']);
                                ?>
                                <tr>
                                    <td class="text-center"><?= $no++ ?></td>
                                    <td class="fw-semibold"><?= htmlspecialchars($item['nama_barang']) ?></td>
                                    <td><?= htmlspecialchars($item['kategori']) ?></td>
                                    <td class="text-center fw-bold"><?= $item['jumlah'] ?></td>
                                    <td><span class="badge-custom <?= $badge ?>"><?= $item['kondisi'] ?></span></td>
                                    <td><?= $inventory->formatDate($item['tanggal_pembelian'], 'd/m/Y') ?></td>
                                    <td class="text-end">
                                        <button class="action-btn history" onclick="openUsageModal(<?= $item['id'] ?>, '<?= addslashes($item['nama_barang']) ?>')" title="Catat Penggunaan">
                                            <i class="fas fa-history"></i>
                                        </button>
                                        <button class="action-btn edit" onclick="openEditModal(<?= $item['id'] ?>, '<?= addslashes($item['nama_barang']) ?>', '<?= $item['kategori'] ?>', <?= $item['jumlah'] ?>, '<?= $item['kondisi'] ?>', '<?= addslashes($item['keterangan']) ?>')" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?delete=<?= $item['id'] ?>" class="action-btn delete" onclick="return confirm('Yakin hapus?')" title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!-- Riwayat Penggunaan -->
            <div class="card-custom">
                <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span>Riwayat Penggunaan Peralatan</span>
                    <div class="d-flex gap-2">
                        <button class="btn-custom btn-coffee btn-sm" onclick="openPrintUsageReport()">
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
                                <label class="form-label small"><i class="fas fa-box me-1 text-muted"></i>Filter
                                    Barang</label>
                                <select name="filter_barang" id="filter_barang" class="form-control form-control-sm"
                                    onchange="applyFilter()">
                                    <option value="">Semua Barang</option>
                                    <?php foreach ($inventory->inventory_list as $b): ?>
                                        <option value="<?= $b['id'] ?>" <?= $inventory->filter_barang == $b['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($b['nama_barang']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small"><i
                                        class="fas fa-tools me-1 text-muted"></i>Jenis</label>
                                <select name="filter_jenis" id="filter_jenis" class="form-control form-control-sm"
                                    onchange="applyFilter()">
                                    <option value="">Semua</option>
                                    <option value="Pemakaian Harian" <?= $inventory->filter_jenis == 'Pemakaian Harian' ? 'selected' : '' ?>>Pemakaian Harian</option>
                                    <option value="Maintenance" <?= $inventory->filter_jenis == 'Maintenance' ? 'selected' : '' ?>>
                                        Maintenance</option>
                                    <option value="Perbaikan" <?= $inventory->filter_jenis == 'Perbaikan' ? 'selected' : '' ?>>
                                        Perbaikan</option>
                                    <option value="Pembersihan" <?= $inventory->filter_jenis == 'Pembersihan' ? 'selected' : '' ?>>
                                        Pembersihan</option>
                                    <option value="Lainnya" <?= $inventory->filter_jenis == 'Lainnya' ? 'selected' : '' ?>>Lainnya
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small"><i
                                        class="fas fa-user me-1 text-muted"></i>Pengguna</label>
                                <select name="filter_pengguna" id="filter_pengguna" class="form-control form-control-sm"
                                    onchange="applyFilter()">
                                    <option value="">Semua Pengguna</option>
                                    <?php foreach ($inventory->unique_users as $u): ?>
                                        <option value="<?= htmlspecialchars($u['pengguna']) ?>"
                                            <?= $inventory->filter_pengguna == $u['pengguna'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($u['pengguna']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small"><i
                                        class="fas fa-calendar-alt me-1 text-muted"></i>Dari</label>
                                <input type="date" name="filter_date_from" id="filter_date_from"
                                    class="form-control form-control-sm"
                                    value="<?= htmlspecialchars($inventory->filter_date_from) ?>" onchange="applyFilter()">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small"><i
                                        class="fas fa-calendar-check me-1 text-muted"></i>Sampai</label>
                                <input type="date" name="filter_date_to" id="filter_date_to"
                                    class="form-control form-control-sm"
                                    value="<?= htmlspecialchars($inventory->filter_date_to) ?>" onchange="applyFilter()">
                            </div>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-custom">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Peralatan</th>
                                    <th>Jenis</th>
                                    <th>Pengguna</th>
                                    <th>Kondisi Setelah</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (count($inventory->all_usage_history) > 0):
                                    foreach (array_slice($inventory->all_usage_history, 0, 10) as $u):
                                        $badge = $inventory->getKondisiBadge($u['kondisi_setelah']);
                                        ?>
                                        <tr>
                                            <td data-label="Tanggal"><?= $inventory->formatDate($u['tanggal']) ?></td>
                                            <td data-label="Peralatan" class="fw-semibold">
                                                <?= htmlspecialchars($u['nama_barang']) ?></td>
                                            <td data-label="Jenis"><?= htmlspecialchars($u['jenis_penggunaan']) ?></td>
                                            <td data-label="Pengguna"><?= htmlspecialchars($u['pengguna']) ?></td>
                                            <td data-label="Kondisi Setelah">
                                                <span class="badge-custom <?= $badge ?>"><?= $u['kondisi_setelah'] ?></span>
                                            </td>
                                            <td data-label="Keterangan" class="text-muted">
                                                <?= htmlspecialchars($u['keterangan']) ?: '-' ?></td>
                                        </tr>
                                        <?php
                                    endforeach;
                                else:
                                    ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="fas fa-inbox me-2"></i>Belum ada riwayat penggunaan
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (count($inventory->all_usage_history) > 10): ?>
                        <div class="text-center mt-3">
                            <small class="text-muted">Menampilkan 10 dari <?= count($inventory->all_usage_history) ?> data</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Edit -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Inventaris</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="inventory_id" id="edit_id">
                        <div class="mb-3">
                            <label class="form-label">Nama Barang</label>
                            <input type="text" name="nama_barang" id="edit_nama" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kategori</label>
                            <select name="kategori" id="edit_kategori" class="form-control" required>
                                <optgroup label="Roasting">
                                    <option value="Mesin Roasting">Mesin Roasting</option>
                                    <option value="Peralatan Roasting">Peralatan Roasting</option>
                                    <option value="Sumber Panas">Sumber Panas</option>
                                </optgroup>
                                <optgroup label="Storage">
                                    <option value="Peralatan Pendinginan">Peralatan Pendinginan</option>
                                    <option value="Penyimpanan">Wadah Penyimpanan</option>
                                    <option value="Packaging">Packaging & Label</option>
                                </optgroup>
                                <optgroup label="Grinding">
                                    <option value="Mesin Grinding">Mesin Grinding</option>
                                    <option value="Peralatan Grinding">Peralatan Grinding</option>
                                    <option value="Suku Cadang">Suku Cadang Grinder</option>
                                </optgroup>
                                <optgroup label="Aksesoris">
                                    <option value="Alat Ukur">Alat Ukur & Timbangan</option>
                                    <option value="Alat Takar">Alat Takar</option>
                                    <option value="Safety">Safety & Perlindungan</option>
                                    <option value="Consumable">Consumable</option>
                                </optgroup>
                                <optgroup label="Quality Control">
                                    <option value="Quality Control">Alat Quality Control</option>
                                </optgroup>
                                <optgroup label="Lainnya">
                                    <option value="Furniture">Furniture</option>
                                    <option value="Elektronik">Elektronik Umum</option>
                                    <option value="Lainnya">Lainnya</option>
                                </optgroup>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jumlah</label>
                            <input type="number" name="jumlah" id="edit_jumlah" class="form-control" required min="1">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kondisi</label>
                            <select name="kondisi" id="edit_kondisi" class="form-control" required>
                                <option value="Baik">Baik</option>
                                <option value="Cukup Baik">Cukup Baik</option>
                                <option value="Dalam Perbaikan">Dalam Perbaikan</option>
                                <option value="Rusak Ringan">Rusak Ringan</option>
                                <option value="Rusak Berat">Rusak Berat</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Keterangan</label>
                            <textarea name="keterangan" id="edit_keterangan" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-custom btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i>Batal
                        </button>
                        <button type="submit" name="update_inventory" class="btn-custom btn-primary">
                            <i class="fas fa-save"></i>Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Riwayat Penggunaan -->
    <div class="modal fade" id="usageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Catat Riwayat Penggunaan</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="inventory_id" id="usage_id">
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Barang:</strong> <span id="usage_nama"></span>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jenis Penggunaan *</label>
                            <input type="text" name="jenis_penggunaan" class="form-control" 
                                placeholder="Contoh: Pemakaian Harian" required>
                            <small class="form-text text-muted">Ketik jenis penggunaan secara manual</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Pengguna</label>
                            <input type="text" name="pengguna" class="form-control" required
                                placeholder="Nama Pengguna">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kondisi Setelah</label>
                            <select name="kondisi_setelah" class="form-control" required>
                                <option value="Baik">Baik</option>
                                <option value="Cukup Baik">Cukup Baik</option>
                                <option value="Rusak Ringan">Rusak Ringan</option>
                                <option value="Rusak Berat">Rusak Berat</option>
                                <option value="Dalam Perbaikan">Dalam Perbaikan</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Keterangan</label>
                            <textarea name="keterangan_penggunaan" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-custom btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i>Batal
                        </button>
                        <button type="submit" name="add_usage" class="btn-custom btn-primary">
                            <i class="fas fa-save"></i>Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/inventory.js"></script>
</body>
</html>