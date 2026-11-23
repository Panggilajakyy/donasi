<?php
include 'koneksi.php';

// Ambil ID dari URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Query campaign berdasarkan ID
$query = "SELECT 
            p.*,
            k.nama_kategori,
            k.slug as kategori_slug
          FROM program_donasi p
          LEFT JOIN kategori_donasi k ON p.kategori_id = k.id
          WHERE p.id = $id AND p.status = 'aktif'";

$result = mysqli_query($conn, $query);
$program = mysqli_fetch_assoc($result);

// Jika program tidak ditemukan
if (!$program) {
    die("<h1>Campaign Tidak Ditemukan</h1><p>Kembali ke <a href='db.php'>halaman utama</a></p>");
}

// Format rupiah
$terkumpul = $program['donasi_terkumpul'];
if ($terkumpul >= 1000000000) {
    $terkumpul_label = 'Rp ' . round($terkumpul / 1000000000, 1) . ' M';
} elseif ($terkumpul >= 1000000) {
    $terkumpul_label = 'Rp ' . round($terkumpul / 1000000) . ' jt';
} else {
    $terkumpul_label = 'Rp ' . number_format($terkumpul, 0, ',', '.');
}

$persen = 0;
if ($program['target_donasi'] > 0) {
    $persen = round(($program['donasi_terkumpul'] / $program['target_donasi']) * 100);
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($program['judul_program']) ?> - Dimzz Donasi</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #eef5f9;
            color: #1a2a3a;
            padding-bottom: 80px;
        }
        .navbar {
            background: #20b2aa;
            padding: 15px;
            text-align: center;
            color: white;
            font-weight: 700;
            font-size: 20px;
        }
        .container {
            max-width: 800px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .campaign-header {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(32, 178, 170, 0.13);
            margin-bottom: 20px;
        }
        .campaign-image {
            width: 100%;
            height: 300px;
            object-fit: cover;
        }
        .campaign-content {
            padding: 30px;
        }
        .badge {
            display: inline-block;
            background: #e63946;
            color: white;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 15px;
        }
        .title {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 15px;
            color: #1a2a3a;
        }
        .description {
            font-size: 15px;
            line-height: 1.8;
            color: #4a6070;
            margin-bottom: 25px;
        }
        .progress-section {
            background: #f8fafb;
            padding: 25px;
            border-radius: 16px;
            margin-bottom: 25px;
        }
        .progress-bar {
            height: 10px;
            background: #e5eef0;
            border-radius: 10px;
            overflow: hidden;
            margin: 15px 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #20b2aa, #3cd4cc);
            border-radius: 10px;
        }
        .stats {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            font-weight: 600;
        }
        .btn-donasi {
            display: block;
            width: 100%;
            max-width: 400px;
            margin: 30px auto;
            padding: 18px;
            background: #20b2aa;
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(32, 178, 170, 0.35);
            transition: all 0.3s;
        }
        .btn-donasi:hover {
            background: #178f88;
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(32, 178, 170, 0.45);
        }
        .back-link {
            display: inline-block;
            margin: 20px 0;
            color: #20b2aa;
            text-decoration: none;
            font-weight: 600;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="navbar">Dimzz Donasi</div>
    
    <div class="container">
        <a href="db.php" class="back-link">← Kembali ke Beranda</a>
        
        <div class="campaign-header">
            <img src="<?= htmlspecialchars($program['gambar_banner'] ?: $program['gambar_thumbnail']) ?>" 
                 alt="<?= htmlspecialchars($program['judul_program']) ?>" 
                 class="campaign-image">
            
            <div class="campaign-content">
                <span class="badge"><?= htmlspecialchars($program['nama_kategori']) ?></span>
                <h1 class="title"><?= htmlspecialchars($program['judul_program']) ?></h1>
                <p class="description"><?= nl2br(htmlspecialchars($program['deskripsi_singkat'])) ?></p>
                
                <div class="progress-section">
                    <h3 style="margin-bottom: 10px;">Progress Donasi</h3>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $persen ?>%"></div>
                    </div>
                    <div class="stats">
                        <span><strong><?= $terkumpul_label ?></strong> terkumpul</span>
                        <span><strong><?= $persen ?>%</strong></span>
                    </div>
                    <p style="margin-top: 10px; font-size: 13px; color: #7a9aab;">
                        Target: Rp <?= number_format($program['target_donasi'], 0, ',', '.') ?> | 
                        <?= number_format($program['jumlah_donatur'], 0, ',', '.') ?> donatur
                    </p>
                </div>
                
                <div style="background: #fff3cd; padding: 20px; border-radius: 12px; border-left: 4px solid #f0a500;">
                    <p style="font-size: 14px; color: #856404;">
                        <strong>⏰ Sisa Waktu:</strong> <?= $program['sisa_hari'] ?> hari lagi
                    </p>
                </div>
            </div>
        </div>
        
        <?php
        $payPages = [
            1 => 'bayardonasiyatim.php',
            2 => 'bayardonasidarurat.php',
            3 => 'bayardonasipendidikan.php',
            4 => 'bayardonasipemberdayaan.php',
            5 => 'bayardonasikesehatan.php',
        ];
        $payUrl = $payPages[$id] ?? 'db.php';
        ?>
        <button class="btn-donasi" onclick="window.location.href='<?= $payUrl ?>'">
            ❤️ Donasi Sekarang
        </button>
    </div>
</body>
</html>