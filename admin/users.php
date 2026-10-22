<?php
require __DIR__ . '/../includes/admin_init.php';

$currentId = (int) $_SESSION['user_id'];     // the admin who is logged in right now

// Default values for an empty "Add user" form
$form   = ['id' => 0, 'name' => '', 'username' => ''];
$errors = [];

// ===================== HANDLE FORM SUBMISSIONS =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // ---------- ADD or EDIT ----------
    if ($action === 'save') {
        $form['id']       = (int) ($_POST['id'] ?? 0);
        $form['name']     = trim($_POST['name'] ?? '');
        $form['username'] = trim($_POST['username'] ?? '');
        $password         = $_POST['password'] ?? '';          // not trimmed: spaces can be part of a password
        $confirm          = $_POST['password_confirm'] ?? '';
        $isNew            = $form['id'] === 0;

        // ----- Validation -----
        if ($form['name'] === '') {
            $errors[] = 'Name is required.';
        } elseif (mb_strlen($form['name']) > 100) {
            $errors[] = 'Name must be 100 characters or less.';
        }

        if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $form['username'])) {
            $errors[] = 'Username must be 3 to 50 characters: letters, numbers, dot, dash, or underscore.';
        }

        // Password rules: required for a new user, optional when editing
        if ($isNew || $password !== '') {
            if (strlen($password) < 8 || strlen($password) > 72) {
                $errors[] = 'Password must be between 8 and 72 characters.';
            } elseif ($password !== $confirm) {
                $errors[] = 'The two passwords do not match.';
            }
        }

        // Username must be unique (the user being edited is excluded from the check)
        if (!$errors) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(username) = LOWER(?) AND id <> ?');
            $stmt->execute([$form['username'], $form['id']]);
            if ($stmt->fetch()) {
                $errors[] = 'This username is already taken.';
            }
        }

        // When editing, make sure the user still exists
        if (!$errors && !$isNew) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
            $stmt->execute([$form['id']]);
            if (!$stmt->fetch()) {
                $errors[] = 'This user no longer exists.';
            }
        }

        // ----- Save -----
        if (!$errors) {
            try {
                if ($isNew) {
                    $stmt = $pdo->prepare('INSERT INTO users (name, username, password_hash) VALUES (?, ?, ?)');
                    $stmt->execute([$form['name'], $form['username'], password_hash($password, PASSWORD_DEFAULT)]);
                    set_flash('success', 'User added.');
                } else {
                    if ($password !== '') {
                        $stmt = $pdo->prepare('UPDATE users SET name = ?, username = ?, password_hash = ? WHERE id = ?');
                        $stmt->execute([
                            $form['name'], $form['username'],
                            password_hash($password, PASSWORD_DEFAULT), $form['id'],
                        ]);
                    } else {
                        $stmt = $pdo->prepare('UPDATE users SET name = ?, username = ? WHERE id = ?');
                        $stmt->execute([$form['name'], $form['username'], $form['id']]);
                    }

                    // If you edited yourself, keep the session in sync
                    if ($form['id'] === $currentId) {
                        $_SESSION['user_name'] = $form['name'];
                        if ($password !== '') {
                            session_regenerate_id(true);    // new session id after a password change
                        }
                    }
                    set_flash('success', 'User updated.');
                }
                header('Location: users.php');
                exit;
            } catch (PDOException $e) {
                $errors[] = 'Could not save the user. Please try again.';
            }
        }
        // With errors, we fall through and show the form again (passwords are never refilled).
    }

    // ---------- DELETE ----------
    elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id === $currentId) {
            set_flash('error', 'You cannot delete your own account.');
        } elseif ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() <= 1) {
            set_flash('error', 'You cannot delete the last admin.');
        } else {
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            set_flash('success', 'User deleted.');
        }
        header('Location: users.php');
        exit;
    }
}

// ===================== LOAD A USER INTO THE FORM (Edit button) =====================
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT id, name, username FROM users WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $row = $stmt->fetch();
    if ($row) {
        $form = $row;
    }
}
$isEdit = $form['id'] > 0;

// ===================== LOAD THE LIST =====================
$users = $pdo->query('SELECT id, name, username, created_at FROM users ORDER BY id')->fetchAll();

$pageTitle = 'Users';
$active    = 'users';
require __DIR__ . '/partials/header.php';
?>

<section class="split">

  <!-- ============ FORM CARD ============ -->
  <div class="card sticky">
    <h2 class="card-title"><?= $isEdit ? 'Edit user' : 'Add user' ?></h2>

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
        <label for="name">Full name</label>
        <input type="text" id="name" name="name" maxlength="100"
               value="<?= e($form['name']) ?>" placeholder="e.g. Sara Khalil" required>
      </div>

      <div class="field">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" maxlength="50"
               value="<?= e($form['username']) ?>" placeholder="e.g. sara" required>
        <small class="hint">Letters, numbers, dot, dash, underscore (3 to 50 characters).</small>
      </div>

      <div class="field">
        <label for="password"><?= $isEdit ? 'New password' : 'Password' ?></label>
        <input type="password" id="password" name="password" autocomplete="new-password"
               <?= $isEdit ? '' : 'required' ?>>
        <small class="hint">
          <?= $isEdit ? 'Leave empty to keep the current password.' : '8 to 72 characters.' ?>
        </small>
      </div>

      <div class="field">
        <label for="password_confirm">Confirm password</label>
        <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password"
               <?= $isEdit ? '' : 'required' ?>>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-brand">
          <i class="bi bi-check2" aria-hidden="true"></i> <?= $isEdit ? 'Save changes' : 'Add user' ?>
        </button>
        <?php if ($isEdit): ?>
          <a href="users.php" class="btn btn-ghost">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- ============ TABLE CARD ============ -->
  <div class="card">
    <div class="card-head">
      <h2 class="card-title">Admin users (<?= count($users) ?>)</h2>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Username</th>
            <th>Created</th>
            <th class="col-actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <?php $isMe = (int) $u['id'] === $currentId; ?>
            <tr>
              <td>
                <span class="cat-name">
                  <i class="bi bi-person-circle" aria-hidden="true"></i>
                  <?= e($u['name']) ?>
                  <?php if ($isMe): ?><span class="badge badge-you">You</span><?php endif; ?>
                </span>
              </td>
              <td class="muted-cell"><?= e($u['username']) ?></td>
              <td class="muted-cell"><?= e(date('d M Y', strtotime($u['created_at']))) ?></td>
              <td>
                <div class="row-actions">
                  <a href="users.php?edit=<?= (int) $u['id'] ?>" class="btn btn-ghost btn-sm">
                    <i class="bi bi-pencil" aria-hidden="true"></i> Edit
                  </a>

                  <?php if ($isMe): ?>
                    <button type="button" class="btn btn-danger btn-sm" disabled
                            title="You cannot delete your own account" aria-label="Delete not allowed">
                      <i class="bi bi-trash" aria-hidden="true"></i>
                    </button>
                  <?php else: ?>
                    <form method="post" class="inline-form"
                          onsubmit="return confirm('Delete this user? They will no longer be able to log in.');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm" aria-label="Delete user">
                        <i class="bi bi-trash" aria-hidden="true"></i>
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</section>

<?php require __DIR__ . '/partials/footer.php'; ?>