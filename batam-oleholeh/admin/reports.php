<?php
session_start();
require_once '../includes/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$low_stock_products = [];
$sql = "SELECT id, nama_produk, stok FROM products WHERE stok <= 10 ORDER BY stok ASC";

if ($stmt = $conn->prepare($sql)) {
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $low_stock_products[] = $row;
        }
    }
    $stmt->close();
} else {
    error_log("Error preparing statement for low stock products report: " . $conn->error);
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Produk - Oleh-oleh Batam Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
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
                    <li><a href="manage_products.php"><i class="fas fa-box"></i> Kelola Produk</a></li>
                    <li><a href="manage_orders.php"><i class="fas fa-clipboard-list"></i> Kelola Pesanan</a></li>
                    <li class="active"><a href="reports.php"><i class="fas fa-chart-bar"></i> Laporan</a></li>
                    <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout (<?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>)</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="admin-main container">
        <section class="admin-card">
            <h2>Laporan Produk</h2>
            <h3>Produk dengan Stok Rendah (<= 10)</h3>

            <?php if (!empty($low_stock_products)): ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID Produk</th>
                                <th>Nama Produk</th>
                                <th>Stok</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($low_stock_products as $product): ?>
                                <tr>
                                    <td data-label="ID Produk:"><?php echo htmlspecialchars($product['id']); ?></td>
                                    <td data-label="Nama Produk:"><?php echo htmlspecialchars($product['nama_produk']); ?></td>
                                    <td data-label="Stok:"><span class="low-stock"><?php echo htmlspecialchars($product['stok']); ?></span></td>
                                    <td data-label="Aksi:" class="action-buttons">
                                        <a href="manage_products.php?edit_id=<?php echo $product['id']; ?>" class="edit-btn"><i class="fas fa-edit"></i> Edit</a>
                                        <a href="../product_detail.php?id=<?php echo $product['id']; ?>" class="view-btn" target="_blank"><i class="fas fa-eye"></i> Lihat</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>Tidak ada produk dengan stok rendah saat ini.</p>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> Oleh-oleh Khas Batam. Admin Panel.</p>
    </footer>

    <script src="../assets/js/script.js"></script>
    <?php $conn->close(); ?>
</body>
</html>