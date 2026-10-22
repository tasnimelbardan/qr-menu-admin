<?php
// Database connection. Every page that needs the database includes this file.

$host   = 'localhost';
$dbname = 'brew_menu';
$user   = 'root';
$pass   = '';          // XAMPP's default MySQL password is empty

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // show errors as exceptions
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // rows come back as arrays
            PDO::ATTR_EMULATE_PREPARES   => false,                   // real prepared statements
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed.');   // never show technical details to visitors
}