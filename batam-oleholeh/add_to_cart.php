<?php
session_start();
require_once 'includes/db_connect.php';

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = '<div class="message info">Anda harus login untuk menambahkan produk ke keranjang.</div>';
    header("Location: login.php?redirect=product_detail&id=" . ($_POST['product_id'] ?? '')); // Redirect kembali ke detail produk jika bisa
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    $product_id = $_POST['product_id'] ?? 0;
    $quantity = $_POST['quantity'] ?? 1;

    // Sanitasi dan validasi input
    $product_id = (int)$product_id;
    $quantity = (int)$quantity;

    if ($product_id <= 0 || $quantity <= 0) {
        $_SESSION['message'] = '<div class="message error">ID Produk atau Kuantitas tidak valid.</div>';
        header("Location: products.php");
        exit();
    }

    // Periksa ketersediaan stok produk
    $stmt_stock = $conn->prepare("SELECT nama_produk, harga, stok FROM products WHERE id = ?");
    $stmt_stock->bind_param("i", $product_id);
    $stmt_stock->execute();
    $result_stock = $stmt_stock->get_result();
    $product_info = $result_stock->fetch_assoc();
    $stmt_stock->close();

    if (!$product_info) {
        $_SESSION['message'] = '<div class="message error">Produk tidak ditemukan.</div>';
        header("Location: products.php");
        exit();
    }

    $available_stock = $product_info['stok'];
    $product_name = $product_info['nama_produk'];
    $product_price = $product_info['harga'];

    // Periksa apakah produk sudah ada di keranjang user
    $stmt_check_cart = $conn->prepare("SELECT quantity FROM carts WHERE user_id = ? AND product_id = ?");
    $stmt_check_cart->bind_param("ii", $user_id, $product_id);
    $stmt_check_cart->execute();
    $result_check_cart = $stmt_check_cart->get_result();
    $existing_cart_item = $result_check_cart->fetch_assoc();
    $stmt_check_cart->close();

    if ($existing_cart_item) {
        // Produk sudah ada di keranjang, update kuantitas
        $current_quantity = $existing_cart_item['quantity'];
        $new_total_quantity = $current_quantity + $quantity;

        if ($new_total_quantity > $available_stock) {
            $_SESSION['message'] = '<div class="message error">Stok ' . htmlspecialchars($product_name) . ' tidak cukup. Tersedia: ' . htmlspecialchars($available_stock) . '. Item Anda di keranjang saat ini: ' . htmlspecialchars($current_quantity) . '.</div>';
        } else {
            $stmt_update = $conn->prepare("UPDATE carts SET quantity = ? WHERE user_id = ? AND product_id = ?");
            $stmt_update->bind_param("iii", $new_total_quantity, $user_id, $product_id);
            if ($stmt_update->execute()) {
                $_SESSION['message'] = '<div class="message success">Kuantitas ' . htmlspecialchars($product_name) . ' berhasil diperbarui di keranjang.</div>';
            } else {
                $_SESSION['message'] = '<div class="message error">Gagal memperbarui kuantitas produk di keranjang.</div>';
                error_log("Error updating cart: " . $stmt_update->error);
            }
            $stmt_update->close();
        }
    } else {
        // Produk belum ada di keranjang, tambahkan sebagai item baru
        if ($quantity > $available_stock) {
            $_SESSION['message'] = '<div class="message error">Stok ' . htmlspecialchars($product_name) . ' tidak cukup. Tersedia: ' . htmlspecialchars($available_stock) . '.</div>';
        } else {
            $stmt_insert = $conn->prepare("INSERT INTO carts (user_id, product_id, quantity) VALUES (?, ?, ?)");
            $stmt_insert->bind_param("iii", $user_id, $product_id, $quantity);
            if ($stmt_insert->execute()) {
                $_SESSION['message'] = '<div class="message success">Produk ' . htmlspecialchars($product_name) . ' berhasil ditambahkan ke keranjang.</div>';
            } else {
                $_SESSION['message'] = '<div class="message error">Gagal menambahkan produk ke keranjang.</div>';
                error_log("Error inserting into cart: " . $stmt_insert->error);
            }
            $stmt_insert->close();
        }
    }

    $conn->close();

    // Redirect kembali ke halaman detail produk atau ke halaman keranjang
    if (isset($_POST['redirect_to_cart']) && $_POST['redirect_to_cart'] == 'true') {
        header("Location: cart.php");
    } else {
        header("Location: product_detail.php?id=" . $product_id);
    }
    exit();

} else {
    // Jika diakses langsung tanpa POST request
    header("Location: products.php");
    exit();
}
?>