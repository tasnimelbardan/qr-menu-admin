<?php
// Top part of every admin page: <head>, sidebar, and top bar.
// The page must set these BEFORE including this file:
//   $pageTitle = 'Dashboard';     (shown in the top bar and the browser tab)
//   $active    = 'dashboard';     (dashboard | categories | items | users)
$active = $active ?? '';
$flash  = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($pageTitle) ?> | Brew Co. Admin</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/admin.css">
</head>
<body>
<div class="admin-shell">

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <i class="bi bi-cup-hot" aria-hidden="true"></i>
      <span>Brew Co. Admin</span>
    </div>

    <nav class="nav">
      <a href="dashboard.php" class="<?= $active === 'dashboard' ? 'active' : '' ?>">
        <i class="bi bi-speedometer2" aria-hidden="true"></i> Dashboard
      </a>
      <a href="categories.php" class="<?= $active === 'categories' ? 'active' : '' ?>">
        <i class="bi bi-tags" aria-hidden="true"></i> Categories
      </a>
      <a href="items.php" class="<?= $active === 'items' ? 'active' : '' ?>">
        <i class="bi bi-cup-straw" aria-hidden="true"></i> Items
      </a>
      <a href="users.php" class="<?= $active === 'users' ? 'active' : '' ?>">
        <i class="bi bi-people" aria-hidden="true"></i> Users
      </a>

      <div class="nav-sep"></div>

      <a href="../index.php" target="_blank" rel="noopener">
        <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> View menu
      </a>

      <a href="logout.php" class="nav-logout">
        <i class="bi bi-box-arrow-left" aria-hidden="true"></i> Log out
      </a>
    </nav>
  </aside>

  <!-- Dark layer behind the sidebar on phones -->
  <div class="overlay" id="overlay"></div>

  <!-- MAIN AREA -->
  <div class="main">

    <header class="topbar">
      <div class="topbar-left">
        <button type="button" class="menu-toggle" id="menuToggle" aria-label="Open menu">
          <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <h1><?= e($pageTitle) ?></h1>
      </div>
      <div class="user-chip">
        <i class="bi bi-person-circle" aria-hidden="true"></i>
        <span><?= e($_SESSION['user_name'] ?? '') ?></span>
      </div>
    </header>

    <main class="content">
      <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>" role="alert"><?= e($flash['message']) ?></div>
      <?php endif; ?>