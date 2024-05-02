<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/config.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/SiteConfig.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/functions/session.php";
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/UsersImages.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/UsersImagesFolders.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/utils.funcs.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Account.class.php';

global $link;

$perm = new Permission();
// Initialize the session
$user =  $_SESSION["usernpub"] ?? null;

if (!$perm->validateLoggedin()  || !isset($_SESSION["usernpub"])) {
	header("Location: /login");
	$link->close();
	exit;
}

// If account is not verified, redirect to signup page
if ($perm->validatePermissionsLevelEqual(0)) {
	header("Location: /plans/");
	$link->close();
	exit;
}

$npub = $_SESSION["usernpub"];
$nym = $_SESSION["nym"];
$ppic = $_SESSION["ppic"];
$wallet = $_SESSION["wallet"];
$acctlevel = $_SESSION["acctlevel"];
$userId = $_SESSION["id"];

// Instanciate account class
$account = new Account($npub, $link);
$daysRemaining = 0;
try {
	// Handle cases for users who has no subscription yet
	$daysRemaining = $account->getRemainingSubscriptionDays();
} catch (Exception $e) {
	error_log($e->getMessage());
}

// If user has no subscription, redirect to plans page
$userAccountExpired = $account->isExpired();
/*
if ($daysRemaining <= 0) {
	header("Location: /plans/");
	$link->close();
	exit;
}
*/

$showRenewalButton = $daysRemaining <= 30;
$showUpgradeButton = $acctlevel < 10 && !$showRenewalButton;

// Fetch user's folder statistics and storage statistics
$usersFoldersTable = new UsersImagesFolders($link);
$usersFoldersStats = $usersFoldersTable->getFoldersStats($user);

// TODO: This should be moved to a separate file, class, method
// Validate space usage
$storageUsed = $usersFoldersStats['TOTAL']['totalSize'] ?? 0;

// Get user storage limit based on their level
$userStorageLimit = SiteConfig::getStorageLimit($acctlevel, $account->getAccountAdditionStorage());

// Check if user is over their limit
$userOverLimit = $storageUsed >= $userStorageLimit || $userStorageLimit === 0;
$userStorageRemaining = $userOverLimit ? 0 : $userStorageLimit - $storageUsed;
?>

<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>nostr.build account</title>

	<link rel="stylesheet" href="/styles/account.css?v=148e0a0e2392c20a08d769499e95b3ba" />
	<link href="/scripts/dist/index.css?v=b7bddffa8a0d0d5b2da391cfc2e24f2d" rel="stylesheet">
	<link href="/styles/twbuild.css?v=7e64909872e8f93fd9159fb10eae9532" rel="stylesheet">
	<link rel="icon" href="/assets/primo_nostr.png" />

	<script defer src="/scripts/dist/index.js?v=c629bcbbd198233225c1c4db7deedbff"></script>
	<script defer src="/scripts/fw/alpinejs-intersect.min.js?v=e6545f3f0a314d90d9a1442ff104eab9"></script>
	<script defer src="/scripts/fw/alpinejs.min.js?v=34fbe266eb872c1a396b8bf9022b7105"></script>
	<script defer src="/scripts/fw/htmx.min.js?v=0dc2b5da8a531cecfa8100af6cec8d61"></script>
	<script defer src="/scripts/fw/htmx/loading-states.js?v=128bcf948f60619461c2d6f77b9b8da4"></script>
	<style>
		[x-cloak] {
			display: none !important;
		}

		.publicly-shared {
			border: 0.3125rem solid #00ff00;
		}
	</style>

</head>

<body x-data="{ url_up_open: false }">
	<aside class="sidebar">
		<h1 class="sidebar_title">nostr.build</h1>
		<div class="account_selector">
			<a href="/functions/settings.php"><img src="<?= empty($ppic) ? '/assets/temp_ppic.png' : $ppic ?>" alt="user image" class="rounded-full w-6 h-6"></a>
			<div class="user_info">
				<p class="user_name"><?= htmlentities($nym) ?></p>
				<p class="user_address"><?= strlen($user) > 17 ? substr(htmlspecialchars($user), 0, 17) . "..." : htmlspecialchars($user) ?></p>
			</div>
			<div class="icons">
				<button class="logout_button" onclick="window.location.href='/functions/logout.php';">
					<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M11.8766 2.31856C11.5384 2.33448 11.1024 2.39202 10.4654 2.47697L6.07073 3.06292C5.8229 3.09596 5.74811 3.1068 5.68998 3.12071C5.13911 3.25249 4.73161 3.71792 4.6738 4.28137C4.6677 4.34083 4.66683 4.4164 4.66683 4.66643C4.66683 5.03462 4.36836 5.3331 4.00017 5.3331C3.63198 5.3331 3.3335 5.03462 3.3335 4.66643V4.63223C3.33345 4.4313 3.33343 4.28178 3.34743 4.14528C3.46305 3.01838 4.27804 2.08751 5.37978 1.82396C5.51323 1.79204 5.66143 1.77231 5.86061 1.7458L10.3184 1.15143C10.919 1.07134 11.4118 1.00562 11.8139 0.986703C12.2292 0.96717 12.6159 0.992543 12.9854 1.1395C13.5538 1.36546 14.0271 1.77988 14.3262 2.31332C14.5207 2.66024 14.597 3.04017 14.6325 3.45438C14.6668 3.85547 14.6668 4.35263 14.6668 4.95853V11.041C14.6668 11.6469 14.6668 12.144 14.6325 12.5452C14.597 12.9594 14.5207 13.3393 14.3262 13.6862C14.0271 14.2196 13.5538 14.6341 12.9854 14.86C12.6159 15.007 12.2292 15.0324 11.8139 15.0128C11.4118 14.9939 10.919 14.9282 10.3184 14.8481L5.86051 14.2537C5.66147 14.2272 5.51318 14.2075 5.37978 14.1756C4.27804 13.912 3.46305 12.9812 3.34743 11.8542C3.33343 11.7178 3.33345 11.5682 3.3335 11.3673V11.3331C3.3335 10.9649 3.63198 10.6664 4.00017 10.6664C4.36836 10.6664 4.66683 10.9649 4.66683 11.3331C4.66683 11.5832 4.6677 11.6587 4.6738 11.7182C4.73161 12.2816 5.13911 12.747 5.68998 12.8788C5.7481 12.8927 5.82291 12.9036 6.07074 12.9366L10.4654 13.5226C11.1024 13.6075 11.5384 13.665 11.8766 13.681C12.2082 13.6966 12.377 13.6671 12.4928 13.621C12.777 13.5081 13.0136 13.3008 13.1632 13.0342C13.2242 12.9254 13.2756 12.762 13.304 12.4312C13.333 12.0939 13.3335 11.6542 13.3335 11.0114V4.98808C13.3335 4.34536 13.333 3.90564 13.304 3.56829C13.2756 3.23757 13.2242 3.07416 13.1632 2.96539C13.0136 2.69867 12.777 2.49146 12.4928 2.37848C12.377 2.3324 12.2082 2.30296 11.8766 2.31856Z" fill="#D0BED8" />
						<path d="M7.52876 4.86176C7.7891 4.60142 8.21123 4.60142 8.47156 4.86176L11.1382 7.52844C11.3986 7.78877 11.3986 8.2109 11.1382 8.47124L8.47156 11.1379C8.21123 11.3982 7.7891 11.3982 7.52876 11.1379C7.26843 10.8776 7.26843 10.4554 7.52876 10.1951L9.05736 8.6665H2.00016C1.63198 8.6665 1.3335 8.36804 1.3335 7.99984C1.3335 7.63164 1.63198 7.33317 2.00016 7.33317H9.05736L7.52876 5.80458C7.26843 5.54422 7.26843 5.12212 7.52876 4.86176Z" fill="#D0BED8" />
					</svg>
				</button>
				<button class="accounts_button" onclick="window.location.href='/functions/settings.php';">
					<svg width="16" height="16" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#e5e7eb">
						<path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.107-1.204l-.527-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z" />
						<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
					</svg>
				</button>
			</div>
		</div>
		<nav class="nav_buttons">
			<button class="nav_item" onclick="window.location.href='/account';">
				<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path opacity="0.4" d="M14.1667 7.5L9.63808 12.0286C9.47308 12.1936 9.39058 12.2761 9.29542 12.307C9.21175 12.3343 9.12158 12.3343 9.03792 12.307C8.94275 12.2761 8.86025 12.1936 8.69525 12.0286L7.13808 10.4714C6.97307 10.3064 6.89056 10.2239 6.79542 10.193C6.71174 10.1657 6.62159 10.1657 6.53791 10.193C6.44278 10.2239 6.36027 10.3064 6.19526 10.4714L2.5 14.1667M14.1667 7.5H10.8333M14.1667 7.5V10.8333" stroke="white" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
					<path d="M2.5 6.5C2.5 5.09987 2.5 4.3998 2.77248 3.86503C3.01217 3.39462 3.39462 3.01217 3.86503 2.77248C4.3998 2.5 5.09987 2.5 6.5 2.5H13.5C14.9002 2.5 15.6002 2.5 16.135 2.77248C16.6054 3.01217 16.9878 3.39462 17.2275 3.86503C17.5 4.3998 17.5 5.09987 17.5 6.5V13.5C17.5 14.9002 17.5 15.6002 17.2275 16.135C16.9878 16.6054 16.6054 16.9878 16.135 17.2275C15.6002 17.5 14.9002 17.5 13.5 17.5H6.5C5.09987 17.5 4.3998 17.5 3.86503 17.2275C3.39462 16.9878 3.01217 16.6054 2.77248 16.135C2.5 15.6002 2.5 14.9002 2.5 13.5V6.5Z" stroke="white" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
				</svg>
				Dashboard
			</button>
			<button class="nav_item" onclick="window.location.href='/';">
				<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path opacity="0.12" d="M10.0003 18.3337C14.6027 18.3337 18.3337 14.6027 18.3337 10.0003C18.3337 5.39795 14.6027 1.66699 10.0003 1.66699C5.39795 1.66699 1.66699 5.39795 1.66699 10.0003C1.66699 14.6027 5.39795 18.3337 10.0003 18.3337Z" fill="#D0BED8" />
					<path d="M13.3337 10.0003L10.0003 6.66699M10.0003 6.66699L6.66699 10.0003M10.0003 6.66699V14.3337C10.0003 15.4926 10.0003 16.0721 10.4591 16.7208C10.7639 17.1519 11.6415 17.6839 12.1647 17.7548C12.9521 17.8615 13.2511 17.7055 13.8492 17.3936C16.5142 16.0033 18.3337 13.2143 18.3337 10.0003C18.3337 5.39795 14.6027 1.66699 10.0003 1.66699C5.39795 1.66699 1.66699 5.39795 1.66699 10.0003C1.66699 13.0848 3.34282 15.7779 5.83366 17.2188" stroke="#D0BED8" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
				</svg>
				Free &amp; profile picture uploads
			</button>
			<button class="nav_item" onclick="window.location.href='/account/ai';">
				<svg class="text-[#f79413]" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bot">
					<path d="M12 8V4H8" />
					<rect width="16" height="12" x="4" y="8" rx="2" />
					<path d="M2 14h2" />
					<path d="M20 14h2" />
					<path d="M15 13v2" />
					<path d="M9 13v2" />
				</svg>
				AI Studio
				<span class="inline-flex items-center rounded-full bg-nbpurple-200 px-1.5 py-0.5 text-xs font-medium text-nbpurple-800 ring-1 ring-inset ring-nbpurple-700/10">New</span>
			</button>

			<?php
			// Menu items for Creators and Admins
			// Menu items for all verified accounts, and moderators
			if ($perm->validatePermissionsLevelAny(1, 2, 3, 4, 10) || $perm->hasPrivilege('canModerate') || $perm->isAdmin()) :
			?>

				<button class="nav_item" onclick="window.location.href='/viewall/'; ">
					<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
						<g clip-path="url(#clip0_419_1449)">
							<path opacity="0.4" d="M7.08317 8.74984C8.00365 8.74984 8.74985 8.00365 8.74985 7.08317C8.74985 6.1627 8.00365 5.4165 7.08317 5.4165C6.1627 5.4165 5.4165 6.1627 5.4165 7.08317C5.4165 8.00365 6.1627 8.74984 7.08317 8.74984Z" stroke="#C449F3" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
							<path d="M4.99949 16.6668L12.3903 9.276C12.7203 8.946 12.8854 8.78092 13.0757 8.71917C13.243 8.66475 13.4233 8.66475 13.5907 8.71917C13.7809 8.78092 13.946 8.946 14.276 9.276L17.8376 12.8376M18.3332 9.99984C18.3332 14.6022 14.6022 18.3332 9.99984 18.3332C5.39746 18.3332 1.6665 14.6022 1.6665 9.99984C1.6665 5.39746 5.39746 1.6665 9.99984 1.6665C14.6022 1.6665 18.3332 5.39746 18.3332 9.99984Z" stroke="#C449F3" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
						</g>
						<defs>
							<clipPath id="clip0_419_1449">
								<rect width="20" height="20" fill="white" />
							</clipPath>
						</defs>
					</svg>
					View All - free images
				</button>
			<?php
			endif;
			// Menu items for Admins only
			if ($perm->isAdmin()) :
			?>

				<button type="button" class="nav_item" data-toggle="modal" data-target="#myModal0" id="myModal0">** Admin Resources</button>

				<!-- The Modal -->
				<div id="myModal0" class="modal">

					<!-- Modal content -->
					<div class="modal-content" style="color: #fff;">
						&ensp;<a href="admin/newacct.php" target="_blank">New Accounts</a><BR>
						&ensp;<a href="admin/profilepics.php" target="_blank">Profile Pics</a><BR>
						&ensp;<a href="admin/list_db.php" target="_blank">Database Tables</a><BR>
						&ensp;<a href="admin/update_db.php" target="_blank">Update Database Tables</a><BR>
						&ensp;<a href="admin/allmediauploads.php" target="_blank">All Media uploaded</a><BR>
						&ensp;<a href="admin/approve.php" target="_blank">Approve Content</a><BR>
						&ensp;<a href="admin/stats.php" target="_blank">Free uploads stats</a><BR>
						&ensp;<a href="admin/account_stats.php" target="_blank">Account stats</a><BR>
						&ensp;<a href="admin/promo.php" target="_blank">Manage Promotions</a><BR>
						&ensp;<a href="https://btcpay.nostr.build" target="_blank">BTCPay Server</a><BR>
					</div>
				</div>
			<?php
			// Menu items for moderators
			elseif ($perm->hasPrivilege('canModerate')) :
			?>
				&ensp;&ensp;&ensp;<a class="nav_item" style="color: #fff;" href="admin/approve.php" target="_blank">** Admin Content Review **</a><BR>
			<?php
			endif;

			$divCount = 0;
			$foldercount = $usersFoldersStats['TOTAL']['folderCount'];
			?>
		</nav>

		<div class="folder_info">
			<p>Your folders (<span> <?= $foldercount ?? 0 ?> </span>)</p>
			<button class="edit_folder_button" onclick="window.location.href='?editfolder=true';">
				<svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
					<g clip-path="url(#clip0_419_1457)">
						<path d="M10.4997 5.83318L8.16636 3.49985M1.45801 12.5415L3.43222 12.3222C3.67343 12.2954 3.79402 12.2819 3.90675 12.2455C4.00676 12.2131 4.10194 12.1674 4.18969 12.1095C4.2886 12.0442 4.37441 11.9585 4.54602 11.7869L12.2497 4.08318C12.894 3.43885 12.894 2.39418 12.2497 1.74985C11.6053 1.10552 10.5607 1.10552 9.91636 1.74985L2.21268 9.45353C2.04108 9.62514 1.95527 9.71089 1.89005 9.80983C1.83218 9.89762 1.78643 9.99276 1.75406 10.0928C1.71757 10.2055 1.70416 10.3261 1.67736 10.5673L1.45801 12.5415Z" stroke="url(#paint0_linear_419_1457)" stroke-width="1.16667" stroke-linecap="round" stroke-linejoin="round" />
					</g>
					<defs>
						<linearGradient id="paint0_linear_419_1457" x1="7.09548" y1="1.2666" x2="7.09548" y2="12.5415" gradientUnits="userSpaceOnUse">
							<stop stop-color="#2EDF95" />
							<stop offset="1" stop-color="#07847C" />
						</linearGradient>
						<clipPath id="clip0_419_1457">
							<rect width="14" height="14" fill="white" />
						</clipPath>
					</defs>
				</svg>
				Edit
			</button>

			<button class="done_folder_button">
				<svg width="12" height="9" viewBox="0 0 12 9" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path fill-rule="evenodd" clip-rule="evenodd" d="M11.8047 0.528756C12.0651 0.789109 12.0651 1.21122 11.8047 1.47157L4.47141 8.8049C4.21105 9.06523 3.78895 9.06523 3.52859 8.8049L0.19526 5.47156C-0.0650867 5.21123 -0.0650867 4.7891 0.19526 4.52876C0.455613 4.26843 0.87772 4.26843 1.13807 4.52876L4 7.3907L10.8619 0.528756C11.1223 0.268409 11.5444 0.268409 11.8047 0.528756Z" fill="url(#paint0_linear_307_2696)" />
					<defs>
						<linearGradient id="paint0_linear_307_2696" x1="5.99999" y1="0.333496" x2="5.99999" y2="9.00015" gradientUnits="userSpaceOnUse">
							<stop stop-color="#2EDF95" />
							<stop offset="1" stop-color="#07847C" />
						</linearGradient>
					</defs>
				</svg>
				Done
			</button>

		</div>
		<section class="folder_section">
			<ul class="drag-sort-enable folders">
				<li class="folder new_folder" id="myBtn">
					<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path opacity="0.4" d="M10.8332 5.83333L9.90359 3.9741C9.636 3.439 9.50225 3.17144 9.30267 2.97597C9.12617 2.80311 8.91342 2.67164 8.67992 2.59109C8.41584 2.5 8.11668 2.5 7.51841 2.5H4.33317C3.39975 2.5 2.93304 2.5 2.57652 2.68166C2.26291 2.84144 2.00795 3.09641 1.84816 3.41002C1.6665 3.76653 1.6665 4.23325 1.6665 5.16667V5.83333" stroke="white" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
						<path d="M9.99984 14.1668V9.16683M7.49984 11.6668H12.4998M1.6665 5.8335H14.3332C15.7333 5.8335 16.4333 5.8335 16.9682 6.10598C17.4386 6.34566 17.821 6.72811 18.0607 7.19852C18.3332 7.7333 18.3332 8.43333 18.3332 9.8335V13.5002C18.3332 14.9003 18.3332 15.6003 18.0607 16.1352C17.821 16.6056 17.4386 16.988 16.9682 17.2277C16.4333 17.5002 15.7333 17.5002 14.3332 17.5002H5.6665C4.26637 17.5002 3.5663 17.5002 3.03153 17.2277C2.56112 16.988 2.17867 16.6056 1.93899 16.1352C1.6665 15.6003 1.6665 14.9003 1.6665 13.5002V5.8335Z" stroke="white" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
					</svg>

					<!-- Trigger/Open The Modal -->
					<button id="myBtn">New Folder</button>

				</li>

				<!-- The Modal -->
				<div id="myModal" class="modal" style="display: none;">

					<!-- Modal content -->
					<div class="modal-content">
						<span class="close" style="color: #fff;">&times;</span>
						<div>
							<input id="folderName" placeholder="Folder name" />
							<button onclick="createFolder();" style="color: #fff;">
								Create
							</button>
						</div>
					</div>
				</div>
				</div>


				<li class="folder" onclick="window.location.href='/account';">
					<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path opacity="0.4" d="M10.8332 5.83333L9.90359 3.9741C9.636 3.439 9.50225 3.17144 9.30267 2.97597C9.12617 2.80311 8.91342 2.67164 8.67992 2.59109C8.41584 2.5 8.11668 2.5 7.51841 2.5H4.33317C3.39975 2.5 2.93304 2.5 2.57652 2.68166C2.26291 2.84144 2.00795 3.09641 1.84816 3.41002C1.6665 3.76653 1.6665 4.23325 1.6665 5.16667V5.83333" stroke="#D0BED8" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
						<path d="M7.9165 9.5835L12.0832 13.7502M12.0832 9.5835L7.9165 13.7502M1.6665 5.8335H14.3332C15.7333 5.8335 16.4333 5.8335 16.9682 6.10598C17.4386 6.34566 17.821 6.72811 18.0607 7.19852C18.3332 7.7333 18.3332 8.43333 18.3332 9.8335V13.5002C18.3332 14.9003 18.3332 15.6003 18.0607 16.1352C17.821 16.6056 17.4386 16.988 16.9682 17.2277C16.4333 17.5002 15.7333 17.5002 14.3332 17.5002H5.6665C4.26637 17.5002 3.5663 17.5002 3.03153 17.2277C2.56112 16.988 2.17867 16.6056 1.93899 16.1352C1.6665 15.6003 1.6665 14.9003 1.6665 13.5002V5.8335Z" stroke="#D0BED8" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
					</svg>
					<p class="folder_name"><a href="/account">No folder</a></p>
					<span><?= $usersFoldersStats['FOLDERS']['/']['fileCount'] ?></span>
					<span><?= formatSizeUnits($usersFoldersStats['FOLDERS']['/']['totalSize']) ?></span>
					<div class="folder_icons">
						<svg class="delete_folder" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path opacity="0.12" d="M12.1993 11.0129L12.6668 4H3.3335L3.80102 11.0129C3.87116 12.065 3.90624 12.5911 4.13348 12.99C4.33356 13.3412 4.63534 13.6235 4.99906 13.7998C5.41218 14 5.93944 14 6.99396 14H9.00636C10.0609 14 10.5882 14 11.0013 13.7998C11.365 13.6235 11.6668 13.3412 11.8668 12.99C12.0941 12.5911 12.1292 12.065 12.1993 11.0129Z" fill="#A58EAD" />
							<path d="M6 2H10M2 4H14M12.6667 4L12.1991 11.0129C12.129 12.065 12.0939 12.5911 11.8667 12.99C11.6666 13.3412 11.3648 13.6235 11.0011 13.7998C10.588 14 10.0607 14 9.0062 14H6.9938C5.93927 14 5.41202 14 4.99889 13.7998C4.63517 13.6235 4.33339 13.3412 4.13332 12.99C3.90607 12.5911 3.871 12.065 3.80086 11.0129L3.33333 4M6.66667 7V10.3333M9.33333 7V10.3333" stroke="#A58EAD" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round" />
						</svg>
						<svg class="arrange_folder" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
							<g opacity="0.8" clip-path="url(#clip0_307_2617)">
								<path d="M13.3337 6H2.66699V7.33333H13.3337V6ZM2.66699 10H13.3337V8.66667H2.66699V10Z" fill="#A58EAD" />
							</g>
							<defs>
								<clipPath id="clip0_307_2617">
									<rect width="16" height="16" fill="white" />
								</clipPath>
							</defs>
						</svg>
					</div>
				</li>
				<?php
				// Display folders
				foreach ($usersFoldersStats['FOLDERS'] as $folder => $folderStats) :
					if ($folder == '/') continue;
				?>
					<li type="button" class="folder" onclick="folderClicked('<?= htmlspecialchars($folder) ?>' , '<?= $usersFoldersStats['TOTAL']['fileCount'] ?>');">
						<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path opacity="0.4" d="M10.8332 5.83333L9.90359 3.9741C9.636 3.43899 9.50225 3.17144 9.30267 2.97597C9.12617 2.80311 8.91342 2.67164 8.67992 2.59109C8.41584 2.5 8.11668 2.5 7.51841 2.5H4.33317C3.39975 2.5 2.93304 2.5 2.57652 2.68166C2.26291 2.84144 2.00795 3.09641 1.84816 3.41002C1.6665 3.76653 1.6665 4.23324 1.6665 5.16667V5.83333" stroke="#D0BED8" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
							<path d="M1.6665 5.8335H14.3332C15.7333 5.8335 16.4333 5.8335 16.9682 6.10598C17.4386 6.34566 17.821 6.72811 18.0607 7.19852C18.3332 7.7333 18.3332 8.43333 18.3332 9.8335V13.5002C18.3332 14.9003 18.3332 15.6003 18.0607 16.1352C17.821 16.6056 17.4386 16.988 16.9682 17.2277C16.4333 17.5002 15.7333 17.5002 14.3332 17.5002H5.6665C4.26637 17.5002 3.5663 17.5002 3.03153 17.2277C2.56112 16.988 2.17867 16.6056 1.93899 16.1352C1.6665 15.6003 1.6665 14.9003 1.6665 13.5002V5.8335Z" stroke="#D0BED8" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
						</svg>
						<p class="folder_name"><a ondblclick="folderDoubleClicked('<?= $folder ?>');" onclick="folderClicked('<?= $folder ?>', '<?= $usersFoldersStats['TOTAL']['fileCount'] ?>');"><?= $folder ?></a></p>
						<span> <?= $folderStats['fileCount'] ?> </span>
						<span> <?= formatSizeUnits($folderStats['totalSize']) ?> </span>
						<div class="folder_icons">
							<svg class="delete_folder" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path opacity="0.12" d="M12.1993 11.0129L12.6668 4H3.3335L3.80102 11.0129C3.87116 12.065 3.90624 12.5911 4.13348 12.99C4.33356 13.3412 4.63534 13.6235 4.99906 13.7998C5.41218 14 5.93944 14 6.99396 14H9.00636C10.0609 14 10.5882 14 11.0013 13.7998C11.365 13.6235 11.6668 13.3412 11.8668 12.99C12.0941 12.5911 12.1292 12.065 12.1993 11.0129Z" fill="#A58EAD" />
								<path d="M6 2H10M2 4H14M12.6667 4L12.1991 11.0129C12.129 12.065 12.0939 12.5911 11.8667 12.99C11.6666 13.3412 11.3648 13.6235 11.0011 13.7998C10.588 14 10.0607 14 9.0062 14H6.9938C5.93927 14 5.41202 14 4.99889 13.7998C4.63517 13.6235 4.33339 13.3412 4.13332 12.99C3.90607 12.5911 3.871 12.065 3.80086 11.0129L3.33333 4M6.66667 7V10.3333M9.33333 7V10.3333" stroke="#A58EAD" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round" />
							</svg>
							<svg class="arrange_folder" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
								<g opacity="0.8" clip-path="url(#clip0_307_2617)">
									<path d="M13.3337 6H2.66699V7.33333H13.3337V6ZM2.66699 10H13.3337V8.66667H2.66699V10Z" fill="#A58EAD" />
								</g>
								<defs>
									<clipPath id="clip0_307_2617">
										<rect width="16" height="16" fill="white" />
									</clipPath>
								</defs>
							</svg>
						</div>
					</li>
				<?php
				endforeach; // Flder list loop end
				?>
			</ul>
			<!-- Empty buffer to unbreak mobile display -->
			<div class="block h-24 w-auto"></div>
		</section>
	</aside>
	<main>
		<header>
			<svg class="menu_button" width="20" height="15" viewBox="0 0 20 15" fill="none" xmlns="http://www.w3.org/2000/svg">
				<path d="M1 7.5H19M1 1.5H19M1 13.5H19" stroke="#A58EAD" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
			</svg>
			<h2 class="header_title">Dashboard</h2>

			<?php
			// doesn't print upload button if user is over their limit
			if (!$userOverLimit && !$userAccountExpired) :
			?>
				<button id="open-account-url-upload-button" type="button" class="inline-flex items-center gap-x-2 rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600" @click="url_up_open = true">
					<span class="hidden sm:inline">Import URL</span>
					<svg class="self-center -mr-0.5 h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
						<path d="M12.232 4.232a2.5 2.5 0 013.536 3.536l-1.225 1.224a.75.75 0 001.061 1.06l1.224-1.224a4 4 0 00-5.656-5.656l-3 3a4 4 0 00.225 5.865.75.75 0 00.977-1.138 2.5 2.5 0 01-.142-3.667l3-3z" />
						<path d="M11.603 7.963a.75.75 0 00-.977 1.138 2.5 2.5 0 01.142 3.667l-3 3a2.5 2.5 0 01-3.536-3.536l1.225-1.224a.75.75 0 00-1.061-1.06l-1.224 1.224a4 4 0 105.656 5.656l3-3a4 4 0 00-.225-5.865z" />
					</svg>
				</button>
				<button id="open-account-dropzone-button" type="button" class="inline-flex items-center gap-x-2 rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
					<span class="hidden sm:inline">Upload Media</span>
					<svg class="self-center -mr-0.5 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
					</svg>
				</button>
				<div id="files-account-dropzone"></div>
			<?php endif; ?>
		</header>

		<?php
		if ($perm->validatePermissionsLevelAny(1, 10, 99) && $usersFoldersStats['TOTAL']['publicCount'] > 0) :
		?>
			<section class="subheader p-2 text-center">
				<!--
				<p class="header_subtitle inline">You have <?= $usersFoldersStats['TOTAL']['publicCount'] ?> media files marked as public showing your Creator Page.</p>
		-->

				<!-- Copy link to clipboard button -->
				<div class="inline-flex rounded-md shadow-sm ml-0 bg-transparent" x-data="{ open: false }">
					<button @click="window.location.href = '/creators/creator/?user=<?= $userId ?>'" type="button" class="relative inline-flex items-center rounded-l-md bg-gradient-to-br from-[#399dfa] to-white px-3 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-[#399dfaec] focus:z-10">Visit Your Creator Page with <?= $usersFoldersStats['TOTAL']['publicCount'] ?> shared files</button>
					<div class="relative -ml-px block">
						<button @click="open = !open" type="button" class="relative inline-flex items-center rounded-r-md bg-[#399dfa] px-2 py-2 text-gray-800 ring-1 ring-inset ring-gray-300 hover:bg-[#399dfaec] focus:z-10" id="option-menu-button" :aria-expanded="open.toString()" aria-haspopup="true">
							<span class="sr-only">Open options</span>
							<svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
								<path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
							</svg>
						</button>

						<div x-show="open" x-transition:enter="transition ease-out duration-100" x-cloak x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100" x-transition:leave-end="transform opacity-0 scale-95" @click.away="open = false" class="absolute right-0 z-10 -mr-1 mt-2 w-56 origin-top-right rounded-md bg-gradient-to-br from-[#399dfa] to-white hover:bg-[#399dfaec] shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none" role="menu" aria-orientation="vertical" aria-labelledby="option-menu-button" tabindex="-1">
							<div class="py-1" role="none">
								<!-- Menu item to copy the link -->
								<button @click="buttonText = 'Link Copied'; setTimeout(() => { buttonText = 'Copy Link' }, 2000); navigator.clipboard.writeText('https://<?= $_SERVER['HTTP_HOST'] ?>/creators/creator/?user=<?= $userId ?>')" x-text="buttonText" class="text-gray-700 block px-4 py-2 text-sm" role="menuitem" tabindex="-1" id="option-menu-item-0" x-data="{ buttonText: 'Copy Link' }">Copy Link</button>
							</div>
						</div>
					</div>
				</div>
			</section>

		<?php
		endif;
		?>


		<section class="dashboard_section">
			<h3 class="dashboard_title">Welcome <b><?= $nym ?? 'anon' ?><?= $userAccountExpired ? '(EXPIRED)' : '' ?></b>!
				<?php
				/*
				echo SiteConfig::getAccountType($acctlevel) . ', ';
				echo '<b>' . ($nym ?? 'anon') . '</b>';
				*/
				?>
				<?php if ($showRenewalButton) : ?>
					<button @click="window.location.href='/plans/'" type="button" class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
						<svg class="-ml-0.5 h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
							<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
						</svg>
						Renew
					</button>
				<?php elseif ($showUpgradeButton) : ?>
					<button @click="window.location.href='/plans/'" type="button" class="inline-flex items-center gap-x-1.5 rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
						<svg class="-ml-0.5 h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
							<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
						</svg>
						Upgrade
					</button>
				<?php endif; ?>
			</h3>
			<div class="dashboard_info">
				<div class="plan_data">
					<div class="plan_data_info">
						<h4>Plan data</h4>
						<p style="text-align: left;">
							<b>Remaining Days:</b> <?= $daysRemaining ?>
						</p>
						<p>
							<b><?= formatSizeUnits($usersFoldersStats['TOTAL']['totalSize']) ?></b> /
							<?= htmlentities(SiteConfig::getStorageLimitMessage($acctlevel)) ?>
							<?= $userOverLimit ? '<br><span style="color: red;">Max storage reached! Delete images or upgrade plan for more space.</span><br>' : '' ?>
						</p>
					</div>
					<div class="plan_data_graph">

						<div class="circular-progress" data-inner-circle-color="#1e1530" data-percentage="<?=
																																															$userOverLimit ? 100 : min(100, max(1, round(($storageUsed / $userStorageLimit) * 100)))
																																															?>" data-progress-color="rgba(46, 223, 149, 1)" data-bg-color="rgba(255, 255, 255, 0.16)">

							<div class="inner-circle"></div>
							<p class="percentage">0%</p>
						</div>

					</div>
				</div>
				<div class="activity_info">
					<div class="files_info">
						<h4>Files</h4>
						<p><?= $usersFoldersStats['TOTAL']['fileCount'] ?></p>
					</div>
					<ul class="list_details">
						<li>
							<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
								<g clip-path="url(#clip0_303_2210)">
									<path opacity="0.4" d="M7.08366 8.74984C8.00413 8.74984 8.75034 8.00365 8.75034 7.08317C8.75034 6.1627 8.00413 5.4165 7.08366 5.4165C6.16318 5.4165 5.41699 6.1627 5.41699 7.08317C5.41699 8.00365 6.16318 8.74984 7.08366 8.74984Z" stroke="#D0BED8" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
									<path d="M4.99998 16.6668L12.3908 9.276C12.7208 8.946 12.8859 8.78092 13.0762 8.71917C13.2435 8.66475 13.4238 8.66475 13.5912 8.71917C13.7814 8.78092 13.9465 8.946 14.2765 9.276L17.8381 12.8376M18.3337 9.99984C18.3337 14.6022 14.6027 18.3332 10.0003 18.3332C5.39795 18.3332 1.66699 14.6022 1.66699 9.99984C1.66699 5.39746 5.39795 1.6665 10.0003 1.6665C14.6027 1.6665 18.3337 5.39746 18.3337 9.99984Z" stroke="#D0BED8" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
								</g>
								<defs>
									<clipPath id="clip0_303_2210">
										<rect width="20" height="20" fill="white" />
									</clipPath>
								</defs>
							</svg>
							<p>Images</p>
							<span><?= $usersFoldersStats['TOTAL']['imageCount'] ?></span>
						</li>
						<li>
							<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path opacity="0.12" d="M1.66699 8.1665C1.66699 6.76637 1.66699 6.0663 1.93948 5.53153C2.17916 5.06112 2.56161 4.67867 3.03202 4.43899C3.56679 4.1665 4.26686 4.1665 5.66699 4.1665H10.167C11.5672 4.1665 12.2672 4.1665 12.802 4.43899C13.2724 4.67867 13.6548 5.06112 13.8945 5.53153C14.167 6.0663 14.167 6.76637 14.167 8.1665V11.8332C14.167 13.2333 14.167 13.9333 13.8945 14.4682C13.6548 14.9386 13.2724 15.321 12.802 15.5607C12.2672 15.8332 11.5672 15.8332 10.167 15.8332H5.66699C4.26686 15.8332 3.56679 15.8332 3.03202 15.5607C2.56161 15.321 2.17916 14.9386 1.93948 14.4682C1.66699 13.9333 1.66699 13.2333 1.66699 11.8332V8.1665Z" fill="#D0BED8" stroke="#D0BED8" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
								<path d="M18.3337 7.44258C18.3337 6.93773 18.3337 6.68532 18.2338 6.56843C18.1472 6.46701 18.0172 6.41318 17.8843 6.42365C17.7311 6.43571 17.5526 6.6142 17.1956 6.97118L14.167 9.99978L17.1956 13.0284C17.5526 13.3854 17.7311 13.5639 17.8843 13.5759C18.0172 13.5864 18.1472 13.5325 18.2338 13.4311C18.3337 13.3143 18.3337 13.0618 18.3337 12.5569V7.44258Z" stroke="#D0BED8" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
								<path d="M1.66699 8.1665C1.66699 6.76637 1.66699 6.0663 1.93948 5.53153C2.17916 5.06112 2.56161 4.67867 3.03202 4.43899C3.56679 4.1665 4.26686 4.1665 5.66699 4.1665H10.167C11.5672 4.1665 12.2672 4.1665 12.802 4.43899C13.2724 4.67867 13.6548 5.06112 13.8945 5.53153C14.167 6.0663 14.167 6.76637 14.167 8.1665V11.8332C14.167 13.2333 14.167 13.9333 13.8945 14.4682C13.6548 14.9386 13.2724 15.321 12.802 15.5607C12.2672 15.8332 11.5672 15.8332 10.167 15.8332H5.66699C4.26686 15.8332 3.56679 15.8332 3.03202 15.5607C2.56161 15.321 2.17916 14.9386 1.93948 14.4682C1.66699 13.9333 1.66699 13.2333 1.66699 11.8332V8.1665Z" stroke="#D0BED8" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
							</svg>
							<p>AV</p>
							<span><?= $usersFoldersStats['TOTAL']['avCount'] ?></span>
						</li>
						<li>
							<svg width="18" height="16" viewBox="0 0 18 16" fill="none" xmlns="http://www.w3.org/2000/svg">
								<g clip-path="url(#clip0_303_2245)">
									<path d="M16 1H2C0.895313 1 0 1.89531 0 3V13C0 14.1047 0.895313 15 2 15H16C17.1047 15 18 14.1047 18 13V3C18 1.89531 17.1031 1 16 1ZM16.5 13C16.5 13.2757 16.2757 13.5 16 13.5H2C1.72431 13.5 1.5 13.2757 1.5 13V3C1.5 2.72431 1.72431 2.5 2 2.5H16C16.2757 2.5 16.5 2.72431 16.5 3V13ZM14.5 5.125H11.75C11.4047 5.125 11.125 5.40478 11.125 5.75V10.25C11.125 10.5953 11.4048 10.875 11.75 10.875C12.0952 10.875 12.375 10.5952 12.375 10.25V8.625H14C14.3453 8.625 14.625 8.34522 14.625 8C14.625 7.65478 14.3438 7.375 14 7.375H12.375V6.375H14.5C14.8453 6.375 15.125 6.09522 15.125 5.75C15.125 5.40478 14.8438 5.125 14.5 5.125ZM9.5 5.125C9.15469 5.125 8.875 5.40478 8.875 5.75V10.25C8.875 10.5953 9.15478 10.875 9.5 10.875C9.84522 10.875 10.125 10.5952 10.125 10.25V5.75C10.125 5.37813 9.84375 5.125 9.5 5.125ZM7.5 7.625H5.7125C5.36719 7.625 5.0875 7.90478 5.0875 8.25C5.0875 8.59522 5.36728 8.875 5.7125 8.875H6.875V9.30406C6.23531 9.7625 5.15 9.69812 4.60094 9.14878C4.29375 8.84062 4.125 8.43437 4.125 8C4.125 7.56563 4.29394 7.15812 4.60094 6.85094C5.21531 6.23625 6.24781 6.23625 6.86219 6.85094C7.10584 7.09506 7.50125 7.09506 7.74594 6.85094C7.99006 6.60728 7.99006 6.21125 7.74594 5.96719C6.66 4.88125 4.80312 4.88031 3.71781 5.96719C3.175 6.50938 2.875 7.23125 2.875 8C2.875 8.76875 3.17431 9.48969 3.71719 10.0328C4.25312 10.5687 5.02187 10.875 5.82812 10.875C6.63531 10.875 7.40531 10.5679 7.94031 10.0328C8.05937 9.91562 8.125 9.75625 8.125 9.59062V8.25C8.125 7.87813 7.84375 7.625 7.5 7.625Z" fill="#D0BED8" />
								</g>
								<defs>
									<clipPath id="clip0_303_2245">
										<rect width="18" height="16" fill="white" />
									</clipPath>
								</defs>
							</svg>
							<p>GIFs</p>
							<span><?= $usersFoldersStats['TOTAL']['gifCount'] ?></span>
						</li>
					</ul>
				</div>
				<!-- <div class="activity">
						<div class="activity_graph">
							<h4>Activity</h4>
						</div>
					</div> -->
			</div>
			<h4 class="dashboard_subtitles">My media</h4>
			<section class="image_container">
				<?php
				// Display folders
				if (isset($_GET['editfolder'])) :
					// Display list of folders for editing
					foreach ($usersFoldersStats['FOLDERS'] as $folder => $folderStats) :
						if ($folder == '/') continue;
				?>
						<div style="margin:auto; margin-bottom: 1.25rem; color: #fff;">
							<a onclick="folderClicked('<?= htmlspecialchars($folder) ?>');" ondblclick="folderDoubleClicked('<?= htmlspecialchars($folder) ?>' , '<?= $usersFoldersStats['TOTAL']['fileCount'] ?>');">
								<svg class="w-24 h-24" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
									<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
								</svg>
							</a>
							<div style="display: flex; justify-content: space-between; margin: 0 0.75rem 0 0.75rem">
								<label><?= htmlentities($folder) ?></label>
								<input class="folder_select_checkbox" data-folder-id="<?= $folderStats['id'] ?>" type="checkbox" id="cb<?= $folderStats['id'] ?>" style="width: 2rem; margin-left: 1rem;" onclick="checkboxClicked('<?= $folderStats['id'] ?>' , 0)" />
							</div>
						</div>
					<?php
					endforeach;
				else :
					// Display image grid
					$images_sql = "SELECT * FROM users_images WHERE usernpub=? AND image != 'https://nostr.build/p/Folder.png' ORDER BY id DESC";
					$images_stmt = $link->prepare($images_sql);
					$images_stmt->bind_param("s", $_SESSION['usernpub']);
					$images_stmt->execute();
					$images_result = $images_stmt->get_result();

					// TODO: This is definetly going away, just a temp blaspheme until rewrite
					// There is nothing more permanent tham a temporary solution ;)
					$i = 0;
					$divId = 0;

					while ($images_row = $images_result->fetch_assoc()) :
						// Get mime type and image URL
						$type = explode('/', $images_row['mime_type'])[0];
						$image = $images_row['image'];

						// Parse URL and get only the filename
						$parsed_url = parse_url($image);
						$filename = pathinfo($parsed_url['path'], PATHINFO_BASENAME);

						// Add 'professional_account_' prefix to the $type
						$professional_type = 'professional_account_' . $type;

						// Use SiteConfig to get the base URL for this type
						try {
							$base_url = SiteConfig::getFullyQualifiedUrl($professional_type);
						} catch (Exception $e) {
							// Handle exception or use a default URL
							$base_url = SiteConfig::ACCESS_SCHEME . "://" . SiteConfig::DOMAIN_NAME . "/p/"; // default URL in case of error
						}

						$new_url = $base_url . $filename;
						$image_url = htmlspecialchars($new_url);
						$thumb_url = htmlspecialchars(SiteConfig::getThumbnailUrl($professional_type) . $filename);

						$element = "element" . $i;
					?>
						<div style="margin:auto; margin-bottom: 1.25rem; <?= ($images_row['folder'] != null ? "display: none;" : "") ?>" id="div<?= $divId ?>">
							<input id="input<?= $divId ?>" value="<?= htmlentities($images_row['folder']) ?>" style="display: none;" />
							<figure class="image_card">
								<?php if ($type == 'image') : ?>
									<img id="<?= $element ?>" x-data="{ src: '<?= $thumb_url ?>'}" x-intersect.margin.500px="$el.src = src" data-src="<?= $image_url ?>" alt="" class="image <?= $images_row['flag'] ? 'publicly-shared' : '' ?>" />
								<?php elseif ($type == 'video') : ?>
									<video id="img<?= $element ?>" x-data="{ src: '<?= $thumb_url ?>'}" x-intersect.margin.500px="$el.src = src, $nextTick(() => $el.load())" alt="" class="image <?= $images_row['flag'] ? 'publicly-shared' : '' ?>" preload="auto" controls>
										<source id="<?= $element ?>" type="<?= $images_row['mime_type'] ?>" data-src="<?= $image_url ?>">
									</video>
								<?php elseif ($type == 'audio') : ?>
									<audio id="img<?= $element ?>" x-data="{ src: '<?= $thumb_url ?>'}" x-intersect.margin.500px="$el.src = src, $nextTick(() => $el.load())" alt="" class="image <?= $images_row['flag'] ? 'publicly-shared' : '' ?>" controls>
										<source id="<?= $element ?>" type="<?= $images_row['mime_type'] ?>" data-src="<?= $image_url ?>">
									</audio>
								<?php endif; ?>


								<button class="delete_button">
									<input class="media_select_checkbox" type="checkbox" id="cb<?= $images_row['id'] ?>" onclick="checkboxClicked('<?= $images_row['id'] ?>' , 1)" />
								</button>

								<button class="copy_link" id="bt<?= $element ?>" onclick="copyToClipboard('<?= $element ?>')">
									<svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path fill-rule="evenodd" clip-rule="evenodd" d="M8.51692 2.33379C9.27132 1.60516 10.2817 1.20198 11.3305 1.21109C12.3793 1.2202 13.3825 1.64088 14.1242 2.38252C14.8658 3.12414 15.2864 4.12739 15.2956 5.17618C15.3047 6.22497 14.9015 7.2354 14.1729 7.9898L14.1648 7.99807L12.1648 9.99793C11.7594 10.4036 11.2713 10.7173 10.7339 10.9178C10.1965 11.1183 9.62232 11.2008 9.05018 11.1598C8.47805 11.1189 7.92145 10.9553 7.41812 10.6803C6.91472 10.4053 6.47642 10.0253 6.13286 9.56593C5.91232 9.27113 5.97256 8.85333 6.26739 8.6328C6.56222 8.41227 6.97998 8.47247 7.20058 8.76733C7.42958 9.07353 7.72178 9.32687 8.05738 9.5102C8.39292 9.69353 8.76405 9.8026 9.14545 9.82993C9.52685 9.8572 9.90965 9.8022 10.2679 9.66853C10.6262 9.53487 10.9515 9.32574 11.2219 9.05534L13.2177 7.05956C13.701 6.55705 13.9684 5.88514 13.9623 5.18777C13.9562 4.48858 13.6758 3.81974 13.1814 3.32532C12.6869 2.8309 12.0181 2.55045 11.3189 2.54438C10.6213 2.53831 9.94905 2.80592 9.44645 3.28967L8.30338 4.42609C8.04232 4.68568 7.62018 4.68445 7.36058 4.42334C7.10098 4.16224 7.10225 3.74012 7.36332 3.48054L8.50998 2.34054L8.51692 2.33379Z" fill="#D0BED8" />
										<path fill-rule="evenodd" clip-rule="evenodd" d="M5.26591 6.08234C5.80332 5.88187 6.37755 5.79932 6.94969 5.8403C7.52175 5.88127 8.07842 6.0448 8.58175 6.31981C9.08509 6.59482 9.52342 6.97486 9.86702 7.43417C10.0876 7.72897 10.0273 8.14677 9.73249 8.3673C9.43762 8.58784 9.01982 8.52764 8.79928 8.23277C8.57029 7.92657 8.27802 7.67324 7.94249 7.4899C7.60689 7.30657 7.23582 7.19757 6.85442 7.17024C6.47301 7.1429 6.09019 7.19797 5.73191 7.33157C5.37365 7.46524 5.04831 7.67437 4.77797 7.94477L2.78221 9.94057C2.29885 10.4431 2.03148 11.115 2.03754 11.8124C2.04361 12.5116 2.32407 13.1804 2.81849 13.6748C3.31291 14.1692 3.98174 14.4497 4.68093 14.4558C5.37829 14.4618 6.05021 14.1944 6.55272 13.7111L7.68842 12.5754C7.94875 12.315 8.37089 12.315 8.63122 12.5754C8.89155 12.8358 8.89155 13.2578 8.63122 13.5182L7.49122 14.6582L7.48295 14.6664C6.72855 15.395 5.71813 15.7982 4.66935 15.789C3.62056 15.7799 2.61731 15.3592 1.87568 14.6176C1.13405 13.876 0.713366 12.8727 0.704253 11.824C0.695139 10.7752 1.09832 9.76477 1.82695 9.01037L1.83507 9.0021L3.83499 7.00216C3.83496 7.00219 3.83501 7.00213 3.83499 7.00216C4.24048 6.59655 4.72854 6.28279 5.26591 6.08234Z" fill="#D0BED8" />
									</svg>
									Copy
								</button>

								<?php
								if ($perm->validatePermissionsLevelAny(1, 10, 99)) :
								?>
									<button class="public_button">Creators page<label class="toggle-switch">
											<label class="toggle-switch">
												<input type="checkbox" id="cs<?= $images_row['id'] ?>" onclick="checksliderClicked('<?= $images_row['id'] ?>' , '<?= $element ?>')" <?= $images_row['flag'] ? "checked" : "" ?> />
												<div class="toggle-switch-background">
													<div class="toggle-switch-handle"></div>
												</div>
											</label>
									</button>
								<?php
								endif;
								?>
							</figure>
						</div>
				<?php
						$divId++;
						$i++;
					endwhile;
					$images_stmt->close(); // Close statement for images
				endif;
				?>
			</section>

			<div class="toast hidden_element">
				<div class="import_icon">
					<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M10.5893 15.3033L9.41081 16.4818C7.78361 18.109 5.14542 18.109 3.51824 16.4818C1.89106 14.8546 1.89106 12.2164 3.51824 10.5893L4.69675 9.41077M15.3034 10.5893L16.4819 9.41077C18.1091 7.78355 18.1091 5.14536 16.4819 3.51818C14.8547 1.89099 12.2165 1.89099 10.5893 3.51818L9.41083 4.69669M7.08341 12.9166L12.9167 7.0833" stroke="url(#paint0_linear_160_1541)" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
						<defs>
							<linearGradient id="paint0_linear_160_1541" x1="10.0001" y1="2.29779" x2="10.0001" y2="17.7022" gradientUnits="userSpaceOnUse">
								<stop stop-color="#2EDF95" />
								<stop offset="1" stop-color="#07847C" />
							</linearGradient>
						</defs>
					</svg>
				</div>
				<div class="toast_info">
					Copied
					<p>Link of your media copied to clipboard</p>
				</div>
			</div>

			<!-- Edit bar -->
			<div x-data class="fixed inset-x-0 bottom-0 z-50" x-cloak>
				<div class="relative w-screen" x-show="$store.mediaEditBar.show" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-y-full" x-transition:enter-end="opacity-100 transform translate-y-0" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 transform translate-y-0" x-transition:leave-end="opacity-0 transform translate-y-full">

					<div class="h-1/4 bg-purple-500/80 shadow-xl overflow-y-auto relative">
						<button @click="$store.mediaEditBar.toggle()" type="button" class="inset-auto absolute top-1 right-1 flex items-center justify-center h-8 w-8 rounded-full focus:outline-none focus:ring-1 focus:ring-inset focus:ring-white">
							<span class="sr-only">Close panel</span>
							<svg class="h-6 w-6 text-purple-100" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
							</svg>
						</button>
						<div class="px-4 py-3 sm:p-5">
							<div class="mt-0">
								<!-- Your content goes here -->
								<button x-data="{
									getSelectedCount() {
										return $store.checkedCheckboxesMedia.count + $store.checkedCheckboxesFolders.count;
									},
									getSelectedType() {
										if ($store.checkedCheckboxesMedia.count > 0 && $store.checkedCheckboxesFolders.count > 0) {
											return 'item(s)';
										} else if ($store.checkedCheckboxesMedia.count > 0) {
											return 'file(s)';
										} else if ($store.checkedCheckboxesFolders.count > 0) {
											return 'folder(s)';
										} else {
											return 'item(s)';
										}
									}
								}" @click="$store.deleteConfirmation.open()" type="button" class="rounded-md bg-red-500 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500">
									Delete <span x-text="getSelectedCount()"></span> <span x-text="getSelectedType()"></span>
								</button>
								<?php if (!isset($_GET['editfolder'])) : ?>
									<button x-data="{
										getSelectedCount() {
											return $store.checkedCheckboxesMedia.count;
										},
									}" @click="$store.moveToFolder.open()" type="button" class="rounded-md bg-purple-500 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-purple-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-purple-500">
										Move to <span x-text="getSelectedCount()"></span> file(s) to folder
									</button>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Delete confirmation window -->
			<div x-cloak x-data class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
				<div x-show="$store.deleteConfirmation.isOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

				<div x-show="$store.deleteConfirmation.isOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="fixed inset-0 z-50 overflow-y-auto">
					<div class="flex min-h-screen items-end justify-center p-4 text-center sm:items-center sm:p-0">
						<div class="relative transform overflow-hidden rounded-lg bg-purple-50 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
							<div class="bg-purple-50 px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
								<div class="sm:flex sm:items-start">
									<div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
										<svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
											<path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
										</svg>
									</div>
									<div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
										<h3 class="text-base font-semibold leading-6 text-gray-900" id="modal-title">Confirm Delete</h3>
										<div class="mt-2">
											<p class="text-sm text-gray-500" x-data="{
													getSelectedCount() {
														return $store.checkedCheckboxesMedia.count + $store.checkedCheckboxesFolders.count;
													},
													getSelectedType() {
														if ($store.checkedCheckboxesMedia.count > 0 && $store.checkedCheckboxesFolders.count > 0) {
															return 'item(s)';
														} else if ($store.checkedCheckboxesMedia.count > 0) {
															return 'file(s)';
														} else if ($store.checkedCheckboxesFolders.count > 0) {
															return 'folder(s)';
														} else {
															return 'item(s)';
														}
													}
												}">
												Are you sure you want to delete <span x-text="getSelectedCount()"></span> <span x-text="getSelectedType()"></span>? This action cannot be undone.
											</p>
											<!-- Notice about deletion -->
											<div class="mt-3">
												<p class="text-sm text-red-500">Note: Deleting media won't immediately remove it from browser/client caches, other proxies, especially if it has been already publicly shared.</p>
											</div>
										</div>
									</div>
								</div>
							</div>
							<div class="bg-purple-50 px-4 py-3 gap-3 flex flex-row-reverse sm:px-6">
								<button @click="$store.deleteConfirmation.confirm()" type="button" class="mt-3 inline-flex w-full justify-center rounded-md bg-red-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
									<svg x-show="$store.deleteConfirmation.isLoading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
										<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
										<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
									</svg>
									Delete
								</button>
								<button @click="$store.deleteConfirmation.close()" type="button" class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancel</button>
							</div>
						</div>
					</div>
				</div>
			</div>


			<!-- Move to folder modal -->
			<div x-cloak x-data="{ searchTerm: '' }" class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
				<!-- Background overlay -->
				<div x-show="$store.moveToFolder.isOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
				<!-- Modal content -->
				<div x-show="$store.moveToFolder.isOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-0 sm:-translate-y-4" x-transition:enter-end="opacity-100 translate-y-0 sm:translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-0 sm:-translate-y-4 sm:scale-95" class="fixed inset-0 z-50 overflow-y-auto">
					<div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
						<div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all h-52 max-h-72 my-8 w-full sm:max-w-lg overflow-y-auto">
							<div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
								<div class="sm:flex sm:items-start">
									<div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
										<div class="mt-2 flex items-center">
											<label for="combobox" class="block text-sm font-medium leading-6 text-gray-900 mr-2">Select Folder</label>
											<div class="relative w-full z-auto">
												<input id="combobox" type="text" x-model="$store.moveToFolder.selectedFolder" @input="searchTerm = $store.moveToFolder.selectedFolder" @click="$store.moveToFolder.toggleDropdown()" @click.away="searchTerm = ''; $store.moveToFolder.isDropdownOpen = false" class="w-full rounded-md border-0 bg-white py-1.5 pl-3 pr-12 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" role="combobox" aria-controls="options" :aria-expanded="$store.moveToFolder.isDropdownOpen.toString()">
												<button type="button" @click="$store.moveToFolder.toggleDropdown()" class="absolute inset-y-0 right-0 flex items-center rounded-r-md px-2 focus:outline-none">
													<svg class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
														<path fill-rule="evenodd" d="M10 3a.75.75 0 01.55.24l3.25 3.5a.75.75 0 11-1.1 1.02L10 4.852 7.3 7.76a.75.75 0 01-1.1-1.02l3.25-3.5A.75.75 0 0110 3zm-3.76 9.2a.75.75 0 011.06.04l2.7 2.908 2.7-2.908a.75.75 0 111.1 1.02l-3.25 3.5a.75.75 0 01-1.1 0l-3.25-3.5a.75.75 0 01.04-1.06z" clip-rule="evenodd" />
													</svg>
												</button>
												<ul x-show="$store.moveToFolder.isDropdownOpen" @click.away="$store.moveToFolder.isDropdownOpen = false" class="absolute z-10 mt-1 h-32 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm" id="options" role="listbox">
													<template x-for="folder in $store.moveToFolder.folders.filter(f => f.name.toLowerCase().includes(searchTerm.toLowerCase()))">
														<li class="relative cursor-default select-none py-2 pl-3 pr-9 text-gray-900" :id="'option-' + folder.id" role="option" @click="$store.moveToFolder.selectFolder(folder.name); $store.moveToFolder.isDropdownOpen = false" @mouseenter="$store.moveToFolder.hoveredFolder = folder.id" @mouseleave="$store.moveToFolder.hoveredFolder = null" :class="{ 'bg-indigo-600 text-white': folder.id === $store.moveToFolder.hoveredFolder, 'text-gray-900': folder.id !== $store.moveToFolder.hoveredFolder }">
															<div class="flex">
																<span x-text="folder.name" class="truncate" :class="{ 'font-semibold': folder.name === $store.moveToFolder.selectedFolder }"></span>
															</div>
															<span x-show="folder.name === $store.moveToFolder.selectedFolder" class="absolute inset-y-0 right-0 flex items-center pr-4" :class="{ 'text-white': folder.id === $store.moveToFolder.hoveredFolder, 'text-indigo-600': folder.id !== $store.moveToFolder.hoveredFolder }">
																<svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
																	<path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
																</svg>
															</span>
														</li>
													</template>
												</ul>
											</div>
										</div>
									</div>
								</div>
							</div>
							<div class="bg-white px-4 py-3 sm:px-6 sm:flex sm:flex-wrap sm:justify-end">
								<button x-data="{
													getSelectedCount() { return $store.checkedCheckboxesMedia.count; }
												}" x-show="!$store.moveToFolder.isLoading" @click="$store.moveToFolder.moveToSelectedFolder()" type="button" class="inline-flex justify-center rounded-md bg-indigo-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:order-1 sm:ml-3 sm:w-auto sm:text-sm">
									Move&nbsp;<span x-text="getSelectedCount()"></span>&nbsp;file(s) to folder
								</button>
								<button x-show="$store.moveToFolder.isLoading" type="button" class="inline-flex justify-center rounded-md bg-indigo-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:order-1 sm:ml-3 sm:w-auto sm:text-sm" disabled>
									<svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
										<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
										<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
									</svg>
									Moving...
								</button>
								<button @click="$store.moveToFolder.close()" type="button" class="mt-3 inline-flex justify-center rounded-md bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:order-0 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
									Cancel
								</button>
							</div>
						</div>
					</div>
				</div>
			</div>


	</main>
	<script src="/scripts/account.js?v=431039814f9c9bb55961ae9f04fd9c76"></script>

	<script>
		var previousBtId;
		var currentBtId;
		var checkedFileList = "";
		var checkedFolderList = "";

		function folderDoubleClicked(folder_name) {
			if (checkedFolderList == "") {
				// console.log('There is no folder clicked.');
				if (checkedFileList != "") {
					// console.log(checkedFileList);
					var lastIndex = window.location.href.indexOf("/");
					var ipAddress = window.location.href.substring(0, lastIndex) + '/functions/add_to_folder.php';
					var addedList = checkedFileList.replaceAll("cb", "id");
					window.location.replace(`${ipAddress}?fld=${folder_name}&idList=${addedList}`);
				}
			}
		}

		function folderClicked(folder_name, divCount) {
			// console.log(folder_name, ", ", divCount);
			for (var i = 0; i < divCount; i++) {
				// console.log(document.getElementById('input' + i).value);
				if (document.getElementById('input' + i).value == folder_name) {
					document.getElementById('div' + i).style.display = 'block';
				} else {
					document.getElementById('div' + i).style.display = 'none';
				}
			}
		}

		function createFolder() {
			if (document.getElementById('folderName').value == "") {
				window.alert("Please enter folder name");
			} else {
				// console.log(document.getElementById('folderName').value);
				var lastIndex = window.location.href.indexOf("/");
				var ipAddress = window.location.href.substring(0, lastIndex) + '/functions/create_folder.php';
				var folderName = document.getElementById('folderName').value;
				window.location.replace(`${ipAddress}?folder_name=${folderName}`);
			}
		}

		async function checksliderClicked(id, element) {
			const checkbox = document.getElementById("cs" + id);
			const targetElement = checkbox.closest('figure').querySelector('img, audio, video');

			const flag = checkbox.checked ? '1' : '0';
			const originalState = !checkbox.checked; // Store the original state
			const originalClassState = targetElement.classList.contains('publicly-shared'); // Store the original class state

			// Update UI first (optimistic UI update)
			if (checkbox.checked) {
				targetElement.classList.add('publicly-shared');
			} else {
				targetElement.classList.remove('publicly-shared');
			}

			// Assuming your project is in a subfolder and the toggle.php is in /functions/
			const url = new URL(`/functions/toggle.php`, window.location.origin);
			url.searchParams.append('id', id);
			url.searchParams.append('flag', flag);

			try {
				const response = await fetch(url, {
					method: 'GET',
					redirect: 'manual'
				});

				if (response.type === 'opaqueredirect') {
					// Operation successful, do nothing
				} else {
					// Revert UI state if response type is not 'opaqueredirect'
					revertUIState(checkbox, targetElement, originalState, originalClassState);
				}
			} catch (error) {
				revertUIState(checkbox, targetElement, originalState, originalClassState);
			}
		}

		function revertUIState(checkbox, targetElement, originalState, originalClassState) {
			checkbox.checked = originalState;
			if (originalClassState) {
				targetElement.classList.add('publicly-shared');
			} else {
				targetElement.classList.remove('publicly-shared');
			}
		}

		document.addEventListener('alpine:init', () => {
			console.log('Alpine initialized');
			Alpine.store('mediaEditBar', {
				show: false,

				toggle() {
					this.show = !this.show
					// Uncheck all checkboxes
					const checkboxes = document.querySelectorAll('input[type="checkbox"].media_select_checkbox');
					checkboxes.forEach(checkbox => {
						checkbox.checked = false;
					});
				}
			})
			Alpine.store('deleteConfirmation', {
				isOpen: false,
				isLoading: false,
				open() {
					this.isOpen = true;
					this.isLoading = false;
				},
				close() {
					this.isOpen = false;
					this.isLoading = false;
				},
				confirm() {
					// Perform the delete action here
					console.log('Delete action confirmed');
					this.isLoading = true;
					checkDelete();
					//this.close();
				}
			})
			// Store number of checked checkboxes for media
			Alpine.store('checkedCheckboxesMedia', {
				count: 0
			})
			// Store number of checked checkboxes for folders
			Alpine.store('checkedCheckboxesFolders', {
				count: 0
			})

			Alpine.store('moveToFolder', {
				isOpen: false,
				isLoading: false,
				selectedFolder: '',
				folders: [
					<?php foreach ($usersFoldersStats['FOLDERS'] as $folder => $folderStats) : ?>
						<?php if ($folder == '/') continue; ?> {
							id: <?= $folderStats['id'] ?>,
							name: '<?= htmlspecialchars(addslashes($folder)) ?>'
						},
					<?php endforeach; ?>
				],
				isDropdownOpen: false,
				hoveredFolder: null,
				open() {
					this.isOpen = true;
					this.isLoading = false;
					this.selectedFolder = '';
					this.isDropdownOpen = false;
					this.hoveredFolder = null;
				},
				close() {
					this.isOpen = false;
					this.isLoading = false;
					this.selectedFolder = '';
					this.isDropdownOpen = false;
					this.hoveredFolder = null;
				},
				toggleDropdown() {
					this.isDropdownOpen = !this.isDropdownOpen;
				},
				selectFolder(folderName) {
					this.selectedFolder = folderName;
					this.isDropdownOpen = false;
				},
				moveToSelectedFolder() {
					this.isLoading = true;
					// Perform the move to folder action here
					console.log('Moving to folder:', this.selectedFolder);
					folderDoubleClicked(this.selectedFolder);
				},
			});
		})

		function checkboxClicked(element, type) {
			if (type == 0) {
				if (document.getElementById("cb" + element).checked) {
					checkedFolderList += ("cb" + element);
				} else {
					checkedFolderList = checkedFolderList.replace(("cb" + element), '');
				}
				// console.log("checkedFolderList : " , checkedFolderList);
			} else
			if (type == 1) {
				if (document.getElementById("cb" + element).checked) {
					checkedFileList += ("cb" + element);
				} else {
					checkedFileList = checkedFileList.replace(("cb" + element), '');
				}
				// console.log("checkedFileList : " , checkedFileList);
			}
			// Check if any checkbox is checked
			const checkboxesMedia = document.querySelectorAll('input[type="checkbox"].media_select_checkbox');
			const checkboxesFolders = document.querySelectorAll('input[type="checkbox"].folder_select_checkbox');
			const checkedCountMedia = Array.from(checkboxesMedia).filter(checkbox => checkbox.checked).length;
			const checkedCountFolders = Array.from(checkboxesFolders).filter(checkbox => checkbox.checked).length;
			// Update number of checked checkboxes
			Alpine.store('checkedCheckboxesMedia').count = checkedCountMedia;
			Alpine.store('checkedCheckboxesFolders').count = checkedCountFolders;
			const isAnyCheckboxChecked = checkedCountMedia > 0 || checkedCountFolders > 0;
			console.log('isAnyCheckboxChecked:', isAnyCheckboxChecked);
			console.log('checkedCountMedia:', Alpine.store('checkedCheckboxesMedia').count);
			console.log('checkedCountFolders:', Alpine.store('checkedCheckboxesFolders').count);

			// Update the isOpen state based on checkbox status
			Alpine.store('mediaEditBar').show = isAnyCheckboxChecked;
		}

		function copyToClipboard(element) {
			var mediaElement = document.getElementById(element);

			if (!mediaElement) {
				console.error('Element not found:', element);
				return;
			}

			var imageUrl = mediaElement.dataset.src;

			if (window.isSecureContext && navigator.clipboard) {
				navigator.clipboard.writeText(imageUrl);
				const copyButtonTextContent = document.getElementById('bt' + element).textContent;
				document.getElementById('bt' + element).textContent = 'Copied';
				// Revert back to 'Copy' after 2 seconds
				setTimeout(() => {
					document.getElementById('bt' + element).textContent = copyButtonTextContent;
				}, 2000);
				if (document.getElementById('bt' + previousBtId) != null && previousBtId != element) {
					document.getElementById('bt' + previousBtId).textContent = 'Copy';
				}
				previousBtId = element;
			} else {
				var textArea = document.createElement("textarea");
				textArea.textContent = imageUrl;
				document.body.appendChild(textArea);
				var selection = document.getSelection();
				var range = document.createRange();
				range.selectNode(textArea);
				selection.addRange(range);
				try {
					if (document.execCommand('copy')) {
						document.getElementById('bt' + element).textContent = 'Copied';
						if (document.getElementById('bt' + previousBtId) != null && previousBtId != element) {
							document.getElementById('bt' + previousBtId).textContent = 'Copy';
						}
						previousBtId = element;
						selection.removeAllRanges();
					} else {
						alert("Failed to copy URL!");
					}
				} catch (err) {
					console.error('Unable to copy to clipboard', err);
					alert("Failed to copy URL!");
				}
				document.body.removeChild(textArea);
			}
		}


		function checkDelete() {
			var lastIndex = window.location.href.indexOf("/");
			var ipAddress = window.location.href.substring(0, lastIndex) + '../functions/delete.php';
			var checkedList = checkedFolderList.replaceAll('cb', 'idr_');
			checkedList += checkedFileList.replaceAll('cb', 'ide_');
			window.location.replace(`${ipAddress}?idList=${checkedList}`);
		}

		// Get the modal
		const modal = document.getElementById("myModal");

		// Get the button that opens the modal
		const btn = document.getElementById("myBtn");

		// Get the <span> element that closes the modal
		const span = document.getElementsByClassName("close")[0];

		// When the user clicks on the button, open the modal
		btn.onclick = function() {
			modal.style.display = "block";
		}

		// When the user clicks on <span> (x), close the modal
		span.onclick = function() {
			modal.style.display = "none";
		}

		// When the user clicks anywhere outside of the modal, close it
		window.onclick = function(event) {
			if (event.target == modal) {
				modal.style.display = "none";
			}
		}
	</script>
	<!-- URL Upload Modal -->
	<div x-show="url_up_open" class="relative z-10" aria-labelledby="modal-title" role="dialog" aria-modal="true">
		<div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" x-cloak x-show="url_up_open" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>

		<div class=" fixed inset-0 z-10 overflow-y-auto">
			<div class="flex min-h-full items-center justify-center p-4 text-center sm:items-center sm:p-0">
				<div class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6" x-cloak x-show="url_up_open" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">

					<div class="absolute right-0 top-0 pr-4 pt-4 block">
						<button type="button" class="rounded-md bg-white text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2" @click="url_up_open = false">
							<span class="sr-only">Close</span>
							<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
								<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
							</svg>
						</button>
					</div>

					<form id="url-import-form" action="/api/v2/account/url" method="POST" hx-ext="loading-states">
						<label for="url" class="block text-sm font-medium leading-6 text-gray-900">Import media from URL</label>
						<div class="mt-2 flex rounded-md shadow-sm">
							<div class="relative flex flex-grow items-stretch focus-within:z-10">
								<div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
									<svg data-loading-class="hidden" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
										<path d="M12.232 4.232a2.5 2.5 0 013.536 3.536l-1.225 1.224a.75.75 0 001.061 1.06l1.224-1.224a4 4 0 00-5.656-5.656l-3 3a4 4 0 00.225 5.865.75.75 0 00.977-1.138 2.5 2.5 0 01-.142-3.667l3-3z" />
										<path d="M11.603 7.963a.75.75 0 00-.977 1.138 2.5 2.5 0 01.142 3.667l-3 3a2.5 2.5 0 01-3.536-3.536l1.225-1.224a.75.75 0 00-1.061-1.06l-1.224 1.224a4 4 0 105.656 5.656l3-3a4 4 0 00-.225-5.865z" />
									</svg>
									<svg data-loading="block" class="hidden animate-spin -ml-1 mr-3 h-5 w-5 text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
										<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
										<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
									</svg>
								</div>
								<input type="url" name="url" id="url" data-loading-disable class="disabled:opacity-75 block w-full rounded-none rounded-l-md border-0 py-1.5 pl-10 text-gray-900 ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6" placeholder="https://..." required pattern="https?://.+" title="Enter a valid URL starting with http:// or https://">
							</div>
							<button type="submit" hx-post="/api/v2/account/url" hx-trigger="click" hx-swap="none" hx-validate data-loading-disable class="disabled:opacity-75 relative -ml-px inline-flex items-center gap-x-1.5 rounded-r-md px-3 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50">
								<svg class="-ml-0.5 h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
									<path fill-rule="evenodd" d="M13.75 7h-3V3.66l1.95 2.1a.75.75 0 101.1-1.02l-3.25-3.5a.75.75 0 00-1.1 0L6.2 4.74a.75.75 0 001.1 1.02l1.95-2.1V7h-3A2.25 2.25 0 004 9.25v7.5A2.25 2.25 0 006.25 19h7.5A2.25 2.25 0 0016 16.75v-7.5A2.25 2.25 0 0013.75 7zm-3 0h-1.5v5.25a.75.75 0 001.5 0V7z" clip-rule="evenodd" />
								</svg>
								Import
							</button>
						</div>
						<p class="mt-2 text-sm text-red-600 hidden" id="url-error"></p>
					</form>

				</div>
			</div>
		</div>
	</div>
	<script>
		document.body.addEventListener('htmx:configRequest', (event) => {
			const form = document.getElementById('url-import-form');
			if (!form.checkValidity()) {
				event.preventDefault();
				const inputs = form.querySelectorAll('input,select,textarea');
				for (var i = 0; i < inputs.length; i++) {
					const input = inputs[i];
					if (!input.checkValidity()) {
						// Show the error message
						var errorMessageDiv = document.getElementById(input.id + '-error');
						errorMessageDiv.textContent = input.validationMessage;
						errorMessageDiv.classList.remove('hidden');

						// Clear the error on input
						input.addEventListener('input', () => {
							errorMessageDiv.textContent = '';
							errorMessageDiv.classList.add('hidden');
						});
					}
				}
			}
		});

		document.body.addEventListener('htmx:afterRequest', function(event) {
			const xhr = event.detail.xhr;
			if (xhr.getResponseHeader('Content-Type').includes('application/json')) {
				const response = JSON.parse(xhr.responseText);
				if (response.status === "error") {
					//alert(response.message); // or update some part of your UI to display the error
					const input = document.getElementById('url');
					// Show the error message
					const errorMessageDiv = document.getElementById(input.id + '-error');
					errorMessageDiv.textContent = response.message;
					errorMessageDiv.classList.remove('hidden');

					// Clear the error on input
					input.addEventListener('input', () => {
						errorMessageDiv.textContent = '';
						errorMessageDiv.classList.add('hidden');
					});
				} else if (response.status === "success") {
					location.reload();
				}
			}
		});
	</script>

</body>

</html>