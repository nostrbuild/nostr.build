<?php
// TODO: Migrate to use table classes
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
$usernpub = $password = $confirm_password = "";
$usernpub_err = $password_err = $confirm_password_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate usernpub
    if (empty(trim($_POST["usernpub"]))) {
        $usernpub_err = "Please enter a usernpub.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', trim($_POST["usernpub"]))) {
        $usernpub_err = "Your public key can only contain letters, numbers, and underscores.";
    } elseif (substr($_POST["usernpub"], 0, 5)    != 'npub1') {
        $usernpub_err = 'Your public key always begins with "npub1".<BR> Do NOT enter your private key!';
    } else {
        // Prepare a select statement
        $sql = "SELECT id FROM users WHERE usernpub = ?";

        if ($stmt = mysqli_prepare($link, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "s", $param_usernpub);

            // Set parameters
            $param_usernpub = trim($_POST["usernpub"]);

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                /* store result */
                mysqli_stmt_store_result($stmt);

                if (mysqli_stmt_num_rows($stmt) == 1) {
                    $usernpub_err = "This usernpub is already taken.";
                } else {
                    $usernpub = trim($_POST["usernpub"]);
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }

    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have atleast 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Password did not match.";
        }
    }

    // Check input errors before inserting in database
    if (empty($usernpub_err) && empty($password_err) && empty($confirm_password_err)) {

        // Prepare an insert statement
        $sql = "INSERT INTO users (usernpub, password) VALUES (?, ?)";

        if ($stmt = mysqli_prepare($link, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "ss", $param_usernpub, $param_password);

            // Set parameters
            $param_usernpub = $usernpub;
            $param_password = password_hash($password, PASSWORD_DEFAULT); // Creates a password hash

            // Attempt to execute the prepared statement
            if (mysqli_stmt_execute($stmt)) {
                // Redirect to login page
                header("location: /login");
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
    <link rel="stylesheet" href="../styles/index.css" />
    <link rel="stylesheet" href="../styles/login.css" />
    <link rel="icon" href="../assets/01.png" />
    <title>nostr.build</title>
</head>

<body>
    <main>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="login_form">
            <h1 class="login_title">Register</h1>
            <p class="login_text signup_text">
                If you have not yet chosen and paid for your account, <br />
                <a href="https://btcpay.nostr.build/apps/4Nyftv3DHxjJYanPEdyC2ikpNdmk/pos" class="link_to_create">please go here</a>
            </p>

            <input name="usernpub" placeholder="Your public key(npub)" class="login_key" type="text" class="text" class="form-control <?php echo (!empty($usernpub_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $usernpub; ?>" />
            <input name="password" placeholder="Choose your password" type="password" class="login_password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $password; ?>" />
            <input name="confirm_password" placeholder="Confirm password" type="password" class="login_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $confirm_password; ?>" />
            <input type="submit" class="login_button" value="Submit">
            <input type="reset" class="reset_button" value="Reset">

            <p class="sign_up_link">Already have an account? <a href="../login/"> Login here </a></p>
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
                <p>Please be careful to not enter your private key(nsec) here, you could compromise your nostr credentials.</p>
            </div>
            <button class="close">
                <svg width="10" height="10" viewBox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M8.33341 1.66663L1.66675 8.33329M1.66675 1.66663L8.33341 8.33329" stroke="#D0BED8" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </button>
        </div>
    </main>
    <?= include $_SERVER['DOCUMENT_ROOT'] . '/components/footer.php'; ?>
    <script src="../scripts/index.js"></script>
    <script src="../scripts/login.js"></script>
</body>

</html>