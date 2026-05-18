<?php
require_once 'config/config.php';

// Ambil produk
$products = [];
if ($conn) {
    $result = mysqli_query($conn, "SELECT * FROM products WHERE stok > 0 ORDER BY created_at DESC LIMIT 6");
    if ($result) {
        while($row = mysqli_fetch_assoc($result)) {
            $products[] = $row;
        }
    }
}

// Helper function untuk cek login customer
function isCustomerLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] == 'customer';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TEFA Coffee</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/customer.css">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-premium fixed-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <div class="logo-wrapper me-2">
                <img src="assets/images/logopolije.png" alt="Polije" class="brand-logo logo-polije" 
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                <div class="logo-fallback" style="display: none;"><i class="fas fa-university"></i></div>
            </div>
            <div class="logo-wrapper me-2">
                <img src="assets/images/sip.png" alt="TEFA Coffee" class="brand-logo logo-tefa"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                <div class="logo-fallback" style="display: none;"><i class="fas fa-coffee"></i></div>
            </div>
            <span class="brand-text d-none d-md-inline">TEFA COFFEE</span>
        </a>
        <button class="navbar-toggler border-0" type="button" 
                data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- Navbar Content -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item"><a class="nav-link" href="#home">Beranda</a></li>
                <li class="nav-item"><a class="nav-link" href="#products">Produk</a></li>
                <li class="nav-item"><a class="nav-link" href="#features">Keunggulan</a></li>
                <li class="nav-item"><a class="nav-link" href="#about">Tentang</a></li>
                
                <?php if(isCustomerLoggedIn()): ?>
                    <!-- User sudah login -->
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-dashboard btn-sm" href="customer/dashboard.php">Dashboard
                        </a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <a class="nav-link" href="logout.php" title="Logout">Logout
                        </a>
                    </li>
                <?php else: ?>
                    <!-- User belum login -->
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-login btn-sm" href="customer/login.php">Login
                        </a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-register btn-sm" href="customer/register.php">Register
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- HERO SECTION -->
<section id="home" class="hero-section d-flex align-items-center" 
         style="background: url('assets/images/kopi.jpg') center/cover no-repeat fixed; min-height: 100vh; position: relative;">
    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1;"></div>
    <div class="container position-relative" style="z-index: 2;">
        <div class="row">
            <div class="col-lg-7 fade-in-up">
                <h1 class="hero-title" style="color: white !important; text-shadow: 2px 4px 8px rgba(0,0,0,0.5);">
                    Setiap Cangkir,<br><strong>Diracik Sempurna</strong>
                </h1>
                <p class="hero-subtitle" style="color: rgba(255,255,255,0.95) !important;">
                    Nikmati kopi istimewa dalam suasana yang nyaman dan mengundang.
                </p>
                <div class="d-flex gap-3 flex-wrap">
                    <a href="#products" class="btn btn-gold btn-lg">
                        <i class="fas fa-shopping-bag me-2"></i>Lihat Produk
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- PRODUCTS SECTION -->
<section id="products" class="section-padding">
    <div class="container">
        <h2 class="section-title">Produk Kami</h2>
        <p class="section-subtitle">Pilihan kopi premium dengan kualitas terbaik, siap menemani aktivitas harian Anda</p>
        
        <?php if(empty($products)): ?>
        <div class="alert alert-info text-center py-5">
            <h5>Produk Sedang Dalam Persiapan</h5>
            <p class="mb-0">Silakan kunjungi kembali nanti untuk melihat koleksi kopi premium kami.</p>
        </div>
        <?php else: ?>
        <div class="row g-4">
            <?php foreach($products as $product): ?>
            <div class="col-md-6 col-lg-4">
                <div class="product-card">
                    <div class="product-image">
                        <?php if(!empty($product['gambar']) && file_exists('assets/images/products/' . $product['gambar'])): ?>
                            <img src="assets/images/products/<?= htmlspecialchars($product['gambar']) ?>" 
                                 alt="<?= htmlspecialchars($product['nama_produk']) ?>"
                                 style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <i class="fas fa-coffee" style="font-size: 5rem; color: var(--coffee-dark); opacity: 0.7;"></i>
                        <?php endif; ?>
                    </div>
                    <div class="product-body">
                        <h3 class="product-title"><?= htmlspecialchars($product['nama_produk']) ?></h3>
                        <p class="product-desc"><?= htmlspecialchars($product['deskripsi']) ?></p>
                        <div class="product-meta">
                            <span class="product-price">Rp <?= number_format($product['harga'], 0, ',', '.') ?></span>
                            <small class="text-muted"><i class="fas fa-box me-1"></i><?= (int)$product['stok'] ?> pcs</small>
                        </div>
                        
                        <?php if(isCustomerLoggedIn()): ?>
                            <a href="customer/dashboard.php" class="btn btn-coffee w-100">Beli Sekarang
                            </a>
                        <?php else: ?>
                            <a href="customer/login.php" class="btn btn-coffee w-100">Login untuk Beli
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- FEATURES SECTION -->
<section id="features" class="section-padding features-section">
    <div class="container">
        <h2 class="section-title">Keunggulan TEFA Coffee</h2>
        <p class="section-subtitle">Mengapa memilih kami untuk kebutuhan kopi Anda?</p>
        <div class="row g-4 justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="feature-item">
                    <div class="feature-icon"><i class="fas fa-seedling"></i></div>
                    <h4 class="feature-title">Biji Kopi Pilihan</h4>
                    <p class="feature-desc">Robusta & Arabica terbaik dari petani lokal berkualitas tinggi</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-item">
                    <div class="feature-icon"><i class="fas fa-medal"></i></div>
                    <h4 class="feature-title">Kualitas Terjamin</h4>
                    <p class="feature-desc">Proses roasting profesional dengan standar kualitas internasional</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-item">
                    <div class="feature-icon"><i class="fa-solid fa-gears"></i></div>
                    <h4 class="feature-title">Produksi Konsisten</h4>
                    <p class="feature-desc">Menjaga cita rasa kopi tetap stabil di setiap produksi</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!--  ABOUT SECTION -->
<section id="about" class="section-padding about-section">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <div class="about-image-wrapper">
                    <img src="assets/images/kopi.jpg" alt="Tentang TEFA Coffee"
                         onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22600%22 height=%22450%22%3E%3Crect fill=%22%23D4A574%22 width=%22600%22 height=%22450%22/%3E%3Ctext fill=%22%232C1810%22 font-family=%22Arial%22 font-size=%2280%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dominant-baseline=%22middle%22%3E🏪%3C/text%3E%3C/svg%3E'">
                </div>
            </div>
            <div class="col-lg-6">
                <div class="about-content">
                    <h2>Tentang TEFA Coffee</h2>
                    <p>TEFA Coffee berkomitmen menyediakan kopi berkualitas premium untuk para pecinta kopi Indonesia. Kami bekerja sama langsung dengan petani lokal untuk memastikan setiap biji kopi yang kami sajikan memiliki kualitas terbaik.</p>
                    <p>Dengan proses roasting yang profesional dan standar kualitas yang ketat, kami menjamin setiap cangkir kopi yang Anda nikmati memberikan pengalaman yang tak terlupakan.</p>
                    
                    <?php if(isCustomerLoggedIn()): ?>
                        <a href="customer/dashboard.php" class="btn btn-gold btn-lg mt-4">
                            <i class="fas fa-shopping-bag me-2"></i>Mulai Belanja
                        </a>
                    <?php else: ?>
                        <a href="customer/register.php" class="btn btn-gold btn-lg mt-4">
                            <i class="fas fa-user-plus me-2"></i>Daftar & Mulai Belanja
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!--  CTA SECTION -->
<section class="cta-section">
    <div class="container">
        <h2 class="cta-title">Siap Menikmati Kopi Premium?</h2>
        <p class="cta-subtitle">Daftar sekarang dan nikmati pengalaman berbelanja kopi terbaik.</p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <?php if(isCustomerLoggedIn()): ?>
                <a href="customer/dashboard.php" class="btn btn-gold btn-lg btn-lg-custom">
                    <i class="fas fa-shopping-bag me-2"></i>Ke Dashboard Belanja
                </a>
            <?php else: ?>
                <a href="customer/register.php" class="btn btn-gold btn-lg btn-lg-custom">Register sekarang
                </a>
                <a href="customer/login.php" class="btn btn-outline-light btn-lg btn-lg-custom"> sudah punya akun? Login
                </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!--  FOOTER -->
<footer class="footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="footer-brand">
                   TEFA COFFEE
                </div>
                <p class="footer-desc">Menyajikan kopi premium terbaik untuk menemani setiap momen spesial Anda. Kualitas, rasa, dan kepuasan pelanggan adalah prioritas kami.</p>
                <div class="social-links">
                    <a href="#" class="social-link" data-bs-toggle="tooltip" title="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-link" data-bs-toggle="tooltip" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-link" data-bs-toggle="tooltip" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                    <a href="#" class="social-link" data-bs-toggle="tooltip" title="TikTok"><i class="fab fa-tiktok"></i></a>
                </div>
            </div>
            <div class="col-lg-2 col-md-6">
                <h5 class="footer-title">Navigasi</h5>
                <ul class="footer-links">
                    <li><a href="#home"><i class="fas fa-chevron-right me-1 small"></i>Beranda</a></li>
                    <li><a href="#products"><i class="fas fa-chevron-right me-1 small"></i>Produk</a></li>
                    <li><a href="#features"><i class="fas fa-chevron-right me-1 small"></i>Keunggulan</a></li>
                    <li><a href="#about"><i class="fas fa-chevron-right me-1 small"></i>Tentang</a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-md-6">
                <h5 class="footer-title">Akun</h5>
                <ul class="footer-links">
                    <?php if(isCustomerLoggedIn()): ?>
                        <li><a href="customer/dashboard.php"><i class="fas fa-chevron-right me-1 small"></i>Dashboard</a></li>
                        <li><a href="logout.php"><i class="fas fa-chevron-right me-1 small"></i>Logout</a></li>
                    <?php else: ?>
                        <li><a href="customer/login.php"><i class="fas fa-chevron-right me-1 small"></i>Login</a></li>
                        <li><a href="customer/register.php"><i class="fas fa-chevron-right me-1 small"></i>Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="col-lg-3 col-md-6">
                <h5 class="footer-title">Kontak Kami</h5>
                <ul class="footer-links footer-contact">
                    <li><i class="fas fa-map-marker-alt mt-1"></i><span>Jl. Mastrip, Kotak Pos 164, Jember 68101, Jawa Timur, Indonesia.</span></li>
                    <li><i class="fas fa-phone mt-1"></i><span>0812-3456-7890</span></li>
                    <li><i class="fas fa-envelope mt-1"></i><span>tefacofesip@polije.ac.id</span></li>
                    <li><i class="fas fa-clock mt-1"></i><span>Senin-Minggu: 08.00-20.00</span></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p class="mb-0">&copy; <?= date('Y') ?> TEFA Coffee - Politeknik Negeri Jember. All Rights Reserved.</p>
        </div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/customer.js"></script>
</body>
</html>