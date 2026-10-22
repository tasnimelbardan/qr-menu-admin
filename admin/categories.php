<?php
require __DIR__ . '/../includes/admin_init.php';

// Icons the admin can choose from (key = Bootstrap Icons class)
$icons = [
    'bi-cup-hot'   => 'Hot drink',
    'bi-cup-straw' => 'Cold drink',
    'bi-cup'       => 'Cup',
    'bi-cake2'     => 'Cake / pastry',
    'bi-egg-fried' => 'Breakfast',
    'bi-basket'    => 'Basket',
    'bi-snow'      => 'Iced',
    'bi-fire'      => 'Hot / special',
    'bi-star'      => 'Favorites',
    'bi-gift'      => 'Gift',
];

// Default values for the form (an empty "Add" form)
$form   = ['id' => 0, 'name' => '', 'icon' => 'bi-cup-hot', 'sort_order' => '', 'is_active' => 1];
$errors = [];

// ===================== HANDLE FORM SUBMISSIONS =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // ---------- ADD or EDIT ----------
    if ($action === 'save') {
        $form['id']         = (int) ($_POST['id'] ?? 0);
        $form['name']       = trim($_POST['name'] ?? '');
        $form['icon']       = $_POST['icon'] ?? '';
        $form['sort_order'] = trim($_POST['sort_order'] ?? '');
        $form['is_active']  = isset($_POST['is_active']) ? 1 : 0;

        // Validation: collect every problem, then show them together
        if ($form['name'] === '') {
            $errors[] = 'Category name is required.';
        } elseif (mb_strlen($form['name']) > 100) {
            $errors[] = 'Category name must be 100 characters or less.';
        }
        if (!isset($icons[$form['icon']])) {
            $errors[] = 'Please choose a valid icon.';
        }
        if ($form['sort_order'] !== '' && !preg_match('/^\d{1,5}$/', $form['sort_order'])) {
            $errors[] = 'Sort order must be a whole number (0 or more).';
        }

        // No two categories with the same name
        if (!$errors) {
            $stmt = $pdo->prepare('SELECT id FROM categories WHERE LOWER(name) = LOWER(?) AND id <> ?');
            $stmt->execute([$form['name'], $form['id']]);
            if ($stmt->fetch()) {
                $errors[] = 'A category with this name already exists.';
            }
        }

        if (!$errors) {
            // Empty sort order = put it last
            if ($form['sort_order'] === '') {
                $sort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM categories')->fetchColumn();
            } else {
                $sort = (int) $form['sort_order'];
            }

            if ($form['id'] > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE categories SET name = ?, icon = ?, sort_order = ?, is_active = ? WHERE id = ?'
                );
                $stmt->execute([$form['name'], $form['icon'], $sort, $form['is_active'], $form['id']]);
                set_flash('success', 'Category updated.');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO categories (name, icon, sort_order, is_active) VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([$form['name'], $form['icon'], $sort, $form['is_active']]);
                set_flash('success', 'Category added.');
            }
            header('Location: categories.php');
            exit;
        }
        // If there were errors, we fall through and show the page again with the typed values.
    }

    // ---------- ACTIVATE / DEACTIVATE ----------
    elseif ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE categories SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        set_flash('success', 'Category status changed.');
        header('Location: categories.php');
        exit;
    }

    // ---------- DELETE ----------
    elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM items WHERE category_id = ?');
        $stmt->execute([$id]);
        $itemCount = (int) $stmt->fetchColumn();

        if ($itemCount > 0) {
            set_flash('error', "This category still has $itemCount item(s). Move or delete them first.");
        } else {
            try {
                $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
                set_flash('success', 'Category deleted.');
            } catch (PDOException $e) {
                set_flash('error', 'Could not delete this category.');
            }
        }
        header('Location: categories.php');
        exit;
    }
}

// ===================== LOAD A CATEGORY INTO THE FORM (Edit button) =====================
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT id, name, icon, sort_order, is_active FROM categories WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $row = $stmt->fetch();
    if ($row) {
        $form = $row;
    }
}
$isEdit = $form['id'] > 0;

// ===================== LOAD THE LIST =====================
$categories = $pdo->query(
    'SELECT c.id, c.name, c.icon, c.sort_order, c.is_active, COUNT(i.id) AS item_count
     FROM categories c
     LEFT JOIN items i ON i.category_id = c.id
     GROUP BY c.id, c.name, c.icon, c.sort_order, c.is_active
     ORDER BY c.sort_order, c.id'
)->fetchAll();

$pageTitle = 'Categories';
$active    = 'categories';
require __DIR__ . '/partials/header.php';
?>

<section class="split">

  <!-- ============ FORM CARD ============ -->
  <div class="card sticky">
    <h2 class="card-title"><?= $isEdit ? 'Edit category' : 'Add category' ?></h2>

    <?php if ($errors): ?>
      <div class="alert alert-error" role="alert">
        <ul>
          <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">

      <div class="field">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" maxlength="100"
               value="<?= e($form['name']) ?>" placeholder="e.g. Coffee" required>
      </div>

      <div class="field">
        <label for="icon">Icon</label>
        <div class="icon-select">
          <span class="icon-preview"><i id="iconPreview" class="bi <?= e($form['icon']) ?>"></i></span>
          <select id="icon" name="icon">
            <?php foreach ($icons as $class => $label): ?>
              <option value="<?= e($class) ?>" <?= $form['icon'] === $class ? 'selected' : '' ?>>
                <?= e($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="field">
        <label for="sort_order">Display order</label>
        <input type="number" id="sort_order" name="sort_order" min="0" max="99999"
               value="<?= e((string) $form['sort_order']) ?>" placeholder="Leave empty to put it last">
        <small class="hint">Smaller numbers appear first on the menu.</small>
      </div>

      <div class="field">
        <label class="check">
          <input type="checkbox" name="is_active" value="1" <?= $form['is_active'] ? 'checked' : '' ?>>
          <span>Active (visible on the customer menu)</span>
        </label>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-brand">
          <i class="bi bi-check2" aria-hidden="true"></i> <?= $isEdit ? 'Save changes' : 'Add category' ?>
        </button>
        <?php if ($isEdit): ?>
          <a href="categories.php" class="btn btn-ghost">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- ============ TABLE CARD ============ -->
  <div class="card">
    <div class="card-head">
      <h2 class="card-title">All categories (<?= count($categories) ?>)</h2>
    </div>

    <?php if (!$categories): ?>
      <p class="muted">No categories yet. Add your first one using the form.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Order</th>
              <th>Category</th>
              <th>Items</th>
              <th>Status</th>
              <th class="col-actions">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($categories as $c): ?>
              <tr>
                <td class="muted-cell"><?= (int) $c['sort_order'] ?></td>
                <td>
                  <span class="cat-name">
                    <i class="bi <?= e($c['icon']) ?>" aria-hidden="true"></i>
                    <?= e($c['name']) ?>
                  </span>
                </td>
                <td><?= (int) $c['item_count'] ?></td>
                <td>
                  <?php if ($c['is_active']): ?>
                    <span class="badge badge-on">Active</span>
                  <?php else: ?>
                    <span class="badge badge-off">Inactive</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="row-actions">
                    <a href="categories.php?edit=<?= (int) $c['id'] ?>" class="btn btn-ghost btn-sm">
                      <i class="bi bi-pencil" aria-hidden="true"></i> Edit
                    </a>

                    <form method="post" class="inline-form">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                      <button type="submit" class="btn btn-ghost btn-sm">
                        <?= $c['is_active'] ? 'Deactivate' : 'Activate' ?>
                      </button>
                    </form>

                    <form method="post" class="inline-form"
                          onsubmit="return confirm('Delete this category? This cannot be undone.');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm" aria-label="Delete category">
                        <i class="bi bi-trash" aria-hidden="true"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

</section>

<script>
  // Updates the small icon preview when a different icon is chosen
  document.getElementById('icon').addEventListener('change', function () {
    document.getElementById('iconPreview').className = 'bi ' + this.value;
  });
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>