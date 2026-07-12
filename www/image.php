<?php

require_once( 'functions.php' );

$x1 = ( array_key_exists( 'x1', $_GET ) )? (int)$_GET['x1']: 0;
$x2 = ( array_key_exists( 'x2', $_GET ) )? (int)$_GET['x2']: 500;
$y1 = ( array_key_exists( 'y1', $_GET ) )? (int)$_GET['y1']: 0;
$y2 = ( array_key_exists( 'y2', $_GET ) )? (int)$_GET['y2']: 500;
$scale = ( array_key_exists( 'scale', $_GET ) )? (int)$_GET['scale']: 4;

$imgUrl = "/image-api?x1=$x1&x2=$x2&y1=$y1&y2=$y2&scale=$scale";

$cellsW = $x2 - $x1 + 1;
$cellsH = $y2 - $y1 + 1;
$imgW   = $cellsW * $scale;
$imgH   = $cellsH * $scale;

ob_start();
?>
<div class="card master-image">
    <p class="card-title">Pixel Playground</p>
    <h1>Current Image</h1>

    <div id="image_wrapper">
        <div id="image_background" style="height: <?= $imgH ?>px; width: <?= $imgW?>px;">
            <div id="image" style="background-image: url( '<?= $imgUrl ?>' ); 
                height: <?= $imgH ?>px; width: <?= $imgW?>px;">
            </div>
        </div>
    </div>

    <div id="msg" class="msg" style="display:none"></div>
</div>

<?php

$pageBody = ob_get_clean();
$pageContent = renderPage( $pageBody, 'auth' );

echo $pageContent;
