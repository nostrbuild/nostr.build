<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/SiteConfig.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/NCMECReportHandler.class.php');

// Create new Permission object
$perm = new Permission();

if (!$perm->isAdmin()) {
  header("location: /login");
  exit;
}

// For file search
$searchFile = '';
if (isset($_POST['searchFile'])) {
  $path = parse_url($_POST['searchFile'], PHP_URL_PATH);
  // Get the filename
  $searchFile = basename($path);
}

// Set HTTP headers to prevent all caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

/*
Table: identified_csam_cases
+------------------------+--------------+------+-----+-------------------+-------------------+
| Field                  | Type         | Null | Key | Default           | Extra             |
+------------------------+--------------+------+-----+-------------------+-------------------+
| id                     | int          | NO   | PRI | NULL              | auto_increment    |
| timestamp              | timestamp    | YES  | MUL | CURRENT_TIMESTAMP | DEFAULT_GENERATED |
| identified_by_npub     | varchar(255) | YES  |     | NULL              |                   |
| evidence_location_url  | varchar(255) | YES  |     | NULL              |                   |
| file_sha256_hash       | varchar(64)  | YES  | MUL | NULL              |                   |
| logs                   | json         | YES  |     | NULL              |                   |
| ncmec_submitted_report | json         | YES  |     | NULL              |                   |
| ncmec_report_id        | varchar(255) | YES  |     | NULL              |                   |
+------------------------+--------------+------+-----+-------------------+-------------------+
*/
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <title>Admin CSAM Cases</title>
  <!-- NO CACHE meta tags -->
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
  <meta http-equiv="Pragma" content="no-cache" />
  <meta http-equiv="Expires" content="0" />
  <meta charset="UTF-8">
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    body {
      font-family: Arial, sans-serif;
      padding: 0 10px;
    }

    .main-content {
      margin-top: 20px;
    }

    .btn-xsm {
      font-size: 10px;
      padding: 0.25rem 0.5rem;
      line-height: 1.5;
      border-radius: 0.2rem;
    }

    .table-responsive {
      overflow-x: auto;
    }

    .table td,
    .table th {
      vertical-align: middle;
    }

    .text-truncate {
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .table td {
      font-size: 14px;
    }
  </style>
</head>

<body>
  <main class="container main-content">
    <section class="title_section">
      <h1>Admin CSAM Cases</h1>
    </section>
    <!-- Add Search Box -->
    <form method="post" class="mb-3">
      <div class="input-group">
        <input type="text" class="form-control" name="searchFile" placeholder="Enter file hash to search" value="<?= htmlspecialchars($searchFile) ?>">
        <button class="btn btn-primary" type="submit">Search</button>
      </div>
    </form>
    <?php
    // Query to get the total count of CSAM cases and total reported
    $sql = <<<SQL
        SELECT 
            COUNT(*) AS total_count,
            COUNT(CASE 
                    WHEN ncmec_report_id IS NOT NULL 
                    AND ncmec_report_id NOT LIKE 'TEST_%' 
                    AND ncmec_report_id NOT LIKE 'FALSE_MATCH' 
                    THEN 1 
                END) AS total_reported
        FROM 
            identified_csam_cases;
        SQL;
    $result = $link->query($sql);
    $row = $result->fetch_assoc();
    $totalCount = $row['total_count'];
    $totalReported = $row['total_reported'];
    $result->close();

    echo '<p class="fw-bold">Total CSAM cases: <span class="text-primary">' . htmlspecialchars($totalCount) . '</span></p>';
    echo '<p class="fw-bold">Total Reported: <span class="text-primary">' . htmlspecialchars($totalReported) . '</span></p>';

    // Query to get the number of cases per day for the past week
    $sql = <<<SQL
        SELECT 
            DATE(timestamp) AS report_date,
            COUNT(*) AS total_count,
            COUNT(CASE 
                    WHEN ncmec_report_id IS NOT NULL 
                    AND ncmec_report_id NOT LIKE 'TEST_%' 
                    AND ncmec_report_id NOT LIKE 'FALSE_MATCH' 
                    THEN 1 
                END) AS total_reported
        FROM 
            identified_csam_cases
        WHERE 
            timestamp >= CURDATE() - INTERVAL 7 DAY
        GROUP BY 
            DATE(timestamp)
        ORDER BY 
            report_date DESC;
        SQL;
    $result = $link->query($sql);

    // Start the table
    echo '<table class="table table-striped">';
    echo '<thead><tr><th>Date</th><th>Created Cases</th><th>Submitted Cases</th></tr></thead>';
    echo '<tbody>';

    // Populate the table with the array data
    while ($row = $result->fetch_assoc()) {
      $recordDate = $row['report_date'];
      $recordCount = $row['total_count'];
      $reportCount = $row['total_reported'];

      echo '<tr><td>' . htmlspecialchars($recordDate) . '</td><td>' . htmlspecialchars($recordCount) . '</td><td>' . htmlspecialchars($reportCount) . '</td></tr>';
    }

    echo '</tbody></table>';

    // Display the CSAM cases in a table
    $perpage = 50;
    $page = isset($_GET['p']) ? intval($_GET['p']) : 0;
    $start = $page * $perpage;
    $limit = $perpage;

    // If search is specified
    if (!empty($searchFile)) {
      $sql = "SELECT * FROM identified_csam_cases WHERE file_sha256_hash = ? ORDER BY timestamp DESC";
      $stmt = $link->prepare($sql);
      $stmt->bind_param('s', $searchFile);
    } else {
      $sql = "SELECT * FROM identified_csam_cases ORDER BY timestamp DESC LIMIT ?, ?";
      $stmt = $link->prepare($sql);
      $stmt->bind_param('ii', $start, $limit);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    // Admin's npub to indicate who marked media as CSAM
    $admin_npub = $_SESSION['usernpub'];

    // Start the table
    echo '<div class="table-responsive">';
    echo '<table class="table table-bordered">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>ID</th>';
    echo '<th>Timestamp</th>';
    echo '<th style="max-width: 150px;">Identified By</th>';
    echo '<th>Evidence URL</th>';
    echo '<th style="max-width: 200px;">File Hash</th>';
    echo '<th>NCMEC Report ID</th>';
    echo '<th>Actions</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    while ($row = $result->fetch_assoc()) {
      $id = $row['id'];
      $timestamp = $row['timestamp'];
      $identified_by_npub = $row['identified_by_npub'];
      $evidence_location_url = $row['evidence_location_url'];
      $file_sha256_hash = $row['file_sha256_hash'];
      $ncmec_report_id = $row['ncmec_report_id'];

      echo '<tr>';
      echo '<td>' . htmlspecialchars($id) . '</td>';
      echo '<td>' . htmlspecialchars($timestamp) . '</td>';
      echo '<td>';
      echo '<div style="max-width: 150px;" class="text-truncate">';
      echo ($admin_npub === $identified_by_npub ? '<b>YOU</b>' : htmlspecialchars($identified_by_npub));
      echo '</div>';
      echo '</td>';
      echo '<td><a href="' . htmlspecialchars($evidence_location_url) . '" target="_blank">Link</a></td>';
      echo '<td>';
      echo '<div style="max-width: 200px;" class="text-truncate">';
      echo htmlspecialchars($file_sha256_hash);
      echo '</div>';
      echo '</td>';
      // Change background color to green if the report is submitted and id is numeric or FALSE_MATCH
      $bgColor = (is_numeric($ncmec_report_id) || $ncmec_report_id === 'FALSE_MATCH') ? 'bg-success' : 'bg-danger';
      echo "<td class=\"{$bgColor}\">" . htmlspecialchars($ncmec_report_id) . '</td>';
      echo '<td>';

      // Actions: View Logs, View NCMEC Report, View Evidence, Submit NCMEC Report

      // View Logs Button
      echo '<button style="margin:3px" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#logsModal' . $id . '">View Logs</button>';

      // View NCMEC Report Button (if ncmec_submitted_report is not null)
      if (!empty($row['ncmec_submitted_report'])) {
        echo ' <button style="margin:3px" class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#ncmecModal' . $id . '">View NCMEC Report</button>';
      }

      // View Evidence Button (updated to use AJAX)
      echo ' <button style="margin:3px" class="btn btn-sm btn-primary view-evidence-btn" data-incident-id="' . htmlspecialchars($id) . '">View Evidence</button>';

      // Submit NCMEC Report Button (if ncmec_report_id is null or report ID starts with TEST_)
      // Show below buttons if only report id is not 'FALSE_MATCH'
      if ($ncmec_report_id !== 'FALSE_MATCH') {
        if (empty($ncmec_report_id) || strpos($ncmec_report_id, 'TEST_') === 0 || $ncmec_report_id === 'Null: Technical Error') {
          echo ' <button style="margin:3px" class="btn btn-sm btn-danger submit-report-btn" data-incident-id="' . htmlspecialchars($id) . '" data-test-report="false">Submit NCMEC Report</button>';
        }

        // Optionally, a button to submit a test report (if ncmec_report_id is null)
        if (empty($ncmec_report_id)) {
          echo ' <button style="margin:3px" class="btn btn-sm btn-warning submit-report-btn" data-incident-id="' . htmlspecialchars($id) . '" data-test-report="true">Submit Test Report</button>';
        }

        // Add the Unblacklist User button
        if ('PhotoDNA API Match' === $row['identified_by_npub'] && empty($row['ncmec_report_id'])) {
          echo ' <button style="margin:3px" class="btn btn-sm btn-success unblacklist-user-btn" data-incident-id="' . htmlspecialchars($id) . '">Unblacklist User and mark as false report</button>';
        }
      }

      echo '</td>';
      echo '</tr>';

      // Modals for Logs
      $logs = $row['logs'];
      $prettyLogs = json_encode(json_decode($logs), JSON_PRETTY_PRINT);
      $escapedLogs = htmlspecialchars($prettyLogs, ENT_QUOTES, 'UTF-8');

      echo '
            <!-- Logs Modal -->
            <div class="modal fade" id="logsModal' . $id . '" tabindex="-1" aria-labelledby="logsModalLabel' . $id . '" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="logsModalLabel' . $id . '">Logs for Incident ID ' . $id . '</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <pre>' . $escapedLogs . '</pre>
                        </div>
                    </div>
                </div>
            </div>
            ';

      // Modals for NCMEC Report
      if (!empty($row['ncmec_submitted_report'])) {
        $ncmec_report = $row['ncmec_submitted_report'];
        $prettyReport = json_encode(json_decode($ncmec_report), JSON_PRETTY_PRINT);
        $escapedReport = htmlspecialchars($prettyReport, ENT_QUOTES, 'UTF-8');

        echo '
                <!-- NCMEC Report Modal -->
                <div class="modal fade" id="ncmecModal' . $id . '" tabindex="-1" aria-labelledby="ncmecModalLabel' . $id . '" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="ncmecModalLabel' . $id . '">NCMEC Report for Incident ID ' . $id . '</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <pre>' . $escapedReport . '</pre>
                            </div>
                        </div>
                    </div>
                </div>
                ';
      }

      // Modals for Evidence are now handled via JavaScript and AJAX
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';

    // Pagination
    // Get total count
    if (!empty($searchFile)) {
      $sql = "SELECT COUNT(*) as total FROM identified_csam_cases WHERE file_sha256_hash = ?";
      $stmt = $link->prepare($sql);
      $stmt->bind_param('s', $searchFile);
    } else {
      $sql = "SELECT COUNT(*) as total FROM identified_csam_cases";
      $stmt = $link->prepare($sql);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $total = 0;
    if ($row = $result->fetch_assoc()) {
      $total = $row['total'];
    }

    $pages = ceil($total / $perpage);

    echo '<nav aria-label="Page navigation">';
    echo '  <ul class="pagination justify-content-center">';
    for ($i = 0; $i < $pages; $i++) {
      $active_class = (($i == $page) ? 'active' : '');
      echo '    <li class="page-item ' . $active_class . '"><a class="page-link" href="?p=' . $i . '">' . ($i + 1) . '</a></li>';
    }
    echo '  </ul>';
    echo '</nav>';
    ?>
  </main>

  <!-- Evidence Modal (Generic) -->
  <div class="modal fade" id="evidenceModal" tabindex="-1" aria-labelledby="evidenceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Evidence</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body text-center" id="evidenceModalBody">
          <!-- Image will be inserted here -->
        </div>
      </div>
    </div>
  </div>

  <!-- Report Preview Modal -->
  <div class="modal fade" id="reportPreviewModal" tabindex="-1" aria-labelledby="reportPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">NCMEC Report Preview</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <pre id="reportPreviewContent"></pre>
        </div>
        <div class="modal-footer">
          <input type="hidden" id="previewIncidentId" value="">
          <input type="hidden" id="previewTestReport" value="">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" id="confirmSubmitReportBtn">Confirm and Submit</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS and dependencies -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // View Evidence button click handler
      const viewEvidenceButtons = document.querySelectorAll('.view-evidence-btn');
      viewEvidenceButtons.forEach(function(button) {
        button.addEventListener('click', function() {
          const incidentId = this.getAttribute('data-incident-id');
          // Make AJAX request to get the evidence image
          fetch('get_evidence.php?incidentId=' + incidentId)
            .then(response => response.text())
            .then(data => {
              // Insert the image into the modal body
              document.getElementById('evidenceModalBody').innerHTML = data;
              // Update the modal title
              document.querySelector('#evidenceModal .modal-title').innerText = 'Evidence for Incident ID ' + incidentId;
              // Show the modal
              var evidenceModal = new bootstrap.Modal(document.getElementById('evidenceModal'));
              evidenceModal.show();
            })
            .catch(error => {
              console.error('Error fetching evidence:', error);
              alert('Error fetching evidence.');
            });
        });
      });

      // Submit Report button click handler
      const submitReportButtons = document.querySelectorAll('.submit-report-btn');
      submitReportButtons.forEach(function(button) {
        button.addEventListener('click', function() {
          const incidentId = this.getAttribute('data-incident-id');
          const testReport = this.getAttribute('data-test-report');
          // Make AJAX request to get the sanitized report data
          fetch('preview_report.php?incidentId=' + incidentId + '&testReport=' + testReport)
            .then(response => response.json())
            .then(data => {
              if (data.error) {
                alert('Error: ' + data.error);
                return;
              }
              // Display the sanitized report data in the modal
              document.getElementById('reportPreviewContent').textContent = JSON.stringify(data, null, 2);
              // Store the incidentId and testReport in hidden inputs
              document.getElementById('previewIncidentId').value = incidentId;
              document.getElementById('previewTestReport').value = testReport;
              // Show the modal
              var reportPreviewModal = new bootstrap.Modal(document.getElementById('reportPreviewModal'));
              reportPreviewModal.show();
            })
            .catch(error => {
              console.error('Error fetching report preview:', error);
              alert('Error fetching report preview.');
            });
        });
      });

      // Confirm and Submit button click handler
      document.getElementById('confirmSubmitReportBtn').addEventListener('click', function() {
        const incidentId = document.getElementById('previewIncidentId').value;
        const testReport = document.getElementById('previewTestReport').value;
        // Make AJAX request to submit the report
        fetch('submit_report.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              incidentId: incidentId,
              testReport: testReport
            })
          })
          .then(response => response.json())
          .then(data => {
            // Handle the response
            if (data.httpCode === 200) {
              alert('Report submitted successfully.');
              // Optionally, reload the page or update the table to reflect the submitted report
              location.reload();
            } else {
              const errorMsg = data.error || JSON.stringify(data.response);
              alert('Error submitting report: ' + errorMsg);
            }
            // Hide the modal
            var reportPreviewModal = bootstrap.Modal.getInstance(document.getElementById('reportPreviewModal'));
            reportPreviewModal.hide();
          })
          .catch(error => {
            console.error('Error submitting report:', error);
            alert('Error submitting report.');
            // Hide the modal
            var reportPreviewModal = bootstrap.Modal.getInstance(document.getElementById('reportPreviewModal'));
            reportPreviewModal.hide();
          });
      });

      // Unblacklist User button click handler
      const unblacklistUserButtons = document.querySelectorAll('.unblacklist-user-btn');
      unblacklistUserButtons.forEach(function(button) {
        button.addEventListener('click', function() {
          const incidentId = this.getAttribute('data-incident-id');
          // Confirm action
          if (confirm('Are you sure you want to unblacklist this user?')) {
            // Make AJAX request to unblacklist the user
            fetch('unblacklist_user.php', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                  incidentId: incidentId
                })
              })
              .then(response => response.json())
              .then(data => {
                if (data.success) {
                  alert('User has been unblacklisted successfully.');
                  // Optionally, reload the page or update the UI
                  location.reload();
                } else {
                  alert('Error unblacklisting user: ' + data.error);
                }
              })
              .catch(error => {
                console.error('Error unblacklisting user:', error);
                alert('Error unblacklisting user.');
              });
          }
        });
      });
    });
  </script>
</body>

</html>