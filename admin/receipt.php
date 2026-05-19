<?php
require_once '../config/config.php';
checkRole('admin');

// Cek parameter kode
if (!isset($_GET['kode'])) {
    if (isset($_GET['format']) && $_GET['format'] === 'json') {
        http_response_code(400);
        echo json_encode(['error' => 'Kode transaksi tidak ditemukan']);
        exit;
    }
    redirect('admin/transactions.php');
}

$kode_transaksi = mysqli_real_escape_string($conn, $_GET['kode']);

// Ambil data transaksi (admin bisa lihat semua)
$transaction = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT t.*, u.nama_lengkap, u.telepon 
    FROM transactions t
    JOIN users u ON t.user_id = u.id
    WHERE t.kode_transaksi = '$kode_transaksi'
"));

if (!$transaction) {
    if (isset($_GET['format']) && $_GET['format'] === 'json') {
        http_response_code(404);
        echo json_encode(['error' => 'Transaksi tidak ditemukan']);
        exit;
    }
    die("<div style='text-align:center;padding:20px;font-family:Arial;'>
            <h2> Transaksi Tidak Ditemukan</h2>
            <a href='transactions.php'>Kembali</a>
         </div>");
}

$nama_lengkap = $transaction['nama_lengkap'] ?? 'Customer';
$telepon = $transaction['telepon'] ?? '-';

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

// JSON RESPONSE FOR MODAL (AJAX)

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
    exit;
}

mysqli_data_seek($details, 0);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk - <?= $kode_transaksi ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background: #f0f0f0;
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            line-height: 1.4;
            color: #000;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
        }

        .receipt {
            width: 320px;
            background: #fff;
            padding: 10px 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .receipt::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            right: 0;
            height: 10px;
            background: radial-gradient(circle at 5px -5px, transparent 5px, #fff 6px) repeat-x, radial-gradient(circle at 15px -5px, transparent 5px, #fff 6px) repeat-x;
            background-size: 20px 10px;
        }

        .receipt-logo {
            text-align: center;
            padding-bottom: 8px;
            border-bottom: 1px dashed #000;
            margin-bottom: 8px;
        }

        .receipt-logo img {
            width: 80px;
            height: auto;
            margin-bottom: 5px;
        }

        .receipt-logo .store-name {
            font-weight: bold;
            font-size: 14px;
            margin: 3px 0;
            text-transform: uppercase;
        }

        .receipt-logo .store-address {
            font-size: 9px;
            text-align: center;
            margin: 2px 0;
        }

        .receipt-logo .divider {
            border-bottom: 2px solid #000;
            margin: 5px 0;
        }

        .status-badge {
            text-align: center;
            font-weight: bold;
            padding: 3px 0;
            margin: 5px 0;
            border: 1px solid #000;
            text-transform: uppercase;
        }

        .status-badge.lunas,
        .status-badge.dikonfirmasi {
            background: #000;
            color: #fff;
        }

        .status-badge.pending {
            background: #fff;
            color: #000;
        }

        .receipt-info {
            margin: 8px 0;
            font-size: 10px;
        }

        .receipt-info .row {
            display: flex;
            justify-content: space-between;
            margin: 2px 0;
        }

        .receipt-info .label {
            font-weight: bold;
        }

        .pickup-box {
            border: 1px solid #000;
            padding: 5px;
            margin: 8px 0;
            font-size: 9px;
        }

        .pickup-box .title {
            font-weight: bold;
            margin-bottom: 3px;
            text-align: center;
        }

        .pickup-box .status {
            text-align: center;
            font-weight: bold;
            margin: 3px 0;
        }

        .pickup-box .info {
            margin-top: 3px;
            text-align: center;
        }

        .items-divider {
            border-top: 2px solid #000;
            border-bottom: 1px dashed #000;
            margin: 8px 0;
            padding: 3px 0;
            font-weight: bold;
            text-align: center;
        }

        .item {
            display: flex;
            justify-content: space-between;
            margin: 4px 0;
            font-size: 10px;
        }

        .item-name {
            flex: 1;
            padding-right: 5px;
        }

        .item-qty {
            margin: 0 3px;
        }

        .item-price {
            text-align: right;
            min-width: 70px;
        }

        .total-section {
            border-top: 2px solid #000;
            padding-top: 5px;
            margin-top: 5px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            font-size: 12px;
            margin: 3px 0;
        }

        .payment-box {
            margin: 8px 0;
            padding: 5px;
            border: 1px dashed #000;
            font-size: 9px;
            text-align: center;
        }

        .payment-box .method {
            font-weight: bold;
            margin-bottom: 3px;
        }

        .receipt-footer {
            text-align: center;
            margin-top: 10px;
            padding-top: 8px;
            border-top: 2px solid #000;
            font-size: 9px;
        }

        .receipt-footer .thank-you {
            font-weight: bold;
            margin-bottom: 3px;
        }

        .receipt-footer .note {
            margin: 2px 0;
        }

        .receipt-footer .timestamp {
            margin-top: 5px;
            font-size: 8px;
        }

        .print-actions {
            position: fixed;
            top: 10px;
            right: 10px;
            display: flex;
            gap: 5px;
            z-index: 1000;
        }

        .btn-print {
            background: #2C1810;
            color: #fff;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-print:hover {
            background: #4A3728;
        }

        .btn-back {
            background: #fff;
            color: #2C1810;
            border: 2px solid #2C1810;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .btn-back:hover {
            background: #f5f5f5;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
                display: block;
            }

            .print-actions {
                display: none !important;
            }

            .receipt {
                box-shadow: none;
                width: 100%;
                max-width: 320px;
                margin: 0 auto;
                padding: 5px 10px;
            }

            .receipt::after {
                display: none;
            }

            @page {
                margin: 0;
                size: auto;
            }
        }
    </style>
</head>

<body>
    <div class="print-actions no-print">
        <button onclick="window.print()" class="btn-print"><i class="fas fa-print"></i> Cetak</button>
        <a href="transactions.php" class="btn-back"><i class="fas fa-arrow-left"></i> Kembali</a>
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
                <div class="status taken">✓ SUDAH DIAMBIL</div>
                <div class="info"><?= date('d/m/Y H:i', strtotime($transaction['tanggal_diambil'] ?? 'now')) ?><br>Oleh:
                    <?= htmlspecialchars($transaction['diambil_oleh'] ?? '-') ?></div>
            <?php else: ?>
                <div class="status pending"> BELUM DIAMBIL</div>
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
            <?php endwhile; else: ?>
            <div style="text-align:center;padding:10px;color:#999;">Detail pesanan tidak tersedia</div>
        <?php endif; ?>
        <div class="total-section">
            <div class="total-row"><span>TOTAL</span><span>Rp
                    <?= number_format($transaction['total_harga'], 0, ',', '.') ?></span></div>
        </div>
        <div class="payment-box">
            <div class="method">METODE: <?= strtoupper($transaction['metode_pembayaran'] ?? 'COD') ?></div>
            <div>
                <?= ($transaction['metode_pembayaran'] ?? 'cod') == 'cod' ? 'Bayar tunai di outlet TEFA Coffee' : 'Pembayaran via QRIS' ?>
            </div>
        </div>
        <div class="receipt-footer">
            <div class="thank-you">*** TERIMA KASIH ***</div>
            <div class="note">Dukungan Anda membantu</div>
            <div class="note">mahasiswa Politeknik Jember</div>
            <div class="note" style="margin-top:5px;font-weight:bold;">Struk ini sah tanpa tanda tangan</div>
            <div class="timestamp"><?= date('d/m/Y H:i:s') ?></div>
        </div>
    </div>
    <script>
        <?php if (isset($_GET['print'])): ?>
            window.addEventListener('load', function () { setTimeout(() => window.print(), 300); });
        <?php endif; ?>
    </script>
</body>

</html>