<?php
require_once '../config/config.php';
checkRole('customer');

// Cek parameter kode
if (!isset($_GET['kode'])) {
    redirect('customer/dashboard.php');
}

$kode_transaksi = $_GET['kode'];
$user_id = $_SESSION['user_id'];

// Ambil data transaksi (untuk BOTH JSON dan HTML)
$transaction = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT * FROM transactions 
    WHERE kode_transaksi = '$kode_transaksi' AND user_id = '$user_id'
"));

if (!$transaction) {
    // Handle untuk JSON
    if (isset($_GET['format']) && $_GET['format'] === 'json') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Transaksi tidak ditemukan']);
        exit;
    }
    // Handle untuk HTML
    die("<div style='text-align:center;padding:20px;font-family:Arial;'>
            <h2> Transaksi Tidak Ditemukan</h2>
            <a href='dashboard.php'>Kembali</a>
         </div>");
}

// Ambil data user
$user = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT nama_lengkap, telepon FROM users WHERE id = '$user_id'
"));
$nama_lengkap = $user['nama_lengkap'] ?? $_SESSION['nama'] ?? 'Customer';
$telepon = $user['telepon'] ?? '-';

// Ambil detail items
$items = [];
$details = mysqli_query($conn, "
    SELECT td.*, p.nama_produk 
    FROM transaction_details td
    LEFT JOIN products p ON td.product_id = p.id
    WHERE td.transaction_id = '{$transaction['id']}'
");
while ($item = mysqli_fetch_assoc($details)) {
    $items[] = [
        'nama_produk' => $item['nama_produk'] ?? 'Produk',
        'qty' => $item['qty'] ?? $item['quantity'] ?? 1,
        'harga_satuan' => $item['harga_satuan'] ?? $item['harga'] ?? 0,
        'subtotal' => $item['subtotal'] ?? 0
    ];
}

// ============================================
// JSON RESPONSE FOR MODAL (AJAX)
// ============================================
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
    echo json_encode([
        'kode_transaksi' => $transaction['kode_transaksi'],
        'tanggal_transaksi' => $transaction['tanggal_transaksi'],
        'nama_lengkap' => $nama_lengkap,
        'telepon' => $telepon,
        'status_pembayaran' => $transaction['status_pembayaran'],
        'status_pengambilan' => $transaction['status_pengambilan'] ?? 'belum_diambil',
        'tanggal_diambil' => $transaction['tanggal_diambil'] ?? '',
        'diambil_oleh' => $transaction['diambil_oleh'] ?? '',
        'metode_pembayaran' => $transaction['metode_pembayaran'] ?? 'cod',
        'total_harga' => $transaction['total_harga'],
        'items' => $items
    ]);
    exit; // ← Exit hanya untuk JSON
}

// ============================================
// HTML RESPONSE FOR DIRECT ACCESS / PRINT
// ============================================
// Reset pointer query untuk HTML rendering
mysqli_data_seek($details, 0);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk - <?= $kode_transaksi ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/receipt.css">
</head>

<body>
    <div class="print-actions no-print">
        <button onclick="window.print()" class="btn-print"><i class="fas fa-print"></i> Cetak</button>
        <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali</a>
    </div>

    <div class="receipt">
        <div class="receipt-logo">
            <img src="../assets/images/logopolije.png" alt="Polije" onerror="this.style.display='none'">
            <div class="store-name">TEFA COFFEE</div>
            <div class="store-address">Politeknik Negeri Jember</div>
            <div class="store-address">Jl. Mastrip, Kotak Pos 164</div>
            <div class="store-address">Jember 68101, Jawa Timur</div>
            <div class="store-address"><i class="fas fa-phone"></i> 0812-3456-7890</div>
            <div class="divider"></div>
        </div>

        <div class="status-badge <?= $transaction['status_pembayaran'] ?>">
            <?= strtoupper($transaction['status_pembayaran']) ?>
        </div>

        <div class="receipt-info">
            <div class="row"><span class="label">No:</span><span><?= $transaction['kode_transaksi'] ?></span></div>
            <div class="row"><span
                    class="label">Tgl:</span><span><?= date('d/m/Y H:i', strtotime($transaction['tanggal_transaksi'])) ?></span>
            </div>
            <div class="row"><span class="label">Nama:</span><span><?= htmlspecialchars($nama_lengkap) ?></span></div>
            <div class="row"><span class="label">Telp:</span><span><?= htmlspecialchars($telepon) ?></span></div>
        </div>

        <div class="pickup-box">
            <div class="title"><i class="fas fa-box"></i> PENGAMBILAN PRODUK</div>
            <?php if (($transaction['status_pengambilan'] ?? '') == 'sudah_diambil'): ?>
                <div class="status taken">SUDAH DIAMBIL</div>
                <div class="info">
                    <?= date('d/m/Y H:i', strtotime($transaction['tanggal_diambil'] ?? 'now')) ?><br>
                    Oleh: <?= htmlspecialchars($transaction['diambil_oleh'] ?? '-') ?>
                </div>
            <?php else: ?>
                <div class="status pending">BELUM DIAMBIL</div>
                <div class="info">Tunjukkan struk ini ke admin saat ambil</div>
            <?php endif; ?>
        </div>

        <div class="items-divider">ITEM PESANAN</div>

        <?php if ($details && mysqli_num_rows($details) > 0):
            mysqli_data_seek($details, 0);
            while ($item = mysqli_fetch_assoc($details)):
                $qty = $item['qty'] ?? $item['quantity'] ?? 1;
                $harga = $item['harga_satuan'] ?? $item['harga'] ?? 0;
                $subtotal = $item['subtotal'] ?? ($qty * $harga);
                $nama_produk = $item['nama_produk'] ?? 'Produk';
                ?>
                <div class="item">
                    <span class="item-name"><?= htmlspecialchars($nama_produk) ?></span>
                    <span class="item-qty"><?= $qty ?>x<?= number_format($harga, 0, ',', '.') ?></span>
                    <span class="item-price"><?= number_format($subtotal, 0, ',', '.') ?></span>
                </div>
            <?php
            endwhile;
        else:
            ?>
            <div style="text-align:center;padding:10px;color:#999;">Detail pesanan tidak tersedia</div>
        <?php endif; ?>

        <div class="total-section">
            <div class="total-row">
                <span>TOTAL</span>
                <span>Rp <?= number_format($transaction['total_harga'], 0, ',', '.') ?></span>
            </div>
        </div>

        <div class="payment-box">
            <div class="method">METODE: COD (CASH)</div>
            <div>Bayar tunai di outlet TEFA Coffee</div>
        </div>

        <div class="receipt-footer">
            <div class="thank-you">*** TERIMA KASIH ***</div>
            <div class="note">Dukungan Anda membantu</div>
            <div class="note">mahasiswa Politeknik Jember</div>
            <div class="note" style="margin-top:5px;font-weight:bold;">Struk ini sah tanpa tanda tangan</div>
            
        </div>
    </div>

    <script>
        <?php if (isset($_GET['print'])): ?>
            window.addEventListener('load', function () {
                setTimeout(() => window.print(), 300);
            });
        <?php endif; ?>
    </script>
</body>
</html>