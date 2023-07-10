<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/S3Service.class.php';

global $link;
global $awsConfig;
$perm = new Permission();
$s3 = new S3Service($awsConfig);

if (!$perm->validateLoggedin()) {
    header("location: /login");
    $link->close();
    exit;
}

if (!isset($_GET['idList'])) {
    echo "Error: Missing idList parameter";
    exit;
}

$idListRaw = $_GET['idList'];
$idList = preg_split('/(ide_|idr_)/', $idListRaw, -1, PREG_SPLIT_NO_EMPTY); // remove empty entries and correctly split
$prefixList = array_filter(preg_split('/[0-9]+/', $idListRaw)); // get the prefixes
error_log(print_r($idList, true));
error_log(print_r($prefixList, true));

foreach ($idList as $index => $id) {
    $prefix = $prefixList[$index]; // get the corresponding prefix
    $stmt = mysqli_prepare($link, "SELECT * FROM users_images WHERE id = ? AND usernpub = ?");

    mysqli_stmt_bind_param($stmt, "is", $id, $_SESSION['usernpub']);

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_array($result);
        $file_path = strpos($row['image'], '://') === false ? '/p/' . $row['image'] : parse_url($row['image'], PHP_URL_PATH);

        if ($prefix == 'ide_') { // if it's an image
            $s3_file_path = ltrim($file_path, '/'); // remove the leading slash
            if ($s3->getObjectMetadataFromS3($s3_file_path) !== false) {
                error_log("Deleting file from S3: " . $s3_file_path . PHP_EOL);
                if (!$s3->deleteFromS3($s3_file_path)) {
                    echo "Error: File deletion failed";
                    print($s3_file_path);
                    mysqli_stmt_close($stmt);
                    mysqli_close($link);
                    exit;
                } else {
                    // Delete the record with the ID ONLY AFTER S3 deletion was successful
                    $del_stmt = mysqli_prepare($link, "DELETE FROM users_images WHERE id = ? AND usernpub = ?");
                    mysqli_stmt_bind_param($del_stmt, "is", $id, $_SESSION['usernpub']);
                    mysqli_stmt_execute($del_stmt);
                    mysqli_stmt_close($del_stmt);
                }
            }
        } elseif ($prefix == 'idr_') { // if it's a folder
            // TODO: This is a temp hack to delete the folder while old folder structure is in place
            // Delete the folder from users_images_folders table
            $del_folder_stmt = mysqli_prepare($link, "DELETE FROM users_images_folders WHERE folder = ? AND usernpub = ?");
            mysqli_stmt_bind_param($del_folder_stmt, "ss", $row['folder'], $_SESSION['usernpub']);
            mysqli_stmt_execute($del_folder_stmt);
            mysqli_stmt_close($del_folder_stmt);

            // Update all images from the same usernpub to have NULL in the folder column
            error_log("Updating images from the same usernpub to have NULL in the folder column: " . $row['folder'] . PHP_EOL);
            $update_stmt = mysqli_prepare($link, "UPDATE users_images SET folder = NULL WHERE usernpub = ? AND folder = ?");
            mysqli_stmt_bind_param($update_stmt, "ss", $_SESSION['usernpub'], $row['folder']);
            mysqli_stmt_execute($update_stmt);
            mysqli_stmt_close($update_stmt);

            // Delete the record with the ID
            $del_stmt = mysqli_prepare($link, "DELETE FROM users_images WHERE id = ? AND usernpub = ?");
            mysqli_stmt_bind_param($del_stmt, "is", $id, $_SESSION['usernpub']);
            mysqli_stmt_execute($del_stmt);
            mysqli_stmt_close($del_stmt);
        }
    } else {
        echo "Error: Record not found";
        mysqli_stmt_close($stmt);
        mysqli_close($link);
        exit;
    }
    mysqli_stmt_close($stmt);
}

mysqli_close($link);

header("location: /account");
exit;
