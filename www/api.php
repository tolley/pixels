<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'db.php';
require_once 'jwt.php';

try {
    $pdo    = getDb();
    $method = $_SERVER['REQUEST_METHOD'];

    switch( $method ) {
        case 'GET':
            $x1 = max(0, (int)($_GET['x1'] ?? 0));
            $y1 = max(0, (int)($_GET['y1'] ?? 0));
            $x2 = max($x1, (int)($_GET['x2'] ?? $x1 + 200));
            $y2 = max($y1, (int)($_GET['y2'] ?? $y1 + 200));

            $stmt = $pdo->prepare(
                'SELECT x, y, c FROM pixels
                WHERE x BETWEEN :x1 AND :x2
                AND y BETWEEN :y1 AND :y2'
            );
            $stmt->execute([':x1' => $x1, ':x2' => $x2, ':y1' => $y1, ':y2' => $y2]);

            echo json_encode(['pixels' => $stmt->fetchAll()]);
            break;
        
        case 'POST':
            $authUser = jwtFromRequest();
            if (!$authUser) {
                http_response_code(401);
                echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
                exit;
            }

            $body   = json_decode(file_get_contents('php://input'), true);
            $pixels = $body['pixels'] ?? [];

            if (empty($pixels)) {
                echo json_encode(['ok' => true, 'count' => 0]);
                exit;
            }

            $batchId = bin2hex(random_bytes(16));
            $valid   = [];
            foreach ($pixels as $p) {
                $x = isset($p['x']) ? (int)$p['x'] : null;
                $y = isset($p['y']) ? (int)$p['y'] : null;
                if ($x === null || $y === null) continue;

                $color = null;
                if (!array_key_exists('c', $p) || $p['c'] === null) {
                    $color = null; // erase
                } elseif (preg_match('/^#[0-9a-fA-F]{6}$/', $p['c'])) {
                    $color = $p['c'];
                } else {
                    continue; // invalid color string
                }

                $valid[] = [$batchId, $x, $y, $color, (int)$authUser['sub'], $authUser['username']];
            }

            if (empty($valid)) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'No valid pixels in request']);
                exit;
            }

            $placeholders = implode(', ', array_fill(0, count($valid), '(?, ?, ?, ?, ?, ?)'));
            $pdo->prepare(
                "INSERT INTO pending_pixels (batch_id, x, y, c, user_id, username) VALUES $placeholders"
            )->execute(array_merge(...$valid));

            echo json_encode(['ok' => true, 'count' => count($valid)]);
            break;

        case 'DELETE':
            $authUser = jwtFromRequest();
            if (!$authUser || empty($authUser['is_admin'])) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'error' => 'Forbidden']);
                exit;
            }

            if (isset($_GET['x1'])) {
                $x1 = max(0, (int)$_GET['x1']);
                $y1 = max(0, (int)$_GET['y1']);
                $x2 = max($x1, (int)$_GET['x2']);
                $y2 = max($y1, (int)$_GET['y2']);

                $pdo->prepare(
                    'DELETE FROM pixels WHERE x BETWEEN :x1 AND :x2 AND y BETWEEN :y1 AND :y2'
                )->execute([':x1' => $x1, ':x2' => $x2, ':y1' => $y1, ':y2' => $y2]);
            } else {
                $pdo->exec('DELETE FROM pixels');
            }

            echo json_encode(['ok' => true]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
