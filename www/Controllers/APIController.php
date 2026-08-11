<?php namespace pixels\Controller;
/**
 * A "api" controller to handle routes for things like the
 * getting and setting the pixels
 */
use Slim\Views\PhpRenderer;
use pixels\functions;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class APIController {
    private $container;

    public function __construct( ContainerInterface $container ) {
        $this->container = $container;
    }

    public function get( Request $req, Response $resp, array $args ): Response {
        $pdo = $this->container->get( 'pdo' );
        
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
        $body = [ 'pixels' => $stmt->fetchAll() ];

        $resp->getBody()->write( json_encode ( $body ) );
        return $resp;
    }

    public function post( Request $req, Response $resp, array $args ): Response {
        $data = $req->getParsedBody();

        $pixels = $data['pixels'] ?? [];
        if( empty( $pixels ) ) {
            $resp->getBody()->write( json_encode ( ['ok' => true, 'count', 0] ) );
            return $resp;
        }

        $authUser = \pixels\functions\getUserFromJWT();

        $batchId = bin2hex( random_bytes( 16 ) );
        $valid   = [];
        foreach( $pixels as $p ) {
            $x = isset($p['x']) ? (int)$p['x'] : null;
            $y = isset($p['y']) ? (int)$p['y'] : null;

            if( $x === null || $y === null )
                continue;

            $color = null;
            if( !array_key_exists( 'c', $p ) || $p['c'] === null ) {
                $color = null; // erase
            } elseif( preg_match( '/^#[0-9a-fA-F]{6}$/', $p['c'] ) ) {
                $color = $p['c'];
            } else {
                continue; // invalid color string
            }

            $valid[] = [$batchId, $x, $y, $color, (int)$authUser['sub'], $authUser['username']];
        }

        if( empty( $valid ) ) {
            $respBody = json_encode( [ 'ok' => false, 'error' => 'No valid pixels in request'] );
            $resp->getBody()->write( $respBody );
            $resp->withStatus( 400 );
            return $resp;
        }

        $pdo = $this->container->get( 'pdo' );
        $placeholders = implode(', ', array_fill(0, count($valid), '(?, ?, ?, ?, ?, ?)'));
        $pdo->prepare(
            "INSERT INTO pending_pixels (batch_id, x, y, c, user_id, username) VALUES $placeholders"
        )->execute(array_merge(...$valid));

        $respBody = json_encode( [ 'ok' => true, 'count' => count( $valid ) ] );
        $resp->getBody()->write( $respBody );
        return $resp;
    }

    public function delete( Request $req, Response $resp, array $args ): Response {
        $authUser = \pixels\functions\getUserFromJWT();
        if( !$authUser || empty( $authUser['is_admin'] ) ) {
            $resp->withStatus( 403 );
            $resp->getBody0>write( json_encode( ['ok' => false, 'error' => 'Forbidden'] ) );
            return $resp;
        }

        if( isset( $_GET['x1'] ) ) {
            $x1 = max(0, (int)$_GET['x1']);
            $y1 = max(0, (int)$_GET['y1']);
            $x2 = max($x1, (int)$_GET['x2']);
            $y2 = max($y1, (int)$_GET['y2']);

            $pdo = $this->container->get( 'pdo' );
            $pdo->prepare(
                'DELETE FROM pixels WHERE x BETWEEN :x1 AND :x2 AND y BETWEEN :y1 AND :y2'
            )->execute( [':x1' => $x1, ':x2' => $x2, ':y1' => $y1, ':y2' => $y2] );
        } else {
            $pdo->exec( 'DELETE FROM pixels' );
        }

        $resp->getBody()->write( json_encode( [ 'ok' => true ] ) );
        return $resp;
    }

    /**
     * Renders the raw image (gif) using the query string parameters
     */
    public function image( Request $req, Response $resp, array $args ): Response {
        $authUser = \pixels\functions\getUserFromJWT();

        $x1 = ( array_key_exists( 'x1', $_GET ) )? (int)$_GET['x1']: 0;
        $x2 = ( array_key_exists( 'x2', $_GET ) )? (int)$_GET['x2']: 300;
        $y1 = ( array_key_exists( 'y1', $_GET ) )? (int)$_GET['y1']: 0;
        $y2 = ( array_key_exists( 'y2', $_GET ) )? (int)$_GET['y2']: 300;
        $scale = ( array_key_exists( 'scale', $_GET ) )? (int)$_GET['scale']: 4;

        $imgUrl = "/raw?x1=$x1&x2=$x2&y1=$y1&y2=$y2&scale=$scale";

        $cellsW = $x2 - $x1 + 1;
        $cellsH = $y2 - $y1 + 1;
        $imgW   = $cellsW * $scale;
        $imgH   = $cellsH * $scale;

        $renderer = new PhpRenderer( './templates' );
        $viewData = [
            'cssBodyClass' => 'grid',
            'bodyTemplate' => 'image.php',
            'bodyData' => [
                'user' => $authUser,
                'imgUrl' => $imgUrl,
                'user'   => $authUser,
                'imgW' => $imgW,
                'imgH' => $imgH
            ]
        ];

        return $renderer->render( $resp, 'main.php', $viewData );
    }
}// End APIController