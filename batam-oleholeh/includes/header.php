<?php
// includes/header.php
// Menentukan halaman aktif untuk penyorotan navigasi
$currentPage = isset($_GET['page']) ? $_GET['page'] : 'home';
$currentProductId = isset($_GET['product_id']) ? $_GET['product_id'] : '';

// Set page title dynamically
$pageTitle = "Oleh-oleh Batam"; // Mengubah judul default
if ($currentPage === 'products') {
    $pageTitle = "Produk Kami - Oleh-oleh Batam";
} elseif ($currentPage === 'product_detail' && !empty($currentProductId)) {
    require_once 'includes/product_data.php'; // Pastikan path ini benar
    $foundProduct = null;
    foreach ($products as $product) {
        if ($product['id'] === $currentProductId) {
            $foundProduct = $product;
            break;
        }
    }
    if ($foundProduct) {
        $pageTitle = "{$foundProduct['name']} - Oleh-oleh Batam";
    } else {
        $pageTitle = "Produk Tidak Ditemukan - Oleh-oleh Batam";
    }
} elseif ($currentPage === 'checkout') { // Tambahkan kondisi untuk halaman checkout
    $pageTitle = "Checkout - Oleh-oleh Batam";
} elseif ($currentPage === 'admin_dashboard') {
    $pageTitle = "Dasbor Admin - Oleh-oleh Batam";
} elseif ($currentPage === 'admin_products') {
    $pageTitle = "Produk Admin - Oleh-oleh Batam";
} elseif ($currentPage === 'admin_add_product') {
    $pageTitle = "Tambah Produk - Oleh-oleh Batam";
}
?>
<!DOCTYPE html>
<html lang="id"> <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="assets/css/style.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    </head>
<body>
    <header <?php echo (strpos($currentPage, 'admin') !== false) ? 'class="admin-header"' : ''; ?>>
        <div class="container">
            <h1><?php echo (strpos($currentPage, 'admin') !== false) ? 'Panel Admin' : 'Oleh-oleh Batam'; ?></h1>
            <nav <?php echo (strpos($currentPage, 'admin') !== false) ? 'class="admin-nav"' : ''; ?>>
                <ul>
                    <?php if (strpos($currentPage, 'admin') !== false) : ?>
                        <li class="<?php echo ($currentPage === 'admin_dashboard') ? 'active' : ''; ?>"><a href="index.php?page=admin_dashboard"><i class="fas fa-tachometer-alt"></i> Dasbor</a></li>
                        <li class="<?php echo ($currentPage === 'admin_products' || $currentPage === 'admin_add_product') ? 'active' : ''; ?>"><a href="index.php?page=admin_products"><i class="fas fa-box"></i> Produk</a></li>
                        <li><a href="#"><i class="fas fa-users"></i> Pengguna</a></li>
                        <li><a href="#"><i class="fas fa-shopping-cart"></i> Pesanan</a></li>
                        <li><a href="index.php"><i class="fas fa-sign-out-alt"></i> Keluar</a></li>
                    <?php else : ?>
                        <li class="<?php echo ($currentPage === 'home' || $currentPage === '') ? 'active' : ''; ?>"><a href="index.php">Beranda</a></li>
                        <li class="<?php echo ($currentPage === 'products' || $currentPage === 'product_detail') ? 'active' : ''; ?>"><a href="index.php?page=products">Produk</a></li>
                        <li class="<?php echo ($currentPage === 'checkout') ? 'active' : ''; ?>"><a href="checkout.php">Keranjang</a></li>
                        <li><a href="#">Logout (Aju)</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php if (strpos($currentPage, 'admin') === false) : ?>
            <button class="hamburger" aria-label="Toggle navigation">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </button>
            <?php endif; ?>
        </div>
    </header>

    <main class="<?php echo (strpos($currentPage, 'admin') !== false) ? 'admin-main' : ''; ?> <?php echo ($currentPage === 'product_detail') ? 'product-detail-section' : ''; ?>">
        <div class="container">
            <?php
            // Contoh pesan statis (tidak akan muncul/hilang secara dinamis)
            // Hapus komentar untuk menampilkan pesan
            // echo '<div class="message success"><p><i class="fas fa-check-circle"></i> Item berhasil ditambahkan ke keranjang!</p></div>';
            // echo '<div class="message error"><p><i class="fas fa-exclamation-triangle"></i> Gagal menambahkan item ke keranjang. Silakan coba lagi.</p></div>';
            ?>