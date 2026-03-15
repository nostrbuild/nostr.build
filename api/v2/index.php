<?php
$__apiTimingStart = hrtime(true);

// Set to true to enable detailed request timing logs (disable in production)
define('API_TIMING_DEBUG', false);

function apiTimingLog(string $label, ?float $startNs = null): void
{
  if (!API_TIMING_DEBUG) return;
  $now = hrtime(true);
  if ($startNs !== null) {
    $ms = ($now - $startNs) / 1e6;
    error_log("[API_TIMING] {$label}: {$ms}ms");
  } else {
    error_log("[API_TIMING] {$label}");
  }
}

$__requestPath = $_SERVER['REQUEST_URI'] ?? '?';
if (API_TIMING_DEBUG) {
  $__fpmQueueMs = (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000;
  error_log("[API_TIMING] FPM queue/session wait for {$__requestPath}: {$__fpmQueueMs}ms");
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';

// Catch any delay after script end (destructors, session close, etc.)
if (API_TIMING_DEBUG) {
  register_shutdown_function(function () {
    global $__apiTimingStart, $__requestPath;
    $totalMs = (hrtime(true) - $__apiTimingStart) / 1e6;
    error_log("[API_TIMING] SHUTDOWN {$__requestPath} total including cleanup: {$totalMs}ms");
  });
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/MultimediaUpload.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/S3Service.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/S3Multipart.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/UploadsData.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/UsersImages.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/UsersImagesFolders.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/BTCPayWebhook.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Account.class.php';
// Add GifBrowser class
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/GifBrowser.class.php';
// Delete media class
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/DeleteMedia.class.php';

// Blacklist and Rejected
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/BlacklistTable.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/RejectedFilesTable.class.php';

use DI\Container;
use Slim\Factory\AppFactory;
use Slim\Middleware\ContentLengthMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

apiTimingLog('index.php requires done', $__apiTimingStart);

// Get user-agent
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (API_TIMING_DEBUG) {
  error_log('User agent: ' . $userAgent . PHP_EOL);
}

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

$container->set('accountClass', function () {
  return function (string $npub) {
    global $link;
    return new Account($npub, $link);
  };
});

// Setup GifBrowser
$container->set('gifBrowser', function () {
  global $link;
  return new GifBrowser($link);
});

// Setup UploadsData
$container->set('uploadsData', function () {
  global $link;
  return new UploadsData($link);
});

// Setup BlacklistTable
$container->set('blacklistTable', function () {
  global $link;
  return new BlacklistTable($link);
});
// Setup RejectedFilesTable
$container->set('rejectedFilesTable', function () {
  global $link;
  return new RejectedFilesTable($link);
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

// Delete media factory
class DeleteMediaFactory
{
  private $link;
  private $awsConfig;

  public function __construct($link, $awsConfig)
  {
    $this->link = $link;
    $this->awsConfig = $awsConfig;
  }

  public function create($userNpub, $mediaName)
  {
    return new DeleteMedia($userNpub, $mediaName, $this->link, new S3Service($this->awsConfig));
  }
}

$container->set('deleteMediaFactory', function () {
  global $link;
  global $awsConfig;
  return new DeleteMediaFactory($link, $awsConfig);
});

// S3 Multipart upload handler
$container->set('s3Multipart', function () {
  global $link;
  global $awsConfig;
  return new S3Multipart($awsConfig, $link);
});


apiTimingLog('container setup done', $__apiTimingStart);

// Create app
$app = AppFactory::create();
// Middleware to add CORS headers
$app->add(function (Request $request, RequestHandler $handler): Response {
  $__corsStart = hrtime(true);
  $response = $handler->handle($request);
  apiTimingLog('CORS middleware (after handler) ' . $request->getUri()->getPath(), $__corsStart);
  // Check if the Origin header is present
  $origin = $request->getHeaderLine('Origin');
  if (empty($origin)) {
    return $response; // Return the response without CORS headers if Origin is not present
  }

  // Define patterns for allowed Origins and their corresponding paths
  $allowedOriginsAndPaths = [
    'https://nostr\.build' => ['/api/v2/.*'],
    'https://.*\.nostr\.build' => ['/api/v2/.*'],
    '([a-z]+)?://localhost(:[0-9]+)?' => ['/api/v2/upload/.*', '/api/v2/nip96/.*'],
    'https://.*' => ['/api/v2/upload/.*', '/api/v2/nip96/.*'],
    // add more origin and path patterns as needed
    // allow CORS from app://obsidian.md - requested by npub10a8kw2hsevhfycl4yhtg7vzrcpwpu7s6med27juf4lzqpsvy270qrh8zkw
    'app://obsidian\.md' => ['/api/v2/upload/.*', '/api/v2/nip96/.*'],
  ];

  $currentPath = $request->getUri()->getPath();

  // Never allow CORS for session-authenticated dashboard routes
  if (preg_match('#^/api/v2/account/dashboard(/|$)#', $currentPath)) {
    return $response;
  }

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
$app->addErrorMiddleware(false, true, true);
$app->addBodyParsingMiddleware();

$__routesStart = hrtime(true);
require_once __DIR__ . '/routes_upload.php'; // Include free upload routes
require_once __DIR__ . '/routes_nip96.php'; // Include nip96 upload routes
require_once __DIR__ . '/routes_uppy.php'; // Include uppy upload routes
require_once __DIR__ . '/routes_account.php'; // Include pro account routes
require_once __DIR__ . '/routes_btcpay.php'; // Include btcpay routes
require_once __DIR__ . '/routes_banned.php'; // Include btcpay routes
require_once __DIR__ . '/routes_gifs.php'; // Include gif routes
require_once __DIR__ . '/routes_blossom.php'; // Include blossom routes
require_once __DIR__ . '/routes_s3.php'; // Include S3 multipart upload routes
require_once __DIR__ . '/routes_account_dashboard.php'; // Include account dashboard routes
apiTimingLog('all route files loaded', $__routesStart);

$contentLengthMiddleware = new ContentLengthMiddleware();
$app->add($contentLengthMiddleware);

apiTimingLog('app ready', $__apiTimingStart);

// Break $app->run() into individual steps to time each phase
$__t1 = hrtime(true);
$serverRequestCreator = \Slim\Factory\ServerRequestCreatorFactory::create();
$request = $serverRequestCreator->createServerRequestFromGlobals();
apiTimingLog('1-createRequest ' . $request->getUri()->getPath(), $__t1);

$__t2 = hrtime(true);
$response = $app->handle($request);
apiTimingLog('2-handle (routing+middleware+handler) ' . $request->getUri()->getPath(), $__t2);

$__t3 = hrtime(true);
$responseEmitter = new \Slim\ResponseEmitter();
$responseEmitter->emit($response);
apiTimingLog('3-emit (send response to client) ' . $request->getUri()->getPath(), $__t3);

apiTimingLog('total request ' . $request->getUri()->getPath(), $__apiTimingStart);
