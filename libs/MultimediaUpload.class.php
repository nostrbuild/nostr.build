<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/imageproc.class.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/utils.funcs.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/S3Service.class.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/GifConverter.class.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/db/UploadsData.class.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/db/UsersImages.class.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/db/UsersImagesFolders.class.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/config.php"; // Size limits
require_once $_SERVER['DOCUMENT_ROOT'] . "/SiteConfig.php";

// Vendor autoload
require_once $_SERVER['DOCUMENT_ROOT'] . "/vendor/autoload.php";

use Hashids\Hashids;

// Globals
global $link;
global $storageLimits;

/**
 * Summary of MultimediaUpload
 * The flow should be as follows:
 * 1) Accept file from user
 * 2) Validate file (size, type, etc.)
 * 3) [if free] get file's sha256 hash (we want to fingerprint original uploads)
 * 4) Perform image processing (resize, compress, etc.), pro upload only optimization, profile pic resize and crop
 * 5) Store file in database (filename, metadata, file size, type)
 * 6) [PRO] Generate a new filename from the database ID [perform a database tranasction to ensure consistency]
 * 7) Upload file to S3
 * Example usage of the class:
 * $upload = new MultimediaUpload($link, $s3Service);
 * $upload->setFiles($_FILES);
 * $upload->uploadFiles();
 * $urls = $upload->getFileUrls();
 */

// TODO: Once the full functionality is rewritten, this class needs major refactoring
// and splitting into multiple classes, with the MultimediaUpload class being the
// base class that will be extended by the other classes.

class MultimediaUpload
{
  /**
   * Summary of db
   * @var 
   */
  protected $db; // Instance of the mysqli class used to instantiate this table classes, e.g., UploadsData, UsersImages
  /**
   * The structure of the array for proccessed files:
   *     [{ /* data about the file, or empty object in case of an error * /
   *      'fileName' => <name of the file with extention>,
   *      'url' => <url of the file>,
   *      'thumbnail' => <url of the thumbnail of the file>,
   *      'blurhash' => <blurhash of the file>,
   *      'sha256' => <sha256 of the file>,
   *      'type' => <'image', 'video', 'audio', 'profile' or 'unknown'>,
   *      'mime' => <mime type of the file>,
   *      'size' => <size of the file in bytes>,
   *      'metadata' => <metadata of the file>, // Metadata may include EXIF data, etc.
   *      'dimentions' => { // Dimentions are only available for images, may add for videos later
   *       'width' => <width of the file in pixels>,
   *       'height' => <height of the file in pixels>,
   *      },
   *      'responsive' => {
   *        '240p' => <url of the 428x426 responsive image>,
   *        '360p' => <url of the 640x640 responsive image>,
   *        '480p' => <url of the 854x854 responsive image>,
   *        '720p' => <url of the 1280x1280 responsive image>,
   *        '1080p' => <url of the 1920x1920 responsive image>,
   *       },
   *     },
   *      ...
   *     ]
   */
  protected $filesArray; // The $_FILES reconstructed array
  /**
   * Summary of uploadedFiles
   * @var array
   */
  protected $uploadedFiles = []; // Array of uploaded files with URLs and other info
  /**
   * Summary of file
   * @var 
   */
  protected $file; // Used for temporary storage of the current file in the loop
  /**
   * Summary of uploadsData
   * @var 
   */
  protected $uploadsData; // Used for free uploads; instance of the UploadsData class
  /**
   * Summary of usersImages
   * @var 
   */
  protected $usersImages; // Used for authenticated uploads; instance of the UsersImages class
  /**
   * Summary of usersImagesFolders
   * @var 
   */
  protected $usersImagesFolders; // Used for authenticated uploads; instance of the UsersImagesFolders class
  /**
   * Summary of s3Service
   * @var 
   */
  protected $s3Service; // Instance of the S3Service class
  /**
   * Summary of userNpub
   * @var 
   */
  protected $userNpub; // Used for authenticated uploads.
  /**
   * Summary of pro
   * @var 
   */
  protected $pro; // Used to determine if the upload is pro or free
  // Workaround for type matchin, needs fixing
  /**
   * Summary of typeMap
   * @var array
   */
  protected $typeMap = [
    'picture' => 'image',
    'image' => 'image',
    'video' => 'video',
    'audio' => 'audio',
    'profile' => 'profile_picture',
    'unknown' => 'unknown',
  ];
  // Per client handling of the file types
  /**
   * Summary of apiClient
   * @var 
   */
  protected $apiClient;
  /**
   * Summary of gifConverter
   * @var 
   */
  protected $gifConverter;

  /**
   * Summary of __construct
   * @param mysqli $db
   * @param S3Service $s3Service
   * @param bool $pro
   * @param string $userNpub
   */
  public function __construct(mysqli $db, S3Service $s3Service, bool $pro = false, string $userNpub = '')
  {
    $this->db = $db;
    $this->s3Service = $s3Service;
    $this->userNpub = $userNpub;
    $this->pro = $pro;
    $this->uploadsData = new UploadsData($db);
    $this->gifConverter = new GifConverter();
    if ($this->pro) {
      $this->usersImages = new UsersImages($db);
      $this->usersImagesFolders = new UsersImagesFolders($db);
    }
    // Check if the upload is pro and userNpub is not set or empty
    if ($this->pro && empty($this->userNpub)) {
      throw new Exception('UserNpub is required for pro uploads');
    }
  }

  /**
   * Summary of __destruct
   * Clean up the temporary files
   */
  public function __destruct()
  {
    // Delete the temporary files
    if (is_array($this->filesArray)) {
      foreach ($this->filesArray as $file) {
        if (file_exists($file['tmp_name'])) {
          unlink($file['tmp_name']);
        }
      }
    }
  }

  /**
   * Summary of setFiles
   * @param array $files
   * @param string $tempDirectory
   * @return void
   */
  public function setFiles(array $files, string $tempDirectory = null): void
  {
    // We make temp directory optional, and use the system's temp directory by default
    if ($tempDirectory === null) {
      $tempDirectory = sys_get_temp_dir();
    }
    $this->filesArray = $this->restructureFilesArray($files, $tempDirectory);
  }

  /**
   * Summary of setPsrFiles
   * @param array $files
   * @param mixed $meta
   * @param string $tempDirectory
   * @return void
   */
  public function setPsrFiles(array $files, mixed $meta = [], string $tempDirectory = null): void
  {
    // We make temp directory optional, and use the system's temp directory by default
    if ($tempDirectory === null) {
      $tempDirectory = sys_get_temp_dir();
    }
    $this->filesArray = $this->restructurePsrFilesArray($files, $meta, $tempDirectory);
  }

  /**
   * Summary of getUploadedFiles
   * @return array
   */
  public function getUploadedFiles(): array
  {
    return $this->uploadedFiles;
  }

  /**
   * Summary of getFileUrls
   * @return array
   */
  public function getFileUrls(): array
  {
    $urls = [];
    foreach ($this->uploadedFiles as $file) {
      $urls[] = $file['url'];
    }
    return $urls;
  }

  /**
   * Summary of addFileToUploadedFilesArray
   * @param mixed $file
   * @return void
   */
  protected function addFileToUploadedFilesArray($file)
  {
    // We could probably do more checking here, but for now we just check if it's an array
    if (is_array($file)) {
      $this->uploadedFiles[] = $file;
    }
  }

  /**
   * Summary of restructureFilesArray
   * @param mixed $files
   * @param mixed $tempDirectory
   * @return array<array>
   * 
   * This method will restructure the $_FILES array to make it easier to work with.
   * It will also move the uploaded files to a temporary directory.
   * It is independent of the file field names, so it will work with any form.
   * Example iteration of the returned array:
   * foreach ($files as $file) {
   *   $file['input_name']; // The name of the file input field
   *   $file['name']; // The original name of the file
   *   $file['type']; // The MIME type of the file
   *   $file['tmp_name']; // The path to the temporary file
   *   $file['error']; // The error code
   *   $file['size']; // The file size in bytes
   * }
   */
  protected function restructureFilesArray($files, $tempDirectory): array
  {
    $restructured = [];

    foreach ($files as $fileInputName => $fileArray) {
      if (is_array($fileArray['name'])) {
        $fileCount = count($fileArray['name']);
        for ($i = 0; $i < $fileCount; $i++) {
          $tempFilePath = $tempDirectory . '/' . uniqid('file_upload_');
          if (move_uploaded_file($fileArray['tmp_name'][$i], $tempFilePath)) {
            $restructured[] = [
              'input_name' => $fileInputName,
              'name' => $fileArray['name'][$i],
              'type' => $fileArray['type'][$i],
              'tmp_name' => $tempFilePath,
              'error' => $fileArray['error'][$i],
              'size' => $fileArray['size'][$i],
            ];
          }
        }
      } else {
        $tempFilePath = $tempDirectory . '/' . uniqid('file_upload_');
        if (move_uploaded_file($fileArray['tmp_name'], $tempFilePath)) {
          $restructured[] = [
            'input_name' => $fileInputName,
            'name' => $fileArray['name'],
            'type' => $fileArray['type'],
            'tmp_name' => $tempFilePath,
            'error' => $fileArray['error'],
            'size' => $fileArray['size'],
          ];
        }
      }
    }

    return $restructured;
  }


  /**
   * Summary of restructurePsrFilesArray
   * @param array $files
   * @param array $meta
   * @param string $tempDirectory
   * @return array
   */
  protected function restructurePsrFilesArray(mixed $files, mixed $meta, string $tempDirectory): array
  {
    $restructured = [];

    foreach ($files as $index => $file) {
      // check if $file is an array and handle accordingly
      if (is_array($file)) {
        foreach ($file as $i => $individualFile) {
          // The $individualFile here is an instance of UploadedFileInterface
          $fileMeta = isset($meta[$index]) ? $meta[$index] : null;
          $restructured[] = $this->handlePsrUploadedFile('APIv2', $individualFile, $tempDirectory, $fileMeta);
        }
      } else {
        // The $file here is an instance of UploadedFileInterface
        $fileMeta = is_array($meta) ? $meta : [];
        $restructured[] = $this->handlePsrUploadedFile('APIv2', $file, $tempDirectory, $fileMeta);
      }
    }

    return $restructured;
  }

  /**
   * Summary of handlePsrUploadedFile
   * @param string $fileInputName
   * @param mixed $file
   * @param string $tempDirectory
   * @param mixed $metadata
   * @return array
   */
  private function handlePsrUploadedFile(string $fileInputName, mixed $file, string $tempDirectory, mixed $metadata): array
  {
    $tempFilePath = $tempDirectory . '/' . uniqid('file_upload_');

    // Move the file to the temporary directory
    $file->moveTo($tempFilePath);

    return [
      'input_name' => $fileInputName,
      'name' => $file->getClientFilename(),
      'type' => $file->getClientMediaType(),
      'tmp_name' => $tempFilePath,
      'error' => $file->getError(),
      'size' => $file->getSize(),
      'metadata' => $metadata, // Include the metadata for the file
    ];
  }

  /**
   * Summary of uploadProfilePicture
   * @return bool
   * For profile picture we only accept pictures and only a single file
   * The flow should be as follows:
   * 1) Accept file from user
   * 2) Validate file (size, type, etc.)
   * 3) Perform file transformations (crop, resize, compress, etc.)
   * 4) Store file in database (filename, metadata, file size, type)
   * 
   */
  public function uploadProfilePicture(): bool
  {
    // Check if $this->filesArray is empty, and throw an exception if it is
    if (!is_array($this->filesArray) || empty($this->filesArray)) {
      throw new Exception('No files to upload');
    }

    // Work only with the single file, there is no need to loop over the whole array
    try {
      // Begin a database transaction, so that we can rollback if anything fails
      $this->db->begin_transaction();
      $this->file = $this->filesArray[0];
      $fileType = detectFileExt($this->file['tmp_name']);
      if ($fileType['type'] !== 'image' && $fileType['type'] !== 'video') {
        throw new Exception('Invalid file type, only images and videos are allowed');
      }

      // Calculate the sha256 hash of the file before any transformations
      $fileSha256 = $this->generateFileName(0);
      $this->file['sha256'] = $fileSha256;

      // Check uploads_data table for duplicates of profile pictures;
      if ($this->checkForDuplicates($fileSha256, true)) {
        // If duplicate was detected, our data are already populated with the file info
        // It is safe to return true here, and rollback the transaction
        error_log('Duplicate file');
        $this->db->rollback();
        return true;
      }

      // Validate the file before we proceed
      if (!$this->validateFile()) {
        throw new Exception('File validation failed');
      }
      // Perform image manipulations
      $fileData = $this->processProfileImage($fileType);
      // Rediscover filedata after conversion
      $fileType = detectFileExt($this->file['tmp_name']);

      // We use original file hash as the file name, so that we can detect duplicates later
      $newFileName = $fileSha256 . '.' . $fileType['extension'];
      $newFilePrefix = $this->determinePrefix('profile');
      $newFileSize = filesize($this->file['tmp_name']); // Capture the file size after transformations
      $newFileType = 'profile';

      // Insert the file data into the database
      $insert_id = $this->storeInDatabaseFree(
        $newFileName,
        json_encode($fileData['metadata'] ?? []),
        $newFileSize,
        $newFileType,
        $fileData['dimensions']['width'] ?? 0,
        $fileData['dimensions']['height'] ?? 0,
        $fileData['blurhash'] ?? null
      );

      // Confirm successful insert
      if ($insert_id === false) {
        throw new Exception('Failed to insert into database');
      }

      // Upload the file to S3
      if (!$this->uploadToS3($newFilePrefix . $newFileName)) {
        throw new Exception('Upload to S3 failed');
      }

      // Populate the uploadedFiles array with the file data
      $this->addFileToUploadedFilesArray([
        'input_name' => $this->file['input_name'],
        'name' => $newFileName,
        'url' => $this->generateMediaURL($newFileName, 'profile'), // Construct URL
        'sha256' => $fileSha256,
        'type' => $newFileType,
        'mime' => $fileType['mime'],
        'size' => $newFileSize,
        'dimensions' => $fileData['dimensions'] ?? [],
        'blurhash' => $fileData['blurhash'] ?? '',
      ]);

      // Commit
      $this->db->commit();
    } catch (Exception $e) {
      error_log("Profile picture upload failed: " . $e->getMessage());
      $this->db->rollback();
      return false;
    }
    // Remove temp file if exists
    if (file_exists($this->file['tmp_name'])) {
      unlink($this->file['tmp_name']);
    }

    // If we reached this far, upload was successful
    return true;
  }

  /**
   * Summary of uploadFiles
   * @throws \Exception
   * @return bool
   */
  public function uploadFiles(): bool
  {
    // Check if $this->filesArray is empty, and throw an exception if it is
    if (!is_array($this->filesArray) || empty($this->filesArray)) {
      throw new Exception('No files to upload');
    }

    // Wrap in a try-catch block to catch any exceptions and handle what we can
    // Loop through the files array that was passed in
    foreach ($this->filesArray as $file) {
      error_log('Processing file: ' . print_r($file, true) . PHP_EOL);
      try {
        // Begin a database transaction, so that we can rollback if anything fails
        $this->db->begin_transaction();

        // We set the file property to the current file in the loop
        // so that we can access it in other methods without passing it around
        $this->file = $file;

        // Calculate the sha256 hash of the file before any transformations
        $fileSha256 = $this->generateFileName(0);
        $this->file['sha256'] = $fileSha256;

        // Check uploads_data table for duplicates
        // If the file already exists, we will skip it
        // Populate the uploadedFiles array with the file data
        if (!$this->pro && $this->checkForDuplicates($fileSha256)) {
          throw new Exception('Duplicate file');
        }

        // All initial validations are performed here, e.g., file size, type, etc.
        // This also should validate against the table of known rejected files,
        // or files that were requested to be deleted by the user
        if (!$this->validateFile()) {
          throw new Exception('File validation failed');
        }

        // Identify what file we are dealing with, e.g., image, video, audio
        // Throws an exception if the file type is not supported
        // Example of expected structure:
        // $fileType = [
        //   'type' => 'image',
        //   'extension' => 'jpg',
        //   'mime' => 'image/jpeg',
        // ];
        $fileType = detectFileExt($this->file['tmp_name']);
        if ($fileType['type'] === 'image') {
          if ($this->pro) {
            $fileData = $this->processProUploadImage();
          } else {
            $fileData = $this->processFreeUploadImage();
            // We need to detect the file type again, because it may have changed du to conversion
            $fileType = detectFileExt($this->file['tmp_name']);
          }
        } else {
          $fileData = [];
        }
        // By this time the image has been processed and saved to a temporary location
        // It is now ready to be uploaded to S3 and information about it stored in the database
        if (!$this->pro) {
          // Handle free uploads
          // We use original file hash as the file name, so that we can detect duplicates later
          $newFileName = $fileSha256 . '.' . $fileType['extension'];
          $newFilePrefix = $this->determinePrefix($fileType['type']);
          $newFileSize = filesize($this->file['tmp_name']); // Capture the file size after transformations
          // Make DB compatible file type
          // Accepted values are: 'picture', 'video', 'profile', 'unknown'
          $newFileType = match ($fileType['type']) {
            'image' => 'picture',
            'video' => 'video',
            'audio' => 'video',
            default => 'unknown',
          };

          // Insert the file data into the database but don't commit yet
          $insert_id = $this->storeInDatabaseFree(
            $newFileName,
            json_encode($fileData['metadata'] ?? []),
            $newFileSize,
            $newFileType,
            (int)($fileData['dimensions']['width'] ?? 0),
            (int)($fileData['dimensions']['height'] ?? 0),
            $fileData['blurhash'] ?? null
          );
          if ($insert_id === false) {
            throw new Exception('Failed to insert into database');
          }
        } else {
          // Pro upload uses different file naming scheme and relies on the database ID,
          // hence we need to insert file info with "dummy" name and update it later
          // as part of the database transaction
          // To enhance security and prevent people from "walking" the incrementing IDs
          // and retrieving files in sequence, we will use a hashid of the ID

          // Insert the file data into the database but don't commit yet
          $insert_id = $this->storeInDatabasePro();
          if ($insert_id === false) {
            throw new Exception('Failed to insert into database');
          }

          $newFileName = $this->generateFileName($insert_id) . '.' . $fileType['extension'];
          $newFilePrefix = $this->determinePrefix($fileType['type']);
          $newFileSize = filesize($this->file['tmp_name']); // Capture the file size after transformations
          // Irrelevant for pro uploads for now
          $newFileType = match ($fileType['type']) {
            'image' => 'picture',
            'video' => 'video',
            'audio' => 'video',
            default => 'unknown',
          };

          // Update the file data in the database
          if (!$this->updateDatabasePro(
            $insert_id,
            $newFileName,
            $newFileSize,
            $fileData['dimensions']['width'] ?? 0,
            $fileData['dimensions']['height'] ?? 0,
            $fileData['blurhash'] ?? null,
            $fileType['mime'],
          )) {
            throw new Exception('Failed to update database');
          }
        }
        if (!$this->uploadToS3($newFilePrefix . $newFileName)) {
          throw new Exception('Upload to S3 failed');
        }
        // Populate the uploadedFiles array with the file data
        $this->addFileToUploadedFilesArray([
          'input_name' => $this->file['input_name'],
          'name' => $newFileName,
          'url' => $this->generateMediaURL($newFileName, $fileType['type']), // Construct URL
          'thumbnail' => $this->generateImageThumbnailURL($newFileName, $fileType['type']), // Construct thumbnail URL
          'responsive' => $this->generateResponsiveImagesURL($newFileName, $fileType['type']), // Construct responsive images URLs
          'blurhash' => $fileData['blurhash'] ?? '',
          'sha256' => $this->generateFileName(0), // Reuse method to generate a hash of transformed file
          'type' => $newFileType,
          'mime' => $fileType['mime'],
          'size' => $newFileSize, // Capture the file size after transformations
          'metadata' => $fileData['metadata'] ?? [],
          'dimensions' => $fileData['dimensions'] ?? [],
        ]);
        $this->db->commit();
      } catch (Exception $e) {
        error_log("File loop exception: " . $e->getMessage());
        $this->db->rollback();
        // Since upload to S3 happenese last, we should be safe to do not delete the file from S3
        // unless something goes wrong with DB commit.
        // We want to loop over all files and not stop on the errors
        continue;
      }
    }

    return true;
  }

  /**
   * Summary of uploadFileFromUrl
   * @param string $url
   * @param bool $pfp
   * @throws \Exception
   * @return bool
   * 
   * Thos method will perform the following:
   * 1) HEAD request to get the file size and type
   * 2) Check if the file size is within the limit
   * 3) Check if the file type is supported
   * 4) Download the file to a temporary location
   * 5) Set the file property to the downloaded file
   */
  public function uploadFileFromUrl(string $url, $pfp = false): bool
  {
    global $freeUploadLimit;

    // Get the metadata from the URL
    try {
      $metadata = $this->getUrlMetadata($url);
    } catch (Exception $e) {
      error_log($e->getMessage());
      throw new Exception($e->getMessage());
    }

    // Extract the file type from the MIME type
    list($fileType) = explode("/", $metadata['type'], 2);

    // Ensure the file is of a valid type (image, video, or audio)
    if (!in_array($fileType, ['image', 'video', 'audio'])) {
      throw new Exception('Invalid file type: ' . $fileType);
    }

    // Check the file size against the limit
    if (intval($metadata['size']) > $freeUploadLimit) {
      throw new Exception('File size exceeds the limit of ' . formatSizeUnits($freeUploadLimit));
    }

    // Create a curl instance for the actual download
    $ch = curl_init($url);

    // Fail if the URL is not found or not accessible
    if ($ch === false) {
      throw new Exception('Failed to initialize cURL');
    }

    // Set options for the actual download
    curl_setopt($ch, CURLOPT_NOBODY, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);

    // Create a new file in the system's temp directory
    $tempFile = tempnam(sys_get_temp_dir(), 'url_download');
    if ($tempFile === false) {
      throw new Exception('Failed to create temporary file');
    }
    $fp = fopen($tempFile, 'wb');

    // Check if the file was created successfully
    if ($fp === false) {
      throw new Exception('Failed to open file for writing');
    }

    // Set options to download the file
    curl_setopt($ch, CURLOPT_FILE, $fp);

    // Set option to have a hard limit on how many bytes we will download
    // This should prevent from the potential DOS attack
    curl_setopt($ch, CURLOPT_NOPROGRESS, false);
    curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function ($ch, $downloadSize, $downloaded, $uploadSize, $uploaded) use (&$fileSize, $freeUploadLimit) {
      // Assign the file size to a variable that is accessible outside of this function
      $fileSize = $downloaded;
      // Check if the file size exceeds the limit
      if ($fileSize > $freeUploadLimit) {
        return 1; // Abort the download by returning a non-zero value
      }
      // Carry on with the download
      return 0;
    });

    // Perform the download
    $success = curl_exec($ch);

    // Close the file descriptor
    fclose($fp);

    // Check for any errors during the download
    if ($success === false) {
      // Remove the temporary file
      unlink($tempFile);
      // Log the error
      error_log(curl_error($ch));
      // Check if the error was caused by the file size limit
      if ($fileSize > $freeUploadLimit) {
        throw new Exception('File size exceeds the limit of ' . formatSizeUnits($freeUploadLimit));
      }
      // Throw the error
      throw new Exception('cURL error: ' . curl_error($ch));
    }

    // Close the cURL resource
    curl_close($ch);

    // Set the file property
    $this->filesArray[] = [
      'input_name' => 'url',
      'name' => $metadata['name'],
      'type' => $metadata['type'],
      'tmp_name' => realpath($tempFile),
      'error' => UPLOAD_ERR_OK, // No error
      'size' => filesize($tempFile),
    ];

    // Lastly, trigger the uploadFiles method to process and store the file
    if ($pfp) {
      return $this->uploadProfilePicture();
    } else {
      return $this->uploadFiles(); // URL uploads are always free
    }
  }

  /**
   * Summary of getUrlMetadata
   * @param string $url
   * @throws \Exception
   * @return array
   */
  public function getUrlMetadata(string $url): array
  {
    // Perform check of the URL to avoid common exploits:
    try {
      $sanity = checkUrlSanity($url);
    } catch (Exception $e) {
      error_log($e->getMessage());
      throw new Exception($e->getMessage());
    }

    // Create a curl instance
    $ch = curl_init($url);

    // Fail if the URL is not found or not accessible
    if ($ch === false) {
      throw new Exception('Failed to initialize cURL');
    }

    // Set curl options for a HEAD request
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    // Execute the HEAD request
    $headers = curl_exec($ch);

    // Check if any error occurred
    if (curl_errno($ch)) {
      throw new Exception('Curl error: ' . curl_error($ch));
    }

    // Extract headers from the response
    $headerList = explode("\n", $headers);
    $headerMap = [];
    foreach ($headerList as $header) {
      $parts = explode(": ", $header, 2);
      if (count($parts) == 2) {
        // Convert the header name to lowercase
        $headerName = strtolower($parts[0]);
        $headerMap[$headerName] = trim($parts[1]);
      }
    }

    // Close the cURL resource
    curl_close($ch);

    // Check if Content-Length header is present
    if (!isset($headerMap['content-length'])) {
      throw new Exception('Unable to determine file size');
    }

    // Check if Content-Type header is present
    if (!isset($headerMap['content-type'])) {
      throw new Exception('Unable to determine file type');
    }

    // Prepare result to return
    $result = [
      'name' => basename($url),
      'type' => $headerMap['content-type'],
      'size' => $headerMap['content-length']
    ];

    return $result;
  }

  /**
   * Summary of validateFile
   * @return bool
   */
  protected function validateFile(): bool
  {
    global $freeUploadLimit;
    global $storageLimits;

    // Validate if file upload is OK
    if ($this->file['error'] !== UPLOAD_ERR_OK) {
      error_log('File upload error: ' . $this->file['error']);
      return false;
    }

    // Check if the file size exceeds the upload limit for free users
    if (!$this->pro && $this->file['size'] > $freeUploadLimit) {
      error_log('File size exceeds the limit of ' . formatSizeUnits($freeUploadLimit));
      return false;
    }

    // Check if file has been rejected for free users
    if (!$this->pro && $this->uploadsData->checkRejected($this->file['sha256'])) {
      error_log('File has been flagged as rejected');
      return false;
    }

    // Calculate remaining space and check if file size exceeds the remaining space for pro users
    if ($this->pro) {
      // Calculate and log total used space
      $totalUsed = $this->usersImages->getTotalSize($this->userNpub);
      error_log('Total used: ' . formatSizeUnits($totalUsed));

      // Determine account limit (handle '-1' as unlimited) and log it
      $accountLimit = $storageLimits[$_SESSION['acctlevel']]['limit'] ?? 0;
      $accountLimit = $accountLimit === -1 ? PHP_INT_MAX : $accountLimit;
      error_log('Account limit: ' . formatSizeUnits($accountLimit));

      // Calculate remaining space and log it
      $remainingSpace = $accountLimit - $totalUsed;
      error_log('Remaining space: ' . formatSizeUnits($remainingSpace));

      // Check if the file size exceeds the remaining space and log if it does
      if ($this->file['size'] > $remainingSpace) {
        error_log('File size exceeds the remaining space of ' . formatSizeUnits($remainingSpace));
        return false;
      }
    }

    return true;
  }

  /**
   * Summary of checkForDuplicates
   * Only used for free uploads
   * @param string $filehash
   * @param bool $profile
   * @return bool
   */
  protected function checkForDuplicates(string $filehash, bool $profile = false): bool
  {
    $data = $this->uploadsData->getUploadData($filehash);

    if ($data === false) {
      return false;
    }

    $width = $data['media_width'] ?? 0;
    $height = $data['media_height'] ?? 0;
    $blurhash = $data['blurhash'] ?? "LEHV6nWB2yk8pyo0adR*.7kCMdnj"; // Default blurhash

    if (
      in_array($data['type'], ['picture']) &&
      ($width === 0 || $height === 0 || $blurhash === "LEHV6nWB2yk8pyo0adR*.7kCMdnj")
    ) {
      $img = new ImageProcessor($this->file['tmp_name']);
      $dimensions = $img->getImageDimensions();
      $blurhash = $img->calculateBlurhash();

      $width = $dimensions['width'];
      $height = $dimensions['height'];

      // DEBUG log
      error_log("Updating uploads_data table with width: $width, height: $height, blurhash: $blurhash");

      $this->uploadsData->update(
        $data['id'],
        [
          'media_width' => $width,
          'media_height' => $height,
          'blurhash' => $blurhash
        ]
      );
    }

    try {
      $key = $this->determinePrefix($data['type']) . $data['filename'];
      $fileS3Metadata = $this->s3Service->getObjectMetadataFromS3($key);

      if ($fileS3Metadata === false) {
        throw new Exception('Failed to get S3 metadata');
      }
    } catch (Exception $e) {
      error_log("Failed to get S3 metadata: " . $e->getMessage());
      return false;
    }

    $fileData = [
      'input_name' => $this->file['input_name'],
      'name' => $data['filename'],
      'sha256' => $filehash,
      'type' => $data['type'],
      'mime' => $fileS3Metadata->get('ContentType'),
      'size' => $data['file_size'],
      'blurhash' => $blurhash,
      'dimensions' => ['width' => $width, 'height' => $height],
    ];

    if ($profile) {
      // If we have a duplicate in non-profile uploads, we should not count it as a duplicate
      if ($data['type'] !== 'profile') {
        return false;
      }

      // check the upload date against the filter
      // to allow old uploads to be re-uploaded and optimized
      $date = '2023-07-18';
      if ($date !== null && $data['upload_date'] !== null) {
        $uploadDate = DateTime::createFromFormat('Y-m-d', $data['upload_date']);
        $filterDate = DateTime::createFromFormat('Y-m-d', $date);
        if ($uploadDate < $filterDate) {
          return false;
        }
      }

      $fileData['url'] = $this->generateMediaURL($data['filename'], 'profile');
    } else {
      // if we have a duplicate in profile uploads, we should not count it as a duplicate
      if ($data['type'] === 'profile') {
        return false;
      }
      $fileData['url'] = $this->generateMediaURL($data['filename'], $data['type']);
      $fileData['thumbnail'] = $this->generateImageThumbnailURL($data['filename'], $data['type']);
      $fileData['responsive'] = $this->generateResponsiveImagesURL($data['filename'], $data['type']);
      try {
        $fileData['metadata'] = json_decode($data['metadata'], true);
      } catch (Exception $e) {
        error_log("Failed to decode metadata: " . $e->getMessage());
        $fileData['metadata'] = [];
      }
    }

    $this->addFileToUploadedFilesArray($fileData);

    return true;
  }

  /**
   * Summary of processFreeUploadImage
   * @return array
   * We resize and compress free account images, strip metadata, and optimize
   */
  protected function processFreeUploadImage(): array
  {
    $img = new ImageProcessor($this->file['tmp_name']);
    $img->convertToJpeg() // Convert to JPEG for images that are not visually affected by the conversion
      ->fixImageOrientation()
      ->resizeImage(1920, 1920) // Resize to 1920x1920 (HD)
      ->reduceQuality(60) // 60 should be a good balance between quality and size
      ->stripImageMetadata()
      ->save();
    $img->optimiseImage(); // Optimise the image, can take upto 60 seconds
    return [
      'metadata' => $img->getImageMetadata(),
      'dimensions' => $img->getImageDimensions(),
      'blurhash' => $img->calculateBlurhash(),
    ];
  }

  /**
   * Summary of processProUploadImage
   * @return array
   * We do not alter Pro account images in any way, except for stripping metadata and optimizing
   */
  protected function processProUploadImage(): array
  {
    $img = new ImageProcessor($this->file['tmp_name']);
    $img->fixImageOrientation()
      ->stripImageMetadata()
      ->save();
    $img->optimiseImage(); // Optimise the image, can take upto 60 seconds
    return [
      'metadata' => $img->getImageMetadata(),
      'dimensions' => $img->getImageDimensions(),
      'blurhash' => $img->calculateBlurhash(),
    ];
  }

  /**
   * Summary of processProfileImage
   * @param array $fileType
   * @return array
   * We resize and crop profile pictures, strip metadata, and optimize
   */
  protected function processProfileImage($fileType): array
  {
    // Determine if submitted file is animated image or video
    if (
      ($fileType['type'] === 'image' && in_array($fileType['extension'], ['gif', 'apng'])) ||
      $fileType['type'] === 'video'
    ) {
      // Process animated image or video with GifConverter class
      $tmp_gif = $this->gifConverter->convertToGif($this->file['tmp_name']);
      // Unlink old file.
      if (file_exists($this->file['tmp_name'])) {
        unlink($this->file['tmp_name']);
      }
      $this->file['tmp_name'] = $tmp_gif;
    } else {
      // Process static image with ImageProcessor class
      $img = new ImageProcessor($this->file['tmp_name']);
      $img->fixImageOrientation()
        ->cropSquare()
        ->resizeImage(256, 256) // Resize to 256x256
        ->reduceQuality(60); // 60 should be a good balance between quality and size
    }

    // Common image processing steps
    $img = $img ?? new ImageProcessor($this->file['tmp_name']);
    $img->stripImageMetadata()
      ->save();
    $img->optimiseImage();

    return [
      'metadata' => $img->getImageMetadata(),
      'dimensions' => $img->getImageDimensions(),
      'blurhash' => $img->calculateBlurhash(),
    ];
  }


  /**
   * Summary of generateFileName
   * @param int $id
   * @return string
   * Method to generate a new file name from the database ID or the file hash
   * Also used to generate a hash of the transformed file
   */
  protected function generateFileName(int $id = 0): string
  {
    $hashids = new Hashids($_SERVER['HASHIDS_SALT']); // The salt must be the same to do not have collisions
    // This method should be called before any other file transformations are performed.
    return $id === 0 ? hash_file('sha256', realpath($this->file['tmp_name'])) : $hashids->encode($id);
  }

  /**
   * Summary of determinePrefix
   * @param string $type
   * @return string
   */
  protected function determinePrefix(string $type = 'unknown'): string
  {
    $mappedType = $this->typeMap[$type] ?? $type;
    $mappedType = $this->apiClient ? $this->apiClient . '_' . $mappedType : $mappedType;
    try {
      return SiteConfig::getPath(($this->pro ? 'professional_account_' : '') . $mappedType);
    } catch (Exception $e) {
      error_log($e->getMessage() . PHP_EOL . $e->getTraceAsString());
      return SiteConfig::getPath('unknown');
    }
  }


  /**
   * Summary of generateImageThumbnailURL
   * @param string $fileName
   * @param string $type
   * @return string
   */
  protected function generateImageThumbnailURL(string $fileName, string $type): string
  {
    $mappedType = $this->typeMap[$type] ?? $type;
    $mappedType = $this->apiClient ? $this->apiClient . '_' . $mappedType : $mappedType;
    try {
      return SiteConfig::getThumbnailUrl(($this->pro ? 'professional_account_' : '') . $mappedType) . $fileName;
    } catch (Exception $e) {
      error_log($e->getMessage() . PHP_EOL . $e->getTraceAsString());
      return SiteConfig::getThumbnailUrl('unknown') . $fileName;
    }
  }

  /**
   * Summary of generateResponsiveImagesURL
   * @param string $fileName
   * @param string $type
   * @return array
   */
  protected function generateResponsiveImagesURL(string $fileName, string $type): array
  {
    $mappedType = $this->typeMap[$type] ?? $type;
    $mappedType = $this->apiClient ? $this->apiClient . '_' . $mappedType : $mappedType;
    $resolutions = ['240p', '360p', '480p', '720p', '1080p'];
    $urls = [];

    foreach ($resolutions as $resolution) {
      try {
        $urls[$resolution] = SiteConfig::getResponsiveUrl(($this->pro ? 'professional_account_' : '') . $mappedType, $resolution) . $fileName;
      } catch (Exception $e) {
        error_log($e->getMessage() . PHP_EOL . $e->getTraceAsString());
        $urls[$resolution] = SiteConfig::getResponsiveUrl('unknown', $resolution) . $fileName;
      }
    }

    return $urls;
  }

  /**
   * Summary of generateMediaURL
   * @param string $fileName
   * @param string $type
   * @return string
   */
  protected function generateMediaURL(string $fileName, string $type): string
  {
    $mappedType = $this->typeMap[$type] ?? $type;
    $mappedType = $this->apiClient ? $this->apiClient . '_' . $mappedType : $mappedType;
    try {
      return SiteConfig::getFullyQualifiedUrl(($this->pro ? 'professional_account_' : '') . $mappedType) . $fileName;
    } catch (Exception $e) {
      error_log($e->getMessage() . PHP_EOL . $e->getTraceAsString());
      return SiteConfig::getFullyQualifiedUrl('unknown') . $fileName;
    }
  }

  /**
   * Summary of storeInDatabasePro
   * @return int|bool
   */
  protected function storeInDatabasePro(): int | bool
  {
    // Insert the file data into the database but don't commit yet
    try {
      $insert_id = $this->usersImages->insert([
        'usernpub' => $this->userNpub,
        'image' => uniqid('tmp_'), // Temporary name, will be updated later
        // Private by default
        'flag' => 0, // 0 - private, 1 - public
        'folder_id' => null, // null - root folder
      ], false); // false parameter to not commit yet

      return $insert_id;
    } catch (Exception $e) {
      error_log($e->getMessage());
      return false;
    }
  }

  /**
   * Summary of storeInDatabaseFree
   * @param string $filename
   * @param string $metadata
   * @param int $file_size
   * @param string $type
   * @param int $media_width
   * @param int $media_height
   * @param mixed $blurhash
   * @return int|bool
   */
  protected function storeInDatabaseFree(
    string $filename,
    string $metadata,
    int $file_size,
    string $type = 'unknown',
    int $media_width = 0,
    int $media_height = 0,
    ?string $blurhash = null,
  ): int | bool {
    // Insert the file data into the database but don't commit yet
    try {
      $insert_id = $this->uploadsData->insert([
        'filename' => $filename,
        'metadata' => $metadata, // metadata to be updated later
        'file_size' => $file_size,
        'media_width' => $media_width,
        'media_height' => $media_height,
        'blurhash' => $blurhash,
        'type' => $type, // 'picture', 'video', 'unknown', 'profile'
        // All new free uploads are pending approval by default
        // Rejected files are not stored in the database
        'approval_status' => 'pending', // 'approved', 'pending', 'rejected', 'adult'
      ], false); // false parameter to do not commit yet

      return $insert_id;
    } catch (Exception $e) {
      error_log($e->getMessage());
      return false;
    }
  }

  /**
   * Summary of updateDatabasePro
   * @param int $id
   * @param string $newName
   * @param int $fileSize
   * @param int $mediaWidth
   * @param int $mediaHeight
   * @param mixed $blurhash
   * @param string $fileMimeType
   * @param mixed $folder_name
   * @return bool
   */
  protected function updateDatabasePro(
    int $id,
    string $newName,
    int $fileSize,
    int $mediaWidth,
    int $mediaHeight,
    ?string $blurhash,
    string $fileMimeType,
    ?string $folder_name = null
  ): bool {
    // Update the database with the new file name and size
    $folder_id = null;
    if ($folder_name !== null) {
      $folder_id = $this->usersImagesFolders->findFolderByNameOrCreate($folder_name);
    }
    try {
      $this->usersImages->update($id, [
        'image' => $newName,
        'file_size' => $fileSize,
        'folder_id' => $folder_id,
        'media_width' => $mediaWidth,
        'media_height' => $mediaHeight,
        'blurhash' => $blurhash,
        'mime_type' => $fileMimeType,
      ]);
    } catch (Exception $e) {
      error_log($e->getMessage());
      return false;
    }
    return true;
  }

  /**
   * Summary of uploadToS3
   * @param string $objectName
   * @return bool
   * Method that will upload the file to S3
   */
  protected function uploadToS3(string $objectName): bool
  {
    try {
      $this->s3Service->uploadToS3($this->file['tmp_name'], $objectName);
    } catch (Exception $e) {
      error_log($e->getMessage());
      return false;
    }
    return true;
  }

  /**
   * @param mixed $apiClient 
   * @return self
   */
  public function setApiClient($apiClient): self
  {
    $this->apiClient = $apiClient;
    return $this;
  }

  /**
   * @return mixed
   */
  public function getApiClient()
  {
    return $this->apiClient;
  }
}
