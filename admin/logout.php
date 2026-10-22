<?php
require __DIR__ . '/../includes/auth.php';

$_SESSION = [];                                   // empty the session data

if (ini_get('session.use_cookies')) {             // delete the session cookie too
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();
header('Location: login.php');
exit;