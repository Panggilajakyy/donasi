<?php
// notif_helper.php — Helper Notifikasi WhatsApp & Email Dimzz Donasi

/**
 * Format nomor HP Indonesia menjadi format internasional (62xxxx).
 * @param string $phone
 * @return string
 */
function formatNomorWA(string $phone): string
{
    // Hapus karakter non-digit
    $cleaned = preg_replace('/[^0-9]/', '', $phone);

    // Ubah 08xxx menjadi 628xxx
    if (str_starts_with($cleaned, '0')) {
        $cleaned = '62' . substr($cleaned, 1);
    } elseif (str_starts_with($cleaned, '8')) {
        $cleaned = '62' . $cleaned;
    }

    return $cleaned;
}

/**
 * Buat teks pesan WhatsApp resmi untuk konfirmasi donasi berhasil.
 * @param array $donasi Data dari tabel donations
 * @return string
 */
function generateTeksWADonasi(array $donasi): string
{
    $nama = (!empty($donasi['is_anonymous'])) ? 'Hamba Allah' : ($donasi['donator_name'] ?: 'Donatur');
    $nominal = 'Rp ' . number_format((float)($donasi['nominal_donation'] ?: $donasi['total_amount']), 0, ',', '.');
    $program = $donasi['campaign_name'] ?: 'Program Kebaikan Dimzz';
    $invoice = $donasi['invoice_no'];
    $metode = strtoupper($donasi['payment_method'] ?: 'QRIS');

    // Buat URL kuitansi
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $receiptUrl = $protocol . $host . "/donasi/kuitansi.php?invoice=" . urlencode($invoice);

    $msg = "✨ *DIMZZ DONASI — BUKTI DONASI RESMI* ✨\n\n";
    $msg .= "Assalamu’alaikum Warahmatullahi Wabarakatuh,\n";
    $msg .= "Terima kasih banyak, *" . $nama . "*!\n\n";
    $msg .= "Alhamdulillah, donasi Anda telah kami terima dengan rincian sebagai berikut:\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━\n";
    $msg .= "📋 *No. Invoice* : #" . $invoice . "\n";
    $msg .= "🎯 *Program*    : " . $program . "\n";
    $msg .= "💰 *Nominal*    : *" . $nominal . "*\n";
    $msg .= "💳 *Metode*     : " . $metode . "\n";
    $msg .= "🟢 *Status*     : LUNAS / TERVERIFIKASI\n";
    $msg .= "━━━━━━━━━━━━━━━━━━━━\n\n";

    if (!empty($donasi['doa_message'])) {
        $msg .= "🤲 *Doa/Pesan Anda:*\n";
        $msg .= "“" . $donasi['doa_message'] . "”\n\n";
    }

    $msg .= "📄 *Lihat / Unduh Kuitansi Sah Digital:*\n";
    $msg .= $receiptUrl . "\n\n";
    $msg .= "Semoga Allah SWT membalas kebaikan Anda dengan pahala yang berlipat ganda, melapangkan rezeki, dan memberikan keberkahan bagi keluarga. Aamiin ya Rabbal 'Alamin.\n\n";
    $msg .= "_Pesan otomatis resmi diterbitkan oleh Platform Dimzz Donasi._";

    return $msg;
}

/**
 * Buat tautan langsung WhatsApp Click-to-Chat.
 * @param string $phone
 * @param string $pesan
 * @return string
 */
function getTautanWhatsApp(string $phone, string $pesan): string
{
    $formattedPhone = formatNomorWA($phone);
    return "https://api.whatsapp.com/send?phone=" . $formattedPhone . "&text=" . urlencode($pesan);
}
