<?php

function debug( $v ) {
    echo '<pre>';
    print_r( $v );
    echo '</pre>';
}

function dine() {
	$trace = debug_backtrace();
	$level = array_shift( $trace );
	die( 'died: ' . $level['file'] . ' ' . $level['line'] );
}

function mark() {
    $trace = debug_backtrace();
	$level = array_shift( $trace );
	echo 'MARK: ' . $level['file'] . ' ' . $level['line'];
}

function getDb(): PDO {
    $env = parse_ini_file( '.env', false, INI_SCANNER_RAW );

    $host   = $env['MYSQL_HOST'] ?: 'db';
    $dbname = $env['MYSQL_DATABASE'] ?: 'appdb';
    $user   = $env['MYSQL_USER']     ?: 'tolley'; // 'appuser';
    $pass   = $env['MYSQL_PASSWORD'] ?: 'secret';
    $port   = $env['MYSQL_PORT']     ?: '3306';

    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8;port=$port",
            $user, $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    } catch( Exception $e ) {
        echo 'Could not connect to the DB!';
        echo '<pre>'; print_r( $e ); echo '</pre>';
        die();
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
        username       VARCHAR(64)   NOT NULL,
        email          VARCHAR(255)  NOT NULL,
        password       VARCHAR(255)  NULL,
        email_verified TINYINT(1)    NOT NULL DEFAULT 0,
        oauth_provider VARCHAR(32)   NULL,
        oauth_subject  VARCHAR(255)  NULL,
        created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_username (username),
        UNIQUE KEY uq_email    (email),
        UNIQUE KEY uq_oauth    (oauth_provider, oauth_subject)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    // Migrate existing tables that predate the email_verified column.
    $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'email_verified'")->rowCount();
    if ($col === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0");
    }

    // Migrate existing tables that predate the OAuth columns.
    $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'oauth_provider'")->rowCount();
    if ($col === 0) {
        $pdo->exec("ALTER TABLE users
            MODIFY COLUMN password VARCHAR(255) NULL,
            ADD COLUMN oauth_provider VARCHAR(32)  NULL,
            ADD COLUMN oauth_subject  VARCHAR(255) NULL");
        $pdo->exec("ALTER TABLE users ADD UNIQUE KEY uq_oauth (oauth_provider, oauth_subject)");
    }

    $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_admin'")->rowCount();
    if ($col === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0");
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
        c           CHAR(7)      NOT NULL,
        create_date TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (x, y)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    // Migrate: rename color → c
    if ($pdo->query("SHOW COLUMNS FROM pixels LIKE 'color'")->rowCount() > 0) {
        $pdo->exec("ALTER TABLE pixels CHANGE color c CHAR(7) NOT NULL");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS pending_pixels (
        id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
        batch_id     CHAR(32)      NOT NULL,
        x            INT UNSIGNED  NOT NULL,
        y            INT UNSIGNED  NOT NULL,
        c            CHAR(7)       NULL,
        user_id      INT UNSIGNED  NOT NULL,
        username     VARCHAR(64)   NOT NULL,
        submitted_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        status       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        reviewed_at  TIMESTAMP     NULL,
        PRIMARY KEY (id),
        KEY idx_batch  (batch_id),
        KEY idx_status (status),
        CONSTRAINT fk_pp_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

    // Migrate: rename color → c
    if ($pdo->query("SHOW COLUMNS FROM pending_pixels LIKE 'color'")->rowCount() > 0) {
        $pdo->exec("ALTER TABLE pending_pixels CHANGE color c CHAR(7) NULL");
    }

    return $pdo;
}
