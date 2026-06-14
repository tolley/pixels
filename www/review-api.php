<?php
header('Content-Type: application/json');
require_once 'db.php';
require_once 'jwt.php';

function sendPixelReviewEmail(string $to, string $username, string $status): void {
    $appUrl   = rtrim(getenv('APP_URL') ?: 'http://localhost:8080', '/');
    $imageUrl = $appUrl . '/image.php';
    $approved = $status === 'approved';
    $subject  = $approved ? 'Your pixels have been approved!' : 'Your pixels were not approved';

    $headingColor = $approved ? '#2e7d32' : '#c62828';
    $heading      = $approved ? 'Pixels Approved!' : 'Pixel Submission Update';
    $safeUser     = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $safeUrl      = htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8');

    $statusLine = $approved
        ? 'Great news &mdash; your pixel submission has been <strong>approved</strong> and your colors are now live on the canvas!'
        : 'Unfortunately your pixel submission was <strong>not approved</strong> this time. Feel free to try again!';

    $body  = '<!DOCTYPE html><html><head><meta charset="utf-8"></head>';
    $body .= '<body style="font-family:sans-serif;max-width:560px;margin:40px auto;color:#333">';
    $body .= '<h2 style="color:' . $headingColor . '">' . $heading . '</h2>';
    $body .= '<p>Hi ' . $safeUser . ',</p>';
    $body .= '<p>' . $statusLine . '</p>';
    $body .= '<p><a href="' . $safeUrl . '" style="display:inline-block;padding:10px 20px;background:#1565c0;color:#fff;border-radius:4px;text-decoration:none">View the Canvas</a></p>';
    $body .= '<p>Thank you so much for contributing and helping make this experiment look amazing &mdash; every pixel counts!</p>';
    $body .= '<p style="color:#888;font-size:0.85em">&mdash; The Pixel Playground Team</p>';
    $body .= '</body></html>';

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Pixel Playground <noreply@pixelplayground.local>\r\n";

    mail($to, $subject, $body, $headers);
}

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

        $newStatus = $action === 'approve' ? 'approved' : 'rejected';

        $pdo->prepare(
            'UPDATE pending_pixels SET status = ?, reviewed_at = NOW() WHERE batch_id = ?'
        )->execute([$newStatus, $batchId]);

        // Look up user email and username for the notification
        $userRow = $pdo->prepare(
            'SELECT u.email, u.username
             FROM users u
             JOIN pending_pixels pp ON pp.user_id = u.id
             WHERE pp.batch_id = ?
             LIMIT 1'
        );
        $userRow->execute([$batchId]);
        $recipient = $userRow->fetch();

        if ($recipient) {
            sendPixelReviewEmail($recipient['email'], $recipient['username'], $newStatus);
        }

        echo json_encode(['ok' => true]);

    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
