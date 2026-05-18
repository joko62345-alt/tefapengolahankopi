<?php
require_once '../config/config.php';
checkRole('customer');
$user_id = $_SESSION['user_id'];
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Fetch user data untuk profil dari database
$user_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id='$user_id'"));

// Handle add to cart via GET parameter
if (isset($_GET['product'])) {
    $pid = (int) $_GET['product'];
    $product = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM products WHERE id='$pid' AND stok > 0"));
    if ($product) {
        $existing = array_search($pid, array_column($_SESSION['cart'], 'id'));
        if ($existing !== false) {
            if ($_SESSION['cart'][$existing]['qty'] < $product['stok']) {
                $_SESSION['cart'][$existing]['qty']++;
            }
        } else {
            $_SESSION['cart'][] = [
                'id' => $product['id'],
                'name' => $product['nama_produk'],
                'price' => $product['harga'],
                'qty' => 1
            ];
        }
    }
    redirect('customer/dashboard.php');
}

$my_transactions = mysqli_query($conn, "SELECT * FROM transactions WHERE user_id='$user_id' ORDER BY tanggal_transaksi DESC LIMIT 10");
$products = mysqli_query($conn, "SELECT * FROM products WHERE stok > 0 ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - TEFA Coffee</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
    <nav class="navbar navbar-expand-md navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <img src="../assets/images/logopolije.png" alt="Polije" class="brand-logo"
                    onerror="this.style.display='none'">
                <img src="../assets/images/sip.png" alt="TEFA" class="brand-logo" onerror="this.style.display='none'">
                <span class="brand-text">TEFA COFFEE</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a href="../index.php" class="nav-btn" title="Beranda">
                            <i class="fas fa-home"></i>
                            <span>Beranda</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <button class="nav-btn" onclick="showProfileModal()" title="Profil">
                            <i class="fas fa-user"></i>
                            <span><?= htmlspecialchars($_SESSION['nama']) ?></span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <a href="../logout.php" class="nav-btn" title="Logout">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container main-content" style="flex: 1;">
        <div class="row g-4">
            <!-- Products Section -->
            <div class="col-lg-8">
                <h2 class="section-title mb-2">Produk Kami</h2>
                <p class="section-subtitle text-muted mb-4">Pilihan kopi premium dengan kualitas terbaik, siap menemani
                    aktivitas harian Anda</p>
                <?php if (mysqli_num_rows($products) == 0): ?>
                    <div class="alert alert-info text-center py-5">
                        <h5>Produk Sedang Dalam Persiapan</h5>
                        <p class="mb-0">Silakan kunjungi kembali nanti.</p>
                    </div>
                <?php endif; ?>
                <div class="row g-4">
                    <?php while ($product = mysqli_fetch_assoc($products)): ?>
                        <div class="col-md-6">
                            <div class="product-card">
                                <div class="product-image">
                                    <?php
                                    $imgPath = '../assets/images/products/' . $product['gambar'];
                                    if ($product['gambar'] && file_exists($imgPath)): ?>
                                        <img src="<?= $imgPath ?>" alt="<?= htmlspecialchars($product['nama_produk']) ?>">
                                    <?php else: ?>
                                        <i class="fas fa-coffee"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="product-body">
                                    <h3 class="product-title"><?= htmlspecialchars($product['nama_produk']) ?></h3>
                                    <p class="product-desc"><?= htmlspecialchars($product['deskripsi']) ?></p>
                                    <div class="product-meta">
                                        <span class="product-price">Rp
                                            <?= number_format($product['harga'], 0, ',', '.') ?></span>
                                        <small class="text-muted"><i class="fas fa-box me-1"></i><?= $product['stok'] ?>
                                            pcs</small>
                                    </div>
                                    <button class="btn-coffee"
                                        onclick="addToCart(<?= $product['id'] ?>, '<?= addslashes($product['nama_produk']) ?>', <?= $product['harga'] ?>, <?= $product['stok'] ?>)">
                                        <i class="fas fa-cart-plus"></i> masukkan keranjang
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Cart & History Sidebar - STICKY -->
            <div class="col-lg-4">
                <div class="sidebar-sticky">
                    <!-- Cart Widget -->
                    <div class="cart-widget">
                        <div class="cart-head">
                            <span><i class="fas fa-shopping-cart me-2"></i>Keranjang</span>
                            <span class="cart-count" id="cartCount">0 item</span>
                        </div>
                        <div class="cart-body">
                            <div id="cartItems">
                                <div class="cart-empty">
                                    <i class="fas fa-basket-shopping"></i>
                                    <p class="mb-1 fw-medium">Keranjang masih kosong</p>
                                    <small class="d-block">Pilih produk untuk mulai belanja</small>
                                </div>
                            </div>
                            <div id="cartSummary" style="display: none">
                                <div class="cart-footer">
                                    <div class="cart-total">
                                        <span>Subtotal</span>
                                        <span id="cartSubtotal">Rp 0</span>
                                    </div>
                                    <button class="btn-checkout" onclick="showCheckoutModal()">
                                        <i class="fas fa-lock"></i> Checkout
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- History Widget -->
                    <div class="history-widget">
                        <div class="history-head">
                            <i class="fas fa-clock-rotate-left me-2"></i> Riwayat Transaksi
                        </div>
                        <div class="history-list">
                            <?php if (mysqli_num_rows($my_transactions) > 0): ?>
                                <?php while ($trans = mysqli_fetch_assoc($my_transactions)): ?>
                                    <div class="history-item">
                                        <div class="history-code"><?= htmlspecialchars($trans['kode_transaksi']) ?></div>
                                        <div class="history-row">
                                            <span class="history-amount">Rp
                                                <?= number_format($trans['total_harga'], 0, ',', '.') ?></span>
                                            <span class="status-badge status-<?=
                                                $trans['status_pembayaran'] == 'lunas' ? 'lunas' :
                                                ($trans['status_pembayaran'] == 'dikonfirmasi' ? 'dikonfirmasi' : 'pending')
                                                ?>">
                                                <?= ucfirst($trans['status_pembayaran']) ?>
                                            </span>
                                        </div>
                                        <button class="btn-receipt" onclick="viewReceipt('<?= $trans['kode_transaksi'] ?>')">
                                            <i class="fas fa-receipt me-1"></i> Lihat Struk
                                        </button>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-receipt fa-2x mb-2 d-block opacity-25"></i>
                                    <p class="small mb-0">Belum ada transaksi</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer dengan Tombol Profil -->
    <footer class="footer-custom">
        <div class="mt-3 text-muted small">
            &copy; 2026 TEFA Coffee - Politeknik Negeri Jember
        </div>
    </footer>

<!-- Checkout Modal - Premium E-Commerce -->
<div class="modal fade" id="checkoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            
            <!-- Header -->
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-shopping-bag"></i>
                    Konfirmasi Pesanan
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <!-- Body -->
            <div class="modal-body">
                <div class="checkout-container">
                    <form action="checkout.php" method="POST" id="checkoutForm">
                        
                        <!-- Order Summary -->
                        <div class="order-summary">
                            <h6 class="order-summary-title"> Ringkasan Pesanan
                            </h6>
                            <div class="order-items" id="checkoutItems">
                                <!-- Items injected by JavaScript -->
                            </div>
                        </div>

                        <!-- Total -->
                        <div class="order-total-section">
                            <div class="total-row sub">
                                <span>Subtotal</span>
                                <span id="subtotalDisplay">Rp 0</span>
                            </div>
                            <div class="total-row sub">
                                <span>Ongkir</span>
                                <span>-</span>
                            </div>
                            <div class="total-row divider grand">
                                <span>Total</span>
                                <span class="amount" id="modalTotal">Rp 0</span>
                            </div>
                        </div>


                        <!-- Info Note -->
                        <div class="info-note">
                            <div class="info-note-title">Cara Ambil Pesanan
                            </div>
                            <ul class="info-note-list">
                                <li>Simpan kode transaksi atau struk yang akan dikirim</li>
                                <li>Datang ke outlet TEFA Coffee</li>
                                <li>Tunjukkan kode ke kasir & lakukan pembayaran</li>
                                <li>Pesanan siap diambil</li>
                            </ul>
                        </div>

                        <!-- WhatsApp Note (Optional) -->
                        <div class="whatsapp-note">
                            <div class=
                              >
                            </div>
                            <div class="whatsapp-note-content">
                                <h6>Butuh Bantuan?</h6>
                                <p>Hubungi admin untuk pertanyaan seputar pesanan</p>
                                <a href="https://wa.me/6281234567890" class="btn-whatsapp-mini" target="_blank">
                                    <i class="fab fa-whatsapp"></i> Chat Admin
                                </a>
                            </div>
                        </div>

                        <!-- Hidden Inputs -->
                        <input type="hidden" name="payment_method" id="payment_method" value="cod">
                        <input type="hidden" name="cart_data" id="cartData">
                        
                    </form>
                </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer">
                <button type="submit" form="checkoutForm" class="btn-premium primary" id="btnBayar">Pesan Sekarang
                </button>
            </div>
            
        </div>
    </div>
</div>
    <!-- Profile Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <!-- Header -->
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-circle"></i>
                        Profil Pengguna
                    </h5>
                </div>

                <!-- Body -->
                <div class="modal-body">
                    <div class="profile-info-list">
                        <!-- Nama Lengkap -->
                        <div class="profile-info-item name-item">
                            <div class="profile-info-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="profile-info-content">
                                <span class="profile-info-label">Nama Lengkap</span>
                                <div class="profile-info-value">
                                    <?= htmlspecialchars($user_data['nama_lengkap'] ?? $_SESSION['nama']) ?>
                                </div>
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="profile-info-item">
                            <div class="profile-info-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="profile-info-content">
                                <span class="profile-info-label">Email</span>
                                <div class="profile-info-value"><?= htmlspecialchars($user_data['email'] ?? ' - ') ?>
                                </div>
                            </div>
                        </div>

                        <!-- Telepon -->
                        <div class="profile-info-item">
                            <div class="profile-info-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div class="profile-info-content">
                                <span class="profile-info-label">Telepon</span>
                                <div class="profile-info-value"><?= htmlspecialchars($user_data['telepon'] ?? ' - ') ?>
                                </div>
                            </div>
                        </div>

                        <!-- Alamat -->
                        <div class="profile-info-item">
                            <div class="profile-info-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="profile-info-content">
                                <span class="profile-info-label">Alamat</span>
                                <div class="profile-info-value" style="white-space: normal;">
                                    <?= htmlspecialchars($user_data['alamat'] ?? ' - ') ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="modal-footer">
                    <button type="button" class="btn-modal-close" data-bs-dismiss="modal"> Tutup
                    </button>
                    <a href="edit_profile.php" class="btn-modal-edit">Edit Profil
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- RECEIPT MODAL - SAMA PERSIS DENGAN TRANSACTIONS.PHP -->
    <div class="modal fade" id="receiptModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content receipt-modal-content">
                <div class="modal-header receipt-modal-header">

                </div>
                <div class="modal-body receipt-modal-body p-0">
                    <!-- Header Struk -->
                    <div
                        style="text-align: center; margin-bottom: 15px; border-bottom: 1px dashed #000; padding-bottom: 15px;">
                        <img src="../assets/images/logopolije.png" alt="Polije"
                            style="width: 60px; height: 60px; margin-bottom: 10px;" onerror="this.style.display='none'">
                        <div style="font-weight: bold; font-size: 14px; margin: 5px 0;">TEFA COFFEE</div>
                        <div style="font-size: 9px; line-height: 1.4;">
                            Politeknik Negeri Jember<br>
                            Jl. Mastrip, Kotak Pos 164<br>
                            Jember 68101, Jawa Timur<br>
                            <i class="fas fa-phone"></i> 0812-3456-7890
                        </div>
                    </div>

                    <!-- Status Badge -->
                    <div style="border: 1px solid #000; padding: 5px; margin-bottom: 15px; text-align: center; font-weight: bold; font-size: 10px; text-transform: uppercase;"
                        id="receiptStatus">
                        PENDING
                    </div>

                    <!-- Info Customer -->
                    <div style="font-size: 10px; margin-bottom: 15px; line-height: 1.6;">
                        <div style="display: flex; justify-content: space-between;">
                            <span style="font-weight: bold;">No:</span>
                            <span style="font-family: monospace;" id="receiptKode"></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="font-weight: bold;">Tgl:</span>
                            <span id="receiptTanggal"></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="font-weight: bold;">Nama:</span>
                            <span id="receiptNama"></span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="font-weight: bold;">Telp:</span>
                            <span id="receiptTelepon"></span>
                        </div>
                    </div>

                    <!-- Status Pengambilan -->
                    <div style="border: 1px solid #000; padding: 8px; margin-bottom: 15px; font-size: 9px;">
                        <div style="text-align: center; font-weight: bold; margin-bottom: 5px;"> PENGAMBILAN PRODUK
                        </div>
                        <div id="receiptPickupStatus" style="text-align: center; font-weight: bold;"></div>
                        <div style="text-align: center; margin-top: 3px; font-size: 8px;" id="receiptPickupInfo"></div>
                    </div>

                    <!-- Items -->
                    <div
                        style="border-top: 2px solid #000; border-bottom: 1px dashed #000; padding: 5px 0; text-align: center; font-weight: bold; font-size: 10px; margin-bottom: 10px;">
                        ITEM PESANAN
                    </div>
                    <div id="receiptItems" style="font-size: 9px; margin-bottom: 15px; line-height: 1.8;"></div>

                    <!-- Total -->
                    <div style="border-top: 2px solid #000; padding-top: 8px; margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 12px;">
                            <span>TOTAL</span>
                            <span id="receiptTotal"></span>
                        </div>
                    </div>
                    <!-- Footer Struk -->
                    <div
                        style="text-align: center; border-top: 2px solid #000; padding-top: 10px; font-size: 9px; line-height: 1.6;">
                        <div style="font-weight: bold; margin-bottom: 5px;">*** TERIMA KASIH ***</div>
                        <div>Dukungan Anda membantu</div>
                        <div>mahasiswa Politeknik Jember</div>
                        <div style="font-weight: bold; margin-top: 5px;">Struk ini sah tanpa tanda tangan</div>
                        
                    </div>
                </div>

                <!-- Footer Modal -->
                <div class="modal-footer receipt-modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"
                        style="margin-right: 5px;">
                        Tutup
                    </button>
                    <button type="button" class="btn btn-sm" onclick="printModalReceipt()"
                        style="background: #2C1810; color: #fff; border: none;">
                        <i class="fas fa-print"></i> Cetak
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      

// Initialize cart from PHP session
let cart = <?= json_encode($_SESSION['cart'] ?? []) ?>;

// ✅ Professional Rupiah Formatter (Monospace-friendly)
function formatRupiahPro(amount) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount).replace('Rp', 'Rp ').trim();
}

//  Add to Cart
function addToCart(id, name, price, stock) {
    const existing = cart.find(i => i.id === id);
    
    if (existing) {
        if (existing.qty < stock) {
            existing.qty++;
            showNotification('success', `${name} ditambahkan ke keranjang`);
        } else {
            showNotification('error', 'Stok tidak mencukupi');
            return;
        }
    } else {
        if (stock > 0) {
            cart.push({ id, name, price, qty: 1 });
            showNotification('success', `${name} ditambahkan ke keranjang`);
        } else {
            showNotification('error', 'Produk habis');
            return;
        }
    }
    
    updateCart();
    saveCart();
}

//  Remove from Cart
function removeFromCart(index) {
    if (cart[index]) {
        cart.splice(index, 1);
        updateCart();
        saveCart();
        showNotification('success', 'Item dihapus dari keranjang');
    }
}

//  Update Quantity
function updateQty(index, change) {
    if (!cart[index]) return;
    
    const newQty = cart[index].qty + change;
    
    if (newQty > 0) {
        cart[index].qty = newQty;
        updateCart();
        saveCart();
    } else {
        removeFromCart(index);
    }
}

//  Update Cart Display (Sidebar Widget)
function updateCart() {
    const cartItemsEl = document.getElementById('cartItems');
    const cartSummaryEl = document.getElementById('cartSummary');
    const cartCountEl = document.getElementById('cartCount');
    
    // Filter invalid items
    cart = cart.filter(item => item && item.id && item.price);
    
    if (!cart || cart.length === 0) {
        if (cartItemsEl) {
            cartItemsEl.innerHTML = `
                <div class="cart-empty">
                    <i class="fas fa-basket-shopping"></i>
                    <p class="mb-1 fw-medium">Keranjang masih kosong</p>
                    <small class="d-block">Pilih produk untuk mulai belanja</small>
                </div>
            `;
        }
        if (cartSummaryEl) cartSummaryEl.style.display = 'none';
        if (cartCountEl) cartCountEl.textContent = '0 item';
        return;
    }
    
    let html = '';
    let total = 0;
    let count = 0;
    
    cart.forEach((item, i) => {
        const subtotal = item.price * item.qty;
        total += subtotal;
        count += item.qty;
        
        html += `
            <div class="cart-item">
                <div class="cart-item-info">
                    <h6>${item.name}</h6>
                    <div class="cart-item-meta">${formatRupiahPro(item.price)} × ${item.qty}</div>
                    <div class="qty-control">
                        <button class="qty-btn" onclick="updateQty(${i}, -1)">−</button>
                        <span class="fw-medium">${item.qty}</span>
                        <button class="btn-remove" onclick="removeFromCart(${i})" title="Hapus">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
                <div class="text-end">
                    <div class="cart-item-price">${formatRupiahPro(subtotal)}</div>
                </div>
            </div>
        `;
    });
    
    if (cartItemsEl) cartItemsEl.innerHTML = html;
    if (document.getElementById('cartSubtotal')) {
        document.getElementById('cartSubtotal').textContent = formatRupiahPro(total);
    }
    if (document.getElementById('modalTotal')) {
        document.getElementById('modalTotal').textContent = formatRupiahPro(total);
    }
    if (cartCountEl) {
        cartCountEl.textContent = count + ' item' + (count !== 1 ? 's' : '');
    }
    if (cartSummaryEl) cartSummaryEl.style.display = 'block';
}

//  Save Cart to Session (AJAX)
function saveCart() {
    fetch('ajax_cart.php', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'cart=' + encodeURIComponent(JSON.stringify(cart))
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Cart saved successfully');
        }
    })
    .catch(error => {
        console.log('Cart sync error:', error);
    });
}

//  Populate Checkout Modal Items (HANYA update dynamic content)
function populateCheckoutItems() {
    const container = document.getElementById('checkoutItems');
    if (!container || !cart || cart.length === 0) {
        if (container) {
            container.innerHTML = '<p class="text-muted small text-center py-3">Keranjang kosong</p>';
        }
        return;
    }
    
    let html = '';
    let subtotal = 0;
    
    cart.forEach(item => {
        const itemTotal = item.price * item.qty;
        subtotal += itemTotal;
        
        html += `
            <div class="order-item">
                <div class=">
                  
                </div>
                <div class="order-item-details">
                    <div class="order-item-name">${item.name}</div>
                    <div class="order-item-meta">
                        <span class="order-item-qty">${item.qty}×</span>
                        <span>${formatRupiahPro(item.price)}</span>
                    </div>
                </div>
                <div class="order-item-price">${formatRupiahPro(itemTotal)}</div>
            </div>
        `;
    });
    
    if (container) container.innerHTML = html;
    
    //  HANYA update angka/harga (dynamic), JANGAN ubah teks statis
    if (document.getElementById('subtotalDisplay')) {
        document.getElementById('subtotalDisplay').textContent = formatRupiahPro(subtotal);
    }
    if (document.getElementById('modalTotal')) {
        document.getElementById('modalTotal').textContent = formatRupiahPro(subtotal);
    }
    if (document.getElementById('cartData')) {
        document.getElementById('cartData').value = JSON.stringify(cart);
    }
    
}

//  Select Payment Method
function selectPayment(method) {
    if (!method) return;
    
    // Update hidden input
    const paymentInput = document.getElementById('payment_method');
    if (paymentInput) {
        paymentInput.value = method;
    }
    
    // Update visual selection
    document.querySelectorAll('.payment-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    if (event && event.currentTarget) {
        event.currentTarget.classList.add('selected');
    }
    
    // Enable submit button
    const submitBtn = document.getElementById('btnBayar');
    if (submitBtn) {
        submitBtn.disabled = false;
    }
}

//  Show Checkout Modal - TIDAK override text tombol
function showCheckoutModal() {
    if (!cart || cart.length === 0) {
        showNotification('error', 'Keranjang belanja masih kosong');
        return;
    }
    
    // Populate items (hanya dynamic content)
    populateCheckoutItems();
    
    //  Reset button state TANPA ubah text
    const submitBtn = document.getElementById('btnBayar');
    if (submitBtn) {
        submitBtn.disabled = false;
        
    }
    
    // Reset payment selection
    const paymentInput = document.getElementById('payment_method');
    if (paymentInput) {
        paymentInput.value = 'cod';
    }
    
    const codCard = document.getElementById('card-cod');
    if (codCard) {
        codCard.classList.add('selected');
    }
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('checkoutModal'));
    modal.show();
}

//  Show Profile Modal
function showProfileModal() {
    const modal = new bootstrap.Modal(document.getElementById('profileModal'));
    modal.show();
}

//  View Receipt (Same as transactions.php)
function viewReceipt(kode) {
    fetch(`receipt.php?kode=${encodeURIComponent(kode)}&format=json`, {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            showNotification('error', data.error);
            return;
        }
        populateReceiptModal(data);
        const modal = new bootstrap.Modal(document.getElementById('receiptModal'));
        modal.show();
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('error', 'Gagal memuat struk');
    });
}

//  Populate Receipt Modal (HANYA dynamic content)
function populateReceiptModal(data) {
    //  Update hanya data dinamis dari database
    if (document.getElementById('receiptKode')) {
        document.getElementById('receiptKode').textContent = data.kode_transaksi;
    }
    if (document.getElementById('receiptTanggal')) {
        document.getElementById('receiptTanggal').textContent = formatDateIndonesia(data.tanggal_transaksi);
    }
    if (document.getElementById('receiptNama')) {
        document.getElementById('receiptNama').textContent = data.nama_lengkap;
    }
    if (document.getElementById('receiptTelepon')) {
        document.getElementById('receiptTelepon').textContent = data.telepon;
    }

    // Status pembayaran
    const statusEl = document.getElementById('receiptStatus');
    if (statusEl) {
        statusEl.textContent = data.status_pembayaran.toUpperCase();
        statusEl.style.background = data.status_pembayaran === 'lunas' ? '#000' : '#fff';
        statusEl.style.color = data.status_pembayaran === 'lunas' ? '#fff' : '#000';
    }

    // Status pengambilan
    const pickupStatusEl = document.getElementById('receiptPickupStatus');
    const pickupInfoEl = document.getElementById('receiptPickupInfo');
    if (pickupStatusEl && pickupInfoEl) {
        if (data.status_pengambilan === 'sudah_diambil') {
            pickupStatusEl.innerHTML = ' SUDAH DIAMBIL';
            pickupInfoEl.innerHTML = formatDateIndonesia(data.tanggal_diambil) + '<br>Oleh: ' + data.diambil_oleh;
        } else {
            pickupStatusEl.innerHTML = 'BELUM DIAMBIL';
            pickupInfoEl.innerHTML = 'Tunjukkan struk ini ke admin saat ambil';
        }
    }

    // Items
    let itemsHtml = '';
    if (data.items && Array.isArray(data.items)) {
        data.items.forEach(item => {
            const qty = item.qty || item.quantity || 1;
            const subtotal = item.subtotal || (item.harga * qty);
            itemsHtml += `
                <div class="receipt-item">
                    <span class="item-name">${item.nama_produk || item.nama}</span>
                    <span class="item-qty">${qty}x</span>
                    <span class="item-price">Rp ${formatRupiahPro(subtotal).replace('Rp ', '')}</span>
                </div>
            `;
        });
    }
    if (document.getElementById('receiptItems')) {
        document.getElementById('receiptItems').innerHTML = itemsHtml;
    }

    // Total
    if (document.getElementById('receiptTotal')) {
        document.getElementById('receiptTotal').textContent = 'Rp ' + formatRupiahPro(data.total_harga).replace('Rp ', '');
    }

    // Payment method (dynamic based on data)
    const paymentMethod = data.metode_pembayaran || data.payment_method || 'cod';
    const paymentEl = document.getElementById('receiptPayment');
    const paymentDescEl = document.getElementById('receiptPaymentDesc');
    
    if (paymentEl && paymentDescEl) {
        if (paymentMethod === 'qris') {
            paymentEl.textContent = 'QRIS (Digital)';
            paymentDescEl.textContent = 'Pembayaran via QR Code';
        } else {
            paymentEl.textContent = 'COD (Cash)';
            paymentDescEl.textContent = 'Bayar tunai di outlet TEFA Coffee';
        }
    }

    // Timestamp (dynamic)
    if (document.getElementById('receiptTimestamp')) {
        document.getElementById('receiptTimestamp').textContent = 'Dicetak: ' + new Date().toLocaleString('id-ID');
    }
    
}

//  Format Date Indonesia
function formatDateIndonesia(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${day}/${month}/${year} ${hours}:${minutes}`;
}

//  Print Receipt
function printModalReceipt() {
    const printWindow = window.open('', '_blank', 'width=400,height=700');
    const modalContent = document.querySelector('#receiptModal .modal-content');
    
    if (!modalContent) {
        showNotification('error', 'Gagal memuat struk untuk dicetak');
        return;
    }
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Cetak Struk</title>
            <style>
                body { 
                    font-family: 'Courier New', Courier, monospace; 
                    padding: 20px; 
                    background: white; 
                    margin: 0; 
                }
                .modal-content { 
                    box-shadow: none !important; 
                    border: none !important; 
                    max-width: 100%; 
                }
                .modal-footer, .btn-close { 
                    display: none !important; 
                }
                @media print { 
                    body { padding: 0; } 
                    .no-print { display: none !important; } 
                }
            </style>
        </head>
        <body>${modalContent.outerHTML}</body>
        </html>
    `);
    printWindow.document.close();
    setTimeout(() => printWindow.print(), 500);
}

//  Show Notification Toast
function showNotification(type, message) {
    // Remove existing toast
    const existing = document.querySelector('.toast-notification');
    if (existing) existing.remove();
    
    // Create new toast
    const toast = document.createElement('div');
    toast.className = `toast-notification alert alert-${type === 'success' ? 'success' : 'danger'} toast-${type}`;
    toast.innerHTML = `
        <div class="d-flex align-items-center p-3">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} me-3" style="font-size:1.2rem"></i>
            <span class="flex-grow-1 fw-medium">${message}</span>
            <button class="btn-close btn-close-white" onclick="this.closest('.toast-notification').remove()"></button>
        </div>
    `;
    
    document.body.appendChild(toast);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        if (toast.parentElement) {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(120px)';
            setTimeout(() => toast.remove(), 300);
        }
    }, 3000);
}

//  Prevent Double Submit on Checkout Form
function initCheckoutForm() {
    const checkoutForm = document.getElementById('checkoutForm');
    if (!checkoutForm) return;
    
    checkoutForm.addEventListener('submit', function(e) {
        const submitBtn = document.getElementById('btnBayar');
        if (submitBtn) {
            submitBtn.disabled = true;
            //  Hanya ubah saat loading, text asli tetap dari HTML
            submitBtn.dataset.originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
        }
    });
}

//  Initialize on DOM Ready
document.addEventListener('DOMContentLoaded', function() {
    console.log(' JavaScript loaded - Cart:', cart);
    updateCart();
    initCheckoutForm();
    
    // Close sidebar on mobile when clicking link
    document.querySelectorAll('.sidebar-menu a, .navbar-nav a').forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebarOverlay');
                if (sidebar) sidebar.classList.remove('active');
                if (overlay) overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });
});
    </script>
</body>

</html>