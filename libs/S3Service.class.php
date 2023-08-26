<?php
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
  public function uploadToS3($sourcePath, $destinationPath)
  {
    // Get the mime type of the file
    $mimeType = mime_content_type($sourcePath);

    // Check if the file exists
    if (!file_exists($sourcePath)) {
      error_log("The source file does not exist.\n");
      return false;
    }

    // Define the options for S3 upload
    $s3Options = [
      'Bucket' => $this->bucket,
      'Key'    => $destinationPath,
      'SourceFile' => $sourcePath,
      'ACL'    => 'private',
      'StorageClass' => 'STANDARD',
      'CacheControl' => 'max-age=2592000',
      'ContentType' => $mimeType,
    ];

    // Define the options for R2 upload
    $r2Options = [
      'Bucket' => $this->r2bucket,
      'Key'    => $destinationPath,
      'SourceFile' => $sourcePath,
      'ACL'    => 'private',
      'StorageClass' => 'STANDARD',
      'CacheControl' => 'max-age=2592000',
      'ContentType' => $mimeType,
    ];

    // Create promises for the S3 and R2 uploads
    $s3Promise = $this->s3->putObjectAsync($s3Options);
    $r2Promise = $this->r2->putObjectAsync($r2Options);

    try {
      // Wait for all promises to complete, with a concurrency of 2
      $results = Promise\Utils::unwrap([$s3Promise, $r2Promise]);

      foreach ($results as $result) {
        if (!isset($result['ObjectURL'])) {
          error_log("The file was not uploaded.\n");
          return false;
        }
      }
    } catch (AwsException $e) {
      // Output error message if fails
      error_log("s3 Upload Error: " . $e->getMessage());
      return false;
    }

    return true;
  }

  // Delete an object from S3
  public function deleteFromS3($objectKey)
  {
    $s3DeleteOptions = [
      'Bucket' => $this->bucket,
      'Key'    => $objectKey,
    ];

    $r2DeleteOptions = [
      'Bucket' => $this->r2bucket,
      'Key'    => $objectKey,
    ];

    $s3DeletePromise = $this->s3->deleteObjectAsync($s3DeleteOptions);
    $r2DeletePromise = $this->r2->deleteObjectAsync($r2DeleteOptions);

    try {
      $results = Promise\Utils::unwrap([$s3DeletePromise, $r2DeletePromise]);

      foreach ($results as $result) {
        if ($result['@metadata']['statusCode'] != 204) {
          error_log("The object was not deleted.\n");
          return false;
        }
      }

      return true;
    } catch (Exception $e) {
      error_log("An error occurred during parallel delete: " . $e->getMessage());
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

  public function getObjectMetadataFromR2($objectKey): bool | Aws\Result
  {
    try {
      // Get the object metadata from the specified bucket
      $result = $this->r2->headObject([
        'Bucket' => $this->bucket,
        'Key'    => $objectKey,
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
  public function listObjectsInBucket($prefix = '', $maxKeys = 1000)
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
  public function copyObject($sourceKey, $destinationKey)
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
  public function downloadObject($key, $saveAs)
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

  // Get a URL for the object
  public function getObjectUrl($key)
  {
    try {
      $url = $this->s3->getObjectUrl($this->bucket, $key);
      return $url;
    } catch (AwsException $e) {
      error_log("s3 Get Object URL Error: " . $e->getMessage());
      return false;
    }
  }
}
