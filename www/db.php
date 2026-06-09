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

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
        username       VARCHAR(64)   NOT NULL,
        email          VARCHAR(255)  NOT NULL,
        password       VARCHAR(255)  NOT NULL,
        email_verified TINYINT(1)    NOT NULL DEFAULT 0,
        created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_username (username),
        UNIQUE KEY uq_email    (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    // Migrate existing tables that predate the email_verified column.
    $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'email_verified'")->rowCount();
    if ($col === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS verify_tokens (
        token      CHAR(64)     NOT NULL,
        user_id    INT UNSIGNED NOT NULL,
        expires_at DATETIME     NOT NULL,
        PRIMARY KEY (token),
        KEY fk_vt_user (user_id),
        CONSTRAINT fk_vt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    $pdo->exec("CREATE TABLE IF NOT EXISTS pixels (
        x           INT UNSIGNED NOT NULL,
        y           INT UNSIGNED NOT NULL,
        color       CHAR(7)      NOT NULL,
        create_date TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (x, y)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    return $pdo;
}
