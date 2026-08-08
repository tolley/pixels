<?php namespace pixels\middleware;

require_once( './functions.php' );

use Psr\Http\MessageInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Server\MiddlewareInterface;
use pixels\functions;

class AuthMiddleware implements MiddlewareInterface {
    // public function process( Request $request, RequesttHandler $handler ): Response
    public function process( Request $request, RequestHandler $handler ): Response
    {
        $token = $_COOKIE['jwt'] ?? null;

        if( !$token ) {
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $tokenData = \pixels\functions\jwtDecode( $token );

        if( empty( $tokenData ) || 
            ! array_key_exists( 'username', $tokenData ) || empty( $tokenData['username'] ) ) {
            
            $response = new Response();
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $request->withAttribute( 'username', $tokenData['username'] );

        return $handler->handle( $request );
    }
};