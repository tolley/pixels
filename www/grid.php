<?php
require_once( 'functions.php' );
require_once( 'jwt.php' );

$user = jwtFromRequest();
if( ! $user ) {
    header('Location: login');
    exit;
}
$verified = isset($_GET['verified']);

$hasGoogle = array_key_exists( 'GOOGLE_CLIENT_ID', $_ENV ) && strlen( $_ENV['GOOGLE_CLIENT_ID'] ) > 0;
$hasGoogle = false;
$hasApple = array_key_exists( 'APPLE_CLIENT_ID', $_ENV ) && strlen( $_ENV['APPLE_CLIENT_ID'] ) > 0;
$hasSocial = $hasGoogle || $hasApple;

ob_start();
?>
<div id="toast"><?= $verified ? 'Email verified — welcome!' : '' ?></div>

<div id="toolbar">
    <h1>Pixels</h1>

    <div id="active-color-wrap" title="Select a color">
        <div id="active-color"></div>
        <input type="color" id="color-picker">
    </div>

    <button type="button" id="btn-eraser" title="Erase pixel (remove colour)">✕</button>

    <div class="toolbar-actions">
        <button type="button" id="btn-submit" title="Submit pixels for review for inclusion" disabled>Submit</button>
        <button type="button" id="btn-reset" title="Clear non submitted pixels" disabled>Reset</button>
    </div>

    <div id="user-info">
        <span><?= htmlspecialchars($user['username']) ?></span>
        <a id="toggle-email-allowed" title="Enable/Disable emails">@</a>
        <a href="/image" title="See the GIF version of this grid." target="_blank">GIF</a>

        <?php if (!empty($user['is_admin'])): ?>
            <a href="review" title="Review Submissions">Review</a>
        <?php endif; ?>
        
        <a href="logout" title="Logout">Logout</a>
    </div>
</div>

<div id="workspace">
    <div id="grid-loading">Loading…</div>
    <canvas id="canvas"></canvas>

    <div id="side">
        <div class="panel-section">
            <span class="panel-label">Zoom</span>
            <div id="zoom-controls">
                <button type="button" id="btn-zoom-out">−</button>
                <span class="panel-value" id="zoom-display">4 px</span>
                <button type="button" id="btn-zoom-in">+</button>
            </div>
            <br />

            <span class="panel-label">Step size</span>
            <select id="step-select">
                <option value="1">1 cell</option>
                <option value="5">5 cells</option>
                <option value="10">10 cells</option>
                <option value="25" selected>25 cells</option>
                <option value="50">50 cells</option>
                <option value="100">100 cells</option>
            </select>
        </div>

        <div class="panel-section">
            <span class="panel-label">Navigate</span>
            <div id="nav-pad">
                <button type="button" id="btn-nav-up">▲</button>
                <button type="button" id="btn-nav-left">◀</button>
                <button type="button" id="btn-nav-right">▶</button>
                <button type="button" id="btn-nav-down">▼</button>
            </div>
        </div>

        <div class="panel-section">
            <span class="panel-label">
                Position
            </span>
            <span class="panel-value" id="coords">x: 0  y: 0</span>
            <br />

            <span class="panel-label">Visible cells</span>
            <span class="panel-value" id="vp-display">— × —</span>
        </div>

        <?php
        /*
        <div id="ad-slot">
            <ins class="adsbygoogle"
                 style="display:block"
                 data-ad-client="ca-pub-483307165"
                 data-ad-slot="auto"
                 data-ad-format="auto"
                 data-full-width-responsive="true"></ins>
        </div>
        */
        ?>
    </div>
</div>
<script type="text/javascript" src="js/grid.js"></script>

<?php
$pageBody = ob_get_clean();
$pageContent = renderPage( $pageBody, 'grid');

echo $pageContent;
