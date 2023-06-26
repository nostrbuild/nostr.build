<?php
// Include config and session files
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';

$perm = new Permission();

// Check if the user is logged in, if not then redirect to login page
if (!$perm->validateLoggedin()) {
    header("location: /login");
    $link->close();
    exit;
}

// Check if id and flag parameters exist
if (!isset($_GET['id']) && !isset($_GET['flag'])) {
    echo "Error: Missing id or flag parameter";
    $link->close();
    exit;
}

$id = $_GET['id'];
$flag = $_GET['flag'];

echo $id;
echo $flag;

// Prepare the statement for preventing SQL Injection
// MUST ALWAYS USE usernpub SESSION VARIABLE TO PREVENT HIJACKING
$stmt = mysqli_prepare($link, "UPDATE users_images SET flag = ? WHERE id = ? AND usernpub = ?");

// Bind parameters
mysqli_stmt_bind_param($stmt, "sis", $flag, $id, $_SESSION['usernpub']);

// Execute the query
mysqli_stmt_execute($stmt);

mysqli_stmt_close($stmt);
mysqli_close($link);

header("location: /account");
exit;

?>
