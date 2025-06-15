<?php
session_start(); // Memulai sesi

// Redirect jika user sudah login
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'includes/db_connect.php'; // Koneksi ke database

$message = ''; // Variabel untuk pesan error/sukses

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    // --- START: Perubahan di sini untuk kolom baru
    $nama_lengkap = $_POST['nama_lengkap'] ?? ''; // Ambil nama_lengkap dari POST
    $alamat = $_POST['alamat'] ?? null; // Alamat boleh NULL di DB, set null jika kosong dari form
    $no_telepon = $_POST['no_telepon'] ?? null; // No telepon boleh NULL di DB, set null jika kosong dari form
    // --- END: Perubahan di sini untuk kolom baru


    // Validasi input
    // Menambahkan validasi untuk nama_lengkap karena itu NOT NULL di DB Anda
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($nama_lengkap)) {
        $message = '<div class="message error">Username, Email, Password, Konfirmasi Password, dan Nama Lengkap harus diisi!</div>';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = '<div class="message error">Format email tidak valid!</div>';
    } elseif (strlen($password) < 6) {
        $message = '<div class="message error">Password minimal 6 karakter!</div>';
    } elseif ($password !== $confirm_password) {
        $message = '<div class="message error">Konfirmasi password tidak cocok!</div>';
    } else {
        // Cek apakah username atau email sudah terdaftar
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt_check->bind_param("ss", $username, $email);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $message = '<div class="message error">Username atau Email sudah terdaftar!</div>';
        } else {
            // Hash password sebelum disimpan
            $password_hash_to_store = password_hash($password, PASSWORD_DEFAULT);
            $role = 'user'; // Default role untuk pendaftaran

            // --- START: Perubahan di sini untuk query INSERT dan bind_param
            // Sesuaikan query INSERT agar memasukkan data ke semua kolom yang sesuai di tabel 'users'
            $stmt_insert = $conn->prepare("INSERT INTO users (username, password, email, role, nama_lengkap, alamat, no_telepon) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_insert->bind_param(
                "sssssss", // 7 's' untuk 7 parameter: username, password, email, role, nama_lengkap, alamat, no_telepon
                $username,
                $password_hash_to_store, // Menggunakan variabel yang sudah di-hash
                $email,
                $role,
                $nama_lengkap,
                $alamat,
                $no_telepon
            );
            // --- END: Perubahan di sini untuk query INSERT dan bind_param

            if ($stmt_insert->execute()) {
                $_SESSION['message'] = '<div class="message success">Pendaftaran berhasil! Silakan login.</div>';
                header('Location: login.php'); // Redirect ke halaman login setelah pendaftaran berhasil
                exit();
            } else {
                $message = '<div class="message error">Gagal mendaftar. Silakan coba lagi. Debug: ' . $stmt_insert->error . '</div>';
                error_log("Error during registration: " . $stmt_insert->error); // Log error for debugging
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - Oleh-oleh Batam</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>Oleh-oleh Batam</h1>
            <button class="hamburger" aria-label="Toggle navigation">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </button>
            <nav>
                <ul>
                    <li><a href="index.php">Beranda</a></li>
                    <li><a href="products.php">Produk</a></li>
                    <li><a href="cart.php">Keranjang</a></li>
                    <li><a href="login.php">Login</a></li>
                    <li class="active"><a href="register.php">Daftar</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>)</a></li>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <li><a href="admin/dashboard.php">Admin Panel</a></li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <section class="container form-section">
            <h2 class="text-center">Daftar Akun Baru</h2>
            <?php echo $message; // Menampilkan pesan error/sukses ?>
            <form action="register.php" method="POST" class="admin-form">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="nama_lengkap">Nama Lengkap:</label>
                    <input type="text" id="nama_lengkap" name="nama_lengkap" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Konfirmasi Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <div class="form-group">
                    <label for="alamat">Alamat (Opsional):</label>
                    <textarea id="alamat" name="alamat" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label for="no_telepon">No. Telepon (Opsional):</label>
                    <input type="text" id="no_telepon" name="no_telepon">
                </div>
                <div class="form-group text-center">
                    <button type="submit" class="btn-primary">Daftar</button>
                </div>
                <p class="text-center">Sudah punya akun? <a href="login.php">Login di sini</a></p>
            </form>
        </section>
    </main>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> Oleh-oleh Khas Batam. Semua Hak Dilindungi.</p>
    </footer>

    <script src="assets/js/script.js"></script>
    <?php $conn->close(); ?>
</body>
</html>