<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/koneksi.php';

echo "<h2>Setup & Perbaikan Database Donasi - InfinityFree</h2>";
echo "<p>Status Koneksi MySQL: <strong>BERHASIL TERHUBUNG</strong> ke database <code>" . htmlspecialchars($db) . "</code></p>";

$sqlFile = __DIR__ . '/donasi_schema.sql';
if (!file_exists($sqlFile)) {
    die("<p style='color:red;'>File <code>donasi_schema.sql</code> tidak ditemukan di server.</p>");
}

$sql = file_get_contents($sqlFile);

// Jalankan skema
if ($conn->multi_query($sql)) {
    do {
        if ($res = $conn->store_result()) {
            $res->free();
        }
    } while ($conn->more_results() && $conn->next_result());
}

// Pastikan kolom campaign_id ada di doa_comments
$res = $conn->query("SHOW COLUMNS FROM `doa_comments` LIKE 'campaign_id'");
if ($res && $res->num_rows === 0) {
    $conn->query("ALTER TABLE `doa_comments` ADD `campaign_id` VARCHAR(50) DEFAULT NULL AFTER `program_id`");
}

// Sinkronkan data campaign_id jika ada doa_comments yang belum memiliki campaign_id
$conn->query("UPDATE `doa_comments` SET `campaign_id` = CONCAT('card-', `program_id`) WHERE `campaign_id` IS NULL AND `program_id` IS NOT NULL");

// Pastikan kolom campaign_id ada di donations
$resDon = $conn->query("SHOW COLUMNS FROM `donations` LIKE 'campaign_id'");
if ($resDon && $resDon->num_rows === 0) {
    $conn->query("ALTER TABLE `donations` ADD `campaign_id` VARCHAR(50) DEFAULT NULL AFTER `program_id`");
}

// Perbaiki gambar program donasi darurat banjir agar memakai donasi.jpeg
$conn->query("UPDATE `program_donasi` SET `gambar_banner` = 'donasi.jpeg', `gambar_thumbnail` = 'donasi.jpeg' WHERE `id` = 2 OR `kategori_id` = 5");

// Perbaiki icon kategori donasi
$icons = [
    'yatim' => '<svg fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"><circle cx="9" cy="7" r="4"/><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/></svg>',
    'pendidikan' => '<svg fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>',
    'pemberdayaan' => '<svg fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg>',
    'kesehatan' => '<svg fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>',
    'darurat' => '<svg fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>'
];
foreach ($icons as $slug => $svg) {
    $stmt = $conn->prepare("UPDATE `kategori_donasi` SET `icon_svg` = ? WHERE `slug` = ?");
    if ($stmt) {
        $stmt->bind_param("ss", $svg, $slug);
        $stmt->execute();
        $stmt->close();
    }
}

echo "<p style='color:green; font-weight:bold; font-size:16px;'>✓ Database donasi dan seluruh tabel BERHASIL diperbarui & disinkronkan!</p>";
echo "<p><a href='db.php' style='padding:10px 20px; background:#20b2aa; color:#fff; text-decoration:none; border-radius:5px; font-weight:bold;'>Buka Beranda (db.php)</a> &nbsp; <a href='donasidarurat.php' style='padding:10px 20px; background:#2563eb; color:#fff; text-decoration:none; border-radius:5px; font-weight:bold;'>Tes Donasi Darurat</a></p>";
?>