<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/S3Service.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/utils.funcs.php';

/**
 *  Class to perform image scan using PhotoDNA to reject and report CSAM images
 * Response codes:
 * 3000: OK
 * 3002: Invalid or missing request parameter(s)
 * 3004: Unknown scenario or unhandled error occurred while processing request
 * 3206: The given file could not be verified as an image
 * 3208: Image size in pixels is not within allowed range (minimum size is 160x160 pixels; maximum size is 4MB)
 * 
 * Request:
 * {
 *  "DataRepresentation": "URL",
 *  "Value": "https://example.com/image.jpg"
 * }
 * 
 * Direct file upload supported mime types:
 * Content-Type: image/gif
 * Content-Type: image/jpeg
 * Content-Type: image/png
 * Content-Type: image/bmp
 * Content-Type: image/tiff 
 * 
 * Returned JSON:
 * {
 *   "Status": {
 *       "Code": 3000,
 *       "Description": "OK",
 *       "Exception": null
 *   },
 *   "TrackingId": "1_photodna_a0e3d02b-1a0a-4b38-827f-764acd288c25",
 *   "ContentId": "TestId",
 *   "IsMatch": true,
 *   "MatchDetails": {
 *       "AdvancedInfo": [],
 *       "MatchFlags": [{
 *           "AdvancedInfo": [{
 *               "Key": "MatchId",
 *               "Value": "104149"
 *           }],
 *           "Source": "Test"
 *       }]
 *   },
 *   "EvaluateResponse": null
 * }
 */

class PhotoDNA
{
  private $uploaderNpub;
  private $fileSha256;
  private $filePath;
  private $fileName;
  private $db;
  private $csamReportingConfig;
  private $api_url;
  private $api_key;

  // Results
  private $csamMatchResult;

  public function __construct(string $uploaderNpub, string $fileSha256, string $filePath)
  {
    global $link;
    global $csamReportingConfig;
    global $photoDNAConfig;
    $this->db = $link;
    $this->csamReportingConfig = $csamReportingConfig;
    $this->api_url = $photoDNAConfig['api_url'];
    $this->api_key = $photoDNAConfig['api_key'];
    $this->uploaderNpub = $uploaderNpub;
    $this->fileSha256 = $fileSha256;
    $this->filePath = $filePath;
    // Derive filename based on sha256 and mime type appropriate extension
    try {
      $fileDetect = detectFileExt($this->filePath);
      $this->fileName = "{$this->fileSha256}.{$fileDetect['extension']}";
    } catch (Exception $e) {
      error_log("Error occurred while deriving filename: " . $e->getMessage());
      // Assign a generic file extension
      $this->fileName = "{$this->fileSha256}.bin";
    }
  }

  // Returns true if image is a match for CSAM
  public function scan(): bool
  {
    $tries = 0;
    for ($i = 0; $i < 3; $i++) {
      try {
        $this->csamMatchResult = $this->apiRequestMatch();

        // Return true if Status code is 3000 and IsMatch is true
        $isCSAMMatch = $this->csamMatchResult['Status']['Code'] === 3000 && $this->csamMatchResult['IsMatch'];
        // Throw if Status code is not 3000
        if ($this->csamMatchResult['Status']['Code'] === 3004) {
          error_log("Error occurred while scanning image with PhotoDNA: " . $this->csamMatchResult['Status']['Description']);
          throw new Exception("Error occurred while scanning image with PhotoDNA", 3004);
        }
        return $isCSAMMatch;
      } catch (Exception $e) {
        error_log("Error occurred while scanning image with PhotoDNA: " . $e->getMessage());
        $tries++;
        // Perform exponential backoff
        usleep(500 ** $tries);
      }
    }
    // All failed, throw an exception
    throw new Exception("Error occurred while scanning image with PhotoDNA", 3004);
  }

  public function apiRequestMatch(): mixed
  {
    $curl = curl_init($this->api_url);
    $content_type = null;
    $content_length = 0;

    // Content Type
    $content_type = mime_content_type(realpath($this->filePath));
    $file_size = filesize(realpath($this->filePath));
    // Throw if file mime is not image/*
    if (!preg_match('/^image\//', $content_type)) {
      error_log("The given file could not be verified as an image");
      throw new Exception("The given file could not be verified as an image", 3206);
    }
    // If mime type is not one of the supported, convert to JPEG
    if (
      !in_array($content_type, ['image/gif', 'image/jpeg', 'image/png', 'image/bmp', 'image/tiff']) ||
      $file_size > 4 * 1024 * 1024
    ) {
      error_log("Image size in pixels is not within allowed range (minimum size is 160x160 pixels; maximum size is 4MB)");
      $content_type = 'image/jpeg';
      $request_body = $this->convertToJpeg($this->filePath);
      // Content Length
      $content_length = strlen($request_body);
    } else {
      $request_body = file_get_contents($this->filePath);
      // Content Length
      $content_length = filesize($this->filePath);
    }

    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    // Request headers
    $headers = array(
      'Content-Type: ' . $content_type,
      'Content-Length: ' . $content_length,
      'Cache-Control: no-cache',
      'Ocp-Apim-Subscription-Key: ' . $this->api_key,
    );
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $request_body);
    // Set timeout to 20 seconds
    curl_setopt($curl, CURLOPT_TIMEOUT, 20);

    $resp = curl_exec($curl);
    $status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    // Close request
    $curl = null;
    // Throw if status code is not 200
    if ($status_code !== 200) {
      error_log("Error occurred while scanning image with PhotoDNA: " . $status_code);
      throw new Exception("Error occurred while scanning image with PhotoDNA", 3004);
    }

    // Check for errors
    if ($resp === false) {
      error_log("Error occurred while scanning image with PhotoDNA: " . $curl_error);
      throw new Exception("Error occurred while scanning image with PhotoDNA", 3004);
    }
    $json_resp = json_decode($resp, true, 512, JSON_THROW_ON_ERROR);
    return $json_resp;
  }

  // This method uploads the image to the CSAM evidence bucket
  // and retained for the required period of time
  private function uploadCSAMEvidence(array $evidenceData): void
  {
    $evidenceLogKey = "{$this->fileSha256}/uploads_log.json";
    // Store the logs
    $resLogStore = storeJSONObjectToR2Bucket(
      object: $evidenceData,
      destinationKey: $evidenceLogKey,
      destinationBucket: $this->csamReportingConfig['r2EvidenceBucket'],
      endPoint: $this->csamReportingConfig['r2EndPoint'],
      accessKey: $this->csamReportingConfig['r2AccessKey'],
      secretKey: $this->csamReportingConfig['r2SecretKey'],
    );

    // Store the file
    $evidenceFileKey = "{$this->fileSha256}/{$this->fileName}";
    $resFileStore = storeToR2Bucket(
      sourceFilePath: $this->filePath,
      destinationKey: $evidenceFileKey,
      destinationBucket: $this->csamReportingConfig['r2EvidenceBucket'],
      endPoint: $this->csamReportingConfig['r2EndPoint'],
      accessKey: $this->csamReportingConfig['r2AccessKey'],
      secretKey: $this->csamReportingConfig['r2SecretKey'],
    );

    // Check if we have stored logs and file
    if (($resLogStore === false && !empty($logsJSON)) || $resFileStore === false) {
      // Throw an exception if we failed to store logs or file
      throw new Exception("Failed to store logs or file in CSAM evidence bucket", 3004);
    }
  }

  private function updateCSAMReportTable(array $evidenceData): void
  {
    $logsJSON = [
      'evidenceData' => $evidenceData,
      'csamMatchResult' => $this->csamMatchResult,  // The result of the PhotoDNA API match scan
    ];
    // Perform storage of info about the case in DB
    $stmt = $this->db->prepare("INSERT INTO identified_csam_cases (identified_by_npub, evidence_location_url, file_sha256_hash, logs) VALUES (?, ?, ?, ?)");
    // Prepare bind parameters
    $evidenceReportingNpub = 'PhotoDNA API Match';
    $evidenceLocationURL = "{$this->csamReportingConfig['r2EndPoint']}/{$this->csamReportingConfig['r2EvidenceBucket']}/{$this->fileSha256}/";
    $evidenceFileSha256Hash = $this->fileSha256;
    $evidenceJSONLogs = json_encode($logsJSON);
    // Bind parameters
    $stmt->bind_param("ssss", $evidenceReportingNpub, $evidenceLocationURL, $evidenceFileSha256Hash, $evidenceJSONLogs);
    // Execute the statement
    $stmt->execute();
    // Close the statement
    $stmt->close();
    $this->db->commit();
    error_log("CSAM report table updated");
  }

  private function blackListUser(array $evidenceData): void
  {
    // Perform blacklisting of the user
    $stmt = $this->db->prepare("INSERT INTO blacklist (npub, ip, user_agent, reason) VALUES (?, ?, ?, ?)");
    $ip = $evidenceData['ReporteeIPAddress'];
    $ua = $evidenceData['AdditionalMetadata']['additionalInfo']['userAgent'] ?? '';
    $npub = $evidenceData['ReporteeName'] ?? $this->uploaderNpub;
    $blockReason = 'PhotoDNA CSAM Match API Match';
    // Insert the row into the 'blacklist' table
    $stmt->bind_param("ssss", $npub, $ip, $ua, $blockReason);
    $stmt->execute();
    $stmt->close();
    $this->db->commit();
    error_log("User blacklisted: $npub");
  }

  private function addFileToRejectedTable(): void
  {
    $filename = $this->fileName;
    $type = 'picture';
    // Add the file to the rejected table
    $stmt = $this->db->prepare("INSERT INTO rejected_files (filename, type) VALUES (?, ?)");
    $stmt->bind_param("ss", $filename, $type);
    $stmt->execute();
    $stmt->close();
  }

  // This method only registers the evidence, without submitting it to NCMEC
  // The submission is done by human moderators after reviewing the evidence
  private function createCSAMEvidenceReport(bool $testSubmission = true): mixed
  {
    /**
     * Parameters Name	Description	Optional
     * OrgName	This is the name of your organization	No
     * ReporterName	Name of the Reporter	No
     * ReporterEmail	Email address of reporter	No
     * IncidentTime	The time the incident occurred and the IP address was recorded.	No
     * ReporteeName	Name of the person/organization who is being reported	No
     * ReporteeIPAddress	The IP address from which the incident occurred	No
     * ViolationContentCollection	 The actual information of the content being reported It is a collection of the following data for each file:
     *  Name: The filename of the image that is being reported
     *  Value: The base-64 encoded value of the content being reported
     *  Location (optional): The GPS location, the content is marked with
     *  UploadIpAddress (optional): This represents the upload IP address of the file and when it occurred.
     *  UploadDateTime (optional): The time the upload occurred and when the IP address was recorded.
     *  AdditionalMetadata (optional): This is collection of KeyValue pairs to specify additional options. Currently available options:
     *   viewedByEsp
     *   publiclyAvailable
     *   additionalInfo
     * AdditionalMetadata	This is collection of KeyValue pairs to specify additional options. Currently available options:
     *  IsTest - passing a value of 'true' will force the transaction to hit the NCMEC test end point.
     */
    $utcISODate = date('Y-m-d\TH:i:s\Z');
    $userUploadInfoOtherInfo = json_decode($_SERVER['CLIENT_REQUEST_INFO'], true, 512, JSON_THROW_ON_ERROR);
    $fileGPSLocation = get_image_location($this->filePath);
    $fileGPSLocation = $fileGPSLocation ? json_encode($fileGPSLocation) : null;


    $IncidentTime = $utcISODate;
    $ReporteeName = empty($this->uploaderNpub) ? 'Unknown' : $this->uploaderNpub;
    $ReporteeIPAddress = $userUploadInfoOtherInfo['realIp'];
    $ViolationContentCollection = [];
    $ViolationContentCollection['Name'] = ''; // The filename of the image that is being reported
    $ViolationContentCollection['Value'] = ''; // The base-64 encoded value of the content being reported
    $ViolationContentCollection['Location'] = $fileGPSLocation;
    $ViolationContentCollection['UploadIpAddress'] = $userUploadInfoOtherInfo['realIp'];
    $ViolationContentCollection['UploadDateTime'] = $utcISODate;
    $ViolationContentCollection['AdditionalMetadata']['publiclyAvailable'] = false;
    $ViolationContentCollection['AdditionalMetadata']['additionalInfo'] = json_decode($_SERVER['CLIENT_REQUEST_INFO'], true, 512, JSON_THROW_ON_ERROR);

    // Collect all the data into one associative array
    $evidenceData = [
      'OrgName' => '', // To be provided on submission
      'ReporterName' => '', // To be provided on submission
      'ReporterEmail' => '', // To be provided on submission
      'IncidentTime' => $IncidentTime,
      'ReporteeName' => $ReporteeName,
      'ReporteeIPAddress' => $ReporteeIPAddress,
      'ViolationContentCollection' => $ViolationContentCollection,
      'AdditionalMetadata' => [
        'IsTest' => $testSubmission,
      ],
    ];

    return $evidenceData;
  }

  private function convertToJpeg($img_file_path)
  {
    // Use Imagick to convert image to JPEG
    $img = new Imagick($img_file_path);
    $img->setImageFormat('jpeg');
    $img->resizeImage(1200, 1200, Imagick::FILTER_LANCZOS, 1);
    $img->setImageCompressionQuality(75);
    // Return image as binary string
    return $img->getImageBlob();
  }

  public function proccessUploadedImage(): bool
  {
    // Check if image is a match for CSAM
    if ($this->scan($this->filePath)) {
      error_log("PhotoDNA: CSAM match found for file: {$this->fileName}\n");
      // Create evidence report
      $evidenceData = $this->createCSAMEvidenceReport();
      error_log("Evidence data: " . json_encode($evidenceData) . PHP_EOL);
      // Upload the image to the CSAM evidence bucket
      $this->uploadCSAMEvidence($evidenceData);
      // Update the CSAM report table
      $this->updateCSAMReportTable($evidenceData);
      // Blacklist the user
      if (!empty($this->uploaderNpub) && $this->uploaderNpub !== 'Unknown') {
        $this->blackListUser($evidenceData);
      }
      // Add the file to the rejected table
      $this->addFileToRejectedTable();
      return true;
    }
    error_log("PhotoDNA: No CSAM match found for file: {$this->fileName}\n");
    return false;
  }
}

/**
 * https://www.codexworld.com/get-geolocation-latitude-longitude-from-image-php/
 * get_image_location
 * Returns an array of latitude and longitude from the Image file
 * @param $image file path
 * @return multitype:array|boolean
 */
function get_image_location(string $image = ''): mixed
{
  $exif = exif_read_data($image, 0, true);
  if ($exif && isset($exif['GPS'])) {
    $GPSLatitudeRef = $exif['GPS']['GPSLatitudeRef'];
    $GPSLatitude    = $exif['GPS']['GPSLatitude'];
    $GPSLongitudeRef = $exif['GPS']['GPSLongitudeRef'];
    $GPSLongitude   = $exif['GPS']['GPSLongitude'];

    $lat_degrees = count($GPSLatitude) > 0 ? gps2Num($GPSLatitude[0]) : 0;
    $lat_minutes = count($GPSLatitude) > 1 ? gps2Num($GPSLatitude[1]) : 0;
    $lat_seconds = count($GPSLatitude) > 2 ? gps2Num($GPSLatitude[2]) : 0;

    $lon_degrees = count($GPSLongitude) > 0 ? gps2Num($GPSLongitude[0]) : 0;
    $lon_minutes = count($GPSLongitude) > 1 ? gps2Num($GPSLongitude[1]) : 0;
    $lon_seconds = count($GPSLongitude) > 2 ? gps2Num($GPSLongitude[2]) : 0;

    $lat_direction = ($GPSLatitudeRef == 'W' or $GPSLatitudeRef == 'S') ? -1 : 1;
    $lon_direction = ($GPSLongitudeRef == 'W' or $GPSLongitudeRef == 'S') ? -1 : 1;

    $latitude = $lat_direction * ($lat_degrees + ($lat_minutes / 60) + ($lat_seconds / (60 * 60)));
    $longitude = $lon_direction * ($lon_degrees + ($lon_minutes / 60) + ($lon_seconds / (60 * 60)));

    return array('latitude' => $latitude, 'longitude' => $longitude);
  } else {
    return false;
  }
}

function gps2Num(string $coordPart): float
{
  $parts = explode('/', $coordPart);
  if (count($parts) <= 0)
    return 0;
  if (count($parts) == 1)
    return $parts[0];
  return floatval($parts[0]) / floatval($parts[1]);
}
