<?php

use Respect\Validation\Rules\Url;

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/functions/session.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/SiteConfig.php');

// Create new Permission object
$perm = new Permission();

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
    if (isset($_POST['button1'])) {
      $sql = "UPDATE uploads_data SET approval_status='approved' WHERE approval_status='pending'";
      if ($link->query($sql) === TRUE) {
        echo "<div class='alert alert-success'>Images approved to view!</div>";
      } else {
        echo "<div class='alert alert-danger'>Error updating record: " . $link->error . "</div>";
      }
    }

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
		WHERE upload_date >= DATE(NOW()) - INTERVAL 7 DAY
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

    ?>
    <form method="post">
      <button type="submit" name="button1" class="btn btn-primary mb-2" onclick="return confirm('Are you sure?')">Approve All</button>
    </form>
    <?php

    // Here is the code that handles displaying images and pagination
    if (!empty($searchFile)) {
      // Search display
      $sql = "SELECT * FROM uploads_data WHERE filename LIKE ?";
      $stmt = $link->prepare($sql);
      $searchTerm = $searchFile . '%';
      $stmt->bind_param('s', $searchTerm);
      $stmt->execute();
      $result = $stmt->get_result();
    } else {
      // General display of images
      $perpage = 200;
      $page = isset($_GET['p']) ? $_GET['p'] : 0;
      $start = $page * $perpage;
      $end = $perpage;

      $sql = "SELECT * FROM uploads_data WHERE approval_status = 'pending' ORDER BY upload_date DESC LIMIT ?, ?";
      $stmt = $link->prepare($sql);
      $stmt->bind_param('ii', $start, $end);
      $stmt->execute();

      $result = $stmt->get_result();
    }

    // Open the container for images
    echo '<div class="row">';

    while ($row = $result->fetch_assoc()) {
      $filename = $row['filename'];
      $image_id = $row['id'];
      $file_type = $row['type'];

      if ($file_type === 'picture') {
        $link_to_image = htmlentities(SiteConfig::getFullyQualifiedUrl('image') . $filename);
        $thumb = htmlentities(SiteConfig::getThumbnailUrl('image') . $filename);
        $display_element = '<img  height ="150" class="card-img-top" src="' . $thumb . '" alt="' . $filename . '">';
      } elseif ($file_type === 'profile') {
        $link_to_image = htmlentities(SiteConfig::getFullyQualifiedUrl('profile_picture') . $filename);
        $thumb = htmlentities(SiteConfig::getThumbnailUrl('profile_picture') . $filename);
        $display_element = '<img  height ="150" class="card-img-top" src="' . $thumb . '" alt="' . $filename . '">';
      } elseif ($file_type = 'video') {
        $link_to_image = htmlentities(SiteConfig::getFullyQualifiedUrl('video') . $filename);
        $video_extensions = ['mp4', 'mov', 'avi', 'wmv', 'flv', 'webm', 'mkv', 'm4v', 'mpg', 'mpeg', '3gp', '3g2', 'm2v', 'm4v', 'svi', 'mxf', 'roq', 'nsv', 'f4v', 'f4p', 'f4a', 'f4b', 'ogv', 'gifv'];
        $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mime_type = 'video/mp4';
        $thumb = htmlentities(SiteConfig::getThumbnailUrl('video') . $filename);
        $display_element = '<video controls height ="150" width ="150"><source src="' . $thumb . '" type="' . $mime_type . '"></video>';
      } else {
        $link_to_image = '';
        $display = "UNKNOWN TYPE!!!";
      }

      // Open the individual image container
      echo '<div class="col-lg-2 col-md-4 col-sm-6 mb-2">';
      echo '  <div class="card h-100">';
      echo '    <div class="position-relative">';
      echo '      <a href="' . $link_to_image . '" target="_blank">';
      echo $display_element;
      echo '      </a>';
      echo '      <span class="badge bg-primary position-absolute top-0 end-0 p-1 fs-6 status-badge">' . $file_type . '</span>';
      echo '    </div>';
      echo '    <div class="card-body">';
      echo '      <form action="change_status.php" method="post">';
      echo '        <input type="hidden" name="id" value="' . $row['id'] . '">';
      echo '        <div class="d-flex justify-content-center">';
      echo '        <div class="btn-group mx-auto" role="group" >';
      echo '          <button type="button" name="status" value="adult" class="btn btn-warning mb-1 btn-xsm status-btn">Adult</button>';
      echo '          <button type="button" name="status" value="rejected" class="btn btn-danger mb-1 btn-xsm status-btn">Flag</button>';
      echo '        </div>';
      echo '        </div>';
      echo '      </form>';
      echo '    </div>';
      echo '  </div>'; // Close card
      echo '</div>'; // Close col


    }


    // Close the container for images
    echo '</div>'; // close row

    $sql = "SELECT COUNT(*) as total FROM uploads_data WHERE approval_status = 'pending'";
    $stmt = $link->prepare($sql);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];

    // Pagination links
    $pages = ceil($total / $perpage);
    echo '<nav aria-label="Page navigation">';
    echo '  <ul class="pagination justify-content-center">';
    for ($i = 0; $i < $pages; $i++) {
      $active_class = (($i == $page) ? 'active' : '');
      echo '    <li class="page-item ' . $active_class . '"><a class="page-link" href="?p=' . $i . '">' . ($i + 1) . '</a></li>';
    }
    echo '  </ul>';
    echo '</nav>';


    $link->close();
    ?>
  </main>
</body>

</html>