<?php namespace pixels;

require_once './dependencies.php';
require_once './routes.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use DI\Container;
use Slim\Factory\AppFactory as AppFactory;
use Slim\Views\PhpRenderer;

AppFactory::setContainer( $container );

$app = appFactory::create();
$app->addBodyParsingMiddleware();

routes\applyRoutes( $app );

$app->run();