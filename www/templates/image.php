<link rel="stylesheet" type="text/css" href="/css/app.css" media="screen, projection">

<div class="card master-image" style="border: solid 1px #F00; width: 100%;">
    <p class="card-title">Pixel Playground</p>
    <h1>Current Image</h1>

    <?php
    if( ! $user ) {
        echo 'Create a <a href="/signup">free account</a> and start creating some pixel art of your own!<br />';
        echo 'Already have an account, login and get to work before all the good pixels are placed!<br />';
    } else {
        echo 'Return to <a href="/grid" class="header_link">the grid</a>';
    }
    ?>

    <div id="image_wrapper">
        <div id="image_background" style="height: <?= $imgH ?>px; width: <?= $imgW?>px;">
            <div id="image" style="background-image: url( '<?= $imgUrl ?>' ); 
                height: <?= $imgH ?>px; width: <?= $imgW?>px;">
            </div>
        </div>
    </div>

    <div id="msg" class="msg" style="display:none"></div>
</div>