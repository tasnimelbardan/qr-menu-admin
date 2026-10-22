<?php
// Loads everything an admin page needs, then locks the page behind login.
require __DIR__ . '/db.php';
require __DIR__ . '/functions.php';
require __DIR__ . '/auth.php';
require_login();