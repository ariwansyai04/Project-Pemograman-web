<?php
session_start();
require_once 'includes/db_connect.php';

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = '<div class="message info">Anda harus login untuk melihat konfirmasi pesanan.</div>';
    header("Location: login.php");
    exit();
}

$order_id = $_GET['order_id'] ?? 0;
$user_id = $_SESSION['user_id'];
$order_details = null;
$order_items = [];
$message = ''; // Untuk menampilkan pesan dari session

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Hapus pesan setelah ditampilkan
}

// Validasi order_id
if ($order_id <= 0) {
    $_SESSION['message'] = '<div class="message error">Nomor pesanan tidak valid.</div>';
    header("Location: products.php"); // Atau halaman lain yang relevan
    exit();
}

// Ambil detail pesanan dari tabel 'orders'
$stmt_order = $conn->prepare("
    SELECT
        id, order_date, total_amount, status_pesanan,
        alamat_pengiriman, metode_pembayaran, payment_details
    FROM
        orders
    WHERE
        id = ? AND user_id = ?
");
$stmt_order->bind_param("ii", $order_id, $user_id);
$stmt_order->execute();
$result_order = $stmt_order->get_result();

if ($result_order->num_rows > 0) {
    $order_details = $result_order->fetch_assoc();
} else {
    $_SESSION['message'] = '<div class="message error">Pesanan tidak ditemukan atau Anda tidak memiliki akses ke pesanan ini.</div>';
    header("Location: products.php"); // Atau halaman daftar pesanan
    exit();
}
$stmt_order->close();

// Ambil item-item pesanan dari tabel 'order_items'
// PERBAIKAN: Mengambil nama_produk dari tabel products (p.nama_produk)
$stmt_items = $conn->prepare("
    SELECT
        p.nama_produk AS product_name,  -- Mengambil nama_produk dari tabel 'products' dan aliaskan sebagai 'product_name'
        oi.quantity,
        oi.price_at_order,
        p.gambar_url
    FROM
        order_items oi
    JOIN
        products p ON oi.product_id = p.id
    WHERE
        oi.order_id = ?
");
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$result_items = $stmt_items->get_result();

if ($result_items->num_rows > 0) {
    while ($row = $result_items->fetch_assoc()) {
        $order_items[] = $row;
    }
}
$stmt_items->close();

$conn->close(); // Tutup koneksi database setelah semua query

require_once 'includes/header.php'; // Sertakan header
?>

<main>
    <div class="container">
        <section class="admin-card">
            <h2><i class="fas fa-check-circle"></i> Konfirmasi Pesanan</h2>
            <?php echo $message; ?>

            <?php if ($order_details): ?>
                <div class="order-summary">
                    <h3>Detail Pesanan #<?php echo htmlspecialchars($order_details['id']); ?></h3>
                    <p><strong>Tanggal Pesanan:</strong> <?php echo date('d M Y H:i', strtotime($order_details['order_date'])); ?></p>
                    <p><strong>Total Pembayaran:</strong> Rp <?php echo number_format($order_details['total_amount'], 0, ',', '.'); ?></p>
                    <p><strong>Status Pesanan:</strong> <span class="order-status-<?php echo strtolower(htmlspecialchars($order_details['status_pesanan'])); ?>">
                        <?php echo htmlspecialchars(ucwords($order_details['status_pesanan'])); ?>
                    </span></p>
                    <p><strong>Metode Pembayaran:</strong> <?php echo htmlspecialchars($order_details['metode_pembayaran']); ?></p>

                    <h4>Informasi Pengiriman:</h4>
                    <p style="white-space: pre-wrap;"><?php echo htmlspecialchars($order_details['alamat_pengiriman']); ?></p>
                </div>

                <div class="table-responsive mt-30">
                    <h3>Item Pesanan:</h3>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Harga Satuan</th>
                                <th>Kuantitas</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order_items as $item): ?>
                                <tr>
                                    <td data-label="Produk">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <img src="<?php echo htmlspecialchars($item['gambar_url']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">
                                            <?php echo htmlspecialchars($item['product_name']); ?>
                                        </div>
                                    </td>
                                    <td data-label="Harga Satuan">Rp <?php echo number_format($item['price_at_order'], 0, ',', '.'); ?></td>
                                    <td data-label="Kuantitas"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                    <td data-label="Subtotal">Rp <?php echo number_format($item['price_at_order'] * $item['quantity'], 0, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" style="text-align: right;">Total Item:</th>
                                <th>Rp <?php echo number_format(array_sum(array_map(fn($item) => $item['price_at_order'] * $item['quantity'], $order_items)), 0, ',', '.'); ?></th>
                            </tr>
                            <tr>
                                <th colspan="3" style="text-align: right;">Biaya Pengiriman:</th>
                                <th>Rp <?php echo number_format(15000, 0, ',', '.'); ?></th>
                            </tr>
                            <tr>
                                <th colspan="3" style="text-align: right; font-size: 1.2rem; color: var(--primary-color);">Total Keseluruhan:</th>
                                <th style="font-size: 1.2rem; color: var(--accent-color);">Rp <?php echo number_format($order_details['total_amount'], 0, ',', '.'); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="mt-30 text-center">
                    <p>Terima kasih telah berbelanja di Oleh-oleh Batam!</p>
                    <?php if ($order_details['metode_pembayaran'] !== 'cod' && strtolower($order_details['status_pesanan']) === 'pending'): ?>
                        <p>Silakan selesaikan pembayaran Anda. Instruksi pembayaran akan dikirim ke email Anda.</p>
                        <?php endif; ?>
                    <a href="products.php" class="btn-add-to-cart" style="width: auto; padding: 10px 20px; background-color: var(--secondary-color);">Lanjut Belanja</a>
                    <a href="orders.php" class="btn-add-to-cart" style="width: auto; padding: 10px 20px;">Lihat Riwayat Pesanan</a>
                </div>

            <?php else: ?>
                <p class="text-center">Tidak dapat menampilkan detail pesanan. Silakan coba lagi atau <a href="contact.php">hubungi kami</a>.</p>
            <?php endif; ?>
        </section>
    </div>
</main>

<?php
require_once 'includes/footer.php'; // Sertakan footer
?>

<style>
    /* Styling untuk status pesanan */
    .order-status-pending { color: orange; font-weight: bold; }
    .order-status-paid { color: green; font-weight: bold; }
    .order-status-shipped { color: blue; font-weight: bold; }
    .order-status-delivered { color: purple; font-weight: bold; }
    .order-status-cancelled { color: red; font-weight: bold; }
</style>