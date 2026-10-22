<?php
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/auth.php';

// Already logged in? Skip the login page.
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error    = '';
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Housekeeping: forget attempts older than a day
    $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');

    // 5 failed attempts in the last 15 minutes from this address? Block before checking anything.
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)'
    );
    $stmt->execute([$ip]);

    if ((int) $stmt->fetchColumn() >= 5) {
        $error = 'Too many failed attempts. Please try again in 15 minutes.';
    } else {
        $stmt = $pdo->prepare('SELECT id, name, password_hash FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $pdo->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);   // success clears the counter
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            header('Location: dashboard.php');
            exit;
        }

        $pdo->prepare('INSERT INTO login_attempts (ip) VALUES (?)')->execute([$ip]);
        usleep(300000);
        $error = 'Wrong username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login | Brew Co.</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/admin.css">
</head>
<body class="login-page">

  <main class="login-card">
    <div class="login-icon"><i class="bi bi-cup-hot" aria-hidden="true"></i></div>
    <h1>Brew Co. Admin</h1>
    <p class="sub">Sign in to manage the menu</p>

    <?php if ($error): ?>
      <div class="alert-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <?= csrf_field() ?>

      <div class="field">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" value="<?= e($username) ?>" required autofocus>
      </div>

      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
      </div>

      <button type="submit" class="btn btn-brand btn-block">Log in</button>
    </form>
  </main>

</body>
</html>