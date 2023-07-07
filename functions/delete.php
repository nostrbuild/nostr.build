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

// Check if idList parameter exists
if (!isset($_GET['idList'])) {
    echo "Error: Missing idList parameter";
    exit;
}

$idListRaw = $_GET['idList'];
$idList = explode("ide_", $idListRaw);
print_r($idList);

// Prepare statement for preventing SQL Injection
$stmt = mysqli_prepare($link, "SELECT * FROM users_images WHERE id = ? AND usernpub = ?");

for ($i = 1; $i < sizeof($idList); $i++) {
    // Bind parameter to statement
    mysqli_stmt_bind_param($stmt, "is", $idList[$i], $_SESSION['usernpub']);

    // Execute the statement
    mysqli_stmt_execute($stmt);

    // Bind result to variable
    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_array($result);
	$file_path = parse_url($row['image'], PHP_URL_PATH);

        // Delete the record
        $del_stmt = mysqli_prepare($link, "DELETE FROM users_images WHERE id = ? AND usernpub = ?");
        mysqli_stmt_bind_param($del_stmt, "is", $idList[$i], $_SESSION['usernpub']);
        mysqli_stmt_execute($del_stmt);

        $path = $_SERVER['DOCUMENT_ROOT'] . $file_path;

        // Check if the file exists before trying to delete it
        if (file_exists($path)) {
            unlink($path);
        } else {
            echo "Error: File not found";
	    print($path);
            mysqli_stmt_close($stmt);
            mysqli_close($link);
            exit;
        }
    } else {
        echo "Error: Record not found";
        mysqli_stmt_close($stmt);
        mysqli_close($link);
        exit;
    }
}

mysqli_stmt_close($stmt);
mysqli_close($link);

header("location: /account");
exit;
