<?php

require_once( 'db.php' );

/**
 * renderPage
 * @param string body: The main body (html) for the page
 * @param string cssBodyClass: The class name to apply to the html body element
 * @return string pageHtml: The html for the page
 */
function renderPage( string $body, string $cssBodyClass ): string {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Pixel Playground</title>
        <link rel="stylesheet" href="css/app.css">

        <?php
        // <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8390628961139184" 
        // crossorigin="anonymous"></script>
        // ?>

        <script async src="https://www.googletagmanager.com/gtag/js?id=G-7254H2D132"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag( 'config', '<?= @$googleClientId ?>' );
        </script>
    </head>
    <body class="<?= $cssBodyClass ?>">
        <?= $body; ?>
    </body>
    </html>

    <?php
    $pageContent = ob_get_clean();
    return $pageContent;
}

/**
 * Returns true if username has email allowed (if we can email them)
 * 
 * @param string    username: the username of the user
 * @return bool Returns the value of user.email_allowed
 */
function isEmailAllowed( string $username ): int {
    if( !$username || empty( $username ) ) {
        return 0;
    }
    
    $pdo = getDb();

    $sql = 'SELECT email_allowed
            FROM users
            WHERE username = ?';

    $stmt = $pdo->prepare( $sql );
    $stmt->execute( [$username] );
    $results = $stmt->fetch();

    return $results['email_allowed'];
}

