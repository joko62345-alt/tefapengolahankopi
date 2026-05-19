<?php
require_once '../config/config.php';
class StaffLogin {
    private $conn;
    private $error;
    
    /**
     * Constructor - Inisialisasi koneksi dan error session
     * @param mysqli $connection Koneksi database dari config.php
     */
    public function __construct($connection) {
        $this->conn = $connection;
        $this->error = $_SESSION['error'] ?? '';
        unset($_SESSION['error']);
    }
    
    /**
     * Redirect user jika sudah login sesuai role
     * @return void
     */
    public function handleAuthenticatedUser() {
        if (!isLoggedIn()) {
            return;
        }
        
        $role = $_SESSION['role'] ?? '';
        
        if ($role === 'admin') {
            redirect('admin/dashboard.php');
        } elseif ($role === 'manager') {
            redirect('manager/dashboard.php');
        } elseif ($role === 'customer') {
            $_SESSION['error'] = 'Akses ditolak! Halaman ini khusus staff TEFA Coffee.';
            redirect('index.php');
        }
    }
    
    /**
     * Proses login ketika form disubmit
     * @return void
     */
    public function processLogin() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        
        $username = mysqli_real_escape_string($this->conn, $_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        $query = "SELECT * FROM users WHERE username = '$username'";
        $result = mysqli_query($this->conn, $query);
        
        if (mysqli_num_rows($result) === 1) {
            $user = mysqli_fetch_assoc($result);
            
            $this->authenticateUser($user, $password);
        } else {
            $this->error = 'Username salah!';
        }
    }
    
    /**
     * Validasi kredensial user dan setup session
     * @param array $user Data user dari database
     * @param string $password Password input user
     * @return void
     */
    private function authenticateUser($user, $password) {
        // Blokir akses customer ke sistem internal
        if ($user['role'] === 'customer') {
            $this->error = 'Akun customer tidak dapat mengakses sistem internal!';
            return;
        }
        
        // Verifikasi password
        if (!password_verify($password, $user['password'])) {
            $this->error = 'Password salah!';
            return;
        }
        
        // Setup session login
        $this->setUserSession($user);
        
        // Redirect berdasarkan role
        $this->redirectByRole($user['role']);
    }
    
    /**
     * Set session data untuk user yang berhasil login
     * @param array $user Data user
     * @return void
     */
    private function setUserSession($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['nama'] = $user['nama_lengkap'];
    }
    
    /**
     * Redirect ke dashboard sesuai role user
     * @param string $role Role user
     * @return void
     */
    private function redirectByRole($role) {
        if ($role === 'admin') {
            redirect('admin/dashboard.php');
        } elseif ($role === 'manager') {
            redirect('manager/dashboard.php');
        }
    }
    
    /**
     * Getter untuk error message
     * @return string
     */
    public function getError() {
        return $this->error;
    }
    
    /**
     * Setter untuk error message (jika diperlukan dari luar)
     * @param string $message
     * @return void
     */
    public function setError($message) {
        $this->error = $message;
    }
}

$staffLogin = new StaffLogin($conn);
$staffLogin->handleAuthenticatedUser();
$staffLogin->processLogin();
$error = $staffLogin->getError();
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login - TEFA Coffee Internal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/login.css">
</head>
<body>

    <div class="login-container">
        <!-- Left Side - Login Form -->
        <div class="login-left">
            <div class="login-header">
                <h1 class="login-title">Staff Login</h1>
                <p class="login-subtitle">TEFA Coffee Internal System</p>
            </div>

            <?php if ($error): ?>
                <div class="alert-custom alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <div class="input-wrapper">
                        <input type="text" name="username" id="username" class="form-control" required autofocus
                            placeholder="Masukkan username admin/manager" autocomplete="username">
                        <i class="fas fa-user"></i>
                    </div>
                </div>

                <div class="form-group">
    <label class="form-label">Password</label>
    <div class="input-wrapper">
        <input type="password" name="password" id="password" 
               class="form-control" required
               placeholder="••••••••" 
               autocomplete="current-password">
        <i class="fas fa-lock"></i>
        <button type="button" class="password-toggle" id="togglePassword">
            <i class="fas fa-eye"></i>
        </button>
    </div>
</div>

                <button type="submit" class="btn-login"> Masuk 
                </button>
            </form>

            <div class="back-link">
                <a href="../index.php"> Lihat Website TEFA Coffee
                </a>
            </div>
        </div>

        <!-- Right Side  -->
        <div class="login-right">
            <!-- Decorative Circles -->
            <div class="circle circle-1"></div>
            <div class="circle circle-2"></div>
            <div class="circle circle-3"></div>
            <div class="circle circle-4"></div>

            <div class="welcome-content">
                <div>
                    
                </div>
                <h2 class="welcome-title">SELAMAT DATANG</h2>
                <p class="welcome-text">
                    Portal khusus untuk admin dan manajer
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        
    // Toggle Password Visibility
    document.getElementById('togglePassword')?.addEventListener('click', function () {
        const passwordInput = document.getElementById('password');
        const icon = this.querySelector('i');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
        
        // Fokus kembali ke input password setelah toggle
        passwordInput.focus();
    });

    // Form validation dengan shake animation
    document.getElementById('loginForm')?.addEventListener('submit', function (e) {
        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value;

        if (!username || !password) {
            e.preventDefault();
            const loginLeft = document.querySelector('.login-left');
            loginLeft.style.animation = 'shake 0.5s ease';
            setTimeout(() => {
                loginLeft.style.animation = '';
            }, 500);
        }
    });

    // Add shake animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
    `;
    document.head.appendChild(style);
</script>
        
</body>

</html>