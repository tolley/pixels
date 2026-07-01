<?php
// GET /image?x1=0&y1=0&x2=199&y2=199[&scale=1]
//
// Returns a GIF where each grid cell is one pixel (or scale×scale pixels).
// Cells with no data are transparent.

require_once 'db.php';

$x1    = max(0, (int)($_GET['x1']    ?? 0));
$y1    = max(0, (int)($_GET['y1']    ?? 0));
$x2    = max($x1, (int)($_GET['x2'] ?? $x1 + 512));
$y2    = max($y1, (int)($_GET['y2'] ?? $y1 + 512));
$scale = max(1, min(16, (int)($_GET['scale'] ?? 4)));

// Cap region to avoid generating huge images (max 2048 cells per axis)
$x2 = min($x2, $x1 + 2047);
$y2 = min($y2, $y1 + 2047);

$cellsW = $x2 - $x1 + 1;
$cellsH = $y2 - $y1 + 1;
$imgW   = $cellsW * $scale;
$imgH   = $cellsH * $scale;

try {
    $pdo  = getDb();
    $stmt = $pdo->prepare(
        'SELECT x, y, c FROM pixels
         WHERE x BETWEEN :x1 AND :x2
           AND y BETWEEN :y1 AND :y2'
    );
    $stmt->execute([':x1' => $x1, ':x2' => $x2, ':y1' => $y1, ':y2' => $y2]);
    $rows = $stmt->fetchAll();
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

// Build image — palette mode required for GIF transparency
$img = imagecreate($imgW, $imgH);

// First allocated color is the GIF background; mark it as the transparent index
$transparent = imagecolorallocate($img, 0, 0, 0);
imagecolortransparent($img, $transparent);

// Cache allocations so repeated colors don't exhaust the 256-slot palette
$colors = [];
foreach ($rows as $row) {
    $cx  = ((int)$row['x'] - $x1) * $scale;
    $cy  = ((int)$row['y'] - $y1) * $scale;
    $hex = ltrim($row['c'], '#');
    if (!isset($colors[$hex])) {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $colors[$hex] = imagecolorallocate($img, $r, $g, $b)
                     ?: imagecolorclosest($img, $r, $g, $b);
    }
    $c = $colors[$hex];

    if ($scale === 1) {
        imagesetpixel($img, $cx, $cy, $c);
    } else {
        imagefilledrectangle($img, $cx, $cy, $cx + $scale - 1, $cy + $scale - 1, $c);
    }
}

header('Content-Type: image/gif');
header('Cache-Control: no-store');
imagegif($img);
imagedestroy($img);
