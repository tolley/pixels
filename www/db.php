<?php
function getDb(): PDO {
    $host   = getenv('MYSQL_HOST')     ?: 'db';
    $dbname = getenv('MYSQL_DATABASE') ?: 'appdb';
    $user   = getenv('MYSQL_USER')     ?: 'appuser';
    $pass   = getenv('MYSQL_PASSWORD') ?: 'secret';

    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8",
        $user, $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $pdo->exec("CREATE TABLE IF NOT EXISTS pixels (
        x     INT UNSIGNED NOT NULL,
        y     INT UNSIGNED NOT NULL,
        color CHAR(7)      NOT NULL,
        PRIMARY KEY (x, y)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    return $pdo;
}
