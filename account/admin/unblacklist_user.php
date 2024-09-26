<?php
// unblacklist_user.php
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

// Check if the user is an admin
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php');
$perm = new Permission();

if (!$perm->isAdmin()) {
  http_response_code(403);
  echo json_encode(['error' => 'Unauthorized access.']);
  exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['incidentId'])) {
  http_response_code(400);
  echo json_encode(['error' => 'Incident ID is required.']);
  exit;
}

$incidentId = intval($data['incidentId']);

try {
  // Create a NCMECReportHandler instance
  $ncmecReportHandler = new NCMECReportHandler($incidentId, false);

  // Call the unBlacklistUser method
  $result = $ncmecReportHandler->unBlacklistUser();

  if ($result) {
    // Update report id as FALSE_MATCH
    echo json_encode(['success' => true]);
  } else {
    echo json_encode(['success' => false, 'error' => 'Failed to unblacklist user. User may not be blacklisted or an error occurred.']);
  }
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Error unblacklisting user: ' . $e->getMessage()]);
}
