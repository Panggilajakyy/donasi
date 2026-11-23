<?php
/**
 * api_notif_donasi.php
 * Endpoint untuk mengambil donasi terbaru (paid) guna notifikasi real-time.
 * Letakkan file ini di root project yang sama dengan db.php / koneksi.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

include 'koneksi.php';

// Ambil 10 donasi terbaru yang sudah paid
$sql = "
    SELECT
        donator_name,
        is_anonymous,
        campaign_name,
        campaign_img,
        nominal_donation,
        paid_at,
        sapaan
    FROM donations
    WHERE payment_status = 'paid'
    ORDER BY paid_at DESC
    LIMIT 10
";

$result = mysqli_query($conn, $sql);

$list = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {

        // ── Nama: sembunyikan jika anonim ────────────────────
        if ($row['is_anonymous']) {
            $nama = 'Hamba Allah';
        } else {
            $nama = trim($row['donator_name']) ?: 'Seseorang';
            $sapaan = trim($row['sapaan'] ?? '');

            // Tambahkan sapaan HANYA jika valid DAN nama belum diawali sapaan itu
            if ($sapaan && in_array($sapaan, ['Bapak', 'Ibu', 'Kak'])) {
                $sudahAdaSapaan = stripos($nama, $sapaan) === 0;

                if (!$sudahAdaSapaan) {
                    $nama = $sapaan . ' ' . $nama;
                }
            }
        }

        // ── Inisial avatar (1 karakter pertama nama asli / 'A' jika anonim) ──
        $inisial = $row['is_anonymous']
            ? 'A'
            : strtoupper(mb_substr(trim($row['donator_name']) ?: 'A', 0, 1));

        // ── Format nominal ────────────────────────────────────
        $nom = (float) $row['nominal_donation'];
        if ($nom >= 1_000_000_000)
            $nom_label = 'Rp ' . round($nom / 1_000_000_000, 1) . ' M';
        elseif ($nom >= 1_000_000)
            $nom_label = 'Rp ' . round($nom / 1_000_000) . ' jt';
        elseif ($nom >= 1_000)
            $nom_label = 'Rp ' . round($nom / 1_000) . ' rb';
        else
            $nom_label = 'Rp ' . number_format($nom, 0, ',', '.');

        // ── Waktu relatif ─────────────────────────────────────
        $paid_ts = strtotime($row['paid_at']);
        $diff_sec = time() - $paid_ts;
        if ($diff_sec < 60)
            $waktu = 'Baru saja';
        elseif ($diff_sec < 3600)
            $waktu = round($diff_sec / 60) . ' menit yang lalu';
        elseif ($diff_sec < 86400)
            $waktu = round($diff_sec / 3600) . ' jam yang lalu';
        else
            $waktu = round($diff_sec / 86400) . ' hari yang lalu';

        $list[] = [
            'nama' => $nama,
            'inisial' => $inisial,
            'campaign' => $row['campaign_name'] ?? '',
            'img' => $row['campaign_img'] ?? '',
            'nominal' => $nom_label,
            'waktu' => $waktu,
        ];
    }
}

echo json_encode($list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);