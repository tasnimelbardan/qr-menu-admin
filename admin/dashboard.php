<?php
require __DIR__ . '/../includes/admin_init.php';

// ---------- 1) KPI numbers: one query, five counters ----------
$stats = $pdo->query(
    'SELECT
        (SELECT COUNT(*) FROM categories)                  AS total_categories,
        (SELECT COUNT(*) FROM categories WHERE is_active=1) AS active_categories,
        (SELECT COUNT(*) FROM items)                       AS total_items,
        (SELECT COUNT(*) FROM items WHERE is_active=1)      AS active_items,
        (SELECT COUNT(*) FROM users)                       AS total_users'
)->fetch();

// ---------- 2) Items per category (LEFT JOIN keeps empty categories too) ----------
$perCategory = $pdo->query(
    'SELECT c.name, c.is_active, COUNT(i.id) AS item_count
     FROM categories c
     LEFT JOIN items i ON i.category_id = c.id
     GROUP BY c.id, c.name, c.is_active
     ORDER BY c.sort_order, c.id'
)->fetchAll();

$counts   = array_map('intval', array_column($perCategory, 'item_count'));
$maxCount = max(1, $counts ? max($counts) : 1);   // used to scale the bars

// ---------- 3) Five most recently added items ----------
$recent = $pdo->query(
    'SELECT i.name, i.price, i.is_active, c.name AS category
     FROM items i
     JOIN categories c ON c.id = i.category_id
     ORDER BY i.id DESC
     LIMIT 5'
)->fetchAll();

// ---------- KPI cards definition: label, value, icon, link ----------
$cards = [
    ['Total Categories',   $stats['total_categories'],                           'bi-tags',         'categories.php'],
    ['Total Menu Items',   $stats['total_items'],                                'bi-cup-straw',    'items.php'],
    ['Active Menu Items',  $stats['active_items'],                               'bi-check-circle', 'items.php'],
    ['Inactive Items',     $stats['total_items'] - $stats['active_items'],       'bi-eye-slash',    'items.php'],
    ['Active Categories',  $stats['active_categories'],                          'bi-toggle-on',    'categories.php'],
    ['Admin Users',        $stats['total_users'],                                'bi-people',       'users.php'],
];

$pageTitle = 'Dashboard';
$active    = 'dashboard';
require __DIR__ . '/partials/header.php';
?>

<!-- KPI CARDS -->
<section class="kpi-grid">
  <?php foreach ($cards as [$label, $value, $icon, $link]): ?>
    <a href="<?= e($link) ?>" class="kpi">
      <div class="kpi-icon"><i class="bi <?= e($icon) ?>" aria-hidden="true"></i></div>
      <div>
        <div class="kpi-value"><?= (int) $value ?></div>
        <div class="kpi-label"><?= e($label) ?></div>
      </div>
    </a>
  <?php endforeach; ?>
</section>

<!-- TWO PANELS: items per category + recent items -->
<section class="panel-grid">

  <div class="card">
    <h2 class="card-title">Items per category</h2>

    <?php if (!$perCategory): ?>
      <p class="muted">No categories yet.</p>
    <?php else: ?>
      <ul class="bars">
        <?php foreach ($perCategory as $row): ?>
          <?php $n = (int) $row['item_count']; ?>
          <li>
            <div class="bar-head">
              <span>
                <?= e($row['name']) ?>
                <?php if (!$row['is_active']): ?><span class="badge badge-off">Inactive</span><?php endif; ?>
              </span>
              <strong><?= $n ?></strong>
            </div>
            <div class="bar-track">
              <div class="bar-fill" style="width: <?= round($n / $maxCount * 100) ?>%"></div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head">
      <h2 class="card-title">Recently added items</h2>
      <a href="items.php" class="btn btn-ghost btn-sm">View all</a>
    </div>

    <?php if (!$recent): ?>
      <p class="muted">No items yet.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th>Item</th><th>Category</th><th>Price</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php foreach ($recent as $r): ?>
              <tr>
                <td><?= e($r['name']) ?></td>
                <td class="muted-cell"><?= e($r['category']) ?></td>
                <td>$<?= number_format((float) $r['price'], 2) ?></td>
                <td>
                  <?php if ($r['is_active']): ?>
                    <span class="badge badge-on">Active</span>
                  <?php else: ?>
                    <span class="badge badge-off">Inactive</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</section>

<?php require __DIR__ . '/partials/footer.php'; ?>