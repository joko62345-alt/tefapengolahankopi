<?php
require_once '../config/config.php';

// Customer sudah login → redirect ke dashboard
if (isLoggedIn() && $_SESSION['role'] == 'customer') {
    redirect('customer/dashboard.php');
}

// Staff login → redirect ke dashboard mereka
if (isLoggedIn()) {
    if ($_SESSION['role'] == 'admin')
        redirect('../admin/dashboard.php');
    elseif ($_SESSION['role'] == 'manager')
        redirect('../manager/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    $query = "SELECT * FROM users WHERE username = '$username'";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);

        if ($user['role'] != 'customer') {
            $error = 'Akun staff silakan login di halaman staff login!';
        } elseif (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['nama'] = $user['nama_lengkap'];

            redirect('customer/dashboard.php');
        } else {
            $error = 'Password salah!';
        }
    } else {
        $error = 'Username tidak ditemukan!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Customer - TEFA Coffee</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <!-- Login Card - COMPACT -->
    <div class="login-card">
        <!-- Brand Header -->
        <div class="card-brand">
            <div class=>
                <i class=></i>
            </div>
            <h1 class="brand-title">Customer Login</h1>
            <p class="brand-subtitle">Belanja kopi di TEFA Coffee</p>
        </div>

        <!-- Body -->
        <div class="card-body-custom">
            <!-- Error Message -->
            <?php if ($error): ?>
                <div class="alert-custom" role="alert">
                    <i class="fas fa-circle-exclamation"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" class="auth-form" novalidate>
                <!-- Username -->
                <div class="mb-3">
                    <label class="form-label" for="username">
                        <i class="fas fa-user me-1"></i>Username
                    </label>
                    <input type="text" id="username" name="username" class="form-control"
                        placeholder="Masukkan Username Anda" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        required autofocus autocomplete="username">
                </div>

                <!-- Password -->
                <div class="mb-3">
                    <label class="form-label" for="password">
                        <i class="fas fa-lock me-1"></i>Password
                    </label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" class="form-control"
                            placeholder="Masukkan Password Anda" required autocomplete="current-password">
                        <button type="button" class="password-toggle" id="togglePassword" title="Lihat password"
                            aria-label="Toggle password visibility">
                            <i class=""></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-login"> Login
                </button>
            </form>
            <!-- Divider -->
            <div class="divider"><span>atau</span></div>

            <!-- Register CTA -->
            <div class="text-center">
                <small class="text-muted d-block mb-2">Belum punya akun?</small>
                <a href="register.php" class="btn-register"> Register
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div class="card-footer-custom">
            <a href="../index.php">Kembali ke Beranda</a>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/login.js"></script>
   
</body>

</html>