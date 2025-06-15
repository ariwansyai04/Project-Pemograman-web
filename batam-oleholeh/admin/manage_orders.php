<?php
// admin/manage_orders.php
session_start();
require_once '../includes/db_connect.php';

// Proteksi admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$message = '';
// Sesuaikan dengan ENUM status_pesanan di tabel orders Anda
$order_status_options = ['pending', 'paid', 'shipped', 'delivered', 'cancelled'];

// --- Proses Update Status Pesanan ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $order_id = $_POST['order_id'] ?? 0;
    $new_status = $_POST['new_status'] ?? '';

    if (!empty($order_id) && is_numeric($order_id) && in_array($new_status, $order_status_options)) {
        // Perbaikan: gunakan status_pesanan
        $stmt = $conn->prepare("UPDATE orders SET status_pesanan = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $order_id);
        if ($stmt->execute()) {
            $message = "<div class='message success'>Status pesanan #$order_id berhasil diperbarui menjadi '" . htmlspecialchars($new_status) . "'.</div>";
        } else {
            $message = "<div class='message error'>Gagal memperbarui status pesanan: " . htmlspecialchars($stmt->error) . "</div>";
        }
        $stmt->close();
    } else {
        $message = "<div class='message error'>Input tidak valid untuk pembaruan status.</div>";
    }
}

// --- Ambil Semua Pesanan untuk Ditampilkan ---
$orders = [];
$sql_fetch_orders = "SELECT
                        o.id AS order_id,
                        u.username,
                        u.nama_lengkap,
                        o.order_date,
                        o.total_amount,
                        o.status_pesanan,
                        o.alamat_pengiriman,
                        o.metode_pembayaran
                    FROM orders o
                    JOIN users u ON o.user_id = u.id
                    ORDER BY o.order_date DESC"; // Urutkan dari yang terbaru

$result_fetch_orders = $conn->query($sql_fetch_orders);
if ($result_fetch_orders) {
    while ($row = $result_fetch_orders->fetch_assoc()) {
        $orders[] = $row;
    }
} else {
    $message .= "<div class='message error'>Gagal mengambil data pesanan: " . htmlspecialchars($conn->error) . "</div>";
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pesanan - Admin</title>
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
        <h2>Kelola Pesanan</h2>

        <?php echo $message; // Menampilkan pesan sukses/error ?>

        <div class="admin-card">
            <h3>Daftar Pesanan</h3>
            <?php if (empty($orders)): ?>
                <p class="no-items-message">Belum ada pesanan yang masuk.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID Pesanan</th>
                                <th>Pelanggan</th>
                                <th>Tanggal Pesanan</th>
                                <th>Total (Rp)</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td data-label="ID Pesanan">#<?php echo htmlspecialchars($order['order_id']); ?></td>
                                    <td data-label="Pelanggan"><?php echo htmlspecialchars($order['nama_lengkap'] ?? $order['username']); ?></td>
                                    <td data-label="Tanggal Pesanan"><?php echo date('d M Y H:i', strtotime($order['order_date'])); ?></td>
                                    <td data-label="Total (Rp)">Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></td>
                                    <td data-label="Status">
                                        <span class="status-badge status-<?php echo strtolower($order['status_pesanan']); ?>">
                                            <?php echo htmlspecialchars(ucfirst($order['status_pesanan'])); ?>
                                        </span>
                                    </td>
                                    <td data-label="Aksi" class="action-buttons">
                                        <a href="manage_order_detail.php?id=<?php echo htmlspecialchars($order['order_id']); ?>" class="view-btn"><i class="fas fa-eye"></i> Detail</a>
                                        <form action="manage_orders.php" method="POST" class="form-inline status-update-form">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['order_id']); ?>">
                                            <select name="new_status" class="status-select">
                                                <?php foreach ($order_status_options as $status_option): ?>
                                                    <option value="<?php echo htmlspecialchars($status_option); ?>"
                                                        <?php echo (strtolower($status_option) === strtolower($order['status_pesanan'])) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars(ucfirst($status_option)); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="submit-btn update-status-btn"><i class="fas fa-sync-alt"></i> Update</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> Oleh-oleh Khas Batam. Admin Panel.</p>
    </footer>

    <?php $conn->close(); ?>
</body>
</html>