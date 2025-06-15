<?php
session_start();
require_once '../includes/db_connect.php';

// Cek apakah user sudah login dan apakah role-nya admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Fetch dashboard data
$total_products = 0;
$total_orders = 0;
$total_users = 0;

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM products");
$stmt->execute();
$result = $stmt->get_result();
$total_products = $result->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM orders");
$stmt->execute();
$result = $stmt->get_result();
$total_orders = $result->fetch_assoc()['total'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM users");
$stmt->execute();
$result = $stmt->get_result();
$total_users = $result->fetch_assoc()['total'];
$stmt->close();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Oleh-oleh Batam</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    </head>
<body>
    <header class="admin-header">
        <div class="container">
            <h1>Oleh-oleh Batam Admin</h1>
            <nav class="admin-nav">
                <ul>
                    <li><a href="../index.php"><i class="fas fa-home"></i> Situs Utama</a></li>
                    <li class="active"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="manage_categories.php"><i class="fas fa-tags"></i> Kelola Kategori</a></li>
                    <li><a href="manage_products.php"><i class="fas fa-box"></i> Kelola Produk</a></li>
                    <li><a href="manage_orders.php"><i class="fas fa-clipboard-list"></i> Kelola Pesanan</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Laporan</a></li>
                    <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout (<?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>)</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="admin-main container">
        <section class="admin-card">
            <h2>Ringkasan Umum</h2>
            <div class="dashboard-cards">
                <div class="dashboard-card-item">
                    <h3>Total Produk</h3>
                    <div class="value"><?php echo $total_products; ?></div>
                </div>
                <div class="dashboard-card-item">
                    <h3>Total Pesanan</h3>
                    <div class="value"><?php echo $total_orders; ?></div>
                </div>
                <div class="dashboard-card-item">
                    <h3>Total Pengguna</h3>
                    <div class="value"><?php echo $total_users; ?></div>
                </div>
                </div>
        </section>
        
        <section class="admin-card">
            <h2>Produk Stok Rendah</h2>
            <?php
            $low_stock_products = [];
            $stmt = $conn->prepare("SELECT id, nama_produk, stok FROM products WHERE stok <= 10 ORDER BY stok ASC LIMIT 5");
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $low_stock_products[] = $row;
                }
            }
            $stmt->close();
            ?>

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