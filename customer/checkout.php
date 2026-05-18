<?php
require_once '../config/config.php';
checkRole('customer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('customer/dashboard.php');
}

$user_id = $_SESSION['user_id'];
$cart_data = json_decode($_POST['cart_data'], true);

if (empty($cart_data)) {
    redirect('customer/dashboard.php');
}

// Generate transaction code
$kode_transaksi = 'TRX-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

// Calculate total
$total_harga = 0;
foreach ($cart_data as $item) {
    $total_harga += $item['price'] * $item['qty'];
}

// Insert transaction - SEMUA transaksi mulai dengan status 'pending'
$query = "INSERT INTO transactions (user_id, kode_transaksi, total_harga, status_pembayaran, status_pengambilan) 
          VALUES ('$user_id', '$kode_transaksi', '$total_harga', 'pending', 'belum_diambil')";

if (mysqli_query($conn, $query)) {
    $transaction_id = mysqli_insert_id($conn);

    // Insert transaction details
    foreach ($cart_data as $item) {
        mysqli_query($conn, "
            INSERT INTO transaction_details (transaction_id, product_id, quantity, harga_satuan, subtotal) 
            VALUES ('$transaction_id', {$item['id']}, {$item['qty']}, {$item['price']}, " . ($item['price'] * $item['qty']) . ")
        ");
    }

    // Clear cart
    unset($_SESSION['cart']);

    // Redirect ke halaman konfirmasi tunggal
    redirect("customer/cod_confirm.php?kode=$kode_transaksi");
    
} else {
    die("Error: " . mysqli_error($conn));
}
?>