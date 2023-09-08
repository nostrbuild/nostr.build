<?php

use Respect\Validation\Rules\Url;

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/functions/session.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php');

// Create new Permission object
$perm = new Permission();
global $link;

if (!$perm->isAdmin()) {
  header("location: /login");
  $link->close();
  exit;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <title>nostr.build - Free uploads stats</title>
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
  </script>
</head>

<body>
  <main class="container main-content">
    <section class="title_section">
      <h1>Free Uploads Stats</h1>
    </section>
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