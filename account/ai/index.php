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
<ul role="list" class="flex flex-1 flex-col gap-y-7" x-data="{ menuStore: \$store.menuStore, fileStore: \$store.fileStore }" x-init="menuStore.activeMenu = menuStore.menuItems[0].name">

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
						<svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
						</svg>
						<span class="sr-only">Profile Settings</span>
					</a>
					<a href="#" class="text-sm font-semibold leading-6 text-nbpurple-50 hover:bg-nbpurple-800 p-2 rounded-md">
						<svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
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
						<svg class="size-5 text-nbpurple-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
						<svg class="size-5 text-nbpurple-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
						<svg class="size-5 text-nbpurple-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6Z" />
							<path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5Z" />
						</svg>
						<span class="ml-2 text-sm font-medium text-nbpurple-300" x-text="'Media(' + menuStore.formatNumberInThousands(menuStore.fileStats.totalFiles) + ')'"></span>
					</div>
					<div class="flex items-center space-x-2">
						<div class="flex items-center">
							<svg class="size-4 text-nbpurple-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
								<path stroke-linecap="round" stroke-linejoin="round" d="M12.75 8.25v7.5m6-7.5h-3V12m0 0v3.75m0-3.75H18M9.75 9.348c-1.03-1.464-2.698-1.464-3.728 0-1.03 1.465-1.03 3.84 0 5.304 1.03 1.464 2.699 1.464 3.728 0V12h-1.5M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
							</svg>
							<span class="ml-1 text-sm font-medium text-nbpurple-300" x-text="menuStore.formatNumberInThousands(menuStore.fileStats.totalGifs)"></span> </div>
						<div class="flex items-center">
							<svg class="size-4 text-nbpurple-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
							</svg>
							<span class="ml-1 text-sm font-medium text-nbpurple-300" x-text="menuStore.formatNumberInThousands(menuStore.fileStats.totalImages)"></span> </div>
						<div class="flex items-center">
							<svg class="size-4 text-nbpurple-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
								<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
							</svg>
							<span class="ml-1 text-sm font-medium text-nbpurple-300" x-text="menuStore.formatNumberInThousands(menuStore.fileStats.totalVideos)"></span> </div>
					</div>
				</div>
			</li>
			<!-- /File statistics -->
		</ul>
	</li>
	<!-- /Sidebar widgets -->
	<!-- Menu items -->
	<li>
		<ul role="list" class="-mx-2 space-y-1">
			<template x-for="item in menuStore.menuItems" :key="item.name">
				<li>
					<a :href="item.route" :class="{ 'bg-nbpurple-800 text-nbpurple-50': menuStore.activeMenu === item.name, 'text-nbpurple-300 hover:text-nbpurple-50 hover:bg-nbpurple-800': menuStore.activeMenu !== item.name }" class="group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold" @click="menuStore.activeMenu = item.name; mobileMenuOpen = false">
						<svg class="size-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" x-html="item.icon"></svg>
						<span x-text="item.name"></span>
					</a>
				</li>
			</template>
		</ul>
	</li>
	<!-- /Menu items -->
	<!-- Folders -->
	<li>
		<div class="text-xs font-semibold leading-6 text-nbpurple-300">Folders (<span class="font-bold" x-text="menuStore.fileStats.totalFolders"></span>)</div>
		<ul role="list" class="-mx-2 mt-2 space-y-1">
			<template x-for="folder in menuStore.folders" :key="folder.name">
				<li>
					<a :href="folder.route" :class="{ 'bg-nbpurple-800 text-nbpurple-50': menuStore.activeFolder === folder.name, 'text-nbpurple-300 hover:text-nbpurple-50 hover:bg-nbpurple-800': menuStore.activeFolder !== folder.name }" class="group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold" @click.prevent="menuStore.setActiveFolder(folder.name); mobileMenuOpen = false">
						<span class="flex size-6 shrink-0 items-center justify-center rounded-lg border border-nbpurple-700 bg-nbpurple-800 text-[0.625rem] font-medium text-nbpurple-300 group-hover:text-nbpurple-50" x-text="folder.icon"></span>
						<span class="truncate" x-text="folder.name"></span>
					</a>
				</li>
			</template>
		</ul>
	</li>
	<!-- /Folders -->
</ul>
HTML;
?>

<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>nostr.build account</title>

	<link rel="icon" href="/assets/primo_nostr.png" />
	<link href="/styles/twbuild.css?v=74" rel="stylesheet">
	<script defer src="/scripts/fw/alpinejs-intersect.min.js?v=12"></script>
	<script defer src="/scripts/fw/alpinejs.min.js?v=12"></script>
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
	})" class="h-full bg-gradient-to-tr from-nbpurple-950 to-nbpurple-800 bg-fixed bg-no-repeat bg-cover">
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
							<div class="flex h-16 shrink-0 items-center">
								<?= $NBLogoSVG ?>
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
					<div class="flex h-16 shrink-0 items-center">
						<?= $NBLogoSVG ?>
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
				</div>

				<main x-data="{ GAI: $store.GAI }" class="lg:pr-[41rem]">
					<!-- Main content -->
					<?php if ($perm->validatePermissionsLevelAny(1, 10, 99)) : ?>
						<div class="p-4">
							<form action="#" class="relative" x-data="{ assignOpen: false, labelOpen: false, dueDateOpen: false, title: '', prompt: '', selectedModel: '@cf/lykon/dreamshaper-8-lcm' }">
								<!-- Clear button -->
								<div x-cloak x-show="title.length > 0 || prompt.length > 0" class="flex-shrink-0 absolute top-1 right-1 z-10">
									<button type="button" class="inline-flex items-center rounded-full bg-nbpurple-600/50 p-1 sm:text-sm text-xs font-semibold text-nbpurple-50 shadow-sm hover:bg-nbpurple-500/50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nbpurple-600" @click="title = ''; prompt = ''">
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
													<ul x-cloak @click.outside="modelMenuOpen = false" x-show="modelMenuOpen" x-transition:enter="" x-transition:enter-start="" x-transition:enter-end="" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="absolute left-0 z-10 mt-2 sm:w-96 w-80 origin-top-left divide-y divide-gray-200 overflow-hidden rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none" tabindex="-1" role="listbox" aria-labelledby="listbox-label" aria-activedescendant="listbox-option-0">
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
							<div class="bg-black/50 p-4">
								<p x-cloak x-show="!GAI.ImageShow && !GAI.ImageLoading" class="flex items-center justify-center text-nbpurple-200 text-center text-lg h-72" x-transition:enter="transition-opacity duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
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
								<img x-cloak x-show="GAI.ImageShow" @load="GAI.ImageShow = true; GAI.ImageLoading = false" :src="GAI.file.thumb" :srcset="GAI.file.srcset" :sizes="GAI.file.sizes" :alt="GAI.file.title || GAI.file.name" :width="GAI.file.width" :height="GAI.file.height" loading="eager" class="w-full" x-transition:enter="transition-opacity ease-in duration-750" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" />
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
				<aside x-data="{ showScrollButton: false }" x-ref="sidebar" @scroll.window.throttle="showScrollButton = window.pageYOffset > 500" @scroll.throttle="showScrollButton = $refs.sidebar.scrollTop > 500" class="bg-nbpurple-900/10 lg:fixed lg:bottom-0 lg:right-0 lg:top-16 lg:w-[40rem] lg:overflow-y-auto lg:border-l lg:border-nbpurple-950/5">
					<!-- Activity feed content -->
					<div class="p-4" x-data="Alpine.store('fileStore')" x-init="if (files.length === 0) fetchFiles($store.menuStore.activeFolder); $watch('$store.menuStore.activeFolder', folder => fetchFiles(folder))">
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
								<li class="relative">
									<div class="group aspect-h-7 aspect-w-10 block w-full overflow-hidden rounded-lg bg-black/50 focus-within:ring-2 focus-within:ring-indigo-500 focus-within:ring-offset-2 focus-within:ring-offset-gray-100">
										<svg class="absolute inset-0 pointer-events-none object-cover group-hover:opacity-75 h-full w-full text-nbpurple-400 animate-pulse" x-show="!file.loaded" fill="none" viewBox="0 0 24 24" stroke="currentColor">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
										</svg>
										<img x-intersect.margin.100px="true" :src="file.thumb" :srcset="file.srcset" :sizes="file.sizes" :alt="file.name" :width="file.width" :height="file.height" loading="lazy" class="pointer-events-none object-contain group-hover:opacity-75" x-cloak @load="file.loaded = true" x-transition:enter="transition-opacity ease-in duration-1000" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" />
										<button @click="openModal(file)" type="button" class="absolute inset-0 focus:outline-none">
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
										<div x-data="{copyClick: false}">
											<button @click="copyUrlToClipboard(file.url); copyClick = true; setTimeout(() => copyClick = false, 2000); showToast = true" class="mt-2 px-2 py-1 bg-nbpurple-600 text-white rounded-md hover:bg-nbpurple-700 focus:outline-none focus:ring-2 focus:ring-nbpurple-500" aria-label="Copy image URL to clipboard">
												<svg x-show="!copyClick" class="size-6 inline-block" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" x-transition:enter="transition-opacity duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
													<path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75" />
												</svg>
												<svg x-cloak x-show="copyClick" class="animate-[pulse_3s_ease-in-out_infinite] size-6 inline-block" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" x-transition:enter="transition-opacity duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
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
					<div x-cloak x-show="showScrollButton" class="fixed bottom-6 right-6 z-50">
						<button @click="showScrollButton = false; window.scrollTo({ top: 0, behavior: 'smooth' }); $refs.sidebar.scrollTo({ top: 0, behavior: 'smooth' })" type="button" class="bg-nbpurple-500 text-nbpurple-50 rounded-full p-2 shadow-md hover:bg-nbpurple-600 focus:outline-none focus:ring-2 focus:ring-nbpurple-500">
							<svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
								<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 18.75 7.5-7.5 7.5 7.5" />
								<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 7.5-7.5 7.5 7.5" />
							</svg>
						</button>
					</div>

				</aside>
			</div>
		</div>


		<!-- Edit bar -->
		<div x-cloak x-data class="fixed inset-x-0 bottom-0 z-50" x-cloak>
			<div class="relative w-screen" x-show="$store.mediaEditBar.show" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-y-full" x-transition:enter-end="opacity-100 transform translate-y-0" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 transform translate-y-0" x-transition:leave-end="opacity-0 transform translate-y-full">

				<div class="h-1/4 bg-nbpurple-500/80 shadow-xl overflow-y-auto relative">
					<button @click="$store.mediaEditBar.toggle()" type="button" class="inset-auto absolute top-1 right-1 flex items-center justify-center h-8 w-8 rounded-full focus:outline-none focus:ring-1 focus:ring-inset focus:ring-white">
						<span class="sr-only">Close panel</span>
						<svg class="size-6 text-nbpurple-100" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
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
							<button x-data="{
										getSelectedCount() {
											return $store.checkedCheckboxesMedia.count;
										},
									}" @click="$store.moveToFolder.open()" type="button" class="rounded-md bg-nbpurple-500 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-nbpurple-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nbpurple-500">
								Move to <span x-text="getSelectedCount()"></span> file(s) to folder
							</button>
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
					<div class="relative transform overflow-hidden rounded-lg bg-nbpurple-50 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
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
						<div class="bg-nbpurple-50 px-4 py-3 gap-3 flex flex-row-reverse sm:px-6">
							<button @click="$store.deleteConfirmation.confirm()" type="button" class="mt-3 inline-flex w-full justify-center rounded-md bg-red-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
								<svg x-show="$store.deleteConfirmation.isLoading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
									<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
									<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
								</svg>
								Delete
							</button>
							<button @click="$store.deleteConfirmation.close()" type="button" class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-nbpurple-500 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancel</button>
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

			function updateHashURL(f, p) {
				const params = new URLSearchParams(window.location.hash.slice(1));
				if (f) params.set('f', encodeURIComponent(f));
				if (p) params.set('p', encodeURIComponent(p));
				history.replaceState(null, null, `#${params.toString()}`);
			}

			function getUodatedHashLink(f, p) {
				const params = new URLSearchParams(window.location.hash.slice(1));
				if (f) params.set('f', encodeURIComponent(f));
				if (p) params.set('p', encodeURIComponent(p));
				return `#${params.toString()}`;
			}

			Alpine.store('menuStore', {

				menuItems: [{
						name: 'Generative AI',
						icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42" />',
						route: getUodatedHashLink('AI: Generated Images', 'gai')
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
						route: '#'
					},
					{
						name: 'Home: Main Folder',
						icon: 'H',
						route: '#'
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
				profile: {
					name: '<?= strlen($nym) > 15 ? htmlspecialchars(substr($nym, 0, 15)) . "..." : htmlspecialchars($nym) ?>',
					npub: '<?= strlen($user) > 15 ? htmlspecialchars(substr($user, 0, 15)) . "..." : htmlspecialchars($user) ?>',
					imageUrl: '<?= htmlspecialchars($ppic) ?>',
					profileUrl: getUodatedHashLink(null, 'profile')
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
				},
				formatNumberInThousands(number) {
					return number > 999 ? `${(number / 1000).toFixed(1)}k` : number;
				}
			});
			Alpine.store('fileStore', {
				files: [],
				loading: false,
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
					} catch (error) {
						console.error('Error fetching files:', error);
						this.loading = false;
					}
				},
				injectFile(file) {
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
					this.modalImageAlt = file.name;
					this.modalImageDimensions = `${file.width}x${file.height}`;
					this.modalImageFilesize = file.size;
					this.modalImageTitle = file.title;
					this.modalImagePrompt = file.ai_prompt;
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