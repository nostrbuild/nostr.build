<?php
// get_evidence.php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/NCMECReportHandler.class.php');

// Create new Permission object
$perm = new Permission();

if (!$perm->isAdmin()) {
  http_response_code(403);
  echo 'Unauthorized: ' . $e->getMessage();
  exit;
}

// Get incidentId from GET parameters
if (!isset($_GET['incidentId'])) {
  http_response_code(400);
  echo 'Incident ID is required.';
  exit;
}

$incidentId = intval($_GET['incidentId']);

try {
  // Create a NCMECReportHandler instance
  $ncmecReportHandler = new NCMECReportHandler($incidentId);
  $imgTag = $ncmecReportHandler->getEvidenceImgTag(500, 500);
  if ($imgTag === '') {
    throw new Exception('Unable to retrieve evidence image.');
  }
  echo $imgTag;
} catch (Exception $e) {
  http_response_code(500);
  echo 'Error fetching evidence: ' . htmlspecialchars($e->getMessage());
}
