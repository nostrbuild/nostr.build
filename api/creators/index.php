<?php
// TODO: Migrate to APIv2 and use Table class
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/SiteConfig.php';

// Temp workaround for new URL structure
function getImageUrl($image, $mime)
{
    $type = explode('/', $mime)[0];
    // Parse URL and get only the filename
    $parsed_url = parse_url($image);
    $filename = pathinfo($parsed_url['path'], PATHINFO_BASENAME);
    // Add 'professional_account_' prefix to the $type
    $professional_type = 'professional_account_' . $type;

    // Use SiteConfig to get the base URL for this type
    try {
        $base_url = SiteConfig::getFullyQualifiedUrl($professional_type);
    } catch (Exception $e) {
        // Handle exception or use a default URL
        $base_url = SiteConfig::ACCESS_SCHEME . "://" . SiteConfig::DOMAIN_NAME . "/p/"; // default URL in case of error
    }

    return $base_url . $filename;
}

if (isset($_GET['user'])) {
    $username = $_GET['user'];

    $stmt = $link->prepare("SELECT u.nym as nym, u.ppic as ppic, u.wallet as wallet, ui.image as image, ui.mime_type as mime_type FROM users u LEFT JOIN users_images ui ON u.usernpub = ui.usernpub WHERE u.usernpub=? AND ui.flag=1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    $row = mysqli_fetch_array($result);
    if ($row) {
        $nym = $row['nym'];
        $ppic = $row['ppic'];
        $wallet = $row['wallet'];
        $imgarray[] = getImageUrl($row['image'], $row['mime_type']);
    } else {
        echo 'No user data found';
        exit();
    }

    while ($row = mysqli_fetch_array($result)) {
        $imgarray[] = getImageUrl($row['image'], $row['mime_type']);
    }

    $array = array('user_info' => array('nym' => $nym, 'ppic' => $ppic, 'wallet' => $wallet), 'images' => $imgarray);
} else {
    $stmt = $link->prepare(
        "SELECT u.*, i.image, i.mime_type, c.countImage
        FROM users AS u
        INNER JOIN (
            SELECT *, ROW_NUMBER() OVER(PARTITION BY usernpub ORDER BY RAND()) as rn 
            FROM users_images 
            WHERE flag=1
        ) AS i ON u.usernpub = i.usernpub
        INNER JOIN (
            SELECT usernpub, COUNT(*) as countImage 
            FROM users_images
            WHERE flag=1
            GROUP BY usernpub
        ) AS c ON u.usernpub = c.usernpub
        WHERE i.rn = 1
        ORDER BY RAND()");

    $stmt->execute();
    $result_users = $stmt->get_result();

    $array = array();
    while ($row_users = $result_users->fetch_array(MYSQLI_ASSOC)) {
        $new_url = getImageUrl($row_users['image'], $row_users['mime_type']);
        $username = $row_users['nym'];
        $usernick = $row_users['usernpub'];
        $array[] = array('nym' => $username, 'usernpub' => $usernick, 'countImage' => $row_users['countImage'], 'userURL' => 'https://nostr.build/creators/creator/?user=' . $usernick, 'image' => $new_url);
    }
}
echo json_encode($array);
mysqli_close($link);
