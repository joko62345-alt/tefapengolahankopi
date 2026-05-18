<?php
interface ReportInterface
{
    public function getData(): array;
    public function getSummary(): array;
    public function render(bool $printMode = false): string;
    public function getFilters(): array;
    public function setFilters(array $filters): void;
}

abstract class AbstractReport implements ReportInterface
{
    protected mysqli $conn;
    protected string $startDate;
    protected string $endDate;
    protected array $filters = [];
    protected array $data = [];
    protected array $summary = [];

    public function __construct(mysqli $conn, string $startDate, string $endDate, array $filters = [])
    {
        $this->conn = $conn;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->filters = $filters;
        $this->loadData();
        $this->calculateSummary();
    }

    abstract protected function loadData(): void;
    abstract protected function calculateSummary(): void;
    abstract protected function renderContent(bool $printMode): string;

    public function getData(): array { return $this->data; }
    public function getSummary(): array { return $this->summary; }
    public function getFilters(): array { return $this->filters; }
    
    public function setFilters(array $filters): void
    {
        $this->filters = array_merge($this->filters, $filters);
        $this->loadData();
        $this->calculateSummary();
    }

    public function render(bool $printMode = false): string
    {
        $header = $this->renderHeader($printMode);
        $content = $this->renderContent($printMode);
        $footer = $this->renderFooter($printMode);
        return $header . $content . $footer;
    }

    protected function renderHeader(bool $printMode): string
    {
        if (!$printMode) return '';
        $periodText = date('d M Y', strtotime($this->startDate)) . ' - ' . date('d M Y', strtotime($this->endDate));
        $logoPath = '../assets/images/logopolije.png';
        return <<<HTML
            <div class="report-header">
                <img src="$logoPath" alt="Logo" class="logo" onerror="this.style.display='none'">
                <h1>TEFA COFFEE</h1>
                <h2>{$this->getReportTitle()}</h2>
                <div class="report-info">
                    <table>
                        <tr><td>Periode</td><td>: $periodText</td></tr>
                        <tr><td>Total Data</td><td>: {$this->getTotalCount()} data</td></tr>
                    </table>
                </div>
            </div>
        HTML;
    }

    protected function renderFooter(bool $printMode): string
    {
        if (!$printMode) return '';
        $printDate = date('d M Y, H:i:s');
        return <<<HTML
            <div class="report-footer">
                <div class="footer-section">
                    <h4>Mengetahui,</h4><p>Manager TEFA Coffee</p>
                    <div class="signature-line"><strong>( ___________________ )</strong></div>
                </div>
                <div class="footer-section">
                    <h4>Dibuat Oleh,</h4><p>Admin TEFA Coffee</p>
                    <div class="signature-line"><strong>( ___________________ )</strong></div>
                </div>
            </div>
            <div class="print-date"> Dicetak pada: $printDate WIB</div>
        HTML;
    }

    abstract protected function getReportTitle(): string;
    abstract protected function getTotalCount(): int;

    protected function escape(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
    protected function formatRupiah(float $amount): string { return 'Rp ' . number_format($amount, 0, ',', '.'); }
    protected function formatDate(string $date, string $format = 'd M Y H:i'): string { return date($format, strtotime($date)); }
    protected function formatNumber(float $num, int $decimals = 0): string { return number_format($num, $decimals, ',', '.'); }
}

// ============================================================================
// CONCRETE REPORT CLASSES
// ============================================================================

class SalesReport extends AbstractReport
{
    protected function loadData(): void
    {
        $start = $this->conn->real_escape_string($this->startDate);
        $end = $this->conn->real_escape_string($this->endDate);
        $query = "SELECT t.*, u.nama_lengkap FROM transactions t 
                  JOIN users u ON t.user_id = u.id 
                  WHERE DATE(t.tanggal_transaksi) BETWEEN '$start' AND '$end' 
                  AND t.status_pembayaran IN ('lunas','dikonfirmasi') 
                  ORDER BY t.tanggal_transaksi DESC";
        $result = $this->conn->query($query);
        $this->data = [];
        while ($row = $result->fetch_assoc()) { $this->data[] = $row; }
    }

    protected function calculateSummary(): void
    {
        $start = $this->conn->real_escape_string($this->startDate);
        $end = $this->conn->real_escape_string($this->endDate);
        $result = $this->conn->query("SELECT SUM(total_harga) as total, COUNT(*) as count FROM transactions 
            WHERE DATE(tanggal_transaksi) BETWEEN '$start' AND '$end' 
            AND status_pembayaran IN ('lunas','dikonfirmasi')")->fetch_assoc();
        $total = (float)($result['total'] ?? 0);
        $count = (int)($result['count'] ?? 0);
        $this->summary = [
            'total_revenue' => $total,
            'total_transactions' => $count,
            'average_per_transaction' => $count > 0 ? $total / $count : 0
        ];
    }

    protected function renderContent(bool $printMode): string
    {
        if (empty($this->data)) {
            return '<div class="empty-state"><i class="fas fa-inbox"></i><h3>Tidak Ada Data</h3><p>Tidak ada transaksi pada periode yang dipilih</p></div>';
        }
        if ($printMode) return $this->renderPrintTable();
        return $this->renderWebTable();
    }

    private function renderPrintTable(): string
    {
        $rows = '';
        $no = 1;
        foreach ($this->data as $t) {
            $rows .= "<tr><td class=\"text-center\">$no</td><td>{$this->formatDate($t['tanggal_transaksi'])}</td>
                      <td>{$this->escape($t['kode_transaksi'])}</td><td>{$this->escape($t['nama_lengkap'])}</td>
                      <td class=\"text-right\">{$this->formatRupiah($t['total_harga'])}</td></tr>";
            $no++;
        }
        $avg = $this->formatRupiah($this->summary['average_per_transaction']);
        return "<table class=\"data-table\"><thead><tr><th style=\"width:5%\">No</th><th style=\"width:15%\">Tanggal</th>
                <th style=\"width:20%\">Kode</th><th style=\"width:25%\">Customer</th><th style=\"width:20%\">Total</th></tr></thead>
                <tbody>$rows</tbody></table>
                <div class=\"summary-box\"><h3>Ringkasan Penjualan</h3><table class=\"summary-table\">
                <tr><td>Total Transaksi</td><td>{$this->summary['total_transactions']} transaksi</td></tr>
                <tr><td>Total Pendapatan</td><td>{$this->formatRupiah($this->summary['total_revenue'])}</td></tr>
                <tr class=\"total-row\"><td>Rata-rata/Transaksi</td><td>$avg</td></tr></table></div>";
    }

    private function renderWebTable(): string
    {
        $stats = "<div class=\"stats-row\">
            <div class=\"stat-box\"><div class=\"label\">Total Pendapatan</div><div class=\"value success text-rupiah\">{$this->formatRupiah($this->summary['total_revenue'])}</div></div>
            <div class=\"stat-box\"><div class=\"label\">Total Transaksi</div><div class=\"value\">{$this->summary['total_transactions']}</div></div>
            <div class=\"stat-box\"><div class=\"label\">Rata-rata/Transaksi</div><div class=\"value text-rupiah\">{$this->formatRupiah($this->summary['average_per_transaction'])}</div></div>
        </div>";
        if (empty($this->data)) {
            return $stats . '<div class="table-responsive"><table class="table table-custom"><tbody><tr><td colspan="4" class="text-center py-4"><div class="empty-state"><i class="fas fa-inbox"></i><p>Tidak ada transaksi pada periode ini</p></div></td></tr></tbody></table></div>';
        }
        $rows = '';
        foreach ($this->data as $t) {
            $rows .= "<tr><td data-label=\"Tanggal\">{$this->formatDate($t['tanggal_transaksi'], 'd/m/Y H:i')}</td>
                      <td data-label=\"Kode\" class=\"fw-semibold\">{$this->escape($t['kode_transaksi'])}</td>
                      <td data-label=\"Customer\">{$this->escape($t['nama_lengkap'])}</td>
                      <td data-label=\"Total\" class=\"text-rupiah fw-semibold\">{$this->formatRupiah($t['total_harga'])}</td></tr>";
        }
        return $stats . "<div class=\"table-responsive\"><table class=\"table table-custom\">
            <thead><tr><th>Tanggal</th><th>Kode</th><th>Customer</th><th>Total</th></tr></thead>
            <tbody>$rows</tbody>
            <tfoot><tr><td colspan=\"3\" class=\"text-end\"><strong>TOTAL</strong></td>
            <td class=\"text-rupiah\"><strong>{$this->formatRupiah($this->summary['total_revenue'])}</strong></td></tr></tfoot>
        </table></div>";
    }

    protected function getReportTitle(): string { return 'LAPORAN PENJUALAN'; }
    protected function getTotalCount(): int { return $this->summary['total_transactions']; }
}

class StockReport extends AbstractReport
{
    protected function loadData(): void
    {
        $start = $this->conn->real_escape_string($this->startDate);
        $end = $this->conn->real_escape_string($this->endDate);
        $dateFilter = ($this->startDate && $this->endDate) ? "AND DATE(tanggal) BETWEEN '$start' AND '$end'" : "";
        $query = "SELECT p.*,
            COALESCE((SELECT SUM(jumlah) FROM stock_movements WHERE product_id = p.id AND jenis = 'masuk' $dateFilter), 0) as total_masuk,
            COALESCE((SELECT SUM(jumlah) FROM stock_movements WHERE product_id = p.id AND jenis = 'keluar' $dateFilter), 0) as total_keluar
            FROM products p ORDER BY p.nama_produk";
        $result = $this->conn->query($query);
        $this->data = [];
        while ($row = $result->fetch_assoc()) { $this->data[] = $row; }
    }

    protected function calculateSummary(): void
    {
        $result = $this->conn->query("SELECT COUNT(*) as total_items, SUM(stok) as total_stock, 
            SUM(CASE WHEN stok < 20 THEN 1 ELSE 0 END) as low_stock FROM products")->fetch_assoc();
        $this->summary = [
            'total_items' => (int)($result['total_items'] ?? 0),
            'total_stock' => (int)($result['total_stock'] ?? 0),
            'low_stock' => (int)($result['low_stock'] ?? 0)
        ];
    }

    protected function renderContent(bool $printMode): string
    {
        if (empty($this->data)) {
            return '<div class="empty-state"><i class="fas fa-box-open"></i><h3>Tidak Ada Data</h3><p>Belum ada data produk</p></div>';
        }
        
        $rows = '';
        $no = 1;
        foreach ($this->data as $p) {
            $badge = $p['stok'] < 20 ? 'badge-danger' : 'badge-success';
            $rows .= "<tr><td class=\"text-center\">$no</td><td>{$this->escape($p['nama_produk'])}</td>
                      <td>{$this->escape(ucfirst($p['kategori']))}</td>
                      <td class=\"text-right\"><span class=\"badge-custom $badge\">{$p['stok']} unit</span></td>
                      <td class=\"text-right text-success\">+{$p['total_masuk']}</td>
                      <td class=\"text-right text-danger\">-{$p['total_keluar']}</td></tr>";
            $no++;
        }
        
        $tableClass = $printMode ? 'data-table' : 'table table-custom';
        $thead = $printMode 
            ? '<thead><tr><th style="width:5%">No</th><th style="width:30%">Produk</th><th style="width:15%">Kategori</th><th style="width:15%">Stok</th><th style="width:15%">Masuk</th><th style="width:15%">Keluar</th></tr></thead>'
            : '<thead><tr><th>No</th><th>Produk</th><th>Kategori</th><th>Stok</th><th>Masuk</th><th>Keluar</th></tr></thead>';
        
        $table = "<table class=\"$tableClass\">$thead<tbody>$rows</tbody></table>";
        
        $summary = $printMode
            ? "<div class=\"summary-box\"><h3>Ringkasan Stok</h3><table class=\"summary-table\">
                <tr><td>Total Item</td><td>{$this->summary['total_items']} produk</td></tr>
                <tr><td>Total Stok</td><td>{$this->summary['total_stock']} unit</td></tr>
                <tr class=\"total-row\"><td>Stok Kritis (&lt;20)</td><td>{$this->summary['low_stock']} produk</td></tr>
            </table></div>"
            : "<div class=\"stats-row\">
                <div class=\"stat-box\"><div class=\"label\">Total Item</div><div class=\"value\">{$this->summary['total_items']} produk</div></div>
                <div class=\"stat-box\"><div class=\"label\">Total Stok</div><div class=\"value\">{$this->summary['total_stock']} unit</div></div>
                <div class=\"stat-box\"><div class=\"label\">Stok Kritis (&lt;20)</div><div class=\"value danger\">{$this->summary['low_stock']} produk</div></div>
            </div>";
        
        if ($printMode) {
            return $table . $summary;
        } else {
            return "<div class=\"table-responsive\">$table</div>" . $summary;
        }
    }

    protected function getReportTitle(): string { return 'LAPORAN STOK PRODUK'; }
    protected function getTotalCount(): int { return $this->summary['total_items']; }
}

class BeansHistoryReport extends AbstractReport
{
    protected function loadData(): void
    {
        $where = "1=1";
        if (!empty($this->filters['bean_id'])) $where .= " AND smb.bean_id = " . (int)$this->filters['bean_id'];
        if (!empty($this->filters['jenis'])) {
            $jenis = $this->conn->real_escape_string($this->filters['jenis']);
            $where .= " AND smb.jenis = '$jenis'";
        }
        if (!empty($this->filters['date_from'])) {
            $date = $this->conn->real_escape_string($this->filters['date_from']);
            $where .= " AND DATE(smb.tanggal) >= '$date'";
        }
        if (!empty($this->filters['date_to'])) {
            $date = $this->conn->real_escape_string($this->filters['date_to']);
            $where .= " AND DATE(smb.tanggal) <= '$date'";
        }
        $query = "SELECT smb.*, cb.nama_biji_kopi FROM stock_movements_beans smb 
                  JOIN coffee_beans cb ON smb.bean_id = cb.id WHERE $where 
                  ORDER BY smb.tanggal DESC LIMIT 500";
        $result = $this->conn->query($query);
        $this->data = [];
        while ($row = $result->fetch_assoc()) { $this->data[] = $row; }
    }

    protected function calculateSummary(): void
    {
        $masuk = 0; $keluar = 0;
        foreach ($this->data as $item) {
            if ($item['jenis'] === 'masuk') $masuk += (float)$item['jumlah'];
            else $keluar += (float)$item['jumlah'];
        }
        $this->summary = [
            'total_masuk' => $masuk, 'total_keluar' => $keluar,
            'netto' => $masuk - $keluar, 'total_records' => count($this->data)
        ];
    }

    protected function renderContent(bool $printMode): string
    {
        if (empty($this->data)) {
            return '<div class="empty-state"><i class="fas fa-inbox"></i><h3>Tidak Ada Data</h3><p>Tidak ada riwayat stok biji kopi yang sesuai dengan filter yang dipilih</p></div>';
        }
        
        $rows = '';
        $no = 1;
        foreach ($this->data as $hist) {
            $sign = $hist['jenis'] === 'masuk' ? '+' : '-';
            $badge = $hist['jenis'] === 'masuk' ? 'badge-success' : 'badge-danger';
            $icon = $hist['jenis'] === 'masuk' ? 'arrow-down' : 'arrow-up';
            $color = $hist['jenis'] === 'masuk' ? 'text-success' : 'text-danger';
            $rows .= "<tr><td class=\"text-center\">$no</td><td>{$this->formatDate($hist['tanggal'])}</td>
                      <td>{$this->escape($hist['nama_biji_kopi'])}</td>
                      <td class=\"text-center\"><span class=\"badge-custom $badge\"><i class=\"fas fa-$icon\"></i> {$this->escape(ucfirst($hist['jenis']))}</span></td>
                      <td class=\"text-right fw-bold $color\">{$sign} {$this->formatNumber($hist['jumlah'], 1)} kg</td>
                      <td>{$this->escape($hist['keterangan'] ?? '-')}</td></tr>";
            $no++;
        }
        
        $nettoSign = $this->summary['netto'] >= 0 ? '+' : '';
        $tableClass = $printMode ? 'data-table' : 'table table-custom';
        
        $table = "<table class=\"$tableClass\">
            <thead>
                <tr>
                    <th style=\"width:5%\">No</th>
                    <th style=\"width:15%\">Tanggal</th>
                    <th style=\"width:30%\">Nama Biji Kopi</th>
                    <th style=\"width:12%\">Jenis</th>
                    <th style=\"width:13%\">Jumlah</th>
                    <th style=\"width:25%\">Keterangan</th>
                </tr>
            </thead>
            <tbody>$rows</tbody>
        </table>";
        
        $summary = $printMode
            ? "<div class=\"summary-box\"><h3>Ringkasan Stok</h3><table class=\"summary-table\">
                <tr><td>Total Stok Masuk</td><td>+{$this->formatNumber($this->summary['total_masuk'], 1)} kg</td></tr>
                <tr><td>Total Stok Keluar</td><td>-{$this->formatNumber($this->summary['total_keluar'], 1)} kg</td></tr>
                <tr class=\"total-row\"><td>Netto Perubahan</td><td>{$nettoSign}{$this->formatNumber($this->summary['netto'], 1)} kg</td></tr>
            </table></div>"
            : "<div class=\"stats-row\">
                <div class=\"stat-box\"><div class=\"label\">Total Riwayat</div><div class=\"value\">{$this->summary['total_records']}</div></div>
                <div class=\"stat-box\"><div class=\"label\">Total Masuk</div><div class=\"value success\">+{$this->formatNumber($this->summary['total_masuk'], 1)} kg</div></div>
                <div class=\"stat-box\"><div class=\"label\">Total Keluar</div><div class=\"value danger\">-{$this->formatNumber($this->summary['total_keluar'], 1)} kg</div></div>
                <div class=\"stat-box\"><div class=\"label\">Netto</div><div class=\"value\">{$nettoSign}{$this->formatNumber($this->summary['netto'], 1)} kg</div></div>
            </div>";
        
        if ($printMode) {
            return $table . $summary;
        } else {
            return "<div class=\"table-responsive\">$table</div>" . $summary;
        }
    }

    protected function getReportTitle(): string { return 'LAPORAN RIWAYAT STOK BIJI KOPI'; }
    protected function getTotalCount(): int { return $this->summary['total_records']; }
}

class InventoryReport extends AbstractReport
{
    protected function loadData(): void
    {
        $where = "1=1";
        if (!empty($this->filters['kategori'])) {
            $kategori = $this->conn->real_escape_string($this->filters['kategori']);
            $where .= " AND kategori = '$kategori'";
        }
        if (!empty($this->filters['kondisi'])) {
            $kondisi = $this->conn->real_escape_string($this->filters['kondisi']);
            $where .= " AND kondisi = '$kondisi'";
        }
        $query = "SELECT * FROM inventory WHERE $where ORDER BY created_at DESC";
        $result = $this->conn->query($query);
        $this->data = [];
        while ($row = $result->fetch_assoc()) { $this->data[] = $row; }
    }

    protected function calculateSummary(): void
    {
        $result = $this->conn->query("SELECT COUNT(*) as total_items, SUM(jumlah) as total_qty,
            SUM(CASE WHEN kondisi='Baik' THEN 1 ELSE 0 END) as kondisi_baik,
            SUM(CASE WHEN kondisi='Dalam Perbaikan' THEN 1 ELSE 0 END) as kondisi_perbaikan,
            SUM(CASE WHEN kondisi IN ('Rusak Ringan','Rusak Berat') THEN 1 ELSE 0 END) as kondisi_rusak
            FROM inventory")->fetch_assoc();
        $this->summary = [
            'total_items' => (int)($result['total_items'] ?? 0),
            'total_qty' => (int)($result['total_qty'] ?? 0),
            'kondisi_baik' => (int)($result['kondisi_baik'] ?? 0),
            'kondisi_perbaikan' => (int)($result['kondisi_perbaikan'] ?? 0),
            'kondisi_rusak' => (int)($result['kondisi_rusak'] ?? 0)
        ];
    }

    protected function renderContent(bool $printMode): string
    {
        if (empty($this->data)) {
            return '<div class="empty-state"><i class="fas fa-clipboard-list"></i><h3>Tidak Ada Data</h3><p>Tidak ada data inventaris</p></div>';
        }
        
        $getBadge = fn($kondisi) => match($kondisi) {
            'Baik' => 'badge-success', 'Dalam Perbaikan' => 'badge-warning', default => 'badge-danger'
        };
        $rows = '';
        $no = 1;
        foreach ($this->data as $item) {
            $badge = $getBadge($item['kondisi']);
            $rows .= "<tr><td class=\"text-center\">$no</td><td>{$this->escape($item['nama_barang'])}</td>
                      <td>{$this->escape($item['kategori'])}</td><td class=\"text-right fw-semibold\">{$item['jumlah']}</td>
                      <td class=\"text-center\"><span class=\"badge-custom $badge\">{$this->escape($item['kondisi'])}</span></td>
                      <td class=\"text-center\">{$this->formatDate($item['tanggal_pembelian'], 'd M Y')}</td>
                      <td>{$this->escape($item['keterangan'] ?? '-')}</td></tr>";
            $no++;
        }
        
        $tableClass = $printMode ? 'data-table' : 'table table-custom';
        $thead = $printMode
            ? '<thead><tr><th style="width:5%">No</th><th style="width:25%">Nama Barang</th><th style="width:15%">Kategori</th><th style="width:10%">Jumlah</th><th style="width:15%">Kondisi</th><th style="width:15%">Tanggal Beli</th><th style="width:15%">Keterangan</th></tr></thead>'
            : '<thead><tr><th>No</th><th>Nama Barang</th><th>Kategori</th><th>Jumlah</th><th>Kondisi</th><th>Tanggal Beli</th><th>Keterangan</th></tr></thead>';
        
        $table = "<table class=\"$tableClass\">$thead<tbody>$rows</tbody></table>";
        
        $alert = '';
        if ($this->summary['kondisi_perbaikan'] > 0 || $this->summary['kondisi_rusak'] > 0) {
            $alert = "<div class=\"alert-custom alert-warning mt-4\"><i class=\"fas fa-wrench\"></i><strong>Perhatian:</strong> {$this->summary['kondisi_perbaikan']} item dalam perbaikan, {$this->summary['kondisi_rusak']} item rusak. Segera jadwalkan maintenance.</div>";
        }
        
        $summary = $printMode
            ? "<div class=\"summary-box\"><h3>Ringkasan Inventaris</h3><table class=\"summary-table\">
                <tr><td>Total Item</td><td>{$this->summary['total_items']} item</td></tr>
                <tr><td>Total Qty</td><td>{$this->summary['total_qty']} unit</td></tr>
                <tr><td>Kondisi Baik</td><td>{$this->summary['kondisi_baik']} item</td></tr>
                <tr><td>Dalam Perbaikan</td><td>{$this->summary['kondisi_perbaikan']} item</td></tr>
                <tr class=\"total-row\"><td>Rusak</td><td>{$this->summary['kondisi_rusak']} item</td></tr>
            </table></div>"
            : "<div class=\"stats-row\">
                <div class=\"stat-box\"><div class=\"label\">Total Item</div><div class=\"value\">{$this->summary['total_items']}</div></div>
                <div class=\"stat-box\"><div class=\"label\">Total Qty</div><div class=\"value\">{$this->summary['total_qty']} unit</div></div>
                <div class=\"stat-box\"><div class=\"label\">Kondisi Baik</div><div class=\"value success\">{$this->summary['kondisi_baik']}</div></div>
                <div class=\"stat-box\"><div class=\"label\">Perbaikan</div><div class=\"value warning\">{$this->summary['kondisi_perbaikan']}</div></div>
                <div class=\"stat-box\"><div class=\"label\">Rusak</div><div class=\"value danger\">{$this->summary['kondisi_rusak']}</div></div>
            </div>";
        
        if ($printMode) {
            return $table . $alert . $summary;
        } else {
            return "<div class=\"table-responsive\">$table</div>" . $alert . $summary;
        }
    }

    protected function getReportTitle(): string { return 'LAPORAN INVENTARIS BARANG'; }
    protected function getTotalCount(): int { return $this->summary['total_items']; }
}

// ============================================================================
// FACTORY PATTERN
// ============================================================================

class ReportFactory
{
    private const REPORT_TYPES = [
        'sales' => SalesReport::class,
        'stock' => StockReport::class,
        'beans_history' => BeansHistoryReport::class,
        'inventory' => InventoryReport::class
    ];

    public static function create(mysqli $conn, string $type, string $startDate, string $endDate, array $filters = []): AbstractReport
    {
        if (!isset(self::REPORT_TYPES[$type])) throw new InvalidArgumentException("Report type '$type' not supported");
        $class = self::REPORT_TYPES[$type];
        return new $class($conn, $startDate, $endDate, $filters);
    }

    public static function getAvailableTypes(): array { return array_keys(self::REPORT_TYPES); }
    public static function isValidType(string $type): bool { return isset(self::REPORT_TYPES[$type]); }
}

// ============================================================================
// MAIN APPLICATION LOGIC
// ============================================================================

require_once '../config/config.php';
checkRole('manager');

$printMode = isset($_GET['print']) && $_GET['print'] === '1';

// ✅ VALIDASI TANGGAL - TAMBAHAN FIX
function validateDate($date, $default) {
    if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return $default;
    }
    $timestamp = strtotime($date);
    return $timestamp ? $date : $default;
}

$startDate = validateDate($_GET['start'] ?? '', date('Y-m-01'));
$endDate = validateDate($_GET['end'] ?? '', date('Y-m-d'));

// Pastikan end >= start
if (strtotime($endDate) < strtotime($startDate)) {
    $endDate = $startDate;
}

$reportType = $_GET['type'] ?? 'sales';

if (!ReportFactory::isValidType($reportType)) $reportType = 'sales';

$filters = [];
switch ($reportType) {
    case 'beans_history':
        $filters = ['bean_id'=>$_GET['filter_bean']??'', 'jenis'=>$_GET['filter_bean_jenis']??'', 'date_from'=>$_GET['filter_bean_date_from']??'', 'date_to'=>$_GET['filter_bean_date_to']??''];
        break;
    case 'inventory':
        $filters = ['kategori'=>$_GET['inv_kategori']??'', 'kondisi'=>$_GET['inv_kondisi']??''];
        break;
}

$report = ReportFactory::create($conn, $reportType, $startDate, $endDate, $filters);

$beansList = [];
if ($reportType === 'beans_history') {
    $result = $conn->query("SELECT id, nama_biji_kopi FROM coffee_beans WHERE stok > 0 ORDER BY nama_biji_kopi");
    while ($row = $result->fetch_assoc()) $beansList[] = $row;
}

// ============================================================================
// PRINT MODE OUTPUT
// ============================================================================
if ($printMode): ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - TEFA COFFEE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/receipt.css">
</head>
<body>
    <div class="print-actions">
        <button onclick="window.print()" class="btn-print"><i class="fas fa-print"></i> Cetak Laporan</button>
        <a href="reports.php?type=<?= $reportType ?>&start=<?= $startDate ?>&end=<?= $endDate ?>" class="btn-close"><i class="fas fa-times"></i> Kembali</a>
    </div>
    <div class="container"><?= $report->render(true) ?></div>
    <script>
    window.addEventListener('load', function() {
        setTimeout(function() {
            window.print();
        }, 500);
    });
    
    // Opsional: Redirect setelah print selesai 
    window.addEventListener('afterprint', function() {
        /
    });
</script>
</body>
</html>
<?php exit; endif; ?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - TEFA Coffee</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/reports.css">
</head>
<body>
    <div class="top-header"><div class="container"><div class="header-content">
        <div class="logo-container">
            <img src="../assets/images/logopolije.png" alt="Polije" class="logo-polije" onerror="this.src='https://via.placeholder.com/42x42/2C1810/FFFFFF?text=P'">
            <div class="logo-divider"></div>
            <img src="../assets/images/sip.png" alt="TEFA" class="logo-tefa" onerror="this.src='https://via.placeholder.com/42x42/A67C52/FFFFFF?text=T'">
            <span class="brand-text">TEFA COFFEE</span>
        </div>
        <button class="hamburger-btn" id="hamburgerBtn"><i class="fas fa-bars"></i></button>
    </div></div></div>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="sidebar" id="sidebar">
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
            <li><a href="reports.php" class="active"><i class="fas fa-file-alt"></i><span>Laporan</span></a></li>
            <li><a href="analytics.php"><i class="fas fa-chart-bar"></i><span>Analisis</span></a></li>
            <div class="sidebar-divider"></div>
            <li><a href="../logout.php" style="color:#ef9a9a;"><i class="fas fa-right-from-bracket"></i><span>Logout</span></a></li>
        </ul>
    </div>

    <div class="main-wrapper"><div class="main-content">
        <div class="page-header"><h1 class="page-title">Laporan TEFA Coffee</h1></div>
        
        <div class="report-tabs no-print">
            <?php foreach(ReportFactory::getAvailableTypes() as $type): 
                $labels = ['sales'=>['icon'=>'fa-chart-line','label'=>'Penjualan'],'stock'=>['icon'=>'fa-warehouse','label'=>'Stok Produk'],
                          'beans_history'=>['icon'=>'fa-history','label'=>'Riwayat Biji Kopi'],'inventory'=>['icon'=>'fa-clipboard-list','label'=>'Inventaris']]; ?>
            <a href="?type=<?= $type ?>&start=<?= $startDate ?>&end=<?= $endDate ?>" class="tab-btn <?= $reportType===$type?'active':'' ?>">
                <i class="fas <?= $labels[$type]['icon'] ?>"></i><?= $labels[$type]['label'] ?>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if(!in_array($reportType,['inventory','beans_history'])): ?>
        <!-- ✅ FILTER CARD DIPERBAIKI: Tambah onchange + tombol submit -->
        <div class="filter-card no-print">
            <form method="GET" class="row g-3" id="dateFilterForm">
                <input type="hidden" name="type" value="<?= htmlspecialchars($reportType) ?>">
                <div class="col-md-4">
                    <label class="form-label">Tanggal Mulai</label>
                    <input type="date" name="start" 
                           value="<?= htmlspecialchars($startDate) ?>" 
                           class="form-control"
                           id="startDate"
                           onchange="this.form.submit()"
                           max="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tanggal Akhir</label>
                    <input type="date" name="end" 
                           value="<?= htmlspecialchars($endDate) ?>" 
                           class="form-control"
                           id="endDate"
                           onchange="this.form.submit()"
                           max="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-custom btn-coffee w-100" style="display:none;">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <div class="card-custom">
            <div class="card-header-custom">
                <span><?= [
                    'sales' => 'Laporan Penjualan ' . date('d/m/Y',strtotime($startDate)) . ' - ' . date('d/m/Y',strtotime($endDate)),
                    'stock' => 'Laporan Stok Produk ' . date('d/m/Y',strtotime($startDate)) . ' - ' . date('d/m/Y',strtotime($endDate)),
                    'beans_history' => 'Laporan Stok Biji Kopi',
                    'inventory' => 'Laporan Inventaris Barang'
                ][$reportType] ?></span>
                <button class="btn-custom btn-coffee btn-sm no-print" onclick="openPrintReport()"><i class="fas fa-file-pdf"></i> Cetak Laporan</button>
            </div>
            
            <?php if($reportType === 'beans_history' || $reportType === 'inventory'): ?>
            <div class="card-body" style="background: var(--coffee-cream-light); border-bottom: 1px solid var(--border-light); padding: 1rem 1.35rem;">
                <?php if($reportType === 'beans_history'): ?>
                <form method="GET" class="row g-3">
                    <input type="hidden" name="type" value="beans_history">
                    <input type="hidden" name="start" value="<?= htmlspecialchars($startDate) ?>">
                    <input type="hidden" name="end" value="<?= htmlspecialchars($endDate) ?>">
                    <div class="col-md-3">
                        <select name="filter_bean" class="form-select" onchange="this.form.submit()">
                            <option value="">Semua Biji Kopi</option>
                            <?php foreach($beansList as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= ($filters['bean_id']??'')==$b['id']?'selected':'' ?>><?= htmlspecialchars($b['nama_biji_kopi']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="filter_bean_jenis" class="form-select" onchange="this.form.submit()">
                            <option value="">Semua Jenis</option>
                            <option value="masuk" <?= ($filters['jenis']??'')==='masuk'?'selected':'' ?>>Masuk</option>
                            <option value="keluar" <?= ($filters['jenis']??'')==='keluar'?'selected':'' ?>>Keluar</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="date" name="filter_bean_date_from" value="<?= htmlspecialchars($filters['date_from']??'') ?>" class="form-control" onchange="this.form.submit()" placeholder="Dari Tanggal">
                    </div>
                    <div class="col-md-3">
                        <input type="date" name="filter_bean_date_to" value="<?= htmlspecialchars($filters['date_to']??'') ?>" class="form-control" onchange="this.form.submit()" placeholder="Sampai Tanggal">
                    </div>
                    <?php if(array_filter($filters)): ?>
                    <div class="col-md-1">
                        <a href="?type=beans_history&start=<?= $startDate ?>&end=<?= $endDate ?>" class="btn-custom btn-secondary btn-sm w-100">Reset</a>
                    </div>
                    <?php endif; ?>
                </form>
                <?php else: ?>
                <form method="GET" class="row g-3">
                    <input type="hidden" name="type" value="inventory">
                    <div class="col-md-4">
                        <select name="inv_kategori" class="form-select" onchange="this.form.submit()">
                            <option value="">Semua Kategori</option>
                            <?php foreach(['Mesin Roasting'=>'Roasting','Peralatan Pendinginan'=>'Storage','Mesin Grinding'=>'Grinding','Alat Ukur'=>'Aksesoris','Quality Control'=>'QC'] as $val=>$lbl): ?>
                                <option value="<?= $val ?>" <?= ($filters['kategori']??'')===$val?'selected':'' ?>><?= $lbl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select name="inv_kondisi" class="form-select" onchange="this.form.submit()">
                            <option value="">Semua Kondisi</option>
                            <?php foreach(['Baik'=>'Baik','Dalam Perbaikan'=>'Perbaikan','Rusak Ringan'=>'Rusak'] as $val=>$lbl): ?>
                                <option value="<?= $val ?>" <?= ($filters['kondisi']??'')===$val?'selected':'' ?>><?= $lbl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if(array_filter($filters)): ?>
                    <div class="col-md-4">
                        <a href="?type=inventory" class="btn-custom btn-secondary btn-sm w-100">Reset Filter</a>
                    </div>
                    <?php endif; ?>
                </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="card-body">
                <?= $report->render(false) ?>
            </div>
        </div>
    </div></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const hamburgerBtn=document.getElementById('hamburgerBtn'),sidebar=document.getElementById('sidebar'),sidebarOverlay=document.getElementById('sidebarOverlay');
        function toggleSidebar(){sidebar.classList.toggle('active');sidebarOverlay.classList.toggle('active');document.body.style.overflow=sidebar.classList.contains('active')?'hidden':'';}
        hamburgerBtn?.addEventListener('click',toggleSidebar);sidebarOverlay?.addEventListener('click',toggleSidebar);
        document.querySelectorAll('.sidebar-menu a').forEach(link=>link.addEventListener('click',()=>{if(window.innerWidth<=768)toggleSidebar();}));
        window.addEventListener('resize',()=>{if(window.innerWidth>768){sidebar.classList.remove('active');sidebarOverlay.classList.remove('active');document.body.style.overflow='';}});
        
       function openPrintReport(){
    const params = new URLSearchParams();
    params.append('type', '<?= $reportType ?>');
    params.append('print', '1');
    params.append('start', '<?= $startDate ?>');
    params.append('end', '<?= $endDate ?>');
    
    <?php if($reportType === 'beans_history'): ?>
    const bean = document.querySelector('select[name="filter_bean"]')?.value || '';
    const jenis = document.querySelector('select[name="filter_bean_jenis"]')?.value || '';
    const dateFrom = document.querySelector('input[name="filter_bean_date_from"]')?.value || '';
    const dateTo = document.querySelector('input[name="filter_bean_date_to"]')?.value || '';
    if(bean) params.append('filter_bean', bean);
    if(jenis) params.append('filter_bean_jenis', jenis);
    if(dateFrom) params.append('filter_bean_date_from', dateFrom);
    if(dateTo) params.append('filter_bean_date_to', dateTo);
    <?php endif; ?>
    
    <?php if($reportType === 'inventory'): ?>
    const invKategori = document.querySelector('select[name="inv_kategori"]')?.value || '';
    const invKondisi = document.querySelector('select[name="inv_kondisi"]')?.value || '';
    if(invKategori) params.append('inv_kategori', invKategori);
    if(invKondisi) params.append('inv_kondisi', invKondisi);
    <?php endif; ?>
    
    const printUrl = 'reports.php?' + params.toString();
    const printWindow = window.open(printUrl, '_blank', 'width=1000,height=800,scrollbars=yes');
    
    if (printWindow) {
        printWindow.focus();
    } else {
        alert(' Popup diblokir browser! Izinkan popup untuk situs ini agar dapat mencetak laporan.');
    }
}

// ✅ TAMBAHAN: Validasi tanggal sebelum submit untuk filter sales/stock
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('dateFilterForm');
    const startInput = document.getElementById('startDate');
    const endInput = document.getElementById('endDate');
    
    if (form && startInput && endInput) {
        // Validasi: end tidak boleh sebelum start
        [startInput, endInput].forEach(input => {
            input.addEventListener('change', function() {
                if (startInput.value && endInput.value && endInput.value < startInput.value) {
                    alert('Tanggal akhir tidak boleh sebelum tanggal mulai');
                    endInput.value = startInput.value;
                    return;
                }
                // Form akan auto-submit via onchange attribute
            });
        });
        
        form.addEventListener('submit', function(e) {
            if (startInput.value && endInput.value && endInput.value < startInput.value) {
                e.preventDefault();
                alert('Tanggal akhir tidak boleh sebelum tanggal mulai');
                endInput.focus();
            }
        });
    }
});
    </script>
</body>
</html>