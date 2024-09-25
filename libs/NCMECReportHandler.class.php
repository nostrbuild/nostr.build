<?php

/**
 * NCMECReport Class
 *
 * This class is responsible for reporting content violations to NCMEC.
 *
 * Usage:
 * - Instantiate the class with the incident ID.
 * - Call `processAndReportViolation()` to process and report the incident.
 * - Call `getEvidenceImgTag($maxWidth, $maxHeight)` to get the image tag for verification.
 * - Call `getSanitizedReportData()` to get the sanitized report data.
 * 
 * Example:
   try {
        $incidentId = 123; // Replace with the actual incident ID
        $ncmecReport = new NCMECReport($incidentId);

        // Get the evidence image tag for verification
        $imgTag = $ncmecReport->getEvidenceImgTag(300, 300);
        echo $imgTag; // Display the image for verification

        // If the content is confirmed and not a false positive, proceed to report
        $result = $ncmecReport->processAndReportViolation();

        if ($result['httpCode'] === 200) {
            echo "Report submitted successfully.";
        } else {
            echo "Error submitting report: " . ($result['error'] ?? json_encode($result['response']));
        }
    } catch (Exception $e) {
        echo 'Error: ' . $e->getMessage();
    } 
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/S3Service.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/utils.funcs.php';


class NCMECReportHandler
{
  private $db;
  private $incidentId;
  private $csamReportingConfig;
  private $orgName;
  private $reporterName;
  private $reporterEmail;
  private $incidentTime;
  private $reporteeName;
  private $reporteeIPAddress;
  private $violationContentCollection;
  private $additionalMetadata;
  private $incidentDetails;

  /**
   * Constructor for the NCMECReportHandler class.
   *
   * @param int $incidentId The ID of the incident to be handled.
   * @param bool $testReport Whether this is a test report.
   * @throws Exception If the logs data is empty.
   */
  public function __construct(int $incidentId, bool $testReport = true)
  {
    global $link;
    global $csamReportingConfig;
    $this->db = $link;
    $this->csamReportingConfig = $csamReportingConfig;
    $this->incidentId = $incidentId;
    // Populate the incident details
    $this->incidentDetails = $this->getIncidentDetails();
    // Process the logs column
    $logs = $this->incidentDetails['logs'] ?? '';
    if (empty($logs)) {
      throw new Exception('Logs data is empty.');
    }
    $this->processLogColumn($logs, $testReport);
  }

  /**
   * Fetches incident details and processes the logs, then reports the violation.
   *
   * @return array The response from the NCMEC API.
   */
  public function processAndReportViolation(): array
  {
    try {
      // Report the violation
      $result = $this->reportViolation();

      // Update the incident report ID and submitted report in the database if successful
      if ($result['httpCode'] === 200) {
        $reportId = $result['response'] ?? '';
        // Get the sanitized report data (without Base64 media)
        $sanitizedReportData = $this->getSanitizedReportData();
        $this->updateIncidentReportId($reportId, json_encode($sanitizedReportData));
      }

      return $result;
    } catch (Exception $e) {
      // Handle exceptions and return an error response
      return [
        'httpCode' => 0,
        'error' => $e->getMessage()
      ];
    }
  }

  /**
   * Retrieves the image tag for the evidence to verify correctness.
   *
   * @param int $maxWidth  The maximum width of the image.
   * @param int $maxHeight The maximum height of the image.
   * @return string The img tag with Base64-encoded media data.
   */
  public function getEvidenceImgTag(int $maxWidth = 300, int $maxHeight = 300): string
  {
    try {
      // Fetch incident details if not already fetched
      if (empty($this->incidentDetails)) {
        $this->incidentDetails = $this->getIncidentDetails();
      }

      // Extract file hash from incident details
      $fileHash = $this->incidentDetails['file_sha256_hash'] ?? '';
      if (empty($fileHash)) {
        throw new Exception('File hash is missing from incident details.');
      }

      // Get the image tag
      return $this->getBase64MediaImgTag($fileHash, $maxWidth, $maxHeight);
    } catch (Exception $e) {
      // Handle exceptions and return an empty string
      error_log('Error getting evidence image tag: ' . $e->getMessage());
      return '';
    }
  }

  /**
   * Retrieves incident details from the database.
   *
   * @return array The incident details.
   * @throws Exception If the incident is not found.
   */
  private function getIncidentDetails(): array
  {
    $sql = 'SELECT * FROM identified_csam_cases WHERE id = ?';
    $stmt = $this->db->prepare($sql);
    $stmt->bind_param('i', $this->incidentId);
    $stmt->execute();
    $result = $stmt->get_result();
    $incident = $result->fetch_assoc();
    $stmt->close();
    if (!$incident) {
      throw new Exception('Incident not found.');
    }
    return $incident;
  }

  /**
   * Updates the incident report ID and submitted report in the database.
   *
   * @param string $reportId The report ID received from NCMEC.
   * @param string $report   The sanitized report data (without Base64 media).
   */
  private function updateIncidentReportId(string $reportId, string $report)
  {
    $sql = 'UPDATE identified_csam_cases SET ncmec_report_id = ?, ncmec_submitted_report = ? WHERE id = ?';
    $stmt = $this->db->prepare($sql);
    $stmt->bind_param('ssi', $reportId, $report, $this->incidentId);
    $stmt->execute();
    $stmt->close();
  }

  /**
   * Returns the sanitized report data (without Base64 media) that was submitted to NCMEC.
   *
   * @return array The sanitized report data.
   */
  public function getSanitizedReportData(): array
  {
    // Create a deep copy of the data
    $sanitizedData = [
      'OrgName' => $this->orgName,
      'ReporterName' => $this->reporterName,
      'ReporterEmail' => $this->reporterEmail,
      'IncidentTime' => $this->incidentTime,
      'ReporteeName' => $this->reporteeName,
      'ReporteeIPAddress' => $this->reporteeIPAddress,
      'ViolationContentCollection' => [],
      'AdditionalMetadata' => $this->additionalMetadata
    ];

    // Loop through ViolationContentCollection and remove the Base64 media
    foreach ($this->violationContentCollection as $violation) {
      $sanitizedViolation = $violation;
      unset($sanitizedViolation['Value']); // Remove the Base64 media
      $sanitizedData['ViolationContentCollection'][] = $sanitizedViolation;
    }

    return $sanitizedData;
  }

  /**
   * Processes the logs column from the incident data.
   *
   * @param string $logs The logs data as a JSON string.
   * @param bool   $testReport Whether this is a test report.
   * @throws Exception If the logs data is invalid or unsupported.
   */
  private function processLogColumn(string $logs, bool $testReport = true): void
  {
    $data = json_decode($logs, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new Exception('Invalid JSON in logs column: ' . json_last_error_msg());
    }

    // Check if 'evidenceData' exists
    if (isset($data['evidenceData'])) {
      $this->processEvidenceData($data['evidenceData'], $testReport);
    } else {
      // Assume Type 1 format
      $this->processType1Log($data, $testReport);
    }
  }

  /**
   * Processes logs of Type 1 format.
   *
   * @param array $data The logs data as an associative array.
   * @param bool  $testReport Whether this is a test report.
   * @throws Exception If required data is missing.
   */
  private function processType1Log(array $data, bool $testReport = true): void
  {
    // Since the top-level keys are filenames, we'll iterate over them
    foreach ($data as $key => $fileData) {
      // Extract necessary information
      $fileUrl = $fileData['fileUrl'] ?? null;
      $fileHash = $fileData['fileHash'] ?? null;
      $uploadNpub = $fileData['uploadNpub'] ?? 'Unknown';
      $uploadTime = $fileData['uploadTime'] ?? time();
      $uploadIpInfo = json_decode($fileData['uploadedFileInfo'] ?? '{}', true);
      $uploadIpAddress = $uploadIpInfo['realIp'] ?? 'Unknown';

      $incidentTime = date('Y-m-d\TH:i:s\Z', $uploadTime);

      // Prepare ViolationContentCollection
      $violationContentCollection = [
        [
          'Name' => $fileData['fileName'] ?? '',
          'Value' => $this->getBase64MediaByHash($fileHash),
          'Location' => null,
          'UploadIpAddress' => $uploadIpAddress,
          'UploadDateTime' => $incidentTime,
          'AdditionalMetadata' => [
            [
              'Key' => 'additionalInfo',
              'Value' => json_encode($uploadIpInfo)
            ],
            [
              'Key' => 'publiclyAvailable',
              'Value' => 'true' // Assuming the content is publicly available
            ]
          ]
        ]
      ];

      // Set report parameters
      $this->setReportParameters(
        incidentTime: $incidentTime,
        reporteeName: $uploadNpub,
        reporteeIPAddress: $uploadIpAddress,
        violationContentCollection: $violationContentCollection,
        testReport: $testReport
      );

      // For Type 1 logs, we can assume only one entry per log, so break after processing
      break;
    }
  }

  /**
   * Processes the evidenceData from logs of Type 2 format.
   *
   * @param array $evidenceData The evidenceData array from the logs.
   * @param bool  $testReport   Whether this is a test report.
   */
  private function processEvidenceData(array $evidenceData, bool $testReport = true): void
  {
    // Set optional fields to null if empty
    $orgName = !empty($evidenceData['OrgName']) ? $evidenceData['OrgName'] : null;
    $reporterName = !empty($evidenceData['ReporterName']) ? $evidenceData['ReporterName'] : null;
    $reporterEmail = !empty($evidenceData['ReporterEmail']) ? $evidenceData['ReporterEmail'] : null;

    // Process ViolationContentCollection
    $violationContent = $evidenceData['ViolationContentCollection'];

    // If ViolationContentCollection is not an array, wrap it in an array
    if (!isset($violationContent[0])) {
      $violationContent = [$violationContent];
    }

    $violationContentCollection = [];
    foreach ($violationContent as $violation) {
      $additionalMetadata = [];

      if (isset($violation['AdditionalMetadata'])) {
        // Since AdditionalMetadata might be an associative array, we need to convert it into the expected array of Key-Value pairs
        foreach ($violation['AdditionalMetadata'] as $key => $value) {
          if (is_array($value)) {
            // If the value is an array (e.g., 'additionalInfo'), we can JSON encode it
            $value = json_encode($value);
          } elseif (is_bool($value)) {
            $value = $value ? 'true' : 'false';
          }

          $additionalMetadata[] = [
            'Key' => $key,
            'Value' => $value
          ];
        }
      }

      $violationContentCollection[] = [
        'Name' => $violation['Name'] ?? '',
        'Value' => $this->getBase64Media($violation['Value'] ?? ''),
        'Location' => $violation['Location'] ?? null,
        'UploadIpAddress' => $violation['UploadIpAddress'] ?? '',
        'UploadDateTime' => $violation['UploadDateTime'] ?? '',
        'AdditionalMetadata' => $additionalMetadata
      ];
    }

    $this->setReportParameters(
      incidentTime: $evidenceData['IncidentTime'],
      reporteeName: $evidenceData['ReporteeName'],
      reporteeIPAddress: $evidenceData['ReporteeIPAddress'],
      violationContentCollection: $violationContentCollection,
      orgName: $orgName,
      reporterName: $reporterName,
      reporterEmail: $reporterEmail,
      testReport: $testReport
    );
  }

  /**
   * Sets the report parameters.
   *
   * @param string      $incidentTime               The time the incident occurred.
   * @param string      $reporteeName               The name of the reportee.
   * @param string      $reporteeIPAddress          The IP address of the reportee.
   * @param array       $violationContentCollection The violation content collection.
   * @param string|null $orgName                    The organization name.
   * @param string|null $reporterName               The reporter's name.
   * @param string|null $reporterEmail              The reporter's email.
   * @param bool|null   $testReport                 Whether this is a test report.
   */
  private function setReportParameters(
    string $incidentTime,
    string $reporteeName,
    string $reporteeIPAddress,
    array $violationContentCollection,
    ?string $orgName = null,
    ?string $reporterName = null,
    ?string $reporterEmail = null,
    ?bool $testReport = true
  ) {
    $this->orgName = $orgName ?? $this->csamReportingConfig['NCMEC_REPORT_ORG_NAME'];
    $this->reporterName = $reporterName ?? $this->csamReportingConfig['NCMEC_REPORTER_NAME'];
    $this->reporterEmail = $reporterEmail ?? $this->csamReportingConfig['NCMEC_REPORTER_EMAIL'];
    $this->incidentTime = $incidentTime;
    $this->reporteeName = $reporteeName;
    $this->reporteeIPAddress = $reporteeIPAddress;
    $this->violationContentCollection = $violationContentCollection;
    $this->additionalMetadata = $testReport ? [['Key' => 'IsTest', 'Value' => 'true']] : [];
  }

  /**
   * Reports the violation to NCMEC.
   *
   * @return array The response from the NCMEC API.
   * @throws Exception If required data is missing.
   */
  private function reportViolation(): array
  {
    // Validate ViolationContentCollection
    if (empty($this->violationContentCollection)) {
      throw new Exception('ViolationContentCollection is empty.');
    }

    foreach ($this->violationContentCollection as &$violation) {
      // Ensure required keys are present
      $requiredKeys = ['Name', 'Value'];
      foreach ($requiredKeys as $key) {
        if (!isset($violation[$key]) || empty($violation[$key])) {
          throw new Exception("ViolationContentCollection item is missing required key: $key");
        }
      }
    }

    $url = $_SERVER['NCMEC_PHOTODNA_API_REPORT_URL'];
    $headers = [
      'Content-Type: application/json',
      'Cache-Control: no-cache',
      'Ocp-Apim-Subscription-Key: ' . $_SERVER['PHOTODNA_API_KEY'],
      'x-usr: ' . $_SERVER['NCMEC_USERNAME'],
      'x-pwd: ' . $_SERVER['NCMEC_PASSWORD']
    ];

    // Prepare the data to be sent
    $data = [
      'OrgName' => $this->orgName,
      'ReporterName' => $this->reporterName,
      'ReporterEmail' => $this->reporterEmail,
      'IncidentTime' => $this->incidentTime,
      'ReporteeName' => $this->reporteeName,
      'ReporteeIPAddress' => $this->reporteeIPAddress,
      'ViolationContentCollection' => $this->violationContentCollection,
      'AdditionalMetadata' => $this->additionalMetadata
    ];

    $data_string = json_encode($data);
    // Throw exception if JSON is invalid
    if ($data_string === false) {
      throw new Exception('JSON encoding error: ' . json_last_error_msg());
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Execute the request
    $result = curl_exec($ch);

    // Error handling
    if ($result === false) {
      $error_msg = curl_error($ch);
      curl_close($ch);
      return [
        'httpCode' => 0,
        'error' => $error_msg
      ];
    }

    // Get HTTP status code
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Parse the response
    $response = json_decode($result, true);

    return [
      'httpCode' => $httpCode,
      'response' => $response
    ];
  }

  /**
   * Retrieves Base64-encoded media data from a URL or file hash.
   *
   * @param string $identifier The URL or file hash of the media.
   * @return string The Base64-encoded media data.
   */
  private function getBase64Media(string $identifier): string
  {
    if (empty($identifier)) {
      return '';
    }

    // Determine if the identifier is a URL or a file hash
    if (filter_var($identifier, FILTER_VALIDATE_URL)) {
      // It's a URL
      return $this->getBase64FromUrl($identifier);
    } else {
      // Assume it's a file hash
      return $this->getBase64MediaByHash($identifier);
    }
  }

  /**
   * Retrieves Base64-encoded media data using a file hash.
   *
   * @param string $fileHash The file hash.
   * @return string The Base64-encoded media data.
   */
  private function getBase64MediaByHash(string $fileHash): string
  {
    // Get the presigned URL using the file hash
    $presignedUrl = $this->getPresignedUrl($fileHash);
    if ($presignedUrl === '') {
      return '';
    }

    // Get the media data
    $mediaData = $this->getBase64FromUrl($presignedUrl);
    if ($mediaData === '') {
      return '';
    }

    return $mediaData;
  }

  /**
   * Generates a presigned URL for a media object based on its file hash.
   *
   * @param string $fileHash The file hash.
   * @return string The presigned URL.
   */
  private function getPresignedUrl(string $fileHash): string
  {
    // Identify the full key of the original media object based on the evidence_location_url
    // which is stored as https://<s3_endpoint_host>/<bucket>/<file_hash>/ (a prefix of the keys in the bucket)
    // The media will be under the prefix https://<s3_endpoint_host>/<bucket>/<file_hash>/<file_hash>.<extension>

    // Get the evidence_location_url
    $evidenceLocationUrl = '';
    try {
      $sql = 'SELECT evidence_location_url FROM identified_csam_cases WHERE file_sha256_hash = ?';
      $stmt = $this->db->prepare($sql);
      $stmt->bind_param('s', $fileHash);
      $stmt->execute();
      $result = $stmt->get_result();
      $row = $result->fetch_assoc();
      $stmt->close();
      if (!$row || empty($row['evidence_location_url'])) {
        return '';
      }
      $evidenceLocationUrl = $row['evidence_location_url'];
    } catch (Exception $e) {
      error_log('Error getting evidence location URL: ' . $e->getMessage());
      return '';
    }
    if ($evidenceLocationUrl === '') {
      return '';
    }

    // Get the original media object key
    // List all objects in the bucket with the prefix
    $evidencePrefix = parse_url($evidenceLocationUrl, PHP_URL_PATH);
    $evidencePrefix = ltrim($evidencePrefix, '/'); // Remove leading slash if any

    try {
      $evidenceObjects = listObjectsUnderPrefix(
        prefix: $evidencePrefix,
        bucket: $this->csamReportingConfig['r2EvidenceBucket'],
        endPoint: $this->csamReportingConfig['r2EndPoint'],
        accessKey: $this->csamReportingConfig['r2AccessKey'],
        secretKey: $this->csamReportingConfig['r2SecretKey']
      );
    } catch (Exception $e) {
      error_log('Error listing objects under prefix: ' . $e->getMessage());
      return '';
    }
    if (empty($evidenceObjects)) {
      error_log('No objects found under the given prefix.');
      return '';
    }

    // Filter for the original media object key
    $originalMediaObjectKey = '';
    foreach ($evidenceObjects as $object) {
      if (strpos($object, $fileHash) !== false) {
        $originalMediaObjectKey = $object;
        break;
      }
    }

    // Prepare and return presigned URL
    if ($originalMediaObjectKey !== '') {
      return getPresignedUrlFromObjectKey(
        objectKey: $originalMediaObjectKey,
        bucket: $this->csamReportingConfig['r2EvidenceBucket'],
        endPoint: $this->csamReportingConfig['r2EndPoint'],
        accessKey: $this->csamReportingConfig['r2AccessKey'],
        secretKey: $this->csamReportingConfig['r2SecretKey']
      );
    } else {
      error_log('Original media object key not found.');
      return '';
    }
  }

  /**
   * Retrieves Base64-encoded data from a URL.
   *
   * @param string $url    The URL of the media.
   * @param bool   $imgTag Whether to return data in img tag format.
   * @return string The Base64-encoded data.
   */
  private function getBase64FromUrl(string $url, bool $imgTag = false): string
  {
    // Use CURL to get the image data
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $data = curl_exec($ch);

    if ($data === false) {
      $error_msg = curl_error($ch);
      curl_close($ch);
      error_log('CURL error: ' . $error_msg);
      return '';
    }

    $returnedMimeType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($imgTag) {
      return 'data:' . $returnedMimeType . ';base64,' . base64_encode($data);
    } else {
      return base64_encode($data);
    }
  }

  /**
   * Generates an img tag with Base64-encoded media data.
   *
   * @param string $fileHash  The file hash.
   * @param int    $maxWidth  The maximum width of the image.
   * @param int    $maxHeight The maximum height of the image.
   * @return string The img tag with Base64-encoded media data.
   */
  private function getBase64MediaImgTag(string $fileHash, int $maxWidth, int $maxHeight): string
  {
    // Get the presigned URL
    $presignedUrl = $this->getPresignedUrl($fileHash);
    if ($presignedUrl === '') {
      return '';
    }

    // Get the media data
    $mediaData = $this->getBase64FromUrl($presignedUrl, true);
    if ($mediaData === '') {
      return '';
    }

    // Return the img tag with style attributes to limit max width and height
    return '<img src="' . $mediaData . '" style="max-width: ' . $maxWidth . 'px; max-height: ' . $maxHeight . 'px;" />';
  }

  /**
   * Un-blacklists a user from the blacklist table based on npub and incident time.
   *
   * @param string $reason The reason for un-blacklisting.
   * @return bool True if the user was un-blacklisted, false otherwise.
   */
  public function unBlacklistUser(?string $reason = "PhotoDNA CSAM Match API Match"): bool
  {
    // Get the details from the incident data
    $npub = $this->reporteeName;
    $reason = "PhotoDNA CSAM Match API Match";
    $sql = 'DELETE FROM blacklist WHERE npub = ? AND reason = ? AND timestamp BETWEEN ? AND ?';
    $stmt = $this->db->prepare($sql);
    $timestamp = date('Y-m-d H:i:s', strtotime($this->incidentTime));
    $timestampStart = date('Y-m-d H:i:s', strtotime($timestamp . ' -10 minutes'));
    $timestampEnd = date('Y-m-d H:i:s', strtotime($timestamp . ' +10 minutes'));
    $stmt->bind_param('ssss', $npub, $reason, $timestampStart, $timestampEnd);
    $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();

    return $affectedRows > 0;
  }

}
