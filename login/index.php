<?php
// Include config, session, and Permission class files
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Account.class.php';

global $link;

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

    // Moderator flags:
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
    // Validate credentials
    if (empty($usernpub_err) && empty($password_err)) {
        $account = new Account($usernpub, $link);
        if ($account->verifyPassword($password)) {
            $account->updateAccountDataFromNostrApi();
            // Check if enable_nostr_login is set and update the account
            if (
                isset($_POST["enable_nostr_login"]) &&
                $account->isNpubVerified()
            ) {
                $account->allowNpubLogin();
            }
            error_log("User " . $_SESSION["usernpub"] . " logged in successfully." . PHP_EOL);
            error_log("User " . $_SESSION["usernpub"] . " has the following permissions: " . print_r($_SESSION["accflags"], true) . PHP_EOL);
            header("location: /account");
            exit;
        } else {
            $login_err = "Invalid usernpub or password.";
        }
    }
    // Close connection
    $link->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="/styles/index.css?v=6" />
    <link rel="stylesheet" href="/styles/login.css?v=7" />
    <link rel="icon" href="/assets/01.png">
    <title>nostr.build login</title>
    <style>
        /* Custom checkbox style */
        .checkbox-container {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            margin-top: 20px;
            position: relative;
        }

        /* Hides the default checkbox */
        #enable_nostr_login {
            opacity: 0;
            position: absolute;
        }

        #enable_nostr_login+label::before {
            content: '';
            display: inline-block;
            vertical-align: middle;
            width: 20px;
            height: 20px;
            background: #f2f2f2;
            border-radius: 3px;
            border: 2px solid #7c7c7c;
            margin-right: 8px;
            cursor: pointer;
            transition: background 0.3s, border-color 0.3s;
        }

        /* Custom checkbox checkmark */
        #enable_nostr_login:checked+label::before {
            background-color: #8a2be2;
            border-color: #8a2be2;
        }

        /* Checkmark icon */
        #enable_nostr_login:checked+label::after {
            content: '✔';
            position: absolute;
            left: 8px;
            top: 0;
            color: white;
            font-size: 25px;
        }

        #enable_nostr_login+label {
            cursor: pointer;
            color: #a58ead;
            text-shadow: 0px 2px 16px rgba(159, 108, 209, 0.32);
            font-size: 1rem;
            line-height: 25px;
        }

        #enable_nostr_login+label:hover::before {
            border-color: #8a2be2;
        }
    </style>

</head>

<body>
    <main>

        <form action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="login_form">
            <h1 class="login_title">Login</h1>
            <svg id="a" xmlns="http://www.w3.org/2000/svg" width="38px" height="38px" viewBox="39 35 184 184">
                <defs>
                    <style>
                        .cls-1 {
                            fill: #b098bb;
                        }
                    </style>
                </defs>
                <path class="cls-1" d="m210.81,116.2v83.23c0,3.13-2.54,5.67-5.67,5.67h-68.04c-3.13,0-5.67-2.54-5.67-5.67v-15.5c.31-19,2.32-37.2,6.54-45.48,2.53-4.98,6.7-7.69,11.49-9.14,9.05-2.72,24.93-.86,31.67-1.18,0,0,20.36.81,20.36-10.72,0-9.28-9.1-8.55-9.1-8.55-10.03.26-17.67-.42-22.62-2.37-8.29-3.26-8.57-9.24-8.6-11.24-.41-23.1-34.47-25.87-64.48-20.14-32.81,6.24.36,53.27.36,116.05v8.38c-.06,3.08-2.55,5.57-5.65,5.57h-33.69c-3.13,0-5.67-2.54-5.67-5.67V55.49c0-3.13,2.54-5.67,5.67-5.67h31.67c3.13,0,5.67,2.54,5.67,5.67,0,4.65,5.23,7.24,9.01,4.53,11.39-8.16,26.01-12.51,42.37-12.51,36.65,0,64.36,21.36,64.36,68.69Zm-60.84-16.89c0-6.7-5.43-12.13-12.13-12.13s-12.13,5.43-12.13,12.13,5.43,12.13,12.13,12.13,12.13-5.43,12.13-12.13Z" />
            </svg>
            <!-- Signup button at the top -->
            <div class="login_button" style="text-align: center; margin-bottom: 0rem; display: flex; justify-content: center; align-items: center">
                <a href="/plans/" class="login_button" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center; height: 40px;;background: linear-gradient(95.49deg, #ffffff 0%, #2edf95 100%);">Create Account</a>
            </div>
            <hr style="width:100%;margin:0rem 0rem 0rem 0rem">
            <input name="nip07_login" class="nip07_button login_button" type="submit" value="Login with Nostr (NIP-07)">
            <span class="login_text" style="margin:0rem;font-size:2rem">or</span>
            <input autocomplete="username" placeholder="Your public key(npub)" type="text" name="usernpub" class="login_key <?= (!empty($usernpub_err)) ? 'is-invalid' : ''; ?>" value="<?= $usernpub; ?>">
            <input autocomplete="current-password" placeholder="Password" type="password" name="password" class="login_password <?= (!empty($password_err)) ? 'is-invalid' : ''; ?>">
            <input autocomplete="off" placeholder="Verification DM Code" type="password" name="dm_code" class="login_password" style="display: none;">
            <!-- Checkbox container -->
            <div class="checkbox-container" id="checkboxContainer" style="display:none;">
                <input type="checkbox" id="enable_nostr_login" name="enable_nostr_login" disabled>
                <label for="enable_nostr_login" style="margin-left: 6px;">Enable Nostr Login</label>
                <p class="login_text">You must login with password first, to enable Nostr Login.</p>
            </div>
            <input class="login_button" type="submit" value="Login">
            <input class="login_button" type="submit" value="Login via Nostr DM" name="dm_login">
            <?php if (!empty($login_err)) : ?>
                <div class="warning">
                    <div class="warning_text">
                        <h2><?= htmlspecialchars($login_err) ?></h2>
                    </div>
                </div>
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
    <script src="/scripts/dist/login.js?v=12"></script>
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

            // Check if NIP-07 extension is installed and enable NIP-07 login
            let nip07Button = document.querySelector("[name='nip07_login']");
            let enableNostrLoginElement = document.getElementById('checkboxContainer');
            if (nip07Button) {
                nip07Button.addEventListener("click", async (e) => {
                    e.preventDefault();
                    await loginWithNip07(window.location.origin + "/api/v2/account/login", window.location.origin + "/account", nip07Button, enableNostrLoginElement)
                });
            }
            // Check if DM login button is present
            let DMButton = document.querySelector("[name='dm_login']");
            let DMCodeInput = document.querySelector("[name='dm_code']");
            let DMNpubInput = document.querySelector("[name='usernpub']");
            let DMPasswordField = document.querySelector("[name='password']");
            let LoginButton = document.querySelector("[value='Login']");
            if (DMButton) {
                DMButton.addEventListener("click", async (e) => {
                    e.preventDefault();
                    await loginWithDM(window.location.origin + "/api/v2/account/login", DMButton, DMCodeInput, DMNpubInput, DMPasswordField, enableNostrLoginElement, LoginButton)
                });
            }
            // Function to disable input of anything that will start with 'nsec1' into the usernpub field
            let usernpubInput = document.querySelector("[name='usernpub']");
            if (usernpubInput) {
                usernpubInput.addEventListener("input", (e) => {
                    if (e.target.value.length > 4 && (e.target.value.startsWith("nsec1") || !e.target.value.startsWith("npub1"))) {
                        e.target.value = "";
                    }
                });
            }
        });
    </script>
</body>

</html>