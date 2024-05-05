<?php
// Initialize the session
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Account.class.php';

global $link;
$perm = new Permission();

// Check if the user is logged in, if not then redirect to login page
if (!$perm->validateLoggedin()) {
    header("location: /login");
    $link->close();
    exit;
}

// Assign session values
$ppic = $_SESSION["ppic"];
$nym = $_SESSION["nym"];
$wallet = $_SESSION["wallet"];

$error = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $account = new Account($_SESSION["usernpub"], $link);

        // Update variables with POST data if available
        $nym = $_POST['nym'] ?? $nym;
        $ppic = $_POST['ppic'] ?? $ppic;
        $wallet = $_POST['wallet'] ?? $wallet;

        $account->updateAccount(nym: $nym, ppic: $ppic, wallet: $wallet);
        $link->close();
        header("Location: /account");
        exit;
    } catch (Exception $e) {
        error_log($e->getMessage() . PHP_EOL);
        $error = "Something went wrong. Please try again later.";
    } finally {
        $link->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>nostr.build Profile Settings</title>
    <link href="/styles/twbuild.css?v=52f84be5d831ea188a9925a83190aac5" rel="stylesheet">
</head>

<body class="bg-gray-100 text-sm text-gray-800">
    <div class="mx-auto py-5 px-4 max-w-sm">
        <div class="bg-white shadow-lg rounded-lg overflow-hidden">
            <h3 class="text-center text-lg font-bold p-4">Welcome, <b><?= htmlspecialchars((!empty($nym) ? $nym : 'anon')) ?></b></h3>
            <div class="flex flex-col items-center p-4">
                <img class="w-32 h-32 rounded-full object-cover" src="<?= htmlspecialchars((!empty($ppic) ? $ppic : '/assets/01.png')) ?>" alt="Profile Pic">
                <?php if (!empty($error)) : ?>
                    <div class="mt-3 p-3 bg-red-100 border border-red-400 text-red-700 rounded" role="alert">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                <form action="" method="post" class="w-full mt-4">
                    <div class="mb-4">
                        <label for="nym" class="block text-gray-700 text-sm font-bold mb-2">Nym</label>
                        <input class="block w-full px-3 py-2 border border-gray-300 rounded-md text-gray-700 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" type="text" name="nym" id="nym" placeholder="@nym" value="<?= htmlspecialchars($nym) ?>">
                    </div>
                    <div class="mb-4">
                        <label for="ppic" class="block text-gray-700 text-sm font-bold mb-2">Profile Picture URL</label>
                        <input class="block w-full px-3 py-2 border border-gray-300 rounded-md text-gray-700 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" type="text" name="ppic" id="ppic" placeholder="profile picture URL" value="<?= htmlspecialchars($ppic) ?>">
                    </div>
                    <div class="mb-4">
                        <label for="wallet" class="block text-gray-700 text-sm font-bold mb-2">Your Wallet Address</label>
                        <input class="block w-full px-3 py-2 border border-gray-300 rounded-md text-gray-700 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" type="text" name="wallet" id="wallet" placeholder="your wallet address" value="<?= htmlspecialchars($wallet) ?>">
                    </div>
                    <div class="mt-3">
                        <input class="btn block w-full text-white bg-blue-500 hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center" type="submit" value="Update">
                    </div>
                </form>
                <a href="reset-password.php" class="btn mt-3 text-white bg-yellow-400 hover:bg-yellow-500 focus:ring-4 focus:ring-yellow-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center">Reset Password</a>
            </div>
        </div>
    </div>
</body>
</html>