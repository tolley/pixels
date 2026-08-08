<?php namespace pixels\Controller;

/**
 * A controller to handle all of the oAuth logins
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;
use Slim\Views\PhpRenderer;
use pixels\functions;

class OAuthController {
    private $container;

    public function __construct( ContainerInterface $container ) {
        $this->container = $container;
    }

    public function doLogin( Request $req, Response $resp, array $args ): Response {
        $body = json_decode((string) $req->getBody(), true);
        $email    = trim( $body['email']    ?? '' );
        $password = $body['password'] ?? '' ;

        if( ! $email || ! $password ) {
            $resp->withStatus( 400 );
            $resp->getBody()->write( json_encode( ['error' => 'Email and password are required'] ) );
            return $resp;
        }

        $pdo = $this->container->get( 'pdo' );
        $stmt = $pdo->prepare('SELECT id, username, password, email_verified, is_admin, email_allowed FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if( !$user || !password_verify( $password, $user['password'] ) ) {
            $resp->withStatus( 401 );
            $resp->getBody()->write( json_encode( ['error' => 'Invalid email or password'] ) );
            return $resp;
        }
        if( !$user['email_verified'] ) {
            $resp->withStatus( 403 );
            $resp->getBody()->write( json_encode( ['error' => 'Please verify your email before logging in', 'code' => 'unverified'] ) );
            return $resp;
        }

        \pixels\functions\jwtSetCookie(
                            (int)$user['id'],
                            (string)$user['username'],
                            (bool)$user['is_admin'], 
                            (bool)$user['email_allowed'] );
        $resp->getBody()->write( json_encode( ['ok' => true] ) );
        return $resp;
    }

    public function logout( Request $req, Response $resp, array $args ): Response {  
        \pixels\functions\jwtClearCookie();

        return $resp->withStatus( 302 )
            ->withHeader( 'Location', '/' );
    }
}