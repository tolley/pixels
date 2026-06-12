<?php
require_once 'jwt.php';
$user = jwtFromRequest();
if (!$user) {
    header('Location: login.php');
    exit;
}
$verified = isset($_GET['verified']);
?>
<!DOCTYPE html>
<html lang="en" class="grid">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pixel Playground</title>
    <link rel="stylesheet" href="css/app.css">
</head>
<body class="grid">

<div id="toast"><?= $verified ? 'Email verified — welcome!' : '' ?></div>

<div id="toolbar">
    <h1>Pixel Playground</h1>

    <div id="active-color-wrap" title="Custom colour">
        <div id="active-color"></div>
        <input type="color" id="color-picker">
    </div>

    <div id="swatches"></div>

    <div class="toolbar-actions">
        <button id="btn-reset">Reset</button>
    </div>

    <div id="user-info">
        <span><?= htmlspecialchars($user['username']) ?></span>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div id="workspace">
    <canvas id="canvas"></canvas>

    <div id="side">

        <div class="panel-section">
            <span class="panel-label">Zoom</span>
            <div id="zoom-controls">
                <button class="zoom-btn" data-px="2">2</button>
                <button class="zoom-btn active" data-px="4">4</button>
                <button class="zoom-btn" data-px="8">8</button>
                <button class="zoom-btn" data-px="12">12</button>
                <button class="zoom-btn" data-px="16">16</button>
            </div>
        </div>

        <div class="panel-section">
            <span class="panel-label">Visible cells</span>
            <span class="panel-value" id="vp-display">— × —</span>
        </div>

        <div class="panel-section">
            <span class="panel-label">Navigate</span>
            <div id="nav-pad">
                <button id="btn-nav-up">▲</button>
                <button id="btn-nav-left">◀</button>
                <button id="btn-nav-right">▶</button>
                <button id="btn-nav-down">▼</button>
            </div>
        </div>

        <div class="panel-section">
            <span class="panel-label">Step size</span>
            <select id="step-select">
                <option value="1">1 cell</option>
                <option value="10" selected>10 cells</option>
                <option value="50">50 cells</option>
                <option value="1000">1,000 cells</option>
                <option value="10000">10,000 cells</option>
            </select>
        </div>

        <div class="panel-section">
            <span class="panel-label">Position</span>
            <span class="panel-value" id="coords">x: 0  y: 0</span>
        </div>

    </div>
</div>

<script>
    const COLS          = 807776;
    const ROWS          = 807776;
    const GAP           = 1;
    const DEFAULT_COLOR = '#3a3a5c';
    const GAP_COLOR     = '#0f0f1a';

    let cellPx = 4;
    let step   = cellPx + GAP;
    let vpCols = 0;
    let vpRows = 0;
    let viewX  = 0;
    let viewY  = 0;

    const PALETTE = [
        '#e63946','#f4a261','#e9c46a','#2a9d8f','#264653',
        '#6a4c93','#1982c4','#8ac926','#ff595e','#ffca3a',
        '#6a994e','#a7c957','#bc6c25','#606c38','#283618',
        '#c9ada7','#9a8c98','#4a4e69','#22223b','#f2e9e4',
    ];

    let colors        = {};
    let selectedColor = PALETTE[0];

    const canvas       = document.getElementById('canvas');
    const ctx          = canvas.getContext('2d');
    const activeEl     = document.getElementById('active-color');
    const pickerEl     = document.getElementById('color-picker');
    const swatchesEl   = document.getElementById('swatches');
    const coordsEl     = document.getElementById('coords');
    const vpDisplay    = document.getElementById('vp-display');
    const stepSelect   = document.getElementById('step-select');
    const zoomControls = document.getElementById('zoom-controls');
    let selectedSwatch = null;
    let hoverC = -1, hoverR = -1;
    let rafPending = false;

    function recalc() {
        vpCols = Math.max(1, Math.floor(canvas.width  / step));
        vpRows = Math.max(1, Math.floor(canvas.height / step));
        viewX  = clamp(viewX, 0, Math.max(0, COLS - vpCols));
        viewY  = clamp(viewY, 0, Math.max(0, ROWS - vpRows));
        vpDisplay.textContent = `${vpCols} × ${vpRows}`;
    }

    function resizeCanvas() {
        canvas.width  = canvas.clientWidth;
        canvas.height = canvas.clientHeight;
        recalc();
        scheduleViewportFetch();
    }

    new ResizeObserver(resizeCanvas).observe(canvas);

    PALETTE.forEach(hex => {
        const s = document.createElement('div');
        s.className = 'swatch';
        s.style.background = hex;
        s.title = hex;
        s.addEventListener('click', () => selectColor(hex, s));
        swatchesEl.appendChild(s);
    });

    pickerEl.addEventListener('input', () => selectColor(pickerEl.value, null));

    function selectColor(hex, swatchEl) {
        selectedColor = hex;
        activeEl.style.background = hex;
        pickerEl.value = hex;
        if (selectedSwatch) selectedSwatch.classList.remove('selected');
        selectedSwatch = swatchEl;
        if (selectedSwatch) selectedSwatch.classList.add('selected');
    }

    selectColor(PALETTE[0], swatchesEl.firstElementChild);

    function render() {
        ctx.fillStyle = GAP_COLOR;
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        for (let r = 0; r < vpRows; r++) {
            for (let c = 0; c < vpCols; c++) {
                ctx.fillStyle = colors[`${viewX + c},${viewY + r}`] ?? DEFAULT_COLOR;
                ctx.fillRect(c * step, r * step, cellPx, cellPx);
            }
        }

        if (hoverC >= 0 && hoverR >= 0) {
            ctx.fillStyle = selectedColor;
            ctx.fillRect(hoverC * step, hoverR * step, cellPx, cellPx);
        }

        rafPending = false;
    }

    function scheduleRender() {
        if (rafPending) return;
        rafPending = true;
        requestAnimationFrame(render);
    }

    let fetchTimer = null;
    function scheduleViewportFetch() {
        clearTimeout(fetchTimer);
        fetchTimer = setTimeout(fetchViewport, 150);
    }

    async function fetchViewport() {
        const x1 = viewX, y1 = viewY;
        const x2 = viewX + vpCols - 1, y2 = viewY + vpRows - 1;
        try {
            const res  = await fetch(`api.php?x1=${x1}&y1=${y1}&x2=${x2}&y2=${y2}`);
            const data = await res.json();
            data.pixels.forEach(p => { colors[`${p.x},${p.y}`] = p.color; });
            render();
        } catch (err) {
            console.error('fetchViewport failed', err);
        }
    }

    async function apiSet(pixels) {
        try {
            await fetch('api.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ pixels }),
            });
        } catch (err) {
            console.error('apiSet failed', err);
        }
    }

    async function apiDelete(region) {
        const url = region
            ? `api.php?x1=${region.x1}&y1=${region.y1}&x2=${region.x2}&y2=${region.y2}`
            : 'api.php';
        try {
            await fetch(url, { method: 'DELETE' });
        } catch (err) {
            console.error('apiDelete failed', err);
        }
    }

    function canvasCell(e) {
        const rect = canvas.getBoundingClientRect();
        const c = Math.floor((e.clientX - rect.left) * (canvas.width  / rect.width)  / step);
        const r = Math.floor((e.clientY - rect.top)  * (canvas.height / rect.height) / step);
        return { c, r, col: viewX + c, row: viewY + r };
    }

    canvas.addEventListener('mousemove', e => {
        const { c, r, col, row } = canvasCell(e);
        if (c === hoverC && r === hoverR) return;
        hoverC = c;
        hoverR = r;
        coordsEl.textContent = `x: ${col.toLocaleString()}  y: ${row.toLocaleString()}`;
        scheduleRender();
    });

    canvas.addEventListener('mouseleave', () => {
        hoverC = -1; hoverR = -1;
        scheduleRender();
    });

    canvas.addEventListener('click', e => {
        const { col, row } = canvasCell(e);
        if (col < 0 || col >= COLS || row < 0 || row >= ROWS) return;
        colors[`${col},${row}`] = selectedColor;
        render();
        apiSet([{ x: col, y: row, color: selectedColor }]);
    });

    function getStep() { return parseInt(stepSelect.value, 10); }

    function navigate(dx, dy) {
        viewX = clamp(viewX + dx, 0, Math.max(0, COLS - vpCols));
        viewY = clamp(viewY + dy, 0, Math.max(0, ROWS - vpRows));
        coordsEl.textContent = `x: ${viewX.toLocaleString()}  y: ${viewY.toLocaleString()}`;
        render();
        scheduleViewportFetch();
    }

    document.getElementById('btn-nav-up').addEventListener('click',    () => navigate(0, -getStep()));
    document.getElementById('btn-nav-down').addEventListener('click',   () => navigate(0,  getStep()));
    document.getElementById('btn-nav-left').addEventListener('click',   () => navigate(-getStep(), 0));
    document.getElementById('btn-nav-right').addEventListener('click',  () => navigate( getStep(), 0));

    document.addEventListener('keydown', e => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') return;
        const s = getStep();
        switch (e.key) {
            case 'ArrowLeft':  e.preventDefault(); navigate(-s,  0); break;
            case 'ArrowRight': e.preventDefault(); navigate( s,  0); break;
            case 'ArrowUp':    e.preventDefault(); navigate( 0, -s); break;
            case 'ArrowDown':  e.preventDefault(); navigate( 0,  s); break;
        }
    });

    function applyZoom(z) {
        cellPx = z;
        step   = cellPx + GAP;
        zoomControls.querySelectorAll('.zoom-btn').forEach(btn => {
            btn.classList.toggle('active', parseInt(btn.dataset.px) === cellPx);
        });
        recalc();
        scheduleViewportFetch();
    }

    zoomControls.addEventListener('click', e => {
        const btn = e.target.closest('.zoom-btn');
        if (btn) applyZoom(parseInt(btn.dataset.px));
    });

    document.getElementById('btn-reset').addEventListener('click', () => {
        colors = {};
        apiDelete();
        render();
    });

    function clamp(v, min, max) { return Math.max(min, Math.min(max, v)); }

    // Show toast if redirected here after email verification
    const toastEl = document.getElementById('toast');
    if (toastEl.textContent) {
        toastEl.classList.add('show');
        setTimeout(() => toastEl.classList.remove('show'), 4000);
        history.replaceState(null, '', location.pathname);
    }
</script>
</body>
</html>
