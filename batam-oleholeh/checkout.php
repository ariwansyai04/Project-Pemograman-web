<?php
session_start(); // Selalu mulai sesi di awal file PHP

require_once 'includes/db_connect.php'; // Hubungkan ke database

// Pastikan user sudah login untuk melihat keranjang mereka
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = '<div class="message info">Anda harus login untuk melanjutkan ke Checkout.</div>';
    header("Location: login.php?redirect=checkout.php"); // Redirect ke login, lalu kembali ke checkout
    exit();
}

$user_id = $_SESSION['user_id'];
$cartItems = []; // Inisialisasi array untuk menyimpan detail item di keranjang
$subtotal = 0;
$shippingCost = 15000.00; // Contoh biaya pengiriman dalam IDR (Rp 15.000)

// Ambil item keranjang dari tabel 'carts' di database untuk user yang sedang login
// Menggunakan JOIN dengan tabel 'products' untuk mendapatkan detail produk
$stmt = $conn->prepare("
    SELECT
        c.product_id,
        c.quantity,
        p.nama_produk,
        p.harga,
        p.gambar_url,
        p.stok
    FROM
        carts c
    JOIN
        products p ON c.product_id = p.id
    WHERE
        c.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Tambahkan item ke array cartItems
        $cartItems[] = [
            'id' => $row['product_id'],
            'name' => $row['nama_produk'],
            'price' => $row['harga'],
            'quantity' => $row['quantity'],
            'image' => $row['gambar_url'],
            'stock' => $row['stok'] // Berguna untuk validasi di sisi server jika diperlukan
        ];
        $subtotal += $row['harga'] * $row['quantity'];
    }
}
$stmt->close();

$total = $subtotal + $shippingCost;

// Sertakan header (ini akan membuka tag HTML, HEAD, BODY, HEADER, dan MAIN)
require_once 'includes/header.php';
?>

    <div class="container"> <section class="admin-card">
            <h2><i class="fas fa-shopping-cart"></i> Checkout</h2>

            <form class="admin-form" action="process_order.php" method="POST">
                <h3>1. Ringkasan Pesanan</h3>
                <div class="table-responsive mb-40">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Harga</th>
                                <th>Kuantitas</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($cartItems)) : ?>
                                <?php foreach ($cartItems as $item) : ?>
                                    <tr>
                                        <td data-label="Produk">
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">
                                                <?php echo htmlspecialchars($item['name']); ?>
                                            </div>
                                        </td>
                                        <td data-label="Harga">Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></td>
                                        <td data-label="Kuantitas"><?php echo htmlspecialchars($item['quantity']); ?></td>
                                        <td data-label="Total">Rp <?php echo number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="4" class="text-center">Keranjang Anda kosong. Silakan <a href="products.php">belanja</a> terlebih dahulu.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" style="text-align: right;">Subtotal:</th>
                                <th>Rp <?php echo number_format($subtotal, 0, ',', '.'); ?></th>
                            </tr>
                            <tr>
                                <th colspan="3" style="text-align: right;">Pengiriman:</th>
                                <th>Rp <?php echo number_format($shippingCost, 0, ',', '.'); ?></th>
                            </tr>
                            <tr>
                                <th colspan="3" style="text-align: right; font-size: 1.2rem; color: var(--primary-color);">Total Keseluruhan:</th>
                                <th style="font-size: 1.2rem; color: var(--accent-color);">Rp <?php echo number_format($total, 0, ',', '.'); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <?php if (!empty($cartItems)) : // Hanya tampilkan bagian ini jika keranjang tidak kosong ?>
                    <h3>2. Informasi Pengiriman</h3>
                    <div class="form-group">
                        <label for="full_name"><i class="fas fa-user"></i> Nama Lengkap:</label>
                        <input type="text" id="full_name" name="full_name" placeholder="Nama Lengkap Anda" required>
                    </div>
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Alamat Email:</label>
                        <input type="email" id="email" name="email" placeholder="email@contoh.com" required>
                    </div>
                    <div class="form-group">
                        <label for="phone"><i class="fas fa-phone"></i> Nomor Telepon:</label>
                        <input type="tel" id="phone" name="phone" placeholder="Contoh: 081234567890" required>
                    </div>
                    <div class="form-group">
                        <label for="address"><i class="fas fa-map-marker-alt"></i> Alamat Jalan:</label>
                        <input type="text" id="address" name="address" placeholder="Jl. Raya Utama No. 123" required>
                    </div>
                    <div class="form-group">
                        <label for="city"><i class="fas fa-city"></i> Kota:</label>
                        <input type="text" id="city" name="city" placeholder="Kota Anda" required>
                    </div>
                    <div class="form-group">
                        <label for="postal_code"><i class="fas fa-mail-bulk"></i> Kode Pos:</label>
                        <input type="text" id="postal_code" name="postal_code" placeholder="12345" required>
                    </div>
                    <div class="form-group">
                        <label for="country"><i class="fas fa-globe"></i> Negara:</label>
                        <select id="country" name="country" required>
                            <option value="">Pilih Negara</option>
                            <option value="ID" selected>Indonesia</option>
                            <option value="MY">Malaysia</option>
                            <option value="SG">Singapura</option>
                            <option value="TH">Thailand</option>
                            <option value="VN">Vietnam</option>
                        </select>
                    </div>

                    <h3>3. Informasi Pembayaran</h3>
                    <div class="form-group">
                        <label for="payment_method"><i class="fas fa-wallet"></i> Metode Pembayaran:</label>
                        <select id="payment_method" name="payment_method" required>
                            <option value="">Pilih Metode Pembayaran</option>
                            <option value="bank_transfer">Transfer Bank</option>
                            <option value="dana">DANA</option>
                            <option value="ovo">OVO</option>
                            <option value="gopay">GoPay</option>
                            <option value="credit_card">Kartu Kredit/Debit</option>
                            <option value="cod">Cash on Delivery (COD)</option>
                        </select>
                    </div>
                    <div id="credit_card_details" style="display: none;">
                        <div class="form-group">
                            <label for="card_name"><i class="fas fa-credit-card"></i> Nama di Kartu:</label>
                            <input type="text" id="card_name" name="card_name" placeholder="NAMA PADA KARTU" >
                        </div>
                        <div class="form-group">
                            <label for="card_number"><i class="fas fa-money-check-alt"></i> Nomor Kartu:</label>
                            <input type="text" id="card_number" name="card_number" placeholder="XXXX XXXX XXXX XXXX" >
                        </div>
                        <div class="form-group" style="display: flex; gap: 20px;">
                            <div style="flex: 1;">
                                <label for="exp_date"><i class="fas fa-calendar-alt"></i> Tanggal Kedaluwarsa (MM/YY):</label>
                                <input type="text" id="exp_date" name="exp_date" placeholder="MM/YY" >
                            </div>
                            <div style="flex: 1;">
                                <label for="cvv"><i class="fas fa-lock"></i> CVV:</label>
                                <input type="text" id="cvv" name="cvv" placeholder="XXX" >
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-add-to-cart" style="background-color: var(--primary-color); width: auto; padding: 15px 30px;"><i class="fas fa-cash-register"></i> Lanjutkan Pembayaran</button>
                <?php endif; ?>
            </form>
        </section>
    </div> <script>
        // JavaScript untuk menampilkan/menyembunyikan detail kartu kredit
        document.addEventListener('DOMContentLoaded', function() {
            const paymentMethodSelect = document.getElementById('payment_method');
            const creditCardDetailsDiv = document.getElementById('credit_card_details');

            function toggleCreditCardDetails() {
                if (paymentMethodSelect.value === 'credit_card') {
                    creditCardDetailsDiv.style.display = 'block';
                    // Tambahkan atribut 'required' untuk input kartu kredit saat dipilih
                    creditCardDetailsDiv.querySelectorAll('input').forEach(input => input.setAttribute('required', 'required'));
                } else {
                    creditCardDetailsDiv.style.display = 'none';
                    // Hapus atribut 'required' saat tidak dipilih
                    creditCardDetailsDiv.querySelectorAll('input').forEach(input => input.removeAttribute('required'));
                }
            }

            paymentMethodSelect.addEventListener('change', toggleCreditCardDetails);

            // Panggil fungsi saat halaman dimuat untuk menangani status awal (misal: jika ada error dan form direload)
            toggleCreditCardDetails();
        });
    </script>

<?php
$conn->close(); // Tutup koneksi database
require_once 'includes/footer.php'; // Sertakan footer (ini akan menutup tag MAIN, BODY, dan HTML)
?>