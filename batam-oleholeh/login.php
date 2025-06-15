<?php
session_start(); // Memulai sesi

// Cek jika user sudah login, arahkan ke halaman utama atau redirect yang diminta
if (isset($_SESSION['user_id'])) {
    if (isset($_GET['redirect'])) {
        header('Location: ' . htmlspecialchars($_GET['redirect']) . '.php');
    } else {
        header('Location: index.php');
    }
    exit();
}

require_once 'includes/db_connect.php'; // Koneksi ke database

$message = ''; // Variabel untuk pesan error/sukses
// Ambil pesan dari sesi jika ada (misal dari add_to_cart.php atau register.php)
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Hapus pesan setelah ditampilkan
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username_or_email = $_POST['username_or_email'] ?? '';
    $password = $_POST['password'] ?? '';
    $redirect_url_from_post = $_POST['redirect_url'] ?? ''; // Ambil dari hidden input

    if (empty($username_or_email) || empty($password)) {
        $message = '<div class="message error">Username/Email dan Password harus diisi!</div>';
    } else {
        // MENGUBAH password_hash menjadi password sesuai struktur DB Anda
        $stmt = $conn->prepare("SELECT id, username, email, password, role FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username_or_email, $username_or_email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            // Verifikasi password yang di-hash (menggunakan kolom 'password' dari DB)
            if (password_verify($password, $user['password'])) { // <-- Perubahan kunci di sini juga
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role']; // Simpan role (misal: 'user', 'admin')

                // Redirect ke halaman beranda, dashboard admin, atau halaman sebelumnya
                if ($user['role'] === 'admin') {
                    header('Location: admin/dashboard.php');
                } elseif (!empty($redirect_url_from_post)) {
                    // Redirect ke URL yang disimpan di hidden input
                    header('Location: ' . htmlspecialchars($redirect_url_from_post) . '.php'); // Asumsi formatnya adalah nama_file.php
                } elseif (isset($_GET['redirect'])) {
                    // Redirect ke URL dari parameter GET (jika tidak ada di POST)
                    header('Location: ' . htmlspecialchars($_GET['redirect']) . '.php');
                } else {
                    header('Location: index.php');
                }
                exit();
            } else {
                $message = '<div class="message error">Username/Email atau Password salah!</div>';
            }
        } else {
            $message = '<div class="message error">Username/Email atau Password salah!</div>';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Oleh-oleh Batam</title>
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
                    <li class="active"><a href="login.php">Login</a></li>
                    <li><a href="register.php">Daftar</a></li>
                    <?php
                    // Logika ini mungkin tidak perlu di halaman login, tapi jaga konsistensi
                    if (isset($_SESSION['user_id'])):
                    ?>
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
            <h2 class="text-center">Login Akun</h2>
            <?php echo $message; // Menampilkan pesan error/sukses ?>
            <form action="login.php<?php echo isset($_GET['redirect']) ? '?redirect=' . htmlspecialchars($_GET['redirect']) : ''; ?>" method="POST" class="admin-form">
                <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars($_GET['redirect'] ?? ''); ?>">
                <div class="form-group">
                    <label for="username_or_email">Username atau Email:</label>
                    <input type="text" id="username_or_email" name="username_or_email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group text-center">
                    <button type="submit" class="btn-primary">Login</button>
                </div>
                <p class="text-center">Belum punya akun? <a href="register.php">Daftar sekarang</a></p>
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