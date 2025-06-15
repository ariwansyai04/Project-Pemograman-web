<?php
session_start();
require_once 'includes/db_connect.php';

$products = [];
$sql = "SELECT id, nama_produk, harga, gambar_url, deskripsi_produk FROM products ORDER BY nama_produk ASC";

if ($stmt = $conn->prepare($sql)) {
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
    $stmt->close();
} else {
    error_log("Error preparing statement for products: " . $conn->error);
}

$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produk - Oleh-oleh Batam</title>
    <link rel="stylesheet" href="assets/css/style.css">
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
                    <li class="active"><a href="products.php">Produk</a></li>
                    <li><a href="cart.php">Keranjang</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>)</a></li>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <li><a href="admin/dashboard.php">Admin Panel</a></li>
                        <?php endif; ?>
                    <?php else: ?>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Daftar</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <section class="products-list container">
            <h2>Daftar Produk</h2>
            <?php echo $message; ?>

            <div class="product-grid">
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $product): ?>
                        <a href="product_detail.php?id=<?php echo htmlspecialchars($product['id']); ?>" class="product-item">
                            <img src="<?php echo htmlspecialchars($product['gambar_url']); ?>" alt="<?php echo htmlspecialchars($product['nama_produk']); ?>">
                            <div class="product-info-overlay">
                                <h4><?php echo htmlspecialchars($product['nama_produk']); ?></h4>
                                <p class="price">Rp <?php echo number_format($product['harga'], 0, ',', '.'); ?></p>
                                <span class="btn-detail">Lihat Detail <i class="fas fa-arrow-right"></i></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-products">Tidak ada produk ditemukan.</p>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> Oleh-oleh Khas Batam. Semua Hak Dilindungi.</p>
    </footer>

    <script src="assets/js/script.js"></script>
    <?php $conn->close(); ?>
</body>
</html>