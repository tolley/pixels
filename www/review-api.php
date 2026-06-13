<?php
header('Content-Type: application/json');
require_once 'db.php';
require_once 'jwt.php';

$authUser = jwtFromRequest();
if (!$authUser || empty($authUser['is_admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

try {
    $pdo    = getDb();
    $method = $_SERVER['REQUEST_METHOD'];

    // ── GET — list pending batches ─────────────────────────────────────────────
    if ($method === 'GET') {
        $stmt = $pdo->query(
            "SELECT
                batch_id,
                username,
                MIN(submitted_at) AS submitted_at,
                MIN(x) AS sample_x,
                MIN(y) AS sample_y,
                COUNT(*) AS total,
                SUM(c IS NOT NULL) AS paint_count,
                SUM(c IS NULL)     AS erase_count,
                GROUP_CONCAT(DISTINCT c ORDER BY c SEPARATOR ',') AS colors
             FROM pending_pixels
             WHERE status = 'pending'
             GROUP BY batch_id, username
             ORDER BY MIN(submitted_at) ASC
             LIMIT 200"
        );
        echo json_encode(['batches' => $stmt->fetchAll()]);

    // ── POST — approve or reject a batch ──────────────────────────────────────
    } elseif ($method === 'POST') {
        $body    = json_decode(file_get_contents('php://input'), true) ?? [];
        $action  = $body['action']   ?? '';
        $batchId = $body['batch_id'] ?? '';

        if (!$batchId || !in_array($action, ['approve', 'reject'], true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request']);
            exit;
        }

        if ($action === 'approve') {
            $stmt = $pdo->prepare(
                'SELECT x, y, c FROM pending_pixels WHERE batch_id = ? AND status = "pending"'
            );
            $stmt->execute([$batchId]);
            $pixels = $stmt->fetchAll();

            $paint = array_values(array_filter($pixels, fn($p) => $p['c'] !== null));
            $erase = array_values(array_filter($pixels, fn($p) => $p['c'] === null));

            if ($paint) {
                $ph   = implode(', ', array_fill(0, count($paint), '(?, ?, ?)'));
                $args = [];
                foreach ($paint as $p) array_push($args, $p['x'], $p['y'], $p['c']);
                $pdo->prepare(
                    "INSERT INTO pixels (x, y, c) VALUES $ph
                     ON DUPLICATE KEY UPDATE c = VALUES(c), create_date = create_date"
                )->execute($args);
            }

            foreach ($erase as $p) {
                $pdo->prepare('DELETE FROM pixels WHERE x = ? AND y = ?')
                    ->execute([$p['x'], $p['y']]);
            }
        }

        $pdo->prepare(
            'UPDATE pending_pixels SET status = ?, reviewed_at = NOW() WHERE batch_id = ?'
        )->execute([$action === 'approve' ? 'approved' : 'rejected', $batchId]);

        echo json_encode(['ok' => true]);

    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
