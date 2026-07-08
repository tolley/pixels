window.addEventListener( 'load', () => {
    const COLS          = 807776;
    const ROWS          = 807776;
    const GAP           = 1;
    const DEFAULT_COLOR = '#3a3a5c';
    const GAP_COLOR     = '#0f0f1a';
    const MAX_VP        = 1024;

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
        '#c9ada7','#9a8c98','#4a4e69','#9494c4','#f2e9e4',
    ];

    const ERASE = '__erase__';

    let colors  = {};
    let pending = {};
    let selectedColor = PALETTE[0];

    const canvas       = document.getElementById('canvas');
    const ctx          = canvas.getContext('2d');
    const activeEl     = document.getElementById('active-color');
    const pickerEl     = document.getElementById('color-picker');
    const coordsEl     = document.getElementById('coords');
    const vpDisplay    = document.getElementById('vp-display');
    const stepSelect   = document.getElementById('step-select');
    const zoomDisplay  = document.getElementById('zoom-display');
    const emailAllowed = document.getElementById('toggle-email-allowed');
    let selectedSwatch = null;
    let hoverC = -1, hoverR = -1;
    let rafPending = false;

    emailAllowed.addEventListener("click", function() {
        fetch( '/toggle-email-allowed' ).then( async ( resp ) => {
            const emailAvail = await resp.text();
            if( emailAvail == 0 )
                emailAllowed.style.color = '#F22';
            else {
                emailAllowed.style.color = '#2F2';
            }
        });
    });

    function recalc() {
        vpCols = Math.max(1, Math.floor(canvas.width  / step));
        vpRows = Math.max(1, Math.floor(canvas.height / step));
        viewX  = clamp(viewX, 0, Math.max(0, COLS - vpCols));
        viewY  = clamp(viewY, 0, Math.max(0, ROWS - vpRows));

        // If viewX or viewY are out of range, put the visible space
        // back into range
        if( viewX > MAX_VP ) {
            let diff = viewX - MAX_VP;
            viewX = viewX - diff;
        }

        if( viewY > MAX_VP ) {
            let diff = viewY - MAX_VP;
            viewY = viewY - diff;
        }

        vpDisplay.textContent = `${vpCols} × ${vpRows}`;
    }

    function resizeCanvas() {
        canvas.width  = canvas.clientWidth;
        canvas.height = canvas.clientHeight;
        recalc();
        scheduleViewportFetch();
    }

    new ResizeObserver(resizeCanvas).observe(canvas);

    pickerEl.addEventListener('input', () => selectColor(pickerEl.value, null));
    pickerEl.addEventListener('click', () => selectColor(pickerEl.value, null));

    const eraserBtn = document.getElementById('btn-eraser');

    function selectColor(hex, swatchEl) {
        selectedColor = hex;
        activeEl.style.background = hex;
        pickerEl.value = hex;
        if (selectedSwatch) selectedSwatch.classList.remove('selected');
        selectedSwatch = swatchEl;
        if (selectedSwatch) selectedSwatch.classList.add('selected');
        eraserBtn.classList.remove('selected');
    }

    eraserBtn.addEventListener('click', () => {
        selectedColor = ERASE;
        if( selectedSwatch )
            selectedSwatch.classList.remove( 'selected' );

        selectedSwatch = null;
        eraserBtn.classList.add('selected');
        gtag('event', 'tool_select', { tool: 'eraser' });
    });

    selectColor( PALETTE[0] );

    const submitBtn = document.getElementById('btn-submit');
    const resetBtn  = document.getElementById('btn-reset');

    function updateButtons() {
        const n = Object.keys(pending).length;
        submitBtn.disabled    = n === 0;
        submitBtn.textContent = n > 0 ? `Submit (${n})` : 'Submit';
        resetBtn.disabled     = n === 0;
    }

    function render() {
        ctx.fillStyle = GAP_COLOR;
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        for( let r = 0; r < vpRows; r++ ) {
            for( let c = 0; c < vpCols; c++ ) {
                ctx.fillStyle = colors[`${viewX + c},${viewY + r}`] ?? DEFAULT_COLOR;
                ctx.fillRect(c * step, r * step, cellPx, cellPx);
            } // End for c
        }// End for r

        // mark unsaved pixels with a small white dot
        ctx.fillStyle = 'rgba(255,255,255,0.7)';
        const dot = Math.max(2, Math.floor(cellPx / 4));
        for( const key of Object.keys( pending ) ) {
            const [px, py] = key.split( ',' );
            const sc = px - viewX, sr = py - viewY;
            if( sc >= 0 && sc < vpCols && sr >= 0 && sr < vpRows ) {
                ctx.fillRect(sc * step, sr * step, dot, dot);
            }
        }

        if( hoverC >= 0 && hoverR >= 0 ) {
            ctx.fillStyle = selectedColor === ERASE ? DEFAULT_COLOR : selectedColor;
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

    const gridLoadingEl = document.getElementById('grid-loading');

    async function fetchViewport() {
        gridLoadingEl.classList.add('show');

        // Tolley
        // Keep the pixels in range
        const x1 = viewX, y1 = viewY;
        const x2 = viewX + vpCols - 1, y2 = viewY + vpRows - 1;

        // console.log( 'fetViewport: x1 = ', x1, 'x2 = ', x2, 'y1 = ', y1, 'y2 = ' , y2);
        
        try {
            const res  = await fetch(`api.php?x1=${x1}&y1=${y1}&x2=${x2}&y2=${y2}`);
            const data = await res.json();
            data.pixels.forEach(p => { colors[`${p.x},${p.y}`] = p.c; });
            render();
        } catch (err) {
            console.error('fetchViewport failed', err);
        } finally {
            setTimeout( () => {
                gridLoadingEl.classList.remove('show');
            }, 100 );
        }
    }

    async function apiSet(pixels) {
        const res  = await fetch('api.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ pixels }),
        });
        const data = await res.json();
        if (!data.ok) throw new Error(data.error ?? 'Save failed');
        return data;
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

    let pixelClickCount = 0;
    canvas.addEventListener('click', e => {
        const { col, row } = canvasCell(e);
        if (col < 0 || col >= COLS || row < 0 || row >= ROWS) return;
        const key = `${col},${row}`;
        if (selectedColor === ERASE) {
            delete colors[key];
            pending[key] = ERASE;
        } else {
            colors[key]  = selectedColor;
            pending[key] = selectedColor;
        }
        pixelClickCount++;
        if (pixelClickCount === 1 || pixelClickCount % 10 === 0) {
            gtag('event', 'pixel_paint', {
                tool:         selectedColor === ERASE ? 'eraser' : 'brush',
                color:        selectedColor === ERASE ? null : selectedColor,
                pixel_x:      col,
                pixel_y:      row,
                total_painted: pixelClickCount,
            });
        }
        updateButtons();
        render();
    });

    function getStep() { return parseInt(stepSelect.value, 10); }

    function navigate(dx, dy) {
        viewX = clamp( viewX + dx, 0, Math.max( 0, COLS - vpCols ) );
        viewY = clamp( viewY + dy, 0, Math.max( 0, ROWS - vpRows ) );

        // If viewX or viewY are out of range, put the visible space
        // back into range
        if( viewX > MAX_VP ) {
            let diff = viewX - MAX_VP;
            viewX = viewX - diff;
        }

        if( viewY > MAX_VP ) {
            let diff = viewY - MAX_VP;
            viewY = viewY - diff;
        }

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

    const MIN_ZOOM = 2;
    const MAX_ZOOM = 16;

    function applyZoom(z) {
        const prev = cellPx;
        cellPx = clamp(z, MIN_ZOOM, MAX_ZOOM);
        step   = cellPx + GAP;
        zoomDisplay.textContent = cellPx + ' px';
        recalc();
        scheduleViewportFetch();
        if (cellPx !== prev) gtag('event', 'zoom_change', { zoom_px: cellPx });
    }

    document.getElementById('btn-zoom-out').addEventListener('click', () => applyZoom(cellPx - 2));
    document.getElementById('btn-zoom-in').addEventListener('click',  () => applyZoom(cellPx + 2));

    submitBtn.addEventListener('click', async () => {
        if (!Object.keys(pending).length) return;
        const pixels = Object.entries(pending).map(([key, color]) => {
            const [x, y] = key.split(',').map(Number);
            return { x, y, c: color === ERASE ? null : color };
        });
        const pixelCount = pixels.length;
        submitBtn.disabled    = true;
        submitBtn.textContent = 'Saving…';
        try {
            await apiSet(pixels);
            pending = {};
            updateButtons();
            showToast('Submitted for review');
            render();
            gtag('event', 'pixels_submit', { pixel_count: pixelCount });
        } catch (err) {
            console.error('submit failed', err);
            submitBtn.disabled    = false;
            submitBtn.textContent = 'Save failed — retry';
            setTimeout(() => updateButtons(), 3000);
            gtag('event', 'submit_error', { pixel_count: pixelCount });
        }
    });

    resetBtn.addEventListener('click', () => {
        const discarded = Object.keys(pending).length;
        for (const key of Object.keys(pending)) delete colors[key];
        pending = {};
        updateButtons();
        render();
        scheduleViewportFetch();
        gtag('event', 'pixels_reset', { pixel_count: discarded });
    });

    function clamp(v, min, max) { return Math.max(min, Math.min(max, v)); }

    const toastEl = document.getElementById('toast');
    let toastTimer = null;
    function showToast(msg) {
        toastEl.textContent = msg;
        toastEl.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toastEl.classList.remove('show'), 3000);
    }

    // Show toast if redirected here after email verification
    if (toastEl.textContent) {
        showToast(toastEl.textContent);
        history.replaceState(null, '', location.pathname);
    }
} );