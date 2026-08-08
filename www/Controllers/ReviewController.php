<?php namespace pixels\Controller;
/**
 * A controller to handle routes related to reviewing pixels
 */
use Slim\Views\PhpRenderer;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use pixels\functions;

class ReviewController {
    private $container;

    public function __construct( ContainerInterface $container ) {
        $this->container = $container;
    }

    public function review( Request $req, Response $resp, array $args ): Response {
        $renderer = new PhpRenderer( './templates' );
        return $renderer->render( $resp, 'review.php' );
    }

    public function get( Request $req, Response $resp, array $args ): Response {
        $pdo = $this->container->get( 'pdo' );
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

        $respBody = json_encode( [ 'batches' => $stmt->fetchAll() ] );

        $resp->getBody()->write( $respBody );
        return $resp;
    }
    
    public function post( Request $req, Response $resp, array $args ): Response {
        $data = $req->getParsedBody();
        $action  = $data['action']   ?? '';
        $batchId = $data['batch_id'] ?? '';

        if( !$batchId || !in_array( $action, ['approve', 'reject'], true ) ) {
            $resp->withStatus( 400 );
            $resp->getBody()->write( json_encode( [ 'error' => 'Invalid request' ] ) );
            return $resp;
        }

        $pdo = $this->container->get( 'pdo' );
        if( $action === 'approve' ) {
            $stmt = $pdo->prepare(
                'SELECT x, y, c FROM pending_pixels WHERE batch_id = ? AND status = "pending"'
            );
            $stmt->execute( [$batchId] );
            $pixels = $stmt->fetchAll();

            $paint = array_values( array_filter( $pixels, fn($p) => $p['c'] !== null ) );
            $erase = array_values( array_filter( $pixels, fn($p) => $p['c'] === null ) );

            if ($paint) {
                $ph   = implode(', ', array_fill(0, count($paint), '(?, ?, ?)'));
                $args = [];
                foreach( $paint as $p )
                    array_push( $args, $p['x'], $p['y'], $p['c'] );

                $pdo->prepare(
                    "INSERT INTO pixels (x, y, c) VALUES $ph
                     ON DUPLICATE KEY UPDATE c = VALUES(c), create_date = create_date"
                )->execute( $args );
            }

            foreach( $erase as $p ) {
                $pdo->prepare( 'DELETE FROM pixels WHERE x = ? AND y = ?' )
                    ->execute( [ $p['x'], $p['y'] ] );
            }
        }

        $newStatus = $action === 'approve' ? 'approved' : 'rejected';

        $pdo->prepare(
            'UPDATE pending_pixels SET status = ?, reviewed_at = NOW() WHERE batch_id = ?'
        )->execute( [$newStatus, $batchId] );

        // Look up user email and username for the notification
        $userRow = $pdo->prepare(
            'SELECT u.email, u.username, u.email_allowed
             FROM users u
             JOIN pending_pixels pp ON pp.user_id = u.id
             WHERE pp.batch_id = ?
             LIMIT 1'
        );
        $userRow->execute( [$batchId] );
        $recipient = $userRow->fetch();

        if( $recipient && $recipient['email_allowed'] ) {
            \pixels\functions\sendPixelReviewEmail($recipient['email'], $recipient['username'], $newStatus);
        }

        $resp->getBody()->write( json_encode( [ 'ok' => true ] ) );
    }

    /**
     * A route to show preview image in review mode for pending batches of pixels
     */
    public function batchPreview( Request $req, Response $resp, array $args ): Response {
        $batchId = $_GET['batch_id'] ?? '';
        if( !$batchId ) {
            $resp->withStatus( 400 );
            return $resp;
        }

        $scale    = 4;
        $padding  = 5;
        $maxCells = 60;

        try {
            $pdo = $this->container->get( 'pdo' );
            $stmt = $pdo->prepare(
                'SELECT x, y, c FROM pending_pixels WHERE batch_id = ? AND status = "pending"'
            );
            $stmt->execute( [$batchId] );
            $pending = $stmt->fetchAll();

            if( !$pending ) {
                $resp->withStatus( 404 );
                return $resp;
            }

            $minX = PHP_INT_MAX; $minY = PHP_INT_MAX;
            $maxX = PHP_INT_MIN; $maxY = PHP_INT_MIN;
            foreach( $pending as $p ) {
                $minX = min( $minX, (int)$p['x'] );
                $minY = min( $minY, (int)$p['y'] );
                $maxX = max( $maxX, (int)$p['x'] );
                $maxY = max( $maxY, (int)$p['y'] );
            }

            $x1 = max( 0, $minX - $padding );
            $y1 = max( 0, $minY - $padding );
            $x2 = $maxX + $padding;
            $y2 = $maxY + $padding;

            // Centre and cap the viewport if the batch spans too many cells
            if( $x2 - $x1 + 1 > $maxCells ) {
                $mid = intdiv($minX + $maxX, 2);
                $x1  = max( 0, $mid - intdiv( $maxCells, 2 ) );
                $x2  = $x1 + $maxCells - 1;
            }
            if( $y2 - $y1 + 1 > $maxCells ) {
                $mid = intdiv( $minY + $maxY, 2 );
                $y1  = max( 0, $mid - intdiv( $maxCells, 2 ) );
                $y2  = $y1 + $maxCells - 1;
            }

            $bgStmt = $pdo->prepare(
                'SELECT x, y, c FROM pixels
                WHERE x BETWEEN :x1 AND :x2 AND y BETWEEN :y1 AND :y2'
            );
            $bgStmt->execute( [':x1' => $x1, ':x2' => $x2, ':y1' => $y1, ':y2' => $y2] );
            $background = $bgStmt->fetchAll();

        } catch( Exception $e ) {
            $resp->withStatus( 500 );
            return $resp;
        }

        $cellsW = $x2 - $x1 + 1;
        $cellsH = $y2 - $y1 + 1;
        $imgW   = $cellsW * $scale;
        $imgH   = $cellsH * $scale;

        $img     = imagecreatetruecolor( $imgW, $imgH );
        $bgColor = imagecolorallocate( $img, 26, 26, 46 ); // #1a1a2e
        imagefill( $img, 0, 0, $bgColor );

        // Existing pixels — dimmed to 45 % brightness so pending pixels stand out
        foreach( $background as $row ) {
            $cx  = ((int)$row['x'] - $x1) * $scale;
            $cy  = ((int)$row['y'] - $y1) * $scale;
            $hex = ltrim($row['c'], '#');
            $r   = (int)( hexdec(substr( $hex, 0, 2 ) ) * 0.45 );
            $g   = (int)( hexdec(substr( $hex, 2, 2 ) ) * 0.45 );
            $b   = (int)( hexdec(substr( $hex, 4, 2 ) ) * 0.45 );
            $c   = imagecolorallocate( $img, $r, $g, $b);
            imagefilledrectangle( $img, $cx, $cy, $cx + $scale - 1, $cy + $scale - 1, $c);
        }

        // Pending pixels — full colour with a white outline; erases drawn in red
        foreach( $pending as $p ) {
            $px = (int)$p['x'];
            $py = (int)$p['y'];
            if( $px < $x1 || $px > $x2 || $py < $y1 || $py > $y2 )
                continue;

            $cx = ( $px - $x1 ) * $scale;
            $cy = ( $py - $y1 ) * $scale;

            if( $p['c'] === null ) {
                $c = imagecolorallocate($img, 230, 57, 70); // red = erase
            } else {
                $hex = ltrim($p['c'], '#');
                $r   = hexdec( substr( $hex, 0, 2 ) );
                $g   = hexdec( substr( $hex, 2, 2 ) );
                $b   = hexdec( substr( $hex, 4, 2 ) );
                $c   = imagecolorallocate( $img, $r, $g, $b );
            }

            imagefilledrectangle( $img, $cx, $cy, $cx + $scale - 1, $cy + $scale - 1, $c );

            // if( $scale >= 2 ) {
            //     $outline = imagecolorallocate( $img, 255, 255, 255 );
            //     imagerectangle( $img, $cx, $cy, $cx + $scale - 1, $cy + $scale - 1, $outline );
            // }
        }

        // Force the image out like this
        header('Content-Type: image/png');
        header('Cache-Control: no-store');
        imagepng( $img );
        imagedestroy( $img );
        return $resp;
    }

    /**
     * Test route for email
     */
    public function test( Request $req, Response $resp, array $args ): Response {
        
        \pixels\functions\sendVerificationEmail( 
            'chris.tolley@gmail.com', 
            'tolley',
            'abcxyznderbaaaaaaaagaaaaaaaaaaaaaa' );
        $resp->getBody()->write( '<marquee>Check your email</marquee>' );
        return $resp;
    }
}
