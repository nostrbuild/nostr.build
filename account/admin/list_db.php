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

$result = mysqli_query($link, "SELECT * FROM users");
$result2 = mysqli_query($link, "SELECT * FROM users_images");

echo "<table border='1'><small>
<tr>
<th>id</th>
<th>npub</th>
<th>nym</th>
<th>wallet</th>
<th>acctlevel</th>
<th>Created</th>
</tr>";

while ($row = mysqli_fetch_array($result)) {
  echo "<tr>";
  echo "<td>" . $row['id'] . "</td>";
  echo "<td>" . $row['usernpub'] . "</td>";
  echo "<td>" . $row['nym'] . "</td>";
  echo "<td>" . $row['wallet'] . "</td>";
  echo "<td>" . $row['acctlevel'] . "</td>";
  echo "<td>" . $row['created_at'] . "</td>";
  echo "</tr>";
}
echo "</table>";

//print users_images_db

echo "<table border='1'>
<tr>
<th>id</th>
<th>npub</th>
<th>image</th>
<th>folder</th>
<th>flag</th>

</tr>";

while ($row = mysqli_fetch_array($result2)) {
  echo "<tr>";
  echo "<td>" . $row['id'] . "</td>";
  echo "<td>" . $row['usernpub'] . "</td>";
  echo "<td>" . $row['image'] . "</td>";
  echo "<td>" . $row['folder'] . "</td>";
  echo "<td>" . $row['flag'] . "</td>";
  echo "</tr>";
}
echo "</small></table>";


$link->close();
