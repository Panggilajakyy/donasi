<?php
// koneksi.php — Konfigurasi Koneksi Database Multi-Environment

// Parser .env sederhana jika file .env ada di direktori project
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// Ambil konfigurasi dari environment (atau fallback ke default lokal)
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$db   = getenv('DB_NAME') ?: 'donasi';
$port = (int)(getenv('DB_PORT') ?: 3306);

// Membuat koneksi
$conn = @new mysqli($host, $user, $pass, $db, $port);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error . " (Pastikan MySQL berjalan dan database '" . htmlspecialchars($db) . "' sudah dibuat)");
}

// Set charset ke utf8mb4
$conn->set_charset("utf8mb4");
?>