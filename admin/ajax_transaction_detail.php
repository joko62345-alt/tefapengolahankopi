<?php
// ajax_transaction_detail.php
header('Content-Type: application/json');
require_once '../config/config.php';

$id = (int) ($_GET['id'] ?? 0);
$trx = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT t.*, u.nama_lengkap, u.telepon, u.alamat 
    FROM transactions t 
    JOIN users u ON t.user_id = u.id 
    WHERE t.id = $id
"));

if (!$trx) {
    echo json_encode(['html' => '<div class="modal-empty"><i class="fas fa-exclamation-circle"></i><p>Transaksi tidak ditemukan</p></div>']);
    exit;
}

$items = [];
$q = mysqli_query($conn, "
    SELECT td.*, p.nama_produk, p.harga 
    FROM transaction_details td 
    JOIN products p ON td.product_id = p.id 
    WHERE td.transaction_id = $id
");
while ($r = mysqli_fetch_assoc($q)) $items[] = $r;

ob_start();
?>

<!-- Customer Info -->
<div class="customer-section">
    <div class="title"><i class="fas fa-user"></i> Customer</div>
    <div class="row-item"><span class="key">Nama</span><span class="val"><?= htmlspecialchars($trx['nama_lengkap']) ?></span></div>
    <div class="row-item"><span class="key">Telepon</span><span class="val"><?= htmlspecialchars($trx['telepon']) ?></span></div>
    <div class="row-item"><span class="key">Alamat</span><span class="val"><?= htmlspecialchars($trx['alamat']) ?></span></div>
</div>

<!-- Info Cards Grid -->
<div class="info-row">
    <div class="info-card">
        <div class="label">Kode</div>
        <div class="value"><?= htmlspecialchars($trx['kode_transaksi']) ?></div>
    </div>
    <div class="info-card">
        <div class="label">Tanggal</div>
        <div class="value"><?= date('d M Y, H:i', strtotime($trx['tanggal_transaksi'])) ?></div>
    </div>
    <div class="info-card">
        <div class="label">Status Bayar</div>
        <div class="value">
            <span class="badge-flat <?= in_array($trx['status_pembayaran'],['lunas','dikonfirmasi'])?'lunas':'pending' ?>">
                <?= ucfirst($trx['status_pembayaran']) ?>
            </span>
        </div>
    </div>
    <div class="info-card">
        <div class="label">Status Ambil</div>
        <div class="value">
            <span class="badge-flat <?= $trx['status_pengambilan']=='sudah_diambil'?'diambil':'belum' ?>">
                <?= ucfirst(str_replace('_',' ',$trx['status_pengambilan'])) ?>
            </span>
        </div>
        <?php if($trx['status_pengambilan']=='sudah_diambil'): ?>
            <div class="value small" style="margin-top:4px">
                <?= htmlspecialchars($trx['diambil_oleh']) ?> • <?= date('d/m/Y H:i',strtotime($trx['tanggal_diambil'])) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Items -->
<div style="margin-bottom:1rem">
    <div style="font-weight:600;color:#2C1810;margin-bottom:0.6rem;font-size:0.9rem">Item</div>
    <table class="items-table">
        <thead>
            <tr>
                <th>Produk</th>
                <th style="width:70px;text-align:center">Qty</th>
                <th style="width:100px;text-align:right">Harga</th>
                <th style="width:110px;text-align:right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($items as $it): ?>
            <tr>
                <td class="col-name" data-label="Produk"><?= htmlspecialchars($it['nama_produk']) ?></td>
                <td class="col-qty" data-label="Qty"><?= $it['quantity'] ?></td>
                <td class="col-price" data-label="Harga">Rp <?= number_format($it['harga'],0,',','.') ?></td>
                <td class="col-price" data-label="Subtotal">Rp <?= number_format($it['quantity']*$it['harga'],0,',','.') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Summary -->
<div class="summary-box">
    <div class="summary-item sub"><span>Subtotal</span><span>Rp <?= number_format($trx['total_harga'],0,',','.') ?></span></div>
    <div class="summary-item total"><span>TOTAL</span><span class="amount">Rp <?= number_format($trx['total_harga'],0,',','.') ?></span></div>
</div>

<!-- Actions -->
<div class="modal-actions">
    <button class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
    <?php if(isset($trx['metode_pembayaran']) && $trx['metode_pembayaran']=='cod' && $trx['status_pembayaran']=='pending'): ?>
    <a href="?confirm_payment=<?= $trx['id'] ?>" class="btn btn-success" onclick="return confirm('Konfirmasi pembayaran COD sudah diterima?')">Konfirmasi</a>
    <?php endif; ?>
    <?php if($trx['status_pengambilan']=='belum_diambil' && in_array($trx['status_pembayaran'],['lunas','dikonfirmasi'])): ?>
    <a href="?confirm_pickup=<?= $trx['id'] ?>" class="btn btn-primary" onclick="return confirm('Konfirmasi produk sudah diambil customer?')">
        <i class="fas fa-box-open"></i> Sudah Diambil
    </a>
    <?php endif; ?>
</div>

<?php
echo json_encode(['html' => ob_get_clean()]);
?>