<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/SiteConfig.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/NCMECReportHandler.class.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/IpAccessControl.class.php');

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
    <section class="title_section d-flex justify-content-between align-items-center flex-wrap gap-2">
      <h1 class="mb-0">Admin CSAM Cases</h1>
      <a href="/account/admin/admin_ip_access.php" class="btn btn-outline-dark btn-sm">Manage IP Blocklist / Whitelist &raquo;</a>
    </section>
    <!-- Add Search Box -->
    <form method="post" class="mb-3">
      <div class="input-group">
        <input type="text" class="form-control" name="searchFile" placeholder="Enter file hash to search" value="<?= htmlspecialchars($searchFile) ?>">
        <button class="btn btn-primary" type="submit">Search</button>
      </div>
    </form>
    <!-- Bulk Submit Unsubmitted Reports -->
    <div class="card mb-3">
      <div class="card-body">
        <div class="d-flex align-items-center gap-3">
          <button id="bulkSubmitBtn" class="btn btn-danger">Submit All Unsubmitted Reports (Past 7 Days)</button>
          <span id="bulkSubmitStatus" class="text-muted"></span>
        </div>
        <div id="bulkSubmitProgress" class="mt-2" style="display:none;">
          <div class="progress mb-2">
            <div id="bulkSubmitBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%">0%</div>
          </div>
          <div id="bulkSubmitLog" class="small" style="max-height:200px;overflow-y:auto;font-family:monospace;white-space:pre-wrap;"></div>
        </div>
      </div>
    </div>

    <!-- Bulk Delete Offender Media (cleanup pass for already-submitted cases) -->
    <div class="card mb-3 border-danger">
      <div class="card-body">
        <div class="d-flex align-items-center gap-3 flex-wrap">
          <button id="bulkDeleteOffendersBtn" class="btn btn-outline-danger">🗑 Delete All Media of Users with Submitted Reports (Past 7 Days)</button>
          <span id="bulkDeleteOffendersStatus" class="text-muted small"></span>
        </div>
        <div class="form-text mt-1">
          Deduped per offender npub. Only acts on cases with a numeric NCMEC report id (excludes TEST_, FALSE_MATCH, technical errors). Same checkbox-gated confirmation as the per-case button.
        </div>
      </div>
    </div>
    <?php
    // Query to get the total count of CSAM cases and total reported
    $sql = <<<SQL
        SELECT 
            COUNT(*) AS total_count,
            COUNT(CASE 
                    WHEN ncmec_report_id IS NOT NULL 
                    AND ncmec_report_id NOT LIKE 'TEST_%' 
                    AND ncmec_report_id NOT LIKE 'FALSE_MATCH' 
                    AND ncmec_report_id NOT LIKE 'ERROR' 
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

      // Lookup & Block IP Button — pulls IP candidates from logs, runs WHOIS, lets admin add to blocklist.
      echo ' <button style="margin:3px" class="btn btn-sm btn-dark lookup-ip-btn" data-incident-id="' . htmlspecialchars($id) . '">Lookup &amp; Block IP</button>';

      // Delete Offender Media — for cases where the offender is positively
      // identified: numeric NCMEC report id (formally submitted) OR the manual
      // EVIDENCE_EXPIRED sentinel (offender confirmed but evidence window
      // closed). They're already npub-banned via processCsamReport.
      if (is_numeric($ncmec_report_id) || $ncmec_report_id === 'EVIDENCE_EXPIRED') {
        echo ' <button style="margin:3px" class="btn btn-sm btn-outline-danger delete-offender-media-btn" data-incident-id="' . htmlspecialchars($id) . '" title="Delete all remaining media uploaded by this offender">🗑 Delete Offender Media</button>';
      }

      // Submit NCMEC Report Button (if ncmec_report_id is null or report ID starts with TEST_)
      // Show below buttons if only report id is not 'FALSE_MATCH'
      if ($ncmec_report_id !== 'FALSE_MATCH') {
        if (empty($ncmec_report_id) || strpos($ncmec_report_id, 'TEST_') === 0 || $ncmec_report_id === 'Null: Technical Error' || $ncmec_report_id === 'ERROR') {
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
    $stmt->close();
    $link->close(); // CLOSE MYSQL LINK
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

  <!-- IP Lookup & Block Modal -->
  <div class="modal fade" id="ipLookupModal" tabindex="-1" aria-labelledby="ipLookupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="ipLookupModalLabel">Lookup &amp; Block IP</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="ipLookupCandidates" class="mb-3">
            <p class="text-muted small mb-1">Loading IP candidates from incident logs...</p>
          </div>

          <div id="ipLookupWhois" class="mb-3" style="display:none;">
            <h6>WHOIS</h6>
            <div id="ipLookupWhoisBanner" class="mb-2" style="display:none;"></div>
            <table class="table table-sm table-bordered mb-0">
              <tbody id="ipLookupWhoisBody"></tbody>
            </table>
          </div>

          <hr>
          <h6>Add to blocklist</h6>
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label small mb-1">CIDR</label>
              <div class="input-group">
                <input type="text" class="form-control" id="ipBlockCidr" placeholder="e.g. 1.2.3.4/32">
                <button type="button" class="btn btn-outline-secondary" id="ipBlockCidrPrefix" title="Use the announced prefix from WHOIS" disabled>Use prefix</button>
              </div>
              <div class="form-text">Bare IPs accepted; min /<?= IpAccessControl::MIN_IPV4_PREFIX ?> v4, /<?= IpAccessControl::MIN_IPV6_PREFIX ?> v6.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label small mb-1">Source</label>
              <input type="text" class="form-control" id="ipBlockSource" value="csam-manual">
            </div>
            <div class="col-md-8">
              <label class="form-label small mb-1">Reason</label>
              <input type="text" class="form-control" id="ipBlockReason" placeholder="(optional, e.g. CSAM incident #123)">
            </div>
            <div class="col-md-4">
              <label class="form-label small mb-1">Expires (optional)</label>
              <input type="datetime-local" class="form-control" id="ipBlockExpires">
            </div>
          </div>
          <div id="ipBlockStatus" class="mt-2 small"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="button" class="btn btn-danger" id="ipBlockSubmitBtn">Add to Blocklist</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Delete Offender Media Modal — confirm + progress, two-stage. -->
  <div class="modal fade" id="deleteOffenderModal" tabindex="-1" aria-labelledby="deleteOffenderModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg">
      <div class="modal-content border border-danger" style="border-width:2px !important;">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title" id="deleteOffenderModalLabel">🚨 Delete All Offender Media</h5>
          <button type="button" class="btn-close btn-close-white" id="deleteOffenderCloseBtn" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <!-- Stage 1: confirm -->
          <div id="deleteOffenderConfirm">
            <p id="deleteOffenderLoading" class="text-muted">Looking up offender's remaining media...</p>

            <!-- Single-case summary (per-row button) -->
            <div id="deleteOffenderSummary" style="display:none;">
              <p class="mb-2">You are about to <strong>permanently delete</strong> <span id="deleteOffenderCount" class="badge bg-danger">0</span> remaining media item(s) uploaded by:</p>
              <pre id="deleteOffenderNpub" class="bg-light border rounded p-2 small mb-2 text-break" style="white-space:pre-wrap;"></pre>
              <p class="small text-muted mb-3">NCMEC Report ID: <code id="deleteOffenderReportId"></code> (Case #<span id="deleteOffenderCaseId"></span>)</p>
            </div>

            <!-- Bulk summary (Past N Days button) -->
            <div id="deleteOffenderBulkSummary" style="display:none;">
              <p class="mb-2">You are about to <strong>permanently delete</strong>
                <span id="deleteBulkUploadCount" class="badge bg-danger">0</span> remaining media item(s) across
                <span id="deleteBulkOffenderCount" class="badge bg-danger">0</span> offender(s)
                from CSAM cases submitted in the past <span id="deleteBulkDays">7</span> day(s):
              </p>
              <div class="border rounded mb-3" style="max-height:240px;overflow-y:auto;">
                <table class="table table-sm mb-0">
                  <thead class="table-light"><tr><th>npub</th><th class="text-end">Items</th><th>Cases</th></tr></thead>
                  <tbody id="deleteBulkOffenderTbody"></tbody>
                </table>
              </div>
            </div>

            <!-- Shared confirmation block (used by both modes) -->
            <div id="deleteOffenderConfirmBlock" style="display:none;">
              <div class="alert alert-danger small mb-3">
                <strong>This action cannot be undone.</strong>
                Files will be removed from S3, purged from the CDN, banned from blossom, and inserted into rejected_files. The user(s) are already npub-banned (this is the cleanup pass).
              </div>

              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="deleteOffenderConfirmCheckbox">
                <label class="form-check-label" for="deleteOffenderConfirmCheckbox">
                  I understand this will permanently delete <span id="deleteOffenderCountInline">0</span> item(s) and cannot be undone.
                </label>
              </div>
            </div>

            <div id="deleteOffenderEmpty" class="alert alert-info mb-0" style="display:none;">
              No remaining media for this offender — nothing to delete.
            </div>

            <div id="deleteOffenderError" class="alert alert-danger mb-0" style="display:none;"></div>
          </div>

          <!-- Stage 2: progress -->
          <div id="deleteOffenderProgress" style="display:none;">
            <div class="progress mb-2" style="height:24px;">
              <div id="deleteOffenderBar" class="progress-bar progress-bar-striped progress-bar-animated bg-danger" role="progressbar" style="width:0%;">0%</div>
            </div>
            <p id="deleteOffenderStatus" class="small text-muted mb-2"></p>
            <div id="deleteOffenderLog" class="small bg-dark text-white-50 p-2 rounded" style="max-height:240px;overflow-y:auto;font-family:monospace;white-space:pre-wrap;"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" id="deleteOffenderCancelBtn" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-danger" id="deleteOffenderConfirmBtn" disabled>Delete All</button>
          <button type="button" class="btn btn-primary" id="deleteOffenderDoneBtn" style="display:none;" data-bs-dismiss="modal">Close &amp; Reload</button>
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
          fetch('/api/v2/admin/csam/evidence?incidentId=' + incidentId, { credentials: 'same-origin' })
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
          fetch('/api/v2/admin/csam/report/preview?incidentId=' + incidentId + '&testReport=' + testReport, { credentials: 'same-origin' })
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
        fetch('/api/v2/admin/csam/report/submit', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
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

      // ===== IP Lookup & Block flow =====
      const ipLookupModalEl = document.getElementById('ipLookupModal');
      const ipLookupModal = new bootstrap.Modal(ipLookupModalEl);
      const ipCandidatesEl = document.getElementById('ipLookupCandidates');
      const ipWhoisWrap = document.getElementById('ipLookupWhois');
      const ipWhoisBody = document.getElementById('ipLookupWhoisBody');
      const ipBlockCidr = document.getElementById('ipBlockCidr');
      const ipBlockCidrPrefix = document.getElementById('ipBlockCidrPrefix');
      const ipBlockSource = document.getElementById('ipBlockSource');
      const ipBlockReason = document.getElementById('ipBlockReason');
      const ipBlockExpires = document.getElementById('ipBlockExpires');
      const ipBlockStatus = document.getElementById('ipBlockStatus');
      const ipBlockSubmitBtn = document.getElementById('ipBlockSubmitBtn');
      let currentIncidentId = null;
      let currentWhoisPrefix = null;

      function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({
          '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
      }

      function setBlockStatus(msg, kind) {
        ipBlockStatus.textContent = msg || '';
        ipBlockStatus.className = 'mt-2 small ' + (kind === 'error' ? 'text-danger' : (kind === 'ok' ? 'text-success' : 'text-muted'));
      }

      function resetIpModal(incidentId) {
        currentIncidentId = incidentId;
        currentWhoisPrefix = null;
        ipCandidatesEl.innerHTML = '<p class="text-muted small mb-1">Loading IP candidates from incident logs...</p>';
        ipWhoisWrap.style.display = 'none';
        ipWhoisBody.innerHTML = '';
        ipBlockCidr.value = '';
        ipBlockCidrPrefix.disabled = true;
        ipBlockReason.value = 'CSAM incident #' + incidentId;
        ipBlockSource.value = 'csam-manual';
        ipBlockExpires.value = '';
        setBlockStatus('');
      }

      async function loadCandidates(incidentId) {
        try {
          const resp = await fetch('/api/v2/admin/security/case-ip/' + incidentId, { credentials: 'same-origin' });
          const data = await resp.json();
          if (!resp.ok) {
            ipCandidatesEl.innerHTML = '<div class="alert alert-danger py-2 mb-0">' + escapeHtml(data.error || 'Failed to load candidates.') + '</div>';
            return;
          }
          if (!data.candidates || data.candidates.length === 0) {
            ipCandidatesEl.innerHTML = '<div class="alert alert-warning py-2 mb-0">No IPs found in this incident\'s logs.</div>';
            return;
          }
          let html = '<h6 class="mb-2">IP candidates from logs</h6>';
          html += '<div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead><tr>'
            + '<th>IP</th><th>Source</th><th>npub</th><th>File</th><th>When</th><th></th>'
            + '</tr></thead><tbody>';
          for (const c of data.candidates) {
            html += '<tr>'
              + '<td><code>' + escapeHtml(c.ip) + '</code></td>'
              + '<td>' + escapeHtml(c.source) + '</td>'
              + '<td class="text-truncate" style="max-width:160px">' + escapeHtml(c.npub || '') + '</td>'
              + '<td class="text-truncate" style="max-width:160px">' + escapeHtml(c.filename || '') + '</td>'
              + '<td>' + escapeHtml(c.datetime || '') + '</td>'
              + '<td><button type="button" class="btn btn-xsm btn-primary ip-whois-btn" data-ip="' + escapeHtml(c.ip) + '">WHOIS</button></td>'
              + '</tr>';
          }
          html += '</tbody></table></div>';
          ipCandidatesEl.innerHTML = html;

          ipCandidatesEl.querySelectorAll('.ip-whois-btn').forEach(btn => {
            btn.addEventListener('click', () => loadWhois(btn.getAttribute('data-ip')));
          });

          // Auto-trigger whois on the first candidate so the block form is pre-populated.
          const first = data.candidates[0];
          ipBlockCidr.value = first.ip;
          loadWhois(first.ip);
        } catch (err) {
          ipCandidatesEl.innerHTML = '<div class="alert alert-danger py-2 mb-0">Network error: ' + escapeHtml(err.message) + '</div>';
        }
      }

      // ASNs that should NEVER be IP-blocked. Blocking them either breaks our
      // own infra (CDNs/clouds) or punishes huge swaths of legitimate users
      // (consumer VPNs / shared VPS). Banner + disabled submit when matched.
      const RISKY_ASN = {
        13335:  { name: 'Cloudflare',         kind: 'CDN / WARP / Tor / Workers' },
        16509:  { name: 'Amazon AWS',         kind: 'cloud' },
        14618:  { name: 'Amazon AES',         kind: 'cloud' },
        15169:  { name: 'Google',             kind: 'cloud / Search / WARP' },
        396982: { name: 'Google Cloud',       kind: 'cloud' },
        8075:   { name: 'Microsoft Azure',    kind: 'cloud' },
        8068:   { name: 'Microsoft',          kind: 'cloud / Office' },
        20940:  { name: 'Akamai',             kind: 'CDN' },
        16276:  { name: 'OVH',                kind: 'shared VPS hosting' },
        24940:  { name: 'Hetzner',            kind: 'shared VPS hosting' },
        14061:  { name: 'DigitalOcean',       kind: 'shared VPS hosting' },
        63949:  { name: 'Linode (Akamai)',    kind: 'shared VPS hosting' },
        9009:   { name: 'M247',               kind: 'shared VPS / consumer VPN exit' },
        46606:  { name: 'Unified Layer',      kind: 'shared hosting' },
      };
      function riskyAsn(asn) { return RISKY_ASN[Number(asn)] || null; }
      const ipWhoisBanner = document.getElementById('ipLookupWhoisBanner');

      async function loadWhois(ip) {
        ipBlockCidr.value = ip;
        ipWhoisWrap.style.display = 'block';
        ipWhoisBanner.style.display = 'none';
        ipWhoisBanner.innerHTML = '';
        ipWhoisBody.innerHTML = '<tr><td colspan="2" class="text-muted small">Looking up ' + escapeHtml(ip) + '...</td></tr>';
        currentWhoisPrefix = null;
        ipBlockCidrPrefix.disabled = true;
        ipBlockSubmitBtn.disabled = false;
        try {
          const resp = await fetch('/api/v2/admin/security/whois?ip=' + encodeURIComponent(ip), { credentials: 'same-origin' });
          const data = await resp.json();
          if (!resp.ok) {
            ipWhoisBody.innerHTML = '<tr><td colspan="2" class="text-danger small">' + escapeHtml(data.error || 'WHOIS failed') + '</td></tr>';
            return;
          }
          if (data.found === false) {
            ipWhoisBody.innerHTML = '<tr><td colspan="2" class="text-warning small">No WHOIS record (private/reserved or not in routing table).</td></tr>';
            return;
          }

          const risky = riskyAsn(data.asn);
          const rows = [
            ['IP', data.ip],
            ['ASN', data.asn ? ('AS' + data.asn) : ''],
            ['AS name', data.as_name || ''],
            ['Announced prefix', data.prefix || ''],
            ['Country', data.country || ''],
            ['Registry', data.registry || ''],
            ['Allocated', data.allocated || ''],
            ['All ASNs', (data.asns || []).join(', ')],
          ];
          ipWhoisBody.innerHTML = rows.map(([k, v]) =>
            '<tr><th class="w-25">' + escapeHtml(k) + '</th><td>' + escapeHtml(v) + '</td></tr>'
          ).join('');

          if (risky) {
            ipWhoisBanner.className = 'alert alert-danger border border-danger border-2 mb-2 py-2';
            ipWhoisBanner.innerHTML =
              '<div class="fw-bold">🚫 DO NOT IP-BLOCK — ' + escapeHtml(risky.name) + ' (' + escapeHtml(risky.kind) + ')</div>'
              + '<div class="small mt-1">This IP belongs to ' + escapeHtml(risky.name) + ' infrastructure. Blocking it will cut off many legitimate users (and possibly our own services). Ban the npub instead.</div>';
            ipWhoisBanner.style.display = 'block';
            ipBlockSubmitBtn.disabled = true;
          } else if (data.prefix) {
            currentWhoisPrefix = data.prefix;
            ipBlockCidrPrefix.disabled = false;
          }
        } catch (err) {
          ipWhoisBody.innerHTML = '<tr><td colspan="2" class="text-danger small">Network error: ' + escapeHtml(err.message) + '</td></tr>';
        }
      }

      ipBlockCidrPrefix.addEventListener('click', () => {
        if (currentWhoisPrefix) ipBlockCidr.value = currentWhoisPrefix;
      });

      ipBlockSubmitBtn.addEventListener('click', async () => {
        const cidr = ipBlockCidr.value.trim();
        if (!cidr) {
          setBlockStatus('Enter a CIDR or IP first.', 'error');
          return;
        }
        ipBlockSubmitBtn.disabled = true;
        setBlockStatus('Submitting...');
        try {
          const body = {
            cidr: cidr,
            reason: ipBlockReason.value.trim(),
            source: ipBlockSource.value.trim() || 'csam-manual',
          };
          // datetime-local -> "YYYY-MM-DDTHH:MM" — convert to MySQL DATETIME.
          if (ipBlockExpires.value) {
            body.expires_at = ipBlockExpires.value.replace('T', ' ') + ':00';
          }
          const resp = await fetch('/api/v2/admin/security/blocklist', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(body),
          });
          const data = await resp.json();
          if (resp.ok && data.success) {
            setBlockStatus('Added: ' + data.cidr + ' (id ' + data.id + ')', 'ok');
          } else {
            setBlockStatus('Error: ' + (data.error || ('HTTP ' + resp.status)), 'error');
          }
        } catch (err) {
          setBlockStatus('Network error: ' + err.message, 'error');
        } finally {
          ipBlockSubmitBtn.disabled = false;
        }
      });

      document.querySelectorAll('.lookup-ip-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          const id = btn.getAttribute('data-incident-id');
          resetIpModal(id);
          ipLookupModal.show();
          loadCandidates(id);
        });
      });

      // ========== Delete Offender Media ==========
      // Two-stage flow that mirrors approve.php's "Reject All & Ban User":
      //   1. Fetch the offender's npub + remaining-uploads list from the server.
      //   2. After a checkbox-gated confirmation, loop through every upload id
      //      in chunks via POST /admin/moderation/reject-batch — that endpoint
      //      runs the same flow as deleteAndRejectUpload (S3 delete + CF purge
      //      + blossom ban + rejected_files insert + uploads_data delete) but
      //      consolidates the CF purge and DB writes for the whole chunk.
      // The offender is already npub-banned (legacy blacklist) at this point,
      // so we don't need a separate ban call up front.
      const delModalEl = document.getElementById('deleteOffenderModal');
      const delModal = new bootstrap.Modal(delModalEl);
      const delLoading = document.getElementById('deleteOffenderLoading');
      const delSummary = document.getElementById('deleteOffenderSummary');
      const delBulkSummary = document.getElementById('deleteOffenderBulkSummary');
      const delConfirmBlock = document.getElementById('deleteOffenderConfirmBlock');
      const delEmpty = document.getElementById('deleteOffenderEmpty');
      const delErrorEl = document.getElementById('deleteOffenderError');
      const delCountEl = document.getElementById('deleteOffenderCount');
      const delCountInline = document.getElementById('deleteOffenderCountInline');
      const delNpubEl = document.getElementById('deleteOffenderNpub');
      const delReportIdEl = document.getElementById('deleteOffenderReportId');
      const delCaseIdEl = document.getElementById('deleteOffenderCaseId');
      const delBulkUploadCountEl = document.getElementById('deleteBulkUploadCount');
      const delBulkOffenderCountEl = document.getElementById('deleteBulkOffenderCount');
      const delBulkDaysEl = document.getElementById('deleteBulkDays');
      const delBulkTbody = document.getElementById('deleteBulkOffenderTbody');
      const delConfirmCb = document.getElementById('deleteOffenderConfirmCheckbox');
      const delConfirmBtn = document.getElementById('deleteOffenderConfirmBtn');
      const delCancelBtn = document.getElementById('deleteOffenderCancelBtn');
      const delCloseBtn = document.getElementById('deleteOffenderCloseBtn');
      const delDoneBtn = document.getElementById('deleteOffenderDoneBtn');
      const delConfirmStage = document.getElementById('deleteOffenderConfirm');
      const delProgressStage = document.getElementById('deleteOffenderProgress');
      const delBar = document.getElementById('deleteOffenderBar');
      const delStatusEl = document.getElementById('deleteOffenderStatus');
      const delLogEl = document.getElementById('deleteOffenderLog');

      // Flat list of items to delete; each entry: { id, filename, npub }
      // (npub is included so the progress log shows whose item it is in bulk mode)
      let delItems = [];
      let delMode = 'single'; // 'single' | 'bulk'

      function delLog(msg, isError) {
        const line = document.createElement('div');
        line.textContent = msg;
        if (isError) line.style.color = '#ff6b6b';
        delLogEl.appendChild(line);
        delLogEl.scrollTop = delLogEl.scrollHeight;
      }

      function escHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({
          '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
      }

      function resetDelModal(mode) {
        delMode = mode;
        delLoading.style.display = 'block';
        delSummary.style.display = 'none';
        delBulkSummary.style.display = 'none';
        delConfirmBlock.style.display = 'none';
        delEmpty.style.display = 'none';
        delErrorEl.style.display = 'none';
        delErrorEl.textContent = '';
        delConfirmCb.checked = false;
        delConfirmBtn.disabled = true;
        delConfirmBtn.style.display = '';
        delConfirmBtn.textContent = mode === 'bulk' ? 'Delete Everything' : 'Delete All';
        delCancelBtn.style.display = '';
        delCloseBtn.style.display = '';
        delDoneBtn.style.display = 'none';
        delConfirmStage.style.display = 'block';
        delProgressStage.style.display = 'none';
        delBar.style.width = '0%';
        delBar.textContent = '0%';
        delBar.classList.remove('bg-success', 'bg-warning');
        delBar.classList.add('progress-bar-animated', 'bg-danger');
        delStatusEl.textContent = '';
        delLogEl.textContent = '';
        delItems = [];
      }

      delConfirmCb.addEventListener('change', () => {
        delConfirmBtn.disabled = !delConfirmCb.checked || delItems.length === 0;
      });

      // ---------- single-case loader ----------
      async function loadOffenderUploads(caseId) {
        try {
          const resp = await fetch('/api/v2/admin/csam/offender-uploads/' + caseId, { credentials: 'same-origin' });
          const data = await resp.json();
          delLoading.style.display = 'none';

          if (!resp.ok) {
            delErrorEl.textContent = data.error || ('HTTP ' + resp.status);
            delErrorEl.style.display = 'block';
            return;
          }

          delCaseIdEl.textContent = data.caseId;
          delReportIdEl.textContent = data.reportId;
          delNpubEl.textContent = data.npub;

          const uploads = Array.isArray(data.uploads) ? data.uploads : [];
          delItems = uploads.map(u => ({ id: u.id, filename: u.filename, npub: data.npub }));

          if (delItems.length === 0) {
            delEmpty.style.display = 'block';
            delConfirmBtn.style.display = 'none';
            return;
          }

          delCountEl.textContent = delItems.length;
          delCountInline.textContent = delItems.length;
          delSummary.style.display = 'block';
          delConfirmBlock.style.display = 'block';
        } catch (err) {
          delLoading.style.display = 'none';
          delErrorEl.textContent = 'Network error: ' + err.message;
          delErrorEl.style.display = 'block';
        }
      }

      // ---------- bulk loader (past N days, deduped per offender) ----------
      async function loadBulkOffenders(days) {
        try {
          const resp = await fetch('/api/v2/admin/csam/submitted-offenders?days=' + days, { credentials: 'same-origin' });
          const data = await resp.json();
          delLoading.style.display = 'none';

          if (!resp.ok) {
            delErrorEl.textContent = data.error || ('HTTP ' + resp.status);
            delErrorEl.style.display = 'block';
            return;
          }

          delBulkDaysEl.textContent = data.days;
          delBulkOffenderCountEl.textContent = data.total_offenders;
          delBulkUploadCountEl.textContent = data.total_uploads;

          // Build the offender preview table and the flat work-list at once.
          delItems = [];
          delBulkTbody.innerHTML = (data.offenders || []).map(o => {
            for (const u of (o.uploads || [])) {
              delItems.push({ id: u.id, filename: u.filename, npub: o.npub });
            }
            const cases = (o.case_ids || []).map(c => '#' + c).join(', ');
            return '<tr>'
              + '<td class="text-truncate" style="max-width:340px;" title="' + escHtml(o.npub) + '"><code>' + escHtml(o.npub) + '</code></td>'
              + '<td class="text-end">' + escHtml(o.remaining_count) + '</td>'
              + '<td class="small text-muted">' + escHtml(cases) + '</td>'
              + '</tr>';
          }).join('');

          if (delItems.length === 0) {
            delEmpty.style.display = 'block';
            delConfirmBtn.style.display = 'none';
            return;
          }

          delCountInline.textContent = delItems.length;
          delBulkSummary.style.display = 'block';
          delConfirmBlock.style.display = 'block';
        } catch (err) {
          delLoading.style.display = 'none';
          delErrorEl.textContent = 'Network error: ' + err.message;
          delErrorEl.style.display = 'block';
        }
      }

      // ---------- shared progress loop ----------
      // Chunk size for the batched reject endpoint. Server caps at 30 (see
      // MAX_REJECT_BATCH in /admin/moderation/reject-batch); 15 keeps each
      // request snappy enough that progress feels live and a single failed
      // request only loses a small slice of work.
      const REJECT_BATCH_SIZE = 15;

      delConfirmBtn.addEventListener('click', async () => {
        if (delItems.length === 0) return;

        delConfirmStage.style.display = 'none';
        delProgressStage.style.display = 'block';
        delConfirmBtn.disabled = true;
        delConfirmBtn.style.display = 'none';
        delCancelBtn.style.display = 'none';
        delCloseBtn.style.display = 'none';

        const total = delItems.length;
        let success = 0;
        let errors = 0;
        // Quick lookup so we can pretty-print log lines from the result map.
        const itemById = new Map(delItems.map(i => [String(i.id), i]));

        delLog(delMode === 'bulk'
          ? `Deleting ${total} item(s) across ${new Set(delItems.map(i => i.npub)).size} offender(s) in chunks of ${REJECT_BATCH_SIZE}...`
          : `Deleting ${total} item(s) for ${delNpubEl.textContent} in chunks of ${REJECT_BATCH_SIZE}...`);

        let processed = 0;
        for (let off = 0; off < total; off += REJECT_BATCH_SIZE) {
          const chunk = delItems.slice(off, off + REJECT_BATCH_SIZE);
          const chunkIds = chunk.map(c => c.id);

          delStatusEl.textContent = `Submitting batch of ${chunk.length} (item ${off + 1}–${off + chunk.length} of ${total})...`;

          let data;
          try {
            const resp = await fetch('/api/v2/admin/moderation/reject-batch', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              credentials: 'same-origin',
              body: JSON.stringify({ ids: chunkIds }),
            });
            data = await resp.json();
            if (!resp.ok && !Array.isArray(data?.results)) {
              // Endpoint-level failure (auth, malformed, etc.) — count the
              // whole chunk as failed and keep going so one bad batch doesn't
              // strand the rest of the queue.
              for (const c of chunk) {
                errors++;
                delLog(`✗ #${c.id}: batch error — ${data?.error || ('HTTP ' + resp.status)}`, true);
              }
              processed += chunk.length;
              const pct = Math.round((processed / total) * 100);
              delBar.style.width = pct + '%';
              delBar.textContent = `${processed} / ${total}`;
              continue;
            }
          } catch (err) {
            for (const c of chunk) {
              errors++;
              delLog(`✗ #${c.id}: network error — ${err.message}`, true);
            }
            processed += chunk.length;
            const pct = Math.round((processed / total) * 100);
            delBar.style.width = pct + '%';
            delBar.textContent = `${processed} / ${total}`;
            continue;
          }

          // Per-id results from the server.
          for (const r of (data.results || [])) {
            const item = itemById.get(String(r.id));
            const filename = item?.filename ?? '(unknown)';
            const tag = (delMode === 'bulk' && item?.npub) ? ` [${item.npub.slice(0, 14)}...]` : '';
            if (r.ok) {
              success++;
              delLog(`✓ #${r.id} (${filename})${tag} deleted`);
            } else {
              errors++;
              delLog(`✗ #${r.id} (${filename})${tag}: ${r.error || 'unknown'}`, true);
            }
          }

          processed += chunk.length;
          const pct = Math.round((processed / total) * 100);
          delBar.style.width = pct + '%';
          delBar.textContent = `${processed} / ${total}`;

          // Brief breather between chunks so we don't hammer S3 / CF / blossom
          // back-to-back. Skip after the last chunk.
          if (off + REJECT_BATCH_SIZE < total) {
            await new Promise(r => setTimeout(r, 100));
          }
        }

        delBar.classList.remove('progress-bar-animated');
        if (errors === 0) {
          delBar.classList.add('bg-success');
          delStatusEl.textContent = `All ${success} item(s) deleted.`;
          delLog(`Done — ${success} succeeded.`);
        } else {
          delBar.classList.add('bg-warning');
          delStatusEl.textContent = `${success} succeeded, ${errors} failed.`;
          delLog(`Done — ${success} succeeded, ${errors} failed.`);
        }
        delDoneBtn.style.display = '';
        delCloseBtn.style.display = '';
      });

      delDoneBtn.addEventListener('click', () => {
        location.reload();
      });

      // Per-row trigger
      document.querySelectorAll('.delete-offender-media-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          const id = btn.getAttribute('data-incident-id');
          resetDelModal('single');
          delModal.show();
          loadOffenderUploads(id);
        });
      });

      // Bulk trigger
      const bulkDelBtn = document.getElementById('bulkDeleteOffendersBtn');
      const bulkDelStatus = document.getElementById('bulkDeleteOffendersStatus');
      if (bulkDelBtn) {
        bulkDelBtn.addEventListener('click', () => {
          bulkDelStatus.textContent = '';
          resetDelModal('bulk');
          delModal.show();
          loadBulkOffenders(7);
        });
      }

      // Unblacklist User button click handler
      const unblacklistUserButtons = document.querySelectorAll('.unblacklist-user-btn');
      unblacklistUserButtons.forEach(function(button) {
        button.addEventListener('click', function() {
          const incidentId = this.getAttribute('data-incident-id');
          // Confirm action
          if (confirm('Are you sure you want to unblacklist this user?')) {
            // Make AJAX request to unblacklist the user
            fetch('/api/v2/admin/csam/unblacklist', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
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

    // Bulk Submit Unsubmitted Reports
    document.getElementById('bulkSubmitBtn').addEventListener('click', async function() {
      if (!confirm('WARNING: This will submit all unsubmitted CSAM reports from the past 7 days to NCMEC. This action cannot be undone. Are you sure you want to proceed?')) {
        return;
      }

      const btn = this;
      const status = document.getElementById('bulkSubmitStatus');
      const progressDiv = document.getElementById('bulkSubmitProgress');
      const progressBar = document.getElementById('bulkSubmitBar');
      const logDiv = document.getElementById('bulkSubmitLog');

      btn.disabled = true;
      status.textContent = 'Fetching unsubmitted reports...';
      logDiv.textContent = '';
      progressDiv.style.display = 'none';

      function log(msg, isError) {
        const line = document.createElement('div');
        line.textContent = msg;
        if (isError) line.style.color = '#dc3545';
        logDiv.appendChild(line);
        logDiv.scrollTop = logDiv.scrollHeight;
      }

      try {
        // Step 1: Get unsubmitted incident IDs
        const listResp = await fetch('/api/v2/admin/csam/unsubmitted?days=7', { credentials: 'same-origin' });
        const listData = await listResp.json();

        if (!listData.ids || listData.ids.length === 0) {
          status.textContent = 'No unsubmitted reports found in the past 7 days.';
          btn.disabled = false;
          return;
        }

        const ids = listData.ids;
        status.textContent = `Found ${ids.length} unsubmitted report(s). Submitting...`;
        progressDiv.style.display = 'block';
        log(`Starting bulk submission of ${ids.length} report(s)...`);

        let successCount = 0;
        let errorCount = 0;

        // Step 2: Submit each one by one
        for (let i = 0; i < ids.length; i++) {
          const incidentId = ids[i];
          const pct = Math.round(((i + 1) / ids.length) * 100);
          progressBar.style.width = pct + '%';
          progressBar.textContent = `${i + 1} / ${ids.length}`;

          try {
            const resp = await fetch('/api/v2/admin/csam/report/submit-single', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              credentials: 'same-origin',
              body: JSON.stringify({ incidentId: incidentId })
            });
            const data = await resp.json();

            if (data.success) {
              successCount++;
              log(`Incident ${incidentId}: submitted successfully (NCMEC report ID: ${data.result?.response || 'N/A'})`);
            } else {
              errorCount++;
              log(`Incident ${incidentId}: FAILED - ${data.error || JSON.stringify(data.result)}`, true);
            }
          } catch (err) {
            errorCount++;
            log(`Incident ${incidentId}: network error - ${err.message}`, true);
          }

          // Delay between submissions to avoid hammering NCMEC API
          if (i < ids.length - 1) {
            await new Promise(resolve => setTimeout(resolve, 1000));
          }
        }

        // Done
        if (errorCount === 0) {
          status.textContent = `All ${successCount} report(s) submitted successfully!`;
          progressBar.classList.remove('progress-bar-animated');
          progressBar.classList.add('bg-success');
        } else {
          status.textContent = `Done: ${successCount} succeeded, ${errorCount} failed.`;
          progressBar.classList.remove('progress-bar-animated');
          progressBar.classList.add('bg-warning');
        }

        log(`\nComplete: ${successCount} succeeded, ${errorCount} failed.`);

      } catch (err) {
        status.textContent = 'Error: ' + err.message;
        log('Fatal error: ' + err.message, true);
      }

      btn.disabled = false;
    });
  </script>
</body>

</html>