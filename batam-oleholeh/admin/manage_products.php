<?php
// admin/manage_products.php
session_start();
require_once '../includes/db_connect.php';

// Proteksi admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$message = '';

// --- Proses Tambah/Edit Produk (kode ini tetap sama) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    // ... (kode untuk tambah dan edit produk Anda sebelumnya) ...

    if ($_POST['action'] === 'add_product' || $_POST['action'] === 'edit_product') {
        $nama_produk = $_POST['nama_produk'] ?? '';
        $deskripsi_produk = $_POST['deskripsi_produk'] ?? '';
        $harga = $_POST['harga'] ?? 0;
        $stok = $_POST['stok'] ?? 0;
        $category_id = $_POST['category_id'] ?? null;
        $product_id = $_POST['product_id'] ?? 0; // Hanya ada saat edit

        // Penanganan upload gambar
        $gambar_url = '';
        $target_dir = "../uploads/products/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        // Jika ada file yang diupload (untuk tambah atau update gambar saat edit)
        if (isset($_FILES['gambar_produk']) && $_FILES['gambar_produk']['error'] == UPLOAD_ERR_OK) {
            $file_tmp_name = $_FILES['gambar_produk']['tmp_name'];
            $file_name = uniqid() . '_' . basename($_FILES['gambar_produk']['name']);
            $target_file = $target_dir . $file_name;

            if (move_uploaded_file($file_tmp_name, $target_file)) {
                $gambar_url = "uploads/products/" . $file_name;
            } else {
                $message = "<div class='message error'>Gagal mengupload gambar.</div>";
                // Jika edit dan upload gambar gagal, gunakan gambar lama jika ada
                if ($_POST['action'] === 'edit_product' && !empty($_POST['current_gambar_url'])) {
                    $gambar_url = $_POST['current_gambar_url'];
                }
            }
        } else {
            // Jika tidak ada upload gambar baru saat edit, gunakan gambar lama
            if ($_POST['action'] === 'edit_product' && isset($_POST['current_gambar_url'])) {
                $gambar_url = $_POST['current_gambar_url'];
            }
        }

        if ($_POST['action'] === 'add_product') {
            $stmt = $conn->prepare("INSERT INTO products (nama_produk, deskripsi_produk, harga, stok, gambar_url, category_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdiss", $nama_produk, $deskripsi_produk, $harga, $stok, $gambar_url, $category_id);
            if ($stmt->execute()) {
                $message = "<div class='message success'>Produk baru berhasil ditambahkan!</div>";
            } else {
                $message = "<div class='message error'>Gagal menambahkan produk: " . htmlspecialchars($stmt->error) . "</div>";
            }
            $stmt->close();
        } elseif ($_POST['action'] === 'edit_product') {
            // Ambil URL gambar lama untuk dihapus jika ada gambar baru
            $old_gambar_url = '';
            if (isset($_POST['current_gambar_url']) && $_FILES['gambar_produk']['error'] == UPLOAD_ERR_OK) {
                // Hanya ambil jika ada upload gambar baru
                $stmt_get_old_image = $conn->prepare("SELECT gambar_url FROM products WHERE id = ?");
                $stmt_get_old_image->bind_param("i", $product_id);
                $stmt_get_old_image->execute();
                $result_old_image = $stmt_get_old_image->get_result();
                if ($row_old_image = $result_old_image->fetch_assoc()) {
                    $old_gambar_url = $row_old_image['gambar_url'];
                }
                $stmt_get_old_image->close();
            }

            // Perbarui query UPDATE untuk gambar
            if ($gambar_url) { // Jika ada gambar baru atau gambar lama yang valid
                $stmt = $conn->prepare("UPDATE products SET nama_produk = ?, deskripsi_produk = ?, harga = ?, stok = ?, gambar_url = ?, category_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->bind_param("ssdissi", $nama_produk, $deskripsi_produk, $harga, $stok, $gambar_url, $category_id, $product_id);
            } else { // Jika tidak ada gambar baru atau gambar lama yang valid (misal, dihapus atau upload gagal dan tidak ada gambar lama)
                $stmt = $conn->prepare("UPDATE products SET nama_produk = ?, deskripsi_produk = ?, harga = ?, stok = ?, category_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->bind_param("ssdii", $nama_produk, $deskripsi_produk, $harga, $stok, $category_id, $product_id);
            }

            if ($stmt->execute()) {
                // Jika produk berhasil diperbarui dan ada gambar lama yang berbeda dengan gambar baru, hapus gambar lama
                if (!empty($old_gambar_url) && $old_gambar_url !== $gambar_url && file_exists("../" . $old_gambar_url)) {
                    unlink("../" . $old_gambar_url);
                }
                $message = "<div class='message success'>Produk #$product_id berhasil diperbarui.</div>";
            } else {
                $message = "<div class='message error'>Gagal memperbarui produk: " . htmlspecialchars($stmt->error) . "</div>";
            }
            $stmt->close();
        }
    }
}

// --- Proses Hapus Produk Secara Permanen ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_product') {
    $product_id_to_delete = $_POST['product_id'] ?? 0;

    if (!empty($product_id_to_delete) && is_numeric($product_id_to_delete)) {
        // Mulai transaksi
        $conn->begin_transaction();

        try {
            // 1. Ambil gambar produk untuk dihapus dari server sebelum menghapus record dari DB
            $stmt_get_image = $conn->prepare("SELECT gambar_url FROM products WHERE id = ?");
            $stmt_get_image->bind_param("i", $product_id_to_delete);
            $stmt_get_image->execute();
            $result_image = $stmt_get_image->get_result();
            $gambar_url = null;
            if ($row_image = $result_image->fetch_assoc()) {
                $gambar_url = $row_image['gambar_url'];
            }
            $stmt_get_image->close();

            // 2. Hapus entri terkait di order_items
            // Ini akan menghapus data riwayat pesanan yang terkait dengan produk ini.
            $stmt_delete_order_items = $conn->prepare("DELETE FROM order_items WHERE product_id = ?");
            $stmt_delete_order_items->bind_param("i", $product_id_to_delete);
            if (!$stmt_delete_order_items->execute()) {
                throw new Exception("Gagal menghapus item pesanan terkait: " . $stmt_delete_order_items->error);
            }
            $stmt_delete_order_items->close();

            // 3. Hapus produk dari tabel products
            $stmt_delete_product = $conn->prepare("DELETE FROM products WHERE id = ?");
            $stmt_delete_product->bind_param("i", $product_id_to_delete);
            if ($stmt_delete_product->execute()) {
                // Jika produk berhasil dihapus dari DB, hapus juga file gambar dari server
                if ($gambar_url && file_exists("../" . $gambar_url)) {
                    unlink("../" . $gambar_url);
                }
                $message = "<div class='message success'>Produk berhasil dihapus secara permanen.</div>";
                $conn->commit(); // Commit transaksi jika semua berhasil
            } else {
                throw new Exception("Gagal menghapus produk: " . $stmt_delete_product->error);
            }
            $stmt_delete_product->close();

        } catch (Exception $e) {
            $conn->rollback(); // Rollback transaksi jika ada error
            $message = "<div class='message error'>Terjadi kesalahan saat menghapus produk: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        $message = "<div class='message error'>ID Produk tidak valid untuk penghapusan.</div>";
    }
}

// --- Ambil Kategori untuk Dropdown (Tidak ada perubahan) ---
$categories = [];
$sql_categories = "SELECT id, nama_kategori FROM categories ORDER BY nama_kategori ASC";
$result_categories = $conn->query($sql_categories);
if ($result_categories) {
    while ($row = $result_categories->fetch_assoc()) {
        $categories[] = $row;
    }
} else {
    $message .= "<div class='message error'>Gagal mengambil data kategori: " . htmlspecialchars($conn->error) . "</div>";
}

// Untuk mode edit, ambil data produk yang akan diedit
$product_data = null;
if (isset($_GET['edit']) && $_GET['edit'] == 'true' && isset($_GET['id'])) {
    $edit_product_id = $_GET['id'];
    $stmt_edit = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt_edit->bind_param("i", $edit_product_id);
    $stmt_edit->execute();
    $result_edit = $stmt_edit->get_result();
    if ($result_edit->num_rows === 1) {
        $product_data = $result_edit->fetch_assoc();
    } else {
        $message = "<div class='message error'>Produk tidak ditemukan untuk diedit.</div>";
    }
    $stmt_edit->close();
}


// --- Ambil Semua Produk untuk Ditampilkan ---
// Jika Anda menggunakan soft delete sebelumnya, HAPUS kondisi `WHERE p.is_active = TRUE` di sini
$products = [];
$sql_fetch_products = "SELECT p.id, p.nama_produk, p.harga, p.stok, p.gambar_url, c.nama_kategori
                       FROM products p
                       JOIN categories c ON p.category_id = c.id
                       ORDER BY p.id DESC"; // Tampilkan semua produk, aktif atau tidak aktif

$result_fetch_products = $conn->query($sql_fetch_products);
if ($result_fetch_products) {
    while ($row = $result_fetch_products->fetch_assoc()) {
        $products[] = $row;
    }
} else {
    $message .= "<div class='message error'>Gagal mengambil data produk: " . htmlspecialchars($conn->error) . "</div>";
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Produk - Admin</title>
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
                    <li class="active"><a href="manage_products.php"><i class="fas fa-box-open"></i> Kelola Produk</a></li>
                    <li><a href="manage_orders.php"><i class="fas fa-shopping-cart"></i> Kelola Pesanan</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-line"></i> Laporan</a></li>
                    <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="admin-main container">
        <h2>Kelola Produk</h2>

        <?php echo $message; // Menampilkan pesan sukses/error ?>

        <div class="admin-card admin-form">
            <h3><?php echo (isset($_GET['edit']) && $_GET['edit'] == 'true') ? 'Edit Produk' : 'Tambah Produk Baru'; ?></h3>
            <form action="manage_products.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?php echo (isset($_GET['edit']) && $_GET['edit'] == 'true') ? 'edit_product' : 'add_product'; ?>">
                <?php if (isset($_GET['edit']) && $_GET['edit'] == 'true' && $product_data): ?>
                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product_data['id']); ?>">
                    <input type="hidden" name="current_gambar_url" value="<?php echo htmlspecialchars($product_data['gambar_url'] ?? ''); ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="nama_produk">Nama Produk:</label>
                    <input type="text" id="nama_produk" name="nama_produk" value="<?php echo htmlspecialchars($product_data['nama_produk'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="deskripsi_produk">Deskripsi Produk:</label>
                    <textarea id="deskripsi_produk" name="deskripsi_produk" rows="4"><?php echo htmlspecialchars($product_data['deskripsi_produk'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="harga">Harga (Rp):</label>
                    <input type="number" id="harga" name="harga" step="0.01" value="<?php echo htmlspecialchars($product_data['harga'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="stok">Stok:</label>
                    <input type="number" id="stok" name="stok" value="<?php echo htmlspecialchars($product_data['stok'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="category_id">Kategori:</label>
                    <select id="category_id" name="category_id" required>
                        <option value="">-- Pilih Kategori --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['id']); ?>"
                                <?php echo (isset($product_data['category_id']) && $product_data['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['nama_kategori']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="gambar_produk">Gambar Produk (Opsional, kosongkan jika tidak ingin mengubah):</label>
                    <input type="file" id="gambar_produk" name="gambar_produk" accept="image/*">
                    <?php if (isset($product_data['gambar_url']) && $product_data['gambar_url']): ?>
                        <p>Gambar saat ini: <img src="../<?php echo htmlspecialchars($product_data['gambar_url']); ?>" alt="Gambar Produk" style="width: 100px; height: auto; margin-top: 10px;"></p>
                    <?php endif; ?>
                </div>
                <button type="submit" class="submit-btn">
                    <i class="fas fa-<?php echo (isset($_GET['edit']) && $_GET['edit'] == 'true') ? 'save' : 'plus'; ?>"></i>
                    <?php echo (isset($_GET['edit']) && $_GET['edit'] == 'true') ? 'Simpan Perubahan' : 'Tambah Produk'; ?>
                </button>
                <?php if (isset($_GET['edit']) && $_GET['edit'] == 'true'): ?>
                    <a href="manage_products.php" class="btn-cancel"><i class="fas fa-times-circle"></i> Batal</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="admin-card">
            <h3>Daftar Produk</h3>
            <?php if (empty($products)): ?>
                <p class="no-items-message">Belum ada produk yang ditambahkan.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Gambar</th>
                                <th>Nama Produk</th>
                                <th>Harga</th>
                                <th>Stok</th>
                                <th>Kategori</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td data-label="ID"><?php echo htmlspecialchars($product['id']); ?></td>
                                    <td data-label="Gambar">
                                        <?php if ($product['gambar_url']): ?>
                                            <img src="../<?php echo htmlspecialchars($product['gambar_url']); ?>" alt="<?php echo htmlspecialchars($product['nama_produk']); ?>">
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Nama Produk"><?php echo htmlspecialchars($product['nama_produk']); ?></td>
                                    <td data-label="Harga">Rp <?php echo number_format($product['harga'], 0, ',', '.'); ?></td>
                                    <td data-label="Stok" class="stock-<?php echo ($product['stok'] > 0) ? 'available' : 'empty'; ?>"><?php echo htmlspecialchars($product['stok']); ?></td>
                                    <td data-label="Kategori"><?php echo htmlspecialchars($product['nama_kategori']); ?></td>
                                    <td data-label="Aksi" class="action-buttons">
                                        <a href="manage_products.php?edit=true&id=<?php echo htmlspecialchars($product['id']); ?>" class="edit-btn"><i class="fas fa-edit"></i> Edit</a>
                                        <form action="manage_products.php" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin MENGHAPUS produk ini secara PERMANEN? Ini akan juga menghapus semua riwayat pesanan yang terkait dengan produk ini.');" class="form-inline">
                                            <input type="hidden" name="action" value="delete_product">
                                            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['id']); ?>">
                                            <button type="submit" class="delete-btn"><i class="fas fa-trash-alt"></i> Hapus</button>
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