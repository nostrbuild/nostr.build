<?php
// Include config, session, and Permission class files
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/BTCPayClient.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Plans.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Account.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Bech32.class.php';

global $link;
// Create new Permission object
$perm = new Permission();

// Instantiate BTCPayClient
/*
global $btcpayConfig;
$btcpayClient = new BTCPayClient(
  $btcpayConfig['apiKey'],
  $btcpayConfig['host'],
  $btcpayConfig['storeId'],
  $btcpayConfig['secret']
);
*/

// Check if the user is already logged in and has paid, if yes then redirect to the account page
/*if ($perm->validateLoggedin() && !$perm->validatePermissionsLevelEqual(0)) {
  header("location: /account");
  exit;
}*/

// Define steps of the sign-up process
$steps = [
  ["Choose a Plan", "1"],
  ["Create Your Account", "2"],
  ["Pay the Fee", "3"],
  ["Done", "4"]
];

// Figureout where we are in the sign-up process

// Check if step is set in GET parameter or in SESSION
if (isset($_GET['step']) && is_numeric($_GET['step'])) {
  $step = $_GET['step'];
  // Store the step progression in a session
  $_SESSION['signup_step'] = $step;
} elseif (isset($_SESSION['signup_step'])) {
  $step = $_SESSION['signup_step'];
} else {
  // Default value if no step is set yet
  $step = 1;
}

// Check if plan is set in GET parameter or in SESSION
if (isset($_GET['plan']) && is_numeric($_GET['plan'])) {
  $selectedPlan = $_GET['plan'];
  // Store the step progression in a session
  $_SESSION['signup_plan'] = $selectedPlan;
} elseif (isset($_SESSION['signup_plan'])) {
  $selectedPlan = $_SESSION['signup_plan'];
} else {
  // Default value if no step is set yet
  $selectedPlan = 2;
}

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  // Define variables and initialize with empty values
  $usernpub = $password = $confirm_password = "";
  $account_create_error = "";
  $bech32 = new Bech32();

  // Validate usernpub
  $usernpub = trim($_POST["usernpub"]);
  if (empty($usernpub)) {
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
      $level = 0;
      $account->createAccount($password, $level);
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
  <title>Nostr.build Signup</title>
  <link rel="stylesheet" href="/styles/twbuild.css?v=7" />
  <script defer src="/scripts/fw/alpinejs.min.js?v=3"></script>
  <script defer src="/scripts/fw/htmx.min.js?v=3"></script>
  <style>
    [x-cloak] {
      display: none !important;
    }
  </style>
</head>

<body class="min-h-screen">
  <!-- Navbar -->
  <header x-data="{ open: false }" class="bg-inherit">
    <nav class="mx-auto flex max-w-7xl items-center justify-between p-6 lg:px-8" aria-label="Global">
      <div class="flex lg:flex-1">
        <a href="/" class="-m-1.5 p-1.5">
          <span class="sr-only">nostr.build</span>
          <img class="h-8 w-auto" src="/signup/logo/nblogo@0.1x.png" alt="nostr.build logo">
        </a>
      </div>
      <div class="flex lg:hidden">
        <button x-on:click="open = ! open" type="button" class="-m-2.5 inline-flex items-center justify-center rounded-md p-2.5 text-gray-300">
          <span class="sr-only">Open main menu</span>
          <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
          </svg>
        </button>
      </div>
      <div class="hidden lg:flex lg:gap-x-12">
        <a href="/" class="text-sm font-semibold leading-6 text-gray-100">Home</a>
        <a href="/builders" class="text-sm font-semibold leading-6 text-gray-100">Builders</a>
        <a href="/creators" class="text-sm font-semibold leading-6 text-gray-100">Creators</a>
      </div>
      <div class="hidden lg:flex lg:flex-1 lg:justify-end">
        <a href="/login" class="text-sm font-semibold leading-6 text-gray-100">Log in <span aria-hidden="true">&rarr;</span></a>
      </div>
    </nav>
    <!-- Mobile menu, show/hide based on menu open state. -->
    <div x-cloak x-show.important="open" class="lg:hidden" role="dialog" aria-modal="true">
      <!-- Background backdrop, show/hide based on slide-over state. -->
      <div x-show.important="open" class="fixed inset-0 z-10"></div>
      <div class="fixed inset-y-0 right-0 z-20 w-full overflow-y-auto bg-gradient-to-b from-[#292556] to-[#120a24] px-6 py-6 sm:max-w-sm sm:ring-1 sm:ring-gray-100/10">
        <div class="flex items-center justify-between">
          <a href="/" class="-m-1.5 p-1.5">
            <span class="sr-only">nostr.build</span>
            <img class="h-8 w-auto" src="/signup/logo/nblogo@0.1x.png" alt="nostr.build logo">
          </a>
          <button x-on:click="open = ! open" type="button" class="-m-2.5 rounded-md p-2.5 text-gray-300">
            <span class="sr-only">Close menu</span>
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="mt-6 flow-root">
          <div class="-my-6 divide-y divide-gray-500/10">
            <div class="space-y-2 py-6">
              <a href="/" class="-mx-3 block rounded-lg px-3 py-2 text-base font-semibold leading-7 text-gray-100 hover:bg-gray-950">Home</a>
              <a href="/builders" class="-mx-3 block rounded-lg px-3 py-2 text-base font-semibold leading-7 text-gray-100 hover:bg-gray-950">Builders</a>
              <a href="/creators" class="-mx-3 block rounded-lg px-3 py-2 text-base font-semibold leading-7 text-gray-100 hover:bg-gray-950">Creators</a>
            </div>
            <div class="py-6">
              <a href="/login" class="-mx-3 block rounded-lg px-3 py-2.5 text-base font-semibold leading-7 text-gray-100 hover:bg-gray-950">Log in</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>
  <!-- /Navbar -->
  <main>
    <nav aria-label="Progress" class="p-5 m-3">
      <ol hx-boost="true" role="list" class="divide-y divide-gray-300 rounded-md border border-gray-300 md:flex md:divide-y-0">
        <?php foreach ($steps as $index => $value) : ?>
          <li class="relative md:flex md:flex-1">
            <a href="?step=<?= $value[1] ?>" class="group flex w-full items-center">
              <span class="flex items-center px-6 py-4 text-sm font-medium">
                <?php if ($step > $value[1] || ($step == $value[1] && $value[1] == end($steps)[1])) : ?>
                  <!-- Completed Step -->
                  <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-indigo-600 group-hover:bg-indigo-800">
                    <svg class="h-6 w-6 text-white" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                      <path fill-rule="evenodd" d="M19.916 4.626a.75.75 0 01.208 1.04l-9 13.5a.75.75 0 01-1.154.114l-6-6a.75.75 0 011.06-1.06l5.353 5.353 8.493-12.739a.75.75 0 011.04-.208z" clip-rule="evenodd" />
                    </svg>
                  </span>
                <?php elseif ($step == $value[1]) : ?>
                  <!-- Current Step -->
                  <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full border-2 border-indigo-600">
                    <span class="text-indigo-300"><?= $value[1] ?></span>
                  </span>
                <?php else : ?>
                  <!-- Upcoming Step -->
                  <span class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full border-2 border-gray-300 group-hover:border-gray-400">
                    <span class="text-gray-500 group-hover:text-gray-900"><?= $value[1] ?></span>
                  </span>
                <?php endif; ?>
                <span class="ml-4 text-sm font-medium <?php echo ($step > $value[1]) ? 'text-gray-100' : (($step == $value[1]) ? 'text-indigo-300' : 'text-gray-500 group-hover:text-gray-100') ?>"><?= $value[0] ?></span>
              </span>
            </a>
            <?php if ($index < count($steps) - 1) : ?>
              <div class="absolute right-0 top-0 hidden h-full w-5 md:block" aria-hidden="true">
                <svg class="h-full w-full text-gray-300" viewBox="0 0 22 80" fill="none" preserveAspectRatio="none">
                  <path d="M0 -2L20 40L0 82" vector-effect="non-scaling-stroke" stroke="currentcolor" stroke-linejoin="round" />
                </svg>
              </div>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
    </nav>
    <?php switch ($step):
      default:
      case 1: ?>
        <!-- Plans section -->
        <?php
        // Initialize Plans class based on user login status and current plan
        // TODO
        Plans::init();
        ?>
        <div class="py-10 sm:py-15">
          <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div class="mx-auto max-w-4xl text-center">
              <h2 class="text-base font-semibold leading-7 text-indigo-600">Paid Plans</h2>
              <p class="mt-2 text-4xl font-bold tracking-tight text-gray-100 sm:text-5xl">Plans for your creative needs</p>
            </div>
            <?php if ($perm->validatePermissionsLevelMoreThanOrEqual(1)) : ?>
              <p class="mx-auto mt-6 max-w-2xl text-center text-lg leading-8 text-gray-300">All upgrades are prorated based on your current usge of the plan, and are priced annually.</p>
            <?php else : ?>
              <p class="mx-auto mt-6 max-w-2xl text-center text-lg leading-8 text-gray-300">All new plans are priced annually, upgradable at any time.</p>
            <?php endif; ?>
            <div class="mt-16 flex justify-center">
              <fieldset class="grid grid-cols-2 gap-x-1 rounded-full p-1 text-center text-xs font-semibold leading-5 ring-1 ring-inset ring-gray-200">
                <legend class="sr-only">New / Upgrade</legend>
                <label class="cursor-pointer rounded-full px-2.5 py-1 <?= $perm->validatePermissionsLevelMoreThanOrEqual(1) ? 'text-gray-500' : 'bg-indigo-600 text-white' ?>">
                  <input type="radio" name="purchase_type" value="new" class="sr-only">
                  <span>New</span>
                </label>
                <label class="cursor-pointer rounded-full px-2.5 py-1 <?= $perm->validatePermissionsLevelMoreThanOrEqual(1) ? 'bg-indigo-600 text-white' : 'text-gray-500' ?>">
                  <input type="radio" name="purchase_type" value="upgrade" class="sr-only">
                  <span>Upgrade</span>
                </label>
              </fieldset>
            </div>
            <div class="isolate mx-auto mt-10 grid max-w-md grid-cols-1 gap-8 md:max-w-2xl md:grid-cols-2 lg:max-w-4xl xl:mx-0 xl:max-w-none xl:grid-cols-4">
              <!-- Plans -->
              <?php foreach (Plans::$PLANS as $plan) : ?>
                <div hx-boost="true" class="rounded-3xl p-8 ring-1 ring-gray-200 <?= $plan->id == $selectedPlan ? 'ring-2 ring-indigo-600' : '' ?>">
                  <h3 id="tier-<?= $plan->id ?>" class="text-lg font-semibold leading-8 <?= $plan->id == $selectedPlan ? 'text-indigo-300' : 'text-gray-100' ?>"><?= $plan->name ?></h3>
                  <p class="mt-4 text-sm leading-6 text-gray-300"><?= $plan->description ?></p>
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
      <?php break;
      case 2: ?>
        <?php if ($perm->validateLoggedin()) : ?>
          <!-- Logged in -->
        <?php else : ?>
          <!-- Create Account -->
          <div class="mt-10 px-8 sm:mx-auto sm:w-full sm:max-w-sm">
            <div class="flex justify-center group">
              <div class="flex items-center">
                <div>
                  <img class="inline-block h-9 w-9 rounded-full" src="/signup/logo/nblogo@0.1x.png" alt="Profile Picture">
                </div>
                <div class="ml-3">
                  <p class="text-sm font-medium text-gray-300 group-hover:text-gray-100"><?= isset($userNym) ? htmlspecialchars($userNym) : 'Anon' ?></p>
                  <p class="text-xs font-medium text-gray-500 group-hover:text-gray-300"><?= isset($userNpub) ? htmlspecialchars($userNpub) : 'npub1...' ?></p>
                </div>
              </div>
            </div>
            <form class="space-y-6" action="#" method="POST">
              <div>
                <label for="usernpub" class="block text-sm font-medium leading-6 text-gray-100">Your npub</label>
                <div class="mt-2">
                  <input id="usernpub" name="usernpub" type="text" autocomplete="off" required class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" placeholder="npub1...">
                </div>
              </div>

              <div class="relative -space-y-px rounded-md shadow-sm">
                <div class="pointer-events-none absolute inset-0 z-10 rounded-md ring-1 ring-inset ring-gray-300"></div>
                <div>
                  <label for="password" class="sr-only">Password</label>
                  <input id="password" name="password" type="password" autocomplete="off" required class="relative block w-full rounded-t-md border-0 py-1.5 text-gray-900 ring-1 ring-inset ring-gray-100 placeholder:text-gray-400 focus:z-10 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" placeholder="Password">
                </div>
                <div>
                  <label for="password" class="sr-only">Confirm Password</label>
                  <input id="confirm_password" name="confirm_password" type="password" autocomplete="off" required class="relative block w-full rounded-b-md border-0 py-1.5 text-gray-900 ring-1 ring-inset ring-gray-900 placeholder:text-gray-400 focus:z-10 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" placeholder="Confirm password">
                </div>
              </div>
              <?php if (!empty($account_create_error)) : ?>
                <p class="mt-2 text-sm text-red-600" id="email-error"><?= htmlentities($account_create_error) ?></p>
              <?php endif; ?>
              <div>
                <button type="submit" class="flex w-full justify-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Create Account</button>
              </div>
            </form>
          </div>
        <?php endif; ?>
      <?php break;
      case 3:
        global $btcpayConfig;
        Plans::init();
        $btcpayClient = new BTCPayClient(
          $btcpayConfig['apiKey'],
          $btcpayConfig['host'],
          $btcpayConfig['storeId'],
        );
        // Check if invoiceId is already set in session, and if invoice is not yet expired.
        // If expired, create a new invoice, otherwise use the existing one.
        $redirectUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/signup/?step=4';
        if (isset($_SESSION['signup_invoiceId'])) {
          try {
            $invoice = $btcpayClient->getInvoice($_SESSION['signup_invoiceId']);
            $invoiceStatus = $invoice->getStatus();
            $invoiceNpub = $invoice->getData()['metadata']['userNpub'];
            $invoicePlan = $invoice->getData()['metadata']['plan'];
            // Need to compare BTCPay Precise Number objects, so convert to string first
            //$priceDiff = Plans::$PLANS[$selectedPlan]->priceInt - $invoice->getAmount();

            error_log('Invoice status: ' . $invoiceStatus . PHP_EOL);
            error_log('Invoice npub: ' . $invoiceNpub . PHP_EOL);
            error_log('Invoice plan: ' . $invoicePlan . PHP_EOL);
            error_log('Invoice price diff: ' . $priceDiff . PHP_EOL);


            if ($invoiceStatus == 'expired') {
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
        <!-- Pay the Fee -->
      <?php break;
      case 4: ?>
        <!-- Done -->
        <h1>You are all set, proceed to the <a href="/login">login page</a> to get started.</h1>
    <?php break;
    endswitch; ?>
  </main>

  <!-- Footer -->
  <footer class="bg-inherit">
    <div class="mx-auto max-w-7xl overflow-hidden px-6 py-20 sm:py-24 lg:px-8">
      <nav class="-mb-6 columns-2 sm:flex sm:justify-center sm:space-x-12" aria-label="Footer">
        <div class="pb-6">
          <a href="/" class="text-sm leading-6 text-gray-300 hover:text-gray-100">Home</a>
        </div>
        <div class="pb-6">
          <a href="/builders" class="text-sm leading-6 text-gray-300 hover:text-gray-100">Builders</a>
        </div>
        <div class="pb-6">
          <a href="/creators" class="text-sm leading-6 text-gray-300 hover:text-gray-100">Creators</a>
        </div>
        <div class="pb-6">
          <a href="/login" class="text-sm leading-6 text-gray-300 hover:text-gray-100">Login</a>
        </div>
      </nav>
      <p class="mt-10 text-center text-xs leading-5 text-gray-500">&copy; 2023 nostr.build. All rights reserved.</p>
    </div>
  </footer>

  <script src="https://btcpay.nostr.build/modal/btcpay.js"></script>
  <script>
    <?php if ($step == 3 && isset($invoiceId)) : ?>
      window.btcpay.showInvoice('<?= $invoiceId ?>');
    <?php endif; ?>
  </script>
</body>

</html>