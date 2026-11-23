<?php
// bayardonasipemberdayaan.php - Form donasi Pemberdayaan UMKM
error_reporting(0);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Jakarta'); // ✅ Tambahan: pastikan semua waktu konsisten WIB
header('Content-Type: text/html; charset=utf-8');

require_once 'koneksi.php';
session_start();

$program_id = 4;
$campaign_name_db = 'Saatnya Bangkit Bersama! Peduli Sesama!';

function rupiah($angka)
{
  return "Rp " . number_format($angka, 0, ',', '.');
}

function jsonResponse($data)
{
  if (ob_get_level())
    ob_clean();
  header('Content-Type: application/json');
  echo json_encode($data);
  exit;
}

// Fungsi untuk insert doa dengan auto-detect kolom tabel
function insertDoa($conn, $program_id, $donatorName, $doaMessage)
{
  // Cek struktur tabel
  $checkTable = mysqli_query($conn, "DESCRIBE doa_comments");
  if (!$checkTable) {
    return ['success' => false, 'error' => 'Tabel doa_comments tidak ada: ' . mysqli_error($conn)];
  }

  $columns = [];
  $nullable = [];
  $defaults = [];
  while ($col = mysqli_fetch_assoc($checkTable)) {
    $columns[] = $col['Field'];
    $nullable[$col['Field']] = ($col['Null'] === 'YES');
    $defaults[$col['Field']] = $col['Default'];
  }

  // Bangun INSERT query dinamis
  $insertFields = [];
  $insertValues = [];
  $insertTypes = '';

  // Kolom wajib
  if (in_array('donator_name', $columns)) {
    $insertFields[] = 'donator_name';
    $insertValues[] = $donatorName;
    $insertTypes .= 's';
  }
  if (in_array('message', $columns)) {
    $insertFields[] = 'message';
    $insertValues[] = $doaMessage;
    $insertTypes .= 's';
  }

  // Kolom opsional (tambahkan jika ada)
  if (in_array('aamiin_count', $columns)) {
    $insertFields[] = 'aamiin_count';
    $insertValues[] = 0;
    $insertTypes .= 'i';
  }
  if (in_array('campaign_id', $columns) && !$nullable['campaign_id']) {
    $insertFields[] = 'campaign_id';
    $insertValues[] = 'card-' . $program_id;
    $insertTypes .= 's';
  } elseif (in_array('campaign_id', $columns) && $nullable['campaign_id']) {
    $insertFields[] = 'campaign_id';
    $insertValues[] = 'card-' . $program_id;
    $insertTypes .= 's';
  }
  if (in_array('created_at', $columns)) {
    $insertFields[] = 'created_at';
    $insertValues[] = date('Y-m-d H:i:s'); // ✅ sudah pakai timezone Asia/Jakarta
    $insertTypes .= 's';
  }

  if (empty($insertFields)) {
    return ['success' => false, 'error' => 'Tabel tidak punya kolom donator_name/message', 'columns' => $columns];
  }

  // Eksekusi INSERT
  $placeholders = implode(',', array_fill(0, count($insertFields), '?'));
  $fieldsStr = implode(',', $insertFields);
  $insertDoa = "INSERT INTO doa_comments ($fieldsStr) VALUES ($placeholders)";

  $stmtDoa = mysqli_prepare($conn, $insertDoa);
  if (!$stmtDoa) {
    return ['success' => false, 'error' => 'Prepare gagal: ' . mysqli_error($conn), 'query' => $insertDoa];
  }

  mysqli_stmt_bind_param($stmtDoa, $insertTypes, ...$insertValues);

  if (mysqli_stmt_execute($stmtDoa)) {
    mysqli_stmt_close($stmtDoa);
    return ['success' => true, 'id' => mysqli_insert_id($conn)];
  } else {
    $error = mysqli_stmt_error($stmtDoa);
    mysqli_stmt_close($stmtDoa);
    return ['success' => false, 'error' => $error, 'query' => $insertDoa, 'columns' => $columns];
  }
}

$method = $_SERVER['REQUEST_METHOD'];

// ============ ENDPOINT DEBUG ============
if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'debug') {
  $result = mysqli_query($conn, "DESCRIBE doa_comments");
  $columns = [];
  if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
      $columns[] = $row;
    }
  }

  $sample = mysqli_query($conn, "SELECT * FROM doa_comments ORDER BY id DESC LIMIT 5");
  $samples = [];
  if ($sample) {
    while ($row = mysqli_fetch_assoc($sample)) {
      $samples[] = $row;
    }
  }

  $totalResult = mysqli_query($conn, "SELECT COUNT(*) as total FROM doa_comments");
  $total = $totalResult ? mysqli_fetch_assoc($totalResult)['total'] : 0;

  jsonResponse([
    'success' => true,
    'table_structure' => $columns,
    'sample_data' => $samples,
    'total_rows' => $total
  ]);
}

// ============ ENDPOINT DONASI ============
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'donasi') {
  $input = json_decode(file_get_contents('php://input'), true);

  if (!$input) {
    jsonResponse(['success' => false, 'message' => 'Data tidak valid']);
  }

  $nama = trim($input['nama'] ?? '');
  $phone = trim($input['phone'] ?? '');
  $email = trim($input['email'] ?? '');
  $sapaan = trim($input['sapaan'] ?? 'Bapak');
  $isAnon = !empty($input['is_anonymous']) ? 1 : 0;
  $nominal = (float) ($input['nominal'] ?? 0);
  $fee = (float) ($input['fee'] ?? 0);
  $total = (float) ($input['total'] ?? 0);
  $methodPay = trim($input['method'] ?? '');
  $doa = trim($input['doa'] ?? '');
  $campaignImg = trim($input['campaignImg'] ?? 'donasipemberdayaan.png');

  if ($nominal < 10000) {
    jsonResponse(['success' => false, 'message' => 'Nominal donasi minimal Rp 10.000']);
  }

  $allowedMethods = ['bca', 'bri', 'mandiri', 'gopay', 'ovo', 'qris'];
  if (!in_array($methodPay, $allowedMethods)) {
    jsonResponse(['success' => false, 'message' => 'Metode pembayaran tidak valid']);
  }

  if (empty($nama) || empty($phone)) {
    jsonResponse(['success' => false, 'message' => 'Nama dan nomor HP wajib diisi']);
  }

  $invoice = 'DON-' . date('YmdHis') . '-' . rand(1000, 9999);
  $expiryHours = in_array($methodPay, ['gopay', 'ovo', 'qris']) ? 2 : 24;
  $expiryTime = date('Y-m-d H:i:s', strtotime("+{$expiryHours} hours"));

  $query = "INSERT INTO donations 
        (program_id, invoice_no, campaign_name, donator_name, donator_phone, donator_email, 
         sapaan, is_anonymous, campaign_img, nominal_donation, admin_fee, total_amount,
         payment_method, payment_status, payment_expiry, doa_message)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)";

  $stmt = mysqli_prepare($conn, $query);
  if (!$stmt) {
    jsonResponse(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
  }

  mysqli_stmt_bind_param(
    $stmt,
    'isssssssdddssss',
    $program_id,
    $invoice,
    $campaign_name_db,
    $nama,
    $phone,
    $email,
    $sapaan,
    $isAnon,
    $campaignImg,
    $nominal,
    $fee,
    $total,
    $methodPay,
    $expiryTime,
    $doa
  );

  if (mysqli_stmt_execute($stmt)) {
    $instructions = getPaymentInstructions($methodPay, $total, $invoice);
    jsonResponse([
      'success' => true,
      'invoice' => $invoice,
      'payment_method' => $methodPay,
      'total' => $total,
      'expiry' => $expiryTime,
      'instructions' => $instructions
    ]);
  } else {
    jsonResponse(['success' => false, 'message' => 'Gagal menyimpan: ' . mysqli_stmt_error($stmt)]);
  }
}

// ============ ENDPOINT CEK STATUS ============
if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'cek_status') {
  $invoice = $_GET['invoice'] ?? '';
  if (empty($invoice)) {
    jsonResponse(['success' => false, 'message' => 'Invoice tidak ditemukan']);
  }

  $query = "SELECT payment_status, total_amount, paid_at FROM donations WHERE invoice_no = ?";
  $stmt = mysqli_prepare($conn, $query);
  mysqli_stmt_bind_param($stmt, 's', $invoice);
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);
  $data = mysqli_fetch_assoc($result);

  if ($data) {
    jsonResponse([
      'success' => true,
      'status' => $data['payment_status'],
      'total' => $data['total_amount'],
      'paid_at' => $data['paid_at']
    ]);
  } else {
    jsonResponse(['success' => false, 'message' => 'Invoice tidak ditemukan']);
  }
}

// ============ ENDPOINT AAMIIN ============
if (isset($_GET['action']) && $_GET['action'] === 'aamiin') {
  $input = json_decode(file_get_contents('php://input'), true);
  $comment_id = isset($input['comment_id']) ? (int) $input['comment_id'] : 0;
  if ($comment_id <= 0) {
    jsonResponse(['success' => false, 'message' => 'ID komentar tidak valid']);
  }
  $session_key = 'aamiin_' . $comment_id;
  if (isset($_SESSION[$session_key])) {
    jsonResponse(['success' => false, 'message' => 'Sudah aamiin']);
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
    jsonResponse(['success' => true, 'new_count' => $row['aamiin_count']]);
  } else {
    jsonResponse(['success' => false, 'message' => 'Gagal update aamiin']);
  }
}

// ============ ENDPOINT KONFIRMASI BAYAR ============
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'konfirmasi_bayar') {
  $input = json_decode(file_get_contents('php://input'), true);
  $invoice = $input['invoice'] ?? '';

  if (empty($invoice)) {
    jsonResponse(['success' => false, 'message' => 'Invoice tidak valid']);
  }

  // Waktu sekarang (PHP, sudah Asia/Jakarta) — dipakai untuk paid_at & created_at doa
  $now = date('Y-m-d H:i:s'); // ✅ Tambahan

  // Ambil data donasi
  $query = "SELECT id, donator_name, doa_message, total_amount, program_id FROM donations 
              WHERE invoice_no = ? AND payment_status = 'pending'";
  $stmt = mysqli_prepare($conn, $query);
  mysqli_stmt_bind_param($stmt, 's', $invoice);
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);
  $donation = mysqli_fetch_assoc($result);

  if (!$donation) {
    jsonResponse(['success' => false, 'message' => 'Donasi tidak ditemukan atau sudah dikonfirmasi']);
  }

  // 1. Update status jadi paid (paid_at & created_at disinkronkan ke $now WIB)
  $update = "UPDATE donations SET payment_status = 'paid', paid_at = ?, created_at = ? WHERE invoice_no = ?";
  $stmtUp = mysqli_prepare($conn, $update);
  mysqli_stmt_bind_param($stmtUp, 'sss', $now, $now, $invoice);

  if (!mysqli_stmt_execute($stmtUp)) {
    jsonResponse(['success' => false, 'message' => 'Gagal konfirmasi pembayaran']);
  }
  mysqli_stmt_close($stmtUp); // ✅ tambahan, biar rapi

  // 2. Update program_donasi
  $progId = $donation['program_id'];
  if ($progId) {
    $updateProgram = "UPDATE program_donasi 
            SET donasi_terkumpul = (SELECT COALESCE(SUM(total_amount), 0) FROM donations WHERE program_id = ? AND payment_status = 'paid'),
                jumlah_donatur = (SELECT COUNT(*) FROM donations WHERE program_id = ? AND payment_status = 'paid')
            WHERE id = ?";
    $stmtProg = mysqli_prepare($conn, $updateProgram);
    mysqli_stmt_bind_param($stmtProg, 'iii', $progId, $progId, $progId);
    mysqli_stmt_execute($stmtProg);
    mysqli_stmt_close($stmtProg);
  }

  // 3. ✅ INSERT DOA DENGAN campaign_id + created_at dari PHP (FIXED!)
  $doaInserted = false;
  $doaError = '';

  if (!empty(trim($donation['doa_message']))) {
    $donatorName = trim($donation['donator_name']);
    $doaMessage = trim($donation['doa_message']);
    $campaignId = 'card-' . $progId; // card-4 untuk Pemberdayaan UMKM

    $insertDoa = "INSERT INTO doa_comments (campaign_id, donator_name, message, aamiin_count, created_at) 
                      VALUES (?, ?, ?, 0, ?)"; // ✅ tambah created_at
    $stmtDoa = mysqli_prepare($conn, $insertDoa);

    if ($stmtDoa) {
      mysqli_stmt_bind_param($stmtDoa, 'ssss', $campaignId, $donatorName, $doaMessage, $now); // ✅ ssss + $now
      if (mysqli_stmt_execute($stmtDoa)) {
        $doaInserted = true;
      } else {
        $doaError = mysqli_stmt_error($stmtDoa);
      }
      mysqli_stmt_close($stmtDoa);
    } else {
      $doaError = mysqli_error($conn);
    }

    if (!$doaInserted) {
      error_log('DOA INSERT FAILED: ' . $doaError . ' | Campaign: ' . $campaignId);
    }
  }

  jsonResponse([
    'success' => true,
    'message' => 'Pembayaran dikonfirmasi. Terima kasih!',
    'doa_inserted' => $doaInserted,
    'doa_error' => $doaError
  ]);
}

function getPaymentInstructions($method, $total, $invoice)
{
  $totalFormatted = number_format($total, 0, ',', '.');
  switch ($method) {
    case 'bca':
      return "Transfer ke Virtual Account BCA: <b>8888$invoice</b><br>Total: Rp $totalFormatted";
    case 'bri':
      return "Transfer ke Virtual Account BRI: <b>8888$invoice</b><br>Total: Rp $totalFormatted";
    case 'mandiri':
      return "Transfer ke Virtual Account Mandiri: <b>8888$invoice</b><br>Total: Rp $totalFormatted";
    case 'gopay':
      return "Bayar via GoPay: buka aplikasi Gojek, pilih Bayar, masukkan kode <b>GOPAY$invoice</b>";
    case 'ovo':
      return "Bayar via OVO: buka aplikasi OVO, pilih Bayar, masukkan kode <b>OVO$invoice</b>";
    case 'qris':
      return "Scan QRIS berikut:<br><img src='https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=QRIS$invoice' alt='QR Code' style='margin-top:10px;'>";
    default:
      return "Ikuti instruksi pembayaran yang dikirim ke WhatsApp Anda.";
  }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Donasi - Pemberdayaan UMKM</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
    rel="stylesheet" />
  <style>
    *,
    *::before,
    *::after {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    :root {
      --teal: #20B2AA;
      --teal-dark: #178f88;
      --teal-lite: #e4f7f6;
      --bg: #f0f4f8;
      --white: #ffffff;
      --text: #1a2a3a;
      --muted: #7a9aab;
      --border: #e2eaef;
    }

    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      justify-content: center;
      padding-bottom: 100px;
    }

    .navbar {
      position: fixed;
      top: 0;
      width: 100%;
      max-width: 600px;
      left: 50%;
      transform: translateX(-50%);
      z-index: 999;
      background: var(--teal);
      padding: 14px 18px;
      display: flex;
      align-items: center;
      gap: 12px;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
      transition: background 0.3s;
    }

    .navbar.scrolled {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(8px);
      box-shadow: 0 2px 16px rgba(0, 0, 0, 0.1);
    }

    .back-btn {
      width: 36px;
      height: 36px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(255, 255, 255, 0.2);
      border-radius: 50%;
      cursor: pointer;
      border: none;
      color: #fff;
      flex-shrink: 0;
      text-decoration: none;
    }

    .back-btn:hover {
      background: rgba(255, 255, 255, 0.35);
    }

    .navbar.scrolled .back-btn {
      background: #f0f4f8;
      color: var(--teal);
    }

    .navbar-title {
      font-size: 14px;
      font-weight: 700;
      color: #fff;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .navbar.scrolled .navbar-title {
      color: var(--text);
    }

    .wrapper {
      width: 100%;
      max-width: 600px;
      padding: 80px 0 20px;
      display: flex;
      flex-direction: column;
      gap: 14px;
    }

    .card {
      background: var(--white);
      box-shadow: 0 1px 8px rgba(0, 0, 0, 0.06);
      overflow: hidden;
    }

    .campaign-preview {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 16px 18px;
      border-bottom: 1px solid var(--border);
    }

    .preview-thumb {
      width: 80px;
      height: 60px;
      object-fit: cover;
      border-radius: 8px;
      background: #ddd;
    }

    .preview-info {
      flex: 1;
      min-width: 0;
    }

    .preview-label {
      font-size: 11px;
      font-weight: 700;
      color: var(--teal);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 4px;
    }

    .preview-title {
      font-size: 14px;
      font-weight: 700;
      line-height: 1.35;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      line-clamp: 2;
      overflow: hidden;
    }

    .section-header {
      padding: 18px 18px 4px;
      font-size: 15px;
      font-weight: 700;
    }

    .nominal-section {
      padding: 12px 18px 18px;
    }

    .nominal-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
    }

    .nominal-btn {
      position: relative;
      padding: 14px 8px;
      border: 1.5px solid var(--border);
      border-radius: 10px;
      background: var(--white);
      font-family: inherit;
      font-size: 14px;
      font-weight: 700;
      cursor: pointer;
      text-align: center;
      transition: all 0.2s;
    }

    .nominal-btn:hover,
    .nominal-btn.active {
      border-color: var(--teal);
      background: var(--teal-lite);
      color: var(--teal);
    }

    .nominal-btn.active {
      box-shadow: 0 0 0 3px rgba(32, 178, 170, 0.15);
    }

    .nominal-btn .popular-tag {
      position: absolute;
      top: -10px;
      left: 50%;
      transform: translateX(-50%);
      background: var(--teal);
      color: #fff;
      font-size: 9px;
      font-weight: 700;
      padding: 2px 8px;
      border-radius: 20px;
      white-space: nowrap;
    }

    .nominal-btn.custom-btn {
      color: var(--muted);
      font-weight: 600;
      font-size: 13px;
    }

    .custom-input-wrap {
      margin-top: 12px;
      display: none;
      position: relative;
    }

    .custom-input-wrap.show {
      display: flex;
      align-items: center;
    }

    .custom-input-prefix {
      position: absolute;
      left: 14px;
      font-size: 14px;
      font-weight: 700;
      color: var(--muted);
      pointer-events: none;
    }

    .custom-input {
      width: 100%;
      padding: 14px 14px 14px 40px;
      border: 1.5px solid var(--teal);
      border-radius: 10px;
      font-family: inherit;
      font-size: 15px;
      font-weight: 700;
      background: var(--teal-lite);
    }

    .payment-section {
      padding: 0 18px 18px;
    }

    .payment-box {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 14px 16px;
      background: #eaf8f7;
      border: 1.5px solid var(--border);
      border-radius: 10px;
      cursor: pointer;
    }

    .payment-box.selected,
    .payment-box:hover {
      border-color: var(--teal);
      background: var(--teal-lite);
    }

    .payment-icon {
      width: 44px;
      height: 44px;
      background: var(--white);
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .payment-label {
      flex: 1;
      font-size: 14px;
      font-weight: 600;
    }

    .payment-label small {
      display: block;
      font-size: 11px;
      color: var(--muted);
      margin-top: 2px;
    }

    .payment-select-btn {
      padding: 6px 14px;
      border: 1.5px solid var(--teal);
      border-radius: 20px;
      background: white;
      color: var(--teal);
      font-size: 12px;
      font-weight: 700;
      cursor: pointer;
    }

    .payment-dropdown {
      display: none;
      flex-direction: column;
      gap: 8px;
      margin-top: 10px;
    }

    .payment-dropdown.open {
      display: flex;
    }

    .payment-option {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 14px;
      border: 1.5px solid var(--border);
      border-radius: 10px;
      cursor: pointer;
      background: white;
    }

    .payment-option:hover,
    .payment-option.selected {
      border-color: var(--teal);
      background: var(--teal-lite);
    }

    .payment-check {
      width: 20px;
      height: 20px;
      border: 2px solid var(--border);
      border-radius: 50%;
      margin-left: 4px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .payment-option.selected .payment-check {
      background: var(--teal);
      border-color: var(--teal);
    }

    .bank-logo {
      width: 40px;
      height: 28px;
      border-radius: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 9px;
      font-weight: 800;
      color: white;
    }

    .bank-logo--bca {
      background: #0066AE;
    }

    .bank-logo--bri {
      background: #00529C;
    }

    .bank-logo--mandiri {
      background: #003087;
      font-size: 8px;
    }

    .bank-logo--gopay {
      background: #00AED6;
    }

    .bank-logo--ovo {
      background: #4C3494;
      font-size: 10px;
    }

    .bank-logo--qris {
      background: #E7272D;
    }

    .form-section {
      padding: 0 18px 18px;
    }

    .sapaan-row {
      display: flex;
      gap: 8px;
      margin-bottom: 14px;
      align-items: center;
    }

    .sapaan-label {
      font-size: 14px;
      font-weight: 600;
      color: var(--muted);
      min-width: 64px;
    }

    .sapaan-group {
      display: flex;
      gap: 8px;
      flex: 1;
    }

    .sapaan-btn {
      flex: 1;
      padding: 10px 6px;
      border: 1.5px solid var(--border);
      border-radius: 8px;
      background: white;
      font-family: inherit;
      font-size: 13px;
      font-weight: 600;
      color: var(--muted);
      cursor: pointer;
      text-align: center;
    }

    .sapaan-btn.active {
      background: var(--teal);
      border-color: var(--teal);
      color: white;
    }

    .form-input {
      width: 100%;
      padding: 14px 16px;
      border: 1.5px solid var(--border);
      border-radius: 10px;
      font-family: inherit;
      font-size: 14px;
      margin-bottom: 12px;
    }

    .form-input:focus {
      border-color: var(--teal);
      outline: none;
      box-shadow: 0 0 0 3px rgba(32, 178, 170, 0.12);
    }

    .toggle-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 12px 0;
      margin-bottom: 12px;
      border-top: 1px solid var(--border);
      border-bottom: 1px solid var(--border);
    }

    .toggle-label {
      font-size: 14px;
      font-weight: 500;
    }

    .toggle-label small {
      display: block;
      font-size: 11px;
      color: var(--muted);
      margin-top: 2px;
    }

    .toggle-switch {
      position: relative;
      width: 48px;
      height: 26px;
      cursor: pointer;
    }

    .toggle-switch input {
      display: none;
    }

    .toggle-track {
      position: absolute;
      inset: 0;
      background: #dde6ea;
      border-radius: 13px;
      transition: background 0.3s;
    }

    .toggle-switch input:checked+.toggle-track {
      background: var(--teal);
    }

    .toggle-thumb {
      position: absolute;
      top: 3px;
      left: 3px;
      width: 20px;
      height: 20px;
      background: white;
      border-radius: 50%;
      transition: transform 0.3s;
    }

    .toggle-switch input:checked~.toggle-thumb {
      transform: translateX(22px);
    }

    .doa-section {
      padding: 0 18px 18px;
    }

    .doa-textarea {
      width: 100%;
      padding: 14px 16px;
      border: 1.5px solid var(--border);
      border-radius: 10px;
      font-family: inherit;
      font-size: 14px;
      resize: none;
      height: 90px;
    }

    .doa-char {
      text-align: right;
      font-size: 11px;
      color: var(--muted);
      margin-top: 6px;
    }

    .summary-section {
      padding: 16px 18px;
      background: #f8fbfc;
    }

    .summary-row {
      display: flex;
      justify-content: space-between;
      font-size: 13px;
      padding: 6px 0;
      color: var(--muted);
      font-weight: 500;
    }

    .summary-row.total {
      font-size: 15px;
      font-weight: 800;
      color: var(--text);
      border-top: 1.5px solid var(--border);
      margin-top: 6px;
      padding-top: 12px;
    }

    .summary-row.total .summary-val {
      color: var(--teal);
    }

    .sticky-btn {
      position: fixed;
      bottom: 0;
      left: 50%;
      transform: translateX(-50%);
      width: 100%;
      max-width: 600px;
      background: white;
      padding: 12px 18px 16px;
      box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
      z-index: 998;
    }

    .pay-btn {
      width: 100%;
      padding: 16px;
      background: var(--teal);
      color: white;
      border: none;
      border-radius: 12px;
      font-family: inherit;
      font-size: 16px;
      font-weight: 800;
      cursor: pointer;
    }

    .pay-btn:hover {
      background: var(--teal-dark);
    }

    .pay-btn:disabled {
      background: #b0c4cc;
      cursor: not-allowed;
    }

    .pay-note {
      text-align: center;
      font-size: 11px;
      color: var(--muted);
      margin-top: 6px;
    }

    @keyframes modalSlideUp {
      from {
        transform: translateY(100%);
      }

      to {
        transform: translateY(0);
      }
    }

    @keyframes modalFadeBg {
      from {
        opacity: 0;
      }

      to {
        opacity: 1;
      }
    }

    .modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.5);
      z-index: 9999;
      display: flex;
      align-items: flex-end;
      justify-content: center;
      animation: modalFadeBg 0.3s;
    }

    .modal-sheet {
      background: white;
      border-radius: 20px 20px 0 0;
      width: 100%;
      max-width: 600px;
      padding: 24px 20px 32px;
      animation: modalSlideUp 0.35s;
    }

    .modal-handle {
      width: 40px;
      height: 4px;
      background: #dde;
      border-radius: 2px;
      margin: 0 auto 20px;
    }

    .modal-detail-card {
      background: #f8fbfc;
      border-radius: 12px;
      padding: 16px;
      margin-bottom: 16px;
    }

    .modal-detail-row {
      display: flex;
      justify-content: space-between;
      font-size: 13px;
      padding: 5px 0;
      color: #7a9aab;
      font-weight: 500;
    }

    .modal-detail-row span:last-child {
      color: #1a2a3a;
      font-weight: 700;
    }

    .modal-detail-row.total {
      font-size: 15px;
      font-weight: 800;
      border-top: 1.5px solid #e2eaef;
      margin-top: 6px;
      padding-top: 10px;
    }

    .modal-detail-row.total span:last-child {
      color: #20B2AA;
    }

    .modal-campaign-row {
      display: flex;
      gap: 12px;
      align-items: center;
      margin-bottom: 14px;
      padding-bottom: 14px;
      border-bottom: 1px solid #e2eaef;
    }

    .modal-campaign-row img {
      width: 64px;
      height: 48px;
      object-fit: cover;
      border-radius: 8px;
    }

    .modal-doa {
      font-size: 13px;
      color: #7a9aab;
      font-style: italic;
      margin-bottom: 16px;
      padding: 10px 14px;
      background: #f0fffe;
      border-radius: 8px;
      border-left: 3px solid #20B2AA;
    }

    .modal-btn-primary {
      width: 100%;
      padding: 15px;
      background: #20B2AA;
      color: white;
      border: none;
      border-radius: 12px;
      font-weight: 800;
      cursor: pointer;
      margin-bottom: 10px;
    }

    .modal-btn-secondary {
      width: 100%;
      padding: 13px;
      background: transparent;
      color: #7a9aab;
      border: 1.5px solid #e2eaef;
      border-radius: 12px;
      font-weight: 600;
      cursor: pointer;
    }

    @media (max-width: 480px) {
      .nominal-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }
  </style>
</head>

<body>
  <nav class="navbar" id="navbar">
    <a href="donasipemberdayaan.php" class="back-btn">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <path d="M19 12H5M12 5l-7 7 7 7" />
      </svg>
    </a>
    <span class="navbar-title">BANGKIT BERSAMA! DUKUNG PEMBERDAYAAN!</span>
  </nav>
  <div class="wrapper">
    <div class="card">
      <div class="campaign-preview">
        <img class="preview-thumb" src="donasipemberdayaan.png"
          onerror="this.src='https://images.unsplash.com/photo-1559027615-cd4628902d4a?w=300&q=70'"
          alt="Pemberdayaan UMKM" />
        <div class="preview-info">
          <div class="preview-label">Pemberdayaan</div>
          <div class="preview-title">SAATNYA BANGKIT BERSAMA! PEDULI SESAMA!</div>
        </div>
      </div>
    </div>
    <div class="card">
      <div class="section-header">Donasi Sekarang</div>
      <div class="nominal-section">
        <div class="nominal-grid">
          <button class="nominal-btn" data-value="50000" onclick="selectNominal(this)">Rp 50.000</button>
          <button class="nominal-btn" data-value="100000" onclick="selectNominal(this)">Rp 100.000</button>
          <button class="nominal-btn active" data-value="200000" onclick="selectNominal(this)"><span
              class="popular-tag">sering dipilih</span>Rp 200.000</button>
          <button class="nominal-btn" data-value="500000" onclick="selectNominal(this)">Rp 500.000</button>
          <button class="nominal-btn" data-value="1000000" onclick="selectNominal(this)">Rp 1.000.000</button>
          <button class="nominal-btn custom-btn" data-value="custom"
            onclick="selectNominal(this)">Nominal<br><small>lainnya</small></button>
        </div>
        <div class="custom-input-wrap" id="customWrap">
          <span class="custom-input-prefix">Rp</span>
          <input type="number" class="custom-input" id="customInput" placeholder="Masukkan nominal lain" min="10000"
            step="1000" oninput="updateSummary()" />
        </div>
      </div>
    </div>
    <div class="card">
      <div class="section-header">Metode Pembayaran</div>
      <div class="payment-section">
        <div class="payment-box" id="paymentBox" onclick="togglePaymentDropdown()">
          <div class="payment-icon"><svg width="24" height="24" fill="none" stroke="#20B2AA" stroke-width="2"
              viewBox="0 0 24 24">
              <rect x="2" y="5" width="20" height="14" rx="3" />
              <path d="M2 10h20" />
            </svg></div>
          <div class="payment-label" id="paymentLabel">Metode Pembayaran<small id="paymentSub">Pilih metode pembayaran
              Anda</small></div>
          <button class="payment-select-btn" id="paymentSelectBtn">Pilih ▾</button>
        </div>
        <div class="payment-dropdown" id="paymentDropdown">
          <div class="payment-option" data-method="bca" data-fee="0" onclick="selectPayment(this)">
            <div class="bank-logo bank-logo--bca">BCA</div><span class="payment-option-name">Transfer BCA</span><span
              class="payment-option-fee">Gratis</span>
            <div class="payment-check"><svg width="10" height="10" fill="none" stroke="#fff" stroke-width="3"
                viewBox="0 0 24 24">
                <path d="M20 6 9 17 4 12" />
              </svg></div>
          </div>
          <div class="payment-option" data-method="bri" data-fee="0" onclick="selectPayment(this)">
            <div class="bank-logo bank-logo--bri">BRI</div><span class="payment-option-name">Transfer BRI</span><span
              class="payment-option-fee">Gratis</span>
            <div class="payment-check"><svg width="10" height="10" fill="none" stroke="#fff" stroke-width="3"
                viewBox="0 0 24 24">
                <path d="M20 6 9 17 4 12" />
              </svg></div>
          </div>
          <div class="payment-option" data-method="mandiri" data-fee="0" onclick="selectPayment(this)">
            <div class="bank-logo bank-logo--mandiri">MANDIRI</div><span class="payment-option-name">Transfer
              Mandiri</span><span class="payment-option-fee">Gratis</span>
            <div class="payment-check"><svg width="10" height="10" fill="none" stroke="#fff" stroke-width="3"
                viewBox="0 0 24 24">
                <path d="M20 6 9 17 4 12" />
              </svg></div>
          </div>
          <div class="payment-option" data-method="gopay" data-fee="1000" onclick="selectPayment(this)">
            <div class="bank-logo bank-logo--gopay">GoPay</div><span class="payment-option-name">GoPay</span><span
              class="payment-option-fee">+Rp 1.000</span>
            <div class="payment-check"><svg width="10" height="10" fill="none" stroke="#fff" stroke-width="3"
                viewBox="0 0 24 24">
                <path d="M20 6 9 17 4 12" />
              </svg></div>
          </div>
          <div class="payment-option" data-method="ovo" data-fee="1000" onclick="selectPayment(this)">
            <div class="bank-logo bank-logo--ovo">OVO</div><span class="payment-option-name">OVO</span><span
              class="payment-option-fee">+Rp 1.000</span>
            <div class="payment-check"><svg width="10" height="10" fill="none" stroke="#fff" stroke-width="3"
                viewBox="0 0 24 24">
                <path d="M20 6 9 17 4 12" />
              </svg></div>
          </div>
          <div class="payment-option" data-method="qris" data-fee="500" onclick="selectPayment(this)">
            <div class="bank-logo bank-logo--qris">QRIS</div><span class="payment-option-name">QRIS</span><span
              class="payment-option-fee">+Rp 500</span>
            <div class="payment-check"><svg width="10" height="10" fill="none" stroke="#fff" stroke-width="3"
                viewBox="0 0 24 24">
                <path d="M20 6 9 17 4 12" />
              </svg></div>
          </div>
        </div>
      </div>
    </div>
    <div class="card">
      <div class="section-header">Data Donatur</div>
      <div class="form-section">
        <div class="sapaan-row"><span class="sapaan-label">Sapaan :</span>
          <div class="sapaan-group">
            <button class="sapaan-btn active" onclick="selectSapaan(this)">Bapak</button>
            <button class="sapaan-btn" onclick="selectSapaan(this)">Ibu</button>
            <button class="sapaan-btn" onclick="selectSapaan(this)">Kak</button>
          </div>
        </div>
        <input type="text" class="form-input" id="namaInput" placeholder="Nama Lengkap" oninput="updateNamaToggle()" />
        <div class="toggle-row">
          <div class="toggle-label">Sembunyikan nama saya<small id="anonLabel">(tampil sebagai nama Anda)</small></div>
          <label class="toggle-switch"><input type="checkbox" id="anonToggle" onchange="updateNamaToggle()" />
            <div class="toggle-track"></div>
            <div class="toggle-thumb"></div>
          </label>
        </div>
        <input type="tel" class="form-input" id="phoneInput" placeholder="No WhatsApp atau Handphone" />
        <input type="email" class="form-input" id="emailInput" placeholder="Email (opsional)" />
      </div>
    </div>
    <div class="card">
      <div class="section-header">Doa & Pesan <span
          style="color:var(--muted);font-weight:500;font-size:13px;">(opsional)</span></div>
      <div class="doa-section">
        <textarea class="doa-textarea" id="doaInput"
          placeholder="Tulis doa atau pesan semangat untuk para pelaku UMKM..." maxlength="300"
          oninput="updateDoaChar()"></textarea>
        <div class="doa-char"><span id="doaCharCount">0</span>/300</div>
      </div>
    </div>
    <div class="card">
      <div class="section-header">Ringkasan</div>
      <div class="summary-section">
        <div class="summary-row"><span>Nominal Donasi</span><span id="summaryNominal">Rp 200.000</span></div>
        <div class="summary-row"><span>Biaya Layanan</span><span id="summaryFee">Rp 0</span></div>
        <div class="summary-row total"><span>Total Pembayaran</span><span class="summary-val" id="summaryTotal">Rp
            200.000</span></div>
      </div>
    </div>
  </div>
  <div class="sticky-btn">
    <button class="pay-btn" id="payBtn" onclick="prosesDonasiForm()">Donasi Sekarang — <span id="payBtnAmount">Rp
        200.000</span></button>
    <p class="pay-note">🔒 Pembayaran aman & terenkripsi. Donasi Anda 100% tersalurkan.</p>
  </div>

  <script>
    var selectedNominal = 200000;
    var selectedMethod = null;
    var selectedFee = 0;

    function rupiah(n) { return 'Rp ' + n.toLocaleString('id-ID'); }

    function selectNominal(btn) {
      document.querySelectorAll('.nominal-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      var val = btn.dataset.value;
      var wrap = document.getElementById('customWrap');
      if (val === 'custom') {
        wrap.classList.add('show');
        selectedNominal = parseInt(document.getElementById('customInput').value) || 0;
      } else {
        wrap.classList.remove('show');
        selectedNominal = parseInt(val);
      }
      updateSummary();
    }

    document.getElementById('customInput').addEventListener('input', function () {
      selectedNominal = parseInt(this.value) || 0;
      updateSummary();
    });

    function togglePaymentDropdown() { document.getElementById('paymentDropdown').classList.toggle('open'); }

    function selectPayment(el) {
      document.querySelectorAll('.payment-option').forEach(o => o.classList.remove('selected'));
      el.classList.add('selected');
      selectedMethod = el.dataset.method;
      selectedFee = parseInt(el.dataset.fee) || 0;
      var name = el.querySelector('.payment-option-name').textContent;
      document.getElementById('paymentLabel').childNodes[0].textContent = name + ' ';
      document.getElementById('paymentSub').innerHTML = selectedFee > 0 ? 'Biaya layanan: ' + rupiah(selectedFee) : 'Tanpa biaya tambahan';
      document.getElementById('paymentSelectBtn').textContent = 'Ubah ▾';
      document.getElementById('paymentBox').classList.add('selected');
      document.getElementById('paymentDropdown').classList.remove('open');
      updateSummary();
    }

    function selectSapaan(btn) {
      document.querySelectorAll('.sapaan-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
    }

    function updateNamaToggle() {
      var nama = document.getElementById('namaInput').value.trim();
      var isAnon = document.getElementById('anonToggle').checked;
      document.getElementById('anonLabel').innerHTML = isAnon ? '(tampil sebagai "Orang Baik")' : '(tampil sebagai ' + (nama || 'nama Anda') + ')';
    }

    function updateDoaChar() { document.getElementById('doaCharCount').innerText = document.getElementById('doaInput').value.length; }

    function updateSummary() {
      var total = selectedNominal + selectedFee;
      document.getElementById('summaryNominal').innerText = rupiah(selectedNominal);
      document.getElementById('summaryFee').innerText = rupiah(selectedFee);
      document.getElementById('summaryTotal').innerText = rupiah(total);
      document.getElementById('payBtnAmount').innerText = rupiah(total);
      document.getElementById('payBtn').disabled = selectedNominal < 10000;
    }

    function prosesDonasiForm() {
      var nama = document.getElementById('namaInput').value.trim();
      var phone = document.getElementById('phoneInput').value.trim();

      if (!selectedMethod) { alert('Silakan pilih metode pembayaran terlebih dahulu.'); return; }
      if (!nama) { alert('Mohon isi nama lengkap Anda.'); document.getElementById('namaInput').focus(); return; }
      if (!phone) { alert('Mohon isi nomor WhatsApp / HP Anda.'); document.getElementById('phoneInput').focus(); return; }

      var isAnon = document.getElementById('anonToggle').checked;
      var sapaan = document.querySelector('.sapaan-btn.active').innerText.trim();
      var doa = document.getElementById('doaInput').value.trim();
      var total = selectedNominal + selectedFee;
      var namaDisplay = isAnon ? 'Orang Baik' : sapaan + ' ' + nama;
      var email = document.getElementById('emailInput').value.trim();

      var orderData = {
        nama: namaDisplay,
        phone: phone,
        email: email,
        sapaan: sapaan,
        is_anonymous: isAnon,
        nominal: selectedNominal,
        fee: selectedFee,
        total: total,
        method: selectedMethod,
        doa: doa,
        campaign: 'Pemberdayaan UMKM',
        campaignImg: 'donasipemberdayaan.png'
      };

      sessionStorage.setItem('orderData', JSON.stringify(orderData));
      showConfirmModal(orderData);
    }

    var methodNames = { bca: 'BCA Virtual Account', bri: 'BRI Virtual Account', mandiri: 'Mandiri Virtual Account', gopay: 'GoPay', ovo: 'OVO', qris: 'QRIS' };

    function showConfirmModal(d) {
      var existing = document.getElementById('confirmModal');
      if (existing) existing.remove();

      var overlay = document.createElement('div');
      overlay.id = 'confirmModal';
      overlay.className = 'modal-overlay';

      var feeRow = d.fee > 0 ? '<div class="modal-detail-row"><span>Biaya Layanan</span><span>' + rupiah(d.fee) + '</span></div>' : '';
      var doaBlock = d.doa ? '<div class="modal-doa">🤲 "' + d.doa + '"</div>' : '';

      overlay.innerHTML = '<div class="modal-sheet"><div class="modal-handle"></div><h3 style="font-size:17px;font-weight:800;margin-bottom:16px;">Konfirmasi Donasi</h3><div class="modal-detail-card"><div class="modal-campaign-row"><img src="donasipemberdayaan.png" onerror="this.src=\'https://images.unsplash.com/photo-1559027615-cd4628902d4a?w=200&q=70\'" /><div>SAATNYA BANGKIT BERSAMA! PEDULI SESAMA!</div></div><div class="modal-detail-row"><span>Atas Nama</span><span>' + d.nama + '</span></div><div class="modal-detail-row"><span>Metode</span><span>' + (methodNames[d.method] || d.method) + '</span></div><div class="modal-detail-row"><span>Nominal</span><span>' + rupiah(d.nominal) + '</span></div>' + feeRow + '<div class="modal-detail-row total"><span>Total</span><span>' + rupiah(d.total) + '</span></div></div>' + doaBlock + '<button class="modal-btn-primary" onclick="konfirmasiDonasi()">Konfirmasi & Bayar ' + rupiah(d.total) + '</button><button class="modal-btn-secondary" onclick="document.getElementById(\'confirmModal\').remove()">Kembali</button></div>';

      overlay.addEventListener('click', function (e) { if (e.target === overlay) overlay.remove(); });
      document.body.appendChild(overlay);
    }

    async function konfirmasiDonasi() {
      document.getElementById('confirmModal').remove();
      var orderData = JSON.parse(sessionStorage.getItem('orderData'));
      var payBtn = document.getElementById('payBtn');
      var originalText = payBtn.innerHTML;
      payBtn.innerHTML = 'Memproses...';
      payBtn.disabled = true;

      try {
        var response = await fetch(window.location.pathname + '?action=donasi', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(orderData)
        });

        var contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
          var text = await response.text();
          console.error('Response bukan JSON:', text.substring(0, 200));
          alert('Server error. Silakan coba lagi.');
          payBtn.innerHTML = originalText;
          payBtn.disabled = false;
          return;
        }

        var result = await response.json();
        if (result.success) {
          sessionStorage.setItem('currentInvoice', result.invoice);
          showPaymentInstructions(result);
        } else {
          alert('Gagal: ' + result.message);
          payBtn.innerHTML = originalText;
          payBtn.disabled = false;
        }
      } catch (err) {
        console.error('Error:', err);
        alert('Terjadi kesalahan: ' + err.message);
        payBtn.innerHTML = originalText;
        payBtn.disabled = false;
      }
    }

    function showPaymentInstructions(data) {
      var modal = document.getElementById('paymentInstructionModal');
      if (modal) modal.remove();

      var overlay = document.createElement('div');
      overlay.id = 'paymentInstructionModal';
      overlay.className = 'modal-overlay';
      overlay.innerHTML = '<div class="modal-sheet" style="max-width:500px;"><div class="modal-handle"></div><h3 style="font-size:18px;font-weight:800;">Instruksi Pembayaran</h3><div class="modal-detail-card"><p><strong>Invoice:</strong> ' + data.invoice + '</p><p><strong>Total:</strong> ' + rupiah(data.total) + '</p><p><strong>Metode:</strong> ' + data.payment_method.toUpperCase() + '</p><p><strong>Batas Bayar:</strong> ' + new Date(data.expiry).toLocaleString() + '</p><div style="background:#f8fbfc; padding:12px; border-radius:8px; margin-top:12px;">' + data.instructions + '</div></div><button class="modal-btn-primary" id="confirmPaymentBtn" style="background:#28a745;">✅ Saya Sudah Membayar</button><button class="modal-btn-primary" id="checkPaymentBtn">Cek Status Pembayaran</button><button class="modal-btn-secondary" onclick="closeInstructionModal()">Tutup</button><p style="font-size:12px; text-align:center; margin-top:12px;">Setelah membayar, klik "Saya Sudah Membayar" untuk konfirmasi.</p></div>';

      document.body.appendChild(overlay);
      document.getElementById('checkPaymentBtn').onclick = () => pollPaymentStatus(data.invoice);
      document.getElementById('confirmPaymentBtn').onclick = () => konfirmasiManual(data.invoice);
      startPolling(data.invoice);
    }

    async function konfirmasiManual(invoice) {
      try {
        var resp = await fetch(window.location.pathname + '?action=konfirmasi_bayar', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ invoice: invoice })
        });

        var contentType = resp.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
          var text = await resp.text();
          console.error('Response bukan JSON:', text.substring(0, 200));
          alert('Server error. Silakan coba lagi.');
          return;
        }

        var result = await resp.json();

        if (result.success) {
          clearInterval(pollingInterval);
          closeInstructionModal();

          var message = '✅ Donasi berhasil dikonfirmasi!\n\nTerima kasih atas kebaikan Anda.';

          if (result.doa_inserted === false) {
            console.warn('Doa gagal disimpan:', result.doa_error);
            message += '\n\n⚠️ Catatan: Doa Anda tidak tersimpan, tapi donasi sudah tercatat.';
          } else if (result.doa_inserted === true) {
            message += '\n\n🤲 Doa Anda telah tersimpan.';
          }

          alert(message + '\n\nMenampilkan kuitansi donasi digital Anda...');
          window.location.href = 'kuitansi.php?invoice=' + encodeURIComponent(invoice);
        } else {
          alert('❌ Gagal: ' + result.message);
        }
      } catch (err) {
        console.error('Error:', err);
        alert('Error: ' + err.message);
      }
    }

    var pollingInterval = null;

    function startPolling(invoice) {
      if (pollingInterval) clearInterval(pollingInterval);
      pollingInterval = setInterval(async () => {
        var status = await checkPaymentStatus(invoice);
        if (status === 'paid') {
          clearInterval(pollingInterval);
          closeInstructionModal();
          window.location.href = 'donasipemberdayaan.php';
        } else if (status === 'expired') {
          clearInterval(pollingInterval);
          alert('Waktu pembayaran habis. Silakan donasi ulang.');
          closeInstructionModal();
        }
      }, 10000);
    }

    async function checkPaymentStatus(invoice) {
      try {
        var res = await fetch(window.location.pathname + '?action=cek_status&invoice=' + invoice);
        var data = await res.json();
        if (data.success) return data.status;
        return 'unknown';
      } catch (e) { return 'unknown'; }
    }

    async function pollPaymentStatus(invoice) {
      var status = await checkPaymentStatus(invoice);
      if (status === 'paid') {
        clearInterval(pollingInterval);
        closeInstructionModal();
        window.location.href = 'donasipemberdayaan.php';
      } else if (status === 'expired') {
        clearInterval(pollingInterval);
        alert('Pembayaran sudah kadaluarsa.');
        closeInstructionModal();
      } else {
        alert('Pembayaran masih pending. Silakan selesaikan pembayaran Anda.');
      }
    }

    function closeInstructionModal() {
      var modal = document.getElementById('paymentInstructionModal');
      if (modal) modal.remove();
      if (pollingInterval) clearInterval(pollingInterval);
    }

    window.addEventListener('scroll', function () {
      var nav = document.getElementById('navbar');
      if (nav) nav.classList.toggle('scrolled', window.scrollY > 60);
    });

    updateSummary();
  </script>
</body>

</html>