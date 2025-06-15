<?php
// logout.php
session_start(); // Memulai sesi

// Hancurkan semua variabel sesi
$_SESSION = array();

// Jika menggunakan cookie sesi, hancurkan juga
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Akhiri sesi
session_destroy();

// Redirect ke halaman utama atau login
header("Location: index.php");
exit();
?>