<?php

use Slim\Factory\AppFactory;
use Slim\Middleware\ContentLengthMiddleware;

require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

// Create app
$app = AppFactory::create();
$app->setBasePath('/api/v2');
$app->addErrorMiddleware(true, true, true);
$app->addBodyParsingMiddleware();

require_once __DIR__ . '/routes_upload.php'; // Include free upload routes
require_once __DIR__ . '/routes_uppy.php'; // Include uppy upload routes
require_once __DIR__ . '/routes_account.php'; // Include pro account routes

$contentLengthMiddleware = new ContentLengthMiddleware();
$app->add($contentLengthMiddleware);
$app->run();
