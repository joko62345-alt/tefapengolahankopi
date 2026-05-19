<?php
require_once '../config/config.php';
checkRole('customer');

if (!isset($_SESSION['user_id'])) {
    redirect('../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

$query = "SELECT * FROM users WHERE id='$user_id'";
$result = mysqli_query($conn, $query);

if (!$result) {
    die("Query error: " . mysqli_error($conn));
}

$user = mysqli_fetch_assoc($result);

if (!$user) {
    die("User tidak ditemukan!");
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $telepon = mysqli_real_escape_string($conn, $_POST['telepon']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);

    $update_query = "UPDATE users SET 
        nama_lengkap = '$nama',
        email = '$email',
        telepon = '$telepon',
        alamat = '$alamat'
        WHERE id = '$user_id'";

    if (mysqli_query($conn, $update_query)) {
        $_SESSION['nama'] = $nama;
        $success = "Profil berhasil diperbarui!";
        $user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id='$user_id'"));
    } else {
        $error = "Gagal memperbarui profil: " . mysqli_error($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profil - TEFA Coffee</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"rel="stylesheet">
    <link rel="stylesheet" href="css/edit.css">
</head>
<body>
    <div class="edit-card">
        <!-- Header -->
        <div class="edit-header">
            <h3 class="edit-title">Edit Profil</h3>
        </div>

        <!-- Body -->
        <div class="edit-body">
            <?php if ($success): ?>
                <div class="alert-custom alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert-custom alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" action="" id="profileForm">
                <!-- Nama Lengkap -->
                <div class="form-group">
                    <label class="form-group-label">
                        <i class="fas fa-user"></i>
                        Nama Lengkap
                        <span class="required">*</span>
                    </label>
                    <div class="input-wrapper">
                        <input type="text" name="nama" class="form-control-custom"
                            value="<?= htmlspecialchars($user['nama_lengkap'] ?? '') ?>"
                            placeholder="masukkan nama Anda" required>
                        <i class=></i>
                    </div>
                    <div class="input-hint">
                        <i class="fas fa-info-circle"></i>
                        Masukkan Nama Anda
                    </div>
                </div>

                <!-- Email -->
                <div class="form-group">
                    <label class="form-group-label">
                        <i class="fas fa-envelope"></i>
                        Email
                        <span class="required">*</span>
                    </label>
                    <div class="input-wrapper">
                        <input type="email" name="email" class="form-control-custom"
                            value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder="masukkan email Anda"
                            required>
                        <i class=></i>
                    </div>
                    <div class="input-hint">
                        <i class="fas fa-shield-alt"></i>
                        Masukkan Email Anda
                    </div>
                </div>

                <!-- Telepon -->
                <div class="form-group">
                    <label class="form-group-label">
                        <i class="fas fa-phone"></i>
                        Nomor Telepon
                        <span class="required">*</span>
                    </label>
                    <div class="input-wrapper">
                        <input type="text" name="telepon" class="form-control-custom"
                            value="<?= htmlspecialchars($user['telepon'] ?? '') ?>"
                            placeholder="masukkan nomor telepon Anda, tanpa spasi atau tanda" pattern="[0-9]{10,13}"
                            required>
                        <i class=></i>
                    </div>
                    <div class="input-hint">
                        <i class="fas fa-info-circle"></i>
                        Minimal 10 digit, tanpa spasi atau tanda
                    </div>
                </div>

                <!-- Alamat -->
                <div class="form-group">
                    <label class="form-group-label">
                        <i class="fas fa-map-marker-alt"></i>
                        Alamat Lengkap
                        <span class="required">*</span>
                    </label>
                    <div class="input-wrapper">
                        <textarea name="alamat" class="form-control-custom"
                            placeholder="masukkan alamat lengkap Anda, termasuk jalan, kota, dan kode pos" rows="4"
                            required><?= htmlspecialchars($user['alamat'] ?? '') ?></textarea>
                        <i class=></i>
                    </div>
                    <div class="input-hint">
                        <i class="fas fa-info-circle"></i>
                        Masukkan Alamat Lengkap Anda
                    </div>
                </div>
            </form>
        </div>
        <div class="edit-footer">
            <a href="dashboard.php" class="btn-modal-close">  Batal
            </a>
            
            <button type="submit" form="profileForm" class="btn-modal-save"> Simpan Perubahan
            </button>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/edit.js"></script>
</body>

</html>