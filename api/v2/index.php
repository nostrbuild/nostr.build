<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/MultimediaUpload.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/S3Service.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/UsersImages.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/UsersImagesFolders.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/BTCPayWebhook.class.php';

use DI\Container;
use Slim\Factory\AppFactory;
use Slim\Middleware\ContentLengthMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

// Get user-agent
$userAgent = $_SERVER['HTTP_USER_AGENT'];
error_log('User agent: ' . $userAgent . PHP_EOL);

// Create Container using PHP-DI
$container = new Container();

// Set container to create App with on AppFactory
AppFactory::setContainer($container);

// Define our container dependencies for injection

// Free upload dependencies
$container->set('freeUpload', function () {
  global $awsConfig;
  global $link;
  // Instantiate S3Service
  $s3 = new S3Service($awsConfig);
  return new MultimediaUpload($link, $s3);
});

// Pro upload dependencies
$container->set('proUpload', function () {
  global $awsConfig;
  global $link;
  // Instantiate S3Service
  $s3 = new S3Service($awsConfig);
  return new MultimediaUpload($link, $s3, true, $_SESSION['usernpub'] ?? '');
});

$container->set('userImages', function () {
  global $link;
  return new UsersImages($link);
});

$container->set('userImagesFolders', function () {
  global $link;
  return new UsersImagesFolders($link);
});

//Setup container for webhooks
$container->set('btcpayWebhook', function () {
  global $btcpayConfig;
  return new BTCPayWebhook(
    $btcpayConfig['apiKey'],
    $btcpayConfig['host'],
    $btcpayConfig['storeId'],
    $btcpayConfig['secret']
  );
});

// TODO: Move the following into its own file and clean-up here
class MultimediaUploadFactory
{
  private $awsConfig;
  private $link;

  public function __construct($awsConfig, $link)
  {
    $this->awsConfig = $awsConfig;
    $this->link = $link;
  }

  public function create($isPro = false, $npub = null)
  {
    $s3 = new S3Service($this->awsConfig);
    $npubValue = $npub ?? $_SESSION['usernpub'] ?? '';
    return new MultimediaUpload($this->link, $s3, $isPro, $npubValue);
  }
}

// TODO: We should migrate other routes to use this factory
$container->set('multimediaUploadFactory', function () {
  global $awsConfig;
  global $link;
  return new MultimediaUploadFactory($awsConfig, $link);
});

// Create app
$app = AppFactory::create();
// Middleware to add CORS headers
$app->add(function (Request $request, RequestHandler $handler): Response {
  $response = $handler->handle($request);
  // Check if the Origin header is present
  $origin = $request->getHeaderLine('Origin');
  if (empty($origin)) {
    return $response; // Return the response without CORS headers if Origin is not present
  }

  // Define patterns for allowed Origins and their corresponding paths
  $allowedOriginsAndPaths = [
    'https://nostr\.build' => ['/api/v2/.*'],
    'https://.*\.nostr\.build' => ['/api/v2/.*'],
    'https?://localhost(:[0-9]+)?' => ['/api/v2/upload/.*'],
    'https://.*' => ['/api/v2/upload/.*'],
    // add more origin and path patterns as needed
  ];

  $currentPath = $request->getUri()->getPath();

  foreach ($allowedOriginsAndPaths as $originPattern => $pathPatterns) {
    if (preg_match('#^' . $originPattern . '$#', $origin)) {
      foreach ($pathPatterns as $pathPattern) {
        if (preg_match('#^' . $pathPattern . '$#', $currentPath)) {
          return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        }
      }
    }
  }

  // No matches found, return response without CORS headers
  return $response;
});
$app->options('/{routes:.+}', function ($request, $response, $args) {
  return $response;
});
$app->setBasePath('/api/v2');
$app->addErrorMiddleware(true, true, true);
$app->addBodyParsingMiddleware();

require_once __DIR__ . '/routes_upload.php'; // Include free upload routes
require_once __DIR__ . '/routes_nip96.php'; // Include nip96 upload routes
require_once __DIR__ . '/routes_uppy.php'; // Include uppy upload routes
require_once __DIR__ . '/routes_account.php'; // Include pro account routes
require_once __DIR__ . '/routes_btcpay.php'; // Include btcpay routes

$contentLengthMiddleware = new ContentLengthMiddleware();
$app->add($contentLengthMiddleware);
$app->run();
