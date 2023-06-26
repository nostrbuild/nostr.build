<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/imageproc.class.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/utils.funcs.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/S3Service.class.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/db/UploadsData.class.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/libs/db/UsersImages.class.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/config.php"; // Size limits

// Globals
global $link;

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

class MultimediaUpload
{
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
   *      ...
   *     ]
   */
  protected $filesArray; // The $_FILES reconstructed array
  protected $uploadedFiles = []; // Array of uploaded files with URLs and other info
  protected $file; // Used for temporary storage of the current file in the loop
  protected $uploadsData; // Used for free uploads; instance of the UploadsData class
  protected $usersImages; // Used for authenticated uploads; instance of the UsersImages class
  protected $s3Service; // Instance of the S3Service class
  protected $userNpub; // Used for authenticated uploads.

  /**
   * Summary of __construct
   * @param mysqli $db
   * @param S3Service $s3Service
   */
  public function __construct(mysqli $db, S3Service $s3Service, string $userNpub = '')
  {
    $this->db = $db;
    $this->s3Service = $s3Service;
    $this->userNpub = $userNpub;
    $this->uploadsData = new UploadsData($db);
    $this->usersImages = new UsersImages($db);
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

  public function setFiles($files, $tempDirectory = null)
  {
    // We make temp directory optional, and use the system's temp directory by default
    if ($tempDirectory === null) {
      $tempDirectory = sys_get_temp_dir();
    }

    $this->filesArray = $this->restructureFilesArray($files, $tempDirectory);
  }

  public function getUploadedFiles(): array
  {
    return $this->uploadedFiles;
  }

  public function getFileUrls(): array
  {
    $urls = [];
    foreach ($this->uploadedFiles as $file) {
      $urls[] = $file['url'];
    }
    return $urls;
  }

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
  protected function restructureFilesArray($files, $tempDirectory)
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

  public function uploadProfilePicture(): bool
  {
    // TODO: Implement method for uploading profile pictures
    // We should reuse main upload method, but with a few changes
    return false;
  }

  public function uploadFiles(bool $pro = false): bool
  {
    // Check if $this->filesArray is empty, and throw an exception if it is
    if (!is_array($this->filesArray) || empty($this->filesArray)) {
      throw new Exception('No files to upload');
    }

    // Wrap in a try-catch block to catch any exceptions and handle what we can
    // Loop through the files array that was passed in
    foreach ($this->filesArray as $file) {
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
        if ($this->checkForDuplicates($fileSha256)) {
          throw new Exception('Duplicate file');
        }

        // All initial validations are performed here, e.g., file size, type, etc.
        // This also should validate against the table of known rejected files,
        // or files that were requested to be deleted by the user
        if (!$this->validateFile($pro)) {
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
          if ($pro) {
            $fileData = $this->processProUploadImage();
          } else {
            $fileData = $this->processFreeUploadImage();
          }
        } else {
          $fileData = [];
        }
        // By this time the image has been processed and saved to a temporary location
        // It is now ready to be uploaded to S3 and information about it stored in the database
        if (!$pro) {
          // Handle free uploads
          // We use original file hash as the file name, so that we can detect duplicates later
          $newFileName = $fileSha256 . '.' . $fileType['extension'];
          $newFilePrefix = $this->determinePrefix($fileType['type'], $pro);
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
            $newFileType
          );
          if ($insert_id === false) {
            throw new Exception('Failed to insert into database');
          }
          if (!$this->uploadToS3($newFilePrefix . $newFileName)) {
            throw new Exception('Upload to S3 failed');
          }
        } else {
          // TODO: Implement pro uploads
        }
        // Populate the uploadedFiles array with the file data
        $this->addFileToUploadedFilesArray([
          'input_name' => $this->file['input_name'],
          'name' => $newFileName,
          'url' => $this->generateMediaURL($newFileName, $fileType['type'], $pro), // Construct URL
          'thumbnail' => $this->generateImageThumbnailURL($newFileName, $fileType['type'], $pro), // Construct thumbnail URL
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
        $this->db->rollback();
        // We want to loop over all files and not stop on the errors
        continue;
      }
    }

    return true;
  }

  /**
   * Summary of uploadFileFromUrl
   * @param string $url
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
  public function uploadFileFromUrl(string $url): bool
  {
    global $freeUploadLimit;
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

    // Check if Content-Length header is present
    if (!isset($headerMap['content-length'])) {
      throw new Exception('Unable to determine file size');
    }

    // Check the file size against the limit
    // This is not a foolproof method, but it should be good enough at this point
    // You must also check the file size after the download is complete
    if (intval($headerMap['content-length']) > $freeUploadLimit) {
      throw new Exception('File size exceeds the limit of ' . formatSizeUnits($freeUploadLimit));
    }

    // Check if Content-Type header is present
    if (!isset($headerMap['content-type'])) {
      throw new Exception('Unable to determine file type');
    }

    // Extract file type from the MIME type
    list($fileType) = explode("/", $headerMap['content-type'], 2);

    // Ensure the file is of a valid type (image, video, or audio)
    if (!in_array($fileType, ['image', 'video', 'audio'])) {
      throw new Exception('Invalid file type: ' . $fileType);
    }

    // Re-initialize curl for the actual download
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
      // Throw any other errors
      throw new Exception('cURL error: ' . curl_error($ch));
    }

    // Close the cURL resource
    curl_close($ch);

    // Set the file property
    $this->filesArray[] = [
      'input_name' => 'url',
      'name' => basename($url),
      'type' => $headerMap['content-type'],
      'tmp_name' => realpath($tempFile),
      'error' => UPLOAD_ERR_OK, // No error
      'size' => filesize($tempFile),
    ];
    // Lastly, trigger the uploadFiles method to process and store the file
    return $this->uploadFiles(false); // URL uploads are always free
  }

  protected function validateFile(bool $pro): bool
  {
    global $freeUploadLimit;
    // Perform size validation, 15MB for free users, remaining space for pro users
    if ($this->file['error'] == UPLOAD_ERR_OK) {
      if ($this->file['size'] > $freeUploadLimit && !$pro) {
        return false;
      } elseif (!$pro) {
        // Check rejection status
        if ($this->uploadsData->checkRejected($this->file['sha256'])) {
          return false;
        }
      }
    }
    return true;
  }

  protected function checkForDuplicates(string $filehash): bool
  {
    // Check if the file already exists in the database
    $data = $this->uploadsData->getUploadData($filehash);
    if ($data !== false) {
      $this->addFileToUploadedFilesArray([
        'input_name' => $this->file['input_name'],
        'name' => $data['filename'],
        'url' => $this->generateMediaURL($data['filename'], $data['type'], false), // Construct URL
        'thumbnail' => $this->generateImageThumbnailURL($data['filename'], $data['type'], false), // Construct thumbnail URL
        'blurhash' => '', // We do not store blurhash in the database, should we?
        'sha256' => $filehash,
        'type' => $data['type'],
        'mime' => $this->file['type'],
        'size' => $data['file_size'],
        'metadata' => $data['metadata'],
        'dimensions' => [], // We do not store image dimensions in the database, should we?
      ]);
      return true;
    }
    return false;
  }

  /**
   * Summary of processFreeUploadImage
   * @return array
   * We resize and compress free account images, strip metadata, and optimize
   */
  protected function processFreeUploadImage(): array
  {
    $img = new ImageProcessor($this->file['tmp_name']);
    $img->fixImageOrientation()
      ->resizeImage(1920, 1920) // Resize to 1920x1920 (HD)
      ->reduceQuality(75) // 75 should be a good balance between quality and size
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
   * @return array
   * We resize and crop profile pictures, strip metadata, and optimize
   */
  protected function processProfileImage(): array
  {
    $img = new ImageProcessor($this->file['tmp_name']);
    $img->fixImageOrientation()
      ->cropSquare()
      ->resizeImage(256, 256) // Resize to 256x256
      ->reduceQuality(75) // 75 should be a good balance between quality and size
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
   * Summary of generateFileName
   * @param int $id
   * @return string
   * Method to generate a new file name from the database ID or the file hash
   * Also used to generate a hash of the transformed file
   */
  protected function generateFileName(int $id = 0): string
  {
    // This method should be called before any other file transformations are performed.
    return $id === 0 ? hash_file('sha256', realpath($this->file['tmp_name'])) : 'nb' . $id;
  }

  protected function determinePrefix(string $type = 'unknown', bool $pro): string
  {
    if ($pro) {
      // Pro accounts store files under the same prefix, regardless of the file type
      return 'p/';
    } elseif ($type === 'video' || $type === 'audio') {
      // Video and audio files are stored under the same prefix
      return 'av/';
    } elseif ($type === 'profile') {
      // Profile pictures are stored under the same prefix, no video or audio allowed
      return 'i/p/';
    } else {
      // Images are stored under the same prefix
      // It's default to catch 'picture' and 'unknown' types
      return 'i/';
    }
  }

  protected function generateImageThumbnailURL(string $fileName, string $type, bool $pro = false): string
  {

    $scheme = $_SERVER['REQUEST_SCHEME'];
    $host = $_SERVER['HTTP_HOST'];
    // We only support thumbnailing of images and profile pictures
    $path = match ($type) {
      'image' => $pro ? 'thumbnail/p/' : 'thumbnail/i/',
      'profile' => 'thumbnail/i/p/',
      default => $this->determinePrefix($type, $pro),
    };
    // Assemble the URL and return it
    return $scheme . '://' . $host . '/' . $path . $fileName;
  }

  protected function generateMediaURL(string $fileName, string $type, bool $pro = false): string
  {
    $scheme = $_SERVER['REQUEST_SCHEME'];
    $host = $_SERVER['HTTP_HOST'];
    // We only support thumbnailing of images and profile pictures
    $path = match ($type) {
      'profile' => 'i/p/',
      default => $this->determinePrefix($type, $pro),
    };
    // Assemble the URL and return it
    return $scheme . '://' . $host . '/' . $path . $fileName;
  }

  protected function storeInDatabasePro(): int | bool
  {
    // Insert the file data into the database but don't commit yet
    try {
      $insert_id = $this->usersImages->insert([
        'usernpub' => $this->userNpub,
        'image' => uniqid('tmp_'), // Temporary name, will be updated later
        // Private by default
        'flag' => 0, // 0 - private, 1 - public
        'file_size' => $this->file['size'],
      ], false); // false parameter to not commit yet

      return $insert_id;
    } catch (Exception $e) {
      error_log($e->getMessage());
      return false;
    }
  }

  protected function storeInDatabaseFree(
    string $filename,
    string $metadata,
    int $file_size,
    string $type = 'unknown'
  ): int | bool {
    // Insert the file data into the database but don't commit yet
    try {
      $insert_id = $this->uploadsData->insert([
        'filename' => $filename,
        'metadata' => $metadata, // metadata to be updated later
        'file_size' => $file_size,
        'type' => $type, // 'picture', 'video', 'unknown', 'profile'
        // All new free apploads are pending approval by default
        // Rejected files are not stored in the database
        'approval_status' => 'pending', // 'approved', 'pending', 'rejected', 'adult'
      ], false); // false parameter to not commit yet

      return $insert_id;
    } catch (Exception $e) {
      error_log($e->getMessage());
      return false;
    }
  }

  protected function updateDatabasePro(int $id, string $newName): bool
  {
    // Update the database with the new file name and size
    try {
      $this->usersImages->update($id, [
        'image' => $newName,
        'size' => $this->file['size'], // Capture the file size after transformations
      ]);
    } catch (Exception $e) {
      error_log($e->getMessage());
      return false;
    }
    return true;
  }

  // Method that will upload the file to S3
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
}
