<?php
require_once 'jwt.php';
$user = jwtFromRequest();
if (!$user || empty($user['is_admin'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Review Queue — Pixel Playground</title>
    <link rel="stylesheet" href="css/app.css">
</head>
<body class="review">

<div id="review-header">
    <h1>Review Queue</h1>
    <nav class="review-nav">
        <a href="grid.php">Grid</a>
        <a href="logout.php">Logout</a>
    </nav>
</div>

<div id="review-body">
    <div id="review-summary"></div>
    <div id="batch-list"></div>
    <div id="empty-state" style="display:none">No pending submissions.</div>
</div>

<script>
let batches = [];

async function load() {
    const res  = await fetch('review-api.php');
    const data = await res.json();
    batches = data.batches ?? [];
    render();
}

function render() {
    const list    = document.getElementById('batch-list');
    const summary = document.getElementById('review-summary');
    const empty   = document.getElementById('empty-state');

    if (!batches.length) {
        list.innerHTML    = '';
        summary.innerHTML = '';
        empty.style.display = '';
        return;
    }

    empty.style.display = 'none';
    summary.innerHTML   = `<p class="review-count">${batches.length} pending submission${batches.length !== 1 ? 's' : ''}</p>`;
    list.innerHTML      = batches.map(renderBatch).join('');

    list.querySelectorAll('[data-action]').forEach(btn => {
        btn.addEventListener('click', () => act(btn.dataset.action, btn.dataset.batch));
    });
}

function renderBatch(b) {
    const date   = new Date(b.submitted_at).toLocaleString();
    const swatches = (b.colors || '').split(',').filter(Boolean)
        .map(c => `<span class="batch-swatch" style="background:${escHtml(c)}" title="${escHtml(c)}"></span>`)
        .join('');
    const counts = [];
    if (+b.paint_count) counts.push(`${b.paint_count} paint`);
    if (+b.erase_count) counts.push(`<span class="erase-tag">${b.erase_count} erase</span>`);

    return `
    <div class="batch-card" data-batch="${escHtml(b.batch_id)}">
        <div class="batch-meta">
            <span class="batch-user">${escHtml(b.username)}</span>
            <span class="batch-time">${escHtml(date)}</span>
            <span class="batch-px">${counts.join(' &amp; ')}</span>
            <span class="batch-coords">around (${Number(b.sample_x).toLocaleString()}, ${Number(b.sample_y).toLocaleString()})</span>
        </div>
        ${swatches ? `<div class="batch-swatches">${swatches}</div>` : ''}
        <div class="batch-actions">
            <button class="btn-approve" data-action="approve" data-batch="${escHtml(b.batch_id)}">Approve</button>
            <button class="btn-reject"  data-action="reject"  data-batch="${escHtml(b.batch_id)}">Reject</button>
        </div>
    </div>`;
}

async function act(action, batchId) {
    const card = document.querySelector(`.batch-card[data-batch="${batchId}"]`);
    if (card) {
        card.querySelectorAll('button').forEach(b => b.disabled = true);
        card.style.opacity = '0.5';
    }

    try {
        const res  = await fetch('review-api.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ action, batch_id: batchId }),
        });
        const data = await res.json();
        if (!data.ok) throw new Error(data.error ?? 'Failed');

        batches = batches.filter(b => b.batch_id !== batchId);
        render();
    } catch (err) {
        if (card) {
            card.querySelectorAll('button').forEach(b => b.disabled = false);
            card.style.opacity = '';
        }
        alert('Error: ' + err.message);
    }
}

function escHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    })[c]);
}

load();
</script>
</body>
</html>
