<?php
namespace pixels;

require_once( './vendor/autoload.php' );
require_once( './functions.php' );

use pdo;
use DI\Container;
use \Dotenv\Dotenv;
use pixels\functions;

// Load the .env file
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$container = new Container();

// Set up the MySql mysimpleorm container here for
// the mysql DB
$pdo = \pixels\functions\createPDO();
$container->set( 'pdo', $pdo );
