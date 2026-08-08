<?php namespace pixels\routes;
/**
 * Routing for HTTP requests to slim methods
 */
require_once './dependencies.php';
require_once './Controllers/mainController.php';
require_once './Controllers/APIController.php';
require_once './Controllers/OAuthController.php';
require_once './Controllers/ReviewController.php';
require_once './AuthMiddleware.php';
require_once './Controllers/GridController.php';
require_once './AuthMiddleware.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;
use pixels\Controller\mainController;
use pixels\Controller\APIController;
use pixels\Controller\OAuthController;
use pixels\Controller\gridController;
use pixels\Controllers\ReviewController;
use pixels\Middleware;

function applyRoutes( $app ) {
    $app->get( '/', [\pixels\Controller\mainController::class, 'home'] );
    
    $app->get( '/signup', [\pixels\Controller\mainController::class, 'signup'] );
    $app->post( '/auth', [\pixels\Controller\mainController::class, 'doSignup'] );

    $app->get( '/emails-allowed', [\pixels\Controller\mainController::class, 'emailsAllowed'] )
        ->add( new \pixels\Middleware\AuthMiddleware() );
    $app->get( '/toggle-emails-allowed', [\pixels\Controller\mainController::class, 'toggleEmailsAllowed'] )
        ->add( new \pixels\Middleware\AuthMiddleware() );
    
    $app->get( '/login', [\pixels\Controller\mainController::class, 'login'] );
    $app->post( '/login', [\pixels\Controller\OAuthController::class, 'doLogin' ] );

    $app->get( '/loginlanding', [\pixels\Controller\mainController::class, 'loginlanding'] );

    $app->get( '/logout', [\pixels\Controller\OAuthController::class, 'logout'] );

    $app->get( '/image-api', [\pixels\Controller\APIController::class, 'get'] );
    
    $app->post( '/image-api', [\pixels\Controller\APIController::class, 'post'] )
        ->add( new \pixels\Middleware\AuthMiddleware() );

    $app->delete('/image-api', [\pixels\Controller\APIController::class, 'delete'] )
        ->add( new \pixels\Middleware\AuthMiddleware() );

    $app->get( '/review', [\pixels\Controller\ReviewController::class, 'review'] )
        ->add( new \pixels\Middleware\AuthMiddleware() );

    $app->get( '/review-api', [\pixels\Controller\ReviewController::class, 'get'] )
        ->add( new \pixels\Middleware\AuthMiddleware() );

    $app->post( '/review-api', [\pixels\Controller\ReviewController::class, 'post'] )
        ->add( new \pixels\Middleware\AuthMiddleware() );
    
    $app->get( '/batch-preview', [\pixels\Controller\ReviewController::class, 'batchPreview'] )
        ->add( new \pixels\Middleware\AuthMiddleware() );

    $app->get( '/getme', [\pixels\Controller\mainController::class, 'getMe'] )
        ->add( new \pixels\Middleware\AuthMiddleware() );

    $app->get( '/grid', [\pixels\Controller\gridController::class, 'grid'] )
        ->add( new \pixels\Middleware\AuthMiddleware() );

    $app->get( '/api', [\pixels\Controller\APIController::class, 'get'] );

    $app->get( '/image', [\pixels\Controller\APIController::class, 'image'] );

    $app->get( '/raw', [\pixels\Controller\GridController::class, 'rawImage'] );

    // Test route
    $app->get( '/test', [\pixels\Controller\ReviewController::class, 'test'] );
}