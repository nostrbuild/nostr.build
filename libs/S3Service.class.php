<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/utils.funcs.php';
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
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
  private $s3;
  private $r2;
  private $bucket;
  private $r2bucket;

  public function __construct($awsConfig)
  {
    // If AWS credentials are not defined, stop the function
    if (
      !isset($awsConfig['aws']['credentials']['key'])
      || !isset($awsConfig['aws']['credentials']['secret'])
      || !isset($awsConfig['aws']['region'])
      || !isset($awsConfig['aws']['bucket'])
    ) {
      error_log("AWS credentials are not set in the config file.\n");
      throw new Exception("AWS credentials are not set in the config file.");
    }

    $this->s3 = new S3Client($awsConfig['aws']);
    $this->bucket = $awsConfig['aws']['bucket'];

    // If AWS credentials are not defined, stop the function
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
  }

  // Upload a file to S3
  public function uploadToS3($sourcePath, $destinationPath, $sha256 = '', $s3backup = false, $npub = ''): bool
  {
    // Number of retries for the upload
    $maxRetries = 3;
    // Get the mime type of the file
    $mimeType = mime_content_type($sourcePath);

    // Check if the file exists
    if (!file_exists($sourcePath)) {
      error_log("The source file does not exist.\n");
      return false;
    }

    // DEBUG
    error_log("Uploading $sourcePath to $destinationPath with sha256: $sha256 and npub: $npub\n");

    // Define the options for S3 upload
    $s3Options = [
      'Bucket' => $this->bucket,
      'Key'    => $destinationPath,
      'SourceFile' => $sourcePath,
      'ACL'    => 'private',
      'retries' => 6,
      'StorageClass' => 'STANDARD',
      'CacheControl' => 'max-age=2592000',
      'ContentType' => $mimeType,
      'Metadata' => [
        'sha256' => $sha256,
        'npub' => $npub,
      ],
    ];

    // Define the options for R2 upload
    $r2BucketAndObjectNames = $this->getR2BucketAndObjectNames($destinationPath, $mimeType);
    $r2Options = [
      'Bucket' => $r2BucketAndObjectNames['bucket'],
      'Key'    => $r2BucketAndObjectNames['objectName'],
      'SourceFile' => $sourcePath,
      'ACL'    => 'private',
      'retries' => 6,
      'StorageClass' => 'STANDARD',
      'CacheControl' => 'max-age=2592000',
      'ContentType' => $mimeType,
      'Metadata' => [
        'sha256' => $sha256,
        'npub' => $npub,
      ],
    ];

    $attemptUpload = function ($client, $options, $retriesLeft) use (&$attemptUpload, $maxRetries) {
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
    };

    // Initialize retriesLeft to maxRetries
    $s3Promise = $attemptUpload($this->s3, $s3Options, $maxRetries);
    $r2Promise = $attemptUpload($this->r2, $r2Options, $maxRetries);

    $uploadPromises = [];
    // Append promises to the array and only add s3 if $s3backup is true
    $uploadPromises[] = $r2Promise;
    if ($s3backup) {
      $uploadPromises[] = $s3Promise;
    }

    try {
      $results = Promise\Utils::unwrap($uploadPromises);
      return true;
    } catch (\Throwable $e) {
      error_log("Upload Error: " . $e->getMessage());
      return false;
    }
  }

  // Delete an object from S3
  public function deleteFromS3($objectKey)
  {
    $s3DeleteOptions = [
      'Bucket' => $this->bucket,
      'Key'    => $objectKey,
    ];

    $r2BucketAndObjectNames = $this->getR2BucketAndObjectNames($objectKey);
    $r2DeleteOptions = [
      'Bucket' => $r2BucketAndObjectNames['bucket'],
      'Key'    => $r2BucketAndObjectNames['objectName'],
    ];

    $promises = [
      's3DeletePromise' => $this->s3->deleteObjectAsync($s3DeleteOptions),
      'r2DeletePromise' => $this->r2->deleteObjectAsync($r2DeleteOptions)
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

  // Retrieve the object metadata from S3
  /**
   * Summary of getObjectMetadataFromS3
   * @param mixed $objectKey
   * @return Aws\Result|bool
   */
  public function getObjectMetadataFromS3($objectKey): bool | Aws\Result
  {
    try {
      // Get the object metadata from the specified bucket
      $result = $this->s3->headObject([
        'Bucket' => $this->bucket,
        'Key'    => $objectKey,
      ]);

      if (!isset($result['Metadata'])) {
        error_log("The object metadata was not found.\n");
        return false;
      }
    } catch (AwsException $e) {
      // Output error message if fails
      error_log("s3 Get Object Metadata Error: " . $e->getMessage());
      return false;
    }

    return $result;
  }

  public function getObjectMetadataFromR2(string $objectKey, ?string $mime = null): bool | Aws\Result
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

    $r2Params = $this->getR2BucketAndObjectNames($objectKey, $mime);
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

  // List all objects in a specific bucket with optional prefix filtering
  /**
   * Usage:
   * $s3Service = new S3Service($awsConfig);
   *
   * foreach ($s3Service->listObjectsInBucket() as $object) {
   *   $objectKey = $object['Key'];
   *   // process $object
   * }
   */
  public function listObjectsInBucketS3($prefix = '', $maxKeys = 1000)
  {
    try {
      $isTruncated = true;
      $marker = '';

      while ($isTruncated) {
        $response = $this->s3->listObjects([
          'Bucket' => $this->bucket,
          'Prefix' => $prefix,
          'Marker' => $marker,
          'MaxKeys' => $maxKeys
        ]);

        foreach ($response['Contents'] as $object) {
          yield $object;
        }

        $isTruncated = $response['IsTruncated'];

        if ($isTruncated) {
          // Get the last object key and use as marker for next request
          $marker = end($response['Contents'])['Key'];
        }
      }
    } catch (AwsException $e) {
      error_log("s3 List Objects Error: " . $e->getMessage());
    }
  }

  // Copy an object from one location to another
  public function copyObjectS3($sourceKey, $destinationKey)
  {
    try {
      $result = $this->s3->copyObject([
        'Bucket'     => $this->bucket,
        'CopySource' => "{$this->bucket}/{$sourceKey}",
        'Key'        => $destinationKey
      ]);

      return $result;
    } catch (AwsException $e) {
      error_log("s3 Copy Object Error: " . $e->getMessage());
      return false;
    }
  }

  // Download a file from S3 to your local system
  public function downloadObjectS3($key, $saveAs)
  {
    try {
      $result = $this->s3->getObject([
        'Bucket' => $this->bucket,
        'Key'    => $key,
        'SaveAs' => $saveAs
      ]);

      return $result;
    } catch (AwsException $e) {
      error_log("s3 Download Object Error: " . $e->getMessage());
      return false;
    }
  }

  public function downloadObjectR2(string $key, ?string $saveAs = null, ?string $mime = null): string
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
    $r2Params = $this->getR2BucketAndObjectNames($key, $mime);
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

  // Get a URL for the object
  public function getObjectUrlS3($key)
  {
    try {
      $url = $this->s3->getObjectUrl($this->bucket, $key);
      return $url;
    } catch (AwsException $e) {
      error_log("s3 Get Object URL Error: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Get the R2 bucket name based on the given object key.
   *
   * @param string $objectKey The object key.
   *
   * @return array The bucket and object name as an associative array.
   */
  private function getR2BucketAndObjectNames(string $objectKey, string | null $mimeType = 'application/octet-stream'): array
  {
    // Validate the objectKey to ensure it contains a '/'
    if (strpos($objectKey, '/') === false) {
      return [
        'bucket' => $this->r2bucket,
        'objectName' => $objectKey
      ];
    }

    // Extract prefix and object name from object key
    $parts = explode('/', $objectKey);
    $objectName = array_pop($parts);
    $prefix = implode('/', $parts);

    // Determine bucket suffix based on prefix
    $bucketSuffix = match ($prefix) {
      'i' => '-img',
      'av' => '-av',
      'i/p' => '-pfp',
      'p' => '-pro',
      default => ''
    };

    // Further augment bucket suffix for paid accounts to split img and av
    if (!empty($mimeType) && substr_count($mimeType, '/') === 1) {
      $mimePrefix = explode('/', $mimeType)[0];
      if ($bucketSuffix === '-pro' && in_array($mimePrefix, ['video', 'audio'])) {
        $bucketSuffix .= '-av';
      }
    }

    return [
      'bucket' => $this->r2bucket . $bucketSuffix,
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
