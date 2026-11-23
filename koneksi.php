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

// Pastikan zona waktu PHP dan MySQL selalu sinkron ke WIB (Asia/Jakarta / +07:00)
date_default_timezone_set('Asia/Jakarta');
@$conn->query("SET time_zone = '+07:00'");

// Auto-sinkronisasi & auto-heal struktur tabel doa_comments jika tabel sudah ada
if ($conn && !$conn->connect_error) {
    // 1. Pastikan kolom campaign_id ada dan program_id nullable
    $chkCol = @$conn->query("SHOW COLUMNS FROM doa_comments LIKE 'campaign_id'");
    if (!$chkCol || $chkCol->num_rows === 0) {
        @$conn->query("ALTER TABLE doa_comments ADD COLUMN campaign_id VARCHAR(50) DEFAULT NULL AFTER program_id");
    }
    @$conn->query("ALTER TABLE doa_comments MODIFY COLUMN program_id INT DEFAULT NULL");
    @$conn->query("ALTER TABLE doa_comments MODIFY COLUMN campaign_id VARCHAR(50) DEFAULT NULL");
    @$conn->query("UPDATE doa_comments SET program_id = CAST(SUBSTRING(campaign_id, 6) AS UNSIGNED) WHERE program_id IS NULL AND campaign_id LIKE 'card-%'");
    @$conn->query("UPDATE doa_comments SET campaign_id = CONCAT('card-', program_id) WHERE campaign_id IS NULL AND program_id IS NOT NULL");

    // 2. Sinkronkan doa dari tabel donations ke doa_comments jika belum ada
    @$conn->query("INSERT INTO doa_comments (program_id, campaign_id, donator_name, message, aamiin_count, created_at)
        SELECT 
            COALESCE(d.program_id, 1), 
            CONCAT('card-', COALESCE(d.program_id, 1)), 
            d.donator_name, 
            d.doa_message, 
            0, 
            COALESCE(d.paid_at, d.created_at)
        FROM donations d
        WHERE d.doa_message IS NOT NULL AND TRIM(d.doa_message) != ''
        AND NOT EXISTS (
            SELECT 1 FROM doa_comments dc 
            WHERE dc.message = d.doa_message
        )");
}
?>