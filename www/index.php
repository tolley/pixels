<?php
$host = getenv('MYSQL_HOST') ?: 'db';
$db   = getenv('MYSQL_DATABASE') ?: 'appdb';
$user = getenv('MYSQL_USER') ?: 'appuser';
$pass = getenv('MYSQL_PASSWORD') ?: 'secret';

phpinfo();

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    echo '<p style="color:green">MySQL connected successfully.</p>';
} catch (PDOException $e) {
    echo '<p style="color:red">MySQL connection failed: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
