<?php
/*
This path will only be used for the new user sign-up, while we will have a separate path for existing users to upgrade their plan.
The new user sign-up will be a 4-step process:
1. Choose a plan
2. Create an account - the account will be set at level 0, and will be unverified.
3. Pay the fee - the user will be shown a BTCPay invoice, and will be redirected to the final step once the invoice is paid.
4. Done - the user will be shown a success message, and will be given the link to the login page.
*/
// Include config, session, and Permission class files

require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/BTCPayClient.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Plans.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Account.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Bech32.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Purchase.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/Promotions.class.php';

// Create new Permission object
$perm = new Permission();

// If the user is logged in and has a verified account, proceed with renew or upgrade path
$account = null;
if (
  $perm->validateLoggedin() &&
  !isset($_SESSION['purchase_npub'])
) {
  // If the user is logged in but has an unverified account, set signup_npub in session
  $_SESSION['purchase_npub'] = $_SESSION['usernpub'];
}

// When purchase_npub is set, be it new user or logged in, we instantiate Account class
if (isset($_SESSION['purchase_npub'])) {
  $account = new Account($_SESSION['purchase_npub'], $link);
}

// If we are working with logged in users, set their current level and get remaining days of the subscription
$remainingDays = null;
$currentAccountLevel = null;
$existingSubscriptionPeriod = null;
if ($account !== null && $account->isAccountValid() && !$perm->isGuest()) {
  $remainingDays = $account->getRemainingSubscriptionDays();
  $existingSubscriptionPeriod = $account->getSubscriptionPeriod();
  $currentAccountLevel = $account->getAccountLevelInt();
}

if (isset($_GET['reset'])) {
  session_destroy();
  header('Location: /plans/');
  exit;
}

// Capture referral code if it exists
if (
  isset($_GET['ref']) &&
  !isset($_SESSION['purchase_ref']) &&
  $_GET['ref'] !== $_SESSION['purchase_ref']
) {
  // Get the referral npub from the referral code
  $referralNpub = findNpubByReferralCode($link, $_GET['ref']);
  // Check if referrl account has valid level
  if (!empty($referralNpub)) {
    $referrerAccount = new Account($referralNpub, $link);
    $_SESSION['purchase_ref_npub'] = $referralNpub;
    $_SESSION['purchase_ref'] = $_GET['ref'];
    // Get the account pfp link and nym from the referral npub
    $_SESSION['purchase_ref_pfp'] = $referrerAccount->getAccount()['ppic'];
    $_SESSION['purchase_ref_nym'] = $referrerAccount->getAccount()['nym'];


    // Redirect to a clean URL
    header('Location: /plans/');
    exit;
  }
}

// Set the purchase period from _GET or _SESSION
if (isset($_GET['period']) && in_array($_GET['period'], ['1y', '2y', '3y'])) { // Always validate user input
  $_SESSION['purchase_period'] = $_GET['period'];
  // Store price multiplier based on the selected period
} elseif (!isset($_SESSION['purchase_period'])) {
  $_SESSION['purchase_period'] = '1y';
}

// Setup environment
global $link; // MySQL connection
global $btcpayConfig; // BTCPay configuration
// Get all applicable promotions
$promotionsTable = new Promotions($link); // Applicable to upgrades and new signups
$perPlanPromotions = [];
$globalPromotions = [];
try {
  $promotions = $promotionsTable->getAllCurrentPromotions();
  $perPlanPromotions = $promotions['perPlan'];
  $globalPromotions = !empty($promotions['global']) ? $promotions['global'][0] : [];
} catch (Exception $e) {
  error_log($e->getMessage());
  // Do nothing
}

// Create new Purchase instance
$purchase = new Purchase(
  new BTCPayClient(
    $btcpayConfig['apiKey'],
    $btcpayConfig['host'],
    $btcpayConfig['storeId'],
  ),
  Plans::getInstance(
    promotions: $perPlanPromotions,
    globalPromotionDiscount: $globalPromotions,
    remainingDays: $remainingDays,
    currentPlanLevel: $currentAccountLevel,
    period: $_SESSION['purchase_period'],
    currentPeriod: $existingSubscriptionPeriod ?? '1y'
  )
);

// Get selected plan
$selectedPlan = $purchase->getSelectedPlanId();
// Get current step
$step = $purchase->getValidatedStep();
// Get all steps
$steps = $purchase->getSteps();

// Processing form data when form is submitted to create an account
// Not applicable to renew and upgrade
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  // Define variables and initialize with empty values
  $usernpub = $password = $confirm_password = "";
  $account_create_error = "";
  $bech32 = new Bech32();

  // Validate usernpub
  $usernpub = trim($_POST["usernpub"]);
  if (empty($_SESSION['signup_npub_verified'])) {
    $account_create_error = "Please verify your npub1 public key.";
  } elseif (
    !empty($usernpub) &&
    !empty($_SESSION['signup_npub_verified']) &&
    $_SESSION['signup_npub_verified'] != $usernpub
  ) {
    $account_create_error = "The npub1 public key you entered does not match the one you verified. Please enter the correct public key.";
  } elseif (empty($usernpub)) {
    $account_create_error = "Please enter a usernpub.";
  } elseif (!$bech32->isValidNpub1Address($usernpub)) {
    $account_create_error = 'Invalid npub1 public key. Please enter a valid public key that begins with "npub1". Do NOT enter your private key!';
  }

  if (empty($account_create_error)) {
    // Validate password
    $password = trim($_POST["password"]);
    if (empty($password)) {
      $account_create_error = "Please enter a password.";
    } elseif (strlen($password) < 6) {
      $account_create_error = "Password must have at least 6 characters.";
    }
  }

  if (empty($account_create_error)) {
    // Validate confirm password
    $confirm_password = trim($_POST["confirm_password"]);
    if (empty($confirm_password)) {
      $account_create_error = "Please confirm password.";
    } elseif ($password != $confirm_password) {
      $account_create_error = "Password did not match.";
    }
  }

  // Check input errors before inserting in database
  error_log("Creating account for $usernpub");
  if (empty($account_create_error)) {
    try {
      $account = new Account($usernpub, $link);
      if ($account->accountExists()) {
        $account_create_error = 'This npub1 public key is already in use. Please enter a different public key.';
      } else {
        $npubVerifiedFlag = $_SESSION['signup_npub_verified'] === $usernpub ? 1 : 0;
        $enableNostrLoginFlag = 1; // Always enable Nostr login for new signups
        error_log("Creating account for $usernpub: $npubVerifiedFlag, $enableNostrLoginFlag");
        if (!$npubVerifiedFlag) {
          $account_create_error = "The npub1 public key you entered does not match the one you verified. Please enter the correct public key.";
        } else {
          $account->createAccount($password, 0 /* level, default to 0 */, $npubVerifiedFlag, $enableNostrLoginFlag);
        }
      }
    } catch (DuplicateUserException $e) {
      $account_create_error = 'This npub1 public key is already in use. Please enter a different public key.';
    } catch (InvalidAccountLevelException $e) {
      $account_create_error = "The specified account level is invalid.";
    } catch (Exception $e) {
      $account_create_error = "An error occurred: " . $e->getMessage();
    }
  }

  // Validate if we accumulated any errors
  if (empty($account_create_error)) {
    // Unverified account created, proceed to the payment step
    $step = 3;
    $_SESSION['purchase_step'] = $step;
    $_SESSION['purchase_npub'] = $usernpub;
  } else {
    // Encountered an error, display it in the form and stay on the same step
    error_log($account_create_error);
    error_log("Failed to create an account for $usernpub");
    $step = 2;
    $_SESSION['purchase_step'] = $step;
  }
}

$svg_logo = <<<SVG
<svg width="125" height="29" fill="none" xmlns="http://www.w3.org/2000/svg" xmlns:v="https://vecta.io/nano"><linearGradient id="A" gradientUnits="userSpaceOnUse" x1="24.652" y1="13.466" x2="126.436" y2="27.951"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M28.7 10.39c.83-.99 1.85-1.5 3.07-1.51.66-.01 1.24.1 1.75.33.51.22.91.52 1.22.91.38.45.63.98.78 1.59a8.71 8.71 0 0 1 .21 2v5.6h-2.76v-5.39c0-.36-.04-.7-.12-1.03-.05-.31-.17-.6-.35-.85a1.51 1.51 0 0 0-.68-.54 2.29 2.29 0 0 0-.93-.17c-.44.01-.82.11-1.13.29a1.83 1.83 0 0 0-.68.7c-.16.29-.27.6-.33.93-.05.34-.08.68-.08 1.03v5.04h-2.74V9.1h2.62l.15 1.29z" fill="url(#A)"/><linearGradient id="B" gradientUnits="userSpaceOnUse" x1="24.908" y1="11.661" x2="126.716" y2="26.15"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M42.59 19.46c-1.66 0-2.93-.47-3.83-1.4-.89-.94-1.34-2.25-1.34-3.91 0-.8.12-1.52.35-2.17.25-.66.58-1.22 1.01-1.69.45-.47 1-.83 1.61-1.05.65-.25 1.38-.37 2.19-.37.8 0 1.52.12 2.16.37a4.03 4.03 0 0 1 1.63 1.05c.45.47.8 1.03 1.03 1.69.25.65.37 1.37.37 2.17 0 1.68-.45 2.98-1.36 3.91-.88.94-2.16 1.4-3.82 1.4h0zm0-8.13c-.84 0-1.46.27-1.87.81-.4.54-.6 1.22-.6 2.03 0 .83.2 1.51.6 2.05s1.02.81 1.86.81 1.46-.27 1.85-.81.58-1.23.58-2.05c0-.81-.19-1.49-.58-2.03-.38-.54-1-.81-1.84-.81h0z" fill="url(#B)"/><linearGradient id="C" gradientUnits="userSpaceOnUse" x1="25.128" y1="10.147" x2="126.923" y2="24.633"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M53.31 11.07c-.19 0-.38.01-.56.04a1.59 1.59 0 0 0-.47.14c-.14.05-.26.15-.35.27s-.13.27-.12.47c0 .2.1.39.25.52.17.13.4.23.7.31l.95.23 1.03.21c.34.07.67.17.99.27s.61.23.85.39c.34.21.62.5.84.87s.32.83.31 1.38c0 .53-.09.99-.27 1.38-.16.37-.4.7-.7.97-.38.34-.85.58-1.42.74a7.25 7.25 0 0 1-1.73.21c-.67 0-1.29-.07-1.86-.21a4 4 0 0 1-1.53-.76c-.34-.28-.63-.61-.85-.99-.22-.4-.35-.88-.39-1.43h2.76c.08.44.29.74.64.91.36.17.79.25 1.28.25l.45-.02c.15-.02.3-.06.45-.12a.94.94 0 0 0 .35-.27c.1-.11.15-.26.16-.41.01-.34-.1-.57-.35-.72a2.26 2.26 0 0 0-.76-.29l-.87-.19-.93-.19c-.3-.06-.6-.14-.89-.23a3.7 3.7 0 0 1-.82-.41c-.38-.24-.71-.56-.95-.93-.25-.39-.35-.9-.31-1.53.03-.56.16-1.03.41-1.41.26-.4.58-.72.95-.95.4-.25.84-.43 1.3-.52.49-.1.99-.16 1.5-.16.56 0 1.08.06 1.57.17a3.5 3.5 0 0 1 1.28.56c.38.26.67.59.89 1.01.23.41.36.92.39 1.51h-2.64c-.05-.43-.22-.71-.51-.85-.29-.15-.62-.22-1.02-.22z" fill="url(#C)"/><linearGradient id="D" gradientUnits="userSpaceOnUse" x1="25.513" y1="7.51" x2="127.285" y2="21.993"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M62.71 11.48v4.13c0 .88.45 1.32 1.34 1.32h1.01v2.38h-1.28c-1.36.05-2.34-.21-2.93-.78-.58-.57-.87-1.43-.87-2.58v-4.48h-1.55V9.1h1.55V6.33h2.74V9.1h2.45v2.38h-2.46z" fill="url(#D)"/><linearGradient id="E" gradientUnits="userSpaceOnUse" x1="25.461" y1="7.812" x2="127.264" y2="22.3"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M69.31 10.38c.41-.49.85-.86 1.32-1.1.48-.25 1.05-.37 1.71-.37l.49.02a2.62 2.62 0 0 1 .43.06v2.52l-.91.02c-.3 0-.58.03-.85.08s-.54.14-.8.25c-.25.1-.47.26-.66.47-.3.34-.49.7-.56 1.09-.06.39-.1.83-.1 1.32v4.59h-2.74V9.1h2.53l.14 1.28h0z" fill="url(#E)"/><linearGradient id="F" gradientUnits="userSpaceOnUse" x1="25.076" y1="10.675" x2="126.884" y2="25.164"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M76.3 16.28c.49 0 .89.15 1.2.45s.47.72.47 1.26-.16.96-.47 1.24-.71.43-1.2.43-.9-.14-1.22-.43c-.31-.28-.47-.7-.47-1.24s.16-.96.47-1.26c.32-.3.73-.45 1.22-.45z" fill="url(#F)"/><linearGradient id="G" gradientUnits="userSpaceOnUse" x1="25.938" y1="4.541" x2="127.724" y2="19.027"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M82.87 10.39c.41-.49.93-.86 1.51-1.1.58-.25 1.2-.39 1.83-.41a4.79 4.79 0 0 1 2.02.39c.62.26 1.14.7 1.55 1.32.34.45.58.99.74 1.61.16.63.24 1.27.23 1.92 0 .84-.1 1.61-.31 2.31-.18.66-.53 1.27-1.01 1.76-.41.43-.9.76-1.46.97-.54.2-1.11.31-1.69.31a5.32 5.32 0 0 1-1.86-.33c-.57-.23-1.09-.63-1.55-1.18v1.36h-2.74V5.59h2.74v4.8h0zm0 3.84c.01.83.25 1.5.7 2.02.47.52 1.11.78 1.92.79.44.01.82-.05 1.13-.19s.56-.34.76-.6a2.91 2.91 0 0 0 .47-.93c.12-.35.17-.73.17-1.14a3.65 3.65 0 0 0-.17-1.12 2.52 2.52 0 0 0-.49-.91c-.21-.26-.47-.46-.78-.6s-.69-.21-1.13-.21c-.41 0-.79.08-1.13.23a2.46 2.46 0 0 0-.82.64c-.21.26-.37.57-.49.93a4.4 4.4 0 0 0-.14 1.09z" fill="url(#G)"/><linearGradient id="H" gradientUnits="userSpaceOnUse" x1="26.035" y1="3.852" x2="127.827" y2="18.338"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M97.22 17.04c.75 0 1.28-.19 1.59-.58.31-.4.45-.97.43-1.71V9.1h2.74v5.74c0 .71-.07 1.32-.21 1.82-.13.5-.36.97-.68 1.38-.25.31-.52.55-.82.72a5.58 5.58 0 0 1-.93.43 5.42 5.42 0 0 1-1.05.21c-.35.05-.71.08-1.07.08-.8 0-1.53-.11-2.19-.33-.64-.21-1.21-.59-1.65-1.1a3.54 3.54 0 0 1-.7-1.38c-.13-.5-.19-1.11-.19-1.82V9.1h2.74v5.66c-.01.74.14 1.3.47 1.69.31.38.82.58 1.52.59h0z" fill="url(#H)"/><linearGradient id="I" gradientUnits="userSpaceOnUse" x1="26.546" y1=".66" x2="128.268" y2="15.136"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M105.63 4.35c.51 0 .91.15 1.22.45s.47.69.47 1.18c0 .52-.16.92-.47 1.22-.31.28-.72.43-1.22.43-.49 0-.9-.14-1.22-.43-.31-.3-.47-.7-.47-1.22 0-.49.16-.88.47-1.18.32-.3.72-.45 1.22-.45zm1.38 14.96h-2.74V9.1h2.74v10.21z" fill="url(#I)"/><linearGradient id="J" gradientUnits="userSpaceOnUse" x1="26.582" y1=".479" x2="128.298" y2="14.954"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M112.07,19.31h-2.74V5.59h2.74V19.31z" fill="url(#J)"/><linearGradient id="K" gradientUnits="userSpaceOnUse" x1="26.715" y1="-.929" x2="128.511" y2="13.558"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M121.76 18.17c-.39.42-.87.75-1.4.97-.51.19-1.12.3-1.85.33-.67 0-1.29-.1-1.86-.31a4.05 4.05 0 0 1-1.48-.97c-.49-.51-.85-1.13-1.05-1.8a7.81 7.81 0 0 1-.29-2.09c0-1.67.47-3 1.4-4.01.41-.44.93-.78 1.55-1.03s1.33-.37 2.12-.37c.6 0 1.13.09 1.59.27.48.17.88.42 1.2.76V5.59h2.76v13.72h-2.54l-.15-1.14zm-2.6-1.13c.83-.03 1.46-.3 1.88-.83.44-.54.66-1.21.66-2 0-.85-.22-1.54-.66-2.07s-1.08-.8-1.92-.81c-.43 0-.8.07-1.13.21-.31.14-.58.35-.8.62a2.71 2.71 0 0 0-.47.91c-.11.36-.16.73-.16 1.1 0 .43.05.81.16 1.16.1.34.26.63.47.89.22.26.49.46.82.6.34.15.72.22 1.15.22z" fill="url(#K)"/><path d="M12.09.18L.25 7.08l11.84 6.9 11.84-6.9L12.09.18z" fill="#6f4099"/><path d="M12.07 1.32L8.8 3.23v7.68l3.29 1.92 3.29-1.92V7.08l3.29-1.92c-.01.01-6.6-3.84-6.6-3.84zm-2.04 4.54a.69.69 0 1 1 0-1.38.69.69 0 1 1 0 1.38zm4.79.9l-2.76-1.58 2.76-1.61 2.74 1.6-2.74 1.59h0z" fill="#fff"/><linearGradient id="L" gradientUnits="userSpaceOnUse" x1="6.057" y1="7.274" x2="6.057" y2="27.97"><stop offset="0" stop-color="#9a4198"/><stop offset="1" stop-color="#3c1e56"/><stop offset="1" stop-color="#231815"/></linearGradient><path d="M.14 7.27v13.8l11.84 6.9v-13.8L.14 7.27z" fill="url(#L)"/><path d="M10.44 25.29l-2.19-1.28v-.64l-4.39-7.66-.54-.32v5.74l-1.65-.95V9.96l2.19 1.27v.64l4.39 7.67.55.32v-5.75l1.64.96z" fill="#fff"/><path d="M12.19 14.17v13.8l11.84-6.9V7.27l-11.84 6.9h0z" fill="#3c1e56"/><path d="M22.5 11.87l-1.64-.96-7.12 4.15v10.22l7.67-4.47 1.1-1.92v-2.55l-1.1-.64 1.1-1.92-.01-1.91h0zm-1.65 7.03l-.82 1.44-4.66 2.71v-3.19l4.66-2.71.82.48v1.27h0zm0-4.47l-.82 1.44-4.66 2.71v-3.19l4.66-2.71.82.48v1.27h0z" fill="#fff"/></svg>
SVG;
?>

<!DOCTYPE html>
<html class="bg-gradient-to-b from-[#292556] to-[#120a24] antialiased" lang="en">

<head>
  <meta charSet="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Nostr.build account signup</title>
  <link rel="stylesheet" href="/styles/twbuild.css?v=b18e00cc8f719fc192928a72156ac258" />
  <link rel="stylesheet" href="/styles/index.css?v=16013407201d48c976a65d9ea88a77a3" />
  <link rel="stylesheet" href="/styles/signup.css?v=8878cbf7163f77b3a4fb9b30804c73ca" />
  <link rel="icon" href="https://cdn.nostr.build/assets/primo_nostr.png" />
  <script defer src="/scripts/fw/alpinejs.min.js?v=34fbe266eb872c1a396b8bf9022b7105"></script>
  <style>
    [x-cloak] {
      display: none !important;
    }
  </style>
</head>

<body class="min-h-screen bg-gradient-to-tr from-[#292556] to-[#120a24]">
  <header>
    <div class="flex items-center justify-center my-4 mx-4">
      <a href="/" class="flex items">
        <span class="sr-only">Nostr.build</span>
        <?= $svg_logo ?>
      </a>
    </div>
    <div class="information pb-5">
      <ul>
        <li>
          <span class="whitespace-pre-line text-pretty text-base sm:text-lg">YakiHonne/Damus/Amethyst/Snort/Coracle/noStrudel
            and Blossom protocol Integrations
            Bitcoin Only | Never Ads | Billed Annually
            Media stays online 1 year after account expiration
          </span>
        </li>
      </ul>
    </div>
  </header>
  <main>
    <?php if (count(Plans::$PLANS) > 0) : ?>
      <nav hx-boost="false" class="flex items-center justify-center" aria-label="Progress">
        <p class="text-sm font-medium text-gray-300">Step <?= $step ?> of <?= count($steps) ?></p>
        <ol role="list" class="ml-8 flex items-center space-x-5">
          <?php foreach ($steps as $index => $value) : ?>
            <li>
              <?php if ($step - 1 > $index) : ?>
                <!-- Completed Step -->
                <a href="?step=<?= $value[1] ?>" title="<?= $value[0] ?>" class="block h-2.5 w-2.5 rounded-full bg-purple-600 hover:bg-purple-900">
                  <span class="sr-only"><?= $value[0] ?></span>
                </a>
              <?php elseif ($step - 1 == $index) : ?>
                <!-- Current Step -->
                <a href="?step=<?= $value[1] ?>" title="<?= $value[0] ?>" class="relative flex items-center justify-center" aria-current="step">
                  <span class="absolute flex h-5 w-5 p-px" aria-hidden="true">
                    <span class="h-full w-full rounded-full bg-purple-200"></span>
                  </span>
                  <span class="relative block h-2.5 w-2.5 rounded-full bg-purple-600" aria-hidden="true"></span>
                  <span class="sr-only"><?= $value[0] ?></span>
                </a>
              <?php else : ?>
                <!-- Upcoming Step -->
                <a href="?step=<?= $value[1] ?>" title="<?= $value[0] ?>" class="block h-2.5 w-2.5 rounded-full bg-gray-200 hover:bg-gray-400">
                  <span class="sr-only"><?= $value[0] ?></span>
                </a>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      </nav>
    <?php endif; ?>
    <?php switch ($step):
      default:
      case 1: ?>
        <!-- Plans section -->
        <?php if (count(Plans::$PLANS) > 0) : ?>
          <div x-data="{ period: '<?= $_SESSION['purchase_period'] ?>' }" class="pb-5 sm:pb-10">
            <div class="flex flex-col items-center">
              <div class="mt-8 flex justify-center">
                <fieldset class="grid grid-cols-3 gap-x-1 rounded-full bg-white/5 p-1 text-center text-xs font-semibold leading-5 text-purple-100">
                  <legend class="sr-only">Payment period</legend>
                  <label :class="{ 'bg-purple-500': period === '1y' }" class="cursor-pointer rounded-full px-2.5 py-1" @click="multiplier = 1">
                    <input type="radio" name="period" value="1y" class="sr-only" x-model="period">
                    <span>1 year</span>
                  </label>
                  <label :class="{ 'bg-purple-500': period === '2y' }" class="cursor-pointer rounded-full px-2.5 py-1" @click="multiplier = 1.8">
                    <input type="radio" name="period" value="2y" class="sr-only" x-model="period">
                    <span>2 years (-<?= Plans::$twoYearDiscount * 100 ?>%)</span>
                  </label>
                  <label :class="{ 'bg-purple-500': period === '3y' }" class="cursor-pointer rounded-full px-2.5 py-1" @click="multiplier = 2.4">
                    <input type="radio" name="period" value="3y" class="sr-only" x-model="period">
                    <span>3 years (-<?= Plans::$threeYearDiscount * 100 ?>%)</span>
                  </label>
                </fieldset>
              </div>
              <!-- Disclaimer text below the fieldset -->
              <p class="mt-2 text-xs text-purple-300" x-cloak x-show="period === '2y' || period === '3y'">
                *Discounts apply to the additional time only.
              </p>
              <p class="mt-2 text-xs text-purple-300" x-cloak x-show="period === '1y'">
                ❤️ Support nostr.build ❤️
              </p>
              <!-- Referral information, if provided -->
              <?php if ($currentAccountLevel === null && !empty($_SESSION['purchase_ref_npub'])) : ?>
                <p class="mt-2 text-lg text-purple-300">
                  Referred by :
                </p>
                <div class="flex justify-center group">
                  <div class="flex items-center">
                    <div>
                      <img class="inline-block h-9 w-9 rounded-full" src="<?= !empty($_SESSION['purchase_ref_pfp']) ? htmlentities($_SESSION['purchase_ref_pfp']) : '/signup/logo/nblogo@0.1x.png' ?>" alt="Referrer Picture">
                    </div>
                    <div class="ml-3">
                      <p class="text-sm font-medium text-gray-300 group-hover:text-gray-100"><?= !empty($_SESSION['purchase_ref_nym']) ? htmlspecialchars($_SESSION['purchase_ref_nym']) : 'Anon' ?></p>
                      <p class="text-xs font-medium text-gray-500 group-hover:text-gray-300"><?= !empty($_SESSION['purchase_ref_npub']) ? substr(htmlspecialchars($_SESSION['purchase_ref_npub']), 0, 16) . '...' : 'npub1...' ?></p>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
          <div class="mx-auto mt-8 max-w-7xl px-6 lg:px-8">
            <div class="isolate mx-auto <?= count(Plans::$PLANS) > 0 ? 'mt-8' : '' ?> grid max-w-md grid-cols-1 gap-8 
                <?= count(Plans::$PLANS) === 0 ? 'md:grid-cols-1 lg:grid-cols-1 xl:grid-cols-1' : '' ?>
                <?= count(Plans::$PLANS) === 1 ? 'md:grid-cols-1 lg:grid-cols-1 xl:grid-cols-1' : '' ?>
                <?= count(Plans::$PLANS) === 2 ? 'md:grid-cols-2 lg:grid-cols-2 xl:grid-cols-2' : '' ?>
                <?= count(Plans::$PLANS) === 3 ? 'md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3' : '' ?>
                <?= count(Plans::$PLANS) === 4 ? 'md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4' : '' ?>
                <?= count(Plans::$PLANS) >= 5 ? 'md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5' : '' ?>
                md:max-w-2xl lg:max-w-4xl xl:mx-0 xl:max-w-none">
              <!-- Plans -->
              <?php foreach (Plans::$PLANS as $plan) : ?>
                <div class="relative rounded-3xl p-8 ring-1 ring-gray-200 <?= $plan->id == $selectedPlan ? 'ring-2 ring-purple-600' : '' ?>">
                  <h3 id="tier-<?= $plan->id ?>" class="text-center text-lg font-semibold leading-8 <?= $plan->id == $selectedPlan ? 'text-purple-300' : 'text-gray-100' ?>" x-text="(period === '1y' ? '<?= $plan->name ?> (1 year)' : (period === '2y' ? '<?= $plan->name ?> (2 years)' : '<?= $plan->name ?> (3 years)'))"></h3>
                  <!-- "Popular" Badge -->
                  <?php if ($plan->id === 1) : ?>
                    <div class="absolute top-0 right-0 transform -translate-y-1/2 -translate-x-1/3">
                      <p class="rounded-full bg-purple-700 text-purple-100 px-2.5 py-1 text-xs font-semibold leading-5">Popular</p>
                    </div>
                  <?php endif; ?>
                  <?php if ($plan->id === 10) : ?>
                    <div class="absolute top-0 right-0 transform -translate-y-1/2 -translate-x-1/3">
                      <p class="rounded-full bg-amber-700 text-purple-100 px-2.5 py-1 text-xs font-semibold leading-5">Exclusive</p>
                    </div>
                  <?php endif; ?>
                  <!-- Current Plan Badge -->
                  <?php if ($plan->isCurrentPlan) : ?>
                    <div class="absolute top-0 left-0 transform -translate-y-1/2 translate-x-1/3">
                      <p class="rounded-full bg-purple-300 text-purple-800 px-2.5 py-1 text-xs font-semibold leading-5">Your plan</p>
                    </div>
                  <?php endif; ?>
                  <p class="mt-6 flex items-baseline gap-x-1 relative md:justify-start justify-center">
                    <!-- Display Monthly Price -->
                    <?php if ($plan->isCurrentPlan) : ?>
                      <span class="text-4xl font-bold tracking-tight text-purple-200"><?= $plan->getCurrencySymbol() ?></span>
                      <span class="text-4xl font-bold tracking-tight text-purple-200" x-text="(period === '1y' ? '<?= $plan->getMonthlyPrice('1y') ?>' : (period === '2y' ? '<?= $plan->getMonthlyPrice('2y') ?>' : '<?= $plan->getMonthlyPrice('3y') ?>'))"></span>
                      <span class="text-sm font-semibold leading-6 text-purple-300">/month</span>
                    <?php else : ?>
                      <span class="text-4xl font-bold tracking-tight text-purple-200" x-text="(period === '1y' ? '<?= $plan->price != -1 ? $plan->getCurrencySymbol() : '' ?>' : (period === '2y' ? '<?= $plan->price2y != -1 ? $plan->getCurrencySymbol() : '' ?>' : '<?= $plan->price3y != -1 ? $plan->getCurrencySymbol() : '' ?>'))"><?= $plan->getCurrencySymbol() ?></span>
                      <span class="text-4xl font-bold tracking-tight text-purple-200" x-text="(period === '1y' ? '<?= $plan->getMonthlyPrice('1y') ?>' : (period === '2y' ? '<?= $plan->getMonthlyPrice('2y') ?>' : '<?= $plan->getMonthlyPrice('3y') ?>'))"></span>
                      <span class="text-sm font-semibold leading-6 text-purple-300" x-text="(period === '1y' ? '<?= $plan->price != -1 ? '/month' : '' ?>' : (period === '2y' ? '<?= $plan->price2y != -1 ? '/month' : '' ?>' : '<?= $plan->price3y != -1 ? '/month' : '' ?>'))">/month</span>
                    <?php endif; ?>
                    <?php if ($plan->promo) : ?>
                      <span class="absolute top-0 right-0 bg-red-500 text-white text-xs font-bold py-1 px-2 rounded-full transform -translate-y-1/3">
                        <?= $plan->discountPercentage ?>% off
                      </span>
                    <?php endif; ?>
                  </p>
                  <!-- Payment Terms with Total -->
                  <p class="mt-2 text-sm text-gray-400 text-center md:text-left" x-text="(period === '1y' ? '<?= $plan->getPaymentTermsWithTotal('1y') ?>' : (period === '2y' ? '<?= $plan->getPaymentTermsWithTotal('2y') ?>' : '<?= $plan->getPaymentTermsWithTotal('3y') ?>'))">
                    <?= $plan->getPaymentTermsWithTotal('1y') ?>
                  </p>
                  <?php if ($plan->isCurrentPlan && !$plan->isRenewable) : ?>
                    <span aria-describedby="tier-<?= $plan->id ?>" class="mt-6 block rounded-md py-2 px-3 text-center text-sm font-semibold leading-6 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 text-purple-300 ring-1 ring-inset ring-purple-200 hover:ring-purple-300">Your current plan</span>
                  <?php else : ?>
                    <?php /*
                    <a :href="'?step=2&plan=<?= $plan->id ?>&period=' + period" aria-describedby="tier-<?= $plan->id ?>" class="mt-6 block rounded-md py-2 px-3 text-center text-sm font-semibold leading-6 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 <?= $plan->id == $selectedPlan ? 'bg-purple-600 text-white shadow-sm hover:bg-purple-500' : 'text-purple-300 ring-1 ring-inset ring-purple-200 hover:ring-purple-300' ?>"><?= $plan->isCurrentPlan && $plan->isRenewable ? 'Renew' : ($currentAccountLevel === null ? 'Buy' : 'Upgrade to') ?> <?= htmlentities($plan->name) ?> plan</a>
                    */ ?>
                    <a :href="((<?= $plan->price ?> != -1 && period === '1y') || (<?= $plan->price2y ?> != -1 && period === '2y') || (<?= $plan->price3y ?> != -1 && period === '3y')) ? '?step=2&plan=<?= $plan->id ?>&period=' + period : null" aria-describedby="tier-<?= $plan->id ?>" class="mt-6 block rounded-md py-2 px-3 text-center text-sm font-semibold leading-6 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600 <?= $plan->id == $selectedPlan ? 'bg-purple-600 text-white shadow-sm hover:bg-purple-500' : 'text-purple-300 ring-1 ring-inset ring-purple-200 hover:ring-purple-300' ?>" :class="{ 'pointer-events-none opacity-50': ((<?= $plan->price ?> == -1 && period === '1y') || (<?= $plan->price2y ?> == -1 && period === '2y') || (<?= $plan->price3y ?> == -1 && period === '3y')) }">
                      <?= $plan->isCurrentPlan && $plan->isRenewable ? 'Renew' : ($currentAccountLevel === null ? 'Buy' : 'Upgrade to') ?> <?= htmlentities($plan->name) ?> plan
                    </a>
                  <?php endif; ?>
                  <ul role="list" class="mt-8 space-y-3 text-sm leading-6 text-gray-300">
                    <!-- Bonus Credits -->
                    <?php if ($currentAccountLevel === null && $plan->bonusCredits > 0) : ?>
                      <li class="flex gap-x-3">
                        <svg class="h-6 w-5 flex-none text-purple-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                          <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                        </svg>
                        <span class="text-blue-300 text-lg" x-text="(period === '1y' ? '<?= $plan->bonusCredits ?>' : (period === '2y' ? '<?= $plan->bonusCredits2y ?>' : '<?= $plan->bonusCredits3y ?>'))"></span>
                        <?php if (!empty($_SESSION['purchase_ref'])): ?>
                          <span class="text-blue-300 font-extrabold text-xs caption-top animate-[pulse_3s_ease-in-out_infinite]" x-text="'(+' + (period === '1y' ? '<?= intval($plan->bonusCredits * 0.05) ?>' : (period === '2y' ? '<?= intval($plan->bonusCredits2y * 0.05) ?>' : '<?= intval($plan->bonusCredits3y * 0.05) ?>')) + ' referral)'"></span>
                        <?php endif; ?>
                        bonus credits**
                      </li>
                    <?php endif; ?>
                    <!-- /Bonus Credits -->
                    <?php foreach ($plan->features as $feature) : ?>
                      <li class="flex gap-x-3">
                        <svg class="h-6 w-5 flex-none text-purple-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                          <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                        </svg>
                        <?= $feature ?>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endforeach; ?>
              <?php if (count(Plans::$PLANS) === 0) : ?>
                <p class="m-6 flex items-baseline gap-x-1 relative">
                  <span class="text-1xl md:text-2xl lg:text-3xl xl:text-4xl font-bold tracking-tight text-purple-200">There are no renewal or upgrade plans available.</span>
                </p>
              <?php endif; ?>
              <!-- /Plans -->
            </div>
          </div>
          <?php if (count(Plans::$PLANS) > 0) : ?>
            <div class="mt-2 text-pretty text-right text-sm font-semibold text-purple-200">
              <p>* Coming soon</p>
              <p>** Used by AI and other features</p>
            </div>
          <?php endif; ?>
          </div>

          <div class="rounded-md bg-nbpurple-800 p-4 mb-2">
            <div class="text-sm text-nbpurple-100 text-center">
              <p>- Account downgrade available on request (we cannot downgrade accounts to Purist tier)</p>
              <p>- 21 day money-back guarantee</p>
              <p> Contact Us - <a href="mailto:admin@nostr.build">admin@nostr.build</a></p>
            </div>
          </div>

          </div>
          <div class="powered">
            Powered by
            <svg width="42" height="19" viewBox="0 0 42 19" fill="none" xmlns="http://www.w3.org/2000/svg">
              <g clip-path="url(#clip0_161_700)">
                <path d="M1.13919 18.2509C0.999576 18.2509 0.861337 18.2234 0.732364 18.1699C0.603391 18.1165 0.486209 18.0381 0.387509 17.9394C0.288809 17.8407 0.210524 17.7234 0.157123 17.5944C0.103723 17.4654 0.0762525 17.3272 0.0762813 17.1876V1.18799C0.0742603 1.04711 0.100263 0.907236 0.152777 0.776494C0.205292 0.645752 0.283271 0.526752 0.382182 0.426412C0.481092 0.326073 0.598961 0.246395 0.728937 0.19201C0.858913 0.137625 0.998402 0.109619 1.1393 0.109619C1.28019 0.109619 1.41968 0.137625 1.54966 0.19201C1.67963 0.246395 1.7975 0.326073 1.89641 0.426412C1.99532 0.526752 2.0733 0.645752 2.12582 0.776494C2.17833 0.907236 2.20433 1.04711 2.20231 1.18799V17.1876C2.20231 17.4696 2.09031 17.74 1.89094 17.9394C1.69158 18.1388 1.42117 18.2509 1.13919 18.2509Z" fill="#D0BED8" />
                <path d="M1.13938 18.2509C0.898013 18.2508 0.663852 18.1687 0.475369 18.0179C0.286886 17.8671 0.155293 17.6567 0.102211 17.4213C0.0491297 17.1858 0.0777162 16.9393 0.183274 16.7222C0.288832 16.5052 0.465083 16.3305 0.683068 16.2268L7.09397 13.1849L0.508068 8.3328C0.286078 8.16369 0.139464 7.91409 0.0998577 7.63785C0.0602513 7.36161 0.130827 7.08087 0.296359 6.85619C0.461891 6.63152 0.70911 6.48093 0.984682 6.4369C1.26025 6.39288 1.54209 6.45895 1.76938 6.62086L9.78591 12.5269C9.93382 12.6361 10.0509 12.7817 10.1258 12.9496C10.2007 13.1175 10.2308 13.3019 10.2132 13.4849C10.1956 13.6679 10.1309 13.8432 10.0254 13.9937C9.91986 14.1442 9.77714 14.2649 9.61113 14.3438L1.59438 18.1483C1.45216 18.2157 1.29677 18.2508 1.13938 18.2509Z" fill="#D0BED8" />
                <path d="M1.13963 11.962C0.915574 11.9623 0.697157 11.8917 0.515565 11.7605C0.333972 11.6292 0.198489 11.444 0.128458 11.2311C0.0584278 11.0183 0.0574306 10.7888 0.125609 10.5754C0.193788 10.3619 0.327656 10.1755 0.508101 10.0427L7.09379 5.1908L0.682882 2.14821C0.429711 2.02636 0.235053 1.80927 0.141435 1.54436C0.0478176 1.27945 0.0628508 0.988251 0.18325 0.734389C0.30365 0.480527 0.519629 0.284635 0.784001 0.189509C1.04837 0.0943823 1.33965 0.107755 1.59419 0.226705L9.61094 4.03164C9.7771 4.11044 9.91998 4.23098 10.0256 4.3815C10.1313 4.53203 10.1961 4.70738 10.2137 4.89042C10.2313 5.07347 10.2011 5.25796 10.1261 5.42586C10.0511 5.59376 9.93381 5.73934 9.78573 5.84836L1.76919 11.7546C1.58692 11.8893 1.36626 11.962 1.13963 11.962Z" fill="#D0BED8" />
                <path d="M2.20166 6.9397V11.4357L5.25191 9.18867L2.20166 6.9397Z" fill="#D0BED8" />
                <path d="M2.2022 1.1881C2.2022 0.906121 2.0902 0.635689 1.89083 0.436279C1.69147 0.23687 1.42106 0.124814 1.13908 0.124756C0.999466 0.124785 0.861228 0.152312 0.732254 0.205765C0.603281 0.259219 0.486099 0.337552 0.387399 0.436293C0.288699 0.535033 0.210414 0.652247 0.157014 0.781243C0.103613 0.910238 0.0761432 1.04849 0.0761719 1.1881V14.5679H2.20242V1.1881H2.2022Z" fill="#D0BED8" />
                <path d="M16.2652 9.05873C16.9464 9.2521 17.3239 9.86898 17.3239 10.642C17.3239 11.848 16.5872 12.41 15.5838 12.41H13.2915V5.96538H15.3169C16.3022 5.96538 17.0663 6.43482 17.0663 7.65917C17.0663 8.27626 16.8083 8.84698 16.2652 9.05873ZM15.3261 8.9021C16.0532 8.9021 16.6425 8.6442 16.6425 7.64998C16.6425 6.64679 16.0353 6.37948 15.2983 6.37948H13.724V8.90188L15.3261 8.9021ZM15.5562 11.9863C16.2925 11.9863 16.8818 11.5995 16.8818 10.642C16.8818 9.61107 16.2098 9.29804 15.3723 9.29804H13.7242V11.986L15.5562 11.9863ZM21.6329 5.96538V6.36132H19.8562V12.41H19.4233V6.36132H17.6464V5.96538H21.6329ZM24.6615 5.87329C25.7574 5.87329 26.7514 6.42542 27.0649 7.74207H26.6503C26.3555 6.66517 25.4903 6.26945 24.6523 6.26945C23.1333 6.26945 22.3324 7.51217 22.3324 9.18779C22.3324 10.9553 23.1333 12.0875 24.6613 12.0875C25.5636 12.0875 26.3736 11.6829 26.6869 10.4953H27.1014C26.8249 11.867 25.7388 12.5021 24.6615 12.5021C22.9399 12.5021 21.9089 11.2316 21.9089 9.18801C21.9089 7.2267 22.9959 5.87329 24.6615 5.87329ZM30.3426 5.96538C31.4563 5.96538 32.2665 6.69295 32.2665 8.10126C32.2665 9.42688 31.4563 10.2279 30.3426 10.2279H28.6482V12.4102H28.2249V5.96538H30.3426ZM30.3426 9.80423C31.1618 9.80423 31.8336 9.27988 31.8336 8.09207C31.8336 6.90448 31.189 6.37073 30.3426 6.37073H28.6482V9.80423H30.3426ZM32.3403 12.41V12.3542L34.9086 5.94701H35.1022L37.643 12.3542V12.41H37.1919L36.4652 10.5504H33.5278L32.8007 12.41H32.3403ZM35.0011 6.7111L33.6757 10.1448H36.3178L35.0011 6.7111ZM41.4643 5.96538H41.9246V6.02948L39.9173 9.73073V12.41H39.4754V9.73073L37.4594 6.02051V5.96538H37.9291L38.8034 7.60426L39.6878 9.30745H39.6966L40.5893 7.60426L41.4643 5.96538Z" fill="#D0BED8" />
              </g>
              <defs>
                <clipPath id="clip0_161_700">
                  <rect width="42" height="18.375" fill="white" />
                </clipPath>
              </defs>
            </svg>
          </div>

        <?php break;
      case 2: ?>
          <!-- Create Account -->
          <?php
          // Only applicable to new users, otherwise the step is skipped
          $userNpub = $_SESSION['purchase_npub'] ?? null;
          $userNpubVerified = $_SESSION['signup_npub_verified'] ?? null;
          $userPfp = $_SESSION['ppic'] ?? null;
          $userNpub = $_SESSION['usernpub'] ?? null;
          $userNym = $_SESSION['nym'] ?? null;
          ?>
          <div class="mt-10 px-8 sm:mx-auto sm:w-full sm:max-w-sm">
            <div class="flex justify-center group">
              <div class="flex items-center">
                <div>
                  <img class="inline-block h-9 w-9 rounded-full" src="<?= !empty($userPfp) ? htmlentities($userPfp) : '/signup/logo/nblogo@0.1x.png' ?>" alt="Profile Picture">
                </div>
                <div class="ml-3">
                  <p class="text-sm font-medium text-gray-300 group-hover:text-gray-100"><?= !empty($userNym) ? htmlspecialchars($userNym) : 'Anon' ?></p>
                  <p class="text-xs font-medium text-gray-500 group-hover:text-gray-300"><?= !empty($userNpub) ? substr(htmlspecialchars($userNpub), 0, 16) . '...' : 'npub1...' ?></p>
                </div>
              </div>
            </div>
            <form class="space-y-6" action="#" method="POST">
              <?php if (empty($userNpubVerified)) : ?>
                <div>
                  <p class="text-sm font-medium leading-6 text-gray-300">Please verify your Nostr identity to proceed.</p>
                  <button id="signup-verify-nip07-button" type="submit" class="flex w-full justify-center rounded-md bg-purple-600 px-3 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-purple-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600">Verify using extention (NIP-07)</button>
                </div>
                <div>
                  <p class="flex w-full justify-center text-md font-medium leading-6 text-gray-300">Or</p>
                </div>
              <?php endif; ?>
              <div>
                <label for="usernpub" class="block text-sm font-medium leading-6 text-gray-100">Your npub</label>
                <div class="mt-2">
                  <?php if (isset($userNpubVerified) && $userNpubVerified) : ?>
                    <input id="usernpub" name="usernpub" type="text" autocomplete="new-username" required class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-purple-600 sm:text-sm sm:leading-6" placeholder="" value="<?= htmlspecialchars($userNpubVerified) ?>" readonly>
                  <?php else : ?>
                    <input id="usernpub" name="usernpub" type="text" autocomplete="new-username" required class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-purple-600 sm:text-sm sm:leading-6" placeholder="npub1...">
                  <?php endif; ?>
                </div>
              </div>

              <?php if (empty($userNpubVerified)) : ?>
                <div id="signup-verify-dm-input" style="display: none;">
                  <label for="signup-verify-dm-code" class="block text-sm font-medium leading-6 text-gray-100">Verification Code</label>
                  <input id="signup-verify-dm-code" name="signup-verify-dm-code" type="tel" pattern="[0-9]{6}" title="Please enter a 6-digit verification code" autocomplete="off" required class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-purple-600 sm:text-sm sm:leading-6" placeholder="000000" value="" maxlength="6" minlength="6">
                </div>
                <div>
                  <button id="signup-verify-dm-button" type="submit" class="flex w-full justify-center rounded-md bg-purple-600 px-3 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-purple-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600">Verify using direct message (DM)</button>
                </div>
              <?php else : ?>

                <div class="relative -space-y-px rounded-md shadow-sm">
                  <div class="pointer-events-none absolute inset-0 z-10 rounded-md ring-1 ring-inset ring-gray-300"></div>
                  <div>
                    <label for="password" class="sr-only">Password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required class="relative block w-full rounded-t-md border-0 py-1.5 text-gray-900 ring-1 ring-inset ring-gray-100 placeholder:text-gray-400 focus:z-10 focus:ring-2 focus:ring-inset focus:ring-purple-600 sm:text-sm sm:leading-6" placeholder="Password">
                  </div>
                  <div>
                    <label for="password" class="sr-only">Confirm Password</label>
                    <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required class="relative block w-full rounded-b-md border-0 py-1.5 text-gray-900 ring-1 ring-inset ring-gray-900 placeholder:text-gray-400 focus:z-10 focus:ring-2 focus:ring-inset focus:ring-purple-600 sm:text-sm sm:leading-6" placeholder="Confirm password">
                  </div>
                </div>
                <?php if (!empty($account_create_error)) : ?>
                  <p class="mt-2 text-sm text-red-600" id="email-error"><?= htmlentities($account_create_error) ?></p>
                <?php endif; ?>
                <div class="flex w-full justify-center">
                  <label for="enable-nostr-login" class="mt-2 text-sm font-medium leading-6 text-gray-300 text-center">Enable Login with Nostr Identity</label>
                  <input type="checkbox" id="enable-nostr-login-view" name="enable-nostr-login" class="mt-2 m-2 p-2" checked disabled>
                  <input type="hidden" name="enable-nostr-login" id="enable-nostr-login" value="on">
                </div>
                <div>
                  <button type="submit" class="flex w-full justify-center rounded-md bg-purple-600 px-3 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-purple-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600">Create Account</button>
                  <h4 class="mt-2 text-sm font-medium leading-6 text-gray-300 text-center">Already have an account? <a href="/login" class="text-purple-300 hover:text-purple-200">Log in</a></h4>
                  <h4 class="mt-2 text-sm font-medium leading-6 text-gray-300 text-center">Creating an account agrees to our <a href="/tos" class="text-purple-300 hover:text-purple-200" target="_blank">Terms of Service</a>.</h4>
                </div>

              <?php endif; ?>
            </form>
          </div>
          <script src="/scripts/dist/signup.js?v=65e8a1b5988daf55c4bf24bfcc268ef0"></script>
          <script>
            document.addEventListener('DOMContentLoaded', (event) => {
              // Check if NIP-07 extension is installed and enable NIP-07 login
              let nip07Button = document.getElementById('signup-verify-nip07-button');
              if (nip07Button) {
                nip07Button.addEventListener("click", async (e) => {
                  e.preventDefault();
                  await verifyWithNip07(window.location.origin + "/api/v2/account/verify", nip07Button)
                });
              }

              // Verify npub ownership using Nostr DM (NIP-04)
              let dmButton = document.getElementById('signup-verify-dm-button');
              let dmCodeInput = document.getElementById('signup-verify-dm-input');
              let npubInput = document.getElementById('usernpub');
              if (dmButton) {
                dmButton.addEventListener("click", async (e) => {
                  e.preventDefault();
                  await verifyWithDM(window.location.origin + "/api/v2/account/verify", dmButton, dmCodeInput, npubInput)
                });
              }
              if (dmCodeInput) {
                //intercept enter keypress on dm code input
                dmCodeInput.addEventListener("keypress", async (e) => {
                  if (e.key === 'Enter') {
                    e.preventDefault();
                    await verifyWithDM(window.location.origin + "/api/v2/account/verify", dmButton, dmCodeInput, npubInput)
                  }
                });
              }
              if (npubInput) {
                //intercept enter keypress on npub input
                npubInput.addEventListener("keypress", async (e) => {
                  if (e.key === 'Enter') {
                    e.preventDefault();
                    await verifyWithDM(window.location.origin + "/api/v2/account/verify", dmButton, dmCodeInput, npubInput)
                  }
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
        <?php break;
      case 3:
        ?>
          <!-- Pay the Fee -->
          <?php
          // Helper function to create the invoice
          // TODO: Relocate this function to a more appropriate file
          function createInvoice(BTCPayClient $btcpayClient, Plan $plan, string $npub, string $period, string $orderType, string $orderIdPrefix = 'nb_signup_order', string $redirectUrl, ?string $referralCode = null): ?string
          {
            $period = $_SESSION['purchase_period'];
            $price = match ($period) {
              '1y' => $plan->priceInt,
              '2y' => $plan->priceInt2y,
              '3y' => $plan->priceInt3y,
              default => $plan->priceInt,
            };
            // Handle renewals
            if ($orderType === 'renewal') {
              $price = match ($period) {
                '1y' => $plan->fullPriceInt,
                '2y' => $plan->full2yPriceInt,
                '3y' => $plan->full3yPriceInt,
                default => $plan->fullPriceInt,
              };
            }
            // Check if price is set to -1, which means the plan is not available for the selected period
            if ($price !== -1) {
              // Store the final price to ensure consistency across invoice validations
              $_SESSION['purchase_price'] = $price;

              return $btcpayClient->createInvoice(
                amount: $price,
                redirectUrl: $redirectUrl,
                orderIdPrefix: $orderIdPrefix,
                metadata: [
                  'plan' => $plan->id,
                  'planName' => $plan->name,
                  'planStart' => date('Y-m-d'),
                  'userNpub' => $npub,
                  'orderPeriod' => $period,
                  'orderType' => $orderType,
                  'purchasePrice' => $price, // Can be used to verify that the full amount was paid.
                  'referralCode' => $referralCode ?? '',
                ]
              );
            } else {
              return null;
            }
          }

          $btcpayClient = $purchase->getBTCPayClient();
          // Check if invoiceId is already set in session, and if invoice is not yet expired.
          // If expired, create a new invoice, otherwise use the existing one.
          $redirectUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/plans/?step=4';

          // Identify order type, e.g. 'signup', 'renewal', 'upgrade'
          $orderType = 'signup';
          $orderIdPrefix = 'nb_signup_order';
          $_selectedPlan = Plans::$PLANS[$selectedPlan];
          if ($currentAccountLevel !== null && $currentAccountLevel !== $selectedPlan) {
            $orderType = 'upgrade';
            $orderIdPrefix = 'nb_upgrade_order';
          } else if ($currentAccountLevel !== null && $currentAccountLevel === $selectedPlan) {
            $orderType = 'renewal';
            $orderIdPrefix = 'nb_renewal_order';
          }

          if (!isset($_SESSION['purchase_invoiceId']) || $_SESSION['purchase_invoiceId'] === null) {
            // If no invoice exists, create a new one
            $referralCode = (isset($_SESSION['purchase_ref']) ? $_SESSION['purchase_ref'] : null);
            $_SESSION['purchase_invoiceId'] = createInvoice(
              $btcpayClient,
              $_selectedPlan,
              $_SESSION['purchase_npub'],
              $_SESSION['purchase_period'],
              $orderType,
              $orderIdPrefix,
              $redirectUrl,
              $referralCode
            );
          } else {
            try {
              $invoice = $btcpayClient->getInvoice($_SESSION['purchase_invoiceId']);
              $invoiceStatus = $invoice->getStatus();
              $invoiceNpub = $invoice->getData()['metadata']['userNpub'];
              $invoicePlan = $invoice->getData()['metadata']['plan'];
              $invoicePeriod = $invoice->getData()['metadata']['orderPeriod'];
              $invoiceOrderType = $invoice->getData()['metadata']['orderType'];
              $purchasePrice = $invoice->getData()['metadata']['purchasePrice'];
              $priceEqual = BTCPayClient::amountEqualString($purchasePrice, $invoice->getAmount());

              if (
                $invoiceStatus == 'Expired' ||
                $invoiceNpub != $_SESSION['purchase_npub'] ||
                $invoicePlan != $selectedPlan ||
                $invoicePeriod != $_SESSION['purchase_period'] ||
                $invoiceOrderType != $orderType ||
                !$priceEqual
              ) {
                // Conditions met for a new invoice creation
                $_SESSION['purchase_invoiceId'] = createInvoice($btcpayClient, $_selectedPlan, $_SESSION['purchase_npub'], $_SESSION['purchase_period'], $orderType, $orderIdPrefix, $redirectUrl);
              }
            } catch (Exception $e) {
              // Handle exception by creating a new invoice if the existing one is not found or any other error occurs
              $_SESSION['purchase_invoiceId'] = createInvoice($btcpayClient, $_selectedPlan, $_SESSION['purchase_npub'], $_SESSION['purchase_period'], $orderType, $orderIdPrefix, $redirectUrl);
            }
          }

          ?>
          <div class="flex flex-col justify-center mx-auto max-w-7xl sm:px-6 lg:px-8 py-16 space-y-4">
            <?php if (!isset($_SESSION['purchase_invoiceId']) && $_SESSION['purchase_invoiceId'] === null) : ?>
              <p class="text-2xl font-semibold text-center text-gray-300">An error occurred while creating the invoice. Please try again later or choose eligible plan.</p>
            <?php endif; ?>
            <button onclick="window.btcpay.showInvoice('<?= $_SESSION['purchase_invoiceId'] ?>');" type="button" class="self-center inline-flex items-center gap-x-2 rounded-md bg-purple-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-purple-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600">
              Show Invoice
              <svg class="-mr-0.5 h-5 w-5" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
              </svg>
            </button>
            <button onclick="window.location.href = '?step=1';" type="button" class="self-center inline-flex items-center gap-x-2 rounded-md bg-purple-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-purple-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-600">
              Change Plan
              <svg class="-mr-0.5 h-5 w-5" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
              </svg>
            </button>
          </div>
        <?php break;
      case 4: ?>
          <!-- Done -->
          <?php
          // If we reached this step, it means that the invoice is Settled and the account is created, renewed, or upgraded.
          // We can now set the account level accordingly.
          // We will independently fetch the session invoice and set plan for the npub specified in invoice metadata.
          try {
            $btcpayClient = $purchase->getBTCPayClient();
            $invoice = $btcpayClient->getInvoice($_SESSION['purchase_invoiceId']);
            $invoiceNpub = $invoice->getData()['metadata']['userNpub'];
            $invoicePlan = $invoice->getData()['metadata']['plan'];
            $invoicePeriod = $invoice->getData()['metadata']['orderPeriod'];
            $invoiceOrderType = $invoice->getData()['metadata']['orderType'];
            $purchasePrice = $invoice->getData()['metadata']['purchasePrice'];
            $priceEqual = BTCPayClient::amountEqualString($purchasePrice, $invoice->getAmount());

            if ($invoiceNpub != $_SESSION['purchase_npub'] || !$priceEqual || !$_SESSION['purchase_finished']) {
              throw new Exception('Invoice did not match the expected npub or price, or was not settled.');
            }

            // Update account level and expiration date
            $finalizeAccount = new Account($invoiceNpub, $link);
            $new = $invoiceOrderType !== 'renewal'; // If not renewal, set boolean to true
            $isNewOrNotRenewal = $new ? 'new' : 'renewal';
            error_log("Setting plan for $invoiceNpub to $invoicePlan, $invoicePeriod, $isNewOrNotRenewal.");
            $finalizeAccount->setPlan((int)$invoicePlan, (string) $invoicePeriod, $new);
            // Destroy account object
            unset($finalizeAccount);
            // Unset session login information
            if ($invoiceOrderType != 'renewal') {
              unset(
                $_SESSION["loggedin"],
                $_SESSION["id"],
                $_SESSION["usernpub"],
                $_SESSION["acctlevel"],
                $_SESSION["nym"],
                $_SESSION["ppic"],
                $_SESSION["wallet"],
                $_SESSION["flag"],
                $_SESSION["accflags"]
              );
            } else {
              // Renewal, update session account level
              $_SESSION["acctlevel"] = $invoicePlan;
            }
            // Unset all purchase_* session variables
            unset(
              $_SESSION['purchase_npub'],
              $_SESSION['purchase_period'],
              $_SESSION['purchase_price'],
              $_SESSION['purchase_invoiceId'],
              $_SESSION['purchase_plan'],
              $_SESSION['purchase_finished']
            );
          } catch (Exception $e) {
            // Something went wrong, display error
            $account_final_error = "An error occurred: " . $e->getMessage();
            error_log($account_final_error . "\n" . $e->getTraceAsString());
          }
          ?>
          <div class="bg-inherit">
            <div class="mx-auto max-w-7xl py-24 sm:px-6 sm:py-32 lg:px-8">
              <div class="relative isolate overflow-hidden bg-inherit px-6 py-24 text-center shadow-2xl sm:rounded-3xl sm:px-16">
                <?php if (empty($account_final_error)) : ?>
                  <h2 class="mx-auto max-w-2xl text-3xl font-bold tracking-tight text-white sm:text-4xl">Thank you!</h2>
                  <p class="mx-auto mt-6 max-w-xl text-lg leading-8 text-gray-300">You are all set, click the Login button below to get started!</p>
                  <div class="mt-10 flex items-center justify-center gap-x-6">
                    <a href="/login" class="rounded-md bg-white px-3.5 py-2.5 text-sm font-semibold text-gray-900 shadow-sm hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">Login</a>
                  </div>
                <?php else : ?>
                  <h2 class="mx-auto max-w-2xl text-3xl font-bold tracking-tight text-white sm:text-4xl">Something went wrong!</h2>
                  <p class="mx-auto mt-6 max-w-xl text-lg leading-8 text-gray-300">We are sorry, but something went wrong with the process, or your payment is delayed. Rest assured, we will investigate and contact you with additional information. If you used an on-chain payment method, it may take some time to confirm, and we will update your account as soon as possible.</p>
                  <div class="mt-10 flex items-center justify-center gap-x-6">
                    <a href="?step=4" class="rounded-md bg-white px-3.5 py-2.5 text-sm font-semibold text-gray-900 shadow-sm hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">Refresh</a>
                  </div>
                <?php endif; ?>
                <svg viewBox="0 0 1024 1024" class="absolute left-1/2 top-1/2 -z-10 h-[64rem] w-[64rem] -translate-x-1/2 [mask-image:radial-gradient(closest-side,white,transparent)]" aria-hidden="true">
                  <circle cx="512" cy="512" r="512" fill="url(#827591b1-ce8c-4110-b064-7cb85a0b1217)" fill-opacity="0.7" />
                  <defs>
                    <radialGradient id="827591b1-ce8c-4110-b064-7cb85a0b1217">
                      <stop stop-color="#7775D6" />
                      <stop offset="1" stop-color="#E935C1" />
                    </radialGradient>
                  </defs>
                </svg>
              </div>
            </div>
          </div>
        <?php break;
    endswitch;
    // Display "Return to Account page" button if user is loggied in and is not a guest
    if (!$perm->isGuest() && $perm->validateLoggedin()) : ?>
        <div class="flex justify-center mt-8">
          <a href="/account" class="rounded-md bg-purple-100 px-3.5 py-2.5 text-sm font-semibold text-purple-900 shadow-sm hover:bg-purple-200 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-100">Return to Account page</a>
        </div>
      <?php endif; ?>

      <!-- Features link for all users -->
      <div class="flex justify-center mt-12 mb-8">
        <div class="text-center">
          <p class="text-gray-400 text-sm mb-3">Want to learn more about what's included?</p>
          <a href="/features/" class="inline-flex items-center text-purple-400 hover:text-purple-300 text-sm font-medium transition-colors duration-200">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Explore all features in detail
            <svg class="w-4 h-4 ml-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
          </a>
        </div>
      </div>
  </main>

  <!-- Footer -->
  <?php include $_SERVER['DOCUMENT_ROOT'] . '/components/footer.php'; ?>

  <script src="https://btcpay.nostr.build/modal/btcpay.js"></script>
  <script>
    <?php if ($step == 3 && isset($_SESSION['purchase_invoiceId'])) : ?>
      window.btcpay.showInvoice('<?= $_SESSION['purchase_invoiceId'] ?>');
    <?php endif; ?>
  </script>
</body>

</html>
