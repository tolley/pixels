<?php
require_once( 'functions.php' );

ob_start();
?>
<div class="card master-image">
    <p class="card-title">Pixel Playground</p>
    <h1>Current Image</h1>

    <div style="background-color: #FFF;">
        <img src="/image-api" height="600" width="600" />
    </div>

    <div id="msg" class="msg" style="display:none"></div>
</div>

<?php

$pageBody = ob_get_clean();
$pageContent = renderPage( $pageBody, 'auth' );

echo $pageContent;
