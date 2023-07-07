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

// Check if fld parameter exists
if (!isset($_GET['fld']) && !isset($_GET['idList'])) {
    echo "Error: Missing fld parameter";
    $link->close();
    exit;
}

$folder_name = $_GET['fld'];
$idListRaw = $_GET['idList'];
$idList = explode("id", $idListRaw);

// Prepare the statement for preventing SQL Injection
// ALWAYS use usernpub stored in SESSION to prevent unauthorized access
$stmt = $link->prepare("UPDATE users_images SET folder = ? WHERE id = ? AND usernpub = ?");

for ($i = 1; $i < sizeof($idList); $i++) {
    echo $idList[$i] . "<BR>";
    // Bind parameters
    $stmt->bind_param("sis", $folder_name, $idList[$i], $_SESSION["usernpub"]);
    // Execute the query
    $stmt->execute();
}

$stmt->close();
$link->close();

header("location: /account");
exit;
