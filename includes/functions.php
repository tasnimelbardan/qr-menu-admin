<?php
// Small helpers shared by the customer page and the admin pages.

// Escapes text before printing it in HTML.
// This stops a name like <script>alert(1)</script> from running as code (XSS protection).
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// ---- Flash messages: shown once after a redirect ----
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];   // type: success or error
}

function get_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);   // remove it so it appears only once
    return $flash;
}

// ---- Image upload helpers ----
const UPLOAD_DIR     = __DIR__ . '/../uploads/';
const MAX_IMAGE_BYTES = 2 * 1024 * 1024;   // 2 MB

// Validates and saves an uploaded image.
// Returns the path to store in the database (e.g. "uploads/ab12...ef.jpg"),
// or null if no file was chosen or something was wrong (then $errors is filled).
function save_uploaded_image(array $file, array &$errors): ?string
{
    $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($code === UPLOAD_ERR_NO_FILE) {
        return null;                                   // no image chosen: not an error
    }
    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
        $errors[] = 'The image is too large (maximum 2 MB).';
        return null;
    }
    if ($code !== UPLOAD_ERR_OK) {
        $errors[] = 'The image upload failed. Please try again.';
        return null;
    }
    if ($file['size'] > MAX_IMAGE_BYTES) {
        $errors[] = 'The image is too large (maximum 2 MB).';
        return null;
    }

    // Read the REAL file type from the file content (the file name can be faked)
    $mime    = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    if (!isset($allowed[$mime]) || @getimagesize($file['tmp_name']) === false) {
        $errors[] = 'The image must be a JPG, PNG, or WebP file.';
        return null;
    }

    // Random name: no clashes and impossible to guess
    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];

    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $name)) {
        $errors[] = 'Could not save the image on the server.';
        return null;
    }

    return 'uploads/' . $name;
}

// Deletes an uploaded image file. Only touches files created by our own upload code,
// so the original images/ folder can never be deleted by mistake.
function delete_uploaded_image(?string $path): void
{
    if ($path && preg_match('#^uploads/[a-f0-9]{32}\.(jpg|png|webp)$#', $path)) {
        $full = UPLOAD_DIR . basename($path);
        if (is_file($full)) {
            unlink($full);
        }
    }
}