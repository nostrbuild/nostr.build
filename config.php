<?php
/* Database credentials. Assuming you are running MySQL
server with default setting (user 'root' with no password) */
define('DB_SERVER', $_SERVER['DB_SERVER']);
define('DB_USERNAME', $_SERVER['DB_USERNAME']);
define('DB_PASSWORD', $_SERVER['DB_PASSWORD']);
define('DB_NAME', $_SERVER['DB_NAME']);

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
$s3Config = [
    'region'  => $_SERVER['AWS_REGION'],
    'version' => $_SERVER['AWS_VERSION'],
    'credentials' => [
        'key'    => $_SERVER['AWS_KEY'],
        'secret' => $_SERVER['AWS_SECRET'],
    ],
    'bucket' => $_SERVER['AWS_BUCKET'],
    'use_aws_shared_config_files' => false,
];

$r2Config = [
    'region'  => $_SERVER['R2_REGION'],
    'version' => $_SERVER['R2_VERSION'],
    'endpoint' => $_SERVER['R2_ENDPOINT'],
    'credentials' => [
        'key'    => $_SERVER['R2_ACCESS_KEY'],
        'secret' => $_SERVER['R2_SECRET_KEY'],
    ],
    'bucket' => $_SERVER['R2_BUCKET'],
    'use_aws_shared_config_files' => false,
];

$awsConfig = [
    'aws' => $s3Config,
    'r2' => $r2Config,
];

$btcpayConfig = [
    'apiKey'  => $_SERVER['BTCPAY_APIKEY'],
    'host'    => $_SERVER['BTCPAY_HOST'],
    'storeId' => $_SERVER['BTCPAY_STOREID'],
    'secret'  => $_SERVER['BTCPAY_SECRET'],
];

$csamReportingConfig = [
    'r2AccessKey' => $_SERVER['CSAM_REPORTING_R2_AK'],
    'r2SecretKey' => $_SERVER['CSAM_REPORTING_R2_SK'],
    'r2EndPoint' => $_SERVER['CSAM_REPORTING_R2_ENDPOINT'],
    'r2EvidenceBucket' => $_SERVER['CSAM_REPORTING_R2_EVIDENCE_BUCKET'],
    'r2LogsBucket' => $_SERVER['CSAM_REPORTING_R2_LOGS_BUCKET'],
];

// Maybe we should move it to a separate place and do not open a link to the database every time?
/* Attempt to connect to MySQL database */
$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($link === false) {
    die("ERROR: Could not connect. " . mysqli_connect_error());
}
