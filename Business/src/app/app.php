<?php

use Slim\Factory\AppFactory;
use DI\Container;

require __DIR__ . '/../../vendor/autoload.php';


$dotenv = Dotenv\Dotenv::createImmutable('/var/www/html');
$dotenv->load();

$container = new Container();

AppFactory::setContainer($container);

$app = AppFactory::create();
$app->addBodyParsingMiddleware();

require_once "routes.php";
require_once "config.php";

$app->run();