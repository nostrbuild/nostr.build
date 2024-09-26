<?php
// preview_report.php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/NCMECReportHandler.class.php');

// Create new Permission object
$perm = new Permission();

if (!$perm->isAdmin()) {
  http_response_code(403);
  echo json_encode(['error' => 'Unauthorized: ' . $e->getMessage()]);
  exit;
}

header('Content-Type: application/json');

// Get incidentId and testReport from GET parameters
if (!isset($_GET['incidentId'])) {
  http_response_code(400);
  echo json_encode(['error' => 'Incident ID is required.']);
  exit;
}

$incidentId = intval($_GET['incidentId']);
$testReport = isset($_GET['testReport']) && $_GET['testReport'] === 'true';

try {
  // Create a NCMECReportHandler instance
  $ncmecReportHandler = new NCMECReportHandler($incidentId, $testReport);
  // Get the sanitized report data
  $sanitizedReport = $ncmecReportHandler->previewReport();
  echo json_encode($sanitizedReport);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Error generating report preview: ' . $e->getMessage()]);
}
