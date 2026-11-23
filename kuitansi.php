<?php
// kuitansi.php — Kuitansi Donasi Digital Resmi Dimzz Donasi
require_once 'koneksi.php';
require_once 'notif_helper.php';

$invoice = trim($_GET['invoice'] ?? '');

if (empty($invoice)) {
    die("<h3>Error: Nomor invoice donasi tidak ditentukan.</h3>");
}

// Ambil data donasi
$stmt = mysqli_prepare($conn, "SELECT * FROM donations WHERE invoice_no = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "s", $invoice);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$donasi = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$donasi) {
    die("<h3>Kuitansi tidak ditemukan untuk invoice: " . htmlspecialchars($invoice) . "</h3>");
}

function rupiah($n) {
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

$namaDonatur = (!empty($donasi['is_anonymous'])) ? 'Hamba Allah' : ($donasi['donator_name'] ?: 'Donatur');
$status = strtolower($donasi['payment_status'] ?? 'pending');
$tglBayar = !empty($donasi['paid_at']) ? date('d F Y - H:i', strtotime($donasi['paid_at'])) . ' WIB' : date('d F Y - H:i', strtotime($donasi['created_at'])) . ' WIB';

// Buat tautan share / notifikasi WhatsApp
$waUrl = getTautanWhatsApp($donasi['donator_phone'] ?: '', generateTeksWADonasi($donasi));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kuitansi Donasi #<?= htmlspecialchars($donasi['invoice_no']) ?> — Dimzz</title>
    <link rel="icon" type="image/png" href="logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            min-height: 100vh;
            padding: 30px 16px 60px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .receipt-card {
            background: #ffffff;
            width: 100%;
            max-width: 650px;
            border-radius: 16px;
            box-shadow: 0 10px 35px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            position: relative;
        }

        /* Top Accent Bar */
        .receipt-accent {
            height: 6px;
            background: linear-gradient(90deg, #20b2aa, #0d9488, #0f766e);
        }

        .receipt-header {
            padding: 28px 32px 20px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 1px dashed #cbd5e1;
        }

        .brand-box {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-logo {
            width: 48px;
            height: 48px;
            object-fit: contain;
        }

        .brand-name {
            font-size: 18px;
            font-weight: 800;
            color: #0f766e;
            letter-spacing: -0.5px;
        }

        .brand-sub {
            font-size: 11px;
            color: #64748b;
        }

        .receipt-title-box {
            text-align: right;
        }

        .receipt-title {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .receipt-invoice {
            font-size: 13px;
            font-weight: 700;
            color: #20b2aa;
            margin-top: 3px;
        }

        .receipt-body {
            padding: 24px 32px;
        }

        /* Status Banner */
        .status-badge-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: <?= $status === 'paid' ? '#f0fdf4' : '#fffbeb' ?>;
            border: 1px solid <?= $status === 'paid' ? '#bbf7d0' : '#fde68a' ?>;
            border-radius: 10px;
            padding: 12px 18px;
            margin-bottom: 24px;
        }

        .status-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: <?= $status === 'paid' ? '#16a34a' : '#d97706' ?>;
        }

        .status-label {
            font-size: 14px;
            font-weight: 700;
            color: <?= $status === 'paid' ? '#15803d' : '#b45309' ?>;
        }

        .status-date {
            font-size: 12px;
            color: #64748b;
        }

        /* Data Grid */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px 24px;
            margin-bottom: 24px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .info-label {
            font-size: 11px;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
        }

        /* Table Rincian */
        .item-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }

        .item-table th {
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            border-bottom: 1px solid #cbd5e1;
            padding: 8px 0;
        }

        .item-table th:last-child {
            text-align: right;
        }

        .item-table td {
            padding: 14px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }

        .item-table td:last-child {
            text-align: right;
            font-weight: 700;
        }

        .total-row td {
            border-top: 2px solid #e2e8f0;
            border-bottom: none;
            padding-top: 14px;
            font-size: 16px;
            font-weight: 800;
            color: #0f766e;
        }

        /* Doa Donatur */
        <?php if (!empty($donasi['doa_message'])): ?>
        .prayer-box {
            background: #f8fafc;
            border-left: 4px solid #20b2aa;
            padding: 12px 16px;
            border-radius: 0 8px 8px 0;
            margin-bottom: 24px;
            font-style: italic;
            font-size: 13px;
            color: #475569;
        }
        .prayer-author {
            font-weight: 700;
            font-style: normal;
            font-size: 11px;
            color: #0f766e;
            margin-top: 4px;
        }
        <?php endif; ?>

        /* Stamp & Footer */
        .receipt-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 32px 28px;
            border-top: 1px dashed #cbd5e1;
            background: #fafaf9;
        }

        .stamp-box {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .digital-stamp {
            border: 2px dashed #0f766e;
            border-radius: 8px;
            padding: 6px 14px;
            color: #0f766e;
            font-weight: 800;
            font-size: 12px;
            letter-spacing: 1px;
            text-transform: uppercase;
            transform: rotate(-3deg);
            display: inline-block;
        }

        .footer-note {
            font-size: 11px;
            color: #64748b;
            line-height: 1.4;
            max-width: 320px;
        }

        /* Action Buttons */
        .action-bar {
            width: 100%;
            max-width: 650px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            gap: 12px;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            border: none;
        }

        .btn-print {
            background: #0f766e;
            color: #ffffff;
        }

        .btn-print:hover {
            background: #115e59;
            transform: translateY(-1px);
        }

        .btn-back {
            background: #ffffff;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        .btn-back:hover {
            background: #f8fafc;
        }

        /* PRINT STYLING */
        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .action-bar {
                display: none !important;
            }
            .receipt-card {
                box-shadow: none;
                border: 1px solid #ccc;
                max-width: 100%;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>

    <div class="receipt-card">
        <div class="receipt-accent"></div>

        <div class="receipt-header">
            <div class="brand-box">
                <img src="logo.png" alt="Dimzz Logo" class="brand-logo">
                <div>
                    <div class="brand-name">DIMZZ DONASI</div>
                    <div class="brand-sub">Platform Kebaikan Digital Indonesia</div>
                </div>
            </div>
            <div class="receipt-title-box">
                <div class="receipt-title">Bukti Donasi</div>
                <div class="receipt-invoice">#<?= htmlspecialchars($donasi['invoice_no']) ?></div>
            </div>
        </div>

        <div class="receipt-body">
            <!-- STATUS DONASI -->
            <div class="status-badge-box">
                <div class="status-left">
                    <div class="status-dot"></div>
                    <div class="status-label">
                        <?= $status === 'paid' ? 'LUNAS / TERVERIFIKASI' : 'MENUNGGU PEMBAYARAN' ?>
                    </div>
                </div>
                <div class="status-date"><?= $tglBayar ?></div>
            </div>

            <!-- INFO DONATUR & PROGRAM -->
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Nama Donatur</span>
                    <span class="info-value"><?= htmlspecialchars($namaDonatur) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Metode Pembayaran</span>
                    <span class="info-value"><?= strtoupper(htmlspecialchars($donasi['payment_method'] ?: 'QRIS')) ?></span>
                </div>
                <div class="info-item" style="grid-column: span 2;">
                    <span class="info-label">Program Donasi</span>
                    <span class="info-value"><?= htmlspecialchars($donasi['campaign_name'] ?: 'Donasi Kemanusiaan') ?></span>
                </div>
            </div>

            <!-- RINCIAN TABLE -->
            <table class="item-table">
                <thead>
                    <tr>
                        <th>Deskripsi</th>
                        <th>Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Donasi Program: <?= htmlspecialchars($donasi['campaign_name'] ?: 'Donasi') ?></td>
                        <td><?= rupiah($donasi['nominal_donation'] ?: $donasi['total_amount']) ?></td>
                    </tr>
                    <tr>
                        <td>Biaya Operasional / Admin</td>
                        <td><?= rupiah($donasi['admin_fee'] ?? 0) ?></td>
                    </tr>
                    <tr class="total-row">
                        <td>TOTAL DONASI</td>
                        <td><?= rupiah($donasi['total_amount']) ?></td>
                    </tr>
                </tbody>
            </table>

            <?php if (!empty($donasi['doa_message'])): ?>
            <div class="prayer-box">
                "<?= htmlspecialchars($donasi['doa_message']) ?>"
                <div class="prayer-author">— Doa dari <?= htmlspecialchars($namaDonatur) ?></div>
            </div>
            <?php endif; ?>
        </div>

        <div class="receipt-footer">
            <div class="stamp-box">
                <div class="digital-stamp">
                    VERIFIED · DIMZZ SYSTEM
                </div>
            </div>
            <div class="footer-note">
                Dokumen ini merupakan bukti donasi digital yang sah diterbitkan oleh sistem Dimzz Donasi. Terima kasih atas partisipasi dan kebaikan Anda.
            </div>
        </div>
    </div>

    <!-- ACTION BUTTONS -->
    <div class="action-bar">
        <a href="db.php" class="btn-action btn-back">
            ← Kembali ke Portal
        </a>
        <div style="display:flex;gap:10px;">
            <a href="<?= $waUrl ?>" target="_blank" class="btn-action" style="background:#25D366;color:#ffffff;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.007c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86.174.086.275.072.376-.044.101-.116.433-.506.549-.68.116-.173.231-.145.39-.086.159.058 1.011.477 1.184.564.173.086.289.13.332.202.043.073.043.419-.101.824z"/>
                </svg>
                Kirim ke WhatsApp 💬
            </a>
            <button onclick="window.print()" class="btn-action btn-print">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                Cetak / Simpan PDF
            </button>
        </div>
    </div>

</body>
</html>
