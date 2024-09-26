<?php
// submit_report.php
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

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['incidentId'])) {
  http_response_code(400);
  echo json_encode(['error' => 'Incident ID is required.']);
  exit;
}

$incidentId = intval($data['incidentId']);
$testReport = isset($data['testReport']) && $data['testReport'] === 'true';

try {
  // Create a NCMECReportHandler instance
  $ncmecReportHandler = new NCMECReportHandler($incidentId, $testReport);
  $response = $ncmecReportHandler->processAndReportViolation();
  echo json_encode($response);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Error submitting report: ' . $e->getMessage()]);
}
