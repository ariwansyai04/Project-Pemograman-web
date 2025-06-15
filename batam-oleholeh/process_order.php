<?php
session_start();
require_once 'includes/db_connect.php';

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = '<div class="message info">Anda harus login untuk memproses pesanan.</div>';
    header("Location: login.php?redirect=checkout.php");
    exit();
}

// Pastikan request adalah POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['message'] = '<div class="message error">Metode request tidak valid.</div>';
    header("Location: checkout.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Ambil data dari form checkout
$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');
$city = trim($_POST['city'] ?? '');
$postal_code = trim($_POST['postal_code'] ?? '');
$country = trim($_POST['country'] ?? '');
$payment_method = trim($_POST['payment_method'] ?? '');

// Ambil detail kartu kredit jika metode pembayaran adalah 'credit_card'
// HATI-HATI: Jangan menyimpan detail kartu kredit secara langsung di database produksi!
// Gunakan gateway pembayaran yang aman (Stripe, Midtrans, dll.)
$card_name = ($payment_method === 'credit_card') ? trim($_POST['card_name'] ?? '') : '';
$card_number = ($payment_method === 'credit_card') ? trim($_POST['card_number'] ?? '') : '';
$exp_date = ($payment_method === 'credit_card') ? trim($_POST['exp_date'] ?? '') : '';
$cvv = ($payment_method === 'credit_card') ? trim($_POST['cvv'] ?? '') : ''; // CVV TIDAK PERNAH DISIMPAN

// --- VALIDASI DATA (PENTING!) ---
// Validasi dasar. Tambahkan validasi yang lebih kuat sesuai kebutuhan.
if (empty($full_name) || empty($email) || empty($phone) || empty($address) || empty($city) || empty($postal_code) || empty($country) || empty($payment_method)) {
    $_SESSION['message'] = '<div class="message error">Semua kolom informasi pengiriman dan metode pembayaran wajib diisi.</div>';
    header("Location: checkout.php");
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['message'] = '<div class="message error">Format email tidak valid.</div>';
    header("Location: checkout.php");
    exit();
}

if ($payment_method === 'credit_card') {
    if (empty($card_name) || empty($card_number) || empty($exp_date) || empty($cvv)) {
        $_SESSION['message'] = '<div class="message error">Detail kartu kredit wajib diisi.</div>';
        header("Location: checkout.php");
        exit();
    }
    // Tambahkan validasi format nomor kartu/tanggal kadaluarsa yang lebih kuat di sini
    // Regex untuk nomor kartu, format tanggal, dll.
}

// Gabungkan detail pengiriman ke dalam satu string untuk kolom alamat_pengiriman
// (Sesuai dengan skema tabel 'orders' Anda yang hanya memiliki 1 kolom alamat)
$full_shipping_address = "Nama: " . htmlspecialchars($full_name) . "\n";
$full_shipping_address .= "Email: " . htmlspecialchars($email) . "\n";
$full_shipping_address .= "Telepon: " . htmlspecialchars($phone) . "\n";
$full_shipping_address .= "Alamat: " . htmlspecialchars($address) . "\n";
$full_shipping_address .= "Kota: " . htmlspecialchars($city) . "\n";
$full_shipping_address .= "Kode Pos: " . htmlspecialchars($postal_code) . "\n";
$full_shipping_address .= "Negara: " . htmlspecialchars($country);


// --- Ambil item keranjang dari database (lagi, untuk memastikan data terbaru dan validasi stok) ---
$cartItems = [];
$subtotal = 0;
$shippingCost = 15000.00; // Pastikan ini konsisten dengan checkout.php

$stmt_cart = $conn->prepare("
    SELECT
        c.product_id,
        c.quantity,
        p.nama_produk,
        p.harga,
        p.stok
    FROM
        carts c
    JOIN
        products p ON c.product_id = p.id
    WHERE
        c.user_id = ?
");
$stmt_cart->bind_param("i", $user_id);
$stmt_cart->execute();
$result_cart = $stmt_cart->get_result();

if ($result_cart->num_rows === 0) {
    $_SESSION['message'] = '<div class="message error">Keranjang Anda kosong. Tidak dapat memproses pesanan.</div>';
    header("Location: products.php"); // Atau kembali ke cart.php
    exit();
}

while ($row = $result_cart->fetch_assoc()) {
    // Validasi stok terakhir sebelum memproses pesanan
    if ($row['quantity'] > $row['stok']) {
        $_SESSION['message'] = '<div class="message error">Maaf, stok ' . htmlspecialchars($row['nama_produk']) . ' tidak cukup. Tersedia: ' . htmlspecialchars($row['stok']) . '.</div>';
        header("Location: checkout.php");
        exit(); // Hentikan proses jika stok tidak cukup
    }
    $cartItems[] = $row; // Simpan item keranjang dengan detail produk
    $subtotal += $row['harga'] * $row['quantity'];
}
$stmt_cart->close();

$total_amount = $subtotal + $shippingCost;

// --- Mulai transaksi database ---
$conn->begin_transaction();

try {
    // Tentukan status pesanan awal berdasarkan metode pembayaran
    $status_pesanan_awal = 'pending'; // Default untuk semua
    if ($payment_method === 'cod') {
        $status_pesanan_awal = 'pending'; // Untuk COD, bisa langsung 'pending' atau 'diproses'
    } else {
        $status_pesanan_awal = 'pending'; // Untuk transfer/kartu kredit, menunggu pembayaran
    }

    // Siapkan detail pembayaran yang akan disimpan
    $payment_details_json = null;
    if ($payment_method === 'credit_card') {
        $payment_details = [
            'card_name' => $card_name,
            'card_number_masked' => 'XXXX XXXX XXXX ' . substr($card_number, -4), // Hanya simpan 4 digit terakhir
            'exp_date' => $exp_date
        ];
        $payment_details_json = json_encode($payment_details);
    } else {
        $payment_details_json = json_encode(['method' => $payment_method]); // Simpan metode pembayaran lainnya
    }

    // 1. Masukkan pesanan ke tabel 'orders'
    $stmt_order = $conn->prepare("
        INSERT INTO orders (
            user_id, order_date, total_amount, status_pesanan,
            alamat_pengiriman, metode_pembayaran, payment_details
        ) VALUES (?, NOW(), ?, ?, ?, ?, ?)
    ");
    // Perhatikan:
    // - tanggal_pembayaran dan bukti_pembayaran_url tidak diisi di sini, akan di-update setelah pembayaran
    // - status_pesanan default 'pending' atau sesuai logika Anda
    // - payment_details kolom baru (jika Anda menambahkannya)
    $stmt_order->bind_param("idssss", // i=int, d=double, s=string
        $user_id,
        $total_amount,
        $status_pesanan_awal,
        $full_shipping_address,
        $payment_method,
        $payment_details_json // Ini akan masuk ke kolom bukti_pembayaran_url jika tidak ada payment_details
    );
    $stmt_order->execute();
    $order_id = $conn->insert_id; // Dapatkan ID pesanan yang baru dibuat
    $stmt_order->close();

    // 2. Masukkan setiap item keranjang ke tabel 'order_items'
    $stmt_order_item = $conn->prepare("
        INSERT INTO order_items (order_id, product_id, quantity, price_at_order)
        VALUES (?, ?, ?, ?)
    ");
    foreach ($cartItems as $item) {
        $stmt_order_item->bind_param("iiid", // i=int, d=double
            $order_id,
            $item['product_id'],
            $item['quantity'],
            $item['harga'] // Menggunakan 'harga' dari produk di DB saat ini sebagai price_at_order
        );
        $stmt_order_item->execute();

        // 3. Kurangi stok produk
        $stmt_update_stock = $conn->prepare("UPDATE products SET stok = stok - ? WHERE id = ?");
        $stmt_update_stock->bind_param("ii", $item['quantity'], $item['product_id']);
        $stmt_update_stock->execute();
        $stmt_update_stock->close();
    }
    $stmt_order_item->close();

    // 4. Kosongkan keranjang pengguna setelah pesanan berhasil dibuat
    $stmt_clear_cart = $conn->prepare("DELETE FROM carts WHERE user_id = ?");
    $stmt_clear_cart->bind_param("i", $user_id);
    $stmt_clear_cart->execute();
    $stmt_clear_cart->close();

    // Jika semua berhasil, commit transaksi
    $conn->commit();
    $_SESSION['message'] = '<div class="message success">Pesanan Anda berhasil ditempatkan! Nomor Pesanan: #' . $order_id . '</div>';
    header("Location: order_confirmation.php?order_id=" . $order_id); // Redirect ke halaman konfirmasi
    exit();

} catch (mysqli_sql_exception $exception) {
    // Jika ada kesalahan, rollback transaksi
    $conn->rollback();
    $_SESSION['message'] = '<div class="message error">Gagal memproses pesanan Anda. Silakan coba lagi.</div>';
    error_log("Error processing order: " . $exception->getMessage()); // Catat error untuk debugging
    header("Location: checkout.php");
    exit();
} finally {
    $conn->close();
}
?>