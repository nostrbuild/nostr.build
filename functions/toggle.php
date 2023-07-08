<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';

global $link;
$perm = new Permission();

// Check if the user is logged in, if not then redirect to login page
if (!$perm->validateLoggedin()) {
    header("Location: /login");
    $link->close();
    exit;
}

// Check if id and flag parameters exist
if (!isset($_GET['id'], $_GET['flag'])) {
    echo "Error: Missing id or flag parameter";
    $link->close();
    exit;
}

$id = $_GET['id'];
$flag = $_GET['flag'];

// Prepare the statement for preventing SQL Injection
$stmt = mysqli_prepare($link, "UPDATE users_images SET flag = ? WHERE id = ? AND usernpub = ?");

// Check if prepare statement was successful
if (!$stmt) {
    echo "Error: Prepare statement failed";
    $link->close();
    exit;
}

// Bind parameters
if (!mysqli_stmt_bind_param($stmt, "sis", $flag, $id, $_SESSION['usernpub'])) {
    echo "Error: Binding parameters failed";
    mysqli_stmt_close($stmt);
    $link->close();
    exit;
}

// Execute the query
if (!mysqli_stmt_execute($stmt)) {
    echo "Error: Query execution failed";
    mysqli_stmt_close($stmt);
    $link->close();
    exit;
}

mysqli_stmt_close($stmt);
$link->close();

header("Location: /account");
exit;
