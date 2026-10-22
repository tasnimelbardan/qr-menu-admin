<?php
// Session, login, and CSRF helpers for the admin side.

// Security headers sent with every admin page
header('X-Frame-Options: DENY');              // the page can't be shown inside another site's frame
header('X-Content-Type-Options: nosniff');    // browsers must respect the declared file type
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');            // the Back button can't show private pages after logout

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');  // refuse session ids the server didn't create
    session_set_cookie_params([
        'httponly' => true,                   // JavaScript can't read the session cookie
        'samesite' => 'Lax',                  // basic protection against cross-site requests
        'secure'   => !empty($_SERVER['HTTPS']),  // HTTPS-only cookie when the site uses HTTPS
    ]);
    session_start();
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

// Put this at the top of every admin page.
// Not logged in, or the account was deleted? Send the visitor to the login page.
function require_login(): void
{
    global $pdo;

    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }

    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    if (!$stmt->fetchColumn()) {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php');
        exit;
    }
}

// ---- CSRF protection ----
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(403);
        exit('Invalid request. Please go back and try again.');
    }
}