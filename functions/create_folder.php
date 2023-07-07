<?php
// Include config and session files
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';

global $link;
$perm = new Permission();

// Check if the user is logged in, if not then redirect to login page
if (!$perm->validateLoggedin()) {
    header("location: /login");
    $link->close();
    exit;
}

// Check if folder_name parameter exists
if (!isset($_GET['folder_name'])) {
    echo "Error: Missing folder_name parameter";
    $link->close();
    exit;
}

$folder_name = $_GET['folder_name'];

// Prepare statement to prevent SQL Injection
$stmt = $link->prepare("INSERT INTO users_images_folders (usernpub, folder) VALUES (?, ?)");

// Bind parameters
$stmt->bind_param("ss", $folder_name, $_SESSION["usernpub"]);

// Execute the query
try {
    $stmt->execute();
    echo "<a>Added folder image</a>" . "<BR>";
} catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}

$stmt->close();
$link->close();

header("location: /account");
exit;
