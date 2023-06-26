<?php
// TODO: Migrate to APIv2 and use Table class
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';

if (isset($_GET['user'])) {
    $username = $_GET['user'];

    $stmt = $link->prepare("SELECT u.nym as nym, u.ppic as ppic, u.wallet as wallet, ui.image as image FROM users u LEFT JOIN users_images ui ON u.usernpub = ui.usernpub WHERE u.usernpub=? AND ui.flag=1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    $row = mysqli_fetch_array($result);
    if ($row) {
        $nym = $row['nym'];
        $ppic = $row['ppic'];
        $wallet = $row['wallet'];
        $imgarray[] = $row['image'];
    } else {
        echo 'No user data found';
        exit();
    }

    while ($row = mysqli_fetch_array($result)) {
        $imgarray[] = $row['image'];
    }

    $array = array('user_info' => array('nym' => $nym, 'ppic' => $ppic, 'wallet' => $wallet), 'images' => $imgarray);
} else {
    $stmt = $link->prepare("
    SELECT 
        u.nym as nym, 
        u.usernpub as usernpub, 
        RAND_IMAGES.countImage as countImage, 
        RAND_IMAGES.image as image
    FROM 
        users u 
    INNER JOIN (
        SELECT 
            usernpub, 
            COUNT(id) AS countImage, 
            (SELECT image FROM users_images WHERE usernpub = ui.usernpub AND flag=1 ORDER BY RAND() LIMIT 1) AS image
        FROM 
            users_images ui
        WHERE 
            flag=1 
        GROUP BY 
            usernpub
    ) RAND_IMAGES ON u.usernpub = RAND_IMAGES.usernpub
    ORDER by RAND()
");

    $stmt->execute();
    $result_users = $stmt->get_result();

    $array = array();
    while ($row_users = $result_users->fetch_array(MYSQLI_ASSOC)) {
        $username = $row_users['nym'];
        $usernick = $row_users['usernpub'];
        $array[] = array('nym' => $username, 'usernpub' => $usernick, 'countImage' => $row_users['countImage'], 'userURL' => 'https://nostr.build/creators/creator/?user=' . $usernick, 'image' => $row_users['image']);
    }
}
echo json_encode($array);
mysqli_close($link);
