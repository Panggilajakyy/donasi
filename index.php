<?php
session_start();
include 'koneksi.php';
require_once 'csrf.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // 1. Validasi CSRF Token
  if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $error = "Sesi form telah kadaluarsa. Silakan muat ulang halaman.";
  } else {
    $email_or_phone = trim($_POST['email_or_phone'] ?? '');
    $password = $_POST['password'] ?? '';

    $valid = true;
    if (empty($email_or_phone)) {
      $error = "Email / Username tidak boleh kosong.";
      $valid = false;
    } elseif (empty($password)) {
      $error = "Password tidak boleh kosong.";
      $valid = false;
    }

    if ($valid) {
      // 2. Ambil data user
      $query = "SELECT id, full_name, email, phone, password, role FROM users WHERE email = ? OR phone = ? LIMIT 1";
      $stmt = mysqli_prepare($conn, $query);
      mysqli_stmt_bind_param($stmt, "ss", $email_or_phone, $email_or_phone);
      mysqli_stmt_execute($stmt);
      $result = mysqli_stmt_get_result($stmt);
      $user = mysqli_fetch_assoc($result);

      // 3. Verifikasi Password (Mendukung Hash Modern & Akun Lama Transparan)
      $password_matches = false;

      if ($user) {
        // Cek dengan password_verify jika sudah di-hash bcrypt
        if (password_verify($password, $user['password'])) {
          $password_matches = true;
        } 
        // Backward-compatibility: Jika akun lama masih plain text
        elseif ($password === $user['password']) {
          $password_matches = true;
          // Otomatis upgrade ke hash modern bcrypt
          $new_hash = password_hash($password, PASSWORD_DEFAULT);
          $up_stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
          mysqli_stmt_bind_param($up_stmt, "si", $new_hash, $user['id']);
          mysqli_stmt_execute($up_stmt);
          mysqli_stmt_close($up_stmt);
        }
      }

      if ($password_matches) {
        // Simpan data ke Session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];

        // Redirect Berdasarkan Role
        if ($user['role'] === 'admin') {
          header("Location: admin.php");
        } else {
          header("Location: db.php");
        }
        exit();
      } else {
        $error = "Email/Phone atau password salah.";
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
  <title>Dimzz Login</title>
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
        <img src="logo.png" alt="Dimzz Login" />
      </div>
    </div>

    <div class="heading">
      <h1>Login</h1>
      <p>Silahkan login jika sudah memiliki akun.</p>
    </div>

    <?php if ($error): ?>
      <div style="background:#f8d7da; color:#721c24; padding:10px; border-radius:8px; margin-bottom:15px;">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <div class="form">
      <form method="POST" action="" onsubmit="return validateForm()">
        <?= csrf_field() ?>
        <div class="field">
          <input type="text" name="email_or_phone" id="login-email" placeholder="Email / Username"
            autocomplete="username"
            value="<?= isset($_POST['email_or_phone']) ? htmlspecialchars($_POST['email_or_phone']) : '' ?>" />
          <span class="field-err" id="err-email">Email tidak boleh kosong.</span>
        </div>

        <div class="field">
          <input type="password" name="password" id="login-pw" placeholder="Password" class="has-toggle"
            autocomplete="current-password" />
          <button class="toggle-eye" type="button" onclick="togglePw('login-pw', this)"
            aria-label="Tampilkan password">👁️</button>
          <span class="field-err" id="err-pw">Password tidak boleh kosong.</span>
        </div>

        <button type="submit" class="btn-primary">Sign In</button>

        <div class="form-footer">
          Belum punya akun? <a href="register.php">Daftar sekarang</a>
        </div>
      </form>
    </div>
  </div>

  <script>
    function togglePw(inputId, btn) {
      const inp = document.getElementById(inputId);
      const hidden = inp.type === 'password';
      inp.type = hidden ? 'text' : 'password';
      btn.innerHTML = hidden ? '🙈' : '👁️';
    }

    function validateForm() {
      let ok = true;
      const email = document.getElementById('login-email').value.trim();
      const pw = document.getElementById('login-pw').value;

      document.querySelectorAll('.field-err').forEach(e => e.classList.remove('show'));
      document.querySelectorAll('.field input').forEach(i => i.classList.remove('error', 'valid'));

      if (!email) { showErr('err-email', 'Email tidak boleh kosong.'); ok = false; }
      if (!pw) { showErr('err-pw', 'Password tidak boleh kosong.'); ok = false; }
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