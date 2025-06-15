<?php
session_start();
require_once 'includes/db_connect.php';

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = '<div class="message info">Anda harus login untuk melihat riwayat pesanan.</div>';
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$orders = []; // Array untuk menyimpan semua pesanan user

// Ambil semua pesanan dari tabel 'orders' untuk user yang sedang login
$stmt = $conn->prepare("
    SELECT
        id, order_date, total_amount, status_pesanan, metode_pembayaran
    FROM
        orders
    WHERE
        user_id = ?
    ORDER BY
        order_date DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}
$stmt->close();
$conn->close();

require_once 'includes/header.php'; // Sertakan header
?>

<main>
    <div class="container">
        <section class="admin-card">
            <h2><i class="fas fa-history"></i> Riwayat Pesanan Anda</h2>

            <?php if (isset($_SESSION['message'])): ?>
                <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
            <?php endif; ?>

            <?php if (!empty($orders)): ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID Pesanan</th>
                                <th>Tanggal</th>
                                <th>Total</th>
                                <th>Metode Pembayaran</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td data-label="ID Pesanan">#<?php echo htmlspecialchars($order['id']); ?></td>
                                    <td data-label="Tanggal"><?php echo date('d M Y', strtotime($order['order_date'])); ?></td>
                                    <td data-label="Total">Rp <?php echo number_format($order['total_amount'], 0, ',', '.'); ?></td>
                                    <td data-label="Metode Pembayaran"><?php echo htmlspecialchars($order['metode_pembayaran']); ?></td>
                                    <td data-label="Status">
                                        <span class="order-status-<?php echo strtolower(htmlspecialchars($order['status_pesanan'])); ?>">
                                            <?php echo htmlspecialchars(ucwords($order['status_pesanan'])); ?>
                                        </span>
                                    </td>
                                    <td data-label="Aksi">
                                        <a href="order_confirmation.php?order_id=<?php echo htmlspecialchars($order['id']); ?>" class="btn-small">Detail</a>
                                        <?php /* BARIS BERIKUT DIHAPUS (FITUR UPLOAD BUKTI)
                                        <?php if (strtolower($order['status_pesanan']) === 'pending' && $order['metode_pembayaran'] !== 'cod'): ?>
                                            <a href="upload_payment.php?order_id=<?php echo htmlspecialchars($order['id']); ?>" class="btn-small btn-success">Upload Bukti</a>
                                        <?php endif; ?>
                                        */ ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-center">Anda belum memiliki riwayat pesanan.</p>
                <p class="text-center"><a href="products.php" class="btn-add-to-cart" style="width: auto; padding: 10px 20px;">Mulai Belanja</a></p>
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
    /* Style untuk tombol kecil */
    .btn-small {
        display: inline-block;
        padding: 5px 10px;
        background-color: var(--primary-color, #007bff);
        color: white;
        border-radius: 5px;
        text-decoration: none;
        font-size: 0.9em;
        margin-right: 5px;
        transition: background-color 0.3s ease;
    }
    .btn-small:hover {
        background-color: var(--dark-primary-color, #0056b3);
    }
    .btn-small.btn-success { /* Ini sebenarnya tidak lagi digunakan jika tombol upload bukti dihapus */
        background-color: var(--success-color, #28a745);
    }
    .btn-small.btn-success:hover { /* Ini sebenarnya tidak lagi digunakan jika tombol upload bukti dihapus */
        background-color: var(--dark-success-color, #218838);
    }
</style>