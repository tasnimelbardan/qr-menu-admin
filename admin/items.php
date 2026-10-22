<?php
require __DIR__ . '/../includes/admin_init.php';

// ===================== FILTERS (from the address bar) =====================
$filters = [
    'q'      => trim($_GET['q'] ?? ''),
    'cat'    => (int) ($_GET['cat'] ?? 0),
    'status' => in_array($_GET['status'] ?? '', ['active', 'inactive'], true) ? $_GET['status'] : '',
];
$qs      = http_build_query(array_filter($filters));      // e.g. "q=latte&cat=1" (empty values removed)
$backUrl = 'items.php' . ($qs ? '?' . $qs : '');          // where we return after every action

// All categories, for the dropdowns
$cats = $pdo->query('SELECT id, name, is_active FROM categories ORDER BY sort_order, id')->fetchAll();

// Default values for an empty "Add item" form
$form = [
    'id'          => 0,
    'name'        => '',
    'description' => '',
    'price'       => '',
    'category_id' => $filters['cat'] ?: ($cats[0]['id'] ?? 0),
    'image'       => null,
    'is_active'   => 1,
];
$errors = [];

// ===================== HANDLE FORM SUBMISSIONS =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // ---------- ADD or EDIT ----------
    if ($action === 'save') {
        $form['id']          = (int) ($_POST['id'] ?? 0);
        $form['name']        = trim($_POST['name'] ?? '');
        $form['description'] = trim($_POST['description'] ?? '');
        $form['price']       = str_replace(',', '.', trim($_POST['price'] ?? ''));
        $form['category_id'] = (int) ($_POST['category_id'] ?? 0);
        $form['is_active']   = isset($_POST['is_active']) ? 1 : 0;
        $removeImage         = isset($_POST['remove_image']);

        // ----- Validation -----
        if ($form['name'] === '') {
            $errors[] = 'Item name is required.';
        } elseif (mb_strlen($form['name']) > 100) {
            $errors[] = 'Item name must be 100 characters or less.';
        }
        if (mb_strlen($form['description']) > 255) {
            $errors[] = 'Description must be 255 characters or less.';
        }
        if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $form['price'])) {
            $errors[] = 'Price must be a number like 3.50 (up to 6 digits, 2 decimals).';
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE id = ?');
        $stmt->execute([$form['category_id']]);
        if (!$stmt->fetchColumn()) {
            $errors[] = 'Please choose a valid category.';
        }

        // When editing: load the current image so we know what to replace or keep
        $oldImage = null;
        if ($form['id'] > 0) {
            $stmt = $pdo->prepare('SELECT image FROM items WHERE id = ?');
            $stmt->execute([$form['id']]);
            $existing = $stmt->fetch();
            if (!$existing) {
                $errors[] = 'This item no longer exists.';
            } else {
                $oldImage      = $existing['image'];
                $form['image'] = $oldImage;     // so the form still shows it if validation fails
            }
        }

        // ----- Image upload (only if everything else is valid) -----
        $newImage = null;
        if (!$errors) {
            $newImage = save_uploaded_image($_FILES['image'] ?? [], $errors);
        }

        // ----- Save to the database -----
        if (!$errors) {
            if ($newImage)        { $image = $newImage; }
            elseif ($removeImage) { $image = null; }
            else                  { $image = $oldImage; }

            $price = number_format((float) $form['price'], 2, '.', '');

            try {
                if ($form['id'] > 0) {
                    $stmt = $pdo->prepare(
                        'UPDATE items
                         SET category_id = ?, name = ?, description = ?, price = ?, image = ?, is_active = ?
                         WHERE id = ?'
                    );
                    $stmt->execute([
                        $form['category_id'], $form['name'], $form['description'],
                        $price, $image, $form['is_active'], $form['id'],
                    ]);
                    set_flash('success', 'Item updated.');
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO items (category_id, name, description, price, image, is_active)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        $form['category_id'], $form['name'], $form['description'],
                        $price, $image, $form['is_active'],
                    ]);
                    set_flash('success', 'Item added.');
                }

                // The old uploaded file is no longer used: remove it
                if ($oldImage && $oldImage !== $image) {
                    delete_uploaded_image($oldImage);
                }

                header('Location: ' . $backUrl);
                exit;
            } catch (PDOException $e) {
                delete_uploaded_image($newImage);      // don't leave an orphan file behind
                $errors[] = 'Could not save the item. Please try again.';
            }
        }
        // With errors, we fall through and show the form again with the typed values.
    }

    // ---------- ACTIVATE / DEACTIVATE ----------
    elseif ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE items SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        set_flash('success', 'Item status changed.');
        header('Location: ' . $backUrl);
        exit;
    }

    // ---------- DELETE ----------
    elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);

        $stmt = $pdo->prepare('SELECT image FROM items WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if ($row) {
            $pdo->prepare('DELETE FROM items WHERE id = ?')->execute([$id]);
            delete_uploaded_image($row['image']);
            set_flash('success', 'Item deleted.');
        } else {
            set_flash('error', 'This item no longer exists.');
        }
        header('Location: ' . $backUrl);
        exit;
    }
}

// ===================== LOAD AN ITEM INTO THE FORM (Edit button) =====================
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['edit'])) {
    $stmt = $pdo->prepare(
        'SELECT id, name, description, price, category_id, image, is_active FROM items WHERE id = ?'
    );
    $stmt->execute([(int) $_GET['edit']]);
    $row = $stmt->fetch();
    if ($row) {
        $form = $row;
    }
}
$isEdit = $form['id'] > 0;

// ===================== LOAD THE LIST (with filters) =====================
$where  = [];
$params = [];

if ($filters['q'] !== '') {
    $where[]  = '(i.name LIKE ? OR i.description LIKE ?)';
    $like     = '%' . addcslashes($filters['q'], '%_\\') . '%';
    $params[] = $like;
    $params[] = $like;
}
if ($filters['cat'] > 0) {
    $where[]  = 'i.category_id = ?';
    $params[] = $filters['cat'];
}
if ($filters['status'] === 'active')   { $where[] = 'i.is_active = 1'; }
if ($filters['status'] === 'inactive') { $where[] = 'i.is_active = 0'; }

$sql = 'SELECT i.id, i.name, i.description, i.price, i.image, i.is_active, c.name AS category
        FROM items i
        JOIN categories c ON c.id = i.category_id'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY c.sort_order, c.id, i.id';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

$hasFilters = ($qs !== '');

$pageTitle = 'Items';
$active    = 'items';
require __DIR__ . '/partials/header.php';
?>

<section class="split">

  <!-- ============ FORM CARD ============ -->
  <div class="card sticky">
    <h2 class="card-title"><?= $isEdit ? 'Edit item' : 'Add item' ?></h2>

    <?php if (!$cats): ?>
      <p class="muted">You need at least one category before adding items.</p>
      <p><a href="categories.php" class="btn btn-brand btn-sm">Create a category</a></p>
    <?php else: ?>

      <?php if ($errors): ?>
        <div class="alert alert-error" role="alert">
          <ul>
            <?php foreach ($errors as $err): ?>
              <li><?= e($err) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= e($backUrl) ?>" enctype="multipart/form-data" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">

        <div class="field">
          <label for="name">Name</label>
          <input type="text" id="name" name="name" maxlength="100"
                 value="<?= e($form['name']) ?>" placeholder="e.g. Latte" required>
        </div>

        <div class="field">
          <label for="description">Description</label>
          <textarea id="description" name="description" rows="3" maxlength="255"
                    placeholder="Short description"><?= e($form['description']) ?></textarea>
        </div>

        <div class="field">
          <label for="price">Price ($)</label>
          <input type="text" id="price" name="price" inputmode="decimal"
                 value="<?= e((string) $form['price']) ?>" placeholder="3.50" required>
        </div>

        <div class="field">
          <label for="category_id">Category</label>
          <select id="category_id" name="category_id" required>
            <?php foreach ($cats as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= (int) $form['category_id'] === (int) $c['id'] ? 'selected' : '' ?>>
                <?= e($c['name']) ?><?= $c['is_active'] ? '' : ' (inactive)' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="image">Image</label>

          <div class="image-box">
            <?php if (!empty($form['image'])): ?>
              <img src="../<?= e($form['image']) ?>" alt="Current image" class="image-preview" id="imagePreview">
            <?php else: ?>
              <img src="" alt="" class="image-preview" id="imagePreview" hidden>
            <?php endif; ?>
          </div>

          <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
          <small class="hint">JPG, PNG, or WebP, up to 2 MB. Square photos look best.</small>

          <?php if ($isEdit && !empty($form['image'])): ?>
            <label class="check check-spaced">
              <input type="checkbox" name="remove_image" value="1">
              <span>Remove the current image</span>
            </label>
          <?php endif; ?>
        </div>

        <div class="field">
          <label class="check">
            <input type="checkbox" name="is_active" value="1" <?= $form['is_active'] ? 'checked' : '' ?>>
            <span>Active (visible on the customer menu)</span>
          </label>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-brand">
            <i class="bi bi-check2" aria-hidden="true"></i> <?= $isEdit ? 'Save changes' : 'Add item' ?>
          </button>
          <?php if ($isEdit): ?>
            <a href="<?= e($backUrl) ?>" class="btn btn-ghost">Cancel</a>
          <?php endif; ?>
        </div>
      </form>

    <?php endif; ?>
  </div>

  <!-- ============ LIST CARD ============ -->
  <div class="card">
    <div class="card-head">
      <h2 class="card-title">Items (<?= count($items) ?>)</h2>
    </div>

    <!-- Filter bar (a GET form: filters go into the address bar) -->
    <form method="get" class="filters">
      <input type="search" name="q" class="grow" value="<?= e($filters['q']) ?>" placeholder="Search by name or description">

      <select name="cat" aria-label="Filter by category">
        <option value="0">All categories</option>
        <?php foreach ($cats as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= $filters['cat'] === (int) $c['id'] ? 'selected' : '' ?>>
            <?= e($c['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <select name="status" aria-label="Filter by status">
        <option value="">Any status</option>
        <option value="active"   <?= $filters['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
        <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
      </select>

      <button type="submit" class="btn btn-ghost">
        <i class="bi bi-funnel" aria-hidden="true"></i> Filter
      </button>
      <?php if ($hasFilters): ?>
        <a href="items.php" class="btn btn-ghost">Reset</a>
      <?php endif; ?>
    </form>

    <?php if (!$items): ?>
      <p class="muted">
        <?= $hasFilters ? 'No items match your filters.' : 'No items yet. Add your first one using the form.' ?>
      </p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="table table-wide">
          <thead>
            <tr>
              <th>Item</th>
              <th>Category</th>
              <th>Price</th>
              <th>Status</th>
              <th class="col-actions">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $it): ?>
              <tr>
                <td>
                  <div class="item-cell">
                    <?php if (!empty($it['image'])): ?>
                      <img src="../<?= e($it['image']) ?>" alt="" class="thumb" loading="lazy">
                    <?php else: ?>
                      <div class="thumb thumb-empty"><i class="bi bi-image" aria-hidden="true"></i></div>
                    <?php endif; ?>
                    <div>
                      <strong><?= e($it['name']) ?></strong>
                      <?php if ($it['description'] !== ''): ?>
                        <small><?= e($it['description']) ?></small>
                      <?php endif; ?>
                    </div>
                  </div>
                </td>
                <td class="muted-cell"><?= e($it['category']) ?></td>
                <td class="price-cell">$<?= number_format((float) $it['price'], 2) ?></td>
                <td>
                  <?php if ($it['is_active']): ?>
                    <span class="badge badge-on">Active</span>
                  <?php else: ?>
                    <span class="badge badge-off">Inactive</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="row-actions">
                    <a href="items.php?edit=<?= (int) $it['id'] ?><?= $qs ? '&' . e($qs) : '' ?>" class="btn btn-ghost btn-sm">
                      <i class="bi bi-pencil" aria-hidden="true"></i> Edit
                    </a>

                    <form method="post" action="<?= e($backUrl) ?>" class="inline-form">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
                      <button type="submit" class="btn btn-ghost btn-sm">
                        <?= $it['is_active'] ? 'Deactivate' : 'Activate' ?>
                      </button>
                    </form>

                    <form method="post" action="<?= e($backUrl) ?>" class="inline-form"
                          onsubmit="return confirm('Delete this item? This cannot be undone.');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm" aria-label="Delete item">
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
  // Shows a live preview of the image the admin just selected
  var fileInput = document.getElementById('image');
  if (fileInput) {
    fileInput.addEventListener('change', function () {
      var preview = document.getElementById('imagePreview');
      if (this.files && this.files[0]) {
        preview.src = URL.createObjectURL(this.files[0]);
        preview.hidden = false;
      }
    });
  }
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>