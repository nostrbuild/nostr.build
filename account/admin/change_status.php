<?php
// Include config file
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/functions/session.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/S3Service.class.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/CloudflarePurge.class.php');

global $link;
global $awsConfig;

$s3 = new S3Service($awsConfig);

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
    if (isset($_POST['id']) && isset($_POST['status'])) {
        $id = $_POST['id'];
        $status = $_POST['status'];

        // Get the filename and type from the 'uploads_data' table
        $stmt = $link->prepare("SELECT filename, type FROM uploads_data WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->bind_result($filename, $type);
        $stmt->fetch();
        $stmt->close();

        // Delete the file if it is rejected
        if ($status === 'rejected' && $filename !== null) {
            $objectName = ($type === 'picture') ? 'i/' . $filename : 'av/' . $filename;

            // Delete requests are free, so we don't bother checking if the object exists
            try {
                $s3->deleteFromS3($objectName); // TODO: Remove when S3 purge is done
                $purger = new CloudflarePurger($_SERVER['NB_API_SECRET'], $_SERVER['NB_API_PURGE_URL']);
                $result = $purger->purgeFiles([$filename]);
                if ($result !== false) {
                    error_log(json_encode($result));
                }
            } catch (Exception $e) {
                error_log("PURGE error occurred: " . $e->getMessage() . "\n");
            }

            // Insert the rejected file into the 'rejected_files' table
            $stmt = $link->prepare("INSERT INTO rejected_files (filename, type) VALUES (?, ?)");
            $stmt->bind_param("ss", $filename, $type);
            $stmt->execute();
            $stmt->close();

            // Delete the row from the 'uploads_data' table
            $stmt = $link->prepare("DELETE FROM uploads_data WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        } else {
            // Update the status in the 'uploads_data' table
            $stmt = $link->prepare("UPDATE uploads_data SET approval_status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $id);
            $stmt->execute();
            $stmt->close();
        }

        // Output success response
        echo json_encode(['success' => true]);
    } else {
        // Output error response
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    }

    // Close connection
    $link->close();
} else {
    // Output error response
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}
