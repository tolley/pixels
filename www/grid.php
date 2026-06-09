<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pixel Playground</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            height: 100%;
        }

        body {
            background: #1a1a2e;
            display: flex;
            flex-direction: column;
            height: 100vh;
            font-family: system-ui, sans-serif;
            color: #eee;
            user-select: none;
            overflow: hidden;
        }

        /* ── toolbar ── */
        #toolbar {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 14px;
            background: #16213e;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            flex-wrap: wrap;
            flex-shrink: 0;
        }

        #toolbar h1 {
            font-size: 0.95rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            opacity: 0.45;
            flex-shrink: 0;
        }

        #active-color-wrap {
            position: relative;
            flex-shrink: 0;
        }

        #active-color {
            width: 32px;
            height: 32px;
            border-radius: 5px;
            border: 2px solid rgba(255,255,255,0.5);
            cursor: pointer;
        }

        #color-picker {
            position: absolute;
            inset: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
            padding: 0;
            border: none;
        }

        #swatches {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            flex: 1;
            min-width: 0;
        }

        .swatch {
            width: 22px;
            height: 22px;
            border-radius: 3px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: transform 0.1s, border-color 0.1s;
            flex-shrink: 0;
        }

        .swatch:hover    { transform: scale(1.2); }
        .swatch.selected { border-color: #fff; transform: scale(1.15); }

        .toolbar-actions { display: flex; gap: 8px; flex-shrink: 0; }

        button {
            background: rgba(255,255,255,0.08);
            color: #eee;
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 6px;
            padding: 6px 14px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: background 0.15s;
        }

        button:hover { background: rgba(255,255,255,0.18); }

        /* ── workspace ── */
        #workspace {
            display: flex;
            flex: 1;
            min-height: 0;
        }

        /* ── canvas ── */
        #canvas {
            display: block;
            cursor: pointer;
            flex: 1;
            min-width: 0;
            min-height: 0;
        }

        /* ── side panel ── */
        #side {
            width: 160px;
            flex-shrink: 0;
            background: #16213e;
            border-left: 1px solid rgba(255,255,255,0.08);
            display: flex;
            flex-direction: column;
            gap: 16px;
            padding: 16px 12px;
            overflow-y: auto;
        }

        .panel-section {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .panel-label {
            font-size: 0.7rem;
            opacity: 0.45;
            text-transform: uppercase;
            letter-spacing: 0.07em;
        }

        .panel-value {
            font-size: 0.8rem;
            font-variant-numeric: tabular-nums;
        }

        #zoom-controls {
            display: flex;
            gap: 4px;
        }

        .zoom-btn {
            flex: 1;
            padding: 4px 0;
            font-size: 0.75rem;
        }

        .zoom-btn.active {
            background: #1982c4;
            border-color: #1982c4;
        }

        #nav-pad {
            display: grid;
            grid-template-areas:
                ".    up   ."
                "left .    right"
                ".    down .";
            grid-template-columns: repeat(3, 1fr);
            grid-template-rows:    repeat(3, 36px);
            gap: 4px;
        }

        #nav-pad button {
            padding: 0;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #btn-nav-up    { grid-area: up; }
        #btn-nav-left  { grid-area: left; }
        #btn-nav-right { grid-area: right; }
        #btn-nav-down  { grid-area: down; }

        select {
            background: rgba(255,255,255,0.08);
            color: #eee;
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 6px;
            padding: 5px 6px;
            font-size: 0.8rem;
            cursor: pointer;
            width: 100%;
        }
    </style>
</head>
<body>

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

    let colors        = {};   // in-memory cache: "x,y" → hex
    let selectedColor = PALETTE[0];

    const canvas      = document.getElementById('canvas');
    const ctx         = canvas.getContext('2d');
    const activeEl    = document.getElementById('active-color');
    const pickerEl    = document.getElementById('color-picker');
    const swatchesEl  = document.getElementById('swatches');
    const coordsEl    = document.getElementById('coords');
    const vpDisplay   = document.getElementById('vp-display');
    const stepSelect  = document.getElementById('step-select');
    const zoomControls = document.getElementById('zoom-controls');
    let selectedSwatch = null;
    let hoverC = -1, hoverR = -1;
    let rafPending = false;

    // ── recalculate viewport from canvas size + zoom ──
    function recalc() {
        vpCols = Math.max(1, Math.floor(canvas.width  / step));
        vpRows = Math.max(1, Math.floor(canvas.height / step));
        viewX  = clamp(viewX, 0, Math.max(0, COLS - vpCols));
        viewY  = clamp(viewY, 0, Math.max(0, ROWS - vpRows));
        vpDisplay.textContent = `${vpCols} × ${vpRows}`;
    }

    // ── resize canvas buffer to match its CSS size, then recalc ──
    function resizeCanvas() {
        canvas.width  = canvas.clientWidth;
        canvas.height = canvas.clientHeight;
        recalc();
        scheduleViewportFetch();
    }

    new ResizeObserver(resizeCanvas).observe(canvas);

    // ── palette ──
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

    // ── render ──
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

    // ── API helpers ──
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

    // ── canvas cell from mouse event ──
    function canvasCell(e) {
        const rect = canvas.getBoundingClientRect();
        const c = Math.floor((e.clientX - rect.left) * (canvas.width  / rect.width)  / step);
        const r = Math.floor((e.clientY - rect.top)  * (canvas.height / rect.height) / step);
        return { c, r, col: viewX + c, row: viewY + r };
    }

    // ── hover highlight ──
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

    // ── click to paint ──
    canvas.addEventListener('click', e => {
        const { col, row } = canvasCell(e);
        if (col < 0 || col >= COLS || row < 0 || row >= ROWS) return;
        colors[`${col},${row}`] = selectedColor;
        render();
        apiSet([{ x: col, y: row, color: selectedColor }]);
    });

    // ── navigation ──
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

    // ── zoom ──
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

    // ── toolbar buttons ──
    document.getElementById('btn-reset').addEventListener('click', () => {
        colors = {};
        apiDelete();   // clear all pixels on server
        render();
    });

    function clamp(v, min, max) { return Math.max(min, Math.min(max, v)); }
</script>
</body>
</html>
