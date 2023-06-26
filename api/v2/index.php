<?php

use Slim\Factory\AppFactory;
use Slim\Middleware\ContentLengthMiddleware;

require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

$app = AppFactory::create();
$app->setBasePath('/api/v2');
$app->addErrorMiddleware(true, true, true);
$app->addBodyParsingMiddleware();

require __DIR__ . '/routes_upload.php'; // Include the routes

$contentLengthMiddleware = new ContentLengthMiddleware();
$app->add($contentLengthMiddleware);
$app->run();
