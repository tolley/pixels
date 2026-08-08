<link rel="stylesheet" type="text/css" href="/css/app.css" media="screen, projection">

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
        <span><?= htmlspecialchars( $user['username'] ) ?></span>
        <a id="toggle-email-allowed" title="Enable/Disable emails">@</a>
        <a href="/image?x1=0&y1=0&x2=16&y2=16&scale=2" title="See the GIF version of this grid." target="_blank">GIF</a>

        <?php if( !empty( $user['is_admin'] ) ): ?>
            <a href="review" title="Review Submissions">Review</a>
        <?php endif; ?>
        
        <a href="/logout" title="Logout">Logout</a>
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
<script type="text/javascript" src="/js/grid.js"></script>