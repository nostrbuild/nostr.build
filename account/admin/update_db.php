<?php
// Include config file
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php');

// Create new Permission object
$perm = new Permission();

if (!$perm->isAdmin()) {
    header("location: /login");
    $link->close(); // CLOSE MYSQL LINK
    exit;
}

$user =  $_SESSION["usernpub"];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>nostr.build DB updater</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            font: 14px sans-serif;
            text-align: center;
        }
    </style>
</head>

<body>


    <form action="" method="post">&ensp;
        <p>
            <input type="text" name="usernpub" id="usernpub" placeholder="user's npub">
            <input type="text" name="nym" id="nym" placeholder="@nym">
            <input type="submit" value="Submit">
        </p>
    </form>

    <form action="" method="post">&ensp;
        <p>
            <input type="text" name="usernpub" id="usernpub" placeholder="user's npub">
            <input type="text" name="ppic" id="ppic" placeholder="link to profile pic">
            <input type="submit" value="Submit">
        </p>
    </form>

    <form action="" method="post">&ensp;
        <p>
            <input type="text" name="usernpub" id="usernpub" placeholder="user's npub">
            <input type="text" name="acctlevel" id="acctlevel" placeholder="account level">
            <input type="submit" value="Submit">
        </p>
    </form>

</body>

</html>

<?php


//update nym in DB
if (array_key_exists('nym', $_POST)) {

    $nym =  $_REQUEST['nym'];
    $user = $_POST['usernpub'];

    if (substr($nym, 0, 1) != '@') $nym = '@' . $nym;

    echo "User: " . $user;
    echo ", acct level " . $acctlevel;

    $sql = "UPDATE users SET nym='$nym' WHERE usernpub='$user' ";

    if (mysqli_query($link, $sql)) {
        echo "<a>Updated nym!</a>";
    } else echo "ERROR: Hush! Sorry $sql. " . mysqli_error($link);

    $link->close(); // CLOSE MYSQL LINK
}

//update pic in DB
if (array_key_exists('ppic', $_POST)) {

    $ppic =  $_REQUEST['ppic'];

    $user = $_POST['usernpub'];

    echo "User: " . $user;
    echo ", ppic " . $ppic;

    $sql = "UPDATE users SET ppic='$ppic' WHERE usernpub='$user' ";

    if (mysqli_query($link, $sql)) {
        echo "<a>Updated profile pic!</a>";
    } else echo "ERROR: Hush! Sorry $sql. " . mysqli_error($link);

    $link->close(); // CLOSE MYSQL LINK
}

//update acctlevel in DB
if (array_key_exists('acctlevel', $_POST)) {

    $acctlevel =  $_REQUEST['acctlevel'];

    $user = $_POST['usernpub'];

    echo "User: " . $user;
    echo ", acct level " . $acctlevel;

    $sql = "UPDATE users SET acctlevel='$acctlevel' WHERE usernpub='$user' ";

    if (mysqli_query($link, $sql)) {
        echo "<a>, updated!</a>";
    } else echo "ERROR: Hush! Sorry $sql. " . mysqli_error($link);

    $link->close(); // CLOSE MYSQL LINK
}

$link->close(); // CLOSE MYSQL LINK
?>