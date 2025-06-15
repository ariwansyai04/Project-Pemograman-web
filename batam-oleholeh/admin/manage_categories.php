<?php
session_start();
require_once '../includes/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$message = '';
$category_id = '';
$category_name = '';
$category_description = '';
$is_editing = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'add' || $action === 'edit') {
            $category_name = trim($_POST['nama_kategori'] ?? '');
            $category_description = trim($_POST['deskripsi'] ?? '');

            if (empty($category_name)) {
                $message = "<div class='message error'><i class='fas fa-exclamation-triangle'></i> Nama kategori tidak boleh kosong.</div>";
            } else {
                if ($action === 'add') {
                    $stmt = $conn->prepare("INSERT INTO categories (nama_kategori, deskripsi) VALUES (?, ?)");
                    $stmt->bind_param("ss", $category_name, $category_description);
                    if ($stmt->execute()) {
                        $message = "<div class='message success'><i class='fas fa-check-circle'></i> Kategori '$category_name' berhasil ditambahkan.</div>";
                        $category_name = '';
                        $category_description = '';
                    } else {
                        $message = "<div class='message error'><i class='fas fa-exclamation-triangle'></i> Gagal menambahkan kategori: " . $stmt->error . "</div>";
                    }
                } elseif ($action === 'edit') {
                    $category_id = $_POST['category_id'] ?? '';
                    if (!empty($category_id) && is_numeric($category_id)) {
                        $stmt = $conn->prepare("UPDATE categories SET nama_kategori = ?, deskripsi = ? WHERE id = ?");
                        $stmt->bind_param("ssi", $category_name, $category_description, $category_id);
                        if ($stmt->execute()) {
                            $message = "<div class='message success'><i class='fas fa-check-circle'></i> Kategori '$category_name' berhasil diperbarui.</div>";
                            $is_editing = false;
                            $category_id = '';
                            $category_name = '';
                            $category_description = '';
                        } else {
                            $message = "<div class='message error'><i class='fas fa-exclamation-triangle'></i> Gagal memperbarui kategori: " . $stmt->error . "</div>";
                        }
                    } else {
                        $message = "<div class='message error'><i class='fas fa-exclamation-triangle'></i> ID kategori tidak valid untuk edit.</div>";
                    }
                }
            }
        } elseif ($action === 'delete') {
            $category_id_to_delete = $_POST['category_id_to_delete'] ?? '';
            if (!empty($category_id_to_delete) && is_numeric($category_id_to_delete)) {
                $check_stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
                $check_stmt->bind_param("i", $category_id_to_delete);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result()->fetch_row()[0];
                $check_stmt->close();

                if ($check_result > 0) {
                    $message = "<div class='message error'><i class='fas fa-exclamation-triangle'></i> Tidak dapat menghapus kategori ini karena masih ada " . $check_result . " produk yang terkait.</div>";
                } else {
                    $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
                    $stmt->bind_param("i", $category_id_to_delete);
                    if ($stmt->execute()) {
                        $message = "<div class='message success'><i class='fas fa-check-circle'></i> Kategori berhasil dihapus.</div>";
                    } else {
                        $message = "<div class='message error'><i class='fas fa-exclamation-triangle'></i> Gagal menghapus kategori: " . $stmt->error . "</div>";
                    }
                }
            } else {
                $message = "<div class='message error'><i class='fas fa-exclamation-triangle'></i> ID kategori tidak valid untuk dihapus.</div>";
            }
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $category_id = $_GET['id'];
    $stmt = $conn->prepare("SELECT nama_kategori, deskripsi FROM categories WHERE id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $category_data = $result->fetch_assoc();
        $category_name = $category_data['nama_kategori'];
        $category_description = $category_data['deskripsi'];
        $is_editing = true;
    } else {
        $message = "<div class='message error'><i class='fas fa-exclamation-triangle'></i> Kategori tidak ditemukan.</div>";
    }
    $stmt->close();
}

$categories = [];
$sql_fetch_categories = "SELECT id, nama_kategori, deskripsi FROM categories ORDER BY nama_kategori ASC";
$result_fetch_categories = $conn->query($sql_fetch_categories);
if ($result_fetch_categories) {
    while ($row = $result_fetch_categories->fetch_assoc()) {
        $categories[] = $row;
    }
} else {
    $message = "<div class='message error'><i class='fas fa-exclamation-triangle'></i> Gagal mengambil data kategori: " . $conn->error . "</div>";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kategori - Oleh-oleh Batam Admin</title>
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
                    <li class="active"><a href="manage_categories.php"><i class="fas fa-tags"></i> Kelola Kategori</a></li>
                    <li><a href="manage_products.php"><i class="fas fa-box"></i> Kelola Produk</a></li>
                    <li><a href="manage_orders.php"><i class="fas fa-shopping-cart"></i> Kelola Pesanan</a></li>
                    <li><a href="reports.php"><i class="fas fa-chart-line"></i> Laporan</a></li>
                    <li><a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="admin-main">
        <div class="container">
            <div class="admin-card">
                <h2>Kelola Kategori</h2>

                <?php echo $message; ?>

                <div class="admin-form">
                    <h3><?php echo $is_editing ? 'Edit Kategori' : 'Tambah Kategori Baru'; ?></h3>
                    <form action="manage_categories.php" method="POST">
                        <?php if ($is_editing): ?>
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="category_id" value="<?php echo htmlspecialchars($category_id); ?>">
                        <?php else: ?>
                            <input type="hidden" name="action" value="add">
                        <?php endif; ?>

                        <div class="form-group">
                            <label for="nama_kategori">Nama Kategori:</label>
                            <input type="text" id="nama_kategori" name="nama_kategori" value="<?php echo htmlspecialchars($category_name); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="deskripsi">Deskripsi (Opsional):</label>
                            <textarea id="deskripsi" name="deskripsi"><?php echo htmlspecialchars($category_description); ?></textarea>
                        </div>
                        <div class="form-group">
                            <button type="submit"><?php echo $is_editing ? '<i class="fas fa-sync-alt"></i> Update Kategori' : '<i class="fas fa-plus-circle"></i> Tambah Kategori'; ?></button>
                            <?php if ($is_editing): ?>
                                <button type="reset" onclick="window.location.href='manage_categories.php'" class="btn-cancel"><i class="fas fa-times-circle"></i> Batal Edit</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <h3 class="mt-40">Daftar Kategori</h3>
                <?php if (empty($categories)): ?>
                    <p>Belum ada kategori yang ditambahkan.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nama Kategori</th>
                                    <th>Deskripsi</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <td data-label="ID"><?php echo htmlspecialchars($cat['id']); ?></td>
                                        <td data-label="Nama Kategori"><?php echo htmlspecialchars($cat['nama_kategori']); ?></td>
                                        <td data-label="Deskripsi"><?php echo htmlspecialchars($cat['deskripsi']); ?></td>
                                        <td data-label="Aksi" class="action-buttons">
                                            <a href="manage_categories.php?action=edit&id=<?php echo htmlspecialchars($cat['id']); ?>" class="edit-btn"><i class="fas fa-edit"></i> Edit</a>
                                            <form action="manage_categories.php" method="POST" style="display:inline-block;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus kategori ini? Tindakan ini tidak dapat dibatalkan.');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="category_id_to_delete" value="<?php echo htmlspecialchars($cat['id']); ?>">
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
        </div>
    </main>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> Oleh-oleh Khas Batam. Admin Panel.</p>
    </footer>

    <?php $conn->close(); ?>
</body>
</html>