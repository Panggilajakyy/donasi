<?php
session_start();
include 'koneksi.php';

// ── Guard: hanya admin ────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
$stmt = mysqli_prepare($conn, "SELECT role, full_name FROM users WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$me = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$me || $me['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// ── Logout ────────────────────────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// ── Halaman aktif ─────────────────────────────────────────────────────────────
$page = $_GET['page'] ?? 'dashboard';
$allowed = ['dashboard', 'donasi', 'users', 'pengumuman', 'doa', 'program', 'kategori', 'payment'];
if (!in_array($page, $allowed))
    $page = 'dashboard';

// ── Helper ────────────────────────────────────────────────────────────────────
function rp($n)
{
    return 'Rp ' . number_format((float) $n, 0, ',', '.');
}
function qval($conn, $sql)
{
    return mysqli_fetch_row(mysqli_query($conn, $sql))[0] ?? 0;
}
function esc($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES);
}
function pill($label, $cls)
{
    return "<span class=\"pill pill-$cls\">$label</span>";
}

function statusDonasi($s)
{
    return match ($s) {
        'paid' => pill('Lunas', 'green'),
        'pending' => pill('Menunggu', 'amber'),
        'expired' => pill('Kadaluarsa', 'red'),
        'failed' => pill('Gagal', 'red'),
        default => pill(esc($s), 'gray'),
    };
}
function statusProgram($s)
{
    return match ($s) {
        'aktif' => pill('Aktif', 'green'),
        'selesai' => pill('Selesai', 'gray'),
        'draft' => pill('Draft', 'amber'),
        default => pill(esc($s), 'gray'),
    };
}
function tipePill($t)
{
    return match ($t) {
        'hadist' => pill('Hadist', 'purple'),
        'quote' => pill('Quote', 'blue'),
        'pengumuman' => pill('Pengumuman', 'amber'),
        default => pill(esc($t), 'gray'),
    };
}

// ── Aksi per halaman ──────────────────────────────────────────────────────────
$msg = '';
$err = '';
$edit = null;

// ---- DONASI: ekspor CSV
if ($page === 'donasi' && isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="donasi_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    // Kolom sesuai schema donations aktual (termasuk sapaan, is_anonymous, doa_message)
    fputcsv($out, ['Invoice', 'Sapaan', 'Donatur', 'Email', 'HP', 'Anonim', 'Campaign', 'Nominal', 'Fee', 'Total', 'Metode', 'Status', 'Doa/Pesan', 'Tgl Dibuat', 'Tgl Bayar']);
    $r = mysqli_query($conn, "SELECT * FROM donations ORDER BY created_at DESC");
    while ($row = mysqli_fetch_assoc($r))
        fputcsv($out, [
            $row['invoice_no'],
            $row['sapaan'],
            $row['donator_name'],
            $row['donator_email'],
            $row['donator_phone'],
            $row['is_anonymous'] ? 'Ya' : 'Tidak',
            $row['campaign_name'],
            $row['nominal_donation'],
            $row['admin_fee'],
            $row['total_amount'],
            $row['payment_method'],
            $row['payment_status'],
            $row['doa_message'],
            $row['created_at'],
            $row['paid_at'],
        ]);
    fclose($out);
    exit();
}

// ---- USERS: tambah / hapus / toggle role
if ($page === 'users') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_user') {
        $fn = trim($_POST['full_name']);
        $em = trim($_POST['email']);
        $ph = trim($_POST['phone']);
        $pw = trim($_POST['password']);
        $rl = in_array($_POST['role'], ['admin', 'user']) ? $_POST['role'] : 'user';
        if (!$fn || !$em || !$ph || !$pw) {
            $err = "Semua field wajib diisi.";
        } else {
            $h = password_hash($pw, PASSWORD_DEFAULT);
            // Schema users: full_name, email, phone, password, role
            $s = mysqli_prepare($conn, "INSERT INTO users(full_name,email,phone,password,role) VALUES(?,?,?,?,?)");
            mysqli_stmt_bind_param($s, "sssss", $fn, $em, $ph, $h, $rl);
            mysqli_stmt_execute($s) ? $msg = "Pengguna ditambahkan." : $err = "Email/HP sudah terdaftar.";
        }
    }
    if (isset($_GET['del_user'])) {
        $uid = (int) $_GET['del_user'];
        if ($uid !== (int) $_SESSION['user_id']) {
            $s = mysqli_prepare($conn, "DELETE FROM users WHERE id=?");
            mysqli_stmt_bind_param($s, "i", $uid);
            mysqli_stmt_execute($s);
        }
        header("Location: ?page=users");
        exit();
    }
    if (isset($_GET['toggle_role'])) {
        $uid = (int) $_GET['toggle_role'];
        if ($uid !== (int) $_SESSION['user_id']) {
            $s = mysqli_prepare($conn, "SELECT role FROM users WHERE id=? LIMIT 1");
            mysqli_stmt_bind_param($s, "i", $uid);
            mysqli_stmt_execute($s);
            $cur = mysqli_fetch_row(mysqli_stmt_get_result($s))[0] ?? 'user';
            $new = $cur === 'admin' ? 'user' : 'admin';
            $s2 = mysqli_prepare($conn, "UPDATE users SET role=? WHERE id=?");
            mysqli_stmt_bind_param($s2, "si", $new, $uid);
            mysqli_stmt_execute($s2);
        }
        header("Location: ?page=users");
        exit();
    }
}

// ---- PENGUMUMAN: CRUD
if ($page === 'pengumuman') {
    if (isset($_GET['del_peng'])) {
        $id = (int) $_GET['del_peng'];
        $s = mysqli_prepare($conn, "DELETE FROM pengumuman WHERE id=?");
        mysqli_stmt_bind_param($s, "i", $id);
        mysqli_stmt_execute($s);
        header("Location: ?page=pengumuman");
        exit();
    }
    if (isset($_GET['toggle_peng'])) {
        $id = (int) $_GET['toggle_peng'];
        $s = mysqli_prepare($conn, "UPDATE pengumuman SET is_active = NOT is_active WHERE id=?");
        mysqli_stmt_bind_param($s, "i", $id);
        mysqli_stmt_execute($s);
        header("Location: ?page=pengumuman");
        exit();
    }
    if (isset($_GET['edit_peng'])) {
        $id = (int) $_GET['edit_peng'];
        $s = mysqli_prepare($conn, "SELECT * FROM pengumuman WHERE id=? LIMIT 1");
        mysqli_stmt_bind_param($s, "i", $id);
        mysqli_stmt_execute($s);
        $edit = mysqli_fetch_assoc(mysqli_stmt_get_result($s));
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_peng') {
        $judul = trim($_POST['judul']);
        $isi = trim($_POST['isi']);
        $tipe = in_array($_POST['tipe'], ['hadist', 'pengumuman', 'quote']) ? $_POST['tipe'] : 'pengumuman';
        $sumber = trim($_POST['sumber']);
        $aktif = isset($_POST['is_active']) ? 1 : 0;
        $pid = (int) ($_POST['pid'] ?? 0);
        if (!$judul || !$isi) {
            $err = "Judul dan isi wajib diisi.";
        } elseif ($pid) {
            // Schema pengumuman: judul, isi, tipe, sumber, is_active
            $s = mysqli_prepare($conn, "UPDATE pengumuman SET judul=?,isi=?,tipe=?,sumber=?,is_active=? WHERE id=?");
            mysqli_stmt_bind_param($s, "ssssii", $judul, $isi, $tipe, $sumber, $aktif, $pid);
            mysqli_stmt_execute($s);
            $msg = "Diperbarui.";
        } else {
            $s = mysqli_prepare($conn, "INSERT INTO pengumuman(judul,isi,tipe,sumber,is_active) VALUES(?,?,?,?,?)");
            mysqli_stmt_bind_param($s, "ssssi", $judul, $isi, $tipe, $sumber, $aktif);
            mysqli_stmt_execute($s);
            $msg = "Tersimpan.";
        }
    }
}

// ---- DOA: hapus — schema doa_comments: id, campaign_id, donator_name, message, aamiin_count, created_at
if ($page === 'doa' && isset($_GET['del_doa'])) {
    $id = (int) $_GET['del_doa'];
    $s = mysqli_prepare($conn, "DELETE FROM doa_comments WHERE id=?");
    mysqli_stmt_bind_param($s, "i", $id);
    mysqli_stmt_execute($s);
    header("Location: ?page=doa");
    exit();
}

// ---- PROGRAM: CRUD
if ($page === 'program') {
    if (isset($_GET['del_prog'])) {
        $id = (int) $_GET['del_prog'];
        $s = mysqli_prepare($conn, "DELETE FROM program_donasi WHERE id=?");
        mysqli_stmt_bind_param($s, "i", $id);
        mysqli_stmt_execute($s);
        header("Location: ?page=program");
        exit();
    }
    if (isset($_GET['toggle_prog'])) {
        $id = (int) $_GET['toggle_prog'];
        $s = mysqli_prepare($conn, "SELECT status FROM program_donasi WHERE id=? LIMIT 1");
        mysqli_stmt_bind_param($s, "i", $id);
        mysqli_stmt_execute($s);
        $cur = mysqli_fetch_row(mysqli_stmt_get_result($s))[0] ?? 'aktif';
        $new = $cur === 'aktif' ? 'draft' : 'aktif';
        $s2 = mysqli_prepare($conn, "UPDATE program_donasi SET status=? WHERE id=?");
        mysqli_stmt_bind_param($s2, "si", $new, $id);
        mysqli_stmt_execute($s2);
        header("Location: ?page=program");
        exit();
    }
    if (isset($_GET['edit_prog'])) {
        $id = (int) $_GET['edit_prog'];
        $s = mysqli_prepare($conn, "SELECT * FROM program_donasi WHERE id=? LIMIT 1");
        mysqli_stmt_bind_param($s, "i", $id);
        mysqli_stmt_execute($s);
        $edit = mysqli_fetch_assoc(mysqli_stmt_get_result($s));
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_prog') {
        // Kolom schema: judul_program, judul_banner, deskripsi_singkat, deskripsi,
        //               kategori_id, target_donasi, tanggal_mulai, tanggal_selesai,
        //               status, is_featured, urutan
        // donasi_terkumpul, progress_percent, jumlah_donatur → dihitung otomatis, tidak diinput manual
        $judul = trim($_POST['judul_program']);
        $jbanner = trim($_POST['judul_banner'] ?? '');
        $target = (float) $_POST['target_donasi'];
        $kat = (int) $_POST['kategori_id'];
        $status = in_array($_POST['status'], ['aktif', 'selesai', 'draft']) ? $_POST['status'] : 'draft';
        $desk_s = trim($_POST['deskripsi_singkat']);
        $desk = trim($_POST['deskripsi']);
        $tmulai = $_POST['tanggal_mulai'];
        $tselesai = $_POST['tanggal_selesai'];
        $featured = isset($_POST['is_featured']) ? 1 : 0;
        $urutan = (int) ($_POST['urutan'] ?? 0);
        $pid = (int) ($_POST['pid'] ?? 0);

        if (!$judul) {
            $err = "Judul wajib diisi.";
        } elseif ($pid) {
            $s = mysqli_prepare(
                $conn,
                "UPDATE program_donasi SET judul_program=?,judul_banner=?,target_donasi=?,kategori_id=?,
                 status=?,deskripsi_singkat=?,deskripsi=?,tanggal_mulai=?,tanggal_selesai=?,
                 is_featured=?,urutan=? WHERE id=?"
            );
            mysqli_stmt_bind_param(
                $s,
                "ssdisssssiii",
                $judul,
                $jbanner,
                $target,
                $kat,
                $status,
                $desk_s,
                $desk,
                $tmulai,
                $tselesai,
                $featured,
                $urutan,
                $pid
            );
            mysqli_stmt_execute($s);
            $msg = "Program diperbarui.";
        } else {
            $s = mysqli_prepare(
                $conn,
                "INSERT INTO program_donasi(judul_program,judul_banner,target_donasi,kategori_id,
                 status,deskripsi_singkat,deskripsi,tanggal_mulai,tanggal_selesai,is_featured,urutan)
                 VALUES(?,?,?,?,?,?,?,?,?,?,?)"
            );
            mysqli_stmt_bind_param(
                $s,
                "ssdisssssii",
                $judul,
                $jbanner,
                $target,
                $kat,
                $status,
                $desk_s,
                $desk,
                $tmulai,
                $tselesai,
                $featured,
                $urutan
            );
            mysqli_stmt_execute($s);
            $msg = "Program ditambahkan.";
        }
    }
}

// ---- KATEGORI: CRUD
if ($page === 'kategori') {
    if (isset($_GET['del_kat'])) {
        $id = (int) $_GET['del_kat'];
        $s = mysqli_prepare($conn, "DELETE FROM kategori_donasi WHERE id=?");
        mysqli_stmt_bind_param($s, "i", $id);
        mysqli_stmt_execute($s);
        header("Location: ?page=kategori");
        exit();
    }
    if (isset($_GET['edit_kat'])) {
        $id = (int) $_GET['edit_kat'];
        $s = mysqli_prepare($conn, "SELECT * FROM kategori_donasi WHERE id=? LIMIT 1");
        mysqli_stmt_bind_param($s, "i", $id);
        mysqli_stmt_execute($s);
        $edit = mysqli_fetch_assoc(mysqli_stmt_get_result($s));
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_kat') {
        // Schema: nama_kategori, slug, icon_svg, warna_badge, halaman_url, urutan, is_active
        $nama = trim($_POST['nama_kategori']);
        $slug = trim($_POST['slug']);
        $icon = trim($_POST['icon_svg'] ?? '');
        $warna = trim($_POST['warna_badge']);
        $hlmurl = trim($_POST['halaman_url'] ?? '');
        $urutan = (int) ($_POST['urutan'] ?? 0);
        $aktif = isset($_POST['is_active']) ? 1 : 0;
        $pid = (int) ($_POST['pid'] ?? 0);

        if (!$nama) {
            $err = "Nama wajib diisi.";
        } elseif ($pid) {
            $s = mysqli_prepare(
                $conn,
                "UPDATE kategori_donasi SET nama_kategori=?,slug=?,icon_svg=?,warna_badge=?,
                 halaman_url=?,urutan=?,is_active=? WHERE id=?"
            );
            mysqli_stmt_bind_param($s, "sssssiii", $nama, $slug, $icon, $warna, $hlmurl, $urutan, $aktif, $pid);
            mysqli_stmt_execute($s);
            $msg = "Kategori diperbarui.";
        } else {
            $s = mysqli_prepare(
                $conn,
                "INSERT INTO kategori_donasi(nama_kategori,slug,icon_svg,warna_badge,halaman_url,urutan,is_active)
                 VALUES(?,?,?,?,?,?,?)"
            );
            mysqli_stmt_bind_param($s, "sssssi i", $nama, $slug, $icon, $warna, $hlmurl, $urutan, $aktif);
            // Fix bind param (hapus spasi)
            mysqli_stmt_bind_param($s, "sssssii", $nama, $slug, $icon, $warna, $hlmurl, $urutan, $aktif);
            mysqli_stmt_execute($s);
            $msg = "Kategori ditambahkan.";
        }
    }
}

// ---- PAYMENT: CRUD
if ($page === 'payment') {
    if (isset($_GET['del_pay'])) {
        $id = (int) $_GET['del_pay'];
        $s = mysqli_prepare($conn, "DELETE FROM payment_methods WHERE id=?");
        mysqli_stmt_bind_param($s, "i", $id);
        mysqli_stmt_execute($s);
        header("Location: ?page=payment");
        exit();
    }
    if (isset($_GET['toggle_pay'])) {
        $id = (int) $_GET['toggle_pay'];
        $s = mysqli_prepare($conn, "UPDATE payment_methods SET is_active = NOT is_active WHERE id=?");
        mysqli_stmt_bind_param($s, "i", $id);
        mysqli_stmt_execute($s);
        header("Location: ?page=payment");
        exit();
    }
    if (isset($_GET['edit_pay'])) {
        $id = (int) $_GET['edit_pay'];
        $s = mysqli_prepare($conn, "SELECT * FROM payment_methods WHERE id=? LIMIT 1");
        mysqli_stmt_bind_param($s, "i", $id);
        mysqli_stmt_execute($s);
        $edit = mysqli_fetch_assoc(mysqli_stmt_get_result($s));
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_pay') {
        // Schema: method_code (UNI), method_name, admin_fee, is_active
        $code = trim($_POST['method_code']);
        $name = trim($_POST['method_name']);
        $fee = (float) $_POST['admin_fee'];
        $aktif = isset($_POST['is_active']) ? 1 : 0;
        $pid = (int) ($_POST['pid'] ?? 0);

        if (!$code || !$name) {
            $err = "Kode dan nama wajib diisi.";
        } elseif ($pid) {
            $s = mysqli_prepare(
                $conn,
                "UPDATE payment_methods SET method_code=?,method_name=?,admin_fee=?,is_active=? WHERE id=?"
            );
            mysqli_stmt_bind_param($s, "ssdii", $code, $name, $fee, $aktif, $pid);
            mysqli_stmt_execute($s);
            $msg = "Diperbarui.";
        } else {
            $s = mysqli_prepare(
                $conn,
                "INSERT INTO payment_methods(method_code,method_name,admin_fee,is_active) VALUES(?,?,?,?)"
            );
            mysqli_stmt_bind_param($s, "ssdi", $code, $name, $fee, $aktif);
            mysqli_stmt_execute($s);
            $msg = "Ditambahkan.";
        }
    }
}

// ── Data query per halaman ────────────────────────────────────────────────────
if ($page === 'dashboard') {
    $s_users = qval($conn, "SELECT COUNT(*) FROM users");
    $s_program = qval($conn, "SELECT COUNT(*) FROM program_donasi WHERE status='aktif'");
    $s_total = qval($conn, "SELECT COALESCE(SUM(total_amount),0) FROM donations WHERE payment_status='paid'");
    $s_pending = qval($conn, "SELECT COUNT(*) FROM donations WHERE payment_status='pending'");
    $s_expired = qval($conn, "SELECT COUNT(*) FROM donations WHERE payment_status IN('expired','failed')");
    $s_paid = qval($conn, "SELECT COUNT(*) FROM donations WHERE payment_status='paid'");

    // 8 donasi terbaru — kolom sesuai schema donations
    $d_recent = mysqli_query(
        $conn,
        "SELECT invoice_no,donator_name,is_anonymous,campaign_name,total_amount,
                payment_method,payment_status,created_at
         FROM donations ORDER BY created_at DESC LIMIT 8"
    );
    // 5 program terbaru — join kategori_donasi
    $p_recent = mysqli_query(
        $conn,
        "SELECT p.id, p.judul_program, p.target_donasi, p.donasi_terkumpul,
                p.progress_percent, p.status, k.nama_kategori
         FROM program_donasi p
         LEFT JOIN kategori_donasi k ON k.id = p.kategori_id
         ORDER BY p.created_at DESC LIMIT 5"
    );
}

$search = trim($_GET['q'] ?? '');
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />
    <title>Administrator | Dimzz</title>
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0
        }

        :root {
            --purple-50: #EEEDFE;
            --purple-400: #7F77DD;
            --purple-600: #534AB7;
            --purple-800: #3C3489;
            --green-50: #EAF3DE;
            --green-600: #3B6D11;
            --green-800: #27500A;
            --red-50: #FCEBEB;
            --red-600: #A32D2D;
            --amber-50: #FAEEDA;
            --amber-600: #854F0B;
            --blue-50: #E6F1FB;
            --blue-600: #185FA5;
            --blue-800: #0C447C;
            --gray-50: #F1EFE8;
            --gray-100: #D3D1C7;
            --gray-600: #5F5E5A;
            --bg: #F5F4F0;
            --surface: #fff;
            --border: rgba(0, 0, 0, .10);
            --border-md: rgba(0, 0, 0, .18);
            --txt: #1a1a1a;
            --txt2: #5F5E5A;
            --txt3: #9a9890;
            --rsm: 6px;
            --rmd: 8px;
            --rlg: 12px;
            --sw: 210px;
            --th: 54px;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg);
            color: var(--txt);
            font-size: 14px;
            line-height: 1.6
        }

        a {
            color: inherit;
            text-decoration: none
        }

        button,
        input,
        select,
        textarea {
            font-family: inherit
        }

        .layout {
            display: flex;
            min-height: 100vh
        }

        .sidebar {
            width: var(--sw);
            background: var(--surface);
            border-right: .5px solid var(--border);
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            flex-shrink: 0
        }

        .sb-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 16px;
            border-bottom: .5px solid var(--border)
        }

        .sb-logo {
            width: 32px;
            height: 32px;
            border-radius: var(--rmd);
            background: var(--purple-400);
            color: #fff;
            font-size: 15px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0
        }

        .sb-name {
            font-size: 14px;
            font-weight: 500
        }

        .sb-role {
            font-size: 11px;
            color: var(--txt3)
        }

        .sb-nav {
            padding: 10px 8px;
            display: flex;
            flex-direction: column;
            gap: 2px;
            flex: 1
        }

        .nav-label {
            font-size: 10px;
            color: var(--txt3);
            padding: 10px 8px 4px;
            letter-spacing: .06em;
            text-transform: uppercase
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 7px 10px;
            border-radius: var(--rmd);
            font-size: 13px;
            color: var(--txt2);
            transition: background .12s, color .12s;
            cursor: pointer
        }

        .nav-item:hover {
            background: var(--gray-50);
            color: var(--txt)
        }

        .nav-item.active {
            background: var(--purple-50);
            color: var(--purple-800);
            font-weight: 500
        }

        .nav-item.nav-out:hover {
            background: var(--red-50);
            color: var(--red-600)
        }

        .nav-dot {
            font-size: 10px;
            opacity: .5
        }

        .nav-badge {
            margin-left: auto;
            background: var(--red-50);
            color: var(--red-600);
            font-size: 10px;
            padding: 1px 6px;
            border-radius: 10px
        }

        .main {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0
        }

        .topbar {
            height: var(--th);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            background: var(--surface);
            border-bottom: .5px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 10
        }

        .topbar-title {
            font-size: 15px;
            font-weight: 500
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 10px
        }

        .topbar-admin {
            font-size: 13px;
            color: var(--txt2)
        }

        .avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--purple-50);
            color: var(--purple-800);
            font-size: 12px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center
        }

        .content {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 14px
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 10px
        }

        .stat {
            background: var(--surface);
            border: .5px solid var(--border);
            border-radius: var(--rlg);
            padding: 14px 16px
        }

        .stat-green {
            background: var(--green-50);
            border-color: rgba(59, 109, 17, .15)
        }

        .stat-amber {
            background: var(--amber-50);
            border-color: rgba(133, 79, 11, .15)
        }

        .stat-red {
            background: var(--red-50);
            border-color: rgba(163, 45, 45, .15)
        }

        .stat-label {
            font-size: 11px;
            color: var(--txt3);
            margin-bottom: 5px
        }

        .stat-val {
            font-size: 21px;
            font-weight: 500
        }

        .stat-green .stat-val {
            color: var(--green-600)
        }

        .stat-amber .stat-val {
            color: var(--amber-600)
        }

        .stat-red .stat-val {
            color: var(--red-600)
        }

        .stat-sub {
            font-size: 11px;
            color: var(--txt3);
            margin-top: 3px
        }

        .section-hd {
            display: flex;
            align-items: center;
            justify-content: space-between
        }

        .section-hd h2 {
            font-size: 14px;
            font-weight: 500
        }

        .card {
            background: var(--surface);
            border: .5px solid var(--border);
            border-radius: var(--rlg);
            padding: 14px 16px
        }

        .table-wrap {
            overflow-x: auto
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px
        }

        .data-table th {
            text-align: left;
            color: var(--txt3);
            font-weight: 400;
            font-size: 11px;
            padding: 6px 10px;
            border-bottom: .5px solid var(--border);
            white-space: nowrap
        }

        .data-table td {
            padding: 9px 10px;
            border-bottom: .5px solid var(--border);
            color: var(--txt2);
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap
        }

        .data-table tr:last-child td {
            border-bottom: none
        }

        .data-table tr:hover td {
            background: var(--gray-50)
        }

        .progress-bar {
            height: 4px;
            background: var(--gray-100);
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 3px;
            min-width: 70px
        }

        .progress-fill {
            height: 100%;
            background: var(--purple-400);
            border-radius: 2px
        }

        .pill {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            white-space: nowrap
        }

        .pill-green {
            background: var(--green-50);
            color: var(--green-600)
        }

        .pill-red {
            background: var(--red-50);
            color: var(--red-600)
        }

        .pill-amber {
            background: var(--amber-50);
            color: var(--amber-600)
        }

        .pill-purple {
            background: var(--purple-50);
            color: var(--purple-800)
        }

        .pill-blue {
            background: var(--blue-50);
            color: var(--blue-600)
        }

        .pill-gray {
            background: var(--gray-50);
            color: var(--gray-600)
        }

        .btn-sm {
            display: inline-block;
            padding: 5px 12px;
            font-size: 12px;
            border: .5px solid var(--border-md);
            border-radius: var(--rmd);
            background: transparent;
            color: var(--txt2);
            cursor: pointer;
            transition: background .12s;
            white-space: nowrap
        }

        .btn-sm:hover {
            background: var(--gray-50);
            color: var(--txt)
        }

        .btn-action {
            display: inline-block;
            padding: 3px 9px;
            font-size: 11px;
            border-radius: var(--rsm);
            border: .5px solid transparent;
            cursor: pointer;
            background: none;
            white-space: nowrap;
            transition: opacity .12s
        }

        .btn-action:hover {
            opacity: .75
        }

        .btn-red {
            background: var(--red-50);
            color: var(--red-600);
            border-color: rgba(163, 45, 45, .2)
        }

        .btn-green {
            background: var(--green-50);
            color: var(--green-600);
            border-color: rgba(59, 109, 17, .2)
        }

        .btn-amber {
            background: var(--amber-50);
            color: var(--amber-600);
            border-color: rgba(133, 79, 11, .2)
        }

        .btn-purple {
            background: var(--purple-50);
            color: var(--purple-800);
            border-color: rgba(83, 74, 183, .2)
        }

        .f-input {
            width: 100%;
            padding: 7px 10px;
            border: .5px solid var(--border-md);
            border-radius: var(--rmd);
            font-size: 13px;
            background: var(--surface);
            color: var(--txt)
        }

        .f-input:focus {
            outline: none;
            border-color: var(--purple-400)
        }

        .f-label {
            font-size: 11px;
            color: var(--txt3);
            margin-bottom: 4px;
            display: block
        }

        .f-group {
            display: flex;
            flex-direction: column
        }

        .f-grid {
            display: grid;
            gap: 10px
        }

        .f-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap
        }

        .alert {
            padding: 10px 14px;
            border-radius: var(--rmd);
            font-size: 13px;
            margin-bottom: 4px
        }

        .alert-green {
            background: var(--green-50);
            color: var(--green-600)
        }

        .alert-red {
            background: var(--red-50);
            color: var(--red-600)
        }

        .quick-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 8px
        }

        .quick-btn {
            display: block;
            padding: 10px 14px;
            font-size: 13px;
            border: .5px solid var(--border-md);
            border-radius: var(--rmd);
            background: var(--surface);
            color: var(--txt2);
            transition: background .12s, color .12s;
            text-align: center
        }

        .quick-btn:hover {
            background: var(--purple-50);
            color: var(--purple-800);
            border-color: rgba(83, 74, 183, .2)
        }

        .row2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px
        }

        @media(max-width:900px) {
            .row2 {
                grid-template-columns: 1fr
            }
        }

        @media(max-width:768px) {
            .sidebar {
                display: none
            }

            .stats {
                grid-template-columns: 1fr 1fr
            }
        }
    </style>
</head>

<body>
    <div class="layout">

        <!-- ══ SIDEBAR ══════════════════════════════════════════════════════════════ -->
        <aside class="sidebar">
            <div class="sb-brand">
                <div class="sb-logo">D</div>
                <div>
                    <div class="sb-name">Dimzz</div>
                    <div class="sb-role">Administrator</div>
                </div>
            </div>
            <nav class="sb-nav">
                <span class="nav-label">Menu Utama</span>
                <?php
                $pending_count = (int) qval($conn, "SELECT COUNT(*) FROM donations WHERE payment_status='pending'");
                $nav = [
                    'dashboard' => 'Dashboard',
                    'donasi' => 'Donasi',
                    'program' => 'Program Donasi',
                    'kategori' => 'Kategori',
                    'payment' => 'Metode Pembayaran',
                    'pengumuman' => 'Pengumuman',
                    'doa' => 'Komentar Doa',
                    'users' => 'Pengguna',
                ];
                foreach ($nav as $key => $label):
                    $cls = $page === $key ? 'nav-item active' : 'nav-item';
                    ?>
                    <a href="?page=<?= $key ?>" class="<?= $cls ?>">
                        <span class="nav-dot">&#9632;</span> <?= $label ?>
                        <?php if ($key === 'donasi' && $pending_count > 0): ?>
                            <span class="nav-badge"><?= $pending_count ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
                <span class="nav-label">Akun</span>
                <a href="?logout=1" class="nav-item nav-out"><span class="nav-dot">&#9632;</span> Keluar</a>
            </nav>
        </aside>

        <!-- ══ MAIN ══════════════════════════════════════════════════════════════════ -->
        <div class="main">
            <header class="topbar">
                <h1 class="topbar-title"><?= match ($page) {
                    'dashboard' => 'Dashboard',
                    'donasi' => 'Manajemen Donasi',
                    'program' => 'Program Donasi',
                    'kategori' => 'Kategori Donasi',
                    'payment' => 'Metode Pembayaran',
                    'pengumuman' => 'Pengumuman',
                    'doa' => 'Komentar Doa',
                    'users' => 'Manajemen Pengguna',
                    default => 'Admin',
                } ?></h1>
                <div class="topbar-right">
                    <?php if ($page === 'donasi'): ?>
                        <a href="?page=donasi&export=csv" class="btn-sm">&#8659; Ekspor CSV</a>
                    <?php endif; ?>
                    <span class="topbar-admin"><?= esc($me['full_name']) ?></span>
                    <div class="avatar"><?= strtoupper(substr($me['full_name'], 0, 2)) ?></div>
                </div>
            </header>

            <div class="content">

                <?php if ($msg): ?>
                    <div class="alert alert-green"><?= esc($msg) ?></div><?php endif; ?>
                <?php if ($err): ?>
                    <div class="alert alert-red"><?= esc($err) ?></div><?php endif; ?>

                <?php /* ══════════════════════ DASHBOARD ══════════════════════ */
                if ($page === 'dashboard'): ?>

                    <div class="stats">
                        <div class="stat">
                            <div class="stat-label">Total pengguna</div>
                            <div class="stat-val"><?= number_format($s_users) ?></div>
                        </div>
                        <div class="stat">
                            <div class="stat-label">Program aktif</div>
                            <div class="stat-val"><?= number_format($s_program) ?></div>
                        </div>
                        <div class="stat stat-green">
                            <div class="stat-label">Donasi masuk</div>
                            <div class="stat-val"><?= rp($s_total) ?></div>
                            <div class="stat-sub"><?= number_format($s_paid) ?> transaksi</div>
                        </div>
                        <div class="stat stat-amber">
                            <div class="stat-label">Menunggu</div>
                            <div class="stat-val"><?= number_format($s_pending) ?></div>
                        </div>
                        <div class="stat stat-red">
                            <div class="stat-label">Expired/Gagal</div>
                            <div class="stat-val"><?= number_format($s_expired) ?></div>
                        </div>
                    </div>

                    <div class="section-hd">
                        <h2>Donasi terbaru</h2><a href="?page=donasi" class="btn-sm">Lihat semua →</a>
                    </div>
                    <div class="card">
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Invoice</th>
                                        <th>Donatur</th>
                                        <th>Program</th>
                                        <th>Total</th>
                                        <th>Metode</th>
                                        <th>Status</th>
                                        <th>Tanggal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($d = mysqli_fetch_assoc($d_recent)): ?>
                                        <tr>
                                            <td style="font-family:monospace;font-size:11px"><?= esc($d['invoice_no']) ?></td>
                                            <td><?= esc($d['is_anonymous'] ? 'Anonim' : $d['donator_name']) ?></td>
                                            <td><?= esc(mb_strimwidth($d['campaign_name'], 0, 30, '…')) ?></td>
                                            <td style="white-space:nowrap;font-weight:500"><?= rp($d['total_amount']) ?></td>
                                            <td><?= esc(strtoupper($d['payment_method'])) ?></td>
                                            <td><?= statusDonasi($d['payment_status']) ?></td>
                                            <td style="white-space:nowrap;color:var(--txt3)">
                                                <?= date('d M Y', strtotime($d['created_at'])) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="section-hd">
                        <h2>Program donasi</h2><a href="?page=program" class="btn-sm">Kelola →</a>
                    </div>
                    <div class="card">
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Judul</th>
                                        <th>Kategori</th>
                                        <th>Target</th>
                                        <th>Terkumpul</th>
                                        <th>Progress</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($p = mysqli_fetch_assoc($p_recent)):
                                        $pct = min(100, (float) ($p['progress_percent'] ?? 0)); ?>
                                        <tr>
                                            <td><?= esc(mb_strimwidth($p['judul_program'], 0, 35, '…')) ?></td>
                                            <td><?= esc($p['nama_kategori'] ?? '-') ?></td>
                                            <td style="white-space:nowrap"><?= rp($p['target_donasi']) ?></td>
                                            <td style="white-space:nowrap"><?= rp($p['donasi_terkumpul'] ?? 0) ?></td>
                                            <td>
                                                <div class="progress-bar">
                                                    <div class="progress-fill" style="width:<?= $pct ?>%"></div>
                                                </div>
                                                <span
                                                    style="font-size:11px;color:var(--txt3)"><?= number_format($pct, 1) ?>%</span>
                                            </td>
                                            <td><?= statusProgram($p['status']) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="section-hd">
                        <h2>Aksi cepat</h2>
                    </div>
                    <div class="quick-grid">
                        <a href="?page=program&new=1" class="quick-btn">+ Program baru</a>
                        <a href="?page=pengumuman&new=1" class="quick-btn">+ Pengumuman</a>
                        <a href="?page=kategori&new=1" class="quick-btn">+ Kategori</a>
                        <a href="?page=payment&new=1" class="quick-btn">+ Metode bayar</a>
                        <a href="?page=donasi&export=csv" class="quick-btn">&#8659; Ekspor CSV</a>
                        <a href="?page=users" class="quick-btn">&#9632; Kelola user</a>
                    </div>

                <?php /* ══════════════════════ DONASI ══════════════════════ */
                elseif ($page === 'donasi'): ?>

                    <?php
                    $fs = $_GET['status'] ?? '';
                    $fq = trim($_GET['q'] ?? '');
                    $where = "1";
                    $bind = [];
                    $types = "";
                    if ($fs) {
                        $where .= " AND payment_status=?";
                        $bind[] = $fs;
                        $types .= "s";
                    }
                    if ($fq) {
                        $like = "%$fq%";
                        $where .= " AND (donator_name LIKE ? OR invoice_no LIKE ? OR campaign_name LIKE ? OR donator_email LIKE ?)";
                        $bind = array_merge($bind, [$like, $like, $like, $like]);
                        $types .= "ssss";
                    }
                    // Kolom sesuai schema donations: tampilkan sapaan & doa_message juga
                    $stmt2 = mysqli_prepare(
                        $conn,
                        "SELECT invoice_no, sapaan, donator_name, donator_email, donator_phone,
                is_anonymous, campaign_name, nominal_donation, admin_fee, total_amount,
                payment_method, payment_status, doa_message, payment_expiry, paid_at, created_at
         FROM donations WHERE $where ORDER BY created_at DESC LIMIT 200"
                    );
                    if ($bind)
                        mysqli_stmt_bind_param($stmt2, $types, ...$bind);
                    mysqli_stmt_execute($stmt2);
                    $rows = mysqli_stmt_get_result($stmt2);
                    ?>
                    <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap">
                        <input type="hidden" name="page" value="donasi" />
                        <input class="f-input" type="text" name="q" placeholder="Cari donatur / invoice / program / email…"
                            value="<?= esc($fq) ?>" style="flex:1;min-width:180px" />
                        <select class="f-input" name="status" style="width:160px">
                            <option value="">Semua status</option>
                            <?php foreach (['paid' => 'Lunas', 'pending' => 'Menunggu', 'expired' => 'Kadaluarsa', 'failed' => 'Gagal'] as $v => $l): ?>
                                <option value="<?= $v ?>" <?= $fs === $v ? 'selected' : '' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn-sm">Cari</button>
                        <a href="?page=donasi" class="btn-sm">Reset</a>
                    </form>

                    <div class="card">
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Invoice</th>
                                        <th>Donatur</th>
                                        <th>Email</th>
                                        <th>Program</th>
                                        <th>Nominal</th>
                                        <th>Fee</th>
                                        <th>Total</th>
                                        <th>Metode</th>
                                        <th>Status</th>
                                        <th>Doa/Pesan</th>
                                        <th>Tanggal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($d = mysqli_fetch_assoc($rows)): ?>
                                        <tr>
                                            <td style="font-family:monospace;font-size:11px"><?= esc($d['invoice_no']) ?></td>
                                            <td>
                                                <?php if (!$d['is_anonymous']): ?>
                                                    <span style="font-size:10px;color:var(--txt3)"><?= esc($d['sapaan']) ?></span>
                                                    <?= esc($d['donator_name']) ?>
                                                <?php else: ?>
                                                    <em style="color:var(--txt3)">Anonim</em>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-size:11px;color:var(--txt3)"><?= esc($d['donator_email'] ?? '-') ?>
                                            </td>
                                            <td><?= esc(mb_strimwidth($d['campaign_name'], 0, 28, '…')) ?></td>
                                            <td style="white-space:nowrap"><?= rp($d['nominal_donation']) ?></td>
                                            <td style="white-space:nowrap;color:var(--txt3)"><?= rp($d['admin_fee']) ?></td>
                                            <td style="white-space:nowrap;font-weight:500"><?= rp($d['total_amount']) ?></td>
                                            <td><?= esc(strtoupper($d['payment_method'])) ?></td>
                                            <td><?= statusDonasi($d['payment_status']) ?></td>
                                            <td style="max-width:160px" title="<?= esc($d['doa_message'] ?? '') ?>">
                                                <?= esc(mb_strimwidth($d['doa_message'] ?? '-', 0, 30, '…')) ?>
                                            </td>
                                            <td style="white-space:nowrap;color:var(--txt3)">
                                                <?= date('d M Y H:i', strtotime($d['created_at'])) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php /* ══════════════════════ PROGRAM ══════════════════════ */
                elseif ($page === 'program'): ?>

                    <?php
                    $kats = mysqli_query(
                        $conn,
                        "SELECT id, nama_kategori FROM kategori_donasi WHERE is_active=1 ORDER BY urutan, nama_kategori"
                    );
                    ?>
                    <div class="section-hd">
                        <h2><?= $edit ? 'Edit program' : 'Tambah program' ?></h2>
                        <?php if ($edit): ?><a href="?page=program" class="btn-sm">+ Baru</a><?php endif; ?>
                    </div>
                    <div class="card">
                        <form method="POST" class="f-grid" style="grid-template-columns:1fr 1fr">
                            <input type="hidden" name="action" value="save_prog" />
                            <input type="hidden" name="pid" value="<?= (int) ($edit['id'] ?? 0) ?>" />

                            <div class="f-group" style="grid-column:1/-1">
                                <label class="f-label">Judul program *</label>
                                <input class="f-input" type="text" name="judul_program"
                                    value="<?= esc($edit['judul_program'] ?? '') ?>" />
                            </div>
                            <div class="f-group" style="grid-column:1/-1">
                                <label class="f-label">Judul banner (opsional)</label>
                                <input class="f-input" type="text" name="judul_banner"
                                    value="<?= esc($edit['judul_banner'] ?? '') ?>" />
                            </div>
                            <div class="f-group">
                                <label class="f-label">Target donasi (Rp)</label>
                                <input class="f-input" type="number" name="target_donasi"
                                    value="<?= $edit['target_donasi'] ?? '' ?>" />
                            </div>
                            <div class="f-group">
                                <label class="f-label">Kategori</label>
                                <select class="f-input" name="kategori_id">
                                    <option value="">— Pilih —</option>
                                    <?php while ($k = mysqli_fetch_assoc($kats)): ?>
                                        <option value="<?= $k['id'] ?>" <?= ($edit['kategori_id'] ?? '') == $k['id'] ? 'selected' : '' ?>>
                                            <?= esc($k['nama_kategori']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="f-group">
                                <label class="f-label">Tanggal mulai</label>
                                <input class="f-input" type="date" name="tanggal_mulai"
                                    value="<?= esc($edit['tanggal_mulai'] ?? '') ?>" />
                            </div>
                            <div class="f-group">
                                <label class="f-label">Tanggal selesai</label>
                                <input class="f-input" type="date" name="tanggal_selesai"
                                    value="<?= esc($edit['tanggal_selesai'] ?? '') ?>" />
                            </div>
                            <div class="f-group">
                                <label class="f-label">Status</label>
                                <select class="f-input" name="status">
                                    <?php foreach (['aktif', 'draft', 'selesai'] as $st): ?>
                                        <option value="<?= $st ?>" <?= ($edit['status'] ?? 'draft') === $st ? 'selected' : '' ?>>
                                            <?= ucfirst($st) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="f-group">
                                <label class="f-label">Urutan tampil</label>
                                <input class="f-input" type="number" name="urutan"
                                    value="<?= (int) ($edit['urutan'] ?? 0) ?>" />
                            </div>
                            <div class="f-group" style="grid-column:1/-1">
                                <label class="f-label">Deskripsi singkat</label>
                                <input class="f-input" type="text" name="deskripsi_singkat"
                                    value="<?= esc($edit['deskripsi_singkat'] ?? '') ?>" />
                            </div>
                            <div class="f-group" style="grid-column:1/-1">
                                <label class="f-label">Deskripsi lengkap</label>
                                <textarea class="f-input" name="deskripsi" rows="4"
                                    style="resize:vertical"><?= esc($edit['deskripsi'] ?? '') ?></textarea>
                            </div>
                            <div style="grid-column:1/-1;display:flex;gap:12px;align-items:center">
                                <label style="display:flex;align-items:center;gap:5px;font-size:13px">
                                    <input type="checkbox" name="is_featured" <?= ($edit['is_featured'] ?? 0) ? 'checked' : '' ?> /> Tampilkan di featured
                                </label>
                                <button type="submit" class="btn-action btn-purple" style="padding:7px 18px;font-size:13px">
                                    <?= $edit ? 'Perbarui' : 'Simpan' ?>
                                </button>
                            </div>
                        </form>
                    </div>

                    <?php
                    $progs = mysqli_query(
                        $conn,
                        "SELECT p.id, p.judul_program, p.target_donasi, p.donasi_terkumpul,
                p.jumlah_donatur, p.progress_percent, p.status, p.is_featured,
                p.tanggal_selesai, k.nama_kategori
         FROM program_donasi p
         LEFT JOIN kategori_donasi k ON k.id = p.kategori_id
         ORDER BY p.urutan ASC, p.created_at DESC"
                    );
                    ?>
                    <div class="card">
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Judul</th>
                                        <th>Kategori</th>
                                        <th>Target</th>
                                        <th>Terkumpul</th>
                                        <th>Donatur</th>
                                        <th>Progress</th>
                                        <th>Status</th>
                                        <th>Featured</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1;
                                    while ($p = mysqli_fetch_assoc($progs)):
                                        $pct = min(100, (float) ($p['progress_percent'] ?? 0)); ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><?= esc(mb_strimwidth($p['judul_program'], 0, 30, '…')) ?></td>
                                            <td><?= esc($p['nama_kategori'] ?? '-') ?></td>
                                            <td style="white-space:nowrap"><?= rp($p['target_donasi']) ?></td>
                                            <td style="white-space:nowrap"><?= rp($p['donasi_terkumpul'] ?? 0) ?></td>
                                            <td style="text-align:center"><?= number_format($p['jumlah_donatur'] ?? 0) ?></td>
                                            <td>
                                                <div class="progress-bar">
                                                    <div class="progress-fill" style="width:<?= $pct ?>%"></div>
                                                </div>
                                                <span
                                                    style="font-size:11px;color:var(--txt3)"><?= number_format($pct, 1) ?>%</span>
                                            </td>
                                            <td><?= statusProgram($p['status']) ?></td>
                                            <td style="text-align:center">
                                                <?= $p['is_featured'] ? pill('Ya', 'purple') : pill('Tidak', 'gray') ?></td>
                                            <td style="display:flex;gap:4px">
                                                <a href="?page=program&edit_prog=<?= $p['id'] ?>"
                                                    class="btn-action btn-green">Edit</a>
                                                <a href="?page=program&toggle_prog=<?= $p['id'] ?>"
                                                    class="btn-action btn-amber">Toggle</a>
                                                <a href="?page=program&del_prog=<?= $p['id'] ?>" class="btn-action btn-red"
                                                    onclick="return confirm('Hapus program ini?')">Hapus</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php /* ══════════════════════ KATEGORI ══════════════════════ */
                elseif ($page === 'kategori'): ?>

                    <div class="section-hd">
                        <h2><?= $edit ? 'Edit kategori' : 'Tambah kategori' ?></h2>
                        <?php if ($edit): ?><a href="?page=kategori" class="btn-sm">+ Baru</a><?php endif; ?>
                    </div>
                    <div class="card">
                        <!-- Schema kategori_donasi: nama_kategori, slug, icon_svg, warna_badge, halaman_url, urutan, is_active -->
                        <form method="POST"
                            style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;align-items:end">
                            <input type="hidden" name="action" value="save_kat" />
                            <input type="hidden" name="pid" value="<?= (int) ($edit['id'] ?? 0) ?>" />
                            <div class="f-group">
                                <label class="f-label">Nama kategori *</label>
                                <input class="f-input" type="text" name="nama_kategori"
                                    value="<?= esc($edit['nama_kategori'] ?? '') ?>" />
                            </div>
                            <div class="f-group">
                                <label class="f-label">Slug</label>
                                <input class="f-input" type="text" name="slug" value="<?= esc($edit['slug'] ?? '') ?>"
                                    placeholder="contoh-slug" />
                            </div>
                            <div class="f-group">
                                <label class="f-label">Warna badge (hex)</label>
                                <input class="f-input" type="text" name="warna_badge"
                                    value="<?= esc($edit['warna_badge'] ?? '#e63946') ?>" placeholder="#e63946" />
                            </div>
                            <div class="f-group">
                                <label class="f-label">URL halaman (opsional)</label>
                                <input class="f-input" type="text" name="halaman_url"
                                    value="<?= esc($edit['halaman_url'] ?? '') ?>" />
                            </div>
                            <div class="f-group">
                                <label class="f-label">Urutan</label>
                                <input class="f-input" type="number" name="urutan"
                                    value="<?= (int) ($edit['urutan'] ?? 0) ?>" />
                            </div>
                            <div class="f-group">
                                <label class="f-label">Icon SVG (opsional)</label>
                                <input class="f-input" type="text" name="icon_svg"
                                    value="<?= esc($edit['icon_svg'] ?? '') ?>" placeholder="<svg>…</svg>" />
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;padding-bottom:2px">
                                <label style="display:flex;align-items:center;gap:5px;font-size:13px">
                                    <input type="checkbox" name="is_active" <?= ($edit['is_active'] ?? 1) ? 'checked' : '' ?> /> Aktifkan
                                </label>
                                <button type="submit" class="btn-action btn-purple" style="padding:7px 14px;font-size:13px">
                                    <?= $edit ? 'Perbarui' : 'Simpan' ?>
                                </button>
                            </div>
                        </form>
                    </div>

                    <?php $kats = mysqli_query($conn, "SELECT * FROM kategori_donasi ORDER BY urutan, nama_kategori"); ?>
                    <div class="card">
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Nama</th>
                                        <th>Slug</th>
                                        <th>Warna</th>
                                        <th>URL</th>
                                        <th>Urutan</th>
                                        <th>Aktif</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1;
                                    while ($k = mysqli_fetch_assoc($kats)): ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><?= esc($k['nama_kategori']) ?></td>
                                            <td style="font-family:monospace;font-size:11px"><?= esc($k['slug']) ?></td>
                                            <td>
                                                <span
                                                    style="display:inline-block;width:14px;height:14px;border-radius:3px;background:<?= esc($k['warna_badge']) ?>;vertical-align:middle;margin-right:5px"></span>
                                                <?= esc($k['warna_badge']) ?>
                                            </td>
                                            <td style="font-size:11px;color:var(--txt3)"><?= esc($k['halaman_url'] ?? '-') ?>
                                            </td>
                                            <td style="text-align:center"><?= (int) $k['urutan'] ?></td>
                                            <td><?= $k['is_active'] ? pill('Ya', 'green') : pill('Tidak', 'gray') ?></td>
                                            <td style="display:flex;gap:4px">
                                                <a href="?page=kategori&edit_kat=<?= $k['id'] ?>"
                                                    class="btn-action btn-green">Edit</a>
                                                <a href="?page=kategori&del_kat=<?= $k['id'] ?>" class="btn-action btn-red"
                                                    onclick="return confirm('Hapus kategori ini?')">Hapus</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php /* ══════════════════════ PAYMENT ══════════════════════ */
                elseif ($page === 'payment'): ?>

                    <div class="section-hd">
                        <h2><?= $edit ? 'Edit metode' : 'Tambah metode pembayaran' ?></h2>
                        <?php if ($edit): ?><a href="?page=payment" class="btn-sm">+ Baru</a><?php endif; ?>
                    </div>
                    <div class="card">
                        <!-- Schema payment_methods: method_code (UNI), method_name, admin_fee, is_active -->
                        <form method="POST"
                            style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;align-items:end">
                            <input type="hidden" name="action" value="save_pay" />
                            <input type="hidden" name="pid" value="<?= (int) ($edit['id'] ?? 0) ?>" />
                            <div class="f-group">
                                <label class="f-label">Kode metode *</label>
                                <input class="f-input" type="text" name="method_code" placeholder="bca_va"
                                    value="<?= esc($edit['method_code'] ?? '') ?>" />
                            </div>
                            <div class="f-group">
                                <label class="f-label">Nama tampil *</label>
                                <input class="f-input" type="text" name="method_name" placeholder="BCA Virtual Account"
                                    value="<?= esc($edit['method_name'] ?? '') ?>" />
                            </div>
                            <div class="f-group">
                                <label class="f-label">Admin fee (Rp)</label>
                                <input class="f-input" type="number" name="admin_fee"
                                    value="<?= $edit['admin_fee'] ?? '0' ?>" />
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;padding-bottom:2px">
                                <label style="display:flex;align-items:center;gap:5px;font-size:13px">
                                    <input type="checkbox" name="is_active" <?= ($edit['is_active'] ?? 1) ? 'checked' : '' ?> /> Aktifkan
                                </label>
                                <button type="submit" class="btn-action btn-purple" style="padding:7px 14px;font-size:13px">
                                    <?= $edit ? 'Perbarui' : 'Simpan' ?>
                                </button>
                            </div>
                        </form>
                    </div>

                    <?php $pays = mysqli_query($conn, "SELECT * FROM payment_methods ORDER BY method_name"); ?>
                    <div class="card">
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Kode</th>
                                        <th>Nama</th>
                                        <th>Admin Fee</th>
                                        <th>Aktif</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1;
                                    while ($pm = mysqli_fetch_assoc($pays)): ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td style="font-family:monospace"><?= esc($pm['method_code']) ?></td>
                                            <td><?= esc($pm['method_name']) ?></td>
                                            <td><?= rp($pm['admin_fee']) ?></td>
                                            <td><?= $pm['is_active'] ? pill('Ya', 'green') : pill('Tidak', 'gray') ?></td>
                                            <td style="display:flex;gap:4px">
                                                <a href="?page=payment&edit_pay=<?= $pm['id'] ?>"
                                                    class="btn-action btn-green">Edit</a>
                                                <a href="?page=payment&toggle_pay=<?= $pm['id'] ?>"
                                                    class="btn-action btn-amber">Toggle</a>
                                                <a href="?page=payment&del_pay=<?= $pm['id'] ?>" class="btn-action btn-red"
                                                    onclick="return confirm('Hapus metode ini?')">Hapus</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php /* ══════════════════════ PENGUMUMAN ══════════════════════ */
                elseif ($page === 'pengumuman'): ?>

                    <div class="section-hd">
                        <h2><?= $edit ? 'Edit pengumuman' : 'Tambah pengumuman' ?></h2>
                        <?php if ($edit): ?><a href="?page=pengumuman" class="btn-sm">+ Baru</a><?php endif; ?>
                    </div>
                    <div class="card">
                        <!-- Schema pengumuman: judul, isi, tipe(enum), sumber, is_active -->
                        <form method="POST" style="display:flex;flex-direction:column;gap:10px">
                            <input type="hidden" name="action" value="save_peng" />
                            <input type="hidden" name="pid" value="<?= (int) ($edit['id'] ?? 0) ?>" />
                            <div style="display:grid;grid-template-columns:1fr 160px;gap:10px">
                                <div class="f-group">
                                    <label class="f-label">Judul *</label>
                                    <input class="f-input" type="text" name="judul"
                                        value="<?= esc($edit['judul'] ?? '') ?>" />
                                </div>
                                <div class="f-group">
                                    <label class="f-label">Tipe</label>
                                    <select class="f-input" name="tipe">
                                        <?php foreach (['pengumuman', 'hadist', 'quote'] as $t): ?>
                                            <option value="<?= $t ?>" <?= ($edit['tipe'] ?? '') === $t ? 'selected' : '' ?>>
                                                <?= ucfirst($t) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="f-group">
                                <label class="f-label">Isi *</label>
                                <textarea class="f-input" name="isi" rows="4"
                                    style="resize:vertical"><?= esc($edit['isi'] ?? '') ?></textarea>
                            </div>
                            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                                <div class="f-group" style="flex:1;min-width:160px">
                                    <label class="f-label">Sumber (opsional)</label>
                                    <input class="f-input" type="text" name="sumber"
                                        value="<?= esc($edit['sumber'] ?? '') ?>" placeholder="HR. Bukhari no. 1" />
                                </div>
                                <label style="display:flex;align-items:center;gap:6px;font-size:13px;white-space:nowrap">
                                    <input type="checkbox" name="is_active" <?= ($edit['is_active'] ?? 1) ? 'checked' : '' ?> /> Aktifkan
                                </label>
                                <button type="submit" class="btn-action btn-purple" style="padding:8px 18px;font-size:13px">
                                    <?= $edit ? 'Perbarui' : 'Simpan' ?>
                                </button>
                            </div>
                        </form>
                    </div>

                    <?php $pengs = mysqli_query($conn, "SELECT * FROM pengumuman ORDER BY created_at DESC"); ?>
                    <div class="card">
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Judul</th>
                                        <th>Tipe</th>
                                        <th>Sumber</th>
                                        <th>Aktif</th>
                                        <th>Tanggal</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1;
                                    while ($pg = mysqli_fetch_assoc($pengs)): ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><?= esc(mb_strimwidth($pg['judul'], 0, 45, '…')) ?></td>
                                            <td><?= tipePill($pg['tipe']) ?></td>
                                            <td style="font-size:11px;color:var(--txt3)">
                                                <?= esc(mb_strimwidth($pg['sumber'] ?? '-', 0, 30, '…')) ?></td>
                                            <td><?= $pg['is_active'] ? pill('Ya', 'green') : pill('Tidak', 'gray') ?></td>
                                            <td style="color:var(--txt3)"><?= date('d M Y', strtotime($pg['created_at'])) ?>
                                            </td>
                                            <td style="display:flex;gap:4px">
                                                <a href="?page=pengumuman&edit_peng=<?= $pg['id'] ?>"
                                                    class="btn-action btn-green">Edit</a>
                                                <a href="?page=pengumuman&toggle_peng=<?= $pg['id'] ?>"
                                                    class="btn-action btn-amber">Toggle</a>
                                                <a href="?page=pengumuman&del_peng=<?= $pg['id'] ?>" class="btn-action btn-red"
                                                    onclick="return confirm('Hapus pengumuman ini?')">Hapus</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php /* ══════════════════════ DOA ══════════════════════ */
                elseif ($page === 'doa'): ?>

                    <!-- Schema doa_comments: id, campaign_id(varchar), donator_name, message, aamiin_count, created_at -->
                    <form method="GET" style="display:flex;gap:8px">
                        <input type="hidden" name="page" value="doa" />
                        <input class="f-input" type="text" name="q" placeholder="Cari nama donatur atau pesan…"
                            value="<?= esc($search) ?>" style="flex:1" />
                        <button type="submit" class="btn-sm">Cari</button>
                        <a href="?page=doa" class="btn-sm">Reset</a>
                    </form>

                    <?php
                    // Prepared statement — tidak pakai string interpolasi langsung
                    if ($search) {
                        $like = "%$search%";
                        $sdoa = mysqli_prepare(
                            $conn,
                            "SELECT * FROM doa_comments
           WHERE donator_name LIKE ? OR message LIKE ? OR campaign_id LIKE ?
           ORDER BY created_at DESC LIMIT 100"
                        );
                        mysqli_stmt_bind_param($sdoa, "sss", $like, $like, $like);
                        mysqli_stmt_execute($sdoa);
                        $doas = mysqli_stmt_get_result($sdoa);
                    } else {
                        $doas = mysqli_query(
                            $conn,
                            "SELECT * FROM doa_comments ORDER BY created_at DESC LIMIT 100"
                        );
                    }
                    ?>
                    <div class="card">
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Campaign ID</th>
                                        <th>Donatur</th>
                                        <th>Pesan</th>
                                        <th>Aamiin</th>
                                        <th>Tanggal</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1;
                                    while ($dc = mysqli_fetch_assoc($doas)): ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td style="font-family:monospace;font-size:11px"><?= esc($dc['campaign_id']) ?></td>
                                            <td><?= esc($dc['donator_name']) ?></td>
                                            <td title="<?= esc($dc['message']) ?>">
                                                <?= esc(mb_strimwidth($dc['message'], 0, 70, '…')) ?></td>
                                            <td style="text-align:center"><?= number_format($dc['aamiin_count']) ?></td>
                                            <td style="color:var(--txt3);white-space:nowrap">
                                                <?= date('d M Y H:i', strtotime($dc['created_at'])) ?></td>
                                            <td>
                                                <a href="?page=doa&del_doa=<?= $dc['id'] ?>" class="btn-action btn-red"
                                                    onclick="return confirm('Hapus komentar ini?')">Hapus</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php /* ══════════════════════ USERS ══════════════════════ */
                elseif ($page === 'users'): ?>

                    <!-- Schema users: full_name, email(UNI), phone(UNI), role(enum), password, created_at -->
                    <div class="section-hd">
                        <h2>Tambah pengguna baru</h2>
                    </div>
                    <div class="card">
                        <form method="POST"
                            style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;align-items:end">
                            <input type="hidden" name="action" value="add_user" />
                            <div class="f-group">
                                <label class="f-label">Nama lengkap *</label>
                                <input class="f-input" type="text" name="full_name" />
                            </div>
                            <div class="f-group">
                                <label class="f-label">Email * (unik)</label>
                                <input class="f-input" type="email" name="email" />
                            </div>
                            <div class="f-group">
                                <label class="f-label">No. HP * (unik)</label>
                                <input class="f-input" type="text" name="phone" />
                            </div>
                            <div class="f-group">
                                <label class="f-label">Password *</label>
                                <input class="f-input" type="password" name="password" />
                            </div>
                            <div class="f-group">
                                <label class="f-label">Role</label>
                                <select class="f-input" name="role">
                                    <option value="user">User</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div>
                                <button type="submit" class="btn-action btn-purple"
                                    style="width:100%;padding:8px;font-size:13px">+ Tambah</button>
                            </div>
                        </form>
                    </div>

                    <form method="GET" style="display:flex;gap:8px">
                        <input type="hidden" name="page" value="users" />
                        <input class="f-input" type="text" name="q" placeholder="Cari nama, email, atau HP…"
                            value="<?= esc($search) ?>" style="flex:1" />
                        <button type="submit" class="btn-sm">Cari</button>
                        <a href="?page=users" class="btn-sm">Reset</a>
                    </form>

                    <?php
                    // Prepared statement untuk search users
                    if ($search) {
                        $like = "%$search%";
                        $suser = mysqli_prepare(
                            $conn,
                            "SELECT * FROM users
           WHERE full_name LIKE ? OR email LIKE ? OR phone LIKE ?
           ORDER BY created_at DESC"
                        );
                        mysqli_stmt_bind_param($suser, "sss", $like, $like, $like);
                        mysqli_stmt_execute($suser);
                        $urows = mysqli_stmt_get_result($suser);
                    } else {
                        $urows = mysqli_query($conn, "SELECT * FROM users ORDER BY created_at DESC");
                    }
                    ?>
                    <div class="card">
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Nama</th>
                                        <th>Email</th>
                                        <th>No. HP</th>
                                        <th>Role</th>
                                        <th>Terdaftar</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1;
                                    while ($u = mysqli_fetch_assoc($urows)): ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><?= esc($u['full_name']) ?></td>
                                            <td style="font-size:12px;color:var(--txt3)"><?= esc($u['email']) ?></td>
                                            <td><?= esc($u['phone']) ?></td>
                                            <td><?= $u['role'] === 'admin' ? pill('Admin', 'purple') : pill('User', 'gray') ?>
                                            </td>
                                            <td style="color:var(--txt3)"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                                            <td style="display:flex;gap:4px">
                                                <?php if ($u['id'] != (int) $_SESSION['user_id']): ?>
                                                    <a href="?page=users&toggle_role=<?= $u['id'] ?>" class="btn-action btn-green"
                                                        onclick="return confirm('Ubah role pengguna ini?')">
                                                        <?= $u['role'] === 'admin' ? '→ User' : '→ Admin' ?>
                                                    </a>
                                                    <a href="?page=users&del_user=<?= $u['id'] ?>" class="btn-action btn-red"
                                                        onclick="return confirm('Hapus permanen?')">Hapus</a>
                                                <?php else: ?>
                                                    <span style="font-size:11px;color:var(--txt3)">Anda</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php endif; ?>

            </div><!-- /content -->
        </div><!-- /main -->
    </div><!-- /layout -->
</body>

</html>