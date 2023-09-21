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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <style>
        body {
            font: 14px sans-serif;
            background-color: #f8f9fa;
        }

        .container {
            max-width: 450px;
        }

        .card {
            border-radius: 15px;
        }

        .card-header {
            text-align: center;
        }

        .card-body {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
            /* Set the width of the card body to 90% */
        }

        .profile-img {
            width: 170px;
            height: 170px;
            border-radius: 50%;
            object-fit: cover;
        }

        .form-group {
            margin-top: 15px;
            width: 100%;
            /* Set the width of the form group to 100% */
        }

        /* Set the width of the form control to 100% */
        .form-group .form-control {
            width: 100%;
        }

        .form-group input[type="submit"] {
            margin-top: 15px;
            width: 100%;
            /* Make the button full width */
        }

        form {
            width: 90%;
            /* Set the width of the form to 90% */
        }
    </style>

</head>

<body>
    <div class="container py-5">
        <div class="card">
            <h3 class="card-header">Welcome, <b><?= htmlspecialchars((!empty($nym) ? $nym : 'anon')) ?></b></h3>
            <div class="card-body">
                <img class="profile-img" src="<?= htmlspecialchars((!empty($ppic) ? $ppic : '/assets/01.png')) ?>" alt="Profile Pic">
                <?php if (!empty($error)) : ?>
                    <div class="alert alert-danger mt-3" role="alert">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                <form action="" method="post">
                    <div class="form-group">
                        <label for="nym">Nym</label>
                        <input class="form-control" type="text" name="nym" id="nym" placeholder="@nym" value="<?= htmlspecialchars($nym) ?>">
                    </div>
                    <div class="form-group">
                        <label for="ppic">Profile Picture URL</label>
                        <input class="form-control" type="text" name="ppic" id="ppic" placeholder="profile picture URL" value="<?= htmlspecialchars($ppic) ?>">
                    </div>
                    <div class="form-group">
                        <label for="wallet">Your Wallet Address</label>
                        <input class="form-control" type="text" name="wallet" id="wallet" placeholder="your wallet address" value="<?= htmlspecialchars($wallet) ?>">
                    </div>
                    <div class="form-group mt-3">
                        <input class="btn btn-primary btn-block" type="submit" value="Update">
                    </div>
                </form>
                <a href="reset-password.php" class="btn btn-warning mt-3">Reset Password</a>
            </div>
        </div>
    </div>
</body>

</html>