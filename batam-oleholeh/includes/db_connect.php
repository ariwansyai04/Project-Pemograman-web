<?php
// includes/db_connect.php

$servername = "localhost"; // Sesuaikan jika MySQL Anda di server lain
$username = "root";        // Ganti dengan username MySQL Anda
$password = "";            // Ganti dengan password MySQL Anda
$dbname = "batam_oleholeh_db"; // Nama database yang sudah Anda buat

// Membuat koneksi
$conn = new mysqli($servername, $username, $password, $dbname);

// Mengecek koneksi
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}
// echo "Koneksi ke database berhasil!"; // Opsional: Untuk debugging saat pertama kali
?>