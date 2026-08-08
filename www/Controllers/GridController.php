<?php namespace pixels\Controller;
/**
 * A "main" controller to handle routes for things like the
 * homepage, the image, and anything else I need
 */
use Slim\Views\PhpRenderer;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use pixels\functions;

class gridController {
    private $container;

    public function __construct( ContainerInterface $container ) {
        $this->container = $container;
    }

    public function grid( Request $req, Response $resp, array $args ) {
        $renderer = new PhpRenderer( './templates' );
        $pdo = $this->container->get( 'pdo' );

        $userData = \pixels\functions\jwtDecode( ( $_COOKIE['jwt'] )? $_COOKIE['jwt']: '' );
        $userInfo = \pixels\functions\getUserInfo( $pdo, $userData['username'] );

        $viewData = [
                'cssBodyClass' => 'grid',
                'bodyTemplate' => 'grid.php',
                'user' => $userInfo,
                'bodyData' => [
            ]
        ];

        return $renderer->render( $resp, 'grid.php', $viewData );
    }

    /**
     * Renders the raw image as a gif using the query string parameters
     */
    public function rawImage( Request $req, Response $resp, array $args ): Response {
        $x1    = max( 0, (int)( $_GET['x1']    ?? 0 ) );
        $y1    = max( 0, (int)( $_GET['y1']    ?? 0 ) );
        $x2    = max( $x1, (int)( $_GET['x2'] ?? $x1 + 1024 ) );
        $y2    = max( $y1, (int)( $_GET['y2'] ?? $y1 + 1024  ));
        $scale = max( 1, min( 16, (int)( $_GET['scale'] ?? 4 ) ) );


        // Cap region to avoid generating huge images (max 2048 cells per axis)
        // $x2 = min($x2, $x1 + 198);
        // $y2 = min($y2, $y1 + 198);

        $cellsW = $x2 - $x1 + 1;
        $cellsH = $y2 - $y1 + 1;
        $imgW   = $cellsW * $scale;
        $imgH   = $cellsH * $scale;

        try {
            $pdo = $this->container->get( 'pdo' );
            $stmt = $pdo->prepare(
                'SELECT x, y, c FROM pixels
                WHERE x BETWEEN :x1 AND :x2
                AND y BETWEEN :y1 AND :y2'
            );
            $stmt->execute( [':x1' => $x1, ':x2' => $x2, ':y1' => $y1, ':y2' => $y2] );
            $rows = $stmt->fetchAll();
        } catch( Exception $e ) {
            $resp->withStatus( 500 );
            $resp->getBody()->write( json_encode( ['error' => $e->getMessage()] ) );
            return $resp;
        }

        // Build image — palette mode required for GIF transparency
        $img = imagecreate( $imgW, $imgH );

        // First allocated color is the GIF background; mark it as the transparent index
        $transparent = imagecolorallocate( $img, 2, 0, 0 );
        imagecolortransparent( $img, $transparent );

        // Cache allocations so repeated colors don't exhaust the 256-slot palette
        $colors = [];
        foreach( $rows as $row ) {
            $cx  = ( (int)$row['x'] - $x1 ) * $scale;
            $cy  = ( (int)$row['y'] - $y1 ) * $scale;
            $hex = ltrim( $row['c'], '#' );
            if( !isset( $colors[$hex] ) ) {
                $r = hexdec( substr( $hex, 0, 2 ) );
                $g = hexdec( substr( $hex, 2, 2 ) );
                $b = hexdec( substr( $hex, 4, 2 ) );
                $colors[$hex] = imagecolorallocate( $img, $r, $g, $b )
                            ?: imagecolorclosest( $img, $r, $g, $b );
            }
            $c = $colors[$hex];

            if( $scale === 1 ) {
                imagesetpixel( $img, $cx, $cy, $c );
            } else {
                imagefilledrectangle( $img, $cx, $cy, $cx + $scale - 1, $cy + $scale - 1, $c );
            }
        }

        header('Content-Type: image/gif');
        header('Cache-Control: no-store');
        imagegif($img);
        return $resp;
    }
}