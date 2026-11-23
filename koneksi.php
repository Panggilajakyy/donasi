<?php
// Konfigurasi database
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'donasi';

// Membuat koneksi
$conn = new mysqli($host, $user, $pass, $db);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

// Set charset ke utf8mb4 (opsional tapi disarankan)
$conn->set_charset("utf8mb4");

// echo "Koneksi database berhasil!"; // Hapus atau comment baris ini di production
?>