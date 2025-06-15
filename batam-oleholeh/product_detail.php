<?php
session_start();
require_once 'includes/db_connect.php';

$product = null;
$message = '';

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $product_id = $_GET['id'];
    $stmt = $conn->prepare("SELECT id, nama_produk, harga, gambar_url, deskripsi_produk, stok FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
    }
    $stmt->close();
}

if (!$product) {
    $_SESSION['message'] = '<div class="message error">Produk tidak ditemukan.</div>';
    header('Location: products.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['nama_produk']); ?> - Oleh-oleh Batam</title>
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
        <section class="product-detail-section container">
            <?php echo $message; ?>
            <div class="detail-content">
                <div class="product-image-container">
                    <img src="<?php echo htmlspecialchars($product['gambar_url']); ?>" alt="<?php echo htmlspecialchars($product['nama_produk']); ?>">
                </div>
                <div class="product-info">
                    <h2><?php echo htmlspecialchars($product['nama_produk']); ?></h2>
                    <p class="product-price">Harga: Rp <?php echo number_format($product['harga'], 0, ',', '.'); ?></p>
                    <p class="product-stock">Stok:
                        <?php
                        if ($product['stok'] > 0) {
                            echo '<span class="in-stock">' . htmlspecialchars($product['stok']) . ' tersedia</span>';
                        } else {
                            echo '<span class="out-of-stock">Stok Habis</span>';
                        }
                        ?>
                    </p>
                    <div class="product-description">
                        <h3>Deskripsi Produk:</h3>
                        <p><?php echo nl2br(htmlspecialchars($product['deskripsi_produk'])); ?></p>
                    </div>

                    <form action="add_to_cart.php" method="POST" class="add-to-cart-form">
                        <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['id']); ?>">
                        <div class="quantity-control">
                            <label for="quantity">Jumlah:</label>
                            <div class="quantity-input-group">
                                <button type="button" class="quantity-btn decrease-qty" <?php echo ($product['stok'] == 0) ? 'disabled' : ''; ?>>-</button>
                                <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?php echo htmlspecialchars($product['stok']); ?>" <?php echo ($product['stok'] == 0) ? 'disabled' : ''; ?> readonly>
                                <button type="button" class="quantity-btn increase-qty" <?php echo ($product['stok'] == 0) ? 'disabled' : ''; ?>>+</button>
                            </div>
                        </div>
                        <button type="submit" class="btn-add-to-cart" <?php echo ($product['stok'] == 0) ? 'disabled' : ''; ?>>
                            <i class="fas fa-cart-plus"></i> <?php echo ($product['stok'] == 0) ? 'Stok Habis' : 'Tambah ke Keranjang'; ?>
                        </button>
                    </form>

                    <a href="products.php" class="back-to-products">
                        <i class="fas fa-arrow-left"></i> Kembali ke Daftar Produk
                    </a>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> Oleh-oleh Khas Batam. Semua Hak Dilindungi.</p>
    </footer>

    <script src="assets/js/script.js"></script>
    <script>
        // JavaScript untuk mengontrol kuantitas di detail produk
        document.addEventListener('DOMContentLoaded', function() {
            const quantityInput = document.getElementById('quantity');
            const decreaseBtn = document.querySelector('.decrease-qty');
            const increaseBtn = document.querySelector('.increase-qty');
            const maxStock = parseInt(quantityInput.max);

            if (decreaseBtn && increaseBtn && quantityInput) {
                decreaseBtn.addEventListener('click', function() {
                    let currentVal = parseInt(quantityInput.value);
                    if (currentVal > parseInt(quantityInput.min)) { // Ensure it doesn't go below min
                        quantityInput.value = currentVal - 1;
                    }
                });

                increaseBtn.addEventListener('click', function() {
                    let currentVal = parseInt(quantityInput.value);
                    if (currentVal < maxStock) {
                        quantityInput.value = currentVal + 1;
                    }
                });
            }
        });
    </script>
    <?php $conn->close(); ?>
</body>
</html>