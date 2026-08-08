<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Pixel Playground</title>
        <link rel="stylesheet" href="css/app.css">
        <script async src="https://www.googletagmanager.com/gtag/js?id=G-7254H2D132"></script>
        <script>
            // window.dataLayer = window.dataLayer || [];
            // function gtag(){dataLayer.push(arguments);}
            // gtag('js', new Date());
            // gtag( 'config', '<?= @$googleClientId ?>' );
        </script>
    </head>
    <body class="<?= $cssBodyClass ?>">
        <?= $this->fetch( $bodyTemplate, $bodyData ) ?>
    </body>
    </html>