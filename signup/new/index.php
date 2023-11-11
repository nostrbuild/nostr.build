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
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/BTCPayClient.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Plans.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Account.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Bech32.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Signup.class.php';

// Create new Permission object
$perm = new Permission();

// If the user is logged in and has a verified account, redirect to the upgrade page
if (
  $perm->validateLoggedin() &&
  $perm->validatePermissionsLevelMoreThanOrEqual(1)
) {
  header('Location: /signup/upgrade/');
  exit;
} elseif (
  $perm->validateLoggedin() &&
  $perm->validatePermissionsLevelEqual(0) &&
  !isset($_SESSION['signup_npub'])
) {
  // If the user is logged in but has an unverified account, set signup_npub in session
  $_SESSION['signup_npub'] = $_SESSION['usernpub'];
}

if (isset($_GET['reset'])) {
  session_destroy();
  header('Location: /signup/new/');
  exit;
}

// Setup environment
global $link;
global $btcpayConfig;
Plans::init(); // Initialize plans

// Create new Signup instance
$signup = new Signup(new BTCPayClient(
  $btcpayConfig['apiKey'],
  $btcpayConfig['host'],
  $btcpayConfig['storeId'],
), Plans::getInstance());

// Get selected plan
$selectedPlan = $signup->getSelectedPlanId();
// Get current step
$step = $signup->getValidatedStep();
// Get all steps
$steps = $signup->getSteps();

// Processing form data when form is submitted to create an account
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  // Define variables and initialize with empty values
  $usernpub = $password = $confirm_password = "";
  $account_create_error = "";
  $bech32 = new Bech32();

  // Validate usernpub
  $usernpub = trim($_POST["usernpub"]);
  if (empty($_SESSION['npub_verified'])) {
    $account_create_error = "Please verify your npub1 public key.";
  } elseif (
    !empty($usernpub) &&
    !empty($_SESSION['npub_verified']) &&
    $_SESSION['npub_verified'] != $usernpub
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
  if (empty($account_create_error)) {
    try {
      $account = new Account($usernpub, $link);
      if ($account->accountExists()) {
        $account_create_error = 'This npub1 public key is already in use. Please enter a different public key.';
      }
      $npubVerifiedFlag = $_SESSION['npub_verified'] === $usernpub ? 1 : 0;
      $enableNostrLoginFlag = isset($_POST['enable-nostr-login']) && $npubVerified ? 1 : 0;
      if (!$npubVerifiedFlag) {
        $account_create_error = "The npub1 public key you entered does not match the one you verified. Please enter the correct public key.";
      }
      $account->createAccount($password, 0 /* level, default to 0 */, $npubVerifiedFlag, $enableNostrLoginFlag);
      if ($npubVerifiedFlag && $enableNostrLoginFlag) {
        // If the npub is verified, set the session variable
        $account->verifyNpub();
        $account->allowNpubLogin();
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
    $_SESSION['signup_step'] = $step;
    $_SESSION['signup_npub'] = $usernpub;
  } else {
    // Encountered an error, display it in the form and stay on the same step
    $step = 2;
    $_SESSION['signup_step'] = $step;
  }
}
?>

<!DOCTYPE html>
<html class="bg-gradient-to-b from-[#292556] to-[#120a24] antialiased" lang="en">

<head>
  <meta charSet="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Nostr.build account signup</title>
  <link rel="stylesheet" href="/styles/twbuild.css?v=18" />
  <link rel="stylesheet" href="/styles/index.css?v=3" />
  <link rel="stylesheet" href="/styles/signup.css?v=3" />
  <link rel="icon" href="/assets/primo_nostr.png" />
  <script defer src="/scripts/fw/alpinejs.min.js?v=6"></script>
  <!--
  <script defer src="/scripts/fw/htmx.min.js?v=6"></script>
  -->
  <style>
    [x-cloak] {
      display: none !important;
    }
  </style>
</head>

<body class="min-h-screen">
  <header>
    <img class="top_img block mx-auto" src="https://cdn.nostr.build/assets/signup.png" alt="nostr.build image" />
    <h1 class="text-2xl">nostr.build account options</h1>
    <div class="information pb-5">
      <ul>
        <li>
          <span class="whitespace-pre-line">Storage on AWS S3 with Cloudflare global CDN
            Damus/Amethyst/Snort/Coracle/noStrudel integration
            Add/Delete media from your private folders
            Bitcoin Only | Never Ads | Billed Annually
          </span>
        </li>
      </ul>
    </div>
  </header>
  <main>
    <nav hx-boost="false" class="flex items-center justify-center" aria-label="Progress">
      <p class="text-sm font-medium text-gray-300">Step <?= $step ?> of <?= count($steps) ?></p>
      <ol role="list" class="ml-8 flex items-center space-x-5">
        <?php foreach ($steps as $index => $value) : ?>
          <li>
            <?php if ($step - 1 > $index) : ?>
              <!-- Completed Step -->
              <a href="?step=<?= $value[1] ?>" title="<?= $value[0] ?>" class="block h-2.5 w-2.5 rounded-full bg-indigo-600 hover:bg-indigo-900">
                <span class="sr-only"><?= $value[0] ?></span>
              </a>
            <?php elseif ($step - 1 == $index) : ?>
              <!-- Current Step -->
              <a href="?step=<?= $value[1] ?>" title="<?= $value[0] ?>" class="relative flex items-center justify-center" aria-current="step">
                <span class="absolute flex h-5 w-5 p-px" aria-hidden="true">
                  <span class="h-full w-full rounded-full bg-indigo-200"></span>
                </span>
                <span class="relative block h-2.5 w-2.5 rounded-full bg-indigo-600" aria-hidden="true"></span>
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
    <?php switch ($step):
      default:
      case 1: ?>

        <!-- Plans section -->

        <div class="py-10 sm:py-15">
          <div class="mx-auto max-w-7xl px-6 lg:px-8">

            <div class="isolate mx-auto mt-10 grid max-w-md grid-cols-1 gap-8 md:max-w-2xl md:grid-cols-2 lg:max-w-4xl xl:mx-0 xl:max-w-none xl:grid-cols-4">
              <!-- Plans -->
              <?php foreach (Plans::$PLANS as $plan) : ?>

                <div hx-boost="fasle" class="rounded-3xl p-8 ring-1 ring-gray-200 <?= $plan->id == $selectedPlan ? 'ring-2 ring-indigo-600' : '' ?>">
                  <img class="mx-auto h-auto w-auto pb-3" src="<?= $plan->image ?>" alt="<?= $plan->imageAlt ?>">
                  <h3 id="tier-<?= $plan->id ?>" class="text-center text-lg font-semibold leading-8 <?= $plan->id == $selectedPlan ? 'text-indigo-300' : 'text-gray-100' ?>"><?= $plan->name ?></h3>
                  <p class="mt-6 flex items-baseline gap-x-1">
                    <span class="text-4xl font-bold tracking-tight text-gray-100"><?= $plan->price ?></span>
                    <span class="text-sm font-semibold leading-6 text-gray-300"><?= $plan->currency ?></span>
                  </p>
                  <a href="?step=2&plan=<?= $plan->id ?>" aria-describedby="tier-<?= $plan->id ?>" class="mt-6 block rounded-md py-2 px-3 text-center text-sm font-semibold leading-6 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 <?= $plan->id == $selectedPlan ? 'bg-indigo-600 text-white shadow-sm hover:bg-indigo-500' : 'text-indigo-300 ring-1 ring-inset ring-indigo-200 hover:ring-indigo-300' ?>">Buy plan</a>
                  <ul role="list" class="mt-8 space-y-3 text-sm leading-6 text-gray-300">
                    <?php foreach ($plan->features as $feature) : ?>
                      <li class="flex gap-x-3">
                        <svg class="h-6 w-5 flex-none text-indigo-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                          <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                        </svg>
                        <?= $feature ?>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endforeach; ?>
              <!-- /Plans -->
            </div>
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
        $userNpub = $_SESSION['signup_npub'] ?? null;
        $userNpubVerified = $_SESSION['npub_verified'] ?? null;
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
                <button id="signup-verify-nip07-button" type="submit" class="flex w-full justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Verfiy using extention (NIP-07)</button>
              </div>
              <div>
                <p class="flex w-full justify-center text-md font-medium leading-6 text-gray-300">Or</p>
              </div>
            <?php endif; ?>
            <div>
              <label for="usernpub" class="block text-sm font-medium leading-6 text-gray-100">Your npub</label>
              <div class="mt-2">
                <?php if (isset($userNpubVerified) && $userNpubVerified) : ?>
                  <input id="usernpub" name="usernpub" type="text" autocomplete="new-username" required class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" placeholder="" value="<?= htmlspecialchars($userNpubVerified) ?>" readonly>
                <?php else : ?>
                  <input id="usernpub" name="usernpub" type="text" autocomplete="new-username" required class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" placeholder="npub1...">
                <?php endif; ?>
              </div>
            </div>

            <?php if (empty($userNpubVerified)) : ?>
              <div id="signup-verify-dm-input" style="display: none;">
                <label for="signup-verify-dm-code" class="block text-sm font-medium leading-6 text-gray-100">Verification Code</label>
                <input id="signup-verify-dm-code" name="signup-verify-dm-code" type="tel" pattern="[0-9]{6}" title="Please enter a 6-digit verification code" autocomplete="off" required class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" placeholder="000000" value="" maxlength="6" minlength="6">
              </div>
              <div>
                <button id="signup-verify-dm-button" type="submit" class="flex w-full justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Verify using direct message (DM)</button>
              </div>
            <?php else : ?>

              <div class="relative -space-y-px rounded-md shadow-sm">
                <div class="pointer-events-none absolute inset-0 z-10 rounded-md ring-1 ring-inset ring-gray-300"></div>
                <div>
                  <label for="password" class="sr-only">Password</label>
                  <input id="password" name="password" type="password" autocomplete="new-password" required class="relative block w-full rounded-t-md border-0 py-1.5 text-gray-900 ring-1 ring-inset ring-gray-100 placeholder:text-gray-400 focus:z-10 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" placeholder="Password">
                </div>
                <div>
                  <label for="password" class="sr-only">Confirm Password</label>
                  <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" required class="relative block w-full rounded-b-md border-0 py-1.5 text-gray-900 ring-1 ring-inset ring-gray-900 placeholder:text-gray-400 focus:z-10 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" placeholder="Confirm password">
                </div>
              </div>
              <?php if (!empty($account_create_error)) : ?>
                <p class="mt-2 text-sm text-red-600" id="email-error"><?= htmlentities($account_create_error) ?></p>
              <?php endif; ?>
              <div class="flex w-full justify-center">
                <label for="enable-nostr-login" class="mt-2 text-sm font-medium leading-6 text-gray-300 text-center">Enable Login with Nostr Identity</label>
                <input type="checkbox" id="enable-nostr-login" name="enable-nostr-login" class="mt-2 m-2 p-2" checked>
              </div>
              <div>
                <button type="submit" class="flex w-full justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Create Account</button>
                <h4 class="mt-2 text-sm font-medium leading-6 text-gray-300 text-center">Already have an account? <a href="/login" class="text-indigo-300 hover:text-indigo-200">Log in</a></h4>
                <h4 class="mt-2 text-sm font-medium leading-6 text-gray-300 text-center">Creating an account agrees to our <a href="/tos" class="text-indigo-300 hover:text-indigo-200" target="_blank">Terms of Service</a>.</h4>
              </div>

            <?php endif; ?>
          </form>
        </div>
        <script src="/scripts/dist/signup.js?v=5"></script>
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
        $btcpayClient = $signup->getBTCPayClient();
        // Check if invoiceId is already set in session, and if invoice is not yet expired.
        // If expired, create a new invoice, otherwise use the existing one.
        $redirectUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/signup/new/?step=4';
        if (isset($_SESSION['signup_invoiceId'])) {
          try {
            $invoice = $btcpayClient->getInvoice($_SESSION['signup_invoiceId']);
            $invoiceStatus = $invoice->getStatus();
            $invoiceNpub = $invoice->getData()['metadata']['userNpub'];
            $invoicePlan = $invoice->getData()['metadata']['plan'];
            $priceEqual = BTCPayClient::amountEqual(Plans::$PLANS[$selectedPlan]->priceInt, $invoice->getAmount());

            if ($invoiceStatus == 'Expired' || $invoiceNpub != $_SESSION['signup_npub'] || $invoicePlan != $selectedPlan || !$priceEqual) {
              $invoiceId = $btcpayClient->createInvoice(Plans::$PLANS[$selectedPlan]->priceInt, $redirectUrl, ['plan' => $selectedPlan, 'userNpub' => $_SESSION['signup_npub']]);
              $_SESSION['signup_invoiceId'] = $invoiceId;
            } else {
              $invoiceId = $_SESSION['signup_invoiceId'];
            }
          } catch (Exception $e) {
            // Invoice not found, create a new one
            $invoiceId = $btcpayClient->createInvoice(Plans::$PLANS[$selectedPlan]->priceInt, $redirectUrl, ['plan' => $selectedPlan, 'userNpub' => $_SESSION['signup_npub']]);
            $_SESSION['signup_invoiceId'] = $invoiceId;
          }
        } else {
          // Create a new invoice
          $invoiceId = $btcpayClient->createInvoice(Plans::$PLANS[$selectedPlan]->priceInt, $redirectUrl, ['plan' => $selectedPlan, 'userNpub' => $_SESSION['signup_npub']]);
          $_SESSION['signup_invoiceId'] = $invoiceId;
        }
        ?>
        <div class="flex flex-col justify-center mx-auto max-w-7xl sm:px-6 lg:px-8 py-16 space-y-4">
          <button onclick="window.btcpay.showInvoice('<?= $invoiceId ?>');" type="button" class="self-center inline-flex items-center gap-x-2 rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
            Show Invoice
            <svg class="-mr-0.5 h-5 w-5" aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
            </svg>
          </button>
          <button onclick="window.location.href = '?step=1';" type="button" class="self-center inline-flex items-center gap-x-2 rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
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
        // If we reached this step, it means that the invoice is Settled and the account is created.
        // We can now set the account level accordingly.
        // We will independently fetch the session invoice and set plan for the npub specified in invoice metadata.
        try {
          $btcpayClient = $signup->getBTCPayClient();
          $invoice = $btcpayClient->getInvoice($_SESSION['signup_invoiceId']);
          $invoiceNpub = $invoice->getData()['metadata']['userNpub'];
          $invoicePlan = $invoice->getData()['metadata']['plan'];
          $priceEqual = BTCPayClient::amountEqual(Plans::$PLANS[$invoicePlan]->priceInt, $invoice->getAmount());

          if ($invoiceNpub != $_SESSION['signup_npub'] || !$priceEqual) {
            throw new Exception('Invoice did not match the expected npub or price.');
          }
          $account = new Account($invoiceNpub, $link);
          $account->setPlan($invoicePlan);
          // Destroy account object
          unset($account);
          // Unset session login information
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
    endswitch; ?>
  </main>

  <!-- Footer -->
  <?php include $_SERVER['DOCUMENT_ROOT'] . '/components/footer.php'; ?>

  <script src="https://btcpay.nostr.build/modal/btcpay.js"></script>
  <script>
    <?php if ($step == 3 && isset($invoiceId)) : ?>
      window.btcpay.showInvoice('<?= $invoiceId ?>');
    <?php endif; ?>
  </script>
</body>

</html>