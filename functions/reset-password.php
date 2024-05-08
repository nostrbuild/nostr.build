<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Account.class.php';

global $link;
$perm = new Permission();

if (!$perm->validateLoggedin()) {
    header("location: /login");
    $link->close();
    exit;
}

$new_password_err = $confirm_password_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password = trim($_POST["new_password"] ?? '');
    $confirm_password = trim($_POST["confirm_password"] ?? '');

    if (empty($new_password)) {
        $new_password_err = "Please enter the new password.";
    } elseif (!preg_match('/^(?=.*\d)(?=.*[A-Z])(?=.*[a-z])(?=.*[^a-zA-Z0-9])(?!.*\s).{8,}$/', $new_password)) {
        $new_password_err = "Password must be at least 8 characters long and include at least one uppercase letter, one lowercase letter, one digit, and one special character.";
    } elseif (empty($confirm_password)) {
        $confirm_password_err = "Please confirm the password.";
    } elseif ($new_password !== $confirm_password) {
        $confirm_password_err = "Passwords did not match.";
    } else {
        $account = new Account($_SESSION["usernpub"], $link);
        try {
            $account->changePassword($new_password);
            session_destroy();
            header("Location: /login");
            $link->close(); // Exit will terminate script imeediately, so close connection first
            exit;
        } catch (Exception $e) {
            error_log($e->getMessage() . PHP_EOL);
            $new_password_err = "Something went wrong. Please try again later.";
        } finally {
            $link->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Reset Password</title>
    <link href="/styles/twbuild.css?v=b570d4fe61ed84c679494a5c2b6ecd6f" rel="stylesheet">
</head>

<body class="bg-gray-100 font-sans">
    <div class="container mx-auto py-5 px-4 max-w-sm">
        <div class="card bg-white shadow-lg rounded-lg overflow-hidden">
            <h3 class="text-center text-lg font-bold p-4">Reset Password</h3>
            <div class="card-body flex flex-col p-4">
                <p>Please fill out this form to reset your password.</p>
                <form action="" method="post">
                    <div class="form-group mb-4">
                        <label class="font-bold">New Password</label>
                        <input type="password" name="new_password" class="form-control block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 <?= (!empty($new_password_err)) ? 'border-red-500' : ''; ?>" value="<?= htmlspecialchars($new_password); ?>">
                        <span class="text-red-500 text-sm"><?= $new_password_err; ?></span>
                    </div>
                    <div class="form-group mb-4">
                        <label class="font-bold">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 <?= (!empty($confirm_password_err)) ? 'border-red-500' : ''; ?>">
                        <span class="text-red-500 text-sm"><?= $confirm_password_err; ?></span>
                    </div>
                    <div class="form-group mt-3">
                        <input type="submit" class="w-full text-white bg-blue-500 hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 text-center" value="Submit">
                        <a class="block text-center text-blue-600 hover:text-blue-700 mt-4" href="/account">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>

</html>