<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/utils.funcs.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/SiteConfig.php';
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\S3\MultipartUploader;
use Aws\Exception\AwsException;
use Aws\Command;
use GuzzleHttp\Promise;

/**
 * Usage:
 *  $s3Service = new S3Service($awsConfig);
 *
 *  // Upload to S3
 *  $sourcePath = 'source/path/to/file';
 *  $destinationPath = 'destination/path/to/file';
 *  $s3Service->uploadToS3($sourcePath, $destinationPath);
 *
 *  // Delete from S3
 *  $objectKey = 'your-object-key';
 *  $s3Service->deleteFromS3($objectKey);
 *
 *  // Get object metadata from S3
 *  $metadata = $s3Service->getObjectMetadataFromS3($objectKey);
 */
class S3Service
{
  private $r2;
  private $e2;
  private $r2bucket;
  private $e2bucket;

  public function __construct($awsConfig)
  {
    // If R2 credentials are not defined, stop the function
    if (
      !isset($awsConfig['r2']['credentials']['key'])
      || !isset($awsConfig['r2']['credentials']['secret'])
      || !isset($awsConfig['r2']['region'])
      || !isset($awsConfig['r2']['bucket'])
    ) {
      error_log("R2 credentials are not set in the config file.\n");
    }

    $this->r2 = new S3Client($awsConfig['r2']);
    $this->r2bucket = $awsConfig['r2']['bucket'];

    // If E2 credentials are not defined, stop the function
    if (
      !isset($awsConfig['e2']['credentials']['key'])
      || !isset($awsConfig['e2']['credentials']['secret'])
      || !isset($awsConfig['e2']['region'])
      || !isset($awsConfig['e2']['bucket'])
    ) {
      error_log("E2 credentials are not set in the config file.\n");
    }

    $this->e2 = new S3Client($awsConfig['e2']);
    $this->e2bucket = $awsConfig['e2']['bucket'];
  }

  // Upload a file to S3
  public function uploadToS3($sourcePath, $destinationPath, $sha256 = '', $npub = '', bool $paidAccount = false): bool
  {
    $maxRetries = 3;
    $mimeType = mime_content_type($sourcePath);
    $fileSize = filesize($sourcePath);
    $multipartThreshold = 69 * 1024 * 1024; // 69 MB threshold

    if (!file_exists($sourcePath) || $fileSize === 0) {
      error_log("The source file does not exist or is empty.\n");
      return false;
    }

    error_log("Uploading $sourcePath to $destinationPath with sha256: $sha256 and npub: $npub\n");

    $commonOptions = [
      'ACL'    => 'private',
      'StorageClass' => 'STANDARD',
      'CacheControl' => 'max-age=2592000',
      'ContentType' => $mimeType,
      'SourceFile' => $sourcePath,  // Include SourceFile here to avoid repetition
      'retries' => 6,
      'Metadata' => [
        'sha256' => $sha256,
        'npub' => $npub,
      ],
    ];

    $r2BucketAndObjectNames = $this->getR2BucketAndObjectNames($destinationPath, $mimeType, $paidAccount);
    $r2Options = array_merge($commonOptions, [
      'Bucket' => $r2BucketAndObjectNames['bucket'],
      'Key'    => $r2BucketAndObjectNames['objectName'],
    ]);

    $e2BucketAndObjectNames = $this->getE2BucketAndObjectNames($destinationPath, $mimeType, $paidAccount);
    $e2Options = array_merge($commonOptions, [
      'Bucket' => $e2BucketAndObjectNames['bucket'],
      'Key'    => $e2BucketAndObjectNames['objectName'],
    ]);

    $attemptUpload = function ($client, $options, $retriesLeft) use (&$attemptUpload, $maxRetries, $fileSize, $multipartThreshold) {
      if ($fileSize > $multipartThreshold) {
        // Multipart upload for large files
        $uploader = new MultipartUploader($client, $options['SourceFile'], [
          'bucket' => $options['Bucket'],
          'key' => $options['Key'],
          'acl' => $options['ACL'],
          'storage_class' => $options['StorageClass'],
          'retries' => $options['retries'],
          'part_size' => $multipartThreshold,
          'before_initiate' => function (Command $command) use ($options) {
            // $command is a CreateMultipartUpload operation
            $command['ContentType'] = $options['ContentType'];
            $command['CacheControl'] = $options['CacheControl'];
            $command['Metadata'] = $options['Metadata'];
          },
        ]);

        error_log("Multipart uploading: {$options['SourceFile']}\n");
        return $uploader->promise()
          ->then(
            function ($result) {
              if (isset($result['ObjectURL'])) {
                return $result;
              }
              throw new Exception("Multipart upload failed, no ObjectURL");
            },
            function ($reason) use ($client, $options, &$attemptUpload, $retriesLeft, $maxRetries) {
              if ($retriesLeft > 0) {
                sleep(pow(2, $maxRetries - $retriesLeft));  // Exponential backoff
                return $attemptUpload($client, $options, $retriesLeft - 1);
              }
              throw $reason;
            }
          );
      } else {
        // Single-part upload for small files
        error_log("Single-part uploading: {$options['SourceFile']}\n");
        return $client->putObjectAsync($options)
          ->then(
            function ($result) {
              if (isset($result['ObjectURL'])) {
                return $result;
              }
              throw new Exception("Upload failed, no ObjectURL");
            },
            function ($reason) use ($client, $options, &$attemptUpload, $retriesLeft, $maxRetries) {
              if ($retriesLeft > 0) {
                sleep(pow(2, $maxRetries - $retriesLeft));  // Exponential backoff
                return $attemptUpload($client, $options, $retriesLeft - 1);
              }
              throw $reason;
            }
          );
      }
    };

    $uploadPromises = [];
    $uploadPromises[] = $attemptUpload($this->r2, $r2Options, $maxRetries);
    $uploadPromises[] = $attemptUpload($this->e2, $e2Options, $maxRetries);

    try {
      $results = Promise\Utils::unwrap($uploadPromises);
      return true;
    } catch (\Throwable $e) {
      error_log("Upload Error: " . $e->getMessage());
      return false;
    }
  }


  // Delete an object from S3
  public function deleteFromS3(string $objectKey, bool $paidAccount = false, string | null $mimeType = null)
  {
    $r2BucketAndObjectNames = $this->getR2BucketAndObjectNames(objectKey: $objectKey, paidAccount: $paidAccount, mimeType: $mimeType);
    $r2DeleteOptions = [
      'Bucket' => $r2BucketAndObjectNames['bucket'],
      'Key'    => $r2BucketAndObjectNames['objectName'],
    ];

    $e2BucketAndObjectNames = $this->getE2BucketAndObjectNames(objectKey: $objectKey, paidAccount: $paidAccount, mimeType: $mimeType);
    $e2DeleteOptions = [
      'Bucket' => $e2BucketAndObjectNames['bucket'],
      'Key'    => $e2BucketAndObjectNames['objectName'],
    ];

    $promises = [
      'r2DeletePromise' => $this->r2->deleteObjectAsync($r2DeleteOptions),
      'e2DeletePromise' => $this->e2->deleteObjectAsync($e2DeleteOptions),
    ];

    // Since we cannot reliably predict which R2 bucket the object will be in for paid accounts,
    // we must delete from all of them.
    if (substr($r2BucketAndObjectNames['bucket'], -4) === '-pro') {
      $additionalR2Bucket = $r2BucketAndObjectNames['bucket'] . '-av';
      $promises['r2ProAvDeletePromise'] = $this->r2->deleteObjectAsync([
        'Bucket' => $additionalR2Bucket,
        'Key'    => $r2BucketAndObjectNames['objectName']
      ]);
    }
    if (substr($e2BucketAndObjectNames['bucket'], -4) === '-pro') {
      $additionalE2Bucket = $e2BucketAndObjectNames['bucket'] . '-av';
      $promises['e2AvProDeletePromise'] = $this->e2->deleteObjectAsync([
        'Bucket' => $additionalE2Bucket,
        'Key'    => $e2BucketAndObjectNames['objectName']
      ]);
    }

    try {
      $results = Promise\Utils::unwrap($promises);

      foreach ($results as $result) {
        if ($result['@metadata']['statusCode'] != 204) {
          error_log("The object was not deleted. Status Code: " . $result['@metadata']['statusCode'] . "\n");
          return false;
        }
      }

      return true;
    } catch (AwsException $e) {
      // Handle AWS-specific exceptions
      if ($e->getAwsErrorCode() === 'NoSuchKey') {
        // There was nothing to delete, so consider it a success
        return true;
      } else {
        // Log other AWS-specific exceptions and return false
        error_log("An AWS-specific error occurred during parallel delete: " . $e->getMessage());
        return false;
      }
    } catch (Exception $e) {
      // Log general exceptions and return false
      error_log("A general error occurred during parallel delete: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Calculate SHA256 hash of an S3 object's content by streaming
   * @return string SHA256 hash of the content, or empty string if object not found
   */
  public function getS3ObjectHash(string $objectKey, bool $paidAccount = false, string | null $mimeType = null): string
  {
    $r2BucketAndObjectNames = $this->getR2BucketAndObjectNames(objectKey: $objectKey, paidAccount: $paidAccount, mimeType: $mimeType);
    $e2BucketAndObjectNames = $this->getE2BucketAndObjectNames(objectKey: $objectKey, paidAccount: $paidAccount, mimeType: $mimeType);

    // Define all possible bucket combinations to try
    $bucketConfigs = [
      // R2 buckets
      [
        'client' => $this->r2,
        'bucket' => $r2BucketAndObjectNames['bucket'],
        'key' => $r2BucketAndObjectNames['objectName'],
      ],
      // E2 buckets
      [
        'client' => $this->e2,
        'bucket' => $e2BucketAndObjectNames['bucket'],
        'key' => $e2BucketAndObjectNames['objectName'],
      ]
    ];

    // Add pro-av buckets if applicable
    if (substr($r2BucketAndObjectNames['bucket'], -4) === '-pro') {
      $bucketConfigs[] = [
        'client' => $this->r2,
        'bucket' => $r2BucketAndObjectNames['bucket'] . '-av',
        'key' => $r2BucketAndObjectNames['objectName'],
      ];
    }
    if (substr($e2BucketAndObjectNames['bucket'], -4) === '-pro') {
      $bucketConfigs[] = [
        'client' => $this->e2,
        'bucket' => $e2BucketAndObjectNames['bucket'] . '-av',
        'key' => $e2BucketAndObjectNames['objectName'],
      ];
    }

    // Try each bucket configuration until we find the object
    foreach ($bucketConfigs as $config) {
      try {
        $result = $config['client']->getObject([
          'Bucket' => $config['bucket'],
          'Key'    => $config['key']
        ]);

        // Calculate hash from stream
        $hashContext = hash_init('sha256');
        $stream = $result['Body'];
        while (!$stream->eof()) {
          hash_update($hashContext, $stream->read(8192));
        }
        return hash_final($hashContext);
      } catch (AwsException $e) {
        error_log("{$config['name']} bucket get object error: " . $e->getMessage());
        continue; // Try next bucket
      }
    }

    // If we get here, we couldn't find the object in any bucket
    return '';
  }


  public function getObjectMetadataFromR2(string $objectKey, ?string $mime = null, ?bool $paidAccount = false): bool | Aws\Result
  {
    // If mime is not provided, determine from filename/objectKey
    if (empty($mime)) {
      // Get extension from objectKey
      $extension = pathinfo($objectKey, PATHINFO_EXTENSION);
      // Get mime type from extension
      $fileType = getFileType($extension);
      $fileType = $fileType === 'unknown' ? 'application/octet-stream' : $fileType;
      $mime = "{$fileType}/{$extension}";
    }

    $r2Params = $this->getR2BucketAndObjectNames($objectKey, $mime, $paidAccount);
    try {
      // Get the object metadata from the specified bucket
      $result = $this->r2->headObject([
        'Bucket' => $r2Params['bucket'],
        'Key'    => $r2Params['objectName'],
      ]);

      if (!isset($result['Metadata'])) {
        error_log("The object metadata was not found.\n");
        return false;
      }
    } catch (AwsException $e) {
      // Output error message if fails
      error_log("r3 Get Object Metadata Error: " . $e->getMessage());
      return false;
    }

    return $result;
  }


  public function downloadObjectR2(string $key, ?string $saveAs = null, ?string $mime = null, ?bool $paidAccount = false): string
  {
    // If mime is not provided, determine from filename/objectKey
    if (empty($mime)) {
      // Get extension from objectKey
      $extension = pathinfo($key, PATHINFO_EXTENSION);
      // Get mime type from extension
      $fileType = getFileType($extension);
      $fileType = $fileType === 'unknown' ? 'application/octet-stream' : $fileType;
      $mime = "{$fileType}/{$extension}";
    }
    $r2Params = $this->getR2BucketAndObjectNames($key, $mime, $paidAccount);
    // If saveAs not provided, use unique random temp file
    if (empty($saveAs)) {
      $saveAs = tempnam(sys_get_temp_dir(), 'r2download');
    }
    try {
      $result = $this->r2->getObject([
        'Bucket' => $r2Params['bucket'],
        'Key'    => $r2Params['objectName'],
        'SaveAs' => $saveAs
      ]);

      if ($result instanceof Aws\Result) {
        // Check is status code is 200
        if ($result['@metadata']['statusCode'] === 200) {
          return $saveAs;
        } else {
          return '';
        }
      } else {
        return '';
      }
    } catch (AwsException $e) {
      error_log("r2 Download Object Error: " . $e->getMessage());
      return '';
    }
  }


  /**
   * Get the R2 bucket name based on the given object key.
   *
   * @param string $objectKey The object key.
   *
   * @return array The bucket and object name as an associative array.
   */
  private function getR2BucketAndObjectNames(string $objectKey, string | null $mimeType = 'application/octet-stream', bool $paidAccount = false): array
  {
    // Validate the objectKey to ensure it contains a '/'
    if (strpos($objectKey, '/') === false) {
      return [
        'bucket' => $this->r2bucket,
        'objectName' => $objectKey
      ];
    }

    $objectName = basename($objectKey);
    //error_log("R2 object key: $objectKey\n" . "R2 mime type: $mimeType\n" . getFileTypeFromName($objectName) . "\n");
    // Handle PFP case
    if (substr($objectKey, 0, 4) === 'i/p/') {
      $bucketSuffix = SiteConfig::getBucketSuffix('profile_picture');
    } else {
      // This can throw but we expect it to be caught in the calling function
      $type = getFileTypeFromName($objectName);
      $type = $type === 'unknown' && !empty($mimeType) && $mimeType !== 'application/octet-stream' ?
        explode('/', $mimeType)[0] :
        $type;
      $bucketSuffix = $paidAccount
        ? SiteConfig::getBucketSuffix('professional_account_' . $type)
        : SiteConfig::getBucketSuffix($type);
    }

    // DEBUG
    //error_log("R2 bucket suffix: $bucketSuffix\n");
    //error_log("R2 object name: $objectName\n");

    return [
      'bucket' => $this->r2bucket . $bucketSuffix,
      'objectName' => $objectName
    ];
  }

  private function getE2BucketAndObjectNames(string $objectKey, string | null $mimeType = 'application/octet-stream', bool $paidAccount = false): array
  {
    // Validate the objectKey to ensure it contains a '/'
    if (strpos($objectKey, '/') === false) {
      return [
        'bucket' => $this->e2bucket,
        'objectName' => $objectKey
      ];
    }

    $objectName = basename($objectKey);
    // Handle PFP case
    if (substr($objectKey, 0, 4) === 'i/p/') {
      $bucketSuffix = SiteConfig::getBucketSuffix('profile_picture');
    } else {
      // This can throw but we expect it to be caught in the calling function
      $type = getFileTypeFromName($objectName);
      $type = $type === 'unknown' && !empty($mimeType) && $mimeType !== 'application/octet-stream' ?
        explode('/', $mimeType)[0] :
        $type;
      $bucketSuffix = $paidAccount
        ? SiteConfig::getBucketSuffix('professional_account_' . $type)
        : SiteConfig::getBucketSuffix($type);
    }

    return [
      'bucket' => $this->e2bucket . $bucketSuffix,
      'objectName' => $objectName
    ];
  }
}


// Set of function to specifiically handle CSAM related operations.
function copyFromR2ToR2Bucket(
  string $sourceBucket,
  string $sourceKey,
  string $destinationBucket,
  string $destinationKey,
  string $endPoint,
  string $accessKey,
  string $secretKey
): bool {
  $r2Config = [
    'region'  => 'auto',
    'version' => 'latest',
    'endpoint' => $endPoint,
    'credentials' => [
      'key'    => $accessKey,
      'secret' => $secretKey,
    ],
    'bucket' => $sourceBucket,
    'use_aws_shared_config_files' => false,
  ];

  $r2 = new S3Client($r2Config);

  $r2Params = [
    'Bucket'     => $destinationBucket,
    'CopySource' => "{$sourceBucket}/{$sourceKey}",
    'Key'        => $destinationKey
  ];

  try {
    $result = $r2->copyObject($r2Params);
    if ($result['@metadata']['statusCode'] !== 200) {
      error_log("The object was not copied. Status Code: " . $result['@metadata']['statusCode'] . "\n");
      return false;
    }
    return true;
  } catch (AwsException $e) {
    error_log("r2 Copy Object Error: " . $e->getMessage());
    return false;
  }
}

function storeToR2Bucket(
  string $sourceFilePath,
  string $destinationKey,
  string $destinationBucket,
  string $endPoint,
  string $accessKey,
  string $secretKey,
  array $metadata = []
): bool {
  $r2Config = [
    'region'  => 'auto',
    'version' => 'latest',
    'endpoint' => $endPoint,
    'credentials' => [
      'key'    => $accessKey,
      'secret' => $secretKey,
    ],
    'bucket' => $destinationBucket,
    'use_aws_shared_config_files' => false,
  ];

  $r2 = new S3Client($r2Config);

  $r2Params = [
    'Bucket'     => $destinationBucket,
    'Key'        => $destinationKey,
    'SourceFile' => $sourceFilePath,
    'ACL'        => 'private',
    'retries'    => 6,
    'StorageClass' => 'STANDARD',
    'CacheControl' => 'max-age=2592000',
    'ContentType' => mime_content_type($sourceFilePath),
    'Metadata' => $metadata,
  ];

  try {
    $result = $r2->putObject($r2Params);
    if ($result['@metadata']['statusCode'] !== 200) {
      error_log("The object was not stored. Status Code: " . $result['@metadata']['statusCode'] . "\n");
      return false;
    }
    return true;
  } catch (AwsException $e) {
    error_log("r2 Store Object Error: " . $e->getMessage());
    return false;
  }
}

function storeJSONObjectToR2Bucket(
  array $object,
  string $destinationKey,
  string $destinationBucket,
  string $endPoint,
  string $accessKey,
  string $secretKey,
): bool {
  $r2Config = [
    'region'  => 'auto',
    'version' => 'latest',
    'endpoint' => $endPoint,
    'credentials' => [
      'key'    => $accessKey,
      'secret' => $secretKey,
    ],
    'bucket' => $destinationBucket,
    'use_aws_shared_config_files' => false,
  ];

  $r2 = new S3Client($r2Config);

  $r2Params = [
    'Bucket'     => $destinationBucket,
    'Key'        => $destinationKey,
    'Body'       => json_encode($object),
    'ACL'        => 'private',
    'retries'    => 6,
    'StorageClass' => 'STANDARD',
    'CacheControl' => 'max-age=2592000',
    'ContentType' => 'application/json',
  ];

  try {
    $result = $r2->putObject($r2Params);
    if ($result['@metadata']['statusCode'] !== 200) {
      error_log("The object was not stored. Status Code: " . $result['@metadata']['statusCode'] . "\n");
      return false;
    }
    return true;
  } catch (AwsException $e) {
    error_log("r2 Store Object Error: " . $e->getMessage());
    return false;
  }
}

// function to fetch the object from R2 bucket that is in JSON format
// we only know part of the key, <prefix>/<prefix>_<timestamp>.json
// we do not know <timestamp> part of the key and they can be multiple
function fetchJsonFromR2Bucket(
  string $prefix,
  string $endPoint,
  string $accessKey,
  string $secretKey,
  string $bucket
): array {
  $r2Config = [
    'region'  => 'auto',
    'version' => 'latest',
    'endpoint' => $endPoint,
    'credentials' => [
      'key'    => $accessKey,
      'secret' => $secretKey,
    ],
    'bucket' => $bucket,
    'use_aws_shared_config_files' => false,
  ];

  $r2 = new S3Client($r2Config);

  $r2Params = [
    'Bucket' => $bucket,
    'Prefix' => $prefix,
  ];

  $objects = [];
  $keys = [];
  try {
    $result = $r2->listObjects($r2Params);
    if (!isset($result['Contents'])) {
      error_log("The object was not found.\n");
      return [];
    }
    foreach ($result['Contents'] as $object) {
      $keys[] = $object['Key'];
    }
    // Iterate over the keys and fetch the object(s)
    foreach ($keys as $key) {
      $r2Params['Key'] = $key;
      $result = $r2->getObject($r2Params);
      $objects[$key] = json_decode($result['Body'], true);
    }
  } catch (AwsException $e) {
    error_log("r2 List Objects Error: " . $e->getMessage());
  }

  return $objects;
}

function fetchJsonFromR2BucketByKeys(
  array $keys,
  string $endPoint,
  string $accessKey,
  string $secretKey,
  string $bucket
): array {
  $r2Config = [
    'region'  => 'auto',
    'version' => 'latest',
    'endpoint' => $endPoint,
    'credentials' => [
      'key'    => $accessKey,
      'secret' => $secretKey,
    ],
    'bucket' => $bucket,
    'use_aws_shared_config_files' => false,
  ];

  $r2 = new S3Client($r2Config);

  $objects = [];
  $r2Params = [
    'Bucket' => $bucket,
  ];
  // Iterate over the keys and fetch the object(s)
  foreach ($keys as $key) {
    $r2Params['Key'] = $key;
    try {
      $result = $r2->getObject($r2Params);
      $objects[$key] = json_decode($result['Body'], true);
    } catch (AwsException $e) {
      error_log("r2 Get Object Error: " . $e->getMessage());
    }
  }

  return $objects;
}

function fetchBinaryFromR2Bucket(
  string $key,
  string $endPoint,
  string $accessKey,
  string $secretKey,
  string $bucket
): string {
  $r2Config = [
    'region'  => 'auto',
    'version' => 'latest',
    'endpoint' => $endPoint,
    'credentials' => [
      'key'    => $accessKey,
      'secret' => $secretKey,
    ],
    'bucket' => $bucket,
    'use_aws_shared_config_files' => false,
  ];

  $r2 = new S3Client($r2Config);

  $r2Params = [
    'Bucket' => $bucket,
    'Key'    => $key,
  ];

  try {
    $result = $r2->getObject($r2Params);
    return $result['Body'];
  } catch (AwsException $e) {
    error_log("r2 Get Object Error: " . $e->getMessage());
    return '';
  }
}

function listObjectsUnderPrefix(
  string $prefix,
  string $endPoint,
  string $accessKey,
  string $secretKey,
  string $bucket
): array {
  $r2Config = [
    'region'  => 'auto',
    'version' => 'latest',
    'endpoint' => $endPoint,
    'credentials' => [
      'key'    => $accessKey,
      'secret' => $secretKey,
    ],
    'bucket' => $bucket,
    'use_aws_shared_config_files' => false,
  ];

  $r2 = new S3Client($r2Config);

  $r2Params = [
    'Bucket' => $bucket,
    'Prefix' => $prefix,
  ];

  $objects = [];
  try {
    $result = $r2->listObjects($r2Params);
    if (!isset($result['Contents'])) {
      error_log("The object was not found.\n");
      return [];
    }
    foreach ($result['Contents'] as $object) {
      $objects[] = $object['Key'];
    }
  } catch (AwsException $e) {
    error_log("r2 List Objects Error: " . $e->getMessage());
  }

  return $objects;
}

function getPresignedUrlFromObjectKey(
  string $objectKey,
  string $endPoint,
  string $accessKey,
  string $secretKey,
  string $bucket,
  int $expires = 3600
): string {
  $r2Config = [
    'region'  => 'auto',
    'version' => 'latest',
    'endpoint' => $endPoint,
    'credentials' => [
      'key'    => $accessKey,
      'secret' => $secretKey,
    ],
    'bucket' => $bucket,
    'use_aws_shared_config_files' => false,
  ];

  $r2 = new S3Client($r2Config);

  $r2Params = [
    'Bucket' => $bucket,
    'Key'    => $objectKey,
  ];

  try {
    $command = $r2->getCommand('GetObject', $r2Params);
    $request = $r2->createPresignedRequest($command, "+{$expires} seconds");
    return (string) $request->getUri();
  } catch (AwsException $e) {
    error_log("r2 Get Presigned URL Error: " . $e->getMessage());
    return '';
  }
}
