<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/functions.php';

// 1) Get the active categories, in the order the admin chose
$categories = $pdo->query(
    'SELECT id, name, icon
     FROM categories
     WHERE is_active = 1
     ORDER BY sort_order, id'
)->fetchAll();

// 2) Get all active items and group them by category id
$itemsByCategory = [];
$items = $pdo->query(
    'SELECT category_id, name, description, price, image
     FROM items
     WHERE is_active = 1
     ORDER BY id'
);
foreach ($items as $row) {
    $itemsByCategory[$row['category_id']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Brew Co. | Menu</title>
  <meta name="description" content="Brew Co. digital menu: coffee, non-coffee drinks, and pastries.">
  <meta name="theme-color" content="#ffffff">
  <link rel="icon" type="image/png" href="images/logo.png">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>

<body>
  <div class="app">

    <header class="app-header">
      <img src="images/hamburger.png" alt="" class="header-icon">
      <img src="images/logo.png" alt="Brew Co. Coffee & More" class="logo">
      <img src="images/cart.png" alt="" class="header-icon">
    </header>

    <main>
      <section class="hero">
        <img src="images/menubanner.png" alt="Good Coffee, Good Day. Made with love, just for you." class="hero-img">
      </section>

      <h2 class="menu-title">MENU</h2>

      <?php foreach ($categories as $cat): ?>
        <?php
          $catItems = $itemsByCategory[$cat['id']] ?? [];
          if (!$catItems) { continue; }   // hide categories that have no active items
        ?>
        <section class="category">

          <div class="category-header">
            <div class="category-name">
              <i class="bi <?= e($cat['icon']) ?>" aria-hidden="true"></i>
              <h3><?= e(strtoupper($cat['name'])) ?></h3>
            </div>
            <span class="view-all">View all <i class="bi bi-chevron-right" aria-hidden="true"></i></span>
          </div>

          <ul class="item-list">
            <?php foreach ($catItems as $item): ?>
              <li class="item">
                <?php if (!empty($item['image'])): ?>
                  <img src="<?= e($item['image']) ?>" alt="<?= e($item['name']) ?>"
                       class="item-img" loading="lazy" width="64" height="64">
                <?php else: ?>
                  <div class="item-img item-img--empty" aria-hidden="true"></div>
                <?php endif; ?>

                <div class="item-info">
                  <h4><?= e($item['name']) ?></h4>
                  <p><?= e($item['description']) ?></p>
                </div>

                <span class="item-price">$<?= number_format((float)$item['price'], 2) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>

        </section>
      <?php endforeach; ?>

      <?php if (!$categories): ?>
        <p class="empty-msg">The menu is being updated. Please check back soon.</p>
      <?php endif; ?>

      <div class="delivery">
        <img src="images/scooter.png" alt="" class="delivery-img">
        <div class="delivery-text">
          <strong>We deliver to you!</strong>
          <span>Fast &amp; fresh to your door.</span>
        </div>
        <i class="bi bi-chevron-right" aria-hidden="true"></i>
      </div>
    </main>

  </div>
</body>
</html>