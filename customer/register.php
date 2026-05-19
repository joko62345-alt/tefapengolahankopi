<?php
require_once '../config/config.php';

// Customer sudah login → redirect
if (isLoggedIn() && $_SESSION['role'] == 'customer') {
    redirect('dashboard.php');
}

// Staff → redirect
if (isLoggedIn()) {
    if ($_SESSION['role'] == 'admin')
        redirect('../admin/dashboard.php');
    elseif ($_SESSION['role'] == 'manager')
        redirect('../manager/dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $telepon = mysqli_real_escape_string($conn, $_POST['telepon']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);

    if (strlen($username) < 4) {
        $error = 'Username minimal 4 karakter!';
    } elseif (strlen($_POST['password']) < 6) {
        $error = 'Password minimal 6 karakter!';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid!';
    } else {
        $check = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username' OR email = '$email'");
        if (mysqli_num_rows($check) > 0) {
            $error = 'Username atau email sudah terdaftar!';
        } else {
            $query = "INSERT INTO users (username, password, role, nama_lengkap, email, telepon, alamat) 
                      VALUES ('$username', '$password', 'customer', '$nama', '$email', '$telepon', '$alamat')";

            if (mysqli_query($conn, $query)) {
                $success = ' Registrasi berhasil! Silakan login.';
            } else {
                $error = 'Terjadi kesalahan!';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - TEFA Coffee</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/register.css">
</head>
<body>
   
    <div class="register-card">
        <div class="card-brand">
            <div class=>
            </div>
            <h1 class="brand-title">Daftar Akun</h1>
            <p class="brand-subtitle">Lengkapi data untuk mulai belanja</p>
        </div>
        <div class="card-body-custom">
            <!-- Messages -->
            <?php if ($error): ?>
                <div class="alert-custom alert-error" role="alert">
                    <i class="fas fa-circle-exclamation"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert-custom alert-success" role="alert">
                    <i class="fas fa-circle-check"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <!-- Register Form -->
            <form method="POST" class="auth-form" novalidate>
                <!-- Username -->
                <div class="mb-2">
                    <label class="form-label" for="username">
                        <i class="fas fa-user"></i>Username <span class="required">*</span>
                    </label>
                    <input type="text" id="username" name="username" class="form-control"
                        placeholder="masukkan username anda" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        required minlength="4" pattern="[a-zA-Z0-9_]{4,}" autocomplete="username">
                </div>

                <!-- Name -->
                <div class="mb-2">
                    <label class="form-label" for="nama">
                        <i class="fas fa-id-card"></i>Nama Lengkap <span class="required">*</span>
                    </label>
                    <input type="text" id="nama" name="nama" class="form-control" placeholder=" masukkan nama lengkap anda"
                        value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>" required autocomplete="name">
                </div>

                <!-- Email -->
                <div class="mb-2">
                    <label class="form-label" for="email">
                        <i class="fas fa-envelope"></i>Email <span class="required">*</span>
                    </label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="masukkan email anda"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autocomplete="email">
                </div>

                <!-- Phone -->
                <div class="mb-2">
                    <label class="form-label" for="telepon">
                        <i class="fas fa-phone"></i>Telepon <span class="required">*</span>
                    </label>
                    <input type="tel" id="telepon" name="telepon" class="form-control" placeholder="masukkan nomor telepon anda"
                        value="<?= htmlspecialchars($_POST['telepon'] ?? '') ?>" required pattern="[0-9]{10,13}"
                        autocomplete="tel">
                </div>

                <!-- Address -->
                <div class="mb-2">
                    <label class="form-label" for="alamat">
                        <i class="fas fa-map-marker-alt"></i>Alamat <span class="required">*</span>
                    </label>
                    <textarea id="alamat" name="alamat" class="form-control" rows="2" placeholder="masukkan alamat lengkap anda" autocomplete="street-address"
                        required><?= htmlspecialchars($_POST['alamat'] ?? '') ?></textarea>
                </div>

                <!-- Password -->
<div class="mb-2">
    <label class="form-label" for="password">
        <i class="fas fa-lock"></i>Password <span class="required">*</span>
    </label>
    <div class="password-wrapper">
    <input type="password" id="password" name="password" class="form-control"
        placeholder="Minimal 6 karakter" required>
    <button type="button" class="password-toggle" id="togglePassword">
        <i class="fas fa-eye"></i>
    </button>
</div>
    <div class="password-strength">
        <div class="strength-bar">
            <div class="strength-fill" id="strengthFill"></div>
        </div>
        <small class="strength-text" id="strengthText">Gunakan kombinasi huruf & angka</small>
    </div>
</div>

                <!-- Terms -->
                <div class="terms-check">
                    <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                    <label class="form-check-label" for="terms">
                        Saya setuju dengan <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Syarat &
                            Ketentuan</a>
                    </label>
                </div>
                <button type="submit" class="btn-register" id="btnSubmit"> Daftar</button>
            </form>
            <div class="divider"><span>atau</span></div>
            <div class="login-section">
                <p class="mb-0">Sudah punya akun?</p>
                <a href="login.php" class="btn-login-link"> Login
                </a>
            </div>
        </div>
    </div>
    <!-- Terms Modal - Compact -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background:var(--c-primary);color:#fff;">
                    <h5 class="modal-title">Syarat & Ketentuan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><strong>1. Penerimaan</strong><br>Dengan mendaftar, Anda menyetujui ketentuan TEFA Coffee.</p>
                    <p><strong>2. Akun</strong><br>Anda bertanggung jawab menjaga keamanan akun.</p>
                    <p><strong>3. Pesanan</strong><br>Pesanan yang dikonfirmasi bersifat mengikat.</p>
                    <p><strong>4. Privasi</strong><br>Data digunakan hanya untuk keperluan transaksi.</p>
                </div>
                <div class="modal-footer">
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/register.js"></script>
</body>
</html>