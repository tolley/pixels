<?php

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
        <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8390628961139184"
        crossorigin="anonymous"></script>

        <script async src="https://www.googletagmanager.com/gtag/js?id=G-7254H2D132"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag( 'config', '<?= $googleClientId ?>' );
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
