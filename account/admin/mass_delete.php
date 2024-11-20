<?php
// Include config file
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/S3Service.class.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/CloudflarePurge.class.php');

global $link;
global $awsConfig;
global $csamReportingConfig;

$s3 = new S3Service($awsConfig);


// Create new Permission object
$perm = new Permission();

// Check if the user is logged in, if not then redirect him to login page
if (!$perm->isAdmin()) {
    echo json_encode(['success' => false, 'error' => 'User not logged in or has no permissions']);
    $link->close(); // CLOSE MYSQL LINK
    exit;
}

// If request method is get, return the form
if ($_SERVER["REQUEST_METHOD"] == "GET") :
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <title>Mass Delete</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>

    <body>
        <div class="container">
            <h2>Mass Delete</h2>
            <form id="mass_delete_form">
                <div class="form-group">
                    <label for="file_list">File List:</label>
                    <textarea class="form-control" id="file_list" name="file_list" rows="10"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Submit</button>
            </form>
            <div id="response" class="mt-3"></div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('mass_delete_form').addEventListener('submit', function(e) {
                    e.preventDefault();
                    var formData = new FormData(this);
                    fetch('/account/admin/mass_delete.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(function(response) {
                            if (response.ok) {
                                return response.json();
                            } else {
                                throw new Error('An error occurred');
                            }
                        })
                        .then(function(data) {
                            if (data.success) {
                                document.getElementById('response').innerHTML = '<div class="alert alert-success" role="alert">Files deleted successfully</div>';
                                // Clear the file list
                                document.getElementById('file_list').value = '';
                                // Show number of files deleted
                                document.getElementById('response').innerHTML += '<div class="alert alert-info" role="alert">Files deleted: ' + data.deleted + '</div>';
                            } else {
                                document.getElementById('response').innerHTML = '<div class="alert alert-danger" role="alert">Error: ' + data.error + '</div>';
                            }
                        })
                        .catch(function(error) {
                            document.getElementById('response').innerHTML = '<div class="alert alert-danger" role="alert">' + error.message + '</div>';
                        });
                });
            });
        </script>
    </body>

    </html>

<?php
endif;
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');
    if (isset($_POST['file_list'])) {
        // Get the file list from the POST request text field
        $fileList = $_POST['file_list'];
        // Break the file list into an array
        $fileList = explode("\n", $fileList);
        // Remove any empty elements
        $fileList = array_filter($fileList);
        // trim each element
        $fileList = array_map('trim', $fileList);

        // Process in batches of 64 files
        $batchSize = 64;
        $batches = array_chunk($fileList, $batchSize);

        // Process each batch
        foreach ($batches as $batch) {
            // Select type of file from the uploads_data table
            $batch = array_values($batch);
            // Strip the filenames of any whitespace
            $batch = array_map('trim', $batch);
            error_log("Processing batch of " . count($batch) . " files");
            $fileTypeMap = [];
            $placeholders = implode(',', array_fill(0, count($batch), '?'));
            $stmt = $link->prepare("SELECT type, filename FROM uploads_data WHERE filename IN ($placeholders)");
            $stmt->bind_param(str_repeat('s', count($batch)), ...$batch);
            $stmt->execute();
            $stmt->bind_result($type, $filename);

            while ($stmt->fetch()) {
                $fileTypeMap[$filename] = $type;
            }
            $stmt->close();
            error_log("Found " . count($fileTypeMap) . " files in uploads_data");

            // Delete from S3
            foreach ($fileTypeMap as $filename => $type) {
                $objectName = ($type === 'video') ? 'av/' . $filename : (($type === 'profile') ? 'i/p/' . $filename : 'i/' . $filename);
                $s3->deleteFromS3(objectKey: $objectName, paidAccount: false);
            }

            error_log("Processing batch of " . count($batch) . " files");
            $purger = new CloudflarePurger($_SERVER['NB_API_SECRET'], $_SERVER['NB_API_PURGE_URL']);
            try {
                // Convert $batch into an array
                $result = $purger->purgeFiles($batch);
                if ($result !== false) {
                    error_log(json_encode($result));
                }
            } catch (Exception $e) {
                error_log("PURGE error occurred: " . $e->getMessage() . "\n");
            }

            // Delete the row from the 'uploads_data' table
            $placeholders = implode(',', array_fill(0, count($batch), '?'));
            $stmt = $link->prepare("DELETE FROM uploads_data WHERE filename IN ($placeholders)");
            $stmt->bind_param(str_repeat('s', count($batch)), ...$batch);
            $stmt->execute();
            $affected_rows_uploads_data = $stmt->affected_rows; // Get the affected rows from the statement
            $stmt->close();
            error_log("Deleted " . $affected_rows_uploads_data . " rows from uploads_data");

            // Delete the rows from the upload_attempts table
            // filename in upload attempts lacks the extension, so we prepare the batch again
            $batch_attempts = array_map(function ($filename) {
                return pathinfo($filename, PATHINFO_FILENAME);
            }, $batch);
            $stmt = $link->prepare("DELETE FROM upload_attempts WHERE filename IN ($placeholders)");
            $stmt->bind_param(str_repeat('s', count($batch_attempts)), ...$batch_attempts);
            $stmt->execute();
            $affected_rows_upload_attempts = $stmt->affected_rows; // Get the affected rows from the statement
            $stmt->close();
            error_log("Deleted " . $affected_rows_upload_attempts . " rows from upload_attempts");
        }
        // Output success response
        echo json_encode(['success' => true, 'deleted' => count($fileList)]);
    } else {
        // Output error response
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    }

    // Close connection
    $link->close(); // CLOSE MYSQL LINK
}
