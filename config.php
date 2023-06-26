<?php
/* Database credentials. Assuming you are running MySQL
server with default setting (user 'root' with no password) */
define('DB_SERVER', $_SERVER['DB_SERVER']);
define('DB_USERNAME', $_SERVER['DB_USERNAME']);
define('DB_PASSWORD', $_SERVER['DB_PASSWORD']);
define('DB_NAME', $_SERVER['DB_NAME']);

// TODO: It may be better move limits to a separate file so we do not create a link to the database every time
// User Storage limits
$storageLimits = [
    '99' => ['limit' => -1, 'message' => 'no limit'],
    '89' => ['limit' => 100, 'message' => '100MB'],
    '5' => ['limit' => 5 * 1024, 'message' => '5GB'],
    '3' => ['limit' => 5 * 1024, 'message' => '5GB'],
    '2' => ['limit' => 10 * 1024, 'message' => '10GB'],
    '1' => ['limit' => 20 * 1024, 'message' => '20GB'],
    '0' => ['limit' => 5 * 1024, 'message' => 'unknown'],
];

// Upload limits
$freeUploadLimit = 25 * 1024 * 1024; // 25MB in bytes

// AWS S3 config that is directly accepted by the S3Client constructor
// https://docs.aws.amazon.com/aws-sdk-php/v3/api/class-Aws.S3.S3Client.html#___construct
/**
 * $awsConfig = [
 *      'version' => 'latest', // Specifies the version of the web service to utilize
 *      'region' => 'us-west-2', // The region to send requests to
 *      'credentials' => [
 *          'key'    => 'my-access-key-id', // Your AWS Access Key ID
 *          'secret' => 'my-secret-access-key', // Your AWS Secret Access Key
 *          'token'  => 'session-token', // (optional) When using temporary credentials you need to supply a session token
 *      ],
 *      'scheme' => 'https', // (optional) URI Scheme of the base URL (defaults to "https")
 *      'endpoint' => 'http://my.endpoint.com', // (optional) Specify the complete base URL to use when sending requests
 *      'profile' => 'default', // (optional) Allows you to specify which profile to use when credentials are created from the AWS credentials file in your HOME directory
 *      'http'    => [ // (optional) Set Guzzle options
 *          'proxy' => '192.168.16.1:10' // (optional) Specify a proxy to use
 *      ],
 *      'signature_version' => 'v4', // (optional) The signature version to use when signing requests. Supported values are v4 and s3.
 *      'debug' => false, // (optional) Set to true to print out debug information
 *      'retries' => 3, // (optional) The number of times to retry failed requests. Set to 0 to disable retries. Set to -1 to retry indefinitely (the default behavior).
 *  ];
 */
$awsConfig = [
    'region' => $_SERVER['AWS_REGION'],
    'version' => $_SERVER['AWS_VERSION'],
    'credentials' => [
        'key' => $_SERVER['AWS_KEY'],
        'secret' => $_SERVER['AWS_SECRET'],
    ],
    'bucket' => $_SERVER['AWS_BUCKET'],
];

// Maybe we should move it to a separate place and do not open a link to the database every time?
/* Attempt to connect to MySQL database */
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($link === false) {
    die("ERROR: Could not connect. " . mysqli_connect_error());
}
