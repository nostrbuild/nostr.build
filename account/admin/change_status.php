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

header('Content-Type: application/json');

// Create new Permission object
$perm = new Permission();

// Check if the user is logged in, if not then redirect him to login page
if (!$perm->isAdmin() && !$perm->hasPrivilege('canModerate')) {
    echo json_encode(['success' => false, 'error' => 'User not logged in or has no permissions']);
    // PERSIST: $link->close();
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
                $s3->deleteFromS3(objectKey: $objectName, paidAccount: false);
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
        } elseif ($status === 'csam' && $perm->isAdmin() && $filename !== null) {
            // We will need to collect evidence and report the user to the authorities
            // First we will copy an offending image to the evidence bucket and then delete it while marking it rejected
            // then, we will fetch upload log and copy that to the evidence bucket
            // finally, we will parse the upload log to find IP, user agent and (if available) user npub and store it in the rejected table to prevent further uploads
            // Blacklisting table will look like this:
            // id | npub | ip | user_agent | timestamp | reason
            /*
            CREATE TABLE blacklist (
                id INT AUTO_INCREMENT PRIMARY KEY,
                npub VARCHAR(255),
                ip VARCHAR(45),
                user_agent VARCHAR(255),
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                reason VARCHAR(255)
            );

            CREATE INDEX idx_blacklist_npub ON blacklist (npub);
            CREATE INDEX idx_blacklist_ip ON blacklist (ip);

            CREATE TABLE identified_csam_cases (
                id INT AUTO_INCREMENT PRIMARY KEY,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                identified_by_npub VARCHAR(255),
                evidence_location_url VARCHAR(255),
                file_sha256_hash VARCHAR(64),
                logs JSON
            );

            CREATE INDEX idx_identified_csam_cases_timestamp ON identified_csam_cases (timestamp);
            CREATE INDEX idx_identified_csam_cases_file_sha256_hash ON identified_csam_cases (file_sha256_hash);
            */
            // we cannot use IP to block uploads, since it can be easily changed, but we can use it to find the user

            // Get the hash from filename
            $file_sha256_hash = pathinfo($filename, PATHINFO_FILENAME);
            // Download offending media into a temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'csam_');
            $objectName = ($type === 'picture') ? 'i/' . $filename : 'av/' . $filename;
            // Even if file has been marked with http-451, we can still download it from R2 directly
            $tempFile = $s3->downloadObjectR2(key: $objectName, saveAs: $tempFile, paidAccount: false);

            // Get the upload logs from R2
            $logsJSON = fetchJsonFromR2Bucket(
                prefix: "{$file_sha256_hash}",
                endPoint: $csamReportingConfig['r2EndPoint'],
                accessKey: $csamReportingConfig['r2AccessKey'],
                secretKey: $csamReportingConfig['r2SecretKey'],
                bucket: $csamReportingConfig['r2LogsBucket']
            );
            // Check if we have logs
            /* JSON has the following structure:
            <R2 object key> : {
                "fileHash": "<sha256hash>",
                "fileName": "<sha256hash>.<ext>",
                "fileSize": <size in bytes>,
                "fileMimeType": "<mime_type>",
                "fileUrl": "<shared public URL>",
                "fileType": "<type: image, video, etc>",
                "shouldTranscode": false,
                "uploadAccountType": "<free, paid>",
                "uploadTime": <unix epoch timestamp>,
                "orginalSha256Hash": "<sha256hash>",
                "uploadNpub": "<npub1....>",
                "uploadedFileInfo": "<json string of upload info: { \"userAgent\": \"<long UA string>\", \"forwardedFor\": \"<forwarded for header content>\", \"realIp\": \"<IP address of uploader>\", \"referer\": \"<referer header>\" }"
            },
            ...
            */
            // In some cases we may not have logs, so we need to check if we have them
            if (empty($logsJSON)) {
                // Output error response
                //echo json_encode(['success' => false, 'error' => 'Failed to fetch logs']);
                //unlink($tempFile);
                //exit;
                // Log the error and continue
                error_log("Failed to fetch logs for CSAM report: {$file_sha256_hash}");
            }
            if (!empty($logsJSON)) {
                // Now lets store logs & file to the evidence bucket
                $evidenceLogKey = "{$file_sha256_hash}/uploads_log.json";
                // Store the logs
                $resLogStore = storeJSONObjectToR2Bucket(
                    object: $logsJSON,
                    destinationKey: $evidenceLogKey,
                    destinationBucket: $csamReportingConfig['r2EvidenceBucket'],
                    endPoint: $csamReportingConfig['r2EndPoint'],
                    accessKey: $csamReportingConfig['r2AccessKey'],
                    secretKey: $csamReportingConfig['r2SecretKey'],
                );
            }
            // Store the file
            $evidenceFileKey = "{$file_sha256_hash}/{$filename}";
            $resFileStore = storeToR2Bucket(
                sourceFilePath: $tempFile,
                destinationKey: $evidenceFileKey,
                destinationBucket: $csamReportingConfig['r2EvidenceBucket'],
                endPoint: $csamReportingConfig['r2EndPoint'],
                accessKey: $csamReportingConfig['r2AccessKey'],
                secretKey: $csamReportingConfig['r2SecretKey'],
            );
            // Check if we have stored logs and file
            if (($resLogStore === false && !empty($logsJSON)) || $resFileStore === false) {
                // Output error response
                echo json_encode(['success' => false, 'error' => 'Failed to store logs or file']);
                unlink($tempFile);
                exit;
            }
            // Unlink the temporary file
            unlink($tempFile);

            // Perform storage of info about the case in DB
            $stmt = $link->prepare("INSERT INTO identified_csam_cases (identified_by_npub, evidence_location_url, file_sha256_hash, logs) VALUES (?, ?, ?, ?)");
            // Prepare bind parameters
            $evidenceReportingNpub = $_SESSION['usernpub'];
            $evidenceLocationURL = "{$csamReportingConfig['r2EndPoint']}/{$csamReportingConfig['r2EvidenceBucket']}/{$file_sha256_hash}/";
            $evidenceFileSha256Hash = $file_sha256_hash;
            $evidenceJSONLogs = json_encode($logsJSON);
            // Bind parameters
            $stmt->bind_param("ssss", $evidenceReportingNpub, $evidenceLocationURL, $evidenceFileSha256Hash, $evidenceJSONLogs);
            // Execute the statement
            $stmt->execute();
            // Close the statement
            $stmt->close();

            // Parse JSON logs to find IPs, UA and NPUBs
            // We "shoot" first and then ask questions, so we will block all NPUBs associated with the CSAM report
            if (!empty($logsJSON)) {
                $stmt = $link->prepare("INSERT INTO blacklist (npub, ip, user_agent, reason) VALUES (?, ?, ?, ?)");
                foreach ($logsJSON as $key => $log) {
                    $logData = json_decode($log['uploadedFileInfo'], true);
                    $ip = $logData['realIp'];
                    $ua = $logData['userAgent'];
                    $npub = $log['uploadNpub'] ?? "anonymous";
                    $blockReason = 'CSAM';
                    // Insert the row into the 'blacklist' table
                    $stmt->bind_param("ssss", $npub, $ip, $ua, $blockReason);
                    $stmt->execute();
                }
                $stmt->close();
            }

            // Delete the file if it is marked as CSAM
            $objectName = ($type === 'picture') ? 'i/' . $filename : 'av/' . $filename;

            // Delete requests are free, so we don't bother checking if the object exists
            try {
                $s3->deleteFromS3(objectKey: $objectName, paidAccount: false);
                $purger = new CloudflarePurger($_SERVER['NB_API_SECRET'], $_SERVER['NB_API_PURGE_URL']);
                $result = $purger->purgeFiles([$filename]);
                if ($result !== false) {
                    error_log(json_encode($result));
                }
            } catch (Exception $e) {
                error_log("PURGE error occurred: " . $e->getMessage() . "\n");
            }

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
    // PERSIST: $link->close();
} else {
    // Output error response
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}
