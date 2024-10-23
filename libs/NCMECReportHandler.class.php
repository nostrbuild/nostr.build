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
  private $reporterFirstName;
  private $reporterLastName;
  private $reporterEmail;
  private $incidentTime;
  private $incidentType;
  private $reporteeName;
  private $reporteeIPAddress;
  private $violationContentCollection;
  private $incidentDetails;
  private $testReport;
  private $ncmecReport; // Instance of the NcmecReport class
  private $apiRequests = [];  // To store API requests (XML)
  private $apiResponses = []; // To store API responses

  /**
   * Constructor for the NCMECReportHandler class.
   *
   * @param int  $incidentId The ID of the incident to be handled.
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
    $this->testReport = $testReport;

    // Initialize the NcmecReport instance
    $this->ncmecReport = new NcmecReport(
      $_SERVER['NCMEC_USERNAME'],
      $_SERVER['NCMEC_PASSWORD'],
      $testReport ? 'test' : 'production'
    );

    // Populate the incident details
    $this->incidentDetails = $this->getIncidentDetails();

    // Process the logs column
    $logs = $this->incidentDetails['logs'] ?? '';
    if (empty($logs)) {
      throw new Exception('Logs data is empty.');
    }
    $this->processLogColumn($logs);
  }

  /**
   * Processes the incident data and reports the violation to NCMEC.
   *
   * @return array The response from the NCMEC API.
   */
  public function processAndReportViolation(): array
  {
    try {
      // Set up the report with the collected data
      $this->prepareReport();

      // Preview the report before submission (optional)
      $reportXml = $this->ncmecReport->getReportXml();
      $this->apiRequests['submitReport'] = $reportXml;

      // Submit the report
      $submitReportResponse = $this->ncmecReport->submitReport();
      $this->apiResponses['submitReport'] = $submitReportResponse;

      // For each violation content, upload the file and submit file details
      foreach ($this->violationContentCollection as $violation) {
        $fileId = $violation['Name'];
        $binaryData = base64_decode($violation['Value']);
        $fileName = $violation['Name'];

        // Use the MIME type from the Content-Type header when fetching the file
        $presignedUrl = $this->getPresignedUrl($this->incidentDetails['file_sha256_hash']);
        if ($presignedUrl === '') {
          throw new Exception('Unable to get presigned URL for file hash.');
        }

        // Fetch the file and get the Content-Type header
        $fileData = $this->fetchFileData($presignedUrl);
        $mimeType = $fileData['mimeType'];
        $binaryData = $fileData['binaryData'];

        // Upload the file
        $uploadFileResponse = $this->ncmecReport->uploadFile($fileId, $binaryData, $fileName, $mimeType);
        $this->apiResponses['uploadFile'][$fileId] = $uploadFileResponse;

        // Prepare file details
        $fileDetails = [
          'fileId' => $fileId,
          'originalFileName' => $fileName,
          'locationOfFile' => $violation['LocationOfFile'] ?? '',
          'fileViewedByEsp' => 'true',
          'publiclyAvailable' => 'true',
          'ipCaptureEvent' => [
            'ipAddress' => $violation['UploadIpAddress'],
            'eventName' => 'Upload',
            'dateTime' => $violation['UploadDateTime'],
            'possibleProxy' => 'false'
          ],
          'additionalInfo' => $violation['AdditionalInfo'] ?? '',
        ];

        // Build the file details XML
        $fileDetailsXml = $this->ncmecReport->buildFileDetailsXml($fileDetails);
        $this->apiRequests['submitFileDetails'][$fileId] = $fileDetailsXml;

        // Submit file details
        $submitFileDetailsResponse = $this->ncmecReport->submitFileDetails($fileDetailsXml);
        $this->apiResponses['submitFileDetails'][$fileId] = $submitFileDetailsResponse;
      }

      // Finish the report
      $finishReportResponse = $this->ncmecReport->finishReport();
      $this->apiResponses['finishReport'] = $finishReportResponse;
      // Store all requests and responses in the database
      $this->apiResponses['allRequests'] = $this->ncmecReport->getAllResponses();
      $this->apiRequests = $this->ncmecReport->getAllRequests();

      // Store all requests and responses in the database
      $reportId = $finishReportResponse['reportId'];
      $this->updateIncidentReport(
        $reportId,
        json_encode($this->apiRequests),
        json_encode($this->apiResponses)
      );

      return [
        'httpCode' => 200,
        'response' => $finishReportResponse
      ];
    } catch (Exception $e) {
      // Handle exceptions and attempt to cancel the report if possible
      if ($this->ncmecReport->reportId) {
        try {
          $cancelReportResponse = $this->ncmecReport->cancelReport();
          $this->apiResponses['cancelReport'] = $cancelReportResponse;
        } catch (Exception $cancelException) {
          error_log('Error during report cancellation: ' . $cancelException->getMessage());
        }
      }
      // Store the error response in the database
      $this->updateIncidentReport(
        'ERROR',
        json_encode($this->apiRequests),
        json_encode(['error' => $e->getMessage(), 'apiResponses' => $this->apiResponses])
      );
      return [
        'httpCode' => 0,
        'error' => $e->getMessage()
      ];
    }
  }

  /**
   * Prepares the NCMEC report with the collected data.
   */
  private function prepareReport()
  {
    // Set the incident summary
    $this->ncmecReport->setIncidentSummary($this->incidentType, $this->incidentTime);

    // Set reporter details
    $this->ncmecReport->setReporter([
      'firstName' => $this->reporterFirstName,
      'lastName' => $this->reporterLastName,
      'email' => $this->reporterEmail
    ]);

    // Set internet details if applicable
    if (isset($this->incidentDetails['url'])) {
      $this->ncmecReport->setInternetDetails([
        'webPageIncident' => [
          'url' => $this->incidentDetails['url'],
          'additionalInfo' => ''
        ]
      ]);
    }

    // Add person or user reported
    $this->ncmecReport->addPersonOrUserReported([
      'espIdentifier' => $this->reporteeName,
      'profileUrl' => 'https://njump.me/' . $this->reporteeName,
      'ipCaptureEvent' => [
        'ipAddress' => $this->reporteeIPAddress,
        'eventName' => 'Upload',
        'dateTime' => $this->incidentTime,
        'possibleProxy' => 'false'
      ]
    ]);
  }

  /**
   * Previews the XML report that will be submitted to NCMEC.
   *
   * @return string The XML representation of the report.
   */
  public function previewReport(): string
  {
    // Prepare the report if not already prepared
    $this->prepareReport();
    return $this->ncmecReport->getReportXml();
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
   * Updates the incident report in the database.
   *
   * @param string $reportId       The report ID received from NCMEC.
   * @param string $sentReport     The XML report that was sent.
   * @param string $reportResponse The responses from NCMEC API calls.
   */
  private function updateIncidentReport(string $reportId, string $sentReport, string $reportResponse)
  {
    $reportId = $this->testReport ? 'TEST_' . $reportId : $reportId;

    // Prepare the data to be stored in the JSON column
    $reportData = [
      'sentReport' => json_decode($sentReport, true),
      'apiResponses' => json_decode($reportResponse, true)
    ];

    $sql = 'UPDATE identified_csam_cases SET ncmec_report_id = ?, ncmec_submitted_report = ? WHERE id = ?';
    $stmt = $this->db->prepare($sql);
    $reportDataJson = json_encode($reportData);
    $stmt->bind_param('ssi', $reportId, $reportDataJson, $this->incidentId);
    $stmt->execute();
    $stmt->close();
  }

  /**
   * Processes the logs column from the incident data.
   *
   * @param string $logs The logs data as a JSON string.
   * @throws Exception If the logs data is invalid or unsupported.
   */
  private function processLogColumn(string $logs): void
  {
    $data = json_decode($logs, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new Exception('Invalid JSON in logs column: ' . json_last_error_msg());
    }

    // Check if 'evidenceData' exists
    if (isset($data['evidenceData'])) {
      $this->processEvidenceData($data['evidenceData']);
    } else {
      // Assume Type 1 format
      $this->processType1Log($data);
    }
  }

  /**
   * Processes logs of Type 1 format.
   *
   * @param array $data The logs data as an associative array.
   * @throws Exception If required data is missing.
   */
  private function processType1Log(array $data): void
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
      $this->violationContentCollection = [
        [
          'Name' => (empty($fileData['fileName']) ? 'Unknown' : $fileData['fileName']),
          'Value' => '', // Will be fetched later
          'LocationOfFile' => $fileUrl ?? '',
          'UploadIpAddress' => $uploadIpAddress,
          'UploadDateTime' => $incidentTime,
          'AdditionalInfo' => htmlspecialchars(json_encode($uploadIpInfo), ENT_QUOTES | ENT_XML1, 'UTF-8')
        ]
      ];

      // Set report parameters
      $this->setReportParameters(
        incidentTime: $incidentTime,
        reporteeName: (empty($uploadNpub) ? 'Unknown' : $uploadNpub),
        reporteeIPAddress: $uploadIpAddress,
        incidentType: 'Child Pornography (possession, manufacture, and distribution)'
      );

      // For Type 1 logs, we can assume only one entry per log, so break after processing
      break;
    }
  }

  /**
   * Processes the evidenceData from logs of Type 2 format.
   *
   * @param array $evidenceData The evidenceData array from the logs.
   */
  private function processEvidenceData(array $evidenceData): void
  {
    // Process ViolationContentCollection
    $violationContent = $evidenceData['ViolationContentCollection'];

    // If ViolationContentCollection is not an array, wrap it in an array
    if (!isset($violationContent[0])) {
      $violationContent = [$violationContent];
    }

    $this->violationContentCollection = [];
    foreach ($violationContent as $violation) {
      $this->violationContentCollection[] = [
        'Name' => empty($violation['Name']) ? 'Unknown' : $violation['Name'],
        'Value' => '', // Will be fetched later
        'LocationOfFile' => $violation['LocationOfFile'] ?? '',
        'UploadIpAddress' => $violation['UploadIpAddress'] ?? '',
        'UploadDateTime' => $violation['UploadDateTime'] ?? '',
        'AdditionalInfo' => htmlspecialchars(json_encode($violation['AdditionalInfo']), ENT_QUOTES | ENT_XML1, 'UTF-8') ?? ''
      ];
    }

    $this->setReportParameters(
      incidentTime: $evidenceData['IncidentTime'],
      reporteeName: (empty($evidenceData['ReporteeName']) ? 'Unknown' : $evidenceData['ReporteeName']),
      reporteeIPAddress: $evidenceData['ReporteeIPAddress'],
      incidentType: $evidenceData['IncidentType'] ?? 'Child Pornography (possession, manufacture, and distribution)'
    );
  }

  /**
   * Sets the report parameters.
   *
   * @param string      $incidentTime      The time the incident occurred.
   * @param string      $reporteeName      The name of the reportee.
   * @param string      $reporteeIPAddress The IP address of the reportee.
   * @param string|null $incidentType      The type of incident.
   */
  private function setReportParameters(
    string $incidentTime,
    string $reporteeName,
    string $reporteeIPAddress,
    /* Possible Values: sextortion, csamSolicitation, minorToMinorInteraction */
    ?string $reportAnnotations = null,
    /* Possible Values:
    Child Pornography (possession, manufacture, and distribution)
    Child Sex Trafficking
    Child Sex Tourism
    Child Sexual Molestation
    Misleading Domain Name
    Misleading Words or Digital Images on the Internet
    Online Enticement of Children for Sexual Acts
    Unsolicited Obscene Material Sent to a Child
    */
    ?string $incidentType = 'Child Pornography (possession, manufacture, and distribution)'
  ) {
    // Split NCMEC_REPORTER_NAME into first and last name
    $reporterNameParts = explode(' ', $_SERVER['NCMEC_REPORTER_NAME']);
    $this->orgName = $_SERVER['NCMEC_REPORT_ORG_NAME'];
    $this->reporterFirstName = $reporterNameParts[0];
    $this->reporterLastName = $reporterNameParts[1];
    $this->reporterEmail = $_SERVER['NCMEC_REPORTER_EMAIL'];
    $this->incidentTime = $incidentTime;
    $this->incidentType = $incidentType;
    $this->reporteeName = $reporteeName;
    $this->reporteeIPAddress = $reporteeIPAddress;
  }

  /**
   * Fetches the file data and MIME type using the Content-Type header.
   *
   * @param string $url The presigned URL of the file.
   * @return array An array containing 'binaryData' and 'mimeType'.
   */
  private function fetchFileData(string $url): array
  {
    // Use CURL to get the media data and headers
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true); // Include headers in the output
    $data = curl_exec($ch);

    if ($data === false) {
      $error_msg = curl_error($ch);
      curl_close($ch);
      throw new Exception('CURL error: ' . $error_msg);
    }

    // Separate headers and body
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($data, 0, $header_size);
    $body = substr($data, $header_size);

    // Get MIME type from headers
    $mimeType = 'application/octet-stream'; // Default MIME type
    if (preg_match('/Content-Type:\s*(.+)/i', $headers, $matches)) {
      $mimeType = trim($matches[1]);
    }

    curl_close($ch);

    return [
      'binaryData' => $body,
      'mimeType' => $mimeType
    ];
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
    $parsed_url = parse_url($evidenceLocationUrl);
    $endpoint_url = $parsed_url['scheme'] . '://' . $parsed_url['host'];
    $path_parts = explode('/', trim($parsed_url['path'], '/'));
    $bucket = array_shift($path_parts);
    $prefix = implode('/', $path_parts);

    try {
      $evidenceObjects = listObjectsUnderPrefix(
        prefix: $prefix,
        bucket: $bucket,
        endPoint: $endpoint_url,
        accessKey: $this->csamReportingConfig['r2AccessKey'],
        secretKey: $this->csamReportingConfig['r2SecretKey']
      );
      error_log('Objects under prefix: ' . json_encode($evidenceObjects));
    } catch (Exception $e) {
      error_log('Error listing objects under prefix: ' . $e->getMessage());
      return '';
    }
    if (empty($evidenceObjects)) {
      error_log('No objects found under the given prefix:' . $prefix);
      error_log('Bucket: ' . $bucket);
      error_log('Endpoint: ' . $endpoint_url);
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

    // Fetch the file data
    try {
      $fileData = $this->fetchFileData($presignedUrl);
      $mimeType = $fileData['mimeType'];
      $binaryData = $fileData['binaryData'];
    } catch (Exception $e) {
      error_log('Error fetching file data: ' . $e->getMessage());
      return '';
    }

    // Return the img tag with style attributes to limit max width and height
    return '<img src="data:' . $mimeType . ';base64,' . base64_encode($binaryData) . '" style="max-width: ' . $maxWidth . 'px; max-height: ' . $maxHeight . 'px;" />';
  }

  /**
   * Un-blacklists a user from the blacklist table based on npub and incident time.
   *
   * @param string|null $reason The reason for un-blacklisting.
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

    // Update the report ID as FALSE_MATCH, even if npub is not found in the blacklist
    $this->updateIncidentReport('FALSE_MATCH', '{}', '{}');
    return $affectedRows > 0;
  }
}

/**
 * Use https://njump.me/<user npub> to construct profile URL if npub is known
 * Use industry classification if file is known to be CSAM and matched by PhotoDNA
 * Allow submitter to provide additional information in the report (free text)
 * Allow submitter to specify reportAnnotations, incidentType, and fileAnnotations
 * /fileDetails/fileAnnotations/animeDrawingVirtualHentai
 * /fileDetails/fileAnnotations/potentialMeme
 * /fileDetails/fileAnnotations/viral
 * /fileDetails/fileAnnotations/possibleSelfProduction
 * /fileDetails/fileAnnotations/physicalHarm
 * /fileDetails/fileAnnotations/violenceGore
 * /fileDetails/fileAnnotations/bestiality
 * /fileDetails/fileAnnotations/liveStreaming
 * /fileDetails/fileAnnotations/infant
 * /fileDetails/fileAnnotations/generativeAi
 * Other fields:
 * /fileDetails/originalFileName
 * /fileDetails/locationOfFile
 * /fileDetails/originalFileHash
 * /fileDetails/ipCaptureEvent
 * /fileDetails/deviceId
 * /fileDetails/details
 * /fileDetails/additionalInfo
 * ipCaptureEvent - IP address of the device that captured the image
 * /ipCaptureEvent/ipAddress
 * /ipCaptureEvent/eventName - Login, Registration, Purchase, Upload, Other, Unknown
 * /ipCaptureEvent/dateTime
 * /ipCaptureEvent/possibleProxy - Whether the reporter has reason to believe this IP address is a proxy.
 * reportAnnotations - Tags to describe the report.
 * /reportAnnotations/sextortion
 * /reportAnnotations/csamSolicitation
 * /reportAnnotations/minorToMinorInteraction
 * internetDetails - Details of an incident being reported. Each supplied <internetDetails> element must have exactly one of the following:
 * /internetDetails/webPageIncident
 * /internetDetails/emailIncident
 * /internetDetails/newsgroupIncident
 * /internetDetails/chatImIncident
 * /internetDetails/onlineGamingIncident
 * /internetDetails/cellPhoneIncident
 * /internetDetails/nonInternetIncident
 * /internetDetails/peer2peerIncident
 * webPageIncident - Details for an incident that occurred on a web page.
 * /webPageIncident/url
 * /webPageIncident/additionalInfo
 * /webPageIncident/thirdPartyHostedContent
 * reporter - Information related to the person or company reporting the incident.
 * /reporter/reportingPerson
 * /reporter/companyTemplate
 * personOrUserReported - A reported user or person involved in the incident. This person will be displayed as the suspect.
 * /personOrUserReported/personOrUserReportedPerson - Information about the reported person or user involved in the incident.
 * /personOrUserReported/espIdentifier - The unique ID of the reported person or user in the reporter’s system.
 * /personOrUserReported/espService - The name of the reporter’s product or service that was used by the reported person or user during the incident.
 * /personOrUserReported/screenName
 * /personOrUserReported/displayName
 * /personOrUserReported/profileUrl
 * /personOrUserReported/ipCaptureEvent
 * 
 * <incidentType> 1 The type of incident being reported. Values include:
 *  - Child Pornography (possession, manufacture, and distribution)
 *  - Child Sex Trafficking
 *  - Child Sex Tourism
 *  - Child Sexual Molestation
 *  - Misleading Domain Name
 *  - Misleading Words or Digital Images on the Internet
 *  - Online Enticement of Children for Sexual Acts
 *  - Unsolicited Obscene Material Sent to a Child
 * 
 * <fileDetails> - Details about a file.
 *  <reportId> 1 The report ID to which the details are related.
 *  type long
 *  validation must be a valid CyberTipline report ID
 *  validation reporter must have permission to update the supplied CyberTipline Report

 *  <fileId> 1 The file ID to which the details are related.

 *  type string
 *  validation must not be blank
 *  validation must be a valid file ID for the supplied report ID

 *  <originalFileName> 0|1 The original filename associated with the file when it was uploaded to the company’s servers by the reported user or person.

 *  type string
 *  validation max length is 2056 characters

 *  <locationOfFile> 0|1 The URL where file was originally located.

 *  type string
 *  validation must be a valid URL
 *  validation max length is 2083 characters

 *  <fileViewedByEsp> 0|1 Whether the reporting company viewed the entire contents of the file being reported to NCMEC.

 *  type boolean
 *  validation must be "true" when the value of <exifViewedByEsp> is "true"

 *  <exifViewedByEsp> 0|1 Whether the reporting company viewed the EXIF for the file being reported to NCMEC.

 *  type boolean

 *  <publiclyAvailable> 0|1 Whether the entire contents of the file were publicly accessible to online users.

 *  type boolean

 *  <fileRelevance> 0|1 The relevance or relation of the file to the report. Unless specified otherwise, a file is "Reported" by default. One of the following values:

 *  Reported
 *  Content that is the motivation for making the CyberTipline report, according to the chosen incident type. Only "Reported" files may be identified as potential meme or be given an industry classification.

 *  Supplemental Reported
 *  Supplementary content that provides contextual value to the report. A part of the communication containing apparent child pornography, including any data or information regarding the transmission of the communication or any images, data, or other digital files contained in, or attached to, the communication (18 U.S. Code § 2258A (b)(5)).

 *  type string
 *  validation may not be "Supplemental Reported" if a potential meme annotation is supplied
 *  validation may not be "Supplemental Reported" if an <industryClassification> is supplied

 *  <fileAnnotations> 0|1 Tags to describe the file.

 *  type <fileAnnotations>
 *  <industryClassification>
 *  0|1
 *  A categorization from the ESP-designated categorization scale. One of:
 *  A1
 *  A2
 *  B1
 *  B2

 *  type string

 *  <originalFileHash> 0+ The original binary hash value of the file at the time it was uploaded by the reported user or person (prior to any potential modification by the reporter).

 *  type <originalFileHash>

 *  <ipCaptureEvent> 0|1 An IP address associated with the file.

 *  type <ipCaptureEvent>

 *  <deviceId> 0+ An ID for a device associated with the file.

 *  type <deviceId>

 *  <details> 0+ Metadata associated with the file.

 *  type <details>

 *  <additionalInfo> 0+ Additional information about this file not covered by any other section.

 *  type string
   

 *  <originalFileHash> An original file hash value.

 *    type string hashType 1 attribute
 *    The type of hash (e.g. MD5, SHA1).

 *    type string validation max length is 64 characters
 *  <details> Metadata associated with a file.

 *    <nameValuePair>
 *    1+
 *    A metadata entry.
 *    <name> 1 The name of the metadata entry.

 *    type string
 *    validation max length is 64 characters
 *    <value>
 *    1
 *    The value of the metadata entry.

 *    type string
 *    type
 *    0|1
 *    attribute
 *    The type of the metadata entry. One of:

 *    EXIF

 *    HASH

 *    type string
 */

class NcmecReport
{
  private string $baseUri;
  private string $username;
  private string $password;
  private bool $validateXml;

  private ?string $xsdSchema = null;  // To hold the XSD schema
  public ?string $reportId = null;   // Store the report ID after submission
  public ?string $fileId = null;     // Store the file ID after submission
  private array $reportResponses = [];  // Store the responses from the API calls
  private array $apiRequests = [];      // Store the requests sent to the API 

  public DOMDocument $reportDoc;     // The DOMDocument for the report
  private DOMElement $report;         // The root element of the report

  public function __construct(string $username, string $password, string $env = 'test')
  {
    $this->username = $username;
    $this->password = $password;
    $this->baseUri = ($env === 'production') ? 'https://report.cybertip.org/ispws' : 'https://exttest.cybertip.org/ispws';

    // Decide whether to validate XML based on environment
    $this->validateXml = ($env === 'test');

    // Initialize the report DOMDocument
    $this->reportDoc = new DOMDocument('1.0', 'UTF-8');
    $this->reportDoc->formatOutput = true;

    // Create the root <report> element
    $this->report = $this->reportDoc->createElement('report');
    //$this->report->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
    //$this->report->setAttribute('xsi:noNamespaceSchemaLocation', 'https://exttest.cybertip.org/ispws/xsd');
    $this->reportDoc->appendChild($this->report);

    // Populate the report

    // Fetch the XSD schema if validation is needed
    if ($this->validateXml) {
      $this->xsdSchema = $this->fetchXsdSchema();
    }
  }

  // Fetch the latest XSD schema from the /xsd endpoint
  private function fetchXsdSchema(): string
  {
    $response = $this->sendCurlRequest('/xsd', null, false, true);
    if (!$response) {
      throw new Exception("Failed to fetch XSD schema");
    }
    return $response;  // Store the XSD schema for later use
  }

  // Helper function to send cURL request
  // Helper function to send cURL request
  private function sendCurlRequest(string $endpoint, $data = null, bool $isFile = false, bool $isXsd = false): string
  {
    $url = $this->baseUri . $endpoint;

    $ch = curl_init();
    curl_setopt_array($ch, [
      CURLOPT_URL => $url,
      CURLOPT_USERPWD => $this->username . ':' . $this->password,
      CURLOPT_RETURNTRANSFER => true,
    ]);

    if ($isXsd) {
      // Handle GET request for XSD
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    } else {
      // Prepare the POST request
      curl_setopt($ch, CURLOPT_POST, true);

      if ($isFile) {
        error_log('Sending file, lenght: ' . strlen($data['content']) . ' bytes, name: ' . $data['filename'] . ', type: ' . $data['filetype']);
        // Create a temporary file for the binary data
        $tempFile = tmpfile();
        $tempFilePath = stream_get_meta_data($tempFile)['uri'];
        fwrite($tempFile, $data['content']);

        $evidenceFile = new CURLFile($tempFilePath, $data['filetype'], $data['filename']);
        $submitForm = [
          'id' => $data['id'],
          'file' => $evidenceFile,
        ];

        curl_setopt($ch, CURLOPT_POSTFIELDS, $submitForm);
      } else {
        // Store the API request for debugging
        $this->apiRequests[] = $data->saveXML();
        // Handle XML data
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data->saveXML());
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: text/xml; charset=utf-8']);
      }
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    // Always close the cURL handle
    curl_close($ch);

    // Clean up temporary file if it was created
    if (isset($tempFile)) {
      fclose($tempFile);
    }

    // Handle cURL errors
    if ($curlError) {
      throw new Exception("cURL Error: {$curlError}");
    }

    // Handle HTTP errors
    if ($httpCode !== 200) {
      throw new Exception("HTTP Error: {$httpCode} while requesting {$endpoint}. Response: {$response}");
    }

    return $response;
  }


  // Parse response to handle errors and extract IDs using DOMDocument
  private function parseResponse(string $response): DOMDocument
  {
    $doc = new DOMDocument();
    $doc->loadXML($response);

    // Check for responseCode and throw exceptions for non-zero codes
    $responseCode = $doc->getElementsByTagName('responseCode')->item(0)->nodeValue;
    if ((int)$responseCode !== 0) {
      $responseDescription = $doc->getElementsByTagName('responseDescription')->item(0)->nodeValue;
      throw new Exception("API Error: {$responseDescription}");
    }
    // Store the report response
    $this->reportResponses[] = $doc->saveXML();

    return $doc;
  }

  // Helper function to remove all child nodes of an element
  private function removeChildNodes(DOMElement $element)
  {
    while ($element->hasChildNodes()) {
      $element->removeChild($element->firstChild);
    }
  }

  // Method to set the incident summary
  public function setIncidentSummary(string $incidentType, string $incidentDateTime)
  {
    // Create or find the <incidentSummary> element
    $incidentSummary = $this->reportDoc->getElementsByTagName('incidentSummary')->item(0);
    if (!$incidentSummary) {
      $incidentSummary = $this->reportDoc->createElement('incidentSummary');
      $this->report->appendChild($incidentSummary);
    }

    // Set the incidentType and incidentDateTime
    $incidentTypeEl = $this->reportDoc->createElement('incidentType', $incidentType);
    $incidentDateTimeISO = date('c', strtotime($incidentDateTime));  // ISO 8601 format
    $incidentDateTimeEl = $this->reportDoc->createElement('incidentDateTime', $incidentDateTimeISO);

    // Remove existing elements if any
    $this->removeChildNodes($incidentSummary);

    $incidentSummary->appendChild($incidentTypeEl);
    $incidentSummary->appendChild($incidentDateTimeEl);
  }

  // Method to set internet details
  public function setInternetDetails(array $details)
  {
    // Create or find the <internetDetails> element
    $internetDetails = $this->reportDoc->getElementsByTagName('internetDetails')->item(0);
    if (!$internetDetails) {
      $internetDetails = $this->reportDoc->createElement('internetDetails');
      $this->report->appendChild($internetDetails);
    }

    // Remove existing child nodes
    $this->removeChildNodes($internetDetails);

    // Depending on the type of incident, create the appropriate incident element
    if (isset($details['webPageIncident'])) {
      $webPageIncident = $this->reportDoc->createElement('webPageIncident');
      $internetDetails->appendChild($webPageIncident);

      if (isset($details['webPageIncident']['url'])) {
        $urlEl = $this->reportDoc->createElement('url', $details['webPageIncident']['url']);
        $webPageIncident->appendChild($urlEl);
      }
      if (isset($details['webPageIncident']['additionalInfo'])) {
        $additionalInfoEl = $this->reportDoc->createElement('additionalInfo', $details['webPageIncident']['additionalInfo']);
        $webPageIncident->appendChild($additionalInfoEl);
      }
      // Add other elements as needed
    }

    // Similarly handle other types of incidents
  }

  // Method to set reporter details
  public function setReporter(array $reporterDetails)
  {
    // Create or find the <reporter> element
    $reporter = $this->reportDoc->getElementsByTagName('reporter')->item(0);
    if (!$reporter) {
      $reporter = $this->reportDoc->createElement('reporter');
      $this->report->appendChild($reporter);
    }

    // Create or find the <reportingPerson> element
    $reportingPerson = null;
    if ($reporter instanceof DOMElement) {
      $reportingPerson = $reporter->getElementsByTagName('reportingPerson')->item(0);
    }
    if (!$reportingPerson) {
      $reportingPerson = $this->reportDoc->createElement('reportingPerson');
      $reporter->appendChild($reportingPerson);
    }

    // Remove existing child nodes
    $this->removeChildNodes($reportingPerson);

    // Add the reporter details
    if (isset($reporterDetails['firstName'])) {
      $firstNameEl = $this->reportDoc->createElement('firstName', $reporterDetails['firstName']);
      $reportingPerson->appendChild($firstNameEl);
    }
    if (isset($reporterDetails['lastName'])) {
      $lastNameEl = $this->reportDoc->createElement('lastName', $reporterDetails['lastName']);
      $reportingPerson->appendChild($lastNameEl);
    }
    if (isset($reporterDetails['email'])) {
      $emailEl = $this->reportDoc->createElement('email', $reporterDetails['email']);
      $reportingPerson->appendChild($emailEl);
    }
    // Add other elements as needed
  }

  // Method to add person or user reported
  public function addPersonOrUserReported(array $personDetails)
  {
    // Create the <personOrUserReported> element
    $personOrUserReported = $this->reportDoc->createElement('personOrUserReported');
    $this->report->appendChild($personOrUserReported);

    // Add the elements
    if (isset($personDetails['personOrUserReportedPerson'])) {
      $personEl = $this->reportDoc->createElement('personOrUserReportedPerson');

      // Add person details
      foreach ($personDetails['personOrUserReportedPerson'] as $key => $value) {
        $el = $this->reportDoc->createElement($key, $value);
        $personEl->appendChild($el);
      }

      $personOrUserReported->appendChild($personEl);
    }

    foreach (['espIdentifier', 'espService', 'screenName', 'displayName', 'profileUrl'] as $key) {
      if (isset($personDetails[$key])) {
        $el = $this->reportDoc->createElement($key, $personDetails[$key]);
        $personOrUserReported->appendChild($el);
      }
    }

    if (isset($personDetails['ipCaptureEvent'])) {
      $ipCaptureEventEl = $this->reportDoc->createElement('ipCaptureEvent');

      foreach ($personDetails['ipCaptureEvent'] as $key => $value) {
        $el = $this->reportDoc->createElement($key, $value);
        $ipCaptureEventEl->appendChild($el);
      }

      $personOrUserReported->appendChild($ipCaptureEventEl);
    }
  }

  // Method to get the report XML as a string (for previewing)
  public function getReportXml(): string
  {
    return $this->reportDoc->saveXML();
  }

  // Method to validate the XML (if validation is enabled)
  private function validateXml()
  {
    if ($this->validateXml) {
      if (!$this->xsdSchema) {
        throw new Exception("XSD schema is not available for validation");
      }
      libxml_use_internal_errors(true);
      if (!$this->reportDoc->schemaValidateSource($this->xsdSchema)) {
        $errors = libxml_get_errors();
        libxml_clear_errors();
        $errorMessages = '';
        foreach ($errors as $error) {
          $errorMessages .= $error->message . "\n";
        }
        throw new Exception("XML validation failed: " . $errorMessages);
      }
    }
  }

  // Method to submit the report
  public function submitReport()
  {
    // Validate the XML before submission
    $this->validateXml();

    // Send the request
    //error_log($this->reportDoc->saveXML());
    $response = $this->sendCurlRequest('/submit', $this->reportDoc);
    //error_log($response);
    $parsedResponse = $this->parseResponse($response);

    // Extract the report ID
    $this->reportId = $parsedResponse->getElementsByTagName('reportId')->item(0)->nodeValue;

    return ['reportId' => $this->reportId];
  }

  // Upload a file to the report
  public function uploadFile(string $fileId, string $binaryData, string $fileName, string $mimeType): array
  {
    if (!$this->reportId) {
      throw new Exception("Report ID is not set. Submit the report first.");
    }

    $fields = [
      'id' => $this->reportId,
      'fileid' => $fileId,
      'filetype' => $mimeType,
      'filename' => $fileName,
      'content' => $binaryData
    ];
    error_log("Uploading file with ID: " . $fileId);

    $response = $this->sendCurlRequest('/upload', $fields, true);
    $parsedResponse = $this->parseResponse($response);
    $this->fileId = $parsedResponse->getElementsByTagName('fileId')->item(0)->nodeValue;
    error_log("File ID: " . $this->fileId);

    return ['fileId' => $fileId];
  }

  // Build the file details XML (updated to include all necessary fields)
  public function buildFileDetailsXml(array $fileDetails): DOMDocument
  {
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->formatOutput = true;

    // Create <fileDetails> root element
    $fileDetailsEl = $doc->createElement('fileDetails');
    $fileDetailsEl->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
    $fileDetailsEl->setAttribute('xsi:noNamespaceSchemaLocation', 'https://exttest.cybertip.org/ispws/xsd');
    $doc->appendChild($fileDetailsEl);

    // Add the necessary elements
    $fileDetailsEl->appendChild($doc->createElement('reportId', $this->reportId));
    $fileDetailsEl->appendChild($doc->createElement('fileId', $this->fileId));

    // Add all relevant fields including 'fileViewedByEsp' and 'publiclyAvailable'
    $fieldsToAdd = [
      'originalFileName',
      'locationOfFile',
      'fileRelevance',
      'fileViewedByEsp',
      'publiclyAvailable',
    ];

    foreach ($fieldsToAdd as $key) {
      if (isset($fileDetails[$key])) {
        $el = $doc->createElement($key, $fileDetails[$key]);
        $fileDetailsEl->appendChild($el);
      }
    }

    if (isset($fileDetails['ipCaptureEvent'])) {
      $ipCaptureEventEl = $doc->createElement('ipCaptureEvent');
      foreach ($fileDetails['ipCaptureEvent'] as $key => $value) {
        $el = $doc->createElement($key, $value);
        $ipCaptureEventEl->appendChild($el);
      }
      $fileDetailsEl->appendChild($ipCaptureEventEl);
    }

    // add additionalInfo
    if (isset($fileDetails['additionalInfo'])) {
      $additionalInfoEl = $doc->createElement('additionalInfo', $fileDetails['additionalInfo']);
      $fileDetailsEl->appendChild($additionalInfoEl);
    }

    if (isset($fileDetails['fileAnnotations'])) {
      $fileAnnotationsEl = $doc->createElement('fileAnnotations');
      foreach ($fileDetails['fileAnnotations'] as $annotation => $value) {
        $annotationEl = $doc->createElement($annotation);
        $fileAnnotationsEl->appendChild($annotationEl);
      }
      $fileDetailsEl->appendChild($fileAnnotationsEl);
    }

    return $doc;
  }

  // Submit additional file details
  public function submitFileDetails(DOMDocument $fileDetailsDoc)
  {
    // Send the request and ensure it's successful
    $response = $this->sendCurlRequest('/fileinfo', $fileDetailsDoc);
    $parsedResponse = $this->parseResponse($response);
    error_log($parsedResponse->saveXML());

    return ['status' => 'success'];
  }

  // Finish the report submission
  public function finishReport(): array
  {
    if (!$this->reportId) {
      throw new Exception("Report ID is not set. Submit the report first.");
    }

    // Prepare the data
    $data = ['id' => $this->reportId];

    // Send the request
    $response = $this->sendCurlRequest('/finish', $data);
    $parsedResponse = $this->parseResponse($response);

    // Return the report ID and associated file IDs
    $fileIds = [];
    foreach ($parsedResponse->getElementsByTagName('fileId') as $fileIdNode) {
      $fileIds[] = $fileIdNode->nodeValue;
    }

    return [
      'reportId' => $parsedResponse->getElementsByTagName('reportId')->item(0)->nodeValue,
      'files' => $fileIds
    ];
  }

  public function getAllResponses(): array
  {
    return $this->reportResponses;
  }

  public function getAllRequests(): array
  {
    return $this->apiRequests;
  }

  // Cancel the report before finishing
  public function cancelReport(): array
  {
    if (!$this->reportId) {
      throw new Exception("Report ID is not set. Cannot cancel.");
    }

    // Prepare the data
    $data = ['id' => $this->reportId];
    error_log(PHP_EOL . "Cancelling report with ID: " . $this->reportId . PHP_EOL);

    // Send the request
    $response = $this->sendCurlRequest('/retract', $data);
    $this->parseResponse($response);

    // Reset the report ID
    $this->reportId = null;

    // Log all responses
    error_log("All responses: " . json_encode($this->reportResponses));

    return ['status' => 'cancelled'];
  }
}
