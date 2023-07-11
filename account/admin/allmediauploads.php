<?php
// Include config file
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/functions/session.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php');

// Create new Permission object
$perm = new Permission();

if (!$perm->isAdmin()) {
    header("location: /login");
    $link->close();
    exit;
}

$query = "SELECT * FROM uploads_data";
$result = mysqli_query($link, $query);

if (mysqli_num_rows($result) > 0) {

    echo "<table border='1'><small>
<tr>
<th>id</th>
<th>filename</th>
<th>approval_status</th>
<th>upload_date</th>
<th>type</th>
</tr>";

    // Fetch all rows and print them
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['filename'] . "</td>";
        echo "<td>" . $row['approval_status'] . "</td>";
        echo "<td>" . $row['upload_date'] . "</td>";
        echo "<td>" . $row['type'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No records found";
}

// Close DB connection
mysqli_close($link);
