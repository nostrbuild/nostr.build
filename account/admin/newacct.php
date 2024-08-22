<?php
// Include config file
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php');

// Create new Permission object
$perm = new Permission();

if (!$perm->isAdmin()) {
    header("location: /login");
    // PERSIST: $link->close();
    exit;
}

$user =  $_SESSION["usernpub"];

$result = mysqli_query($link, "SELECT * FROM users");

while ($row = mysqli_fetch_array($result)) {
  if ($row['acctlevel'] == 0) {
    echo "<table border='1'><small><tr><th>id</th><th>npub</th><th>Created</th></tr>";
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . $row['usernpub'] . "</td>";
    echo "<td>" . $row['created_at'] . "</td>";
    echo "</tr>";
  }
}
echo "</table>";

?>

<form action="" method="post">&ensp;
  <p>
    <input type="text" name="usernpub" id="usernpub" placeholder="user's npub">
    <input type="text" name="acctlevel" id="acctlevel" placeholder="account level">
    <input type="submit" value="Submit">
  </p>
</form>

<?php

//update acctlevel in DB
if (array_key_exists('acctlevel', $_POST)) {

  $acctlevel =  $_REQUEST['acctlevel'];

  $user = $_POST['usernpub'];

  echo "User: " . $user;
  echo ", acct level " . $acctlevel;

  $sql = "UPDATE users SET acctlevel='$acctlevel' WHERE usernpub='$user' ";

  if (mysqli_query($link, $sql)) {
    echo "<a>, updated!</a>";
  } else echo "ERROR: Hush! Sorry $sql. " . mysqli_error($link);
}

// PERSIST: $link->close();
?>