<?php
// admin/manage_order_detail.php
session_start();
require_once '../includes/db_connect.php';

// Proteksi admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$order_id = $_GET['id'] ?? 0;
$order_data = null;
$order_items = [];
$message = '';
// Sesuaikan dengan ENUM status_pesanan di tabel orders Anda
$order_status_options = ['pending', 'paid', 'shipped', 'delivered', 'cancelled'];

if (!empty($order_id) && is_numeric($order_id)) {
    // Ambil data pesanan utama
    $stmt_order = $conn->prepare("SELECT
                                        o.id AS order_id,
                                        u.username,
                                        u.nama_lengkap,
                                        u.email,
                                        u.no_telepon,
                                        u.alamat AS user_alamat,
                                        o.order_date,
                                        o.total_amount,
                                        o.status_pesanan,
                                        o.alamat_pengiriman,
                                        o.metode_pembayaran,
                                        o.tanggal_pembayaran,
                                        o.bukti_pembayaran_url
                                        FROM orders o
                                        JOIN users u ON o.user_id = u.id
                                        WHERE o.id = ?");
    $stmt_order->bind_param("i", $order_id);
    $stmt_order->execute();
    $result_order = $stmt_order->get_result();
    if ($result_order->num_rows === 1) {
        $order_data = $result_order->fetch_assoc();
    } else {
        $message = "<div class='message error'>Pesanan tidak ditemukan.</div>";
    }
    $stmt_order->close();

    // Ambil item-item pesanan
    $stmt_items = $conn->prepare("SELECT
                                        oi.quantity,
                                        oi.price_at_order,
                                        p.nama_produk,
                                        p.gambar_url
                                        FROM order_items oi
                                        JOIN products p ON oi.product_id = p.id
                                        WHERE oi.order_id = ?");
    $stmt_items->bind_param("i", $order_id);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();
    while ($row_item = $result_items->fetch_assoc()) {
        $order_items[] = $row_item;
    }
    $stmt_items->close();

} else {
    $message = "<div class='message error'>ID Pesanan tidak valid.</div>";
}

// --- Proses Update Status dari Halaman Detail ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status_detail') {
    $new_status = $_POST['new_status'] ?? '';
    if ($order_data && in_array($new_status, $order_status_options)) {
        $stmt_update = $conn->prepare("UPDATE orders SET status_pesanan = ? WHERE id = ?");
        $stmt_update->bind_param("si", $new_status, $order_id);
        if ($stmt_update->execute()) {
            $order_data['status_pesanan'] = $new_status; // Update status di variabel agar langsung terlihat
            $message = "<div class='message success'>Status pesanan #$order_id berhasil diperbarui menjadi '" . htmlspecialchars($new_status) . "'.</div>";
        } else {
            $message = "<div class='message error'>Gagal memperbarui status: " . $stmt_update->error . "</div>";
        }
        $stmt_update->close();
    } else {
        $message = "<div class='message error'>Input status tidak valid.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan #<?php echo htmlspecialchars($order_id); ?> - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <header class="admin-header">
        <div class="container">
            <h1>Oleh-oleh Batam Admin</h1>
            <nav class="admin-nav">
                <ul>
                    <li><a href="../index.php"><i class="fas fa-home"></i> Situs Utama</a></li>
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="manage_categories.php"><i class="fas fa-tags"></i> Kelola Kategori</a></li>
                    <li><a href="manage_products.php"><i class="fas fa-box-open"></i> Kelola Produk</a></li>
                    <li class="active"><a href="manage_orders.php"><i class="fas fa-shopping-cart"></i> Kelola Pesanan</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-line"></i> Laporan</a></li>
                    <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="admin-main container">
        <h2>Detail Pesanan #<?php echo htmlspecialchars($order_id); ?></h2>

        <?php echo $message; ?>

        <?php if ($order_data): ?>
            <div class="admin-card order-details-card">
                <h3>Informasi Pesanan</h3>
                <div class="detail-grid">
                    <p><strong>ID Pesanan:</strong> <span>#<?php echo htmlspecialchars($order_data['order_id']); ?></span></p>
                    <p><strong>Pelanggan:</strong> <span><?php echo htmlspecialchars($order_data['nama_lengkap'] ?? $order_data['username']); ?></span></p>
                    <p><strong>Email Pelanggan:</strong> <span><?php echo htmlspecialchars($order_data['email']); ?></span></p>
                    <p><strong>No. Telepon:</strong> <span><?php echo htmlspecialchars($order_data['no_telepon'] ?? 'N/A'); ?></span></p>
                    <p><strong>Tanggal Pesanan:</strong> <span><?php echo date('d F Y H:i', strtotime($order_data['order_date'])); ?></span></p>
                    <p><strong>Total Harga:</strong> <span class="total-amount">Rp <?php echo number_format($order_data['total_amount'], 0, ',', '.'); ?></span></p>
                    <p><strong>Status:</strong>
                        <span class="status-badge status-<?php echo strtolower($order_data['status_pesanan']); ?>">
                            <?php echo htmlspecialchars(ucfirst($order_data['status_pesanan'])); ?>
                        </span>
                    </p>
                    <p><strong>Metode Pembayaran:</strong> <span><?php echo htmlspecialchars($order_data['metode_pembayaran']); ?></span></p>
                    <?php if ($order_data['tanggal_pembayaran']): ?>
                        <p><strong>Tanggal Pembayaran:</strong> <span><?php echo date('d F Y H:i', strtotime($order_data['tanggal_pembayaran'])); ?></span></p>
                    <?php endif; ?>
                    <?php if ($order_data['bukti_pembayaran_url']): ?>
                        <p><strong>Bukti Pembayaran:</strong> <a href="../<?php echo htmlspecialchars($order_data['bukti_pembayaran_url']); ?>" target="_blank" class="view-proof-link"><i class="fas fa-receipt"></i> Lihat Bukti</a></p>
                    <?php endif; ?>
                    <div class="full-width">
                        <p><strong>Alamat Pengiriman:</strong></p>
                        <p class="address-box"><?php echo nl2br(htmlspecialchars($order_data['alamat_pengiriman'])); ?></p>
                    </div>
                </div>
                <div class="form-group status-update-section">
                    <h4><i class="fas fa-sync-alt"></i> Ubah Status Pesanan:</h4>
                    <form action="manage_order_detail.php?id=<?php echo htmlspecialchars($order_id); ?>" method="POST" class="form-inline">
                        <input type="hidden" name="action" value="update_status_detail">
                        <select name="new_status" class="status-select">
                            <?php foreach ($order_status_options as $status_option): ?>
                                <option value="<?php echo htmlspecialchars($status_option); ?>"
                                    <?php echo (strtolower($status_option) === strtolower($order_data['status_pesanan'])) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ucfirst($status_option)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="submit-btn"><i class="fas fa-check-circle"></i> Update Status</button>
                    </form>
                </div>
            </div>

            <div class="admin-card">
                <h3>Item Pesanan</h3>
                <?php if (empty($order_items)): ?>
                    <p class="no-items-message">Tidak ada item dalam pesanan ini.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Gambar</th>
                                    <th>Nama Produk</th>
                                    <th>Kuantitas</th>
                                    <th>Harga Satuan</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                    <tr>
                                        <td data-label="Gambar"><img src="../<?php echo htmlspecialchars($item['gambar_url']); ?>" alt="<?php echo htmlspecialchars($item['nama_produk']); ?>"></td>
                                        <td data-label="Nama Produk"><?php echo htmlspecialchars($item['nama_produk']); ?></td>
                                        <td data-label="Kuantitas"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                        <td data-label="Harga Satuan">Rp <?php echo number_format($item['price_at_order'], 0, ',', '.'); ?></td>
                                        <td data-label="Subtotal">Rp <?php echo number_format($item['quantity'] * $item['price_at_order'], 0, ',', '.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="admin-actions-bottom">
                <a href="manage_orders.php" class="btn-back"><i class="fas fa-arrow-alt-circle-left"></i> Kembali ke Daftar Pesanan</a>
            </div>

        <?php else: ?>
            <div class="admin-card">
                <p class="no-items-message">Data pesanan tidak dapat dimuat atau tidak ditemukan.</p>
                <div class="admin-actions-bottom">
                    <a href="manage_orders.php" class="btn-back"><i class="fas fa-arrow-alt-circle-left"></i> Kembali ke Daftar Pesanan</a>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> Oleh-oleh Khas Batam. Admin Panel.</p>
    </footer>

    <?php $conn->close(); ?>
</body>
</html>