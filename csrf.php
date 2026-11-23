<?php
// csrf.php — Helper Proteksi CSRF (Cross-Site Request Forgery)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Dapatkan token CSRF aktif untuk sesi saat ini, atau buat baru jika belum ada.
 * @return string
 */
function get_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validasi token CSRF dari request form.
 * @param string|null $token
 * @return bool
 */
function verify_csrf_token(?string $token): bool
{
    if (!empty($_SESSION['csrf_token']) && !empty($token)) {
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    return false;
}

/**
 * Render input HTML hidden untuk form.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8') . '" />';
}
