<?php

use Respect\Validation\Rules\Url;

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/functions/session.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php');

// Create new Permission object
$perm = new Permission();
global $link;

if (!$perm->isAdmin() && !$perm->hasPrivilege('canModerate')) {
  header("location: /login");
  $link->close();
  exit;
}

// For file search
$searchFile = '';
if (isset($_POST['searchFile'])) {
  $path = parse_url($_POST['searchFile'], PHP_URL_PATH);
  // Get the filename
  $searchFile = basename($path);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <title>nostr.build - Admin approve</title>
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

    .table {
      width: 100%;
      margin-bottom: 20px;
      border-collapse: collapse;
    }

    .table th,
    .table td {
      border: 1px solid #ddd;
      padding: 8px;
    }

    .table th {
      padding-top: 12px;
      padding-bottom: 12px;
      text-align: left;
      background-color: #4CAF50;
      color: white;
    }

    .img-thumbnail {
      margin: 5px;
    }

    .btn-xsm {
      font-size: 10px;
      padding: 0.25rem 0.5rem;
      line-height: 1.5;
      border-radius: 0.2rem;
    }
  </style>
  <script>
    if (window.history.replaceState) {
      window.history.replaceState(null, null, window.location.href);
    }

    document.addEventListener('DOMContentLoaded', function() {
      document.querySelectorAll('.status-btn').forEach(function(button) {
        button.addEventListener('click', function(e) {
          e.preventDefault();
          var card = button.closest('.card');
          var id = card.querySelector('input[name="id"]').value;
          var status = button.value;
          var badge = card.querySelector('.status-badge');
          fetch('change_status.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
              },
              body: 'id=' + encodeURIComponent(id) + '&status=' + encodeURIComponent(status),
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                // Change the label and its color based on the status
                if (status === 'adult') {
                  badge.textContent = 'Adult';
                  badge.className = 'badge bg-warning position-absolute top-0 end-0 p-1 fs-6 status-badge';
                } else if (status === 'rejected') {
                  badge.textContent = 'Rejected';
                  badge.className = 'badge bg-danger position-absolute top-0 end-0 p-1 fs-6 status-badge';
                }
              } else {
                alert('Error: ' + data.error);
              }
            });
        });
      });
    });
  </script>
</head>

<body>
  <main class="container main-content">
    <section class="title_section">
      <h1>Admin Image Approve</h1>
    </section>
    <!-- Add Search Box -->
    <form method="post" class="mb-3">
      <div class="input-group">
        <input type="text" class="form-control" name="searchFile" placeholder="Enter filename or URL to search" value="<?= $searchFile ?? '' ?>">
        <button class="btn btn-primary" type="submit">Search</button>
      </div>
    </form>
    <?php

    // Query to get the total size of all uploads
    $sql = "SELECT SUM(file_size) AS total_size, COUNT(*) AS total_count, type FROM uploads_data GROUP BY type";
    $result = $link->query($sql);
    $totalSize = 0;
    $totalCount = 0;
    echo '<p class="fw-bold">Breakdown by type: </p>';
    while ($row = $result->fetch_assoc()) {
      $type = $row['type'];
      $count = $row['total_count'];
      $sizeGB = number_format($row['total_size'] / (1024 * 1024 * 1024), 2);

      echo '<p><span class="text-primary">' . $type . ':</span> Count: ' . $count . ' Size: ' . $sizeGB . ' GB</p>';

      $totalSize += $row['total_size'];
      $totalCount += $count;
    }

    $totalSizeGB = number_format($totalSize / (1024 * 1024 * 1024), 2);
    echo '<p class="fw-bold">Total Count: <span class="text-primary">' . $totalCount . '</span></p>';
    echo '<p class="fw-bold">Total Size: <span class="text-primary">' . $totalSizeGB . ' GB</span></p>';

    // Query to get the number of uploads per day for the past week with total size
    $sql = "SELECT DATE(upload_date) AS upload_day, COUNT(*) AS upload_count, SUM(file_size) AS total_size
		FROM uploads_data
		GROUP BY DATE(upload_date)
		ORDER BY upload_day DESC";
    $result = $link->query($sql);

    // Start the table
    echo '<table class="table table-striped">';
    echo '<thead><tr><th>Date</th><th>Uploads</th><th>Total Size (GB)</th></tr></thead>';
    echo '<tbody>';

    // Populate the table with the array data
    while ($row = $result->fetch_assoc()) {
      $uploadDay = $row['upload_day'];
      $uploadCount = $row['upload_count'];
      $totalSize = $row['total_size'];
      $totalSizeGB = number_format($totalSize / (1024 * 1024 * 1024), 2);

      echo '<tr><td>' . $uploadDay . '</td><td>' . $uploadCount . '</td><td>' . $totalSizeGB . '</td></tr>';
    }

    echo '</tbody></table>';

    $link->close();
    ?>
  </main>
</body>

</html>