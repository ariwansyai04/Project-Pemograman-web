<?php
session_start(); // Memulai sesi
require_once 'includes/db_connect.php'; // Koneksi ke database

$cart_items = [];
$total_cart_price = 0;
$message = ''; // Variabel untuk pesan dari add_to_cart.php atau update

// Ambil pesan dari sesi jika ada
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Hapus pesan setelah ditampilkan
}

// Redirect jika user belum login
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = '<div class="message info">Anda harus login untuk melihat keranjang belanja.</div>';
    header("Location: login.php?redirect=cart");
    exit();
}

$user_id = $_SESSION['user_id'];

// Logika untuk mengupdate kuantitas atau menghapus item dari keranjang
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_cart'])) {
        $product_id_to_update = $_POST['product_id'] ?? 0;
        $new_quantity = $_POST['quantity'] ?? 0;

        $product_id_to_update = (int)$product_id_to_update;
        $new_quantity = (int)$new_quantity;

        if ($product_id_to_update > 0 && $new_quantity >= 0) {
            // Periksa ketersediaan stok produk
            $stmt_stock = $conn->prepare("SELECT nama_produk, stok FROM products WHERE id = ?");
            $stmt_stock->bind_param("i", $product_id_to_update);
            $stmt_stock->execute();
            $result_stock = $stmt_stock->get_result();
            $product_info = $result_stock->fetch_assoc();
            $stmt_stock->close();

            if ($product_info) {
                $available_stock = $product_info['stok'];
                $product_name = $product_info['nama_produk'];

                if ($new_quantity == 0) {
                    // Hapus item jika kuantitas 0
                    $stmt_delete = $conn->prepare("DELETE FROM carts WHERE user_id = ? AND product_id = ?");
                    $stmt_delete->bind_param("ii", $user_id, $product_id_to_update);
                    if ($stmt_delete->execute()) {
                        $_SESSION['message'] = '<div class="message success">Produk ' . htmlspecialchars($product_name) . ' berhasil dihapus dari keranjang.</div>';
                    } else {
                        $_SESSION['message'] = '<div class="message error">Gagal menghapus produk dari keranjang.</div>';
                        error_log("Error deleting cart item: " . $stmt_delete->error);
                    }
                    $stmt_delete->close();
                } elseif ($new_quantity > $available_stock) {
                    $_SESSION['message'] = '<div class="message error">Stok ' . htmlspecialchars($product_name) . ' tidak cukup. Tersedia: ' . htmlspecialchars($available_stock) . '.</div>';
                } else {
                    // Update kuantitas
                    $stmt_update = $conn->prepare("UPDATE carts SET quantity = ? WHERE user_id = ? AND product_id = ?");
                    $stmt_update->bind_param("iii", $new_quantity, $user_id, $product_id_to_update);
                    if ($stmt_update->execute()) {
                        $_SESSION['message'] = '<div class="message success">Kuantitas ' . htmlspecialchars($product_name) . ' berhasil diperbarui.</div>';
                    } else {
                        $_SESSION['message'] = '<div class="message error">Gagal memperbarui kuantitas produk.</div>';
                        error_log("Error updating cart quantity: " . $stmt_update->error);
                    }
                    $stmt_update->close();
                }
            } else {
                $_SESSION['message'] = '<div class="message error">Produk tidak ditemukan.</div>';
            }
        } else {
            $_SESSION['message'] = '<div class="message error">Kuantitas tidak valid.</div>';
        }
    } elseif (isset($_POST['remove_item'])) {
        $product_id_to_remove = $_POST['product_id'] ?? 0;
        $product_id_to_remove = (int)$product_id_to_remove;

        if ($product_id_to_remove > 0) {
            // Ambil nama produk sebelum dihapus untuk pesan
            $stmt_name = $conn->prepare("SELECT nama_produk FROM products WHERE id = ?");
            $stmt_name->bind_param("i", $product_id_to_remove);
            $stmt_name->execute();
            $result_name = $stmt_name->get_result();
            $product_name = $result_name->fetch_assoc()['nama_produk'] ?? 'Produk';
            $stmt_name->close();

            $stmt_delete = $conn->prepare("DELETE FROM carts WHERE user_id = ? AND product_id = ?");
            $stmt_delete->bind_param("ii", $user_id, $product_id_to_remove);
            if ($stmt_delete->execute()) {
                $_SESSION['message'] = '<div class="message success">Produk ' . htmlspecialchars($product_name) . ' berhasil dihapus dari keranjang.</div>';
            } else {
                $_SESSION['message'] = '<div class="message error">Gagal menghapus produk dari keranjang.</div>';
                error_log("Error deleting cart item: " . $stmt_delete->error);
            }
            $stmt_delete->close();
        }
    }
    // Redirect kembali ke halaman keranjang untuk menghindari resubmission
    header("Location: cart.php");
    exit();
}


// Ambil item keranjang dari database
$stmt = $conn->prepare("SELECT
                            c.product_id,
                            c.quantity,
                            p.nama_produk,
                            p.harga,
                            p.gambar_url,
                            p.stok AS product_stock
                        FROM carts c
                        JOIN products p ON c.product_id = p.id
                        WHERE c.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $cart_items[] = $row;
        $total_cart_price += ($row['quantity'] * $row['harga']);
    }
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Belanja - Oleh-oleh Batam</title>
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
                    <li><a href="products.php">Produk</a></li>
                    <li class="active"><a href="cart.php">Keranjang</a></li>
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
        <section class="cart-section container">
            <h2>Keranjang Belanja Anda</h2>
            <?php echo $message; // Menampilkan pesan dari sesi ?>

            <?php if (!empty($cart_items)): ?>
                <div class="cart-items-container">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item-card">
                            <img src="<?php echo htmlspecialchars($item['gambar_url']); ?>" alt="<?php echo htmlspecialchars($item['nama_produk']); ?>">
                            <div class="item-details">
                                <h3><?php echo htmlspecialchars($item['nama_produk']); ?></h3>
                                <p class="price">Harga: Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?></p>
                                <p class="total-item-price">Total: Rp <?php echo number_format($item['quantity'] * $item['harga'], 0, ',', '.'); ?></p>

                                <form action="cart.php" method="POST" class="update-quantity-form">
                                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($item['product_id']); ?>">
                                    <label for="quantity_<?php echo $item['product_id']; ?>">Kuantitas:</label>
                                    <input
                                        type="number"
                                        id="quantity_<?php echo $item['product_id']; ?>"
                                        name="quantity"
                                        value="<?php echo htmlspecialchars($item['quantity']); ?>"
                                        min="0"
                                        max="<?php echo htmlspecialchars($item['product_stock']); ?>"
                                        onchange="this.form.submit()"
                                        <?php echo ($item['product_stock'] == 0 && $item['quantity'] == 0) ? 'disabled' : ''; ?>
                                    >
                                </form>
                                <form action="cart.php" method="POST" class="remove-item-form">
                                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($item['product_id']); ?>">
                                    <button type="submit" name="remove_item" class="btn-remove-item" onclick="return confirm('Yakin ingin menghapus <?php echo htmlspecialchars($item['nama_produk']); ?> dari keranjang?');">
                                        <i class="fas fa-trash-alt"></i> Hapus
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="cart-summary">
                    <h3>Ringkasan Belanja</h3>
                    <p>Subtotal: <strong>Rp <?php echo number_format($total_cart_price, 0, ',', '.'); ?></strong></p>
                    <a href="checkout.php" class="btn-checkout"><i class="fas fa-money-check-alt"></i> Lanjutkan ke Checkout</a>
                    <a href="products.php" class="btn-continue-shopping"><i class="fas fa-shopping-bag"></i> Lanjutkan Belanja</a>
                </div>
            <?php else: ?>
                <p class="no-products">Keranjang belanja Anda kosong.</p>
                <div class="text-center">
                    <a href="products.php" class="btn-continue-shopping"><i class="fas fa-shopping-bag"></i> Mulai Belanja</a>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> Oleh-oleh Khas Batam. Semua Hak Dilindungi.</p>
    </footer>

    <script src="assets/js/script.js"></script>
</body>
</html>