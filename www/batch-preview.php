<?php
// GET /batch-preview.php?batch_id=<id>
// Returns a PNG showing pending pixels for a batch with existing pixels dimmed behind them.
// Admin-only.

require_once 'db.php';
require_once 'jwt.php';

$authUser = jwtFromRequest();
if (!$authUser || empty($authUser['is_admin'])) {
    http_response_code(403);
    exit;
}

$batchId = $_GET['batch_id'] ?? '';
if (!$batchId) {
    http_response_code(400);
    exit;
}

$scale    = 4;
$padding  = 5;
$maxCells = 60;

try {
    $pdo  = getDb();
    $stmt = $pdo->prepare(
        'SELECT x, y, c FROM pending_pixels WHERE batch_id = ? AND status = "pending"'
    );
    $stmt->execute([$batchId]);
    $pending = $stmt->fetchAll();

    if (!$pending) {
        http_response_code(404);
        exit;
    }

    $minX = PHP_INT_MAX; $minY = PHP_INT_MAX;
    $maxX = PHP_INT_MIN; $maxY = PHP_INT_MIN;
    foreach ($pending as $p) {
        $minX = min($minX, (int)$p['x']);
        $minY = min($minY, (int)$p['y']);
        $maxX = max($maxX, (int)$p['x']);
        $maxY = max($maxY, (int)$p['y']);
    }

    $x1 = max(0, $minX - $padding);
    $y1 = max(0, $minY - $padding);
    $x2 = $maxX + $padding;
    $y2 = $maxY + $padding;

    // Centre and cap the viewport if the batch spans too many cells
    if ($x2 - $x1 + 1 > $maxCells) {
        $mid = intdiv($minX + $maxX, 2);
        $x1  = max(0, $mid - intdiv($maxCells, 2));
        $x2  = $x1 + $maxCells - 1;
    }
    if ($y2 - $y1 + 1 > $maxCells) {
        $mid = intdiv($minY + $maxY, 2);
        $y1  = max(0, $mid - intdiv($maxCells, 2));
        $y2  = $y1 + $maxCells - 1;
    }

    $bgStmt = $pdo->prepare(
        'SELECT x, y, c FROM pixels
         WHERE x BETWEEN :x1 AND :x2 AND y BETWEEN :y1 AND :y2'
    );
    $bgStmt->execute([':x1' => $x1, ':x2' => $x2, ':y1' => $y1, ':y2' => $y2]);
    $background = $bgStmt->fetchAll();

} catch (Exception $e) {
    http_response_code(500);
    exit;
}

$cellsW = $x2 - $x1 + 1;
$cellsH = $y2 - $y1 + 1;
$imgW   = $cellsW * $scale;
$imgH   = $cellsH * $scale;

$img     = imagecreatetruecolor($imgW, $imgH);
$bgColor = imagecolorallocate($img, 26, 26, 46); // #1a1a2e
imagefill($img, 0, 0, $bgColor);

// Existing pixels — dimmed to 45 % brightness so pending pixels stand out
foreach ($background as $row) {
    $cx  = ((int)$row['x'] - $x1) * $scale;
    $cy  = ((int)$row['y'] - $y1) * $scale;
    $hex = ltrim($row['c'], '#');
    $r   = (int)(hexdec(substr($hex, 0, 2)) * 0.45);
    $g   = (int)(hexdec(substr($hex, 2, 2)) * 0.45);
    $b   = (int)(hexdec(substr($hex, 4, 2)) * 0.45);
    $c   = imagecolorallocate($img, $r, $g, $b);
    imagefilledrectangle($img, $cx, $cy, $cx + $scale - 1, $cy + $scale - 1, $c);
}

// Pending pixels — full colour with a white outline; erases drawn in red
foreach ($pending as $p) {
    $px = (int)$p['x'];
    $py = (int)$p['y'];
    if ($px < $x1 || $px > $x2 || $py < $y1 || $py > $y2) continue;

    $cx = ($px - $x1) * $scale;
    $cy = ($py - $y1) * $scale;

    if ($p['c'] === null) {
        $c = imagecolorallocate($img, 230, 57, 70); // red = erase
    } else {
        $hex = ltrim($p['c'], '#');
        $r   = hexdec(substr($hex, 0, 2));
        $g   = hexdec(substr($hex, 2, 2));
        $b   = hexdec(substr($hex, 4, 2));
        $c   = imagecolorallocate($img, $r, $g, $b);
    }

    imagefilledrectangle($img, $cx, $cy, $cx + $scale - 1, $cy + $scale - 1, $c);

    if ($scale >= 2) {
        $outline = imagecolorallocate($img, 255, 255, 255);
        imagerectangle($img, $cx, $cy, $cx + $scale - 1, $cy + $scale - 1, $outline);
    }
}

header('Content-Type: image/png');
header('Cache-Control: no-store');
imagepng($img);
imagedestroy($img);
