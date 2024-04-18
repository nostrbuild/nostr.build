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

if (!$perm->validateLoggedin()  || empty($user)) {
	header("Location: /login");
	$link->close();
	exit;
}

// BETA: Only allow Advanced and Admin users for now, but allow all users to see the page
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
if ($daysRemaining <= 0) {
	header("Location: /plans/");
	$link->close();
	exit;
}

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

// Get the name of the current user account based on their level, e.g., "Free", "Proffecional", "Basic", "Creator", "Advanced", "Admin"
$accountLevelName = match ($acctlevel) {
	0 => "Free",
	1 => "Creator",
	2 => "Professional",
	3 => "Basic",
	4 => "Basic",
	5 => "Basic",
	10 => "Advanced",
	99 => "Admin",
	default => "Unknown"
};


$NBLogoSVG = <<<SVG
<svg class="h-8 w-auto" id="a" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 33.4 39">
  <defs>
    <linearGradient id="b" x1="8.25" y1="-326.6" x2="8.25" y2="-297.6" gradientTransform="translate(0 336.6)" gradientUnits="userSpaceOnUse">
      <stop offset="0" stop-color="#9b4399"/>
      <stop offset="1" stop-color="#3d1e56"/>
      <stop offset="1" stop-color="#231815"/>
    </linearGradient>
    <style>
      .e{stroke-width:0;fill:#fff}
    </style>
  </defs>
  <path d="M16.7 0 .1 9.8l16.6 9.7 16.5-9.7L16.7 0Z" style="fill:#70429a;stroke-width:0"/>
  <path class="e" d="m16.7 1.7-4.6 2.6v10.8l4.6 2.7 4.6-2.7V9.7l4.5-2.6-9.1-5.4Zm-3 6.3c-1.23-.11-1.13-1.98.1-2 1.3 0 1.27 2.09 0 2m6.8 1.3L16.8 7l3.8-2.3L24.3 7l-3.7 2.3Z"/>
  <path d="M0 10v19.3L16.5 39V19.7L0 10Z" style="fill:url(#b);stroke-width:0"/>
  <path class="e" d="m14.4 35.2-3-1.8v-.9L5.1 21.8l-.8-.4v8L2 28.1V13.8l3 1.7v1l6.2 10.7.8.4v-8l2.3 1.3.1 14.3Z"/>
  <path d="M16.8 19.7V39l16.6-9.7V10l-16.6 9.7Z" style="stroke-width:0;fill:#3d1e56"/>
  <path class="e" d="M31.3 16.4 29 15.1l-10 5.8v14.3L29.7 29l1.6-2.7v-3.6l-1.6-.9 1.6-2.7v-2.7Zm-2.5 9.9-1.1 2-6.5 3.8v-4.5l6.5-3.8 1.2.7-.1 1.8Zm0-6.3-1 2-6.5 3.8v-4.4l6.5-3.8 1.2.6-.2 1.8Z"/>
</svg>
SVG;

$pageMenuContent = <<<HTML
<ul role="list" class="flex flex-1 flex-col gap-y-7" x-data="{ menuStore: \$store.menuStore, fileStore: \$store.fileStore }" x-init="menuStore.activeMenu = menuStore.menuItems[0].name" @click.away="!menuStore.showDeleteFolderModal && menuStore.disableDeleteFolderButtons()">

	<!-- Profile -->
	<ul role="list" class="-mx-2 space-y-1">
		<li>
			<div class="flex items-center justify-between">
				<div class="flex items-center gap-x-4">
					<img class="size-8 rounded-full bg-nbpurple-800" :src="menuStore.profile.imageUrl" alt="User's profile picture">
					<div>
						<span class="block text-sm font-semibold leading-tight text-nbpurple-50" x-text="menuStore.profile.name"></span>
						<span class="block text-xs font-semibold leading-tight text-nbpurple-50" x-text="menuStore.profile.npub"></span>
					</div>
				</div>
				<div class="flex items-center">
					<a href="#" class="text-sm font-semibold leading-6 text-nbpurple-50 hover:bg-nbpurple-800 p-2 rounded-md">
						<svg aria-hidden="true" class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
							<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
						</svg>
						<span class="sr-only">Profile Settings</span>
					</a>
					<a href="#" class="text-sm font-semibold leading-6 text-nbpurple-50 hover:bg-nbpurple-800 p-2 rounded-md">
						<svg aria-hidden="true" class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15M12 9l3 3m0 0-3 3m3-3H2.25" />
						</svg>
						<span class="sr-only">Logout</span>
					</a>
				</div>
			</div>
		</li>
	</ul>
	<!-- /Profile -->
	<!-- Sidebar widgets -->
	<li>
		<ul role="list" class="-mx-2 space-y-1">
			<!-- Storage Usage Widget -->
			<li>
				<div class="flex items-center justify-between">
					<div class="flex items-center">
						<svg aria-hidden="true" class="size-5 text-nbpurple-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
						</svg>
						<span class="ml-2 text-sm font-medium text-nbpurple-300">Usage</span>
					</div>
					<div class="text-sm font-medium text-nbpurple-300" x-text="formatBytes(menuStore.storageUsage.totalUsed) + ' / ' + menuStore.storageUsage.totalAvailable"></div>
				</div>
				<div class="mt-2 w-full bg-nbpurple-200 rounded-full h-2">
					<div class="bg-nbpurple-600 h-2 rounded-full" :style="'width: ' + (menuStore.storageUsage.getRatio().toFixed(2) * 100) + '%'"></div>
				</div>
			</li>
			<!-- /Storage Usage Widget -->
			<!-- Remaining Days Widget -->
			<li>
				<div class="flex items-center justify-between">
					<div class="flex items-center">
						<svg aria-hidden="true" class="size-5 text-nbpurple-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
						</svg>
						<span class="ml-2 text-sm font-medium text-nbpurple-300">Remaining Days</span>
					</div>
					<div class="text-sm font-medium text-nbpurple-300" x-text="menuStore.remainingDays"></div>
				</div>
			</li>
			<!-- /Remaining Days Widget -->
			<!-- File statistics -->
			<li>
				<div class="flex items-center justify-between">
					<div class="flex items-center">
						<svg aria-hidden="true" class="size-5 text-nbpurple-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z" />
							<path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z" />
						</svg>
						<span class="ml-2 text-sm font-medium text-nbpurple-300" x-text="'Media(' + menuStore.formatNumberInThousands(menuStore.fileStats.totalFiles) + ')'"></span>
					</div>
					<div class="flex items-center space-x-2">
						<div class="flex items-center">
							<svg aria-hidden="true" class="size-4 text-nbpurple-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
								<path stroke-linecap="round" stroke-linejoin="round" d="M12.75 8.25v7.5m6-7.5h-3V12m0 0v3.75m0-3.75H18M9.75 9.348c-1.03-1.464-2.698-1.464-3.728 0-1.03 1.465-1.03 3.84 0 5.304 1.03 1.464 2.699 1.464 3.728 0V12h-1.5M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
							</svg>
							<span class="ml-1 text-sm font-medium text-nbpurple-300" x-text="menuStore.formatNumberInThousands(menuStore.fileStats.totalGifs)"></span>
						</div>
						<div class="flex items-center">
							<svg aria-hidden="true" class="size-4 text-nbpurple-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
							</svg>
							<span class="ml-1 text-sm font-medium text-nbpurple-300" x-text="menuStore.formatNumberInThousands(menuStore.fileStats.totalImages)"></span>
						</div>
						<div class="flex items-center">
							<svg aria-hidden="true" class="size-4 text-nbpurple-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
							</svg>
							<span class="ml-1 text-sm font-medium text-nbpurple-300" x-text="menuStore.formatNumberInThousands(menuStore.fileStats.totalVideos)"></span>
						</div>
					</div>
				</div>
			</li>
			<!-- /File statistics -->
			<!-- Creators Page Shares -->
			<li x-clock x-show="menuStore.fileStats.creatorCount > 0">
				<div class="flex items-center justify-between">
					<div class="flex items-center cursor-copy" @click="navigator.clipboard.writeText(menuStore.fileStats.creatorPageLink); showToast = true">
						<svg aria-hidden="true" class="size-4 text-nbpurple-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42" />
						</svg>
						<span class="ml-2 text-sm font-medium text-nbpurple-300">Creators Page</span>
						<svg aria-hidden="true" class="ml-1 size-4 text-nbpurple-300" viewBox="0 0 20 20" fill="currentColor">
							<path fill-rule="evenodd" d="M15.988 3.012A2.25 2.25 0 0 1 18 5.25v6.5A2.25 2.25 0 0 1 15.75 14H13.5v-3.379a3 3 0 0 0-.879-2.121l-3.12-3.121a3 3 0 0 0-1.402-.791 2.252 2.252 0 0 1 1.913-1.576A2.25 2.25 0 0 1 12.25 1h1.5a2.25 2.25 0 0 1 2.238 2.012ZM11.5 3.25a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 .75.75v.25h-3v-.25Z" clip-rule="evenodd" />
							<path d="M3.5 6A1.5 1.5 0 0 0 2 7.5v9A1.5 1.5 0 0 0 3.5 18h7a1.5 1.5 0 0 0 1.5-1.5v-5.879a1.5 1.5 0 0 0-.44-1.06L8.44 6.439A1.5 1.5 0 0 0 7.378 6H3.5Z" />
						</svg>
					</div>
					<a class="flex items-center cursor-pointer" :href="menuStore.fileStats.creatorPageLink">
						<svg aria-hidden="true" class="size-4 text-nbpurple-300" viewBox="0 0 20 20" fill="currentColor">
							<path d="M12.232 4.232a2.5 2.5 0 0 1 3.536 3.536l-1.225 1.224a.75.75 0 0 0 1.061 1.06l1.224-1.224a4 4 0 0 0-5.656-5.656l-3 3a4 4 0 0 0 .225 5.865.75.75 0 0 0 .977-1.138 2.5 2.5 0 0 1-.142-3.667l3-3Z" />
							<path d="M11.603 7.963a.75.75 0 0 0-.977 1.138 2.5 2.5 0 0 1 .142 3.667l-3 3a2.5 2.5 0 0 1-3.536-3.536l1.225-1.224a.75.75 0 0 0-1.061-1.06l-1.224 1.224a4 4 0 1 0 5.656 5.656l3-3a4 4 0 0 0-.225-5.865Z" />
						</svg>
						<span class="ml-1 text-sm font-medium text-nbpurple-300" x-text="menuStore.formatNumberInThousands(menuStore.fileStats.creatorCount)"></span>
					</a>
				</div>
			</li>
			<!-- /Creators Page Shares -->
		</ul>
	</li>
	<!-- /Sidebar widgets -->
	<!-- Menu items -->
	<li>
		<ul role="list" class="-mx-2 space-y-1">
			<template x-for="item in menuStore.menuItems" :key="item.name">
				<li>
					<a :href="item.route" :class="{ 'bg-nbpurple-800 text-nbpurple-50': menuStore.activeMenu === item.name, 'text-nbpurple-300 hover:text-nbpurple-50 hover:bg-nbpurple-800': menuStore.activeMenu !== item.name }" class="group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold" @click="menuStore.activeMenu = item.name; mobileMenuOpen = false">
						<svg aria-hidden="true" class="size-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" x-html="item.icon"></svg>
						<span x-text="item.name"></span>
					</a>
				</li>
			</template>
		</ul>
	</li>
	<!-- /Menu items -->
	<!-- Folders -->
	<li>
		<div class="flex items-center justify-between text-xs font-semibold leading-6 text-nbpurple-300">
			<span class="font-bold" x-text="'Folders (' + menuStore.folders.length + ')'"></span>
			<div>
				<!-- Button to create a new folder -->
				<button type="button" class="ml-2 p-1 text-xs font-semibold text-nbpurple-300 hover:text-nbpurple-50 hover:bg-nbpurple-800 rounded-md" @click="menuStore.addNewFolderForm(); menuStore.disableDeleteFolderButtons(); \$nextTick(() => { \$refs.newFolderNameInput.focus(); })" :disabled="menuStore.isNewFolderFormPresent()">
					<svg aria-hidden="true" class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m3-3H9m4.06-7.19-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
					</svg>
					<span class="sr-only">Create a new folder</span>
				</button>
				<!-- Button to delete folders -->
				<button type="button" class="ml-2 p-1 text-xs font-semibold text-nbpurple-300 hover:text-nbpurple-50 hover:bg-nbpurple-800 rounded-md" @click="menuStore.toggleDeleteFolderButtons()" :disabled="menuStore.isNewFolderFormPresent()">
					<svg x-show="!menuStore.showDeleteFolderButtons" aria-hidden="true" class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" d="M15 13.5H9m4.06-7.19-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
					</svg>
					<svg x-cloak x-show="menuStore.showDeleteFolderButtons" aria-hidden="true" class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
					</svg>
					<span class="sr-only">Delete folder(s)</span>
				</button>
			</div>
		</div>
		<ul role="list" class="-mx-2 mt-2 space-y-1">
			<template x-for="folder in menuStore.folders" :key="folder.name">
				<li>
					<div x-show="!folder.newForm" class="flex justify-between">
						<a :href="folder.route" :class="{ 'bg-nbpurple-800 text-nbpurple-50': menuStore.activeFolder === folder.name, 'text-nbpurple-300 hover:text-nbpurple-50 hover:bg-nbpurple-800': menuStore.activeFolder !== folder.name }" class="w-full group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold" @click.prevent="menuStore.setActiveFolder(folder.name); mobileMenuOpen = false">
							<span class="flex size-6 shrink-0 items-center justify-center rounded-lg border border-nbpurple-700 bg-nbpurple-800 text-[0.625rem] font-medium text-nbpurple-300 group-hover:text-nbpurple-50" x-text="folder.icon"></span>
							<span class="truncate" x-text="folder.name"></span>
						</a>
						<!-- Delete folder Icon -->
						<button x-cloak x-show="menuStore.showDeleteFolderButtons && folder.allowDelete && folder.name !== menuStore.activeFolder" type="button" class="text-nbpurple-300 hover:text-nbpurple-50 hover:bg-nbpurple-800 rounded-md p-2 text-sm leading-6 font-semibold" @click="menuStore.openDeleteFolderModal(folder.id)">
							<svg aria-hidden="true" class="size-5 shrink-0 items-center justify-center rounded-lg border border-nbpurple-700 bg-nbpurple-800 text-[0.625rem] font-medium text-nbpurple-300 group-hover:text-nbpurple-50" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
								<path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
							</svg>
							<span class="sr-only">Delete folder</span>
						</button>
					</div>
					<div x-cloak x-show="folder.newForm">
						<div class="flex items-center gap-x-3 relative">
							<!-- Error message -->
							<div
								id="new-folder-error-message"
								x-show="menuStore.newFolderNameError"
								class="absolute left-1/3 -translate-x-1/2 z-10 text-sm font-semibold text-orange-300 transition-opacity duration-300 bg-nbpurple-950/85 rounded-md p-2"
								:class="{
									'opacity-0': !menuStore.newFolderNameError,
									'opacity-100': menuStore.newFolderNameError
								}"
								x-transition:enter="transition ease-out duration-300"
								x-transition:leave="transition ease-in duration-300"
								x-text="menuStore.newFolderNameError"
								aria-live="assertive"
								role="alert"
							>
							</div>
							<label for="new-folder-name" class="sr-only">New folder name</label>
							<input x-ref="newFolderNameInput" type="text" name="new-folder-name" :class="{'animate-shake': menuStore.newFolderNameError }" class="w-full text-sm font-semibold text-nbpurple-300 bg-nbpurple-800 rounded-md p-2" x-model="folder.newFolderName" @keydown.enter="menuStore.createFolder(folder.newFolderName)" @keydown.escape="menuStore.cancelNewFolderForm(folder)" aria-describedby="new-folder-error-message">
							<button type="button" class="-ml-2 text-nbpurple-300 hover:text-nbpurple-50 hover:bg-nbpurple-800 rounded-md p-2 text-sm leading-6 font-semibold" @click="menuStore.createFolder(folder.newFolderName)">
								<svg aria-hidden="true" class="size-5 shrink-0 items-center justify-center rounded-lg border border-nbpurple-700 bg-nbpurple-800 text-[0.625rem] font-medium text-nbpurple-300 group-hover:text-nbpurple-50" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
									<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
								</svg>
								<span class="sr-only">Create folder</span>
							</button>
							<button type="button" class="-mx-2 text-nbpurple-300 hover:text-nbpurple-50 hover:bg-nbpurple-800 rounded-md p-2 text-sm leading-6 font-semibold" @click="menuStore.cancelNewFolderForm(folder)">
								<svg aria-hidden="true" class="size-5 shrink-0 items-center justify-center rounded-lg border border-nbpurple-700 bg-nbpurple-800 text-[0.625rem] font-medium text-nbpurple-300 group-hover:text-nbpurple-50" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
									<path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
								</svg>
								<span class="sr-only">Cancel</span>
							</button>
						</div>
					</div>
				</li>
			</template>
			<li x-cloak x-show="!menuStore.isNewFolderFormPresent()">
				<button type="button" class="w-full text-nbpurple-300 hover:text-nbpurple-50 hover:bg-nbpurple-800 group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold" @click="menuStore.addNewFolderForm(); \$nextTick(() => { \$refs.newFolderNameInput.focus(); })">
					<svg aria-hidden="true" class="flex size-6 shrink-0 items-center justify-center rounded-lg border border-nbpurple-700 bg-nbpurple-800 text-[0.625rem] font-medium text-nbpurple-300 group-hover:text-nbpurple-50" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m3-3H9m4.06-7.19-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
					</svg>
					<span class="truncate">Create a new folder</span>
				</button>
			</li>
		</ul>
	</li>
	<!-- /Folders -->
	<!-- Bottom buffer -->
	<li class="flex-1 min-h-12"></li>
	<!-- /Bottom buffer -->
</ul>
HTML;
?>

<!DOCTYPE html>
<html lang="en" class="h-full bg-gradient-to-tr from-nbpurple-950 to-nbpurple-800 bg-fixed bg-no-repeat bg-cover antialiased">

<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>nostr.build account</title>

	<link rel="icon" href="/assets/primo_nostr.png" />
	<link href="/styles/twbuild.css?v=f95e0de5bb8bee21a45d018b0f54aa4c" rel="stylesheet">
	<script defer src="/scripts/fw/alpinejs-intersect.min.js?v=e6545f3f0a314d90d9a1442ff104eab9"></script>
	<script defer src="/scripts/fw/alpinejs.min.js?v=34fbe266eb872c1a396b8bf9022b7105"></script>
	<style>
		[x-cloak] {
			display: none !important;
		}
	</style>

</head>

<body x-data="{ mobileMenuOpen: false, showToast: false }" x-init="if (!$store.menuStore.foldersFetched) $store.menuStore.fetchFolders(); $watch('showToast', value => {
		if (value) {
			setTimeout(() => showToast = false, 2000);
		}
	})" class="h-full">
	<main>
		<div>
			<!-- Off-canvas menu for mobile, show/hide based on off-canvas menu state. -->
			<div x-cloak class="relative z-50 xl:hidden" role="dialog" aria-modal="true" x-show="mobileMenuOpen" x-transition:enter="transition-opacity ease-linear duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-linear duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
				<div class="fixed inset-0 bg-nbpurple-900/80"></div>

				<div class="fixed inset-0 flex">
					<div class="relative mr-16 flex w-full max-w-xs flex-1" x-show="mobileMenuOpen" x-transition:enter="transition ease-in-out duration-300 transform" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in-out duration-300 transform" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full">
						<div class="absolute left-full top-0 flex w-16 justify-center pt-5">
							<button type="button" class="-m-2.5 p-2.5" @click="mobileMenuOpen = false">
								<span class="sr-only">Close sidebar</span>
								<svg class="size-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
									<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
								</svg>
							</button>
						</div>

						<!-- Sidebar component -->
						<div class="flex grow flex-col gap-y-5 overflow-y-auto bg-nbpurple-900 px-6 ring-1 ring-white/10" @click.outside="mobileMenuOpen = false">
							<div class="flex h-16 shrink-0 items-center -mb-5">
								<?= $NBLogoSVG ?>
								<span class="text-nbpurple-50 font-semibold ml-4">
									<?= $accountLevelName ?> Account
								</span>
							</div>
							<nav class="flex flex-1 flex-col">
								<!-- Mobile menu content -->
								<?= $pageMenuContent ?>
							</nav>
						</div>
					</div>
				</div>
			</div>

			<!-- Static sidebar for desktop -->
			<div class="hidden xl:fixed xl:inset-y-0 xl:z-50 xl:flex xl:w-72 xl:flex-col">
				<!-- Sidebar component, swap this element with another sidebar if you like -->
				<div class="flex grow flex-col gap-y-5 overflow-y-auto bg-nbpurple-900/10 px-6 ring-1 ring-white/5">
					<div class="flex h-16 shrink-0 items-center -mb-5">
						<?= $NBLogoSVG ?>
						<span class="text-nbpurple-50 font-semibold ml-4">
							<?= $accountLevelName ?> Account
						</span>
					</div>
					<nav class="flex flex-1 flex-col">
						<!-- Desktop menu content -->
						<?= $pageMenuContent ?>
					</nav>
				</div>
			</div>

			<div class="xl:pl-72">
				<!-- Sticky search header -->
				<div class="sticky top-0 z-40 flex h-16 shrink-0 items-center gap-x-6 border-b border-white/5 bg-nbpurple-900 px-4 shadow-sm sm:px-6 lg:px-8">
					<button type="button" class="-m-2.5 p-2.5 text-white xl:hidden" @click="mobileMenuOpen = true">
						<span class="sr-only">Open sidebar</span>
						<svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
							<path fill-rule="evenodd" d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zM2 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10zm0 5.25a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75a.75.75 0 01-.75-.75z" clip-rule="evenodd" />
						</svg>
					</button>

					<div class="flex flex-1 gap-x-4 self-stretch lg:gap-x-6">
						<form class="flex flex-1" action="#" method="GET">
							<label for="search-field" class="sr-only">Search</label>
							<div class="relative w-full">
								<svg class="pointer-events-none absolute inset-y-0 left-0 h-full w-5 text-nbpurple-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
									<path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
								</svg>
								<input id="search-field" class="block h-full w-full border-0 bg-transparent py-0 pl-8 pr-0 text-nbpurple-50 focus:ring-0 sm:text-sm placeholder:text-nbpurple-300" placeholder="Search..." type="search" name="search" disabled>
							</div>
						</form>
					</div>
					<!-- notification bell -->
					<div class="flex items-center gap-x-4 relative">
						<button type="button" class="p-2.5 text-nbpurple-50">
							<span class="sr-only">Notifications</span>
							<svg :class="{ 'animate-[wiggle_1s_ease-in-out_infinite]': false }" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
								<path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
							</svg>
							<!--
							<span x-cloak class="animate-ping absolute top-2 right-2.5 block h-1 w-1 rounded-full ring-2 ring-nbpurple-400 bg-nbpurple-600"></span>
							-->
						</button>
					</div>
				</div>

				<main x-data="{ GAI: $store.GAI }" class="h-full lg:pr-[41rem]">
					<!-- Main content -->
					<?php if ($perm->validatePermissionsLevelAny(1, 10, 99)) : ?>
						<div class="p-4">
							<form action="#" class="relative" x-data="{ assignOpen: false, labelOpen: false, dueDateOpen: false, title: '', prompt: '', selectedModel: '@cf/lykon/dreamshaper-8-lcm' }">
								<!-- Clear button -->
								<div x-cloak x-show="title.length > 0 || prompt.length > 0" class="flex-shrink-0 absolute top-1 right-1 z-10">
									<button type="button" class="inline-flex items-center rounded-full bg-nbpurple-600/50 p-1 sm:text-sm text-xs font-semibold text-nbpurple-50 shadow-sm hover:bg-nbpurple-500/50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nbpurple-600" @click="title = ''; prompt = ''; $store.GAI.clearImage()">
										<span class="sr-only">Clear fields</span>
										<svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-rotate-ccw">
											<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
											<path d="M3 3v5h5" />
										</svg>
									</button>
								</div>
								<!-- Form fields -->
								<div class="overflow-hidden rounded-lg border border-nbpurple-300 shadow-sm focus-within:border-nbpurple-500 focus-within:ring-1 focus-within:ring-nbpurple-500">
									<label for="title" class="sr-only">Title</label>
									<input x-model="title" type="text" name="title" id="title" class="block w-full border-0 pt-2.5 text-sm text-nbpurple-800 font-medium placeholder:text-nbpurple-400 focus:ring-0 bg-nbpurple-50" placeholder="Title (name your creation)">
									<label for="prompt" class="sr-only">Prompt</label>
									<textarea x-model="prompt" rows="3" name="prompt" id="prompt" class="block w-full resize-none border-0 py-0 text-nbpurple-900 placeholder:text-nbpurple-400 focus:ring-0 sm:text-sm sm:leading-6 bg-nbpurple-50" placeholder="(prompt) ex.: purple ostrich surfing a big wave ..."></textarea>

									<!-- Spacer element to match the height of the toolbar -->
									<div aria-hidden="true">
										<div class="py-2">
											<div class="py-px">
												<div class="h-8"></div>
											</div>
										</div>
									</div>
								</div>

								<div class="absolute inset-x-px bottom-0 bg-inherit">
									<div class="flex items-center justify-between space-x-3 border-t border-nbpurple-200 px-2 py-2 sm:px-3">
										<div class="flex">
											<div x-data="{
																		modelMenuOpen: false,
																		selectedModelTitle: 'Dream Shaper',
																		modelOptions: [
																			{ value: '@cf/lykon/dreamshaper-8-lcm', title: 'Dream Shaper', description: 'Stable Diffusion model that has been fine-tuned to be better at photorealism without sacrificing range.', disabled: false },
																			{ value: '@cf/bytedance/stable-diffusion-xl-lightning', title: 'SDXL-Lightning', description: 'SDXL-Lightning is a lightning-fast text-to-image generation model. It can generate high-quality 1024px images in a few steps.', disabled: <?= $perm->validatePermissionsLevelAny(10, 99) ? 'false' : 'true' ?>},
																			{ value: '@cf/stabilityai/stable-diffusion-xl-base-1.0', title: 'Stable Diffusion', description: 'Diffusion-based text-to-image generative model by Stability AI. Generates and modify images based on text prompts.', disabled: <?= $perm->validatePermissionsLevelAny(10, 99) ? 'false' : 'true' ?> },
																		]
																	}">
												<label id="listbox-label" class="sr-only">Change generative model</label>
												<div class="relative">
													<div class="inline-flex divide-x divide-nbpurple-700 rounded-md shadow-sm">
														<div class="inline-flex items-center gap-x-1.5 rounded-l-md bg-nbpurple-600 px-3 py-2 text-white shadow-sm">
															<svg class="-ml-0.5 h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
																<path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
															</svg>
															<p class="sm:text-sm text-xs font-semibold" x-text="selectedModelTitle"></p>
														</div>
														<button type="button" class="inline-flex items-center rounded-l-none rounded-r-md bg-nbpurple-600 p-2 hover:bg-nbpurple-700 focus:outline-none focus:ring-2 focus:ring-nbpurple-600 focus:ring-offset-2 focus:ring-offset-gray-50" aria-haspopup="listbox" aria-expanded="true" aria-labelledby="listbox-label" @click="modelMenuOpen = !modelMenuOpen">
															<span class="sr-only">Change generative model</span>
															<svg class="h-5 w-5 text-white" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
																<path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
															</svg>
														</button>
													</div>
													<ul x-cloak @click.outside="modelMenuOpen = false" x-show="modelMenuOpen" x-transition:enter="" x-transition:enter-start="" x-transition:enter-end="" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="absolute left-0 z-20 mt-2 sm:w-96 xs:w-72 w-64 origin-top-left divide-y divide-gray-200 overflow-hidden rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none" tabindex="-1" role="listbox" aria-labelledby="listbox-label" aria-activedescendant="listbox-option-0">
														<template x-for="(option, index) in modelOptions" :key="option.value">
															<li :class="{
																						'bg-nbpurple-100': selectedModel === option.value,
																						'text-gray-400 cursor-not-allowed': option.disabled,
																						'hover:bg-nbpurple-100 text-gray-900 cursor-default': !option.disabled
																					}" class="select-none p-4 text-sm" :id="'listbox-option-' + index" role="option" @click="if (!option.disabled) { selectedModel = option.value; selectedModelTitle = option.title; modelMenuOpen = false; }">
																<div class="flex flex-col">
																	<div class="flex justify-between">
																		<div class="flex items-center">
																			<p class="font-normal" x-text="option.title"></p>
																			<a x-show="option.disabled" href="/plans/" class="ml-2 text-nbpurple-600 text-xs hover:underline">
																				Upgrade to Advanced
																			</a>
																		</div>
																		<span class="text-nbpurple-600" x-show="selectedModel === option.value">
																			<svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
																				<path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
																			</svg>
																		</span>
																	</div>
																	<p class="text-gray-500 leading-tight mt-2" x-text="option.description"></p>
																</div>
															</li>
														</template>
													</ul>
												</div>
												<!-- Hidden input field to store the selected model value -->
												<input type="hidden" name="selectedModel" x-model="selectedModel">
											</div>
										</div>
										<div class="flex-shrink-0">
											<button @click="$store.GAI.generateImage(title, prompt, selectedModel)" type="button" class="inline-flex items-center rounded-md bg-nbpurple-600 px-3 py-2 sm:text-sm text-xs h-9 font-semibold text-white shadow-sm hover:bg-nbpurple-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nbpurple-600 disabled:bg-nbpurple-400" :disabled="prompt.trim() === '' || GAI.ImageLoading === true">Generate</button>
										</div>
									</div>
								</div>
							</form>
						</div>

						<!-- Generate image -->
						<div class="bg-black/10 rounded-lg shadow-xl my-8 mx-auto w-11/12 max-w-2xl overflow-hidden">
							<div class="bg-black/50 p-4 relative transition-transform">
								<p x-cloak x-show="!GAI.ImageShow && !GAI.ImageLoading" class="flex items-center justify-center text-nbpurple-200 text-center text-lg h-24 lg:h-72" x-transition:enter="transition-opacity duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
									No image to display, generate a new one.
								</p>
								<p x-cloak x-show="!GAI.ImageShow && GAI.ImageLoading" class="animate-pulse flex flex-col items-center justify-center text-nbpurple-200 text-center text-lg h-72" x-transition:enter="transition-opacity duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
									<span>Generating your image ...</span>
									<br />
									<!-- Rocket boom! -->
									<svg x-cloak x-show="GAI.ImageLoading" class="size-24 text-nbpurple-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
										<path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.631 8.41m5.96 5.96a14.926 14.926 0 0 1-5.841 2.58m-.119-8.54a6 6 0 0 0-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 0 0-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 0 1-2.448-2.448 14.9 14.9 0 0 1 .06-.312m-2.24 2.39a4.493 4.493 0 0 0-1.757 4.306 4.493 4.493 0 0 0 4.306-1.758M16.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
									</svg>
								</p>
								<!-- Clear button -->
								<div x-cloak x-show="GAI.ImageShow" class="flex-shrink-0 absolute top-2 right-2 z-10">
									<button type="button" class="inline-flex items-center rounded-full bg-nbpurple-600/50 p-1 sm:text-sm text-xs font-semibold text-nbpurple-50 shadow-sm hover:bg-nbpurple-500/50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nbpurple-600" @click="GAI.clearImage()">
										<span class="sr-only">Clear image</span>
										<svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-rotate-ccw">
											<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
											<path d="M3 3v5h5" />
										</svg>
									</button>
								</div>
								<!-- /Clear button -->
								<img x-cloak x-show="GAI.ImageShow" @load="GAI.ImageShow = true; GAI.ImageLoading = false" :src="GAI.file.thumb" :srcset="GAI.file.srcset" :sizes="GAI.file.sizes" :alt="GAI.file.title || GAI.file.name" :width="GAI.file.width" :height="GAI.file.height" loading="eager" class="w-full transition-transform" x-transition:enter="transition-opacity ease-in duration-750" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" />
							</div>
							<div x-cloak x-show="GAI.ImageShow" class="bg-black/20 px-6 py-4 sm:flex sm:justify-between">
								<div class="mb-4 sm:mb-0">
									<p class="text-sm text-gray-300" x-text="GAI.file.title ? 'Title: ' + GAI.file.title : 'Name: ' + GAI.file.name"></p>
									<p class="text-sm text-gray-300">Size: <span x-text="formatBytes(GAI.file.size)"></span></p>
								</div>
								<div>
									<p class="text-sm text-gray-300">Dimensions: <span x-text="GAI.ImageDimensions"></span></p>
									<p class="text-sm text-gray-300">URL: <a :href="GAI.file.url" target="_blank" x-text="$store.GAI.ImageUrl"></a>
										<svg class="size-3 ml-1 inline-block" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
											<path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
										</svg>
									</p>
								</div>
							</div>
						</div>
						<!-- /Generate image -->
					<?php else : ?>
						<div class="p-4">
							<div class="bg-black/10 rounded-lg shadow-xl my-8 mx-auto w-11/12 max-w-2xl p-4 h-32 align-middle">
								<p class="text-nbpurple-200 text-center text-lg">Your plan does not include Generative AI (AI Early Access).</p>
								<!-- Upgrade button -->
								<div class="flex justify-center mt-4">
									<a href="/plans/" class="inline-flex items-center rounded-md bg-nbpurple-600 px-3 py-2 sm:text-sm text-xs h-9 font-semibold text-white shadow-sm hover:bg-nbpurple-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nbpurple-600">Upgrade to Creator or Advanced Plan</a>
								</div>
							</div>
						</div>
					<?php endif; ?>
					<!-- /Main content -->
				</main>

				<!-- Activity feed -->
				<aside x-data="{
					showScrollButton: false,
					multiSelect: false,
					selectedItems: [],
					toggleSelected(id) {
						const index = this.selectedItems.indexOf(id);
						if (index === -1) {
							this.selectedItems.push(id);
						} else {
							this.selectedItems.splice(index, 1);
						}
					},
					isSelected(id) {
						return this.selectedItems.includes(id);
					},
					toggleMultiSelect() {
						this.multiSelect = !this.multiSelect;
						if (!this.multiSelect) {
							this.selectedItems = [];
						}
					},
				}" x-ref="sidebar" @scroll.window.throttle="showScrollButton = window.pageYOffset > 500" @scroll.throttle="showScrollButton = $refs.sidebar.scrollTop > 500" class="bg-nbpurple-900/10 lg:fixed lg:bottom-0 lg:right-0 lg:top-16 lg:w-[40rem] lg:overflow-y-auto lg:border-l lg:border-nbpurple-950/5">
					<!-- Floating bar -->
					<div class="z-10 sticky top-16 lg:top-0 bg-nbpurple-900/75 py-2 px-2 align-middle flex items-center justify-between">
						<h3 class="text-sm sm:text-base font-semibold leading-6 text-nbpurple-100" x-text="$store.menuStore.activeFolder"></h3>
						<div class="flex ml-4 mt-0 transition-all">
							<button @click="toggleMultiSelect()" type="button" class="ml-3 inline-flex items-center rounded-md bg-nbpurple-600 px-3 py-2 text-xs font-semibold text-nbpurple-50 shadow-sm hover:bg-nbpurple-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nbpurple-600">
								<svg x-show="!multiSelect" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-1">
									<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
								</svg>
								<svg x-cloak x-show="multiSelect" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-1">
									<path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
								</svg>
								<span x-text="multiSelect ? 'Cancel' : 'Select'"></span>
							</button>
						</div>
					</div>
					<!-- /Floating bar -->
					<!-- File upload area -->
					<!-- /File upload area -->
					<!-- File actions -->
					<!-- Activity feed content -->
					<div class=" p-4" x-data="Alpine.store('fileStore')" x-init="if (files.length === 0) fetchFiles($store.menuStore.activeFolder); $watch('$store.menuStore.activeFolder', folder => fetchFiles(folder))">
						<ul role="list" class="grid grid-cols-2 gap-x-4 gap-y-8 md:grid-cols-2 md:gap-x-4 xl:gap-x-6">
							<template x-if="loading">
								<li class="relative">
									<div class="group aspect-h-7 aspect-w-10 block w-full overflow-hidden rounded-lg bg-black/50 focus-within:ring-2 focus-within:ring-indigo-500 focus-within:ring-offset-2 focus-within:ring-offset-gray-100">
										<div class="pointer-events-none object-cover group-hover:opacity-75 h-full flex items-center justify-center">
											<svg class="pointer-events-none object-cover group-hover:opacity-75 h-full w-full text-nbpurple-400 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
											</svg>
										</div>
									</div>
								</li>
							</template>
							<template x-if="!loading && files.length === 0">
								<li class="relative">
									<div class="group aspect-h-7 aspect-w-10 block w-full overflow-hidden rounded-lg bg-black/50 focus-within:ring-2 focus-within:ring-indigo-500 focus-within:ring-offset-2 focus-within:ring-offset-gray-100">
										<svg class="pointer-events-none object-cover group-hover:opacity-75 h-full w-full text-nbpurple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
										</svg>
									</div>
								</li>
							</template>
							<template x-for="file in files" :key="file.id">
								<li :id="file.id" class="relative" x-data="{ showMediaActions: false }">
									<div x-cloak x-show="file.flag === 1" class="cursor-pointer absolute -top-[0.85rem] left-3 z-10">
										<span class="inline-flex items-center rounded-md bg-black/85 px-2 py-1 text-xs font-medium text-yellow-300 ring-1 ring-inset ring-yellow-400/20">
											Creator
											<span class="hidden xs:inline ml-1">Page</span>
										</span>
									</div>
									<div class="group aspect-h-7 aspect-w-10 block w-full overflow-hidden rounded-lg bg-black/50 focus-within:ring-2 focus-within:ring-indigo-500 focus-within:ring-offset-2 focus-within:ring-offset-gray-100">
										<svg class="absolute inset-0 pointer-events-none object-cover group-hover:opacity-75 h-full w-full text-nbpurple-400 animate-pulse" x-show="!file.loaded" fill="none" viewBox="0 0 24 24" stroke="currentColor">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
										</svg>

										<!-- Media actions -->
										<div x-cloak x-show="showMediaActions" @click.outside="showMediaActions = false || deleteConfirmation.isOpen || shareMedia.isOpen || moveToFolder.isOpen" class="absolute inset-0 object-contain bg-black/80 py-1 px-3 sm:py-2 z-10 flex flex-col">
											<div class="m-auto w-5/6 grid gap-1 grid-cols-3 place-items-center">
												<!-- Buttons -->
												<!-- Delete button -->
												<button x-data="{deleteClick: false}" @click="deleteConfirmation.open(file.id)" class="p-1 bg-nbpurple-600/10 text-white rounded-md hover:bg-nbpurple-700/10 focus:outline-none focus:ring-2 focus:ring-nbpurple-500/10" aria-label="Delete media file">
													<svg x-show="!deleteClick" class="max-h-11 w-full inline-block" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" x-transition:enter="transition-opacity duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
														<path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
													</svg>
													<svg x-cloak x-show="deleteClick" class="max-h-11 animate-[pulse_3s_ease-in-out_infinite] w-full inline-block" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" x-transition:enter="transition-opacity duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
														<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
													</svg>
												</button>
												<!-- Share button -->
												<button x-data="{shareClick: false}" @click="shareMedia.open(file.id)" class="p-1 bg-nbpurple-600/10 text-white rounded-md hover:bg-nbpurple-700/10 focus:outline-none focus:ring-2 focus:ring-nbpurple-500/10" aria-label="Share media file">
													<svg x-show="!shareClick" class="max-h-11 w-full inline-block" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" x-transition:enter="transition-opacity duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
														<path stroke-linecap="round" stroke-linejoin="round" d="M7.5 7.5h-.75A2.25 2.25 0 0 0 4.5 9.75v7.5a2.25 2.25 0 0 0 2.25 2.25h7.5a2.25 2.25 0 0 0 2.25-2.25v-7.5a2.25 2.25 0 0 0-2.25-2.25h-.75m0-3-3-3m0 0-3 3m3-3v11.25m6-2.25h.75a2.25 2.25 0 0 1 2.25 2.25v7.5a2.25 2.25 0 0 1-2.25 2.25h-7.5a2.25 2.25 0 0 1-2.25-2.25v-.75" />
													</svg>
													<svg x-cloak x-show="shareClick" class="max-h-11 animate-[pulse_3s_ease-in-out_infinite] w-full inline-block" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" x-transition:enter="transition-opacity duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
														<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
													</svg>
												</button>
												<!-- Move button -->
												<button x-data="{moveClick: false}" @click="moveToFolder.open(file.id)" class="p-1 bg-nbpurple-600/10 text-white rounded-md hover:bg-nbpurple-700/10 focus:outline-none focus:ring-2 focus:ring-nbpurple-500/10" aria-label="Move media file to folder">
													<svg x-show="!moveClick" class="max-h-11 w-full inline-block" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" x-transition:enter="transition-opacity duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
														<path stroke-linecap="round" stroke-linejoin="round" d="M2 9V5a2 2 0 0 1 2-2h3.9a2 2 0 0 1 1.69.9l.81 1.2a2 2 0 0 0 1.67.9H20a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-1" />
														<path stroke-linecap="round" stroke-linejoin="round" d="M2 13h10" />
														<path stroke-linecap="round" stroke-linejoin="round" d="m9 16 3-3-3-3" />
													</svg>
													<svg x-cloak x-show="moveClick" class="max-h-11 animate-[pulse_3s_ease-in-out_infinite] w-full inline-block" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" x-transition:enter="transition-opacity duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
														<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
													</svg>
												</button>
												<!-- Copy link -->
												<button x-data="{copyClick: false}" @click="copyUrlToClipboard(file.url); copyClick = true; setTimeout(() => copyClick = false, 2000); showToast = true" class="p-1 xs:hidden bg-nbpurple-600/10 text-white rounded-md hover:bg-nbpurple-700/10 focus:outline-none focus:ring-2 focus:ring-nbpurple-500/10" aria-label="Copy image URL to clipboard">
													<svg x-show="!copyClick" class="max-h-11 w-full inline-block" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" x-transition:enter="transition-opacity duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
														<path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75" />
													</svg>
													<svg x-cloak x-show="copyClick" class="max-h-11 animate-[pulse_3s_ease-in-out_infinite] w-full inline-block" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" x-transition:enter="transition-opacity duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
														<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
													</svg>
												</button>
											</div>
										</div>
										<!-- /Media actions -->

										<img x-intersect.margin.100px="true" :src="file.thumb" :srcset="file.srcset" :sizes="file.sizes" :alt="file.name" :width="file.width" :height="file.height" loading="lazy" class="pointer-events-none object-contain group-hover:opacity-75" x-cloak @load="file.loaded = true" x-transition:enter="transition-opacity ease-in duration-1000" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" />
										<button @click="openModal(file)" type="button" :class="{'hidden pointer-events-none': showMediaActions}" class="absolute inset-0 focus:outline-none">
											<span class="sr-only" x-text="'View details for ' + file.name"></span>
											<div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-80 transition-opacity duration-300">
												<svg class="size-1/3 text-nbpurple-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
													<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
												</svg>
											</div>
										</button>
									</div>
									<div class="flex justify-between items-center">
										<div>
											<p class="pointer-events-none mt-2 block truncate text-sm font-medium text-nbpurple-300" x-text="file.name"></p>
											<p class="pointer-events-none block text-sm font-medium text-nbpurple-500" x-text="formatBytes(file.size)"></p>
										</div>
										<div x-show="!multiSelect" x-data="{copyClick: false}">
											<button @click="copyUrlToClipboard(file.url); copyClick = true; setTimeout(() => copyClick = false, 2000); showToast = true" class="hidden xs:inline-block mt-2 px-2 py-1 bg-nbpurple-600 text-nbpurple-50 rounded-md hover:bg-nbpurple-700 focus:outline-none focus:ring-2 focus:ring-nbpurple-500" aria-label="Copy image URL to clipboard">
												<svg x-show="!copyClick" class="size-6 inline-block" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" x-transition:enter="transition-opacity duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
													<path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75" />
												</svg>
												<svg x-cloak x-show="copyClick" class="animate-[pulse_3s_ease-in-out_infinite] size-6 inline-block" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" x-transition:enter="transition-opacity duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
													<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
												</svg>
											</button>
											<button @click="showMediaActions = !showMediaActions" class="mt-2 px-2 py-1 bg-nbpurple-600 text-nbpurple-50 rounded-md hover:bg-nbpurple-700 focus:outline-none focus:ring-2 focus:ring-nbpurple-500" aria-label="Copy image URL to clipboard">
												<svg class="size-6 inline-block text-nbpurple-50" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
													<path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
												</svg>
											</button>
										</div>
										<div x-cloak x-show="multiSelect">
											<button @click="toggleSelected(file.id)" type="button" :class="{ 'bg-nbpurple-600 p-1.5 text-nbpurple-50 hover:bg-nbpurple-500': isSelected(file.id), 'text-nbpurple-600 p-1.5 bg-nbpurple-50 hover:bg-nbpurple-200': !isSelected(file.id) }" class="rounded-full p-1.5 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nbpurple-600">
												<svg x-show="!isSelected(file.id)" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
													<path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
												</svg>
												<svg x-show="isSelected(file.id)" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
													<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
												</svg>
											</button>
										</div>
									</div>
								</li>
							</template>
						</ul>
					</div>

					<!-- Scroll to top button -->
					<div x-cloak x-show="showScrollButton" :class="{ 'bottom-6': !multiSelect, 'bottom-16': multiSelect }" class="sticky ml-6 z-10 transition-all w-fit">
						<button @click="showScrollButton = false; window.scrollTo({ top: 0, behavior: 'smooth' }); $refs.sidebar.scrollTo({ top: 0, behavior: 'smooth' })" type="button" class="bg-nbpurple-500 text-nbpurple-50 rounded-full p-2 shadow-md hover:bg-nbpurple-600 focus:outline-none focus:ring-2 focus:ring-nbpurple-500">
							<svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
								<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 18.75 7.5-7.5 7.5 7.5" />
								<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 7.5-7.5 7.5 7.5" />
							</svg>
						</button>
					</div>
					<!-- /Scroll to top button -->

					<!-- Bottom bar -->
					<div x-cloak x-show="multiSelect" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-y-full" x-transition:enter-end="opacity-100 transform translate-y-0" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 transform translate-y-0" x-transition:leave-end="opacity-0 transform translate-y-full" class="z-10 sticky bottom-0 bg-nbpurple-900/75 py-2 px-2 align-middle flex items-center justify-center sm:justify-end">
						<div class="flex items-center">
							<button @click="$store.fileStore.deleteConfirmation.open(selectedItems, () => { toggleMultiSelect() })" :disabled="!selectedItems.length" type="button" class="inline-flex items-center rounded-md bg-nbpurple-600 disabled:bg-nbpurple-400 px-3 py-2 text-xs font-semibold text-nbpurple-50 shadow-sm hover:bg-nbpurple-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nbpurple-600">
								<svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
									<path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
								</svg>
								<span class="hidden xs:inline">Delete </span>(<span x-text="selectedItems.length"></span>)
							</button>
							<button @click="$store.fileStore.shareMedia.open(selectedItems, () => { toggleMultiSelect() })" :disabled="!selectedItems.length" type="button" class="ml-3 inline-flex items-center rounded-md bg-nbpurple-600 disabled:bg-nbpurple-400 px-3 py-2 text-xs font-semibold text-nbpurple-50 shadow-sm hover:bg-nbpurple-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nbpurple-600">
								<svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
									<path stroke-linecap="round" stroke-linejoin="round" d="M7.5 7.5h-.75A2.25 2.25 0 0 0 4.5 9.75v7.5a2.25 2.25 0 0 0 2.25 2.25h7.5a2.25 2.25 0 0 0 2.25-2.25v-7.5a2.25 2.25 0 0 0-2.25-2.25h-.75m0-3-3-3m0 0-3 3m3-3v11.25m6-2.25h.75a2.25 2.25 0 0 1 2.25 2.25v7.5a2.25 2.25 0 0 1-2.25 2.25h-7.5a2.25 2.25 0 0 1-2.25-2.25v-.75" />
								</svg>
								<span class="hidden xs:inline">Share </span>(<span x-text="selectedItems.length"></span>)
							</button>
							<button @click="$store.fileStore.moveToFolder.open(selectedItems, () => { toggleMultiSelect() })" :disabled="!selectedItems.length" type="button" class="ml-3 inline-flex items-center rounded-md bg-nbpurple-600 disabled:bg-nbpurple-400 px-3 py-2 text-xs font-semibold text-nbpurple-50 shadow-sm hover:bg-nbpurple-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nbpurple-600">
								<svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
									<path stroke-linecap="round" stroke-linejoin="round" d="M2 9V5a2 2 0 0 1 2-2h3.9a2 2 0 0 1 1.69.9l.81 1.2a2 2 0 0 0 1.67.9H20a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-1" />
									<path stroke-linecap="round" stroke-linejoin="round" d="M2 13h10" />
									<path stroke-linecap="round" stroke-linejoin="round" d="m9 16 3-3-3-3" />
								</svg>
								<span class="hidden xs:inline">Move </span>(<span x-text="selectedItems.length"></span>)
							</button>
						</div>
					</div>
					<!-- /Bottom bar -->
				</aside>
			</div>
		</div>

		<!-- Delete confirmation window -->
		<div x-cloak x-data="{FS: $store.fileStore}" class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
			<div x-show="FS.deleteConfirmation.isOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

			<div x-show="FS.deleteConfirmation.isOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="fixed inset-0 z-50 overflow-y-auto">
				<div class="flex min-h-screen items-end justify-center p-4 text-center sm:items-center sm:p-0">
					<div @click.outside="!FS.deleteConfirmation.isLoading && FS.deleteConfirmation.close(true)" class="relative transform overflow-hidden rounded-lg bg-nbpurple-50 text-left shadow-xl transition-all mb-16 sm:my-8 sm:w-full sm:max-w-lg">
						<div class="bg-nbpurple-50 px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
							<div class="sm:flex sm:items-start">
								<div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
									<svg class="size-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
										<path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
									</svg>
								</div>
								<div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
									<h3 class="text-base font-semibold leading-6 text-gray-900" id="modal-title">Confirm Delete</h3>
									<div class="mt-2">
										<p class="text-sm text-gray-500">
											Are you sure you want to delete the selected <span class="font-bold" x-text="FS.deleteConfirmation.selectedFiles.length"></span> file(s)? This action cannot be undone.
										</p>
										<!-- List of selected media -->
										<div class="flex items-center justify-center">
											<div class="isolate flex -space-x-2 overflow-hidden pl-3 py-2">
												<template x-if="FS.deleteConfirmation.selectedFiles.length === 1">
													<template x-for="(file, index) in (FS.deleteConfirmation.selectedFiles.length > 5 ? FS.deleteConfirmation.selectedFiles.slice(0, 5) : FS.deleteConfirmation.selectedFiles)" :key="file.id">
														<img :class="'relative z-' + (50 - index * 10)" class="inline-block w-14 h-12 object-cover rounded-full ring-2 ring-white" :src="file.thumb" :alt="file.name">
													</template>
												</template>
												<template x-if="FS.deleteConfirmation.selectedFiles.length > 1">
													<template x-for="(file, index) in (FS.deleteConfirmation.selectedFiles.length > 5 ? FS.deleteConfirmation.selectedFiles.slice(0, 5) : FS.deleteConfirmation.selectedFiles)" :key="file.id">
														<img :class="'relative z-' + (50 - index * 10)" class="inline-block w-12 h-12 object-cover rounded-full ring-2 ring-white" :src="file.thumb" :alt="file.name">
													</template>
												</template>
												<template x-if="FS.deleteConfirmation.selectedFiles.length > 5">
													<div class="relative z-0 inline-flex items-center justify-center w-12 h-12 rounded-full bg-nbpurple-600 text-white ring-2 ring-white">
														<span class="text-xs xs:text-sm font-medium">+<span x-text="FS.deleteConfirmation.selectedFiles.length - 5"></span></span>
													</div>
												</template>
											</div>
										</div>
										<!-- /List of selected media -->
										<!-- Notice about deletion -->
										<div class="mt-3">
											<p class="text-sm text-red-500">Note: Deleting media won't immediately remove it from browser/client caches, other proxies, especially if it has been already publicly shared.</p>
										</div>
										<!-- Error message -->
										<div x-cloak x-show="FS.deleteConfirmation.isError" class="mt-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
											<strong class="font-bold">Error!</strong>
											<span class="block sm:inline">An error occurred while deleting the files.</span>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="bg-nbpurple-50 px-4 py-3 gap-3 flex flex-row-reverse sm:px-6">
							<button :disabled="FS.deleteConfirmation.isLoading" @click="FS.confirmDelete()" type="button" class="mt-3 inline-flex w-full justify-center rounded-md bg-red-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
								<svg x-show="FS.deleteConfirmation.isLoading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
									<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
									<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
								</svg>
								Delete
							</button>
							<button :disabled="FS.deleteConfirmation.isLoading" @click="FS.deleteConfirmation.close(true)" type="button" class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-nbpurple-500 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancel</button>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Share window -->
		<div x-cloak x-data="{FS: $store.fileStore}" class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
			<div x-show="FS.shareMedia.isOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

			<div x-show="FS.shareMedia.isOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="fixed inset-0 z-50 overflow-y-auto">
				<div class="flex min-h-screen items-end justify-center p-4 text-center sm:items-center sm:p-0">
					<div @click.outside="!FS.shareMedia.isLoading && FS.shareMedia.close(true)" class="relative transform overflow-hidden rounded-lg bg-nbpurple-50 text-left shadow-xl transition-all mb-16 sm:my-8 w-full max-w-lg">
						<div class="bg-nbpurple-50 px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
							<div class="sm:flex sm:items-start">
								<div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-nbpurple-100 sm:mx-0 sm:h-10 sm:w-10">
									<svg class="size-6 text-nbpurple-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
										<path stroke-linecap="round" stroke-linejoin="round" d="M7.5 7.5h-.75A2.25 2.25 0 0 0 4.5 9.75v7.5a2.25 2.25 0 0 0 2.25 2.25h7.5a2.25 2.25 0 0 0 2.25-2.25v-7.5a2.25 2.25 0 0 0-2.25-2.25h-.75m0-3-3-3m0 0-3 3m3-3v11.25m6-2.25h.75a2.25 2.25 0 0 1 2.25 2.25v7.5a2.25 2.25 0 0 1-2.25 2.25h-7.5a2.25 2.25 0 0 1-2.25-2.25v-.75" />
									</svg>
								</div>
								<div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
									<h3 class="text-base font-semibold leading-6 text-gray-900" id="modal-title">Share Media</h3>
									<div class="mt-2">
										<!-- List of selected media -->
										<div class="flex items-center justify-center">
											<div class="isolate flex -space-x-2 overflow-hidden pl-3 py-2">
												<template x-if="FS.shareMedia.selectedFiles.length === 1">
													<template x-for="(file, index) in (FS.shareMedia.selectedFiles.length > 5 ? FS.shareMedia.selectedFiles.slice(0, 5) : FS.shareMedia.selectedFiles)" :key="file.id">
														<img :class="'relative z-' + (50 - index * 10)" class="inline-block w-14 h-12 object-cover rounded-full ring-2 ring-white" :src="file.thumb" :alt="file.name">
													</template>
												</template>
												<template x-if="FS.shareMedia.selectedFiles.length > 1">
													<template x-for="(file, index) in (FS.shareMedia.selectedFiles.length > 5 ? FS.shareMedia.selectedFiles.slice(0, 5) : FS.shareMedia.selectedFiles)" :key="file.id">
														<img :class="'relative z-' + (50 - index * 10)" class="inline-block w-12 h-12 object-cover rounded-full ring-2 ring-white" :src="file.thumb" :alt="file.name">
													</template>
												</template>
												<template x-if="FS.shareMedia.selectedFiles.length > 5">
													<div class="relative z-0 inline-flex items-center justify-center w-12 h-12 rounded-full bg-nbpurple-600 text-white ring-2 ring-white">
														<span class="text-xs xs:text-sm font-medium">+<span x-text="FS.shareMedia.selectedFiles.length - 5"></span></span>
													</div>
												</template>
											</div>
										</div>
										<!-- /List of selected media -->
										<!-- Sharing options -->
										<div class="flex justify-center">
											<div x-data="{
																			enabled: false,
																			supported: <?= $perm->validatePermissionsLevelAny(1, 10, 99) ? 'true' : 'false' ?>,
																			init() {
																				this.enabled = this.getFlag();
																				this.$watch('FS.shareMedia.selectedFiles', () => {
																					this.enabled = this.getFlag();
																				});
																			},
																			getFlag() {
																				// return FS.shareMedia.selectedFiles.length === 1 ? FS.shareMedia.selectedFiles[0].flag === 1 : false;
																				// If even one file is flagged, enable the switch
																				return FS.shareMedia.selectedFiles.some(file => file.flag === 1);
																			}
																		}" class="flex mt-3">
												<button :disabled="!supported" :class="{
																																	'cursor-not-allowed': !supported,
																																	'cursor-pointer': supported,
																																	'bg-nbpurple-600': enabled,
																																	'bg-nbpurple-300': !enabled
																																}" type="button" class="relative inline-flex h-6 w-11 flex-shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-nbpurple-600 focus:ring-offset-2" role="switch" :aria-checked="enabled" aria-labelledby="creator-page-share-single" @click="FS.shareMediaCreatorConfirm(!enabled)">
													<span aria-hidden="true" :class="enabled ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-nbpurple-50 shadow ring-0 transition duration-200 ease-in-out"></span>
												</button>
												<span class="ml-3 text-sm" id="creator-page-share-single">
													<span class="font-medium text-nbpurple-900">Share on Creators Page</span>
													<a x-show="!supported" href="/plans/" class="ml-2 text-nbpurple-600 text-xs hover:underline">
														Upgrade to Creator+
													</a>
												</span>
											</div>
										</div>
										<!-- Error message -->
										<div x-cloak x-show="FS.shareMedia.isError" class="mt-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
											<strong class="font-bold">Error!</strong>
											<span class="block sm:inline">An error occurred while sharing the files.</span>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="bg-nbpurple-50 px-4 py-3 gap-3 justify-center sm:justify-normal flex flex-row-reverse sm:px-6">
							<button @click="FS.shareMedia.close()" :disabled="FS.shareMedia.isLoading" type="button" class="mt-3 inline-flex w-1/2 justify-center rounded-md bg-nbpurple-500 px-4 py-2 text-base font-medium text-nbpurple-50 shadow-sm hover:bg-nbpurple-200 focus:outline-none focus:ring-2 focus:ring-nbpurple-500 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
								<svg x-show="FS.shareMedia.isLoading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-nbpurple-50" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
									<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
									<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
								</svg>
								Done
							</button>
							<button @click="FS.shareMedia.close(true)" type="button" class="mt-3 inline-flex justify-center rounded-md bg-nbpurple-200 px-4 py-2 text-base font-medium text-nbpurple-700 shadow-sm hover:bg-nbpurple-50 focus:outline-none focus:ring-2 focus:ring-nbpurple-500 focus:ring-offset-2 sm:order-0 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
								Cancel
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Move to folder modal -->
		<div x-cloak x-data="{ FS: $store.fileStore }" class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
			<!-- Background overlay -->
			<div x-show="FS.moveToFolder.isOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
			<!-- Modal content -->
			<div x-show="FS.moveToFolder.isOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-0 sm:-translate-y-4" x-transition:enter-end="opacity-100 translate-y-0 sm:translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-0 sm:-translate-y-4 sm:scale-95" class="fixed inset-0 z-50 overflow-y-auto">
				<div class="flex min-h-screen items-end justify-center p-4 text-center sm:items-center sm:p-0">
					<div @click.outside="!FS.moveToFolder.isLoading && FS.moveToFolder.close(true)" class="relative transform overflow-hidden rounded-lg bg-nbpurple-50 text-left shadow-xl transition-all min-h-[50vh] max-h-[80vh] my-8 mb-24 w-full sm:max-w-lg flex flex-col">
						<div class="bg-nbpurple-50 px-4 pt-5 pb-4 sm:p-6 sm:pb-4 overflow-y-auto flex-grow">
							<!-- List of selected media -->
							<div class="flex items-center justify-center">
								<div class="isolate flex -space-x-2 overflow-hidden pl-3 py-2">
									<template x-if="FS.moveToFolder.selectedFiles.length === 1">
										<template x-for="(file, index) in (FS.moveToFolder.selectedFiles.length > 5 ? FS.moveToFolder.selectedFiles.slice(0, 5) : FS.moveToFolder.selectedFiles)" :key="file.id">
											<img :class="'relative z-' + (50 - index * 10)" class="inline-block w-14 h-12 object-cover rounded-full ring-2 ring-white" :src="file.thumb" :alt="file.name">
										</template>
									</template>
									<template x-if="FS.moveToFolder.selectedFiles.length > 1">
										<template x-for="(file, index) in (FS.moveToFolder.selectedFiles.length > 5 ? FS.moveToFolder.selectedFiles.slice(0, 5) : FS.moveToFolder.selectedFiles)" :key="file.id">
											<img :class="'relative z-' + (50 - index * 10)" class="inline-block w-12 h-12 object-cover rounded-full ring-2 ring-white" :src="file.thumb" :alt="file.name">
										</template>
									</template>
									<template x-if="FS.moveToFolder.selectedFiles.length > 5">
										<div class="relative z-0 inline-flex items-center justify-center w-12 h-12 rounded-full bg-nbpurple-600 text-white ring-2 ring-white">
											<span class="text-xs xs:text-sm font-medium">+<span x-text="FS.moveToFolder.selectedFiles.length - 5"></span></span>
										</div>
									</template>
								</div>
							</div>
							<!-- /List of selected media -->
							<div class="sm:flex sm:items-start">
								<div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
									<div class="mt-2 flex items-center">
										<label for="combobox" class="block text-sm font-medium leading-6 text-gray-900 mr-2">Select Folder</label>
										<div class="relative w-full z-auto">
											<input id="combobox" type="text" x-model="FS.moveToFolder.selectedFolderName" @input="FS.moveToFolder.searchTerm = $event.target.value; FS.moveToFolder.isDropdownOpen = true" @click="FS.moveToFolder.toggleDropdown()" @click.away="FS.moveToFolder.isDropdownOpen = false" class="w-full rounded-md border-0 bg-nbpurple-50 py-1.5 pl-3 pr-12 text-nbpurple-900 shadow-sm ring-1 ring-inset ring-nbpurple-300 focus:ring-2 focus:ring-inset focus:ring-nbpurple-600 sm:text-sm sm:leading-6" role="combobox" aria-controls="options" :aria-expanded="FS.moveToFolder.isDropdownOpen.toString()">
											<button type="button" @click.stop="FS.moveToFolder.toggleDropdown()" class="absolute inset-y-0 right-0 flex items-center rounded-r-md px-2 focus:outline-none">
												<svg class="h-5 w-5 text-nbpurple-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
													<path fill-rule="evenodd" d="M10 3a.75.75 0 01.55.24l3.25 3.5a.75.75 0 11-1.1 1.02L10 4.852 7.3 7.76a.75.75 0 01-1.1-1.02l3.25-3.5A.75.75 0 0110 3zm-3.76 9.2a.75.75 0 011.06.04l2.7 2.908 2.7-2.908a.75.75 0 111.1 1.02l-3.25 3.5a.75.75 0 01-1.1 0l-3.25-3.5a.75.75 0 01.04-1.06z" clip-rule="evenodd" />
												</svg>
											</button>
											<ul x-show="FS.moveToFolder.isDropdownOpen" @click.away="FS.moveToFolder.isDropdownOpen = false" class="absolute z-10 mt-1 max-h-40 w-full overflow-auto rounded-md bg-nbpurple-50 py-1 text-base shadow-lg ring-1 ring-nbpurple-950 ring-opacity-5 focus:outline-none sm:text-sm" id="options" role="listbox">
												<template x-for="folder in $store.menuStore.folders.filter(f => f.name.toLowerCase().includes(FS.moveToFolder.searchTerm.toLowerCase()))">
													<li class="relative cursor-default select-none py-2 pl-3 pr-9 text-gray-900" :id="'option-' + folder.id" role="option" @click="FS.moveToFolder.destinationFolderId = folder.id; FS.moveToFolder.selectedFolderName = folder.name; FS.moveToFolder.isDropdownOpen = false; FS.moveToFolder.searchTerm = ''" @mouseenter="FS.moveToFolder.hoveredFolder = folder.id" @mouseleave="FS.moveToFolder.hoveredFolder = null" :class="{ 'bg-nbpurple-600 text-nbpurple-50': folder.id === FS.moveToFolder.hoveredFolder, 'text-nbpurple-900': folder.id !== FS.moveToFolder.hoveredFolder }">
														<div class="flex">
															<span x-text="folder.name" class="truncate" :class="{ 'font-semibold': folder.id === FS.moveToFolder.destinationFolderId }"></span>
														</div>
														<span x-show="folder.id === FS.moveToFolder.destinationFolderId" class="absolute inset-y-0 right-0 flex items-center pr-4" :class="{ 'text-nbpurple-50': folder.id === FS.moveToFolder.hoveredFolder, 'text-nbpurple-600': folder.id !== FS.moveToFolder.hoveredFolder }">
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
						<div class="bg-nbpurple-50 px-4 py-3 sm:px-6 sm:flex sm:flex-wrap sm:justify-end">
							<button x-data="{
                    folderSelected: false,
                    init() {
                      this.$watch('FS.moveToFolder.destinationFolderId', () => {
                        this.folderSelected = FS.moveToFolder.destinationFolderId !== null;
                      });
                    },
                    getSelectedCount() { return FS.moveToFolder.selectedFiles.length; },
                    isValidFolderSelected() { return FS.moveToFolder.destinationFolderId !== null; }
                  }" x-show="!FS.moveToFolder.isLoading" @click="FS.moveToFolderConfirm()" type="button" :disabled="!folderSelected || FS.moveToFolder.isLoading" :class="{ 'opacity-50 cursor-not-allowed': !folderSelected }" class="inline-flex justify-center rounded-md bg-nbpurple-600 px-4 py-2 text-base font-medium text-nbpurple-50 shadow-sm hover:bg-nbpurple-700 focus:outline-none focus:ring-2 focus:ring-nbpurple-500 focus:ring-offset-2 sm:order-1 sm:ml-3 sm:w-auto sm:text-sm">
								Move&nbsp;<span x-text="getSelectedCount()"></span>&nbsp;file(s) to folder
							</button>
							<button x-show="FS.moveToFolder.isLoading" type="button" class="inline-flex justify-center rounded-md bg-nbpurple-600 px-4 py-2 text-base font-medium text-nbpurple-50 shadow-sm hover:bg-nbpurple-700 focus:outline-none focus:ring-2 focus:ring-nbpurple-500 focus:ring-offset-2 sm:order-1 sm:ml-3 sm:w-auto sm:text-sm" disabled>
								<svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-nbpurple-50" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
									<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
									<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
								</svg>
								Moving...
							</button>
							<button @click="FS.moveToFolder.close(true)" type="button" class="mt-3 inline-flex justify-center rounded-md bg-nbpurple-200 px-4 py-2 text-base font-medium text-nbpurple-700 shadow-sm hover:bg-nbpurple-50 focus:outline-none focus:ring-2 focus:ring-nbpurple-500 focus:ring-offset-2 sm:order-0 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
								Cancel
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Full-screen image Modal -->
		<div x-cloak x-show="$store.fileStore.modalOpen" x-transition:enter="transition-opacity ease-linear duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-linear duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
			<div class="flex items-center justify-center min-h-screen px-4 pt-6 pb-20 text-center">
				<div class="fixed inset-0 bg-black bg-opacity-75 transition-opacity" aria-hidden="true" @click="$store.fileStore.modalOpen = false"></div>

				<div class="inline-block bg-black/10 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-screen-sm md:max-w-screen-md lg:max-w-screen-lg xl:max-w-screen-xl w-full max-w-full sm:w-11/12 md:w-10/12 lg:w-8/12 xl:w-7/12">
					<div class="bg-black/50 px-2 pt-3 pb-2 sm:p-4 sm:pb-2">
						<button type="button" class="absolute top-2 right-2 inline-flex items-center justify-center p-1 bg-black bg-opacity-50 rounded-full text-nbpurple-50 hover:text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-nbpurple-500" @click="$store.fileStore.modalOpen = false">
							<svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
								<path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
							</svg>
						</button>
						<img :src="$store.fileStore.modalImageUrl" :srcset="$store.fileStore.modalImageSrcset" :sizes="$store.fileStore.modalImageSizes" :alt="$store.fileStore.modalImageAlt" class="w-full">
					</div>
					<div class="bg-black/20 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse justify-between">
						<div class="flex flex-col items-start">
							<p class="text-sm text-gray-300">Name: <span x-text="$store.fileStore.modalImageAlt"></span></p>
							<p class="text-sm text-gray-300">Size: <span x-text="formatBytes($store.fileStore.modalImageFilesize)"></span></p>
						</div>
						<div class="flex flex-col items-start">
							<p class="text-sm text-gray-300">Dimensions: <span x-text="$store.fileStore.modalImageDimensions"></span></p>
							<p class="text-sm text-gray-300">URL: <a :href="$store.fileStore.modalImageUrl" target="_blank" x-text="$store.fileStore.modalImageUrl"></a>
								<svg class="size-3 ml-1 inline-block" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
									<path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
								</svg>
							</p>
						</div>
					</div>
					<!-- Prompt and title with copy button -->
					<div x-show="$store.fileStore.modalImageTitle.length > 0 || $store.fileStore.modalImagePrompt.length > 0" class="bg-black/20 px-4 py-3 sm:px-6 justify-start">
						<div class="flex flex-col items-start">
							<p x-show="$store.fileStore.modalImageTitle.length > 0" class="text-sm text-gray-300 font-medium">Title: <span class="font-normal" x-text="$store.fileStore.modalImageTitle"></span></p>
							<p x-show="$store.fileStore.modalImagePrompt.length > 0" class="text-sm text-gray-300 font-medium">Prompt: <span class="font-normal" x-text="$store.fileStore.modalImagePrompt"></span></p>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Folder deletion confirmation modal -->
		<div x-cloak x-data="{ MS: $store.menuStore }" class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
			<div x-show="MS.showDeleteFolderModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

			<div x-show="MS.showDeleteFolderModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="fixed inset-0 z-50 overflow-y-auto">
				<div class="flex min-h-screen items-end justify-center p-4 text-center sm:items-center sm:p-0">
					<div @click.outside="!MS.isDeletingFolders && MS.closeDeleteFolderModal()" class="relative transform overflow-hidden rounded-lg bg-nbpurple-50 text-left shadow-xl transition-all mb-16 sm:my-8 sm:w-full sm:max-w-lg">
						<div class="bg-nbpurple-50 px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
							<div class="sm:flex sm:items-start">
								<div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
									<svg class="size-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
										<path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
									</svg>
								</div>
								<div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
									<h3 class="text-base font-semibold leading-6 text-gray-900" id="modal-title">Confirm Delete</h3>
									<div class="mt-2">
										<p class="text-sm text-gray-500">
											Are you sure you want to delete the selected <span class="font-bold" x-text="MS.foldersToDeleteIds.length"></span> folder(s)? This action cannot be undone. <span class="font-bold">Deleting a folder will NOT delete the media inside it, and will move them to the main folder instead.</span>
										</p>
										<!-- List of selected folders -->
										<div class="mt-4">
											<ul role="list" class="list-disc space-y-2 pl-5 text-sm">
												<template x-for="folder in MS.foldersToDelete">
													<li x-text="folder.name" class="truncate"></li>
												</template>
											</ul>
										</div>
										<!-- /List of selected folders -->
									</div>
								</div>
							</div>
						</div>
						<div class="bg-nbpurple-50 px-4 py-3 gap-3 flex flex-row-reverse sm:px-6">
							<button :disabled="MS.isDeletingFolders" @click="MS.deleteFoldersConfirm()" type="button" class="mt-3 inline-flex w-full justify-center rounded-md bg-red-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
								<svg x-show="MS.isDeletingFolders" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
									<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
									<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
								</svg>
								Delete
							</button>
							<button :disabled="MS.isDeletingFolders" @click="MS.closeDeleteFolderModal()" type="button" class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-nbpurple-500 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancel</button>
						</div>
					</div>
				</div>
			</div>
		</div>




		<!-- Image upload modal -->
		<!-- URL import modal -->
		<!-- Logout confirmation modal -->
		<!-- Profile edit modal -->

		<!-- Toast notification -->
		<div x-cloak x-show="showToast" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-x-8" x-transition:enter-end="opacity-100 transform translate-x-0" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 transform translate-x-0" x-transition:leave-end="opacity-0 transform translate-x-8" class="z-50 fixed top-6 right-6 bg-orange-500 text-white px-4 py-2 rounded-md flex items-center">
			<span class="mr-2 text-xs">Link Copied</span>
			<svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
			</svg>
		</div>


	</main>

	<script>
		function formatBytes(bytes) {
			if (bytes === 0) return '0 Bytes';

			const k = 1024;
			const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
			const i = Math.floor(Math.log(bytes) / Math.log(k));

			return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
		}

		document.addEventListener('alpine:init', () => {
			console.log('Alpine initialized');

			function updateHashURL(f, p) {
				const params = new URLSearchParams(window.location.hash.slice(1));
				if (f) params.set('f', encodeURIComponent(f));
				if (p) params.set('p', encodeURIComponent(p));
				history.replaceState(null, null, `#${params.toString()}`);
			}

			function getUpdatedHashLink(f, p) {
				const params = new URLSearchParams(window.location.hash.slice(1));
				if (f) params.set('f', encodeURIComponent(f));
				if (p) params.set('p', encodeURIComponent(p));
				return `#${params.toString()}`;
			}

			Alpine.store('menuStore', {

				menuItems: [{
						name: 'Generative AI',
						icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.631 8.41m5.96 5.96a14.926 14.926 0 0 1-5.841 2.58m-.119-8.54a6 6 0 0 0-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 0 0-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 0 1-2.448-2.448 14.9 14.9 0 0 1 .06-.312m-2.24 2.39a4.493 4.493 0 0 0-1.757 4.306 4.493 4.493 0 0 0 4.306-1.758M16.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />',
						route: getUpdatedHashLink('AI: Generated Images', 'gai')
					},
					{
						name: 'Account Main Page',
						icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />',
						route: '/account'
					},
				],
				folders: [{
						name: 'AI: Generated Images',
						icon: 'A',
						route: '#',
						allowDelete: false
					},
					{
						name: 'Home: Main Folder',
						icon: 'H',
						route: '#',
						allowDelete: false
					}
				],
				setActiveFolder(folderName) {
					if (!this.foldersFetched || !folderName) {
						return;
					}
					// If already same folder, do nothing
					if (this.activeFolder === folderName) {
						return;
					}
					// Clear the files list
					Alpine.store('fileStore').files = [];
					this.activeFolder = folderName;
					updateHashURL(folderName);
					console.log('Active folder set:', folderName);
				},
				foldersFetched: false,
				fetchFolders() {
					try {
						const params = {
							action: 'list_folders',
						};

						const searchParams = new URLSearchParams(params).toString();

						const url = new URL(`https://<?= $_SERVER['SERVER_NAME'] ?>/account/ai/ai_json.php?${searchParams}`);

						const request = new Request(url, {
							method: 'GET',
							headers: new Headers({
								'Content-Type': 'application/json'
							})
						});

						fetch(request)
							.then(response => response.json())
							.then(data => {
								const folders = data || [];
								// Sort the fetched folders by name
								folders.sort((a, b) => a.name.localeCompare(b.name));
								// Deduplicate folders by name property
								// Append fetched folders to this.folders
								this.folders = folders.reduce((acc, folder) => {
									const existingFolder = acc.find(f => f.name === folder.name);
									if (!existingFolder) {
										acc.push(folder);
									} else {
										existingFolder.id = folder.id;
										existingFolder.route = folder.route;
										existingFolder.icon = folder.icon;
									}
									return acc;
								}, this.folders);
								// Folders have been fetched
								this.foldersFetched = true;
								console.log('Folders fetched:', this.folders);
								// Set this.activeFolder to the value of URL's # parameter
								const url = new URL(window.location.href);
								const params = new URLSearchParams(url.hash.slice(1));
								const activeFolder = decodeURIComponent(params.get('f') || '');
								console.log('Active folder:', activeFolder);
								const defaultFolder = this.folders.length > 0 ? this.folders[0].name : '';
								console.log('Default folder:', defaultFolder);
								// If URL hash has a folder, set it as active folder, otherwise use defaul
								const folderToSet = this.folders.find(f => f.name === activeFolder) ? activeFolder : defaultFolder;
								console.log('Folder to set:', folderToSet);
								this.setActiveFolder(folderToSet);
							}).catch(error => {
								console.error('Error fetching folders:', error);
							});
					} catch (error) {
						console.error('Error fetching folders:', error);
					}
				},
				addNewFolderForm() {
					// Check if the form already exists and return if it does
					if (this.folders.some(folder => folder.newForm)) {
						return;
					}
					this.folders.unshift({
						newForm: true,
						newFolderName: '',
						name: '',
					});
					console.log('New folder form added');
				},
				newFolderNameError: '',
				createFolder(folderName) {
					// Empty?
					if (!folderName.trim()) {
						this.newFolderNameError = 'Empty folder name.';
						setTimeout(() => {
							this.newFolderNameError = '';
						}, 1000);
						return;
					}
					// Check if duplicate folder name
					if (this.folders.some(folder => folder.name === folderName)) {
						console.error('Folder already exists:', folderName);
						this.newFolderNameError = 'Folder already exists.'
						setTimeout(() => {
							this.newFolderNameError = '';
						}, 1000);
						return;
					}
					// Create new folder structure
					const folderNameNormalized = folderName.normalize('NFC'); // Normalize the string
					const firstChar = [...folderNameNormalized][0]; // Extract the first character as a string
					const newFolder = {
						name: folderName,
						icon: firstChar.toUpperCase(), // Uppercase the first character
						route: getUpdatedHashLink(folderName),
						allowDelete: true,
					};
					// Add new folder to the folders array
					this.folders.push(newFolder);
					// Delete the new folder form
					this.folders = this.folders.filter(folder => !folder.newForm);
					// Set new folder as active folder
					this.setActiveFolder(folderName);
					console.log('Folder created:', folderName);
				},
				cancelNewFolderForm(folder, callback) {
					// Remove the new folder form from the folders array
					this.folders = this.folders.filter(f => f !== folder);
					// Execute callback if provided
					if (callback) {
						callback();
					}
				},
				isNewFolderFormPresent() {
					return this.folders.some(folder => folder.newForm);
				},
				showDeleteFolderButtons: false,
				showDeleteFolderModal: false,
				foldersToDeleteIds: [],
				foldersToDelete: [],
				isDeletingFolders: false,
				toggleDeleteFolderButtons() {
					this.showDeleteFolderButtons = !this.showDeleteFolderButtons;
				},
				disableDeleteFolderButtons() {
					this.showDeleteFolderButtons = false;
				},
				openDeleteFolderModal(folderIds) {
					// Check if array or not, and make it an array
					if (!Array.isArray(folderIds)) {
						folderIds = [folderIds];
					}
					// Check if array is empty and return if it is
					if (folderIds.length === 0) {
						return;
					}
					// Set folder IDs to delete while checking them agains this.folders
					this.foldersToDeleteIds = folderIds.filter(id => this.folders.some(folder => folder.id === id));
					this.foldersToDelete = this.folders.filter(folder => this.foldersToDeleteIds.includes(folder.id));
					// Check if activeFolder name matches any of the folders to delete
					if (this.foldersToDelete.some(folder => folder.name === this.activeFolder)) {
						console.log('Cannot delete active folder:', this.activeFolder);
						return;
					}
					this.showDeleteFolderModal = true;
				},
				closeDeleteFolderModal() {
					this.foldersToDeleteIds = [];
					this.showDeleteFolderModal = false;
				},
				deleteFoldersConfirm() {
					if (this.foldersToDeleteIds.length === 0) {
						return;
					}

					// Set folder IDs to delete while checking them agains this.folders
					const folderIds = this.foldersToDeleteIds.filter(id => this.folders.some(folder => folder.id === id));

					console.log('Deleting folders:', folderIds);

					this.isDeletingFolders = true;

					// Send request to delete folders
					this.deleteFolders(folderIds)
						.then(() => {
							this.closeDeleteFolderModal();
						})
						.catch(error => {
							console.error('Error deleting folders:', error);
						})
						.finally(() => {
							this.isDeletingFolders = false; // Reset the flag after folder deletion is complete
							this.fetchFolders(); // Refetch folders
						});

					console.log('Folders deleted:', folderIds);
				},
				deleteFolders(folderIds) {
					const formData = new FormData();
					formData.append('action', 'delete_folders');
					formData.append('foldersToDelete', JSON.stringify(folderIds));

					const url = new URL('https://<?= $_SERVER['SERVER_NAME'] ?>/account/ai/ai_json.php');

					const request = new Request(url, {
						method: 'POST',
						body: formData,
					});

					return fetch(request)
						.then(response => response.json())
						.then(data => {
							console.log('Folders deleted:', data);
							const deletedFolders = data.deletedFolders || [];
							// Remove the deleted folders from this.folders
							this.folders = this.folders.filter(folder => !deletedFolders.includes(folder.id));
						});
				},
				profile: {
					name: '<?= strlen($nym) > 15 ? htmlspecialchars(mb_substr($nym, 0, 15)) . "..." : htmlspecialchars($nym) ?>',
					npub: '<?= strlen($user) > 15 ? htmlspecialchars(mb_substr($user, 0, 15)) . "..." : htmlspecialchars($user) ?>',
					imageUrl: '<?= htmlspecialchars($ppic) ?>',
					profileUrl: getUpdatedHashLink(null, 'profile')
				},
				activeMenu: '',
				activeFolder: '',
				storageUsage: {
					totalAvailable: '<?= $userStorageLimit === PHP_INT_MAX ? 'Unlimited' : formatSizeUnits($userStorageLimit) ?>',
					userStorageLimit: <?= $userStorageLimit ?>,
					totalUsed: <?= $storageUsed ?>,

					get ratio() {
						return Math.min(1, Math.max(0, this.totalUsed / this.userStorageLimit));
					},

					getRatio() {
						const userOverLimit = <?= $userOverLimit ? 'true' : 'false' ?>;
						return userOverLimit ? 1 : this.ratio;
					},
				},
				updateTotalUsed(addUsed) {
					this.storageUsage.totalUsed += addUsed;
					console.log('Total used updated:', this.storageUsage.totalUsed);
					console.log('Total used ratio:', this.storageUsage.getRatio());
					console.log('Total used added:', addUsed);
				},
				remainingDays: <?= $daysRemaining ?>,
				fileStats: {
					totalFiles: <?= $usersFoldersStats['TOTAL']['fileCount'] ?>,
					totalFolders: <?= $usersFoldersStats['TOTAL']['folderCount'] ?>,
					totalGifs: <?= $usersFoldersStats['TOTAL']['gifCount'] ?>,
					totalImages: <?= $usersFoldersStats['TOTAL']['imageCount'] ?>,
					totalVideos: <?= $usersFoldersStats['TOTAL']['avCount'] ?>,
					creatorCount: <?= $usersFoldersStats['TOTAL']['publicCount'] ?>,
					creatorPageLink: '<?= "https://{$_SERVER['SERVER_NAME']}/creators/creator/?user={$userId}" ?>',
				},
				formatNumberInThousands(number) {
					return number > 999 ? `${(number / 1000).toFixed(1)}k` : number;
				}
			});
			Alpine.store('fileStore', {
				files: [],
				loading: false,
				moveToFolder: {
					isOpen: false,
					isLoading: false,
					isError: false,
					selectedIds: [],
					selectedFiles: [],
					destinationFolderId: null,
					hoveredFolder: null,
					isDropdownOpen: false,
					searchTerm: '',
					selectedFolderName: '',
					callback: null,
					open(ids, callback) {
						// Convert single ID to array
						if (!Array.isArray(ids)) {
							ids = [ids];
						}
						console.log('Opening move to folder modal:', ids);
						this.selectedIds = ids;
						this.selectedFiles = Alpine.store('fileStore').files.filter(file => ids.includes(file.id));
						console.log('Selected files:', this.selectedFiles);
						this.isOpen = true;
						this.callback = callback;
					},
					close(dontCallback) {
						this.selectedIds = [];
						this.selectedFiles = [];
						this.destinationFolderId = null;
						this.hoveredFolder = null;
						this.isDropdownOpen = false;
						this.searchTerm = '';
						this.selectedFolderName = '';
						this.isError = false;
						this.isOpen = false;
						this.isLoading = false;
						// Execute callback if provided
						if (this.callback && !dontCallback) {
							this.callback();
						}
					},
					toggleDropdown() {
						this.isDropdownOpen = !this.isDropdownOpen;
					},
				},
				moveToFolderConfirm() {
					this.moveToFolder.isLoading = true;
					// Check if destination folder is the same as the current folder
					// Get the folder name from the folders list based on id and compare it with the active name
					const destinationFolderName = Alpine.store('menuStore').folders.find(folder => folder.id === this.moveToFolder.destinationFolderId).name;
					if (destinationFolderName === Alpine.store('menuStore').activeFolder) {
						this.moveToFolder.close();
						this.moveToFolder.isLoading = false;
						return;
					}
					// Proceed otherwise
					this.moveItemsToFolder(this.moveToFolder.selectedIds, this.moveToFolder.destinationFolderId)
						.then(() => {
							this.moveToFolder.close();
							this.isError = false;
						})
						.catch(error => {
							console.error('Error moving files:', error);
							this.isError = true;
						})
						.finally(() => {
							this.moveToFolder.isLoading = false;
						});
				},
				moveItemsToFolder(itemIds, folderId) {
					console.log('Moving items to folder:', itemIds, folderId);
					// Construct form data
					const formData = new FormData();
					formData.append('action', 'move_to_folder');
					// Convert itemIds into array if single entry
					if (!Array.isArray(itemIds)) {
						itemIds = [itemIds];
					}
					// Add JSON list of image IDs to form data
					formData.append('imagesToMove', JSON.stringify(itemIds));
					formData.append('destinationFolderId', folderId);
					// Send the form data to the server
					try {
						const url = new URL('https://<?= $_SERVER['SERVER_NAME'] ?>/account/ai/ai_json.php');
						const request = new Request(url, {
							method: 'POST',
							body: formData,
						});

						return fetch(request)
							.then(response => response.json())
							.then(data => {
								console.log('Moved items to folder:', data);
								// Get IDs of moved images from returned data
								const movedImageIds = data.movedImages || [];
								// Remove moved images from the files list
								this.files = this.files.filter(file => !movedImageIds.includes(file.id));
							})
							.catch(error => {
								console.error('Error moving items to folder:', error);
							});
					} catch (error) {
						console.error('Error moving items to folder:', error);
					}
				},
				shareMedia: {
					isOpen: false,
					isLoading: false,
					isError: false,
					selectedIds: [],
					selectedFiles: [],
					callback: null,
					open(ids, callback) {
						// Convert single ID to array
						if (!Array.isArray(ids)) {
							ids = [ids];
						}
						console.log('Opening sharing modal:', ids);
						this.selectedIds = ids;
						this.selectedFiles = Alpine.store('fileStore').files.filter(file => ids.includes(file.id));
						console.log('Selected files:', this.selectedFiles);
						this.isOpen = true;
						this.callback = callback;
					},
					close(dontCallback) {
						this.selectedIds = [];
						this.selectedFiles = [];
						this.isError = false;
						this.isOpen = false;
						this.isLoading = false;
						// execute callback if provided
						if (this.callback && !dontCallback) {
							this.callback();
						}
					},
					getFlag() {
						return this.selectedFiles.length > 0 && this.selectedFiles[0].flag === 1;
					},
				},
				shareMediaCreatorConfirm(shareFlag) {
					this.shareMedia.isLoading = true;
					this.shareItemsCreatorPage(shareFlag)
						.then(() => {
							this.isError = false;
						})
						.catch(error => {
							console.error('Error sharing files:', error);
							this.isError = true;
						})
						.finally(() => {
							this.shareMedia.isLoading = false;
						});
				},
				shareItemsCreatorPage(shareFlag) {
					console.log('Sharing media on Creators page:', this.shareMedia.selectedIds);
					const itemsToShare = !Array.isArray(this.shareMedia.selectedIds) ? [this.shareMedia.selectedIds] : this.shareMedia.selectedIds;
					const formData = new FormData();
					const flag = shareFlag ? 'true' : 'false';
					formData.append('action', 'share_creator_page');
					formData.append('shareFlag', flag);
					formData.append('imagesToShare', JSON.stringify(itemsToShare));
					// Do the fetch to the API
					try {
						const url = new URL('https://<?= $_SERVER['SERVER_NAME'] ?>/account/ai/ai_json.php');
						const request = new Request(url, {
							method: 'POST',
							body: formData,
						});

						return fetch(request)
							.then(response => response.json())
							.then(data => {
								console.log('Shared media on Creators page:', data);
								const sharedImageIds = data.sharedImages || [];
								// Set the shared flag on the files
								this.files.forEach(file => {
									if (sharedImageIds.includes(file.id)) {
										file.flag = shareFlag ? 1 : 0;
										// Increment or reduce the count of shared images
										Alpine.store('menuStore').fileStats.creatorCount += shareFlag ? 1 : -1;
									}
								});
							})
							.catch(error => {
								console.error('Error sharing media on Creators page:', error);
							});
					} catch (error) {
						console.error('Error sharing media on Creators page:', error);
					}
				},
				deleteConfirmation: {
					isOpen: false,
					isLoading: false,
					isError: false,
					selectedIds: [],
					selectedFiles: [],
					callback: null,
					open(ids, callback) {
						// Convert single ID to array
						if (!Array.isArray(ids)) {
							ids = [ids];
						}
						console.log('Opening delete confirmation:', ids);
						this.selectedIds = ids;
						this.selectedFiles = Alpine.store('fileStore').files.filter(file => ids.includes(file.id));
						console.log('Selected files:', this.selectedFiles);
						this.isOpen = true;
						this.callback = callback;
					},
					close(dontCallback) {
						this.selectedIds = [];
						this.selectedFiles = [];
						this.isError = false;
						this.isOpen = false;
						this.isLoading = false;
						if (this.callback && !dontCallback) {
							this.callback();
						}
					}
				},
				confirmDelete() {
					this.deleteConfirmation.isLoading = true;
					this.deleteItem(this.deleteConfirmation.selectedIds)
						.then(() => {
							this.deleteConfirmation.close();
							this.isError = false;
						})
						.catch(error => {
							console.error('Error deleting files:', error);
							this.isError = true;
						})
						.finally(() => {
							this.deleteConfirmation.isLoading = false;
						});
				},
				deleteItem(itemIds) {
					console.log('Deleting image:', itemIds);
					// Construct form data
					const formData = new FormData();
					formData.append('action', 'delete');
					// Convert itemIds into array if single entry
					if (!Array.isArray(itemIds)) {
						itemIds = [itemIds];
					}
					// Add JSON list of image IDs to form data
					formData.append('imagesToDelete', JSON.stringify(itemIds));
					// Send the form data to the server
					try {
						const url = new URL('https://<?= $_SERVER['SERVER_NAME'] ?>/account/ai/ai_json.php');
						const request = new Request(url, {
							method: 'POST',
							body: formData,
						});

						return fetch(request)
							.then(response => response.json())
							.then(data => {
								console.log('Deleted image:', data);
								// Get IDs of deleted images from returned data
								const deletedImageIds = data.deletedImages || [];
								// Collect sum of size of all images to be deleted
								let totalCountToDelete = 0;
								const totalSizeToDelete = deletedImageIds.reduce((acc, id) => {
									const file = this.files.find(f => f.id === id);
									if (file) {
										acc += file.size;
										totalCountToDelete++;
									}
									return acc;
								}, 0);
								// Remove deleted images from the grid
								this.files = this.files.filter(f => !deletedImageIds.includes(f.id));
								// Update file stats
								Alpine.store('menuStore').fileStats.totalImages -= totalCountToDelete;
								Alpine.store('menuStore').fileStats.totalFiles -= totalCountToDelete;
								Alpine.store('menuStore').updateTotalUsed(-totalSizeToDelete);
							})
							.catch(error => {
								console.error('Error deleting image:', error);
							});
					} catch (error) {
						console.error('Error deleting image:', error);
					}
				},
				fetchFiles(folder) {
					// Retrun if folder is empty
					if (folder === '' || !folder) {
						this.files = [];
						return;
					}
					try {
						this.loading = true;

						const params = {
							action: 'list_files',
							folder: folder,
						};

						const searchParams = new URLSearchParams(params).toString();

						const url = new URL(`https://<?= $_SERVER['SERVER_NAME'] ?>/account/ai/ai_json.php?${searchParams}`);

						const request = new Request(url, {
							method: 'GET',
							headers: new Headers({
								'Content-Type': 'application/json'
							})
						});

						fetch(request)
							.then(response => response.json())
							.then(data => {
								this.files = data || [];
							})
							.catch(error => {
								console.error('Error fetching files:', error);
							})
							.finally(() => {
								this.loading = false;
							});
						// Refetch folders
						Alpine.store('menuStore').fetchFolders();
					} catch (error) {
						console.error('Error fetching files:', error);
						this.loading = false;
					}
				},
				injectFile(file) {
					console.log('Injecting file:', file);
					this.files.unshift(file);
				},
				modalOpen: false,
				modalImageUrl: '',
				modalImageSrcset: '',
				modalImageSizes: '',
				modalImageAlt: '',
				modalImageDimensions: '',
				modalImageFilesize: '',
				modalImageTitle: '',
				modalImagePrompt: '',
				openModal(file) {
					this.modalImageUrl = file.url;
					this.modalImageSrcset = file.srcset;
					this.modalImageSizes = file.sizes;
					this.modalImageAlt = file.title || file.name;
					this.modalImageDimensions = `${file.width}x${file.height}`;
					this.modalImageFilesize = file.size;
					this.modalImageTitle = file.title || '';
					this.modalImagePrompt = file.ai_prompt || '';
					this.modalOpen = true;
				},
				closeModal() {
					this.modalOpen = false;
				},
				copyUrlToClipboard(url) {
					navigator.clipboard.writeText(url)
						.then(() => {
							console.log('URL copied to clipboard:', url);
						})
						.catch(error => {
							console.error('Error copying URL to clipboard:', error);
						});
				}
			});
			Alpine.store('GAI', {
				ImageShow: false,
				ImageLoading: false,
				ImageUrl: '',
				ImageTitle: '',
				ImagePrompt: '',
				ImageFilesize: '',
				ImageDimensions: '0x0',
				file: {},
				clearImage() {
					this.ImageShow = false;
					this.ImageUrl = '';
					this.ImageTitle = '';
					this.ImagePrompt = '';
					this.ImageFilesize = '';
					this.ImageDimensions = '0x0';
				},
				generateImage(title, prompt, selectedModel) {
					// Access the form inputs passed as arguments
					console.log('Title:', title);
					console.log('Prompt:', prompt);
					console.log('Selected Model:', selectedModel);
					// Switch to AI: Generated Images folder
					menuStore = Alpine.store('menuStore');
					targetFolder = 'AI: Generated Images';
					if (menuStore.activeFolder !== targetFolder) {
						console.log('Switching to folder:', targetFolder);
						console.log('Current folder:', menuStore.activeFolder);
						menuStore.setActiveFolder(targetFolder);
					}
					// Prepare form data to send to the server
					const formData = new FormData();
					formData.append('title', title);
					formData.append('prompt', prompt);
					formData.append('model', selectedModel);
					formData.append('action', 'generate_ai_image');

					// Send the form data to the server
					try {
						this.ImageShow = false;
						this.ImageLoading = true;
						const url = new URL('https://<?= $_SERVER['SERVER_NAME'] ?>/account/ai/ai_json.php');
						const request = new Request(url, {
							method: 'POST',
							body: formData,
						});

						fetch(request)
							.then(response => response.json())
							.then(data => {
								console.log('Generated image:', data);
								this.ImageUrl = data.url;
								this.ImageFilesize = data.size;
								this.ImageDimensions = `${data.width}x${data.height}`;
								this.ImageTitle = title.length > 0 ? title : data.name;
								this.ImagePrompt = prompt;
								//this.ImageShow = true;

								data.title = title;
								data.ai_prompt = prompt;
								// Add file to the grid
								Alpine.store('fileStore').injectFile(data);
								// Update file stats
								menuStore.fileStats.totalImages++;
								menuStore.fileStats.totalFiles++;
								menuStore.updateTotalUsed(data.size);
								this.file = data;
							})
							.catch(error => {
								console.error('Error generating image:', error);
								this.ImageLoading = false;
							})
							.finally(() => {
								//this.ImageLoading = false;
								console.log('Image loading:', this.ImageLoading);
							});
					} catch (error) {
						console.error('Error generating image:', error);
						this.ImageLoading = false;
					}
				},
			});
		})
	</script>

</body>

</html>