<?php
// Include config file
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/functions/session.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/S3Service.class.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/CloudflarePurge.class.php');

global $link;

header('Content-Type: application/json');

// Create new Permission object
$perm = new Permission();

// Check if the user is logged in, if not then redirect him to login page
if (!$perm->isAdmin() && !$perm->hasPrivilege('canModerate')) {
    echo json_encode(['success' => false, 'error' => 'User not logged in or has no permissions']);
    $link->close();
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Try to get the JSON payload from the POST request
    $jsonPayload = @file_get_contents('php://input');

    if ($jsonPayload === FALSE) {
        // Handle error - unable to read POST data
        http_response_code(400);
        echo json_encode(['error' => 'Unable to read POST data']);
        exit;
    }

    $data = json_decode($jsonPayload, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        // Handle error - JSON decode failed
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    if (!isset($data['ids']) || !is_array($data['ids'])) {
        // Handle error - unexpected payload
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payload, expecting an "ids" array']);
        exit;
    }

    // Prepare an SQL query with placeholders for the image IDs
    $ids_str = implode(',', array_fill(0, count($data['ids']), '?'));

    // SQL query to update records
    $sql = "UPDATE uploads_data SET approval_status='approved' WHERE id IN ($ids_str) AND approval_status='pending'";

    // Prepare and execute the SQL statement
    $stmt = $link->prepare($sql);
    $stmt->bind_param(str_repeat('i', count($data['ids'])), ...$data['ids']);

    // Execute the prepared statement
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }

    // Close the prepared statement
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
