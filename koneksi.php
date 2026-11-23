<?php
// koneksi.php — Konfigurasi Koneksi Database Multi-Environment

// Parser .env sederhana jika file .env ada di direktori project
$envFile = __DIR__ . '/.env';
$env = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            $env[$key] = $value;
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            @putenv("$key=$value");
        }
    }
}

// Ambil konfigurasi (prioritas: parsed .env -> $_ENV -> $_SERVER -> getenv -> default)
$host = $env['DB_HOST'] ?? $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? (@getenv('DB_HOST') ?: 'localhost');
$user = $env['DB_USER'] ?? $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? (@getenv('DB_USER') ?: 'root');
$pass = isset($env['DB_PASS']) ? $env['DB_PASS'] : ($_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? (@getenv('DB_PASS') !== false ? @getenv('DB_PASS') : ''));
$db   = $env['DB_NAME'] ?? $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? (@getenv('DB_NAME') ?: 'donasi');
$port = (int)($env['DB_PORT'] ?? $_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? (@getenv('DB_PORT') ?: 3306));

// Matikan exception fatal di PHP 8.1+ agar error query bisa ditangani dengan aman
mysqli_report(MYSQLI_REPORT_OFF);

// Membuat koneksi
$conn = @new mysqli($host, $user, $pass, $db, $port);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error . " (Host: $host, DB: $db. Pastikan kredensial benar dan database sudah di-import)");
}

// Set charset ke utf8mb4
$conn->set_charset("utf8mb4");
?>