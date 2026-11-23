<?php
// donasikesehatan.php - Halaman campaign Kesehatan dengan database
error_reporting(0);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Jakarta'); // ✅ Tambahan: konsisten WIB

require_once 'koneksi.php';
session_start();

// ✅ ID program dari tabel program_donasi (5 = Kesehatan)
$program_id = 5;
$campaign_id = 'card-' . $program_id; // card-5

// Fungsi helper response JSON
function kirimJson($data)
{
    if (ob_get_level())
        ob_clean();
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Ambil data program dari program_donasi
$program = null;
$queryProgram = "SELECT id, judul_program, deskripsi_singkat, target_donasi, donasi_terkumpul, jumlah_donatur
                 FROM program_donasi WHERE id = ?";
$stmt = mysqli_prepare($conn, $queryProgram);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $program_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $program = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// Fallback jika program tidak ditemukan
if (!$program) {
    $program = [
        'id' => 5,
        'judul_program' => 'AKSES KESEHATAN UNTUK SEMUA',
        'target_donasi' => 150000000,
        'donasi_terkumpul' => 47000000,
        'jumlah_donatur' => 412
    ];
}

// Ambil target dari program_donasi
$target = (float)($program['target_donasi'] ?? 150000000);
$totalDonasi = (int)($program['jumlah_donatur'] ?? 412);
$collected = (float)($program['donasi_terkumpul'] ?? 47000000);

// Ambil total donatur dan terkumpul dari tabel donations
$countQuery = "SELECT COUNT(*) as total, SUM(total_amount) as total_amount 
               FROM donations 
               WHERE (program_id = ? OR campaign_id = ?) AND payment_status = 'paid'";
$stmt = mysqli_prepare($conn, $countQuery);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "is", $program_id, $campaign_id);
    mysqli_stmt_execute($stmt);
    $countResult = mysqli_stmt_get_result($stmt);
    if ($countResult && $countRow = mysqli_fetch_assoc($countResult)) {
        if (!empty($countRow['total'])) {
            $totalDonasi = (int)$countRow['total'];
            $collected = (float)($countRow['total_amount'] ?? 0);
        }
    }
    mysqli_stmt_close($stmt);
}

// ✅ AMBIL DOA - FILTER BERDASARKAN campaign_id atau program_id
$doaList = [];
$queryDoa = "SELECT id, donator_name, message, aamiin_count, created_at 
             FROM doa_comments 
             WHERE campaign_id = ? OR program_id = ? 
             ORDER BY created_at DESC 
             LIMIT 20";
$stmt = @mysqli_prepare($conn, $queryDoa);
if (!$stmt) {
    $queryDoa = "SELECT id, donator_name, message, aamiin_count, created_at 
                 FROM doa_comments 
                 WHERE program_id = ? 
                 ORDER BY created_at DESC 
                 LIMIT 20";
    $stmt = @mysqli_prepare($conn, $queryDoa);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $program_id);
    }
} else {
    mysqli_stmt_bind_param($stmt, "si", $campaign_id, $program_id);
}

if ($stmt) {
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $createdAt = $row['created_at'] ?? date('Y-m-d H:i:s');
            $doaList[] = [
                'id' => $row['id'],
                'campaign_id' => $campaign_id,
                'donator_name' => $row['donator_name'] ?? 'Anonim',
                'message' => $row['message'] ?? '',
                'aamiin_count' => $row['aamiin_count'] ?? 0,
                'created_at' => $createdAt,
                'waktu' => waktuRelatif($createdAt)
            ];
        }
    }
    mysqli_stmt_close($stmt);
}

// ✅ Fungsi helper: hitung waktu relatif (server-side, konsisten timezone)
function waktuRelatif($datetimeStr)
{
    $ts = strtotime($datetimeStr);
    $diff = time() - $ts;

    if ($diff < 60) {
        return 'Baru saja';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' menit lalu';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' jam lalu';
    } else {
        return floor($diff / 86400) . ' hari lalu';
    }
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

// ============ ENDPOINT AAMIIN ============
if ($action === 'aamiin') {
    $input = json_decode(file_get_contents('php://input'), true);
    $comment_id = isset($input['comment_id']) ? (int) $input['comment_id'] : 0;
    if ($comment_id <= 0) {
        kirimJson(['success' => false, 'message' => 'ID komentar tidak valid']);
    }
    $session_key = 'aamiin_' . $comment_id;
    if (isset($_SESSION[$session_key])) {
        kirimJson(['success' => false, 'message' => 'Sudah aamiin']);
    }
    $update = "UPDATE doa_comments SET aamiin_count = aamiin_count + 1 WHERE id = ?";
    $stmt = mysqli_prepare($conn, $update);
    mysqli_stmt_bind_param($stmt, "i", $comment_id);
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION[$session_key] = true;
        $select = "SELECT aamiin_count FROM doa_comments WHERE id = ?";
        $stmt2 = mysqli_prepare($conn, $select);
        mysqli_stmt_bind_param($stmt2, "i", $comment_id);
        mysqli_stmt_execute($stmt2);
        $result = mysqli_stmt_get_result($stmt2);
        $row = mysqli_fetch_assoc($result);
        kirimJson(['success' => true, 'new_count' => $row['aamiin_count']]);
    } else {
        kirimJson(['success' => false, 'message' => 'Gagal update aamiin']);
    }
}

// ============ ENDPOINT GET DONATUR ============
if ($action === 'get_donatur') {
    ob_start();
    error_reporting(0);
    ini_set('display_errors', 0);
    $mode = $_GET['mode'] ?? 'terbaru';
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = max(1, (int) ($_GET['limit'] ?? 8));
    $offset = ($page - 1) * $limit;
    $orderBy = ($mode === 'terbesar') ? "total_amount DESC" : "COALESCE(paid_at, created_at) DESC";
    $query = "SELECT donator_name, total_amount, COALESCE(paid_at, created_at) AS waktu_donasi FROM donations 
              WHERE program_id = ? AND payment_status = 'paid'
              ORDER BY $orderBy LIMIT ? OFFSET ?";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        ob_end_clean();
        kirimJson(['success' => false, 'message' => 'Prepare gagal: ' . mysqli_error($conn)]);
    }
    mysqli_stmt_bind_param($stmt, "iii", $program_id, $limit, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $donatur = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $tglDonasi = $row['waktu_donasi'] ?? date('Y-m-d H:i:s');
        $donatur[] = [
            'name' => htmlspecialchars($row['donator_name']),
            'amount' => (float) $row['total_amount'],
            'time' => $tglDonasi,
            'waktu' => waktuRelatif($tglDonasi)
        ];
    }
    mysqli_stmt_close($stmt);
    $countQuery = "SELECT COUNT(*) as total FROM donations 
                   WHERE program_id = ? AND payment_status = 'paid'";
    $stmt = mysqli_prepare($conn, $countQuery);
    mysqli_stmt_bind_param($stmt, "i", $program_id);
    mysqli_stmt_execute($stmt);
    $countResult = mysqli_stmt_get_result($stmt);
    $total = ($countResult) ? (int) mysqli_fetch_assoc($countResult)['total'] : 0;
    mysqli_stmt_close($stmt);
    ob_end_clean();
    kirimJson(['success' => true, 'data' => $donatur, 'total' => $total, 'page' => $page, 'limit' => $limit]);
}

// ============ ENDPOINT GET STATS ============
if ($action === 'get_stats') {
    header('Content-Type: application/json');
    $countQuery = "SELECT COUNT(*) as total, SUM(total_amount) as total_amount 
                   FROM donations 
                   WHERE program_id = ? AND payment_status = 'paid'";
    $stmt = mysqli_prepare($conn, $countQuery);
    mysqli_stmt_bind_param($stmt, "i", $program_id);
    mysqli_stmt_execute($stmt);
    $countResult = mysqli_stmt_get_result($stmt);
    $countRow = mysqli_fetch_assoc($countResult);
    $totalDonatur = $countRow['total'] ?? 0;
    $totalCollected = $countRow['total_amount'] ?? 0;
    mysqli_stmt_close($stmt);

    echo json_encode([
        'success' => true,
        'total_donatur' => $totalDonatur,
        'total_collected' => $totalCollected,
        'doa_count' => count($doaList)
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Donasi Akses Kesehatan</title>
    <style>
        /* ================= RESET ================= */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
        }

        body {
            background: #ececec;
            display: flex;
            justify-content: center;
            margin: 0;
        }

        .navbar {
            position: fixed;
            top: 0;
            width: 100%;
            max-width: 600px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            justify-content: center;
            z-index: 999;
            background: transparent;
            transition: 0.3s ease;
        }

        .nav-inner {
            width: 100%;
            padding: 17px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .home-icon {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .home-icon span {
            font-weight: 600;
            font-size: 14px;
            color: #fff;
            opacity: 0;
            transform: translateY(-6px);
            pointer-events: none;
            transition: 0.3s ease;
            white-space: nowrap;
        }

        .navbar.scrolled .home-icon span {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
            color: #000;
        }

        .home-icon img {
            width: 25px;
            height: 25px;
            object-fit: contain;
            cursor: pointer;
            transition: 0.3s;
            filter: brightness(0) invert(1);
        }

        .navbar.scrolled {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(0.5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .navbar.scrolled .home-icon img {
            filter: brightness(0);
        }

        .wrapper {
            width: 600px;
            display: flex;
            flex-direction: column;
            gap: 18px;
            padding-bottom: 100px;
        }

        .card {
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.12);
        }

        .hero img {
            width: 100%;
            object-fit: cover;
            display: block;
        }

        .content {
            padding: 20px;
        }

        .content h2 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 14px;
        }

        .progress-info {
            font-size: 14px;
            margin-bottom: 8px;
        }

        .progress {
            height: 10px;
            background: #e5e5e5;
            border-radius: 20px;
            overflow: hidden;
            margin: 10px 0 6px;
        }

        .progress-bar {
            height: 100%;
            width: 0%;
            background: #20B2AA;
            transition: 1s;
        }

        .extra {
            font-size: 13px;
            color: #666;
            display: flex;
            justify-content: space-between;
        }

        .btn {
            width: 100%;
            margin-top: 16px;
            padding: 14px;
            border: none;
            border-radius: 8px;
            background: #20B2AA;
            color: #fff;
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.2s, transform 0.15s;
        }

        .btn:hover {
            background: #178f88;
        }

        .btn:active {
            transform: scale(0.98);
        }

        .organizer {
            padding: 22px 24px;
        }

        .organizer h3 {
            margin-bottom: 18px;
            font-size: 17px;
            font-weight: 700;
            color: #1a2a3a;
        }

        .org-box {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .logo {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
        }

        .logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .org-info {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .org-name {
            font-size: 16px;
            font-weight: 400;
            color: #10c3ff;
        }

        .org-verify {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .verify-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #fff;
            color: #10c3ff;
            border: 1.5px solid #10c3ff;
            border-radius: 20px;
            padding: 1.5px 7px 1.5px 3px;
            font-size: 11px;
            font-weight: 500;
        }

        .verify-badge svg {
            background: #10c3ff;
            border-radius: 50%;
            padding: 1.5px;
            width: 12px;
            height: 12px;
            flex-shrink: 0;
        }

        .verify-text {
            font-size: 13px;
            color: #888;
            font-style: italic;
        }

        .tabs {
            display: flex;
            position: relative;
            background-color: #f8f9fa;
            border-radius: 12px 12px 0 0;
            padding: 4px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .tab-indicator {
            position: absolute;
            bottom: 4px;
            height: 3px;
            background: #20B2AA;
            border-radius: 3px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .tab {
            flex: 1;
            text-align: center;
            padding: 14px 8px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            color: #6c757d;
            border-radius: 8px;
            transition: all 0.2s ease;
            z-index: 1;
        }

        .tab:hover {
            color: #495057;
            background-color: rgba(16, 195, 255, 0.05);
        }

        .tab.active {
            color: #20B2AA;
            background-color: rgba(16, 195, 255, 0.08);
            cursor: default;
        }

        .tab-content {
            padding: 20px;
            font-size: 15px;
            color: #444;
            background-color: #fff;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            line-height: 1.7;
        }

        @media (max-width:450px) {
            .wrapper {
                width: 100%;
            }

            .tab {
                font-size: 14px;
                padding: 12px 4px;
            }
        }

        .timeline-container {
            position: relative;
            padding: 20px 0;
        }

        .timeline-line {
            position: absolute;
            left: 20px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #c6c6c6;
            opacity: 0.3;
        }

        .timeline-item {
            position: relative;
            padding-left: 50px;
            margin-bottom: 30px;
            animation: fadeInUp 0.5s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .timeline-bullet {
            position: absolute;
            left: 14px;
            top: 8px;
            width: 14px;
            height: 14px;
            background: #20B2AA;
            border-radius: 50%;
            border: 3px solid #fff;
            box-shadow: 0 0 0 3px rgba(16, 195, 255, 0.2);
            z-index: 1;
        }

        .timeline-card {
            background: #fff;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .timeline-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
        }

        .timeline-title {
            font-size: 16px;
            font-weight: 700;
            color: #1a365d;
            margin-bottom: 6px;
        }

        .timeline-content {
            font-size: 14px;
            color: #4a5568;
            line-height: 1.6;
            margin-bottom: 12px;
            font-style: italic;
        }

        .timeline-author {
            font-size: 13px;
            color: #000000;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .timeline-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 12px;
        }

        .timeline-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
        }

        .timeline-time {
            font-size: 12px;
            color: #a0aec0;
        }

        .keterangan-wrapper {
            position: relative;
        }

        .keterangan-container {
            position: relative;
            overflow: hidden;
            transition: max-height 0.6s ease;
            margin-bottom: 0;
        }

        .keterangan-container img {
            max-width: 100%;
            display: block;
        }

        .keterangan-container.collapsed {
            max-height: 300px;
        }

        .keterangan-container.expanded {
            max-height: 9999px;
        }

        .keterangan-gradient {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 65%;
            background: linear-gradient(to top, rgba(255, 255, 255, 1) 0%, rgba(255, 255, 255, 1) 30%, rgba(255, 255, 255, 0.9) 50%, rgba(255, 255, 255, 0.5) 70%, rgba(255, 255, 255, 0) 100%);
            pointer-events: none;
            transition: opacity 0.4s ease;
        }

        .keterangan-container.collapsed .keterangan-gradient {
            opacity: 1;
        }

        .keterangan-container.expanded .keterangan-gradient {
            opacity: 0;
        }

        .read-more-btn {
            position: absolute;
            bottom: 16px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 3;
            transition: opacity 0.3s ease;
            white-space: nowrap;
        }

        .read-more-btn.hidden {
            opacity: 0;
            pointer-events: none;
        }

        .read-more-btn button {
            background: rgba(255, 255, 255, 0.92);
            color: #20B2AA;
            border: 1.5px solid #20B2AA;
            padding: 10px 28px;
            border-radius: 30px;
            font-weight: 400;
            font-size: 14px;
            cursor: pointer;
            white-space: nowrap;
            backdrop-filter: blur(4px);
            box-shadow: 0 2px 12px rgba(23, 162, 184, 0.25);
            transition: background 0.3s ease, color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease, transform 0.15s ease;
            animation: floatUpDown 2s ease-in-out infinite;
        }

        .read-more-btn button:hover {
            background: #20B2AA;
            color: #fff;
            border-color: #20B2AA;
            box-shadow: 0 4px 18px rgba(23, 162, 184, 0.35);
            animation-play-state: paused;
        }

        .read-more-btn button:active {
            transform: scale(0.95);
            background: #0d8086;
            box-shadow: 0 1px 6px rgba(23, 162, 184, 0.2);
            transition: transform 0.08s ease, background 0.1s ease;
        }

        @keyframes floatUpDown {

            0%,
            100% {
                transform: translateY(0);
            }

            70% {
                transform: translateY(-20px);
            }
        }

        .donatur-toggle-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .donatur-toggle-btn {
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s ease;
            border: 2px solid #20B2AA;
        }

        .donatur-toggle-btn.active {
            background: #20B2AA;
            color: #fff;
        }

        .donatur-toggle-btn:not(.active) {
            background: #fff;
            color: #20B2AA;
        }

        .donatur-toggle-btn:hover:not(.active) {
            background: rgba(16, 195, 255, 0.08);
        }

        .donatur-card-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .donatur-card-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            background: #fff;
            border: 1px solid #e8ecef;
            border-radius: 12px;
            padding: 16px 18px;
            transition: box-shadow 0.2s ease, transform 0.2s ease;
            animation: fadeInUp 0.4s ease;
        }

        .donatur-card-item:hover {
            box-shadow: 0 4px 18px rgba(16, 195, 255, 0.13);
            transform: translateY(-2px);
        }

        .donatur-card-left {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .donatur-card-name {
            font-size: 16px;
            font-weight: 700;
            color: #1a2a3a;
        }

        .donatur-card-desc {
            font-size: 14px;
            color: #555;
        }

        .donatur-card-desc span {
            font-weight: 700;
            color: #1a2a3a;
        }

        .donatur-card-right {
            font-size: 13px;
            color: #888;
            white-space: nowrap;
            padding-top: 2px;
        }

        .donatur-load-more {
            text-align: center;
            margin-top: 18px;
        }

        .donatur-load-more button {
            padding: 10px 32px;
            background: transparent;
            color: #20B2AA;
            border: 1.5px solid #fff;
            border-radius: 25px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .donatur-load-more button:hover {
            color: #20B2AA;
            border-color: #20B2AA;
        }

        .fundraiser-card {
            background: linear-gradient(to bottom, #ffffff 0%, #eaf9ff 50%, #c8eefa 100%);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-top: 0;
            overflow: hidden;
        }

        .fundraiser-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .fundraiser-title {
            font-size: 18px;
            font-weight: 700;
            color: #333;
        }

        .fundraiser-illustration {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 30px 0;
            background: none;
            padding: 20px;
        }

        .illustration-container {
            position: relative;
            width: 120px;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .megaphone {
            width: 60px;
            height: 60px;
            background: #10c3ff;
            border-radius: 8px 40px 40px 8px;
            position: relative;
            transform: rotate(-15deg);
            box-shadow: 0 4px 10px rgba(16, 195, 255, 0.3);
        }

        .megaphone::before {
            content: '';
            position: absolute;
            width: 30px;
            height: 20px;
            background: #10c3ff;
            left: -25px;
            top: 20px;
            border-radius: 4px;
        }

        .megaphone::after {
            content: '';
            position: absolute;
            width: 15px;
            height: 15px;
            background: #fff;
            border-radius: 50%;
            left: -10px;
            top: 22px;
        }

        .heart {
            position: absolute;
            top: -15px;
            right: -10px;
            width: 30px;
            height: 30px;
            background: #ff6b6b;
            transform: rotate(45deg);
            animation: heartbeat 1.5s ease-in-out infinite;
        }

        .heart::before,
        .heart::after {
            content: '';
            width: 30px;
            height: 30px;
            position: absolute;
            background: #ff6b6b;
            border-radius: 50%;
        }

        .heart::before {
            top: -15px;
            left: 0;
        }

        .heart::after {
            left: -15px;
            top: 0;
        }

        .coin {
            position: absolute;
            bottom: -10px;
            left: -10px;
            width: 35px;
            height: 35px;
            background: #ffd93d;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #333;
            box-shadow: 0 3px 8px rgba(255, 217, 61, 0.4);
            animation: coinBounce 2s ease-in-out infinite;
        }

        @keyframes heartbeat {
            0% {
                transform: rotate(45deg) scale(1);
            }

            25% {
                transform: rotate(45deg) scale(1.1);
            }

            50% {
                transform: rotate(45deg) scale(1);
            }

            75% {
                transform: rotate(45deg) scale(1.05);
            }

            100% {
                transform: rotate(45deg) scale(1);
            }
        }

        @keyframes coinBounce {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-5px);
            }
        }

        .fundraiser-status {
            font-size: 16px;
            color: #999;
            margin: 20px 0 10px;
            text-align: center;
        }

        .fundraiser-description {
            font-size: 14px;
            color: #666;
            text-align: center;
            line-height: 1.5;
            margin-bottom: 24px;
        }

        .fundraiser-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 70%;
            padding: 14px 20px;
            background: #ffffff;
            color: #20B2AA;
            border: 1px solid #20B2AA;
            border-radius: 5px;
            margin-left: 15%;
            font-weight: 500;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.25s ease;
            box-shadow: 0 2px 8px rgba(16, 195, 255, 0.15);
        }

        .fundraiser-btn svg {
            flex-shrink: 0;
            color: #20B2AA;
            transition: color 0.25s ease;
        }

        .fundraiser-btn:hover {
            background: #20B2AA;
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(16, 195, 255, 0.35);
        }

        .fundraiser-btn:hover svg {
            color: #ffffff;
        }

        .fundraiser-badge {
            background: #e8f5fe;
            color: #20B2AA;
            padding: 4px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 500;
        }

        .doa-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .doa-header {
            margin-bottom: 16px;
        }

        .doa-title {
            font-size: 17px;
            font-weight: 700;
            color: #1a2a3a;
        }

        .doa-title span {
            color: #888;
            font-weight: 500;
            font-size: 15px;
        }

        .doa-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .doa-item {
            background: #fff;
            border: 1px solid #e8ecef;
            border-radius: 12px;
            padding: 16px;
            animation: fadeInUp 0.4s ease;
            transition: box-shadow 0.2s ease;
        }

        .doa-item:hover {
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
        }

        .doa-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .doa-item-name {
            font-size: 15px;
            font-weight: 700;
            color: #1a2a3a;
        }

        .doa-item-time {
            font-size: 12px;
            color: #999;
        }

        .doa-item-text {
            font-size: 14px;
            color: #444;
            line-height: 1.6;
            margin-bottom: 12px;
        }

        .doa-aamiin-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: transparent;
            border: 1.5px solid #e2e8f0;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            color: #555;
            cursor: pointer;
            transition: all 0.25s ease;
        }

        .doa-aamiin-btn .aamiin-icon {
            width: 22px;
            height: 22px;
            background: #20B2AA;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            flex-shrink: 0;
            transition: transform 0.2s ease;
        }

        .doa-aamiin-btn:hover {
            border-color: #20B2AA;
            color: #20B2AA;
            background: #fff5f5;
        }

        .doa-aamiin-btn:hover .aamiin-icon {
            transform: scale(1.15);
        }

        .doa-aamiin-btn.active {
            border-color: #20B2AA;
            color: #20B2AA;
            background: #fff5f5;
        }

        .doa-load-more {
            text-align: center;
            margin-top: 16px;
        }

        .doa-load-more button {
            padding: 10px 28px;
            background: transparent;
            color: #20B2AA;
            border: 1.5px solid #ffffff;
            border-radius: 25px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }

        .doa-load-more button:hover {
            background: #fff;
            border-color: #20B2AA;
        }

        .sticky-bar {
            position: fixed;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%) translateY(100%);
            width: 600px;
            display: flex;
            background: #fff;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.12);
            z-index: 998;
            padding: 12px 16px;
            gap: 12px;
            transition: transform 0.7s ease;
        }

        .sticky-bar.visible {
            transform: translateX(-50%) translateY(0);
        }

        .sticky-bar.hidden {
            transform: translateX(-50%) translateY(100%);
        }

        .sticky-share-btn {
            flex: 0.3;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 18px;
            background: #fff;
            color: #20B2AA;
            border: 2px solid #20B2AA;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
        }

        .sticky-share-btn:hover {
            background: #f0fbff;
        }

        .sticky-share-text {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }

        .sticky-share-text span {
            font-size: 14px;
            font-weight: 700;
            color: #20B2AA;
        }

        .sticky-share-text small {
            font-size: 11px;
            color: #999;
            font-weight: 400;
        }

        .sticky-donasi-btn {
            flex: 1;
            padding: 12px;
            background: #20B2AA;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s ease;
        }

        .sticky-donasi-btn:hover {
            background: #178f88;
            transform: translateY(-1px);
        }

        .sticky-donasi-btn:active {
            transform: scale(0.98);
        }

        @media (max-width:600px) {
            .sticky-bar {
                width: 100%;
                transform: translateX(-50%) translateY(100%);
            }
        }
    </style>
</head>

<body>
    <nav class="navbar" id="navbar">
        <div class="nav-inner">
            <div class="home-icon" onclick="window.location.href='db.php'" style="cursor:pointer;">
                <img src="home.png" alt="home" />
                <span>PEDULI KESEHATAN! SELAMATKAN SESAMA!</span>
            </div>
        </div>
    </nav>
    <div class="wrapper">
        <div class="card">
            <div class="hero"><img src="donasikesehatan.png" /></div>
            <div class="content">
                <h2>AKSES KESEHATAN LAYAK UNTUK SEMUA!</h2>
                <div class="progress-info"><b id="collected"></b> terkumpul dari <b id="target"></b></div>
                <div class="progress">
                    <div class="progress-bar" id="progressBar"></div>
                </div>
                <div class="extra"><span id="donasiCount">0 Donasi</span><span>∞ hari lagi</span></div>
                <button class="btn" onclick="window.location.href='bayardonasikesehatan.php'">Donasi Sekarang</button>
            </div>
        </div>
        <div class="card organizer">
            <h3>Penggalang Dana</h3>
            <div class="org-box">
                <div class="logo"><img src="logoo.png" /></div>
                <div class="org-info">
                    <span class="org-name">Dimzz Corporation</span>
                    <div class="org-verify"><span class="verify-badge"><svg width="13" height="13" viewBox="0 0 24 24"
                                fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round"
                                stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12" />
                            </svg>.org</span><span class="verify-text">Verified Organization</span></div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="tabs">
                <div class="tab-indicator"></div>
                <div class="tab active" data-tab="keterangan">Keterangan</div>
                <div class="tab" data-tab="kabar">Kabar Terbaru (5)</div>
                <div class="tab" data-tab="donatur">Donatur (<span id="donaturCount"><?= $totalDonasi ?></span>)</div>
            </div>
            <div class="tab-content" id="tabContent"></div>
        </div>
        <div class="fundraiser-card">
            <div class="fundraiser-header">
                <h3 class="fundraiser-title">Fundraiser</h3><span class="fundraiser-badge">0 Aktif</span>
            </div>
            <div class="fundraiser-illustration">
                <div class="illustration-container">
                    <div class="megaphone"></div>
                    <div class="heart"></div>
                    <div class="coin">Rp</div>
                </div>
            </div>
            <p class="fundraiser-status">Belum ada Fundraiser</p>
            <p class="fundraiser-description">Bergabunglah sebagai fundraiser dan bantu kami mengumpulkan lebih banyak
                donasi untuk akses kesehatan bagi masyarakat kurang mampu. Setiap usaha Anda sangat berarti!</p>
            <button class="fundraiser-btn"
                onclick="window.open('https://wa.me/6285166376862?text=Halo%2C%20saya%20ingin%20jadi%20fundraiser%20untuk%20program%20Kesehatan.%20Mohon%20informasinya.', '_blank')">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                    <circle cx="9" cy="7" r="4" />
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                    <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                </svg>
                Jadi Fundraiser
            </button>
        </div>
        <div class="doa-card">
            <div class="doa-header">
                <h3 class="doa-title">Doa-doa orang baik <span id="doaCount">(<?= count($doaList) ?>)</span></h3>
            </div>
            <div class="doa-list" id="doaList"></div>
            <div class="doa-load-more" id="doaLoadMore" style="display:none;"><button onclick="loadMoreDoa()">Lihat doa
                    lainnya</button></div>
        </div>
    </div>
    <div class="sticky-bar">
        <button class="sticky-share-btn" onclick="shareLink()"><svg xmlns="http://www.w3.org/2000/svg" width="18"
                height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <circle cx="18" cy="5" r="3" />
                <circle cx="6" cy="12" r="3" />
                <circle cx="18" cy="19" r="3" />
                <line x1="8.59" y1="13.51" x2="15.42" y2="17.49" />
                <line x1="15.41" y1="6.51" x2="8.59" y2="10.49" />
            </svg>
            <div class="sticky-share-text"><span>Share</span><small>Bagikan</small></div>
        </button>
        <button class="sticky-donasi-btn" onclick="window.location.href='bayardonasikesehatan.php'">Donasi
            Sekarang</button>
    </div>
    <script>
        // Data campaign dari database
        var collected = <?= (int) $collected ?>;
        var target = <?= (int) $target ?>;
        var totalDonasi = <?= (int) $totalDonasi ?>;

        function rupiah(n) { return "Rp " + n.toLocaleString("id-ID"); }

        // Update tampilan awal
        document.getElementById("collected").innerText = rupiah(collected);
        document.getElementById("target").innerText = rupiah(target);
        document.getElementById("donasiCount").innerText = totalDonasi + " Donasi";
        document.getElementById("donaturCount").innerText = totalDonasi;

        setTimeout(function () {
            document.getElementById("progressBar").style.width = (collected / target) * 100 + "%";
        }, 300);

        // Fungsi untuk update statistik secara real-time
        function updateStats() {
            fetch('?action=get_stats')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        var donasiCountEl = document.getElementById("donasiCount");
                        var donaturCountEl = document.getElementById("donaturCount");

                        if (data.total_donatur != totalDonasi) {
                            animateValue(donasiCountEl, totalDonasi, data.total_donatur, 1000);
                            if (donaturCountEl) {
                                animateValue(donaturCountEl, totalDonasi, data.total_donatur, 1000);
                            }
                            totalDonasi = data.total_donatur;
                        }

                        if (data.total_collected != collected) {
                            var collectedEl = document.getElementById("collected");
                            animateValue(collectedEl, collected, data.total_collected, 1000);
                            collected = data.total_collected;

                            setTimeout(function () {
                                document.getElementById("progressBar").style.width = (collected / target) * 100 + "%";
                            }, 300);
                        }
                    }
                })
                .catch(error => console.error('Error updating stats:', error));
        }

        function animateValue(element, start, end, duration) {
            var startTimestamp = null;
            var step = function (timestamp) {
                if (!startTimestamp) startTimestamp = timestamp;
                var progress = Math.min((timestamp - startTimestamp) / duration, 1);
                var current = Math.floor(progress * (end - start) + start);
                element.innerHTML = rupiah(current) + (element.id === "donasiCount" || element.id === "donaturCount" ? " Donasi" : "");
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                }
            };
            window.requestAnimationFrame(step);
        }

        setInterval(updateStats, 10000);

        var doaData = <?= json_encode($doaList) ?>;
        var doaAamiin = doaData.map(d => d.aamiin_count);
        var userAamiin = new Array(doaData.length).fill(false);
        var doaPage = 1;
        var DOA_PER_PAGE = 3;

        function formatTime(dateStr) {
            var date = new Date(dateStr), now = new Date(), diff = Math.floor((now - date) / 1000);
            if (diff < 60) return "Baru saja";
            if (diff < 3600) return Math.floor(diff / 60) + " menit lalu";
            if (diff < 86400) return Math.floor(diff / 3600) + " jam lalu";
            return Math.floor(diff / 86400) + " hari lalu";
        }
        function escapeHtml(str) { return str.replace(/[&<>]/g, function (m) { return m === '&' ? '&amp;' : m === '<' ? '&lt;' : '&gt;'; }); }
        function renderDoaItem(d, idx) {
            var count = doaAamiin[idx], active = userAamiin[idx];
            return `<div class="doa-item" id="doaItem${idx}">
                <div class="doa-item-header"><span class="doa-item-name">${escapeHtml(d.donator_name)}</span><span class="doa-item-time">${d.waktu}</span></div>
                <p class="doa-item-text">${escapeHtml(d.message)}</p>
                <button class="doa-aamiin-btn ${active ? 'active' : ''}" onclick="toggleAamiin(${idx}, ${d.id})">
                    <span class="aamiin-icon">🤲</span>${count > 0 ? count + ' Aamiin' : 'Aamiin-kan doa ini'}
                </button>
            </div>`;
        }
        function renderDoaList() {
            var list = document.getElementById('doaList'), loadMore = document.getElementById('doaLoadMore');
            var visible = doaData.slice(0, doaPage * DOA_PER_PAGE);
            var html = '';
            for (var i = 0; i < visible.length; i++) html += renderDoaItem(visible[i], i);
            list.innerHTML = html;
            document.getElementById('doaCount').innerText = '(' + doaData.length + ')';
            loadMore.style.display = visible.length >= doaData.length ? 'none' : 'block';
        }
        function loadMoreDoa() { doaPage++; renderDoaList(); }
        async function toggleAamiin(index, commentId) {
            if (userAamiin[index]) return;
            try {
                var resp = await fetch('?action=aamiin', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ comment_id: commentId }) });
                var result = await resp.json();
                if (result.success) { userAamiin[index] = true; doaAamiin[index] = result.new_count; renderDoaList(); }
                else alert('Gagal mengirim Aamiin: ' + (result.message || 'Error'));
            } catch (e) { console.error(e); alert('Error: ' + e.message); }
        }

        var donaturMode = 'terbaru';
        var donaturPage = 1;
        var ITEMS_PER_PAGE = 8;
        var totalDonatur = 0;

        async function renderDonatur(mode) {
            donaturMode = mode;
            donaturPage = 1;
            await loadDonaturData();
        }
        async function loadDonaturData() {
            try {
                var url = `?action=get_donatur&mode=${donaturMode}&page=${donaturPage}&limit=${ITEMS_PER_PAGE}`;
                var resp = await fetch(url);
                var result = await resp.json();
                if (result.success) {
                    totalDonatur = result.total;
                    var donatur = result.data;
                    var hasMore = (donaturPage * ITEMS_PER_PAGE) < totalDonatur;
                    var html = '<div class="donatur-toggle-bar">' +
                        '<button class="donatur-toggle-btn ' + (donaturMode === 'terbaru' ? 'active' : '') + '" onclick="renderDonatur(\'terbaru\')">Terbaru</button>' +
                        '<button class="donatur-toggle-btn ' + (donaturMode === 'terbesar' ? 'active' : '') + '" onclick="renderDonatur(\'terbesar\')">Terbesar</button>' +
                        '</div><div class="donatur-card-list" id="donaturCardList">';
                    for (var i = 0; i < donatur.length; i++) {
                        var d = donatur[i];
                        html += '<div class="donatur-card-item">' +
                            '<div class="donatur-card-left">' +
                            '<div class="donatur-card-name">' + escapeHtml(d.name) + '</div>' +
                            '<div class="donatur-card-desc">Berdonasi sebesar <span>' + rupiah(d.amount) + '</span></div>' +
                            '</div>' +
                            '<div class="donatur-card-right">' + d.waktu + '</div>' +
                            '</div>';
                    }
                    html += '</div>';
                    if (hasMore) html += '<div class="donatur-load-more"><button onclick="loadMoreDonatur()">Muat Lebih Banyak</button></div>';
                    document.getElementById("tabContent").innerHTML = html;
                } else {
                    document.getElementById("tabContent").innerHTML = '<p>Gagal memuat donatur: ' + (result.message || 'Error') + '</p>';
                }
            } catch (e) {
                console.error(e);
                document.getElementById("tabContent").innerHTML = '<p>Error memuat donatur: ' + e.message + '</p>';
            }
        }
        async function loadMoreDonatur() {
            donaturPage++;
            try {
                var url = `?action=get_donatur&mode=${donaturMode}&page=${donaturPage}&limit=${ITEMS_PER_PAGE}`;
                var resp = await fetch(url);
                var result = await resp.json();
                if (result.success) {
                    var donatur = result.data;
                    var list = document.getElementById('donaturCardList');
                    for (var i = 0; i < donatur.length; i++) {
                        var d = donatur[i];
                        var div = document.createElement('div');
                        div.className = 'donatur-card-item';
                        div.innerHTML = '<div class="donatur-card-left"><div class="donatur-card-name">' + escapeHtml(d.name) + '</div><div class="donatur-card-desc">Berdonasi sebesar <span>' + rupiah(d.amount) + '</span></div></div><div class="donatur-card-right">' + d.waktu + '</div>';
                        list.appendChild(div);
                    }
                    if ((donaturPage * ITEMS_PER_PAGE) >= totalDonatur) {
                        var el = document.querySelector('.donatur-load-more');
                        if (el) el.remove();
                    }
                }
            } catch (e) { console.error(e); }
        }

        var newsList = [
            {
                title: "LAYANAN KESEHATAN GRATIS DIBUKA",
                text: "Masyarakat kini bisa mengakses pemeriksaan dokter dan obat secara gratis di klinik yang baru diresmikan",
                author: "— Tim Medis",
                src: "https://statik.tempo.co/data/2025/02/07/id_1375604/1375604_720.jpg",
                time: new Date()
            },
            {
                title: "OBAT-OBATAN TIBA DI PUSKESMAS",
                text: "Ribuan obat untuk penyakit kronis telah didistribusikan ke 12 puskesmas di daerah terpencil",
                author: "— Dinas Kesehatan",
                src: "https://tse4.mm.bing.net/th/id/OIP.qOEKGSLs8aB-DNn3qg0qJAHaFj?pid=Api&P=0&h=180",
                time: new Date()
            },
            {
                title: "MOBIL SEHAT KELILING RESMI BEROPERASI",
                text: "Mobil layanan kesehatan keliling mulai menyambangi desa-desa yang jauh dari fasilitas medis",
                author: "— Relawan Medis",
                src: "https://tse3.mm.bing.net/th/id/OIP.PZictXZT_-ITTljFPcownwHaE_?pid=Api&P=0&h=180",
                time: new Date()
            },
            {
                title: "POSYANDU AKTIF KEMBALI",
                text: "Posyandu di 5 kelurahan kembali aktif dengan program imunisasi dan pemantauan tumbuh kembang anak",
                author: "— Bidan Desa",
                src: "https://tse3.mm.bing.net/th/id/OIP.kWOakic4MmnYIQaCGRCCqQHaFj?pid=Api&P=0&h=180",
                time: new Date()
            },
            {
                title: "DONASI ALAT KESEHATAN MENGALIR",
                text: "Alat kesehatan dari para donatur mulai digunakan di rumah sakit dan puskesmas daerah",
                author: "— Rumah Sakit",
                src: "https://tse4.mm.bing.net/th/id/OIP.KDtSN0bGdFGdiLLc8M3VNAHaE8?pid=Api&P=0&h=180",
                time: new Date()
            }
        ];
        function renderNews() {
            var html = '<div class="timeline-container"><div class="timeline-line"></div>';
            for (var i = 0; i < newsList.length; i++) {
                var n = newsList[i];
                html += '<div class="timeline-item">' +
                    '<div class="timeline-bullet"></div>' +
                    '<div class="timeline-card">' +
                    '<h3 class="timeline-title">' + escapeHtml(n.title) + '</h3>' +
                    '<p class="timeline-content">' + escapeHtml(n.text) + '</p>' +
                    '<div class="timeline-author">' + escapeHtml(n.author) + '</div>' +
                    '<img src="' + n.src + '" class="timeline-image" alt="' + escapeHtml(n.title) + '" onerror="this.src=\'https://via.placeholder.com/700x400?text=No+Image\'">' +
                    '<div class="timeline-footer"><span class="timeline-time">' + formatTime(n.time) + '</span></div>' +
                    '</div></div>';
            }
            html += '</div>';
            document.getElementById("tabContent").innerHTML = html;
        }

        function openTab(tabName) {
            if (tabName === 'keterangan') {
                document.getElementById("tabContent").innerHTML = '<h3>AKSES KESEHATAN LAYAK UNTUK SEMUA</h3><div class="keterangan-wrapper"><div class="keterangan-container collapsed" id="keteranganContainer"><img src="https://donasi.dompetdhuafa.org/wp-content/uploads/2019/08/05-04-Berita-DD-Situasi-Laporan-Program-Kuartal-I.jpg" style="margin-top:10px; border-radius:10px;"><p style="margin-top:8px;">Masih banyak masyarakat Indonesia yang kesulitan mengakses layanan kesehatan dasar. Di daerah terpencil, jarak ke puskesmas atau rumah sakit bisa mencapai puluhan kilometer tanpa akses transportasi memadai.</p><p style="margin-top:8px;">Biaya berobat yang tinggi menjadi salah satu penyebab utama masyarakat menunda pemeriksaan hingga kondisi penyakitnya memburuk. Data menunjukkan bahwa hampir 30% penduduk belum terjamin akses kesehatan yang layak.</p><p style="margin-top:8px;">Melalui program ini, donasi yang terkumpul akan digunakan untuk menyediakan layanan kesehatan gratis, mendistribusikan obat-obatan esensial, dan mengirimkan tim medis ke daerah-daerah yang membutuhkan.</p><p style="margin-top:8px;">Kami juga bekerja sama dengan puskesmas setempat untuk meningkatkan kapasitas layanan, memberikan pelatihan tenaga kesehatan desa, dan menyediakan alat kesehatan yang selama ini belum tersedia.</p><p style="margin-top:8px;">Setiap rupiah yang Anda donasikan bisa menyelamatkan nyawa. Mari bersama-sama memastikan bahwa kesehatan bukan lagi kemewahan, melainkan hak dasar yang bisa dinikmati semua orang.</p><div class="keterangan-gradient"></div></div><div class="read-more-btn" id="readMoreBtn"><button onclick="toggleKeterangan()">Baca selengkapnya ▾</button></div></div>';
            } else if (tabName === 'kabar') renderNews();
            else if (tabName === 'donatur') renderDonatur('terbaru');
        }
        function toggleKeterangan() {
            var container = document.getElementById('keteranganContainer');
            var readMoreBtn = document.getElementById('readMoreBtn');
            if (container && readMoreBtn && container.classList.contains('collapsed')) {
                container.classList.remove('collapsed');
                container.classList.add('expanded');
                readMoreBtn.classList.add('hidden');
            }
        }
        function shareLink() {
            if (navigator.share) navigator.share({ title: 'Peduli Akses Kesehatan', url: window.location.href });
            else navigator.clipboard.writeText(window.location.href).then(() => alert('Link disalin!')).catch(() => prompt('Salin link:', window.location.href));
        }

        document.addEventListener('DOMContentLoaded', function () {
            var tabs = document.querySelectorAll('.tab');
            var indicator = document.querySelector('.tab-indicator');
            function updateIndicator(activeTab) { if (indicator && activeTab) { indicator.style.left = activeTab.offsetLeft + 'px'; indicator.style.width = activeTab.offsetWidth + 'px'; } }
            var activeTabOnLoad = document.querySelector('.tab.active');
            if (activeTabOnLoad) updateIndicator(activeTabOnLoad);
            for (var i = 0; i < tabs.length; i++) {
                (function (tab) {
                    tab.addEventListener('click', function () {
                        for (var j = 0; j < tabs.length; j++) tabs[j].classList.remove('active');
                        tab.classList.add('active');
                        openTab(tab.getAttribute('data-tab'));
                        updateIndicator(tab);
                    });
                })(tabs[i]);
            }
            window.addEventListener('resize', function () { var cur = document.querySelector('.tab.active'); if (cur) updateIndicator(cur); });
            openTab('keterangan');
            renderDoaList();
            setTimeout(updateStats, 2000);
        });

        var lastScrollY = window.scrollY;
        window.addEventListener('scroll', function () {
            var nav = document.getElementById('navbar');
            if (nav) nav.classList.toggle('scrolled', window.scrollY > 150);
            var stickyBar = document.querySelector('.sticky-bar');
            if (stickyBar) {
                if (window.scrollY > lastScrollY && window.scrollY > 100) stickyBar.classList.add('visible');
                else stickyBar.classList.remove('visible');
                lastScrollY = window.scrollY;
            }
        });
    </script>
</body>

</html>