<?php
session_start();
include 'koneksi.php';
require_once 'csrf.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validasi CSRF Token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Sesi form telah kadaluarsa. Silakan muat ulang halaman.";
    } else {
        $nama     = trim($_POST['nama'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validasi sisi server
        $valid = true;
        if (empty($nama)) {
            $error = "Nama tidak boleh kosong.";
            $valid = false;
        } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Masukkan email yang valid.";
            $valid = false;
        } elseif (empty($phone)) {
            $error = "Nomor HP tidak boleh kosong.";
            $valid = false;
        } elseif (strlen($password) < 8 || !preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/\d/', $password)) {
            $error = "Password minimal 8 karakter, memuat huruf besar, huruf kecil, dan angka.";
            $valid = false;
        }

        if ($valid) {
            // Cek duplikat email atau phone
            $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? OR phone = ?");
            mysqli_stmt_bind_param($stmt, "ss", $email, $phone);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $error = "Email atau nomor HP sudah terdaftar.";
            } else {
                // Simpan password dengan hash modern bcrypt
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $query = "INSERT INTO users (full_name, email, phone, password) VALUES (?, ?, ?, ?)";
                $stmt2 = mysqli_prepare($conn, $query);
                mysqli_stmt_bind_param($stmt2, "ssss", $nama, $email, $phone, $hashed_password);
                if (mysqli_stmt_execute($stmt2)) {
                    $success = "Registrasi berhasil! Mengalihkan ke halaman login...";
                    // Redirect setelah 2 detik
                    echo "<script>setTimeout(function(){ window.location.href='index.php'; }, 2000);</script>";
                } else {
                    $error = "Gagal mendaftar: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt2);
            }
            mysqli_stmt_close($stmt);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dimzz Register</title>
    <link rel="stylesheet" href="dimz.css" />
</head>
<body>
<div class="card">
    <div class="blob blob-pink"></div>
    <div class="blob blob-mint"></div>
    <div class="blob blob-yellow"></div>
    <div class="blob blob-circle"></div>

    <div class="logo-wrap">
        <div class="logo-box">
            <img src="logo.png" alt="Dimzz Logo" />
        </div>
    </div>

    <div class="heading">
        <h1>Register</h1>
        <p>Mari bergabung bersama orang baik lainnya.</p>
    </div>

    <?php if ($error): ?>
        <div style="background:#f8d7da; color:#721c24; padding:10px; border-radius:8px; margin-bottom:15px;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div style="background:#d4edda; color:#155724; padding:10px; border-radius:8px; margin-bottom:15px;"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="form">
        <form method="POST" action="" onsubmit="return validateForm()">
            <?= csrf_field() ?>
            <div class="field">
                <input type="text" name="nama" id="reg-name" placeholder="Nama Lengkap" autocomplete="name" value="<?= isset($_POST['nama']) ? htmlspecialchars($_POST['nama']) : '' ?>" />
                <span class="field-err" id="err-name">Nama tidak boleh kosong.</span>
            </div>

            <div class="field">
                <input type="email" name="email" id="reg-email" placeholder="Email" autocomplete="email" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" />
                <span class="field-err" id="err-email">Masukkan email yang valid.</span>
            </div>

            <div class="field">
                <input type="tel" name="phone" id="reg-phone" placeholder="No HP / Whatsapp Aktif" autocomplete="tel" value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>" />
                <span class="field-err" id="err-phone">Nomor HP tidak boleh kosong.</span>
            </div>

            <div class="field">
                <input type="password" name="password" id="reg-pw" placeholder="Password" class="has-toggle" autocomplete="new-password" oninput="checkPwStrength(this.value)" />
                <button class="toggle-eye" type="button" onclick="togglePw('reg-pw', this)" aria-label="Tampilkan password">👁️</button>
                <span class="field-err" id="err-pw">Password tidak memenuhi syarat.</span>
            </div>

            <div class="pw-hint">
                <ul>
                    <li id="hint-len" style="opacity:.5">Password minimal harus 8 karakter</li>
                    <li id="hint-case" style="opacity:.5">Terdiri dari 1 huruf kecil dan 1 huruf besar</li>
                    <li id="hint-num" style="opacity:.5">Memuat setidaknya satu angka juga</li>
                </ul>
            </div>

            <button type="submit" class="btn-primary">Daftar Sekarang</button>
        </form>

        <div class="form-footer">
            Sudah punya akun? <a href="index.php">Login di sini</a>
        </div>
    </div>
</div>

<script>
function togglePw(inputId, btn) {
    const inp = document.getElementById(inputId);
    const hidden = inp.type === 'password';
    inp.type = hidden ? 'text' : 'password';
    btn.innerHTML = hidden ? '🙈' : '👁️';
}

function checkPwStrength(val) {
    setHint('hint-len', val.length >= 8);
    setHint('hint-case', /[a-z]/.test(val) && /[A-Z]/.test(val));
    setHint('hint-num', /\d/.test(val));
}

function setHint(id, ok) {
    const el = document.getElementById(id);
    el.style.opacity = ok ? '1' : '.5';
    el.style.fontWeight = ok ? '600' : '400';
    el.style.color = ok ? '#1a7a5e' : '';
}

function validateForm() {
    let ok = true;
    const name = document.getElementById('reg-name').value.trim();
    const email = document.getElementById('reg-email').value.trim();
    const phone = document.getElementById('reg-phone').value.trim();
    const pw = document.getElementById('reg-pw').value;

    // Reset errors
    document.querySelectorAll('.field-err').forEach(e => e.classList.remove('show'));
    document.querySelectorAll('.field input').forEach(i => i.classList.remove('error', 'valid'));

    if (!name) { showErr('err-name', 'Nama tidak boleh kosong.'); ok = false; }
    if (!email || !/\S+@\S+\.\S+/.test(email)) { showErr('err-email', 'Masukkan email yang valid.'); ok = false; }
    if (!phone) { showErr('err-phone', 'Nomor HP tidak boleh kosong.'); ok = false; }
    const pwOk = pw.length >= 8 && /[a-z]/.test(pw) && /[A-Z]/.test(pw) && /\d/.test(pw);
    if (!pwOk) { showErr('err-pw', 'Password tidak memenuhi syarat di atas.'); ok = false; }
    return ok;
}

function showErr(id, msg) {
    const el = document.getElementById(id);
    el.textContent = msg;
    el.classList.add('show');
    el.previousElementSibling.classList.add('error');
}
</script>
</body>
</html>