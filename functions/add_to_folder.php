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

// Check if fld and idList parameters exist
if (!isset($_GET['fld'], $_GET['idList'])) {
    echo "Error: Missing fld or idList parameter";
    $link->close();
    exit;
}

$folder_name = $_GET['fld'];
$idListRaw = $_GET['idList'];
$idList = explode("id", $idListRaw);

// Prepare the statement for preventing SQL Injection
$stmt = mysqli_prepare($link, "UPDATE users_images SET folder = ? WHERE id = ? AND usernpub = ?");

// Check if prepare statement was successful
if (!$stmt) {
    echo "Error: Prepare statement failed";
    $link->close();
    exit;
}

for ($i = 1; $i < count($idList); $i++) {
    // Bind parameters
    if (!mysqli_stmt_bind_param($stmt, "sis", $folder_name, $idList[$i], $_SESSION["usernpub"])) {
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
}

mysqli_stmt_close($stmt);
$link->close();

header("Location: /account");
exit;
