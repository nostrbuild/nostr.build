<?php
// Include config, session, and Permission class files
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';

// Create new Permission object
$perm = new Permission();

// Check if the user is already logged in, if yes then redirect to the account page
if ($perm->validateLoggedin()) {
    header("location: /account");
    exit;
}

// Define variables and initialize with empty values
$usernpub = $password = "";
$usernpub_err = $password_err = $login_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Check if usernpub is empty
    if (empty(trim($_POST["usernpub"]))) {
        $usernpub_err = "Please enter usernpub.";
    } else {
        $usernpub = trim($_POST["usernpub"]);
    }

    // Check if password is empty
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Validate credentials
    if (empty($usernpub_err) && empty($password_err)) {
        // Prepare a select statement
        $sql = "SELECT id, usernpub, password, acctlevel, nym, ppic, wallet, flag, accflags FROM users WHERE usernpub = ?";

        if ($stmt = mysqli_prepare($link, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "s", $param_usernpub);

            // Set parameters
            $param_usernpub = $usernpub;

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                // Store result
                mysqli_stmt_store_result($stmt);

                // Check if usernpub exists, if yes then verify password
                if (mysqli_stmt_num_rows($stmt) == 1) {
                    // Bind result variables
                    mysqli_stmt_bind_result($stmt, $id, $usernpub, $hashed_password, $acctlevel, $nym, $ppic, $wallet, $flag, $accflags);
                    if (mysqli_stmt_fetch($stmt)) {
                        if (password_verify($password, $hashed_password)) {
                            // Store data in session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["usernpub"] = $usernpub;
                            $_SESSION["acctlevel"] = $acctlevel;
                            $_SESSION["nym"] = $nym;
                            $_SESSION["ppic"] = $ppic;
                            $_SESSION["wallet"] = $wallet;
                            $_SESSION["flag"] = $flag;
                            $_SESSION["accflags"] = json_decode($accflags, true); // Account flags, e.g., moderator, etc.
                            error_log("User " . $_SESSION["usernpub"] . " logged in successfully." . PHP_EOL);
                            error_log("User " . $_SESSION["usernpub"] . " has the following permissions: " . print_r($_SESSION["accflags"], true) . PHP_EOL);
                            /**
                             * JSON:
                             * {
                             *  "canModerate": true,
                             *  "canViewStats": false,
                             *  "canManageUsers": false,
                             *  "canEditContent": true
                             * }
                             * 
                             * To grant Moderator permissions, use the following SQL query (for now)
                             * UPDATE users SET accflags = '{"canModerate": true, "canViewStats": false, "canManageUsers": false, "canEditContent": true}' WHERE usernpub = '<npub>';
                             */

                            // Auto populate nym and ppic - Read npub1 user's JSON file, Decode JSON data into PHP array, all user data exists in 'data' object
                            $api_url = 'https://nostrstuff.com/api/users/' . $_SESSION["usernpub"];

                            try {
                                $response_data = @file_get_contents($api_url);
                                if ($response_data === false) {
                                    throw new Exception("Error retrieving data from $api_url");
                                }

                                $response_data = json_decode($response_data);

                                if (json_last_error() !== JSON_ERROR_NONE) {
                                    throw new Exception("Error decoding JSON: " . json_last_error_msg());
                                }

                                $character = json_decode($response_data->content);

                                // Variables to hold new data
                                $nymUpdate = null;
                                $ppicUpdate = null;
                                $walletUpdate = null;

                                // Only update if the API returned a value and the current session value is null/unset
                                // By only allowing to set user properties when they are not yet set, we prevent unexpected overwrites
                                if (property_exists($character, 'name') && $character->name !== null && null !== $_SESSION['nym']) {
                                    $nymUpdate = $character->name;
                                }

                                if (property_exists($character, 'picture') && $character->picture !== null && null !== $_SESSION['ppic']) {
                                    $ppicUpdate = $character->picture;
                                }

                                if (property_exists($character, 'lud16') && $character->lud16 !== null && null !== $_SESSION['wallet']) {
                                    $walletUpdate = $character->lud16;
                                }

                                // Update the database and session only if necessary
                                if ($nymUpdate !== null || $ppicUpdate !== null || $walletUpdate !== null) {
                                    try {
                                        $stmt = $link->prepare("UPDATE users SET nym = COALESCE(?, nym), ppic = COALESCE(?, ppic), wallet = COALESCE(?, wallet) WHERE usernpub = ?");
                                        $stmt->bind_param('ssss', $nymUpdate, $ppicUpdate, $walletUpdate, $_SESSION["usernpub"]);
                                        $stmt->execute();

                                        if ($nymUpdate !== null) {
                                            $_SESSION['nym'] = $nymUpdate;
                                        }
                                        if ($ppicUpdate !== null) {
                                            $_SESSION['ppic'] = $ppicUpdate;
                                        }
                                        if ($walletUpdate !== null) {
                                            $_SESSION['wallet'] = $walletUpdate;
                                        }
                                    } catch (Exception $e) {
                                        error_log("Error updating user data in database: " . $e->getMessage());
                                    }
                                }
                            } catch (Exception $e) {
                                error_log($e->getMessage());
                            }

                            // Redirect user to account page
                            header("location: /account");
                        } else {
                            // Password is not valid, display a generic error message
                            $login_err = "Invalid usernpub or password.";
                        }
                    }
                } else {
                    // usernpub doesn't exist, display a generic error message
                    $login_err = "Invalid usernpub or password.";
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }

    // Close connection
    mysqli_close($link);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="/styles/index.css?v=2" />
    <link rel="stylesheet" href="/styles/login.css?v=2" />
    <link rel="icon" href="/assets/01.png">
    <title>nostr.build login</title>
</head>

<body>
    <main>

        <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="login_form">
            <h1 class="login_title">Login</h1>
            <p class="login_text">Please fill in your credentials to login</p>
            <input placeholder="Your public key(npub)" type="text" name="usernpub" class="login_key <?= (!empty($usernpub_err)) ? 'is-invalid' : ''; ?>" value="<?= $usernpub; ?>">
            <input placeholder="Password" type="password" name="password" class="login_password <?= (!empty($password_err)) ? 'is-invalid' : ''; ?>">
            <input class="login_button" type="submit" value="Login">
            <p class="sign_up_link">Don’t have an account? <a href="/signup"> Sign up now </a></p>
            <?php if(!empty($login_err)): ?>
              <div class="warning"><div class="warning_text"><h2><?= htmlspecialchars($login_err) ?></h2></div></div>
            <?php endif; ?>
        </form>

        <div class="warning">
            <div class="warning_icon">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path opacity="0.12" d="M10 1.66663L17.5 5.83329V14.1666L10 18.3333L2.5 14.1666V5.83329L10 1.66663Z" fill="url(#paint0_linear_161_738)" />
                    <path d="M10 6.66677V10.0001M10 13.3335H10.0083M2.5 6.61798V13.3822C2.5 13.6678 2.5 13.8105 2.54207 13.9379C2.57929 14.0505 2.64013 14.154 2.72053 14.2411C2.81141 14.3398 2.93621 14.4091 3.18581 14.5478L9.3525 17.9737C9.58883 18.105 9.707 18.1706 9.83208 18.1964C9.94292 18.2192 10.0571 18.2192 10.1679 18.1964C10.293 18.1706 10.4112 18.105 10.6475 17.9737L16.8142 14.5478C17.0638 14.4091 17.1886 14.3398 17.2795 14.2411C17.3598 14.154 17.4207 14.0505 17.4579 13.9379C17.5 13.8105 17.5 13.6678 17.5 13.3822V6.61798C17.5 6.33245 17.5 6.18967 17.4579 6.06235C17.4207 5.9497 17.3598 5.8463 17.2795 5.75906C17.1886 5.66044 17.0638 5.59111 16.8142 5.45244L10.6475 2.02652C10.4112 1.89522 10.293 1.82957 10.1679 1.80382C10.0571 1.78105 9.94292 1.78105 9.83208 1.80382C9.707 1.82957 9.58883 1.89522 9.3525 2.02652L3.18581 5.45244C2.93621 5.59111 2.81141 5.66044 2.72053 5.75906C2.64013 5.8463 2.57929 5.9497 2.54207 6.06235C2.5 6.18967 2.5 6.33245 2.5 6.61798Z" stroke="url(#paint1_linear_161_738)" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
                    <defs>
                        <linearGradient id="paint0_linear_161_738" x1="2.5" y1="1.66663" x2="20.3005" y2="5.57346" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#DABD55" />
                            <stop offset="1" stop-color="#F78533" />
                        </linearGradient>
                        <linearGradient id="paint1_linear_161_738" x1="2.5" y1="1.78674" x2="20.2765" y2="5.74528" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#DABD55" />
                            <stop offset="1" stop-color="#F78533" />
                        </linearGradient>
                    </defs>
                </svg>
            </div>
            <div class="warning_text">
                <h2>Warning</h2>
                <p>Please be careful not to enter your private key(nsec) here, as it could compromise your nostr credentials.</p>
            </div>
            <button class="close">
                <svg width="10" height="10" viewBox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M8.33341 1.66663L1.66675 8.33329M1.66675 1.66663L8.33341 8.33329" stroke="#D0BED8" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>
        </div>
    </main>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/components/footer.php'; ?>
    <script src="/scripts/index.js?v=1"></script>
    <script>
        document.addEventListener('DOMContentLoaded', (event) => {
            let closeButton = document.querySelector(".close");
            if (closeButton) {
                closeButton.addEventListener("click", () => {
                    let warning = document.querySelector(".warning");
                    if (warning) {
                        warning.classList.add("hidden_element");
                    }
                });
            }
        });
    </script>
</body>

</html>
