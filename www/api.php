<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'db.php';

try {
    $pdo    = getDb();
    $method = $_SERVER['REQUEST_METHOD'];

    // ── GET ?x1=&y1=&x2=&y2= ─────────────────────────────────────────────────
    // Returns all stored pixels within the bounding box [x1,x2] × [y1,y2].
    if ($method === 'GET') {
        $x1 = max(0, (int)($_GET['x1'] ?? 0));
        $y1 = max(0, (int)($_GET['y1'] ?? 0));
        $x2 = max($x1, (int)($_GET['x2'] ?? $x1 + 200));
        $y2 = max($y1, (int)($_GET['y2'] ?? $y1 + 200));

        $stmt = $pdo->prepare(
            'SELECT x, y, color FROM pixels
             WHERE x BETWEEN :x1 AND :x2
               AND y BETWEEN :y1 AND :y2'
        );
        $stmt->execute([':x1' => $x1, ':x2' => $x2, ':y1' => $y1, ':y2' => $y2]);

        echo json_encode(['pixels' => $stmt->fetchAll()]);

    // ── POST  {pixels:[{x,y,color},...]} ─────────────────────────────────────
    // Upserts one or more pixels.
    } elseif ($method === 'POST') {
        $body   = json_decode(file_get_contents('php://input'), true);
        $pixels = $body['pixels'] ?? [];

        if (empty($pixels)) {
            echo json_encode(['ok' => true, 'count' => 0]);
            exit;
        }

        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO pixels (x, y, color) VALUES (:x, :y, :color)'
        );

        $saved = 0;
        $pdo->beginTransaction();
        foreach ($pixels as $p) {
            $x     = isset($p['x']) ? (int)$p['x'] : null;
            $y     = isset($p['y']) ? (int)$p['y'] : null;
            $color = isset($p['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $p['color'])
                     ? $p['color'] : null;

            if ($x === null || $y === null || $color === null) continue;

            $stmt->execute([':x' => $x, ':y' => $y, ':color' => $color]);
            if ($stmt->rowCount()) $saved++;
        }
        $pdo->commit();

        echo json_encode(['ok' => true, 'count' => $saved]);

    // ── DELETE  (no body = clear all; ?x1=&y1=&x2=&y2= = clear region) ──────
    } elseif ($method === 'DELETE') {
        if (isset($_GET['x1'])) {
            $x1 = max(0, (int)$_GET['x1']);
            $y1 = max(0, (int)$_GET['y1']);
            $x2 = max($x1, (int)$_GET['x2']);
            $y2 = max($y1, (int)$_GET['y2']);

            $stmt = $pdo->prepare(
                'DELETE FROM pixels WHERE x BETWEEN :x1 AND :x2 AND y BETWEEN :y1 AND :y2'
            );
            $stmt->execute([':x1' => $x1, ':x2' => $x2, ':y1' => $y1, ':y2' => $y2]);
        } else {
            $pdo->exec('DELETE FROM pixels');
        }

        echo json_encode(['ok' => true]);

    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
