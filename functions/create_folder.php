<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';

global $link;
$perm = new Permission();

if (!$perm->validateLoggedin()) {
    header("location: /login");
    $link->close();
    exit;
}

if (!isset($_GET['folder_name'])) {
    echo "Error: Missing folder_name parameter";
    $link->close();
    exit;
}

$folder_name = $_GET['folder_name'];

//$image = 'https://' . $_SERVER['SERVER_NAME'] . "/p/Folder.png";
// TODO: This approach is shit and it must die soon!
$image = 'https://nostr.build/p/Folder.png';

$stmt = $link->prepare("INSERT INTO users_images (usernpub, image, folder) VALUES (?, ?, ?)");

if ($stmt === false) {
    echo "Error preparing statement";
    $link->close();
    exit;
}

$stmt->bind_param("sss", $_SESSION["usernpub"], $image, $folder_name);

try {
    $stmt->execute();
    echo "Added folder image<BR>";
} catch (Exception $e) {
    error_log('Caught exception: ' .  $e->getMessage());
    echo "An error occurred while adding the folder image.";
}

$stmt->close();
$link->close();

header("location: /account");
exit;
