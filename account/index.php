<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/config.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/functions/session.php";
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';

$perm = new Permission();

if (!$perm->validateLoggedin()  || empty($_SESSION["usernpub"])) {
	header("Location: /login");
	$link->close();
	exit;
}

// Redirect to the /plans page if the user does not have a subscription
if ($perm->validatePermissionsLevelEqual(0)) {
	header("Location: /plans/");
	$link->close();
	exit;
}

// TODO:
// - Add simple notification for any important messages
// - Add scheduled nostr posts
// - Use VidStack video/audio player for media playback
// - Use file.show attribute to hide deleted files for the purposes of Trash folder (an idea). Will require deletion from CDN (potentially)
// - Add ability to delete Nostr post when deleting media that is shared
//   - Warn the user (done)
//   - Add switch to trigger deletion of Nostr post (TBD)
//   - Decide how to treat notes that thave other media shared on them (TBD)
//   - Allow cascading deletion of media that is shared on a Nostr post, based on the note (TBD)
//   - [Depends on] Implement simple "Trash" folder for deleted media and disable serving it to the public (TBD)

// - Allow user to verify their npub in profile edit dialog

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
<ul role="list" class="flex flex-1 flex-col gap-y-7" x-data="{ pfpError: false }" x-init="menuStore.setActiveMenuFromHash()" @click.away="!menuStore.showDeleteFolderModal && menuStore.disableDeleteFolderButtons()" x-effect="pfpError = !profileStore.profileInfo.pfpUrl">

	<!-- Profile -->
	<ul role="list" class="-mx-2 space-y-1">
		<li>
			<div class="flex items-center justify-between">
				<div class="flex items-center gap-x-4">
					<!-- PFP -->
					<template x-if="profileStore.profileInfo.pfpUrl && !pfpError">
						<img class="size-8 rounded-full bg-nbpurple-800 object-cover" :src="profileStore.profileInfo.pfpUrl" :alt="'Profile picture for ' + profileStore.profileInfo.name" @error="pfpError = true" @load="pfpError = false">
					</template>
					<template x-if="!profileStore.profileInfo.pfpUrl || pfpError">
						<svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="inline-block size-8 rounded-full text-nbpurple-400">
							<path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
						</svg>
					</template>
					<!-- /PFP -->
					<div>
						<span class="block text-sm font-semibold leading-tight text-nbpurple-50" x-text="profileStore.profileInfo.getNameDisplay()"></span>
						<span class="block text-xs font-semibold leading-tight text-nbpurple-50" x-text="profileStore.profileInfo.getNpubDisplay()"></span>
					</div>
				</div>
				<div class="flex items-center">
					<button @click.prevent="profileStore.openDialog(); menuStore.mobileMenuOpen = false; menuStore.disableDeleteFolderButtons(); " class="text-sm font-semibold leading-6 text-nbpurple-50 hover:bg-nbpurple-800 p-2 rounded-md">
						<svg aria-hidden="true" class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
							<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
						</svg>
						<span class="sr-only">Profile Settings</span>
					</button>
					<button @click.prevent="logoutScreen = true" class="text-sm font-semibold leading-6 text-nbpurple-50 hover:bg-nbpurple-800 p-2 rounded-md">
						<svg aria-hidden="true" class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15M12 9l3 3m0 0-3 3m3-3H2.25" />
						</svg>
						<span class="sr-only">Logout</span>
					</button>
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
					<div class="text-sm font-medium text-nbpurple-300" x-text="formatBytes(profileStore.profileInfo.storageUsed) + ' / ' + profileStore.profileInfo.totalStorageLimit"></div>
				</div>
				<div class="mt-2 w-full bg-nbpurple-200 rounded-full h-2">
					<div class="bg-nbpurple-600 h-2 rounded-full" :style="'width: ' + (profileStore.profileInfo.getStorageRatio().toFixed(2) * 100) + '%'"></div>
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
					<div class="text-sm font-medium text-nbpurple-300" x-text="profileStore.profileInfo.remainingDays"></div>
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
			<li x-cloak x-show="profileStore.profileInfo.isCreatorsPageEligible">
				<div class="flex items-center justify-between">
					<div class="flex items-center cursor-copy" @click="navigator.clipboard.writeText(profileStore.profileInfo.creatorPageLink); showToast = true">
						<svg aria-hidden="true" class="size-4 text-nbpurple-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42" />
						</svg>
						<span class="ml-2 text-sm font-medium text-nbpurple-300">Creators Page</span>
						<svg aria-hidden="true" class="ml-1 size-4 text-nbpurple-300" viewBox="0 0 20 20" fill="currentColor">
							<path fill-rule="evenodd" d="M15.988 3.012A2.25 2.25 0 0 1 18 5.25v6.5A2.25 2.25 0 0 1 15.75 14H13.5v-3.379a3 3 0 0 0-.879-2.121l-3.12-3.121a3 3 0 0 0-1.402-.791 2.252 2.252 0 0 1 1.913-1.576A2.25 2.25 0 0 1 12.25 1h1.5a2.25 2.25 0 0 1 2.238 2.012ZM11.5 3.25a.75.75 0 0 1 .75-.75h1.5a.75.75 0 0 1 .75.75v.25h-3v-.25Z" clip-rule="evenodd" />
							<path d="M3.5 6A1.5 1.5 0 0 0 2 7.5v9A1.5 1.5 0 0 0 3.5 18h7a1.5 1.5 0 0 0 1.5-1.5v-5.879a1.5 1.5 0 0 0-.44-1.06L8.44 6.439A1.5 1.5 0 0 0 7.378 6H3.5Z" />
						</svg>
					</div>
					<a class="flex items-center cursor-pointer" :href="profileStore.profileInfo.creatorPageLink">
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
		<!-- Top-level menu items -->
		<ul role="list" class="-mx-2 space-y-1">
			<template x-for="item in menuStore.menuItems" :key="item.name">
				<li>
					<a :href="item.route" :class="{ 'bg-nbpurple-800 text-nbpurple-50': menuStore.activeMenu === item.name, 'text-nbpurple-300 hover:text-nbpurple-50 hover:bg-nbpurple-800': menuStore.activeMenu !== item.name }" class="group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold" @click="menuStore.setActiveMenu(item.name); menuStore.mobileMenuOpen = false">
						<svg aria-hidden="true" class="size-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" x-html="item.icon"></svg>
						<span x-text="item.name"></span>
					</a>
				</li>
			</template>
		</ul>
		<!-- AI Studio submenu -->
		<!--
		<div x-data="{ AISubMenuExpand: menuStore.menuItemsAI.length === 1, isAI: menuStore.menuItemsAI.find(item=>item.name === menuStore.activeMenu) }" x-init="fileStore.fullWidth = !isAI" class="-mx-2 space-y-1 mt-1">
			<button @click.throttle="AISubMenuExpand = !AISubMenuExpand" type="button" class="text-nbpurple-300 hover:text-nbpurple-50 hover:bg-nbpurple-800 flex items-center w-full text-left rounded-md p-2 gap-x-3 text-sm leading-6 font-semibold" aria-controls="sub-menu-ai" aria-expanded="AISubMenuExpand">
				<svg class="h-6 w-6 shrink-0 text-nbpurple-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
					<path d="M12 8V4H8"/>
					<rect width="16" height="12" x="4" y="8" rx="2"/>
					<path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/>
				</svg>
				AI Studio
				<svg :class="AISubMenuExpand ? 'rotate-90 text-nbpurple-500' : 'text-nbpurple-400'" class="ml-auto h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
					<path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
				</svg>
			</button>
			<ul x-cloak x-show="AISubMenuExpand || isAI" class="mt-1 px-2" id="sub-menu-ai">
				<template x-for="item in menuStore.menuItemsAI" :key="item.name">
					<li>
						<a :href="item.route" :class="{ 'bg-nbpurple-800 text-nbpurple-50': menuStore.activeMenu === item.name, 'text-nbpurple-300 hover:text-nbpurple-50 hover:bg-nbpurple-800': menuStore.activeMenu !== item.name }" class="group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold" @click="menuStore.setActiveMenu(item.name); menuStore.mobileMenuOpen = false">
							<svg aria-hidden="true" class="size-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" x-html="item.icon"></svg>
							<span x-text="item.name"></span>
						</a>
					</li>
				</template>
			</ul>
		</div>
-->
		<ul x-data="{ isAI: menuStore.menuItemsAI.find(item=>item.name === menuStore.activeMenu) }" x-init="fileStore.fullWidth = !isAI" role="list" class="-mx-2 space-y-1">
			<template x-for="item in menuStore.menuItemsAI" :key="item.name">
				<li>
					<a :href="item.route" :class="{ 'bg-nbpurple-800 text-nbpurple-50': menuStore.activeMenu === item.name, 'text-nbpurple-300 hover:text-nbpurple-50 hover:bg-nbpurple-800': menuStore.activeMenu !== item.name }" class="group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold" @click="menuStore.setActiveMenu(item.name); menuStore.mobileMenuOpen = false">
						<svg aria-hidden="true" class="size-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" x-html="item.icon"></svg>
						<span x-text="item.name"></span>
						<span class="inline-flex items-center rounded-full bg-nbpurple-200 px-1.5 py-0.5 text-xs font-medium text-nbpurple-800 ring-1 ring-inset ring-nbpurple-700/10">New</span>
					</a>
				</li>
			</template>
		</ul>
		<!-- External menu items -->
		<ul role="list" class="-mx-2 space-y-1">
			<template x-for="item in menuStore.externalMenuItems" :key="item.name">
				<li x-show="profileStore.profileInfo.allowed(item.allowed)">
					<a :href="item.route" :class="{ 'bg-nbpurple-800 text-nbpurple-50': menuStore.activeMenu === item.name, 'text-nbpurple-300 hover:text-nbpurple-50 hover:bg-nbpurple-800': menuStore.activeMenu !== item.name }" class="group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold">
						<svg aria-hidden="true" class="size-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" x-html="item.icon"></svg>
						<span x-text="item.name"></span>
					</a>
				</li>
			</template>
		</ul>
		<!-- Admin menu items -->
		<template x-if="profileStore.profileInfo.isAdmin || profileStore.profileInfo.isModerator">
			<div x-data="{AdminSubMenuExpand: false}" class="-mx-2 space-y-1 mt-1">
				<button @click.throttle="AdminSubMenuExpand = !AdminSubMenuExpand" type="button" class="text-nbpurple-300 hover:text-nbpurple-50 hover:bg-nbpurple-800 flex items-center w-full text-left rounded-md p-2 gap-x-3 text-sm leading-6 font-semibold" aria-controls="sub-menu-admin" aria-expanded="AdminSubMenuExpand">
					<svg class="h-6 w-6 shrink-0 text-nbpurple-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
						<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0 0 12 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 0 1-2.031.352 5.988 5.988 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971Zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 0 1-2.031.352 5.989 5.989 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971Z" />
					</svg>
					Administrate
					<svg :class="AdminSubMenuExpand ? 'rotate-90 text-nbpurple-500' : 'text-nbpurple-400'" class="ml-auto h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
						<path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
					</svg>
				</button>
				<ul x-cloak x-show="AdminSubMenuExpand" class="mt-1 px-2" id="sub-menu-ai">
					<template x-for="item in menuStore.adminMenuItems" :key="item.name">
						<li x-show="profileStore.profileInfo.allowed(item.allowed)">
							<a @click="window.location.href = item.route" :href="item.route" :class="{ 'bg-nbpurple-800 text-nbpurple-50': menuStore.activeMenu === item.name, 'text-nbpurple-300 hover:text-nbpurple-50 hover:bg-nbpurple-800': menuStore.activeMenu !== item.name }" class="group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold">
								<svg x-show="item.icon.length > 0" aria-hidden="true" class="size-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" x-html="item.icon"></svg>
								<span x-text="item.name"></span>
							</a>
						</li>
					</template>
				</ul>
			</div>
		</template>
	</li>
	<!-- /Menu items -->
	<!-- Folders -->
	<li>
		<div class="flex items-center justify-between text-xs font-semibold leading-6 text-nbpurple-300">
			<span class="font-bold" x-text="'Folders (' + menuStore.folders.length + ')'"></span>
			<div>
				<!-- Button to create a new folder -->
				<button type="button" class="ml-2 p-1 text-xs font-semibold text-nbpurple-300 hover:text-nbpurple-50 hover:bg-nbpurple-800 rounded-md" @click="menuStore.newFolderDialogOpen(); menuStore.disableDeleteFolderButtons()" :disabled="menuStore.newFolderDialog">
					<svg aria-hidden="true" class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m3-3H9m4.06-7.19-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
					</svg>
					<span class="sr-only">Create a new folder</span>
				</button>
				<!-- Button to delete folders -->
				<button type="button" class="ml-2 p-1 text-xs font-semibold text-nbpurple-300 hover:text-nbpurple-50 hover:bg-nbpurple-800 rounded-md" @click="menuStore.toggleDeleteFolderButtons()" :disabled="menuStore.newFolderDialog">
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
			<!-- New Folder Form -->
			<li x-cloak x-show="menuStore.newFolderDialog" x-trap="menuStore.newFolderDialog">
				<div x-id="['new-folder', 'new-folder-error']">
					<div class="flex items-center gap-x-3 relative">
						<!-- Error message -->
						<div
							:id="\$id('new-folder-error')"
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
						<label :for="\$id('new-folder')" class="sr-only">New folder name</label>
						<input :id="\$id('new-folder')" x-ref="newFolderNameInput" type="text" name="new-folder-name" :class="{'animate-shake': menuStore.newFolderNameError }" class="w-full text-sm font-semibold text-nbpurple-300 bg-nbpurple-800 rounded-md p-2" x-model="menuStore.newFolderName" @keydown.enter="menuStore.createFolder(menuStore.newFolderName)" @keydown.escape.stop="menuStore.newFolderDialogClose()" aria-describedby="new-folder-error-message">
						<button type="button" class="-ml-2 text-nbpurple-300 hover:text-nbpurple-50 hover:bg-nbpurple-800 rounded-md p-2 text-sm leading-6 font-semibold" @click="menuStore.createFolder(menuStore.newFolderName)">
							<svg aria-hidden="true" class="size-5 shrink-0 items-center justify-center rounded-lg border border-nbpurple-700 bg-nbpurple-800 text-[0.625rem] font-medium text-nbpurple-300 group-hover:text-nbpurple-50" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
								<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
							</svg>
							<span class="sr-only">Create folder</span>
						</button>
						<button type="button" class="-mx-2 text-nbpurple-300 hover:text-nbpurple-50 hover:bg-nbpurple-800 rounded-md p-2 text-sm leading-6 font-semibold" @click="menuStore.newFolderDialogClose()">
							<svg aria-hidden="true" class="size-5 shrink-0 items-center justify-center rounded-lg border border-nbpurple-700 bg-nbpurple-800 text-[0.625rem] font-medium text-nbpurple-300 group-hover:text-nbpurple-50" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
								<path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
							</svg>
							<span class="sr-only">Cancel</span>
						</button>
					</div>
				</div>
			</li>
			<!-- /New Folder Form -->
			<template x-for="folder in menuStore.folders" :key="folder.name">
				<li x-on:drop.prevent="
								if(menuStore.activeFolder === folder.name) return;
                const mediaId = event.dataTransfer.getData('text/plain');
								if(mediaId) {
									const draggedElement = document.getElementById(mediaId);
									draggedElement.classList.add('animate-pulse');
									fileStore.moveItemsToFolder(mediaId, folder.id)
									.then(() => {
										draggedElement.remove();
									})
									.catch(() => {
										draggedElement.classList.remove('animate-pulse');
									})
									.finally(() => {
										adding = false;
									});
								}
            " x-on:dragover.prevent="adding = true; event.dataTransfer.dropEffect = 'move';" x-on:dragleave.prevent="adding = false" x-data="{ adding: false }" x-on:drop="adding = true">
					<div class="flex justify-between" x-transition:enter="transition-opacity ease-linear duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-linear duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
						<a :href="folder.route" :class="{ 'bg-nbpurple-800 text-nbpurple-50': menuStore.activeFolder === folder.name, 'text-nbpurple-300 hover:text-nbpurple-50 hover:bg-nbpurple-800': menuStore.activeFolder !== folder.name, 'bg-nbpurple-700 text-nbpurple-50 scale-105': adding && menuStore.activeFolder !== folder.name }" class="w-full group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold" @click.prevent="menuStore.setActiveFolder(folder.name); menuStore.mobileMenuOpen = false; menuStore.disableDeleteFolderButtons()">
							<!--
							<span class="flex size-6 shrink-0 items-center justify-center rounded-lg border border-nbpurple-700 bg-nbpurple-800 text-[0.625rem] font-medium text-nbpurple-300 group-hover:text-nbpurple-50" x-text="folder.icon"></span>
							-->
							<span class="relative flex size-6 shrink-0 items-center justify-center rounded-lg border border-nbpurple-700 bg-nbpurple-800 text-[0.5rem] font-normal text-nbpurple-300 group-hover:text-nbpurple-50">
								<svg class="absolute w-full h-full" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
									<path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>
								</svg>
								<span class="relative z-10 -mb-[0.1rem]" x-text="folder.icon"></span>
							</span>
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
				</li>
			</template>
			<li x-cloak x-show="!menuStore.newFolderDialog">
				<button type="button" class="w-full text-nbpurple-300 hover:text-nbpurple-50 hover:bg-nbpurple-800 group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold" @click="menuStore.newFolderDialogOpen()">
					<svg aria-hidden="true" class="flex size-6 shrink-0 items-center justify-center rounded-lg border border-nbpurple-700 bg-nbpurple-800 text-[0.625rem] font-medium text-nbpurple-300 group-hover:text-nbpurple-50" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" d="M12 10.5v6m3-3H9m4.06-7.19-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z" />
					</svg>
					<span class="truncate">Create folder</span>
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

	<script defer src="/scripts/dist/account-v2.js?v=357dff248a71eb29f16ebd49caba08bd"></script>
	<link href="/scripts/dist/account-v2.css?v=b53dd90fe055a3de4cdc4c77295177dd" rel="stylesheet">

	<link rel="icon" href="/assets/nb-logo-color-w.png" />
	<link href="/styles/twbuild.css?v=a2b7fa1f077749f4074a96906ff07816" rel="stylesheet">

	<!-- Pre-connect and DNS prefetch -->
	<link rel="preconnect" href="https://i.nostr.build" crossorigin>
	<link rel="preconnect" href="https://v.nostr.build" crossorigin>
	<link rel="preconnect" href="https://cdn.nostr.build" crossorigin>

	<style>
		[x-cloak] {
			display: none !important;
		}

		/* More of Uppy styles */
		[data-uppy-theme=dark] .uppy-ProviderBrowser-viewType--list {
			background-color: #302B64;
		}

		[data-uppy-theme=dark] .uppy-ProviderBrowser-viewType--list li.uppy-ProviderBrowserItem {
			color: #E4E2F3;
		}

		[data-uppy-theme=dark] .uppy-ProviderBrowser-viewType--grid li.uppy-ProviderBrowserItem--noPreview .uppy-ProviderBrowserItem-inner,
		[data-uppy-theme=dark] .uppy-ProviderBrowser-viewType--unsplash li.uppy-ProviderBrowserItem--noPreview .uppy-ProviderBrowserItem-inner {
			background-color: #A39FD6;
		}

		[data-uppy-theme=dark] .uppy-ProviderBrowser-viewType--grid li.uppy-ProviderBrowserItem--noPreview svg,
		[data-uppy-theme=dark] .uppy-ProviderBrowser-viewType--unsplash li.uppy-ProviderBrowserItem--noPreview svg {
			fill: #DAD8EE;
		}

		[data-uppy-theme=dark] .uppy-ProviderBrowser-viewType--grid .uppy-ProviderBrowserItem-inner,
		[data-uppy-theme=dark] .uppy-ProviderBrowser-viewType--unsplash .uppy-ProviderBrowserItem-inner {
			box-shadow: 0 0 0 3px #8C86CB;
		}

		[data-uppy-theme=dark] .uppy-ProviderBrowser-viewType--list .uppy-ProviderBrowserItem-checkbox:focus {
			border-color: #7069BF;
			box-shadow: 0 0 0 3px #5950B4;
		}

		[data-uppy-theme=dark] .uppy-ProviderBrowser-userLogout:focus {
			background-color: #494299;
		}

		[data-uppy-theme=dark] .uppy-ProviderBrowser-breadcrumbs {
			color: #E4E2F3;
		}

		[data-uppy-theme=dark] .uppy-ProviderBrowser-breadcrumbs button:focus {
			background-color: #494299;
		}

		[data-uppy-theme=dark] .uppy-ProviderBrowser-breadcrumbs button {
			color: #E4E2F3;
		}

		[data-uppy-theme=dark] .uppy-Provider-authTitle {
			color: #BEBBE2;
		}

		[data-uppy-theme=dark] .uppy-Provider-breadcrumbsIcon {
			color: #E4E2F3;
		}

		[data-uppy-theme=dark] .uppy-Provider-breadcrumbsIcon svg {
			fill: #E4E2F3;
		}

		[data-uppy-theme=dark] .uppy-DashboardContent-panelBody {
			background-color: #302B64;
		}

		[data-uppy-theme=dark] .uppy-Provider-breadcrumbs button:hover,
		[data-uppy-theme=dark] .uppy-Provider-breadcrumbs button:focus {
			color: #E4E2F3;
		}

		[data-uppy-theme=dark] .uppy-Dashboard-serviceMsg {
			background-color: #302B64;
			border-top: 1px solid #494299;
			border-bottom: 1px solid #494299;
		}

		[data-uppy-theme=dark] .uppy-Dashboard-serviceMsg-title {
			color: #E4E2F3;
		}

		[data-uppy-theme=dark] .uppy-Size--md.uppy-Dashboard--modal .uppy-Dashboard-inner {
			background-color: #302B64;
		}

		[data-uppy-theme=dark] .uppy-Dashboard-browse:hover,
		[data-uppy-theme=dark] .uppy-Dashboard-browse:focus {
			border-bottom: 1px solid #7069BF;
		}

		[data-uppy-theme=dark] .uppy-Dashboard-fileCard {
			background-color: #302B64;
			box-shadow: 0 0 0 2px #5950B4;
		}

		[data-uppy-theme=dark] .uppy-Dashboard-fileCard .uppy-Dashboard-fileCard-preview {
			background-color: #302B64;
			border-bottom: 1px solid #494299;
		}

		[data-uppy-theme=dark] .uppy-Dashboard-fileCard .uppy-Dashboard-fileCard-info {
			background-color: #302B64;
		}

		[data-uppy-theme=dark] .uppy-Dashboard-fileCard .uppy-Dashboard-fileCard-actions {
			background-color: #302B64;
			border-top: 1px solid #494299;
		}

		[data-uppy-theme=dark] .uppy-Dashboard-AddFiles-title {
			color: #E4E2F3;
		}

		[data-uppy-theme=dark] .uppy-Dashboard-note {
			color: #BEBBE2;
		}

		[data-uppy-theme=dark] .uppy-StatusBar:not([aria-hidden=true]).is-waiting {
			background-color: #302B64;
			border-top: 1px solid #494299;
		}

		[data-uppy-theme=dark] .uppy-StatusBar-serviceMsg {
			color: #E4E2F3;
		}

		[data-uppy-theme=dark] .uppy-DashboardContent-title {
			color: #E4E2F3;
		}

		[data-uppy-theme=dark] .uppy-DashboardContent-back:focus {
			background-color: #494299;
		}

		[data-uppy-theme=dark] .uppy-DashboardContent-addMore:focus {
			background-color: #494299;
		}

		[data-uppy-theme=dark] .uppy-DashboardTab {
			border-bottom: 1px solid #494299;
		}

		[data-uppy-theme=dark] .uppy-DashboardTab-btn {
			color: #E4E2F3;
		}

		[data-uppy-theme=dark] .uppy-DashboardTab-btn:hover {
			background-color: #494299;
		}

		[data-uppy-theme=dark] .uppy-DashboardTab-btn:active,
		[data-uppy-theme=dark] .uppy-DashboardTab-btn:focus {
			background-color: #3C367D;
		}

		[data-uppy-theme=dark] .uppy-Dashboard-Item-previewLink:focus {
			box-shadow: inset 0 0 0 3px #8C86CB;
		}

		[data-uppy-theme=dark] .uppy-Dashboard-Item-action {
			color: #BEBBE2;
		}

		[data-uppy-theme=dark] .uppy-Dashboard-Item-action:focus {
			outline: none;
		}

		[data-uppy-theme=dark] .uppy-Dashboard-Item-action::-moz-focus-inner {
			border: 0;
		}

		[data-uppy-theme=dark] .uppy-Dashboard-Item-action:focus {
			box-shadow: 0 0 0 2px #8C86CB;
		}

		[data-uppy-theme=dark] .uppy-Dashboard-Item-action:hover {
			color: #E4E2F3;
		}

		[data-uppy-theme=dark] .uppy-Dashboard-Item-action--remove {
			color: #3C367D;
		}

		[data-uppy-theme=dark] .uppy-Dashboard-Item-action--remove:hover {
			color: #494299;
		}

		[data-uppy-theme=dark] .uppy-Dashboard-dropFilesHereHint {
			color: #DAD8EE;
			border-color: #7069BF;
		}

		[data-uppy-theme=dark] .uppy-Dashboard-serviceMsg-actionBtn--disabled {
			color: #5950B4;
		}

		[data-uppy-theme=dark] .uppy-ImageCropper .cropper-view-box {
			background: repeating-conic-gradient(#302B64 0% 25%, #292556 0% 50%) 50%/16px 16px;
		}

		[data-uppy-theme=dark] .uppy-ImageCropper .cropper-modal {
			opacity: 0.7;
			background-color: #292556;
		}

		/* Uppy styles */

		[data-uppy-theme=dark] .uppy-Dashboard-inner,
		.uppy-Dashboard-inner {
			background-color: transparent;
			border: none;
		}

		[data-uppy-theme=dark] .uppy-Dashboard-AddFiles {
			background-color: transparent;
			border: none;
		}

		[data-uppy-theme=dark] .uppy-Dashboard-inner {
			background-color: transparent;
		}

		[data-uppy-theme=dark] .uppy-Dashboard-Item {
			border: none;
		}

		[data-uppy-theme=dark] .uppy-DashboardContent-bar {
			background-color: transparent;
			border-bottom: 1px solid rgb(140 134 203);
		}

		[data-uppy-theme=dark] .uppy-StatusBar.is-waiting .uppy-StatusBar-actions {
			background-color: transparent;
		}

		[data-uppy-theme=dark] .uppy-Dashboard-Item-action--remove {
			color: rgb(140 134 203);
		}

		[data-uppy-theme=dark].uppy-Dashboard-dropFilesHereHint,
		.uppy-Dashboard-dropFilesHereHint {
			color: rgb(140 134 203);
			border: none;
			background-color: transparent;
		}

		[data-uppy-theme=dark] .uppy-StatusBar:not([aria-hidden=true]).is-waiting {
			background-color: transparent;
			border-top: 1px solid rgb(140 134 203);
		}

		[data-uppy-theme=dark] .uppy-StatusBar,
		[data-uppy-theme=dark] .uppy-StatusBar:before {
			background-color: transparent;
		}

		.uppy-StatusBar.is-complete .uppy-StatusBar-progress {
			background-color: rgb(163 159 214);
		}

		[data-uppy-theme=dark] .uppy-DashboardTab-inner {
			background-color: transparent;
			box-shadow: 0 1px 1px #0003, 0 1px 2px #0003, 0 2px 3px #00000014;
		}
	</style>

</head>

<body x-data="{
	showToast: false,
	logoutScreen: false,
	// Stores
	menuStore: $store.menuStore,
	fileStore: $store.fileStore,
	GAI: $store.GAI,
	profileStore: $store.profileStore,
	nostrStore: $store.nostrStore,
	uppyStore: $store.uppyStore,
	urlImportStore: $store.urlImportStore,
	}" x-init="if (!menuStore.foldersFetched) await menuStore.fetchFolders(); $watch('showToast', value => {
		if (value) {
			setTimeout(() => showToast = false, 2000);
		}
	})" class="h-full">
	<!-- Big loading spinner -->
	<div x-show="!menuStore.alpineInitiated || !menuStore.menuStoreInitiated || !profileStore.profileDataInitialized" id="bls-screen" class="flex flex-col fixed inset-0 z-[9999] items-center justify-center bg-gradient-to-tr from-nbpurple-950 to-nbpurple-800 bg-fixed bg-no-repeat bg-cover" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-500" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
		<svg class="animate-spin size-1/4 text-nbpurple-100" fill="none" viewBox="0 0 24 24">
			<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
			<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
		</svg>
		<p class="absolute bottom-1/4 text-nbpurple-100 text-lg md:text-2xl lg:text-3xl xl:text-4xl font-semibold ml-4 animate-pulse">Loading...</p>
	</div>
	<main>
		<div>
			<!-- Off-canvas menu for mobile, show/hide based on off-canvas menu state. -->
			<div x-cloak class="relative z-50 xl:hidden" role="dialog" aria-modal="true" x-show="menuStore.mobileMenuOpen" x-transition:enter="transition-opacity ease-linear duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-linear duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
				<div class="fixed inset-0 bg-nbpurple-900/80"></div>

				<div class="fixed inset-0 flex">
					<div class="relative mr-16 flex w-full max-w-xs flex-1" x-show="menuStore.mobileMenuOpen" x-transition:enter="transition ease-in-out duration-300 transform" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in-out duration-300 transform" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full">
						<div class="absolute left-full top-0 flex w-16 justify-center pt-5">
							<button type="button" class="-m-2.5 p-2.5" @click="menuStore.mobileMenuOpen = false">
								<span class="sr-only">Close sidebar</span>
								<svg class="size-6 text-nbpurple-50" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
									<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
								</svg>
							</button>
						</div>

						<!-- Sidebar component -->
						<div class="flex grow flex-col gap-y-5 overflow-y-auto bg-nbpurple-900 px-6 ring-1 ring-nbpurple-50/10" @click.outside="menuStore.mobileMenuOpen = false; menuStore.disableDeleteFolderButtons()" @keydown.escape="menuStore.mobileMenuOpen = false; menuStore.disableDeleteFolderButtons()">
							<div class="flex h-16 shrink-0 items-center -mb-5">
								<?= $NBLogoSVG ?>
								<span class="text-nbpurple-50 font-semibold ml-4" x-text="profileStore.profileInfo.planName + ' Account'"></span>
								<!-- Upgrade or renewal button -->
								<button x-cloak x-show="profileStore.profileInfo.accountEligibleForRenewal || profileStore.profileInfo.accountEligibleForUpgrade" type="button" class="ml-auto p-1 text-nbpurple-50 hover:text-nbpurple-100 rounded-md bg-nbpurple-600 hover:bg-nbpurple-400 text-xs" @click="window.location.href='/plans/'">
									<span x-text="profileStore.profileInfo.accountEligibleForRenewal ? 'Renew' : profileStore.profileInfo.accountEligibleForUpgrade ? 'Upgrade' : ''">Renew</span>
								</button>
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
				<div class="flex grow flex-col gap-y-5 overflow-y-auto bg-nbpurple-900/10 px-6 ring-1 ring-nbpurple-50/5">
					<div class="flex h-16 shrink-0 items-center -mb-5">
						<?= $NBLogoSVG ?>
						<span class="text-nbpurple-50 font-semibold ml-4" x-text="profileStore.profileInfo.planName + ' Account'"></span>
						<!-- Upgrade or renewal button -->
						<button x-cloak x-show="profileStore.profileInfo.accountEligibleForRenewal || profileStore.profileInfo.accountEligibleForUpgrade" type="button" class="ml-auto p-1 text-nbpurple-50 hover:text-nbpurple-100 rounded-md bg-nbpurple-600 hover:bg-nbpurple-400 text-xs" @click="window.location.href='/plans/'">
							<span x-text="profileStore.profileInfo.accountEligibleForRenewal ? 'Renew' : profileStore.profileInfo.accountEligibleForUpgrade ? 'Upgrade' : ''">Renew</span>
						</button>
					</div>
					<nav class="flex flex-1 flex-col">
						<!-- Desktop menu content -->
						<?= $pageMenuContent ?>
					</nav>
				</div>
			</div>

			<div class="xl:pl-72">
				<!-- Sticky search header -->
				<div class="sticky top-0 z-40 flex h-16 shrink-0 items-center gap-x-6 border-b border-nbpurple-50/5 bg-nbpurple-900 px-4 shadow-sm sm:px-6 lg:px-8">
					<button type="button" class="-m-2.5 p-2.5 text-nbpurple-50 xl:hidden" @click="menuStore.mobileMenuOpen = true">
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
						<!--
						<button type="button" class="p-2.5 text-nbpurple-50">
							<span class="sr-only">Notifications</span>
							<svg :class="{ 'animate-[wiggle_1s_ease-in-out_infinite]': false }" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
								<path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
							</svg>
-->
						<!--
							<span x-cloak class="animate-ping absolute top-2 right-2.5 block h-1 w-1 rounded-full ring-2 ring-nbpurple-400 bg-nbpurple-600"></span>
							-->
						<!--
						</button>
-->
						<!-- Refresh folder content button -->
						<button :disabled="loading" @click="loading = true; fileStore.refreshFoldersAfterFetch = true; fileStore.fetchFiles(menuStore.activeFolder, true).finally(() => setTimeout(() => { loading = false}, 1000))" type="button" class="p-2 text-nbpurple-100" x-data="{ loading: false }">
							<svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" :class="{'animate-spin': loading}" class="size-6">
								<path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
							</svg>
							<span class="sr-only">Refresh file list</span>
						</button>
					</div>
				</div>

				<main x-cloak x-show="menuStore.menuItemsAI.find(item => item.name === menuStore.activeMenu)" class="h-full lg:pr-[41rem]">
					<!-- Main content -->
					<h3 class="px-6 py-2 text-1xl font-semibold text-nbpurple-50" x-text="menuStore.activeMenu"></h3>
					<template x-if="profileStore.profileInfo.isAIStudioEligible">
						<div class="p-4">
							<form action="#" class="relative" x-data="{ assignOpen: false, labelOpen: false, dueDateOpen: false, title: '', prompt: '', selectedModel: '@cf/lykon/dreamshaper-8-lcm' }">
								<!-- Clear button -->
								<div x-cloak x-show="title.length > 0 || prompt.length > 0" class="flex-shrink-0 absolute top-1 right-1 z-10">
									<button type="button" class="inline-flex items-center rounded-full bg-nbpurple-600/50 p-1 sm:text-sm text-xs font-semibold text-nbpurple-50 shadow-sm hover:bg-nbpurple-500/50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nbpurple-600" @click="title = ''; prompt = ''; GAI.clearImage()">
										<span class="sr-only">Clear fields</span>
										<svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-rotate-ccw">
											<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
											<path d="M3 3v5h5" />
										</svg>
									</button>
								</div>
								<!-- Form fields -->
								<div class="overflow-hidden rounded-lg border border-nbpurple-300 shadow-sm focus-within:border-nbpurple-500 focus-within:ring-1 focus-within:ring-nbpurple-500">
									<!--
									<label for="title" class="sr-only">Title</label>
									<input x-model="title" type="text" name="title" id="title" class="block w-full border-0 pt-2.5 text-sm text-nbpurple-800 font-medium placeholder:text-nbpurple-400 focus:ring-0 bg-nbpurple-50" placeholder="Title (name your creation)">
					-->
									<label for="prompt" class="sr-only">Prompt</label>
									<textarea x-model="prompt" rows="3" name="prompt" id="prompt" class="block w-full resize-none border-0 py-0 pt-2 text-nbpurple-900 placeholder:text-nbpurple-400 focus:ring-0 sm:text-sm sm:leading-6 bg-nbpurple-100" placeholder="(prompt) ex.: purple ostrich surfing a big wave ..."></textarea>

									<!-- Spacer element to match the height of the toolbar -->
									<div aria-hidden="true">
										<div class="py-2">
											<div class="py-px">
												<div class="h-[2.1rem]"></div>
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
																			{ value: '@cf/lykon/dreamshaper-8-lcm', title: 'Dream Shaper', description: 'Stable Diffusion model that has been fine-tuned to be better at photorealism without sacrificing range.', disabled: !profileStore.profileInfo.isAIDreamShaperEligible },
																			{ value: '@cf/bytedance/stable-diffusion-xl-lightning', title: 'SDXL-Lightning', description: 'SDXL-Lightning is a lightning-fast text-to-image generation model. It can generate high-quality 1024px images in a few steps.', disabled: !profileStore.profileInfo.isAISDXLLightningEligible },
																			{ value: '@cf/stabilityai/stable-diffusion-xl-base-1.0', title: 'Stable Diffusion', description: 'Diffusion-based text-to-image generative model by Stability AI. Generates and modify images based on text prompts.', disabled: !profileStore.profileInfo.isAISDiffusionEligible },
																		]
																	}">
												<label id="listbox-label" class="sr-only">Change generative model</label>
												<div class="relative">
													<div class="inline-flex divide-x divide-nbpurple-700 rounded-md shadow-sm">
														<div class="inline-flex items-center gap-x-1.5 rounded-l-md bg-nbpurple-600 px-3 py-2 text-nbpurple-50 shadow-sm">
															<svg class="-ml-0.5 h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
																<path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
															</svg>
															<p class="sm:text-sm text-xs font-semibold" x-text="selectedModelTitle"></p>
														</div>
														<button type="button" class="inline-flex items-center rounded-l-none rounded-r-md bg-nbpurple-600 p-2 hover:bg-nbpurple-700 focus:outline-none focus:ring-2 focus:ring-nbpurple-600 focus:ring-offset-2 focus:ring-offset-nbpurple-50" aria-haspopup="listbox" aria-expanded="true" aria-labelledby="listbox-label" @click="modelMenuOpen = !modelMenuOpen">
															<span class="sr-only">Change generative model</span>
															<svg class="h-5 w-5 text-nbpurple-50" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
																<path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
															</svg>
														</button>
													</div>
													<ul id="models-listbox" x-cloak @click.outside="modelMenuOpen = false" x-show="modelMenuOpen" x-transition:enter="" x-transition:enter-start="" x-transition:enter-end="" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="absolute left-0 z-30 mt-2 sm:w-96 xs:w-72 w-64 origin-top-left divide-y divide-nbpurple-200 overflow-hidden rounded-md bg-nbpurple-500 shadow-lg ring-1 ring-nbpurple-900 ring-opacity-5 focus:outline-none" tabindex="-1" role="listbox" aria-labelledby="listbox-label" aria-activedescendant="listbox-option-0">
														<template x-for="(option, index) in modelOptions" :key="option.value">
															<li :class="{
																						'bg-nbpurple-600 cursor-pointer': selectedModel === option.value,
																						'text-nbpurple-200 cursor-not-allowed': option.disabled,
																						'hover:bg-nbpurple-700 text-nbpurple-50 cursor-pointer': !option.disabled
																					}" class="select-none p-4 text-sm" :id="'listbox-option-' + index" role="option" @click="if (!option.disabled) { selectedModel = option.value; selectedModelTitle = option.title; modelMenuOpen = false; }">
																<div class="flex flex-col">
																	<div class="flex justify-between">
																		<div class="flex items-center">
																			<p class="font-normal" x-text="option.title"></p>
																			<a x-show="option.disabled" href="/plans/" class="ml-2 text-nbpurple-50 text-xs hover:underline">
																				Upgrade to Advanced or Renew
																			</a>
																		</div>
																		<span class="text-nbpurple-50" x-show="selectedModel === option.value">
																			<svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
																				<path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
																			</svg>
																		</span>
																	</div>
																	<p class="text-nbpurple-50 leading-tight mt-2" x-text="option.description"></p>
																</div>
															</li>
														</template>
													</ul>
												</div>
												<!-- Hidden input field to store the selected model value -->
												<input id="selected-model" type="hidden" name="selectedModel" x-model="selectedModel">
											</div>
										</div>
										<div class="flex-shrink-0">
											<button @click="await GAI.generateImage(title, prompt, selectedModel)" type="button" class="inline-flex items-center rounded-md bg-nbpurple-600 px-3 py-2 sm:text-sm text-xs h-9 font-semibold text-nbpurple-50 shadow-sm hover:bg-nbpurple-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nbpurple-600 disabled:bg-nbpurple-400" :disabled="prompt.trim() === '' || GAI.ImageLoading === true">Generate</button>
										</div>
									</div>
								</div>
							</form>
						</div>
					</template>

					<!-- Generate image -->
					<template x-if="profileStore.profileInfo.isAIStudioEligible">
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
									<p class="text-sm text-gray-300">URL: <a :href="GAI.file.url" target="_blank" x-text="GAI.ImageUrl"></a>
										<svg class="size-3 ml-1 inline-block" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
											<path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
										</svg>
									</p>
								</div>
							</div>
						</div>
					</template>
					<!-- /Generate image -->
					<template x-if="!profileStore.profileInfo.isAIStudioEligible">
						<div class="p-4">
							<div class="bg-black/10 rounded-lg shadow-xl my-8 mx-auto w-11/12 max-w-2xl p-4 max-h-48 align-middle">
								<p class="text-nbpurple-200 text-center text-md">Your plan does not include AI Features or subscription expired/storage full.</p>
								<!-- Upgrade button -->
								<div class="flex justify-center mt-4">
									<a href="/plans/" class="inline-flex items-center rounded-md bg-nbpurple-600 px-3 py-2 sm:text-sm text-xs h-9 font-semibold text-nbpurple-50 shadow-sm hover:bg-nbpurple-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nbpurple-600">Upgrade or Renew</a>
								</div>
							</div>
						</div>
					</template>
					<!-- /Main content -->
				</main>

				<!-- Activity feed -->
				<aside x-data="{
					showScrollButton: false,
					multiSelect: false,
					uploadDialog: false,
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
				}" x-ref="sidebar" @scroll.window.throttle="showScrollButton = window.pageYOffset > 500" @scroll.throttle="showScrollButton = $refs.sidebar.scrollTop > 500" :class="fileStore.fullWidth ? 'lg:w-full xl:pl-72' : 'lg:w-[40rem]'" class="bg-nbpurple-900/10 lg:fixed lg:bottom-0 lg:right-0 lg:top-16 lg:overflow-y-auto lg:border-l lg:border-nbpurple-950/5">
					<!-- Floating bar -->
					<div class="z-20 sticky top-16 lg:top-0 bg-nbpurple-900/75 py-2 px-4 md:px-4 align-middle flex items-center justify-between backdrop-filter backdrop-blur-md">
						<!-- Refresh folder content button -->
						<!--
						<button :disabled="loading" @click="loading = true; fileStore.fetchFiles(menuStore.activeFolder, true).finally(() => setTimeout(() => { loading = false}, 1000))" type="button" class="mr-3 inline-flex items-center rounded-md bg-nbpurple-600 px-3 py-2 text-xs font-semibold text-nbpurple-50 shadow-sm hover:bg-nbpurple-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nbpurple-600" x-data="{ loading: false }">
							<svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" :class="{'animate-spin': loading}" class="size-4">
								<path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
							</svg>
							<span class="hidden sm:inline-block sm:ml-1"> Refresh</span>
						</button>
			-->
						<h3 class="text-sm sm:text-base font-semibold leading-6 text-nbpurple-100" x-text="menuStore.activeFolder"></h3>
						<div class="flex ml-4 mt-0 transition-all">
							<!-- Upload button -->
							<button x-cloak x-show="profileStore.profileInfo.isUploadEligible" @click="uppyStore.mainDialog.toggle()" type="button" class="ml-3 inline-flex items-center rounded-md bg-nbpurple-600 px-3 py-2 text-xs font-semibold text-nbpurple-50 shadow-sm hover:bg-nbpurple-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nbpurple-600">
								<svg x-show="true" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" class="size-4">
									<path d="M10.3 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10l-3.1-3.1a2 2 0 0 0-2.814.014L6 21" />
									<path d="m14 19.5 3-3 3 3" />
									<path d="M17 22v-5.5" />
									<circle cx="9" cy="9" r="2" />
								</svg>
								<span class="hidden sm:inline-block sm:ml-1" x-text="uppyStore.mainDialog.isOpen ? 'Hide' : uppyStore.mainDialog.uploadProgress ? 'Uploading' : 'Upload'"></span>
								<span x-cloak x-show="uppyStore.mainDialog.isLoading && !uppyStore.mainDialog.isOpen" class="text-xs font-semibold text-nbpurple-50 ml-1" x-text="'(' + uppyStore.mainDialog.uploadProgress + ')'"></span>
							</button>
							<!-- Multi-select button -->
							<button @click="toggleMultiSelect()" type="button" class="ml-3 inline-flex items-center rounded-md bg-nbpurple-600 px-3 py-2 text-xs font-semibold text-nbpurple-50 shadow-sm hover:bg-nbpurple-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nbpurple-600">
								<svg x-show="!multiSelect" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 mr-1">
									<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
								</svg>
								<svg x-cloak x-show="multiSelect" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
									<path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
								</svg>
								<span class="hidden xs:inline-block xs:ml-1" x-text="multiSelect ? 'Cancel' : 'Select'"></span>
							</button>
						</div>
					</div>
					<!-- /Floating bar -->
					<!-- File upload area -->
					<div x-cloak x-show="uppyStore.mainDialog.isOpen && profileStore.profileInfo.isUploadEligible" class="z-20 sticky top-28 lg:top-12 bg-nbpurple-900/75 py-2 px-4 md:px-4 backdrop-filter backdrop-blur-md max-h-[52svh]">
						<!-- URL Import -->
						<div class="mt-2 px-2 rounded-lg border border-dashed border-nbpurple-50/25 py-1 w-full h-12">
							<label for="import-url" class="sr-only">Import URL</label>
							<div class="mt-[0.075rem] flex rounded-md shadow-sm">
								<div class="relative flex flex-grow items-stretch focus-within:z-10">
									<div x-show="urlImportStore.importURL.length === 0" @click="urlImportStore.importURL = await navigator.clipboard.readText()" class="cursor-pointer absolute inset-y-0 left-0 flex items-center pl-3">
										<svg class="h-5 w-5 text-nbpurple-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
											<path d="M15 2H9a1 1 0 0 0-1 1v2c0 .6.4 1 1 1h6c.6 0 1-.4 1-1V3c0-.6-.4-1-1-1Z" />
											<path d="M8 4H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2M16 4h2a2 2 0 0 1 2 2v2M11 14h10" />
											<path d="m17 10 4 4-4 4" />
										</svg>
									</div>
									<div x-show="urlImportStore.importURL.length > 0" @click="urlImportStore.importURL = ''" class="cursor-pointer absolute inset-y-0 left-0 flex items-center pl-3">
										<svg class="h-5 w-5 text-nbpurple-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
											<path d="m7 21-4.3-4.3c-1-1-1-2.5 0-3.4l9.6-9.6c1-1 2.5-1 3.4 0l5.6 5.6c1 1 1 2.5 0 3.4L13 21" />
											<path d="M22 21H7" />
											<path d="m5 11 9 9" />
										</svg>
									</div>
									<input :disabled="urlImportStore.isLoading " x-model="urlImportStore.importURL" type="url" name="import-url" id="import-url" class="block w-full rounded-none rounded-l-md border-0 py-1.5 pl-10 text-nbpurple-900 ring-1 ring-inset ring-nbpurple-300 placeholder:text-nbpurple-400 focus:ring-2 focus:ring-inset focus:ring-nbpurple-600 sm:text-sm sm:leading-6" placeholder="https://example.com/image.jpg">
								</div>
								<button @click="urlImportStore.importFromURL()" :disabled="urlImportStore.isLoading" type="button" class="relative -ml-px inline-flex items-center gap-x-1.5 rounded-r-md px-3 py-2 text-sm font-semibold text-nbpurple-50 ring-1 ring-inset ring-nbpurple-300 bg-nbpurple-600 hover:bg-nbpurple-500 disabled:hover:bg-nbpurple-400 disabled:bg-nbpurple-400">
									<svg x-show="!urlImportStore.isLoading && !urlImportStore.isError" class="-ml-0.5 h-5 w-5 text-nbpurple-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
										<path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242" />
										<path d="M12 12v9" />
										<path d="m8 17 4 4 4-4" />
									</svg>
									<svg x-show="urlImportStore.isLoading" class="animate-spin -ml-0.5 h-5 w-5 text-nbpurple-100" fill="none" viewBox="0 0 24 24">
										<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
										<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
									</svg>
									<svg x-show="!urlImportStore.isLoading && urlImportStore.isError" class="-ml-0.5 h-5 w-5 text-orange-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
										<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
									</svg>
									Import
								</button>
							</div>
						</div>
						<!-- File upload (Uppy) -->
						<div x-init="uppyStore.instantiateUppy($el)" class="mt-2 rounded-lg border border-dashed border-nbpurple-50/25 py-1 w-full h-full max-h-[40svh] overflow-y-scroll overflow-visible">
							<!-- Uppy component -->
						</div>
					</div>
					<!-- /File upload area -->
					<!-- File actions -->
					<!-- Activity feed content -->
					<div x-data="{
						/* TODO: Implement drag and drop to upload
							init() {
								$el.addEventListener('dragover', (event) => {
									event.preventDefault();
									//event.stopPropagation();
									event.dataTransfer.dropEffect = 'copy';
								});

								$el.addEventListener('drop', (event) => {
									event.preventDefault();
									event.stopPropagation();
									const files = Array.from(event.dataTransfer.files);
									this.addFilesToUppy(files);
								});
							},
							addFilesToUppy(files) {
								files.forEach((file) => {
									try {
										uppyStore.instance.addFile({
											source: 'drop',
											name: file.name,
											type: file.type,
											data: file,
										});
									} catch (error) {
										console.log('Error adding file to Uppy');
									}
								});
							}
							*/
						}" class="p-4" x-effect="if (fileStore.files.length === 0) await fileStore.fetchFiles(menuStore.activeFolder)">
						<ul role="list" :class="fileStore.fullWidth ? 'lg:grid-cols-4 md:grid-cols-3' : 'md:grid-cols-2'" class="grid grid-cols-2 gap-x-4 gap-y-8 md:gap-x-4 xl:gap-x-6">
							<template x-if="fileStore.loading || !menuStore.activeFolder">
								<li class="col-span-full relative">
									<div class="group aspect-h-2 aspect-w-10 block w-full overflow-hidden rounded-lg bg-black/50 focus-within:ring-2 focus-within:ring-nbpurple-500 focus-within:ring-offset-2 focus-within:ring-offset-nbpurple-100">
										<div class="pointer-events-none object-cover group-hover:opacity-75 h-full flex items-center justify-center">
											<svg class="pointer-events-none object-cover group-hover:opacity-75 h-full w-full text-nbpurple-400 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
											</svg>
										</div>
									</div>
									<!-- Loading state -->
									<div class="mt-2 text-center">
										<p class="text-nbpurple-200 text-2xl animate-pulse">Loading ...</p>
									</div>
								</li>
							</template>
							<template x-if="!fileStore.loading && fileStore.files.length === 0 && menuStore.activeFolder">
								<li class="col-span-full relative">
									<!--
									<div class="group aspect-h-2 aspect-w-10 block w-full overflow-hidden rounded-lg bg-black/50 focus-within:ring-2 focus-within:ring-nbpurple-500 focus-within:ring-offset-2 focus-within:ring-offset-nbpurple-100">
										<svg class="pointer-events-none object-cover group-hover:opacity-75 h-full w-full text-nbpurple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
										</svg>
									</div>
			-->
									<!-- Empty state -->
									<div x-init="uppyStore.mainDialog.open()" class="mt-2 text-center">
										<p class="text-nbpurple-200 text-2xl">No media found in this folder.</p>
									</div>
								</li>
							</template>
							<template x-for="file in fileStore.files" :key="file.id">
								<li :id="file.id" draggable="true" x-on:dragend="
										dragging = false;
										const dragImage = document.getElementById('drag-image');
										if (dragImage) dragImage.remove();
										" x-on:dragstart.self="
											dragging = true;
											event.dataTransfer.effectAllowed='move';
											event.dataTransfer.setData('text/plain', event.target.id);
											const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
											if (!isSafari) {
												const mediaElement = document.getElementById('media_' + file.id);
												if (mediaElement) {
													const dragImage = document.createElement('div');
													dragImage.id = 'drag-image';
													dragImage.classList.add('z-50', 'fixed', 'pointer-events-none', 'opacity-90', 'drop-shadow-xl', 'object-cover', 'rounded-lg', 'overflow-hidden', 'max-h-24', 'max-w-24');

													if (mediaElement.tagName === 'IMG') {
														const imgElement = document.createElement('img');
														imgElement.src = mediaElement.src;
														imgElement.classList.add('w-full', 'h-full', 'object-contain');
														dragImage.appendChild(imgElement);
													} else if (mediaElement.tagName === 'VIDEO') {
														const videoElement = document.createElement('video');
														videoElement.src = mediaElement.src;
														videoElement.classList.add('w-full', 'h-full', 'object-contain');
														videoElement.poster = mediaElement.poster;
														videoElement.autoplay = false;
														videoElement.muted = true;
														dragImage.appendChild(videoElement);
													}

													document.body.appendChild(dragImage);

													const updateDragImagePosition = (e) => {
														const offsetX = mediaElement.clientWidth * 0.25; // Adjust the horizontal offset
														const offsetY = mediaElement.clientHeight * 0.25; // Adjust the vertical offset
														dragImage.style.left = (e.clientX - offsetX) + 'px';
														dragImage.style.top = (e.clientY - offsetY) + 'px';
													};

													updateDragImagePosition(event);
													document.addEventListener('dragover', updateDragImagePosition);

													event.dataTransfer.setDragImage(new Image(), 0, 0);
												}
											}
									" :class="{ 'opacity-50 drop-shadow-xl': dragging }" class="relative" x-data="{ showMediaActions: false, dragging: false }">
									<!-- Media type badge -->
									<div x-show="file.mime.startsWith('image/') && !file.mime.endsWith('/gif')" class="z-10 absolute -top-3 -right-3 size-6 bg-nbpurple-600 text-nbpurple-50 text-xs font-semibold rounded-full flex items-center justify-center">
										<svg stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="size-5">
											<rect width="18" height="18" x="3" y="3" rx="2" ry="2" />
											<circle cx="9" cy="9" r="2" />
											<path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21" />
										</svg>
									</div>
									<div x-show="file.mime.endsWith('/gif')" class="z-10 absolute -top-3 -right-3 size-6 bg-nbpurple-600 text-nbpurple-50 text-xs font-semibold rounded-full flex items-center justify-center">
										<svg stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="size-5">
											<path d="m11 16-5 5" />
											<path d="M11 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6.5" />
											<path d="M15.765 22a.5.5 0 0 1-.765-.424V13.38a.5.5 0 0 1 .765-.424l5.878 3.674a1 1 0 0 1 0 1.696z" />
											<circle cx="9" cy="9" r="2" />
										</svg>
									</div>
									<div x-show="file.mime.startsWith('video/')" class="z-10 absolute -top-3 -right-3 size-6 bg-nbpurple-600 text-nbpurple-50 text-xs font-semibold rounded-full flex items-center justify-center">
										<svg stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="size-5">
											<path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5" />
											<rect x="2" y="6" width="14" height="12" rx="2" />
										</svg>
									</div>
									<div x-show="file.mime.startsWith('audio/')" class="z-10 absolute -top-3 -right-3 size-6 bg-nbpurple-600 text-nbpurple-50 text-xs font-semibold rounded-full flex items-center justify-center">
										<svg stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="size-5">
											<path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3" />
										</svg>
									</div>
									<!-- /Media type badge -->
									<!-- Creators page badge -->
									<div x-cloak x-show="file.flag === 1" class="cursor-pointer absolute -top-[0.85rem] left-3 z-10">
										<span class="inline-flex items-center rounded-md bg-black/85 px-2 py-1 text-xs font-medium text-yellow-300 ring-1 ring-inset ring-yellow-400/20">
											Creators
											<span class="hidden xs:inline ml-1">Page</span>
										</span>
									</div>
									<!-- /Creators page badge -->
									<!-- Nostr badge -->
									<div x-data="{ events: 0 }" x-effect="events = file.associated_notes?.split(',')?.length || 0" x-show="events" class="cursor-pointer hover:animate-[wiggle_1s_ease-in-out_infinite] z-10 absolute py-1 h-auto top-4 -right-3 size-6 bg-nbpurple-600 text-nbpurple-50 text-xs font-semibold rounded-full flex flex-col items-center justify-center">
										<svg class="size-5" stroke="currentColor" fill="currentColor" viewBox="0 0 256 256" xmlns="http://www.w3.org/2000/svg">
											<circle cx="137.9" cy="99" fill="#fff" r="12.1" />
											<path d="M210.8 115.9c0-47.3-27.7-68.7-64.4-68.7-16.4 0-31 4.4-42.4 12.5-3.8 2.7-9 .1-9-4.5 0-3.1-2.5-5.7-5.7-5.7H57.7c-3.1 0-5.7 2.5-5.7 5.7v144c0 3.1 2.5 5.7 5.7 5.7h33.7c3.1 0 5.6-2.5 5.6-5.6v-8.4c0-62.8-33.2-109.8-.4-116 30-5.7 64.1-3 64.5 20.1 0 2 .3 8 8.6 11.2 5 2 12.6 2.6 22.6 2.4 0 0 9.1-.7 9.1 8.5 0 11.5-20.4 10.7-20.4 10.7-6.7.3-22.6-1.5-31.7 1.2-4.8 1.5-9 4.2-11.5 9.1-4.2 8.3-6.2 26.5-6.5 45.5v15.5c0 3.1 2.5 5.7 5.7 5.7h68c3.1 0 5.7-2.5 5.7-5.7v-83.2z" fill="#fff" />
										</svg>
										<span class="text-xs -mt-[0.175] " x-text="events"></span>
									</div>
									<!-- /Nostr badge -->
									<div class="relative group aspect-h-7 aspect-w-10 w-full overflow-hidden rounded-lg bg-black/50 focus-within:ring-2 focus-within:ring-nbpurple-500 focus-within:ring-offset-2 focus-within:ring-offset-nbpurple-100">

										<!-- Loading placeholders -->
										<div :id="'placeholders_' + file.id">
											<template x-if="!file.loaded && file.mime.startsWith('image/') && !file.name.endsWith('.gif')">
												<svg class="absolute inset-0 pointer-events-none object-cover group-hover:opacity-75 h-full w-full text-nbpurple-400 animate-pulse" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
													<rect width="18" height="18" x="3" y="3" rx="2" ry="2" />
													<circle cx="9" cy="9" r="2" />
													<path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21" />
												</svg>
											</template>

											<template x-if="!file.loaded && file.mime.startsWith('image/') && file.name.endsWith('.gif')">
												<svg class="absolute inset-0 pointer-events-none object-cover group-hover:opacity-75 h-full w-full text-nbpurple-400 animate-pulse" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
													<path d="m11 16-5 5" />
													<path d="M11 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6.5" />
													<path d="M15.765 22a.5.5 0 0 1-.765-.424V13.38a.5.5 0 0 1 .765-.424l5.878 3.674a1 1 0 0 1 0 1.696z" />
													<circle cx="9" cy="9" r="2" />
												</svg>
											</template>

											<template x-if="!file.loaded && file.mime.startsWith('video/')">
												<svg class="absolute inset-0 pointer-events-none object-cover group-hover:opacity-75 h-full w-full text-nbpurple-400 animate-pulse" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
													<path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5" />
													<rect x="2" y="6" width="14" height="12" rx="2" />
												</svg>
											</template>

											<template x-if="!file.loaded && file.mime.startsWith('audio/')">
												<svg class="absolute inset-0 pointer-events-none object-cover group-hover:opacity-75 h-full w-full text-nbpurple-400 animate-pulse" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
													<path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3" />
												</svg>
											</template>
										</div>
										<!-- /Loading placeholders -->

										<!-- Media actions -->
										<div x-cloak x-show="showMediaActions" @click.outside="showMediaActions = false || fileStore.deleteConfirmation.isOpen || fileStore.shareMedia.isOpen || fileStore.moveToFolder.isOpen" class="absolute inset-0 object-contain bg-black/80 py-1 px-3 sm:py-2 z-[9] flex flex-col">
											<div class="m-auto w-5/6 grid gap-1 grid-cols-3 place-items-center">
												<!-- Buttons -->
												<!-- Delete button -->
												<button x-data="{deleteClick: false}" @click="fileStore.deleteConfirmation.open(file.id)" class="p-1 bg-nbpurple-600/10 text-nbpurple-50 rounded-md hover:bg-nbpurple-700/10 focus:outline-none focus:ring-2 focus:ring-nbpurple-500/10" aria-label="Delete media file">
													<svg x-show="!deleteClick" class="max-h-11 w-full inline-block" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" x-transition:enter="transition-opacity duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
														<path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
													</svg>
													<svg x-cloak x-show="deleteClick" class="max-h-11 animate-[pulse_3s_ease-in-out_infinite] w-full inline-block" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" x-transition:enter="transition-opacity duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
														<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
													</svg>
												</button>
												<!-- Share button -->
												<button x-cloak x-show="profileStore.profileInfo.isShareEligible" x-data="{shareClick: false}" @click="fileStore.shareMedia.open(file.id)" class="p-1 bg-nbpurple-600/10 text-nbpurple-50 rounded-md hover:bg-nbpurple-700/10 focus:outline-none focus:ring-2 focus:ring-nbpurple-500/10" aria-label="Share media file">
													<svg x-show="!shareClick" class="max-h-11 w-full inline-block" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" x-transition:enter="transition-opacity duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
														<path stroke-linecap="round" stroke-linejoin="round" d="M7.5 7.5h-.75A2.25 2.25 0 0 0 4.5 9.75v7.5a2.25 2.25 0 0 0 2.25 2.25h7.5a2.25 2.25 0 0 0 2.25-2.25v-7.5a2.25 2.25 0 0 0-2.25-2.25h-.75m0-3-3-3m0 0-3 3m3-3v11.25m6-2.25h.75a2.25 2.25 0 0 1 2.25 2.25v7.5a2.25 2.25 0 0 1-2.25 2.25h-7.5a2.25 2.25 0 0 1-2.25-2.25v-.75" />
													</svg>
													<svg x-cloak x-show="shareClick" class="max-h-11 animate-[pulse_3s_ease-in-out_infinite] w-full inline-block" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" x-transition:enter="transition-opacity duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
														<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
													</svg>
												</button>
												<!-- Move button -->
												<button x-data="{moveClick: false}" @click="fileStore.moveToFolder.open(file.id)" class="p-1 bg-nbpurple-600/10 text-nbpurple-50 rounded-md hover:bg-nbpurple-700/10 focus:outline-none focus:ring-2 focus:ring-nbpurple-500/10" aria-label="Move media file to folder">
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
												<button x-data="{copyClick: false}" @click="copyUrlToClipboard(file.url); copyClick = true; setTimeout(() => copyClick = false, 2000); showToast = true" class="p-1 sm:hidden bg-nbpurple-600/10 text-nbpurple-50 rounded-md hover:bg-nbpurple-700/10 focus:outline-none focus:ring-2 focus:ring-nbpurple-500/10" aria-label="Copy image URL to clipboard">
													<svg x-show="!copyClick" class="max-h-11 w-full inline-block" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" x-transition:enter="transition-opacity duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
														<path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75" />
													</svg>
													<svg x-cloak x-show="copyClick" class="max-h-11 animate-[pulse_3s_ease-in-out_infinite] w-full inline-block" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" x-transition:enter="transition-opacity duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
														<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
													</svg>
												</button>
												<!-- Nostr Share -->
												<button x-cloak x-show="profileStore.profileInfo.isNostrShareEligible" @click="nostrStore.share.open(file.id)" class="p-1 xs:hidden bg-nbpurple-600/10 text-nostrpurple-50 rounded-md hover:bg-nbpurple-700/10 focus:outline-none focus:ring-2 focus:ring-nbpurple-500/10" aria-label="Share image on Nostr">
													<svg class="max-h-11 w-full inline-block" stroke="currentColor" fill="currentColor" viewBox="0 0 256 256" xmlns="http://www.w3.org/2000/svg">
														<circle cx="137.9" cy="99" fill="#fff" r="12.1" />
														<path d="M210.8 115.9c0-47.3-27.7-68.7-64.4-68.7-16.4 0-31 4.4-42.4 12.5-3.8 2.7-9 .1-9-4.5 0-3.1-2.5-5.7-5.7-5.7H57.7c-3.1 0-5.7 2.5-5.7 5.7v144c0 3.1 2.5 5.7 5.7 5.7h33.7c3.1 0 5.6-2.5 5.6-5.6v-8.4c0-62.8-33.2-109.8-.4-116 30-5.7 64.1-3 64.5 20.1 0 2 .3 8 8.6 11.2 5 2 12.6 2.6 22.6 2.4 0 0 9.1-.7 9.1 8.5 0 11.5-20.4 10.7-20.4 10.7-6.7.3-22.6-1.5-31.7 1.2-4.8 1.5-9 4.2-11.5 9.1-4.2 8.3-6.2 26.5-6.5 45.5v15.5c0 3.1 2.5 5.7 5.7 5.7h68c3.1 0 5.7-2.5 5.7-5.7v-83.2z" fill="#fff" />
													</svg>
												</button>
											</div>
										</div>
										<!-- /Media actions -->

										<!-- Image -->
										<template x-if="file.mime.startsWith('image/')">
											<img x-intersect.once.margin.1024px="
												$el.src=file.thumb;
												$el.srcset=file.srcset;
												$el.sizes=file.sizes;
												if (file.loadMore && !fileStore.loading && !fileStore.loadingMoreFiles) setTimeout(async () => {await fileStore.loadMoreFiles()}, 0);
												" :id="'media_' + file.id" @load="$el.classList.remove('opacity-0'); file.loaded = true;" @error="file.loaded = true, file.loadError" :data-src="file.thumb" :alt="file.name" :width="file.width" :height="file.height" loading="eager" class="opacity-0 transition-opacity duration-500 ease-in pointer-events-none object-contain group-hover:opacity-75" />
										</template>

										<!-- Video -->
										<template x-if="file.mime.startsWith('video/')">
											<video :id="'media_' + file.id" crossorigin="anonymous" x-intersect.once.margin.1024px="
														$el.src = file.url;
														$el.load();
														if (file.loadMore && !fileStore.loading && !fileStore.loadingMoreFiles) fileStore.loadMoreFiles();
													" @loadedmetadata="file.loaded = true;" playsinline preload="none" class="pointer-events-none object-cover">
												<source :data-src="file.url" type="video/mp4">
												<p>
													Your browser does not support the video tag.
													<a :href="file.url" download>Download the video</a>
												</p>
											</video>
										</template>

										<!-- Audio -->
										<template x-if="file.mime.startsWith('audio/')">
											<!-- Default poster -->
											<img :id="'media_' + file.id" src="https://cdn.nostr.build/assets/audio/jpg/audio-wave@0.5x.jpg" :alt="'Poster for ' + file.name" @load="file.loaded = true;" loading="eager" class="pointer-events-none object-cover group-hover:opacity-75" />
										</template>

										<button @click="fileStore.openModal(file)" type="button" :class="{'hidden pointer-events-none': showMediaActions}" class="absolute inset-0 focus:outline-none">
											<span class="sr-only" x-text="'View details for ' + file.name"></span>
											<div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-80 transition-opacity duration-300">
												<svg x-show="file.mime.startsWith('image/')" class="size-1/3 text-nbpurple-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
													<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
												</svg>
												<svg x-show="file.mime.startsWith('video/')" class="size-1/3 text-nbpurple-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
													<path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5" />
													<rect x="2" y="6" width="14" height="12" rx="2" />
												</svg>
												<svg x-show="file.mime.startsWith('audio/')" class="size-1/3 text-nbpurple-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
													<path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3" />
												</svg>
											</div>
										</button>
									</div>
									<div class="flex justify-between items-center">
										<div>
											<p class="pointer-events-none mt-2 block truncate text-sm font-medium text-nbpurple-300" x-text="file.name"></p>
											<p class="pointer-events-none block text-sm font-medium text-nbpurple-500" x-text="formatBytes(file.size)"></p>
										</div>
										<!-- Normal actions -->
										<div x-show="!multiSelect" x-data="{copyClick: false}">
											<!-- Nostr Share -->
											<button x-cloak x-show="profileStore.profileInfo.isNostrShareEligible" @click="nostrStore.share.open(file.id)" class="hidden xs:inline-block ring-1 ring-nostrpurple-400 mt-2 px-2 py-1 bg-nostrpurple-500 text-nostrpurple-50 rounded-md hover:bg-nostrpurple-400 focus:outline-none focus:ring-2 focus:ring-nostrpurple-300 shadow-sm hover:shadow-nostrpurple-100" aria-label="Share image on Nostr">
												<svg aria-hidden="true" class="size-6 inline-block" stroke="currentColor" fill="currentColor" viewBox="0 0 256 256" xmlns="http://www.w3.org/2000/svg">
													<circle cx="137.9" cy="99" fill="#fff" r="12.1" />
													<path d="M210.8 115.9c0-47.3-27.7-68.7-64.4-68.7-16.4 0-31 4.4-42.4 12.5-3.8 2.7-9 .1-9-4.5 0-3.1-2.5-5.7-5.7-5.7H57.7c-3.1 0-5.7 2.5-5.7 5.7v144c0 3.1 2.5 5.7 5.7 5.7h33.7c3.1 0 5.6-2.5 5.6-5.6v-8.4c0-62.8-33.2-109.8-.4-116 30-5.7 64.1-3 64.5 20.1 0 2 .3 8 8.6 11.2 5 2 12.6 2.6 22.6 2.4 0 0 9.1-.7 9.1 8.5 0 11.5-20.4 10.7-20.4 10.7-6.7.3-22.6-1.5-31.7 1.2-4.8 1.5-9 4.2-11.5 9.1-4.2 8.3-6.2 26.5-6.5 45.5v15.5c0 3.1 2.5 5.7 5.7 5.7h68c3.1 0 5.7-2.5 5.7-5.7v-83.2z" fill="#fff" />
												</svg>
											</button>
											<!-- Copy link -->
											<button @click="copyUrlToClipboard(file.url); copyClick = true; setTimeout(() => copyClick = false, 2000); showToast = true" class="hidden sm:inline-block mt-2 px-2 py-1 bg-nbpurple-600 text-nbpurple-50 rounded-md hover:bg-nbpurple-700 focus:outline-none focus:ring-2 focus:ring-nbpurple-500" aria-label="Copy image URL to clipboard">
												<svg x-show="!copyClick" class="size-6 inline-block" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" x-transition:enter="transition-opacity duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
													<path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75" />
												</svg>
												<svg x-cloak x-show="copyClick" class="animate-[pulse_3s_ease-in-out_infinite] size-6 inline-block" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true" x-transition:enter="transition-opacity duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
													<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
												</svg>
											</button>
											<!-- Media actions -->
											<button @click="showMediaActions = !showMediaActions" class="mt-2 px-2 py-1 bg-nbpurple-600 text-nbpurple-50 rounded-md hover:bg-nbpurple-700 focus:outline-none focus:ring-2 focus:ring-nbpurple-500" aria-label="Show media actions">
												<svg class="size-6 inline-block text-nbpurple-50" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
													<path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
												</svg>
											</button>
										</div>
										<!-- Multi-select -->
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
							<li x-cloak x-show="fileStore.files.length > 0 && (fileStore.loadingMoreFiles || fileStore.fileFetchHasMore)" class="col-span-full relative">
								<div @click="await fileStore.fetchFiles(menuStore.activeFolder)" class="cursor-pointer group aspect-h-2 aspect-w-10 block w-full overflow-hidden rounded-lg bg-black/50 focus-within:ring-2 focus-within:ring-nbpurple-500 focus-within:ring-offset-2 focus-within:ring-offset-nbpurple-100">
									<div class="pointer-events-none object-cover group-hover:opacity-75 h-full flex flex-col items-center justify-center">
										<svg class="pointer-events-none object-cover group-hover:opacity-75 h-1/2 w-1/2 text-nbpurple-400 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
										</svg>
										<span class="pointer-events-none object-cover group-hover:opacity-75 text-nbpurple-400 font-semibold text-2xl text-center mt-2">Retry loading more</span>
									</div>
								</div>
								<div class="mt-2 text-center">
									<p class="text-nbpurple-200 text-2xl animate-pulse">Loading more...</p>
								</div>
							</li>
						</ul>
					</div>

					<!-- Scroll to top button -->
					<div x-cloak x-show="showScrollButton" :class="{ 'bottom-6': !multiSelect, 'bottom-16': multiSelect }" class="sticky ml-6 z-20 transition-all w-fit">
						<button @click="showScrollButton = false; window.scrollTo({ top: 0, behavior: 'smooth' }); $refs.sidebar.scrollTo({ top: 0, behavior: 'smooth' })" type="button" class="bg-nbpurple-500 text-nbpurple-50 rounded-full p-2 shadow-md hover:bg-nbpurple-600 focus:outline-none focus:ring-2 focus:ring-nbpurple-500">
							<svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
								<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 18.75 7.5-7.5 7.5 7.5" />
								<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 7.5-7.5 7.5 7.5" />
							</svg>
						</button>
					</div>
					<!-- /Scroll to top button -->

					<!-- Bottom bar -->
					<div x-cloak x-show="multiSelect" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-y-full" x-transition:enter-end="opacity-100 transform translate-y-0" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 transform translate-y-0" x-transition:leave-end="opacity-0 transform translate-y-full" class="z-20 sticky bottom-0 bg-nbpurple-900/75 py-2 px-2 align-middle flex items-center justify-center sm:justify-end backdrop-filter backdrop-blur-md">
						<div class="flex items-center">
							<button @click="fileStore.deleteConfirmation.open(selectedItems, () => { toggleMultiSelect() })" :disabled="!selectedItems.length" type="button" class="inline-flex items-center rounded-md bg-nbpurple-600 disabled:bg-nbpurple-400 px-3 py-2 text-xs font-semibold text-nbpurple-50 shadow-sm hover:bg-nbpurple-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nbpurple-600">
								<svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
									<path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
								</svg>
								<span class="hidden xs:inline">Delete </span>(<span x-text="selectedItems.length"></span>)
							</button>
							<button x-cloak x-show="profileStore.profileInfo.isShareEligible" @click="fileStore.shareMedia.open(selectedItems, () => { toggleMultiSelect() })" :disabled="!selectedItems.length" type="button" class="ml-3 inline-flex items-center rounded-md bg-nbpurple-600 disabled:bg-nbpurple-400 px-3 py-2 text-xs font-semibold text-nbpurple-50 shadow-sm hover:bg-nbpurple-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nbpurple-600">
								<svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 mr-1">
									<path stroke-linecap="round" stroke-linejoin="round" d="M7.5 7.5h-.75A2.25 2.25 0 0 0 4.5 9.75v7.5a2.25 2.25 0 0 0 2.25 2.25h7.5a2.25 2.25 0 0 0 2.25-2.25v-7.5a2.25 2.25 0 0 0-2.25-2.25h-.75m0-3-3-3m0 0-3 3m3-3v11.25m6-2.25h.75a2.25 2.25 0 0 1 2.25 2.25v7.5a2.25 2.25 0 0 1-2.25 2.25h-7.5a2.25 2.25 0 0 1-2.25-2.25v-.75" />
								</svg>
								<span class="hidden xs:inline">Share </span>(<span x-text="selectedItems.length"></span>)
							</button>
							<button @click="fileStore.moveToFolder.open(selectedItems, () => { toggleMultiSelect() })" :disabled="!selectedItems.length" type="button" class="ml-3 inline-flex items-center rounded-md bg-nbpurple-600 disabled:bg-nbpurple-400 px-3 py-2 text-xs font-semibold text-nbpurple-50 shadow-sm hover:bg-nbpurple-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nbpurple-600">
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
		<div x-cloak class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
			<div x-show="fileStore.deleteConfirmation.isOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-nbpurple-900 bg-opacity-75 transition-opacity"></div>

			<div x-show="fileStore.deleteConfirmation.isOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="fixed inset-0 z-50 overflow-y-auto">
				<div class="flex min-h-screen items-end justify-center p-4 text-center sm:items-center sm:p-0">
					<div @click.outside="!fileStore.deleteConfirmation.isLoading && fileStore.deleteConfirmation.close(true)" @keydown.escape="!fileStore.deleteConfirmation.isLoading && fileStore.deleteConfirmation.close(true)" class="relative transform overflow-hidden rounded-lg bg-nbpurple-700 text-left shadow-xl transition-all mb-24 sm:my-8 sm:w-full sm:max-w-lg">
						<div class="bg-nbpurple-700 px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
							<div class="sm:flex sm:items-start">
								<div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
									<svg class="size-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
										<path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
									</svg>
								</div>
								<div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
									<h3 class="text-base font-semibold leading-6 text-nbpurple-50" id="modal-title">Confirm Delete</h3>
									<div class="mt-2">
										<p class="text-sm text-nbpurple-100">
											Are you sure you want to delete the selected <span class="font-bold" x-text="fileStore.deleteConfirmation.selectedFiles.length"></span> file(s)? This action cannot be undone.
										</p>
										<!-- List of selected media -->
										<div class="flex items-center justify-center">
											<div class="isolate flex -space-x-2 overflow-hidden pl-3 pr-1 py-2">
												<template x-for="(file, index) in (fileStore.deleteConfirmation.selectedFiles.length > 5 ? fileStore.deleteConfirmation.selectedFiles.slice(0, 5) : fileStore.deleteConfirmation.selectedFiles)" :key="file.id">
													<div>
														<img x-show="file.mime.startsWith('image/')" :class="'relative z-' + (50 - index * 10)" class="inline-block w-12 h-12 object-cover rounded-full ring-2 ring-nbpurple-50" :src="file.thumb" :alt="file.name">
														<div x-show="file.mime.startsWith('video/')" :class="'relative z-' + (50 - index * 10)" class="size-12 object-cover rounded-full ring-2 ring-nbpurple-50 bg-nbpurple-500 text-nbpurple-50 flex items-center justify-center">
															<svg stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="size-8">
																<path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5" />
																<rect x="2" y="6" width="14" height="12" rx="2" />
															</svg>
														</div>
														<div x-show="file.mime.startsWith('audio/')" :class="'relative z-' + (50 - index * 10)" class="size-12 object-cover rounded-full ring-2 ring-nbpurple-50 bg-nbpurple-500 text-nbpurple-50 flex items-center justify-center">
															<svg stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="size-8">
																<path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3" />
															</svg>
														</div>
													</div>
												</template>
												<template x-if="fileStore.deleteConfirmation.selectedFiles.length > 5">
													<div class="relative z-0 inline-flex items-center justify-center w-12 h-12 rounded-full bg-nbpurple-600 text-nbpurple-50 ring-2 ring-nbpurple-50">
														<span class="text-xs xs:text-sm font-medium">+<span x-text="fileStore.deleteConfirmation.selectedFiles.length - 5"></span></span>
													</div>
												</template>
											</div>
										</div>
										<!-- /List of selected media -->
										<!-- Nostr shared warning -->
										<div x-show="fileStore.deleteConfirmation.selectedFiles.some(file => file.associated_notes)" class="mt-3">
											<p class="text-sm text-red-100">Note: Some of the selected media have been shared on Nostr.</p>
											<!-- TODO: List of shared notes, ability to see them, and delete them -->
										</div>
										<!-- Nostr shared warning -->
										<!-- Notice about deletion -->
										<div class="mt-3">
											<p class="text-sm text-red-100">Note: Deleting media won't immediately remove it from browser/client caches, other proxies, especially if it has been already publicly shared.</p>
										</div>
										<!-- Error message -->
										<div x-cloak x-show="fileStore.deleteConfirmation.isError" class="mt-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
											<strong class="font-bold">Error!</strong>
											<span class="block sm:inline">An error occurred while deleting the files.</span>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="bg-nbpurple-700 px-4 py-3 gap-3 flex flex-row-reverse sm:px-6">
							<button :disabled="fileStore.deleteConfirmation.isLoading" @click="fileStore.confirmDelete()" type="button" class="mt-3 inline-flex w-full justify-center rounded-md bg-red-600 px-4 py-2 text-base font-medium text-nbpurple-50 shadow-sm hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
								<svg x-show="fileStore.deleteConfirmation.isLoading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-nbpurple-50" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
									<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
									<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
								</svg>
								Delete
							</button>
							<button :disabled="fileStore.deleteConfirmation.isLoading" @click="fileStore.deleteConfirmation.close(true)" type="button" class="mt-3 inline-flex w-full justify-center rounded-md bg-nbpurple-200 px-4 py-2 text-base font-medium text-nbpurple-700 shadow-sm hover:bg-nbpurple-50 focus:outline-none focus:ring-2 focus:ring-nbpurple-500 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancel</button>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Share window -->
		<template x-if="profileStore.profileInfo.isShareEligible">
			<div x-cloak class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
				<div x-show="fileStore.shareMedia.isOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-nbpurple-900 bg-opacity-75 transition-opacity"></div>

				<div x-show="fileStore.shareMedia.isOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="fixed inset-0 z-50 overflow-y-auto">
					<div class="flex min-h-screen items-end justify-center p-4 text-center sm:items-center sm:p-0">
						<div @click.outside="!fileStore.shareMedia.isLoading && fileStore.shareMedia.close(true)" @keydown.escape="!fileStore.shareMedia.isLoading && fileStore.shareMedia.close(true)" class="relative transform overflow-hidden rounded-lg bg-nbpurple-700 text-left shadow-xl transition-all mb-24 sm:my-8 w-full max-w-lg">
							<div class="bg-nbpurple-700 px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
								<div class="sm:flex sm:items-start">
									<div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-nbpurple-100 sm:mx-0 sm:h-10 sm:w-10">
										<svg class="size-6 text-nbpurple-900" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
											<path stroke-linecap="round" stroke-linejoin="round" d="M7.5 7.5h-.75A2.25 2.25 0 0 0 4.5 9.75v7.5a2.25 2.25 0 0 0 2.25 2.25h7.5a2.25 2.25 0 0 0 2.25-2.25v-7.5a2.25 2.25 0 0 0-2.25-2.25h-.75m0-3-3-3m0 0-3 3m3-3v11.25m6-2.25h.75a2.25 2.25 0 0 1 2.25 2.25v7.5a2.25 2.25 0 0 1-2.25 2.25h-7.5a2.25 2.25 0 0 1-2.25-2.25v-.75" />
										</svg>
									</div>
									<div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
										<h3 class="text-base font-semibold leading-6 text-nbpurple-50" id="modal-title">Share Media</h3>
										<div class="mt-2">
											<!-- List of selected media -->
											<div class="flex items-center justify-center">
												<div class="isolate flex -space-x-2 overflow-hidden pl-3 pr-1 py-2">
													<template x-for="(file, index) in (fileStore.shareMedia.selectedFiles.length > 5 ? fileStore.shareMedia.selectedFiles.slice(0, 5) : fileStore.shareMedia.selectedFiles)" :key="file.id">
														<div>
															<img x-show="file.mime.startsWith('image/')" :class="'relative z-' + (50 - index * 10)" class="inline-block w-12 h-12 object-cover rounded-full ring-2 ring-nbpurple-50" :src="file.thumb" :alt="file.name">
															<div x-show="file.mime.startsWith('video/')" :class="'relative z-' + (50 - index * 10)" class="size-12 object-cover rounded-full ring-2 ring-nbpurple-50 bg-nbpurple-500 text-nbpurple-50 flex items-center justify-center">
																<svg stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="size-8">
																	<path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5" />
																	<rect x="2" y="6" width="14" height="12" rx="2" />
																</svg>
															</div>
															<div x-show="file.mime.startsWith('audio/')" :class="'relative z-' + (50 - index * 10)" class="size-12 object-cover rounded-full ring-2 ring-nbpurple-50 bg-nbpurple-500 text-nbpurple-50 flex items-center justify-center">
																<svg stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="size-8">
																	<path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3" />
																</svg>
															</div>
														</div>
													</template>
													<template x-if="fileStore.shareMedia.selectedFiles.length > 5">
														<div class="relative z-0 inline-flex items-center justify-center w-12 h-12 rounded-full bg-nbpurple-600 text-nbpurple-50 ring-2 ring-nbpurple-50">
															<span class="text-xs xs:text-sm font-medium">+<span x-text="fileStore.shareMedia.selectedFiles.length - 5"></span></span>
														</div>
													</template>
												</div>
											</div>
											<!-- /List of selected media -->
											<!-- Sharing options -->
											<div class="flex flex-col items-center sm:justify-center sm:items-start">
												<!-- Creators Page Sharing -->
												<div x-data="{
																			enabled: false,
																			supported: profileStore.profileInfo.isCreatorsPageEligible,
																			init() {
																				this.enabled = this.getFlag();
																				this.$watch('fileStore.shareMedia.selectedFiles', () => {
																					this.enabled = this.getFlag();
																				});
																			},
																			getFlag() {
																				// return fileStore.shareMedia.selectedFiles.length === 1 ? fileStore.shareMedia.selectedFiles[0].flag === 1 : false;
																				// If even one file is flagged, enable the switch
																				return fileStore.shareMedia.selectedFiles.some(file => file.flag === 1);
																			}
																		}" class="flex items-center mt-4">
													<button :disabled="!supported" :class="{
																																	'cursor-not-allowed': !supported,
																																	'cursor-pointer': supported,
																																	'bg-nbpurple-600': enabled,
																																	'bg-nbpurple-300': !enabled
																																}" type="button" class="relative inline-flex h-6 w-11 flex-shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-nbpurple-600 focus:ring-offset-2" role="switch" :aria-checked="enabled" aria-labelledby="creator-page-share-single" @click="fileStore.shareMediaCreatorConfirm(!enabled)">
														<span aria-hidden="true" :class="enabled ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-nbpurple-50 shadow ring-0 transition duration-200 ease-in-out"></span>
													</button>
													<span class="ml-4 text-sm" id="creator-page-share-single">
														<span class="font-medium text-nbpurple-100">Share on Creators Page</span>
														<a x-show="!supported" href="/plans/" class="ml-2 text-nbpurple-300 text-xs hover:underline">
															Upgrade to Creator+
														</a>
													</span>
												</div>
												<!-- /Creators Page Sharing -->
												<!-- Nostr Share -->
												<div x-show="profileStore.profileInfo.isNostrShareEligible" class="flex items-center mt-3">
													<button :disabled="fileStore.shareMedia.isLoading" @click="nostrStore.share.open(fileStore.shareMedia.selectedIds, fileStore.shareMedia.callback); fileStore.shareMedia.close(true);" class="ring-1 ring-nostrpurple-400 px-2 py-1 bg-nostrpurple-500 text-nostrpurple-50 rounded-md hover:bg-nostrpurple-400 focus:outline-none focus:ring-2 focus:ring-nostrpurple-300 shadow-sm hover:shadow-nostrpurple-100 disabled:cursor-not-allowed disabled:bg-nostrpurple-200" aria-label="Share images(s) on Nostr">
														<svg aria-hidden="true" class="size-6 inline-block" stroke="currentColor" fill="currentColor" viewBox="0 0 256 256" xmlns="http://www.w3.org/2000/svg">
															<circle cx="137.9" cy="99" fill="#fff" r="12.1" />
															<path d="M210.8 115.9c0-47.3-27.7-68.7-64.4-68.7-16.4 0-31 4.4-42.4 12.5-3.8 2.7-9 .1-9-4.5 0-3.1-2.5-5.7-5.7-5.7H57.7c-3.1 0-5.7 2.5-5.7 5.7v144c0 3.1 2.5 5.7 5.7 5.7h33.7c3.1 0 5.6-2.5 5.6-5.6v-8.4c0-62.8-33.2-109.8-.4-116 30-5.7 64.1-3 64.5 20.1 0 2 .3 8 8.6 11.2 5 2 12.6 2.6 22.6 2.4 0 0 9.1-.7 9.1 8.5 0 11.5-20.4 10.7-20.4 10.7-6.7.3-22.6-1.5-31.7 1.2-4.8 1.5-9 4.2-11.5 9.1-4.2 8.3-6.2 26.5-6.5 45.5v15.5c0 3.1 2.5 5.7 5.7 5.7h68c3.1 0 5.7-2.5 5.7-5.7v-83.2z" fill="#fff" />
														</svg>
														Share on Nostr
													</button>
												</div>
												<!-- /Nostr Share -->
											</div>
											<!-- Error message -->
											<div x-cloak x-show="fileStore.shareMedia.isError" class="mt-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
												<strong class="font-bold">Error!</strong>
												<span class="block sm:inline">An error occurred while sharing the files.</span>
											</div>
										</div>
									</div>
								</div>
							</div>
							<div class="bg-nbpurple-700 px-4 py-3 gap-3 justify-center sm:justify-normal flex flex-row-reverse sm:px-6">
								<button @click="fileStore.shareMedia.close()" :disabled="fileStore.shareMedia.isLoading" type="button" class="mt-3 inline-flex w-1/2 justify-center rounded-md bg-nbpurple-500 px-4 py-2 text-base font-medium text-nbpurple-50 shadow-sm hover:bg-nbpurple-200 focus:outline-none focus:ring-2 focus:ring-nbpurple-500 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
									<svg x-show="fileStore.shareMedia.isLoading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-nbpurple-50" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
										<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
										<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
									</svg>
									Done
								</button>
								<button @click="fileStore.shareMedia.close(true)" type="button" class="mt-3 inline-flex justify-center rounded-md bg-nbpurple-200 px-4 py-2 text-base font-medium text-nbpurple-700 shadow-sm hover:bg-nbpurple-50 focus:outline-none focus:ring-2 focus:ring-nbpurple-500 focus:ring-offset-2 sm:order-0 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
									Cancel
								</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</template>

		<!-- Move to folder modal -->
		<div x-cloak class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
			<!-- Background overlay -->
			<div x-show="fileStore.moveToFolder.isOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-nbpurple-900 bg-opacity-75 transition-opacity"></div>
			<!-- Modal content -->
			<div x-show="fileStore.moveToFolder.isOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-0 sm:-translate-y-4" x-transition:enter-end="opacity-100 translate-y-0 sm:translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-0 sm:-translate-y-4 sm:scale-95" class="fixed inset-0 z-50 overflow-y-auto">
				<div class="flex min-h-screen items-end justify-center p-4 text-center sm:items-center sm:p-0">
					<div @click.outside="!fileStore.moveToFolder.isLoading && fileStore.moveToFolder.close(true)" @keydown.escape="!fileStore.moveToFolder.isLoading && fileStore.moveToFolder.close(true)" class="relative transform overflow-hidden rounded-lg bg-nbpurple-700 text-left shadow-xl transition-all min-h-[50vh] max-h-[80vh] my-8 mb-24 w-full sm:max-w-lg flex flex-col">
						<div class="bg-nbpurple-700 px-4 pt-5 pb-4 sm:p-6 sm:pb-4 overflow-y-auto flex-grow">
							<!-- List of selected media -->
							<div class="flex items-center justify-center">
								<div class="isolate flex -space-x-2 overflow-hidden pl-3 pr-1 py-2">
									<template x-for="(file, index) in (fileStore.moveToFolder.selectedFiles.length > 5 ? fileStore.moveToFolder.selectedFiles.slice(0, 5) : fileStore.moveToFolder.selectedFiles)" :key="file.id">
										<div>
											<img x-show="file.mime.startsWith('image/')" :class="'relative z-' + (50 - index * 10)" class="inline-block w-12 h-12 object-cover rounded-full ring-2 ring-nbpurple-50" :src="file.thumb" :alt="file.name">
											<div x-show="file.mime.startsWith('video/')" :class="'relative z-' + (50 - index * 10)" class="size-12 object-cover rounded-full ring-2 ring-nbpurple-50 bg-nbpurple-500 text-nbpurple-50 flex items-center justify-center">
												<svg stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="size-8">
													<path d="m16 13 5.223 3.482a.5.5 0 0 0 .777-.416V7.87a.5.5 0 0 0-.752-.432L16 10.5" />
													<rect x="2" y="6" width="14" height="12" rx="2" />
												</svg>
											</div>
											<div x-show="file.mime.startsWith('audio/')" :class="'relative z-' + (50 - index * 10)" class="size-12 object-cover rounded-full ring-2 ring-nbpurple-50 bg-nbpurple-500 text-nbpurple-50 flex items-center justify-center">
												<svg stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="size-8">
													<path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3" />
												</svg>
											</div>
										</div>
									</template>
									<template x-if="fileStore.moveToFolder.selectedFiles.length > 5">
										<div class="relative z-0 inline-flex items-center justify-center w-12 h-12 rounded-full bg-nbpurple-600 text-nbpurple-50 ring-2 ring-nbpurple-50">
											<span class="text-xs xs:text-sm font-medium">+<span x-text="fileStore.moveToFolder.selectedFiles.length - 5"></span></span>
										</div>
									</template>
								</div>
							</div>
							<!-- /List of selected media -->
							<div class="sm:flex sm:items-start">
								<div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
									<div class="mt-2 flex items-center">
										<label for="combobox" class="block text-sm font-medium leading-6 text-nbpurple-50 mr-2">Select Folder</label>
										<div class="relative w-full z-auto">
											<input id="combobox" type="text" x-model="fileStore.moveToFolder.selectedFolderName" @input="fileStore.moveToFolder.searchTerm = $event.target.value; fileStore.moveToFolder.isDropdownOpen = true" @click="fileStore.moveToFolder.toggleDropdown()" @click.away="fileStore.moveToFolder.isDropdownOpen = false" class="w-full rounded-md border-0 bg-nbpurple-50 py-1.5 pl-3 pr-12 text-nbpurple-900 shadow-sm ring-1 ring-inset ring-nbpurple-300 focus:ring-2 focus:ring-inset focus:ring-nbpurple-600 sm:text-sm sm:leading-6" role="combobox" aria-controls="options" :aria-expanded="fileStore.moveToFolder.isDropdownOpen.toString()">
											<button type="button" @click.stop="fileStore.moveToFolder.toggleDropdown()" class="absolute inset-y-0 right-0 flex items-center rounded-r-md px-2 focus:outline-none">
												<svg class="h-5 w-5 text-nbpurple-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
													<path fill-rule="evenodd" d="M10 3a.75.75 0 01.55.24l3.25 3.5a.75.75 0 11-1.1 1.02L10 4.852 7.3 7.76a.75.75 0 01-1.1-1.02l3.25-3.5A.75.75 0 0110 3zm-3.76 9.2a.75.75 0 011.06.04l2.7 2.908 2.7-2.908a.75.75 0 111.1 1.02l-3.25 3.5a.75.75 0 01-1.1 0l-3.25-3.5a.75.75 0 01.04-1.06z" clip-rule="evenodd" />
												</svg>
											</button>
											<ul x-show="fileStore.moveToFolder.isDropdownOpen" @click.away="fileStore.moveToFolder.isDropdownOpen = false" class="absolute z-10 mt-1 max-h-40 w-full overflow-auto rounded-md bg-nbpurple-50 py-1 text-base shadow-lg ring-1 ring-nbpurple-950 ring-opacity-5 focus:outline-none sm:text-sm" id="options" role="listbox">
												<template x-for="folder in menuStore.folders.filter(f => f.name.toLowerCase().includes(fileStore.moveToFolder.searchTerm.toLowerCase()))">
													<li class="relative cursor-default select-none py-2 pl-3 pr-9 text-nbpurple-900" :id="'option-' + folder.id" role="option" @click="fileStore.moveToFolder.destinationFolderId = folder.id; fileStore.moveToFolder.selectedFolderName = folder.name; fileStore.moveToFolder.isDropdownOpen = false; fileStore.moveToFolder.searchTerm = ''" @mouseenter="fileStore.moveToFolder.hoveredFolder = folder.id" @mouseleave="fileStore.moveToFolder.hoveredFolder = null" :class="{ 'bg-nbpurple-600 text-nbpurple-50': folder.id === fileStore.moveToFolder.hoveredFolder, 'text-nbpurple-900': folder.id !== fileStore.moveToFolder.hoveredFolder }">
														<div class="flex">
															<span x-text="folder.name" class="truncate" :class="{ 'font-semibold': folder.id === fileStore.moveToFolder.destinationFolderId }"></span>
														</div>
														<span x-show="folder.id === fileStore.moveToFolder.destinationFolderId" class="absolute inset-y-0 right-0 flex items-center pr-4" :class="{ 'text-nbpurple-50': folder.id === fileStore.moveToFolder.hoveredFolder, 'text-nbpurple-600': folder.id !== fileStore.moveToFolder.hoveredFolder }">
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
						<div class="bg-nbpurple-700 px-4 py-3 sm:px-6 sm:flex sm:flex-wrap sm:justify-end">
							<button x-data="{
                    folderSelected: false,
                    init() {
                      this.$watch('fileStore.moveToFolder.destinationFolderId', () => {
                        this.folderSelected = fileStore.moveToFolder.destinationFolderId !== null;
                      });
                    },
                    getSelectedCount() { return fileStore.moveToFolder.selectedFiles.length; },
                    isValidFolderSelected() { return fileStore.moveToFolder.destinationFolderId !== null; }
                  }" x-show="!fileStore.moveToFolder.isLoading" @click="fileStore.moveToFolderConfirm()" type="button" :disabled="!folderSelected || fileStore.moveToFolder.isLoading" :class="{ 'opacity-50 cursor-not-allowed': !folderSelected }" class="inline-flex justify-center rounded-md bg-nbpurple-500 px-4 py-2 text-base font-medium text-nbpurple-50 shadow-sm hover:bg-nbpurple-600 focus:outline-none focus:ring-2 focus:ring-nbpurple-500 focus:ring-offset-2 sm:order-1 sm:ml-3 sm:w-auto sm:text-sm">
								Move&nbsp;<span x-text="getSelectedCount()"></span>&nbsp;file(s) to folder
							</button>
							<button x-show="fileStore.moveToFolder.isLoading" type="button" class="inline-flex justify-center rounded-md bg-nbpurple-600 px-4 py-2 text-base font-medium text-nbpurple-50 shadow-sm hover:bg-nbpurple-600 focus:outline-none focus:ring-2 focus:ring-nbpurple-500 focus:ring-offset-2 sm:order-1 sm:ml-3 sm:w-auto sm:text-sm" disabled>
								<svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-nbpurple-50" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
									<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
									<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
								</svg>
								Moving...
							</button>
							<button @click="fileStore.moveToFolder.close(true)" type="button" class="mt-3 inline-flex justify-center rounded-md bg-nbpurple-200 px-4 py-2 text-base font-medium text-nbpurple-700 shadow-sm hover:bg-nbpurple-50 focus:outline-none focus:ring-2 focus:ring-nbpurple-500 focus:ring-offset-2 sm:order-0 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
								Cancel
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Full-screen image Modal -->
		<div x-cloak x-show="fileStore.modalOpen" x-trap="fileStore.modalOpen" x-transition:enter="transition-opacity ease-linear duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-linear duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed z-50 inset-0 overflow-y-auto bg-black" aria-labelledby="modal-title" role="dialog" aria-modal="true">
			<div x-data="{ 
      startX: null,
      startY: null,
      showControls: false,
      controlsTimeout: null,
			timeOfTouch: null,
      handleTouchStart: function(event) {
				timeOfTouch = new Date().getTime();
        this.startX = event.touches[0].clientX;
        this.startY = event.touches[0].clientY;
      },
      handleTouchMove: async function(event) {
        if (!this.startX || !this.startY) return;

							const timeTaken = new Date().getTime() - timeOfTouch;
							const currentX = event.touches[0].clientX;
							const currentY = event.touches[0].clientY;
							const diffX = this.startX - currentX;
							const diffY = this.startY - currentY;

							if (Math.abs(diffX) > 125 || Math.abs(diffY) > 125) {
								const diff = Math.abs(diffX) > Math.abs(diffY) ? diffX : diffY;
								const fast = timeTaken < 200;
								if (fast) {
									if (diff > 0) {
										await fileStore.modalNext();
									} else {
										await fileStore.modalPrevious();
									}
								} else {
									this.showControls = !this.showControls;
									clearTimeout(this.controlsTimeout);
									this.controlsTimeout = setTimeout(() => {
										this.showControls = false;
									}, 1500);
								}

								this.startX = null;
								this.startY = null;
							}
						},
						handleTouchEnd: function(event) {
							this.startX = null;
							this.startY = null;
						},
						handleMouseEnter: function() {
							this.showControls = true;
						},
						handleMouseLeave: function() {
							this.showControls = false;
						},
						scrollThreshold: 10,
						scrollTimeout: null,
						handleScroll: async function(event) {
							if (Math.abs(event.deltaY) >= this.scrollThreshold) {
								if (this.scrollTimeout) {
									clearTimeout(this.scrollTimeout);
								}
								
								this.scrollTimeout = setTimeout(async () => {
									if (event.deltaY < 0) {
										// Up
										await fileStore.modalPrevious();
									} else if (event.deltaY > 0) {
										// Down
										await fileStore.modalNext();
									}
								}, 150);
							}
						}
					}" @wheel.passive="handleScroll($event)" @keydown.escape="fileStore.closeModal()" @keydown.down="await fileStore.modalNext()" @keydown.right="await fileStore.modalNext()" @keydown.up="await fileStore.modalPrevious()" @keydown.left="await fileStore.modalPrevious()" @touchstart.passive="handleTouchStart(event)" @touchmove.passive="await handleTouchMove(event)" @touchend.passive="handleTouchEnd(event)" class="flex flex-col h-svh">
				<div class="flex-grow flex items-center justify-center">
					<button type="button" class="z-10 absolute top-3 right-3 inline-flex items-center justify-center p-1 bg-black bg-opacity-50 rounded-full text-nbpurple-50 hover:text-nbpurple-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-nbpurple-500" @click="fileStore.closeModal()">
						<svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
							<path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
						</svg>
					</button>
					<!-- Left arrow -->
					<button type="button" class="z-10 absolute left-3 top-1/2 transform -translate-y-1/2 md:inline-flex items-center justify-center p-1 bg-black bg-opacity-50 rounded-full text-nbpurple-50 hover:text-nbpurple-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-nbpurple-500 hidden xl:block" @click="await fileStore.modalPrevious()">
						<svg class="h-8 w-8" viewBox="0 0 20 20" fill="currentColor">
							<path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
						</svg>
					</button>
					<!-- Right arrow -->
					<button type="button" class="z-10 absolute right-3 top-1/2 transform -translate-y-1/2 md:inline-flex items-center justify-center p-1 bg-black bg-opacity-50 rounded-full text-nbpurple-50 hover:text-nbpurple-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-nbpurple-500 hidden xl:block" @click="await fileStore.modalNext()">
						<svg class="h-8 w-8" viewBox="0 0 20 20" fill="currentColor">
							<path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
						</svg>
					</button>
					<!-- Image -->
					<!-- Progressive load of media is handled by the next/prev functions -->
					<template x-if="fileStore.modalFile.mime?.startsWith('image/')">
						<img :src="fileStore.modalImageUrl" :srcset="fileStore.modalImageSrcset" :sizes="fileStore.modalImageSizes" :alt="fileStore.modalImageAlt" class="w-dvh h-full max-w-full max-h-[80svh] landscape:max-h-svh xl:landscape:max-h-[80svh] object-contain mx-auto">
					</template>
					<template x-if="fileStore.modalFile.mime?.startsWith('video/')">
						<div class="relative w-full h-full flex items-center justify-center" @mouseenter="handleMouseEnter" @mouseleave="handleMouseLeave">
							<video crossorigin="anonymous" x-effect="fileStore.modalFile.mime?.startsWith('video/') ? ($el.load() && $el.play()) : true" x-init="fileStore.modalOpen ? $el.load() : $el.pause()" loop :controls="showControls" playsinline preload="auto" @loadedmetadata="fileStore.modalOpen && $nextTick(() => {$el.play()})" class="w-dvh h-full max-w-full max-h-[80svh] landscape:max-h-svh xl:landscape:max-h-[80svh] object-contain mx-auto">
								<source :src="fileStore.modalFile.url" type="video/mp4">
								Your browser does not support the video tag.
							</video>
						</div>
					</template>
					<!-- Audio -->
					<template x-if="fileStore.modalFile.mime?.startsWith('audio/')">
						<!-- Use video player to play audio files -->
						<!--
						<img src="https://cdn.nostr.build/assets/audio/jpg/audio-wave@0.5x.jpg" :alt="fileStore.modalFile.name" class="w-full h-full object-cover">
						-->
						<video crossorigin="anonymous" x-effect="fileStore.modalFile.mime?.startsWith('audio/') ? ($el.load() && $el.play()) : true" x-init="fileStore.modalOpen ? $el.load() : $el.pause()" poster="https://cdn.nostr.build/assets/audio/jpg/audio-wave@0.5x.jpg" controls playsinline preload="auto" @loadedmetadata="fileStore.modalOpen && $nextTick(() => {$el.play()})" class="w-dvh h-full max-w-full max-h-[80svh] landscape:max-h-svh xl:landscape:max-h-[80svh] object-contain mx-auto">
							<source :src="fileStore.modalFile.url" :type="fileStore.modalFile.mime">
						</video>
					</template>
				</div>
				<div class="bg-black/50 px-4 py-3 sm:px-6 overflow-y-auto landscape:hidden xl:landscape:block">
					<div class="flex justify-between items-center">
						<div class="flex flex-col items-start">
							<p class="text-sm text-gray-300">Name: <span x-text="fileStore.modalImageAlt"></span></p>
							<p class="text-sm text-gray-300">Size: <span x-text="formatBytes(fileStore.modalImageFilesize)"></span></p>
						</div>
						<div class="flex flex-col items-start">
							<p class="text-sm text-gray-300">Dimensions: <span x-text="fileStore.modalImageDimensions"></span></p>
							<p class="text-sm text-gray-300">URL: <a :href="fileStore.modalImageUrl" target="_blank" x-text="fileStore.modalImageUrl"></a>
								<svg class="size-3 ml-1 inline-block" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
									<path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
								</svg>
							</p>
						</div>
					</div>
					<!-- Prompt and title with copy button -->
					<div x-show="fileStore.modalImageTitle.length > 0 || fileStore.modalImagePrompt.length > 0" class="mt-4">
						<div class="flex flex-col items-start">
							<p x-show="fileStore.modalImageTitle.length > 0" class="text-sm text-gray-300 font-medium">Title: <span class="font-normal" x-text="fileStore.modalImageTitle"></span></p>
							<p x-show="fileStore.modalImagePrompt.length > 0" class="text-sm text-gray-300 font-medium">Prompt: <span class="font-normal" x-text="fileStore.modalImagePrompt"></span></p>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Folder deletion confirmation modal -->
		<div x-cloak class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
			<div x-show="menuStore.showDeleteFolderModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-nbpurple-900 bg-opacity-75 transition-opacity"></div>

			<div x-show="menuStore.showDeleteFolderModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="fixed inset-0 z-50 overflow-y-auto">
				<div class="flex min-h-screen items-end justify-center p-4 text-center sm:items-center sm:p-0">
					<div @click.outside="!menuStore.isDeletingFolders && menuStore.closeDeleteFolderModal()" @keydown.escape="!menuStore.isDeletingFolders && menuStore.closeDeleteFolderModal()" class="relative transform overflow-hidden rounded-lg bg-nbpurple-700 text-left shadow-xl transition-all mb-24 sm:my-8 sm:w-full sm:max-w-lg">
						<div class="bg-nbpurple-700 px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
							<div class="sm:flex sm:items-start">
								<div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-200 sm:mx-0 sm:h-10 sm:w-10">
									<svg class="size-6 text-red-700" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
										<path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
									</svg>
								</div>
								<div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
									<h3 class="text-base font-semibold leading-6 text-nbpurple-50" id="modal-title">Confirm Delete</h3>
									<div class="mt-2">
										<p class="text-sm text-nbpurple-100">
											Are you sure you want to delete the selected <span class="font-bold" x-text="menuStore.foldersToDeleteIds.length"></span> folder(s)? This action cannot be undone. <span class="font-bold">Deleting a folder will NOT delete the media inside it, and will move them to the main folder instead.</span>
										</p>
										<!-- List of selected folders -->
										<div class="mt-4">
											<ul role="list" class="list-disc space-y-2 pl-5 text-sm text-nbpurple-50">
												<template x-for="folder in menuStore.foldersToDelete">
													<li x-text="folder.name" class="truncate"></li>
												</template>
											</ul>
										</div>
										<!-- /List of selected folders -->
									</div>
								</div>
							</div>
						</div>
						<div class="bg-nbpurple-700 px-4 py-3 gap-3 flex flex-row-reverse sm:px-6">
							<button :disabled="menuStore.isDeletingFolders" @click="menuStore.deleteFoldersConfirm()" type="button" class="mt-3 inline-flex w-full justify-center rounded-md bg-red-600 px-4 py-2 text-base font-medium text-nbpurple-50 shadow-sm hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
								<svg x-show="menuStore.isDeletingFolders" class="animate-spin -ml-1 mr-3 h-5 w-5 text-nbpurple-50" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
									<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
									<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
								</svg>
								Delete
							</button>
							<button :disabled="menuStore.isDeletingFolders" @click="menuStore.closeDeleteFolderModal()" type="button" class="mt-3 inline-flex w-full justify-center rounded-md bg-nbpurple-50 px-4 py-2 text-base font-medium text-nbpurple-700 shadow-sm hover:bg-nbpurple-50 focus:outline-none focus:ring-2 focus:ring-nbpurple-500 focus:ring-offset-2 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancel</button>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Nostr Share modal -->
		<template x-if="profileStore.profileInfo.isNostrShareEligible">
			<div x-cloak class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
				<div x-show="nostrStore.share.isOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-nbpurple-900 bg-opacity-75 transition-opacity"></div>

				<div x-show="nostrStore.share.isOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-4" class="fixed inset-0 z-50 overflow-y-auto">
					<div class="flex min-h-screen items-end justify-center p-4 text-center">
						<div @click.outside="!nostrStore.share.isLoading && nostrStore.share.close(true)" @keydown.escape="!nostrStore.share.isLoading && nostrStore.share.close(true)" class="relative transform overflow-hidden rounded-lg bg-nostrpurple-700 text-left shadow-xl transition-all mb-24 w-full max-w-xl">
							<div class="bg-nostrpurple-700 px-4 pb-4 pt-5">
								<div class="">
									<div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-nostrpurple-200">
										<svg class="size-8" fill="none" viewBox="0 0 256 256" stroke-width="1.5" stroke="currentColor">
											<circle cx="137.9" cy="99" fill="#fff" r="12.1" />
											<path d="M210.8 115.9c0-47.3-27.7-68.7-64.4-68.7-16.4 0-31 4.4-42.4 12.5-3.8 2.7-9 .1-9-4.5 0-3.1-2.5-5.7-5.7-5.7H57.7c-3.1 0-5.7 2.5-5.7 5.7v144c0 3.1 2.5 5.7 5.7 5.7h33.7c3.1 0 5.6-2.5 5.6-5.6v-8.4c0-62.8-33.2-109.8-.4-116 30-5.7 64.1-3 64.5 20.1 0 2 .3 8 8.6 11.2 5 2 12.6 2.6 22.6 2.4 0 0 9.1-.7 9.1 8.5 0 11.5-20.4 10.7-20.4 10.7-6.7.3-22.6-1.5-31.7 1.2-4.8 1.5-9 4.2-11.5 9.1-4.2 8.3-6.2 26.5-6.5 45.5v15.5c0 3.1 2.5 5.7 5.7 5.7h68c3.1 0 5.7-2.5 5.7-5.7v-83.2z" fill="#fff" />
											<path d="M227.6 54.6c-4.4-12.2-14-21.8-26.2-26.2-11.1-3.5-21.5-3.5-42.2-3.5H96.8c-20.7 0-31.1 0-42.2 3.5-12.2 4.4-21.8 14-26.2 26.2-3.5 11.2-3.5 21.5-3.5 42.2v62.5c0 20.7 0 31.1 3.5 42.2 4.4 12.2 14 21.8 26.2 26.2 11.2 3.5 21.5 3.5 42.2 3.5h62.5c20.7 0 31.1 0 42.2-3.5 12.2-4.4 21.8-14 26.2-26.2 3.5-11.1 3.5-21.5 3.5-42.2V96.8c0-20.7 0-31.1-3.6-42.2zm-22.5 150.2h-68c-3.1 0-5.7-2.5-5.7-5.7v-15.5c.3-19 2.3-37.2 6.5-45.5 2.5-5 6.7-7.7 11.5-9.1 9-2.7 24.9-.9 31.7-1.2 0 0 20.4.8 20.4-10.7 0-9.3-9.1-8.5-9.1-8.5-10 .3-17.7-.4-22.6-2.4-8.3-3.3-8.6-9.2-8.6-11.2-.4-23.1-34.5-25.9-64.5-20.1-32.8 6.2.4 53.3.4 116v8.4c-.1 3.1-2.5 5.6-5.6 5.6H57.7c-3.1 0-5.7-2.5-5.7-5.7v-144c0-3.1 2.5-5.7 5.7-5.7h31.7c3.1 0 5.7 2.5 5.7 5.7 0 4.7 5.2 7.2 9 4.5 11.4-8.2 26-12.5 42.4-12.5 36.7 0 64.4 21.4 64.4 68.7v83.2c-.1 3.2-2.6 5.7-5.8 5.7zM125.7 99c0-6.7 5.4-12.1 12.1-12.1S150 92.3 150 99s-5.4 12.1-12.1 12.1-12.2-5.3-12.2-12.1z" fill="#662482" />
										</svg>
									</div>
									<div class="w-full mt-3 text-center">
										<h3 class="text-base font-semibold leading-6 text-nbpurple-50" id="modal-title">Share on Nostr</h3>
										<div class="mt-2">
											<!-- Profile -->
											<div class="group block flex-shrink-0">
												<div class="flex items-center">
													<div class="flex-shrink-0">
														<img class="size-9 rounded-full object-cover" :src="profileStore.profileInfo.pfpUrl" :alt="profileStore.profileInfo.name">
													</div>
													<div class="ml-3 text-left overflow-hidden">
														<p class="text-sm font-medium text-nostrpurple-100 group-hover:text-nostrpurple-200 truncate">
															<span class="inline-block max-w-full truncate" x-text="profileStore.profileInfo.name">Anon</span>
														</p>
														<p class="text-xs font-medium text-nostrpurple-50 group-hover:text-nostrpurple-100 truncate">
															<span class="inline-block max-w-full truncate" x-text="nostrStore.share.isOpen && abbreviateBech32(await nostrStore.nostrGetBech32Npub())">npub1...</span>
														</p>
													</div>
												</div>
											</div>
											<!-- /Profile -->
											<div class="w-full">
												<label for="nostr-comment" class="sr-only">Add your note</label>
												<div class="mt-2">
													<textarea x-model="nostrStore.share.note" rows="4" name="nostr-comment" id="nostr-comment" class="block w-full rounded-md border-0 py-1.5 bg-nostrpurple-600 text-nostrpurple-50 shadow-sm ring-1 ring-inset ring-nostrpurple-300 placeholder:text-nostrpurple-400 focus:ring-2 focus:ring-inset focus:ring-nostrpurple-600"></textarea>
												</div>
											</div>
											<!-- List of selected media -->
											<div :class="nostrStore.share.selectedFiles.length > 3 ? 'justify-start' : 'justify-center'" class="pt-5 pb-4 flex items-center overflow-x-auto scrollbar-hide">
												<div class="flex space-x-2">
													<template x-for="(file, index) in nostrStore.share.selectedFiles" :key="file.id">
														<div class="relative inline-block transition-all flex-shrink-0">
															<div class="aspect-w-1 aspect-h-1 w-16">
																<img x-show="file.mime.startsWith('image/')" class="object-cover rounded-md" :src="file.thumb" :alt="file.name">
																<video crossorigin="anonymous" x-show="file.mime.startsWith('video/')" loop muted autoplay playsinline preload="auto" class="object-cover rounded-md">
																	<source :src="file.url" type="video/mp4">
																</video>
																<img x-show="file.mime.startsWith('audio/')" src="https://cdn.nostr.build/assets/audio/jpg/audio-wave@0.25x.jpg" :alt="'Poster for ' + file.name" loading="eager" class="object-cover rounded-md" />
															</div>
															<button x-show="nostrStore.share.selectedFiles.length > 1" @click="nostrStore.share.remove(file.id)" class="absolute right-0 top-0 block h-4 w-4 -translate-y-1/2 translate-x-1/2 transform rounded-full bg-nostrpurple-400 ring-2 ring-nostrpurple-50 hover:bg-nostrpurple-200">
																<svg class="h-4 w-4 text-nostrpurple-50" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
																	<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
																</svg>
															</button>
														</div>
													</template>
												</div>
											</div>
											<!-- /List of selected media -->
											<!-- Error message -->
											<div x-cloak x-show="nostrStore.share.isError" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative overflow-y-auto scrollbar-hide max-h-36" role="alert">
												<template x-for="error in nostrStore.share.getDeduplicatedErrors()">
													<p x-html="error" class="text-xs xs:text-sm"></p>
												</template>
											</div>
											<!-- /Error message -->
										</div>
									</div>
								</div>
							</div>
							<div class="bg-nostrpurple-700 -mt-2 px-4 pb-3 pt-0 gap-3 flex flex-row-reverse">
								<button :disabled="nostrStore.share.isLoading || nostrStore.share.isCriticalError" @click="await nostrStore.share.send()" type="button" class="mt-3 inline-flex w-full justify-center rounded-md bg-nostrpurple-600 px-4 py-2 text-base font-medium text-nostrpurple-50 shadow-sm hover:bg-nostrpurple-500 focus:outline-none focus:ring-2 focus:ring-nostrpurple-500 focus:ring-offset-2 disabled:bg-nostrpurple-300 disabled:cursor-not-allowed">
									<svg x-show="nostrStore.share.isLoading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-nostrpurple-50" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
										<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
										<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
									</svg>
									Share
								</button>
								<button :disabled="nostrStore.share.isLoading" @click="nostrStore.share.close(true)" type="button" class="mt-3 inline-flex w-full justify-center rounded-md bg-nostrpurple-100 px-4 py-2 text-base font-medium text-nostrpurple-700 shadow-sm hover:bg-nostrpurple-200 focus:outline-none focus:ring-2 focus:ring-nostrpurple-500 focus:ring-offset-2">Cancel</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</template>

		<!-- Logout confirmation modal -->
		<div x-show="logoutScreen" x-cloak class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
			<div x-show="logoutScreen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-nbpurple-900 bg-opacity-75 transition-opacity"></div>

			<div x-show="logoutScreen" class="fixed inset-0 z-10 w-screen overflow-y-auto">
				<div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
					<div @click.outside="logoutScreen = false" x-show="logoutScreen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="relative transform overflow-hidden rounded-lg bg-nbpurple-700 px-4 pb-4 pt-5 text-left shadow-xl transition-all mb-20 sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
						<div class="sm:flex sm:items-start">
							<div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
								<svg class="h-6 w-6 text-red-700" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
									<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
								</svg>
							</div>
							<div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
								<h3 class="text-base font-semibold leading-6 text-nbpurple-50" id="modal-title">Logout</h3>
								<div class="mt-2">
									<p class="text-sm text-nbpurple-100">Are you sure you want to logout?</p>
								</div>
							</div>
						</div>
						<div class="mt-5 sm:ml-10 sm:mt-4 sm:flex sm:pl-4">
							<a href="/functions/logout.php" class="inline-flex w-full justify-center rounded-md bg-red-700 px-3 py-2 text-sm font-semibold text-nbpurple-50 shadow-sm hover:bg-red-500 sm:w-auto">Logout</a>
							<button @click="logoutScreen = false" type="button" class="mt-3 inline-flex w-full justify-center rounded-md bg-nbpurple-300 px-3 py-2 text-sm font-semibold text-nbpurple-900 shadow-sm ring-1 ring-inset ring-nbpurple-300 hover:bg-nbpurple-400 sm:ml-3 sm:mt-0 sm:w-auto">Cancel</button>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Profile edit modal -->
		<div class="relative z-50" aria-labelledby="slide-over-title" role="dialog" aria-modal="true">
			<!-- Background backdrop, show/hide based on slide-over state. -->
			<div x-cloak class="fixed inset-0 bg-nbpurple-900 bg-opacity-75 transition-opacity" x-show="profileStore.dialogOpen" x-transition:enter="ease-in-out duration-500" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in-out duration-500" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>

			<div x-cloak class="fixed inset-0 overflow-hidden" x-show="profileStore.dialogOpen" x-transition:enter="ease-in-out duration-500" x-transition:enter-start="opacity-0 translate-x-full" x-transition:enter-end="opacity-100 translate-x-0" x-transition:leave="ease-in-out duration-500" x-transition:leave-start="opacity-100 translate-x-0" x-transition:leave-end="opacity-0 translate-x-full">
				<div class="absolute inset-0 overflow-hidden">
					<div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10 sm:pl-16">
						<div @click.outside="profileStore.closeDialog()" class="pointer-events-auto w-screen max-w-3xl">
							<form class="flex h-full flex-col overflow-y-scroll bg-nbpurple-900 shadow-xl" @submit.prevent="await profileStore.updateProfileInfo()">
								<div class="flex-1">
									<!-- Header -->
									<div class="bg-nbpurple-500 px-4 py-6 sm:px-6">
										<div class="flex items-start justify-between space-x-3">
											<div class="space-y-1">
												<h2 class="text-base font-semibold leading-6 text-nbpurple-950" id="slide-over-title">Update Profile</h2>
												<p class="text-sm text-nbpurple-100">Update your profile information below.</p>
											</div>
											<div class="flex h-7 items-center">
												<button type="button" class="relative text-nbpurple-700 hover:text-nbpurple-900" @click="profileStore.closeDialog()">
													<span class="absolute -inset-2.5"></span>
													<span class="sr-only">Close panel</span>
													<svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
														<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
													</svg>
												</button>
											</div>
										</div>
									</div>

									<!-- Success/Error Messages -->
									<div x-show="profileStore.dialogSuccessMessages.length" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-y-4" x-transition:enter-end="opacity-100 transform translate-y-0" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 transform translate-y-0" x-transition:leave-end="opacity-0 transform translate-y-4" class="mx-4 mb-4 bg-nostrpurple-500 text-nostrpurple-50 px-4 py-2 rounded-lg shadow-lg absolute top-5 right-2 z-10">
										<template x-for="message in profileStore.dialogSuccessMessages">
											<p x-text="message"></p>
										</template>
									</div>
									<div x-show="profileStore.dialogError" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-y-4" x-transition:enter-end="opacity-100 transform translate-y-0" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 transform translate-y-0" x-transition:leave-end="opacity-0 transform translate-y-4" class="mx-4 mb-4 bg-red-500 text-nbpurple-50 px-4 py-2 rounded-lg shadow-lg absolute top-5 right-2 z-10">
										<template x-for="message in profileStore.dialogErrorMessages">
											<p x-text="message"></p>
										</template>
									</div>

									<!-- Divider container -->
									<div class="space-y-6 py-6 sm:space-y-0 sm:divide-y sm:divide-nbpurple-200 sm:py-0">
										<!-- Registered NPUB -->
										<div class="space-y-2 px-4 grid sm:grid-cols-3 sm:gap-4 sm:space-y-0 sm:px-6 sm:py-3 min-w-0">
											<div>
												<p class="inline-block text-sm font-medium leading-6 text-nbpurple-200 sm:mt-1.5">Your NPUB</p>
												<span :class="profileStore.profileInfo.npubVerified ? 'bg-nostrpurple-300 text-nostrpurple-900 ring-nostrpurple-200/10' : 'bg-red-100 text-red-600 ring-red-700/10'" class="inline-flex items-center gap-x-1.5 rounded-full px-1.5 py-0.5 text-xs font-medium border ring-1 ring-inset">
													<svg x-show="profileStore.profileInfo.npubVerified" class="size-3 fill-nostrpurple-500" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
														<path fill-rule="evenodd" d="M15 8c0 .982-.472 1.854-1.202 2.402a2.995 2.995 0 0 1-.848 2.547 2.995 2.995 0 0 1-2.548.849A2.996 2.996 0 0 1 8 15a2.996 2.996 0 0 1-2.402-1.202 2.995 2.995 0 0 1-2.547-.848 2.995 2.995 0 0 1-.849-2.548A2.996 2.996 0 0 1 1 8c0-.982.472-1.854 1.202-2.402a2.995 2.995 0 0 1 .848-2.547 2.995 2.995 0 0 1 2.548-.849A2.995 2.995 0 0 1 8 1c.982 0 1.854.472 2.402 1.202a2.995 2.995 0 0 1 2.547.848c.695.695.978 1.645.849 2.548A2.996 2.996 0 0 1 15 8Zm-3.291-2.843a.75.75 0 0 1 .135 1.052l-4.25 5.5a.75.75 0 0 1-1.151.043l-2.25-2.5a.75.75 0 1 1 1.114-1.004l1.65 1.832 3.7-4.789a.75.75 0 0 1 1.052-.134Z" clip-rule="evenodd" />
													</svg>
													<svg x-show="!profileStore.profileInfo.npubVerified" class="size-3 fill-red-500" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
														<path fill-rule="evenodd" d="M3.05 3.05a7 7 0 1 1 9.9 9.9 7 7 0 0 1-9.9-9.9Zm1.627.566 7.707 7.707a5.501 5.501 0 0 0-7.707-7.707Zm6.646 8.768L3.616 4.677a5.501 5.501 0 0 0 7.707 7.707Z" clip-rule="evenodd" />
													</svg>
													<span x-text="profileStore.profileInfo.npubVerified ? 'Verified' : 'Unverified'"></span>
												</span>
											</div>
											<div class="sm:col-span-2 overflow-x-hidden">
												<p x-text="profileStore.profileInfo.npub" class="block w-full py-1.5 text-nbpurple-50 font-bold text-xs sm:text-sm sm:leading-6 truncate"></p>
											</div>
										</div>

										<!-- Allow NOSTR login -->
										<div x-show="profileStore.profileInfo.npubVerified" class="space-y-2 px-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:space-y-0 sm:px-6 sm:py-5 items-center">
											<div class="flex items-center space-x-4">
												<span class="block text-sm font-medium leading-6 text-nbpurple-200" id="allow-nostr-login-label">Enable NOSTR Login</span>
											</div>
											<div class="sm:col-span-2">
												<button @click="profileStore.profileInfo.allowNostrLogin = !profileStore.profileInfo.allowNostrLogin" type="button" :class="profileStore.profileInfo.allowNostrLogin ? 'bg-nbpurple-600' : 'bg-nbpurple-200'" class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-nbpurple-600 focus:ring-offset-2" role="switch" aria-checked="profileStore.profileInfo.allowNostrLogin" aria-labelledby="allow-nostr-login-label" aria-describedby="allow-nostr-login-label">
													<span aria-hidden="true" :class="profileStore.profileInfo.allowNostrLogin ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-nbpurple-50 shadow ring-0 transition duration-200 ease-in-out"></span>
												</button>
											</div>
										</div>

										<!-- Profile Picture URL -->
										<div x-data="{ pfpError: false }" class="space-y-2 px-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:space-y-0 sm:px-6 sm:py-5 items-center">
											<div class="flex items-center space-x-4">
												<label for="pfpUrl" class="block text-sm font-medium leading-6 text-nbpurple-200">Profile Picture URL</label>
												<template x-if="profileStore.profileInfo.pfpUrl && !pfpError">
													<img class="inline-block h-8 w-8 rounded-full object-cover" :src="profileStore.profileInfo.pfpUrl" :alt="'Profile picture for ' + profileStore.profileInfo.name" @error="pfpError = true" @load="pfpError = false">
												</template>
												<template x-if="!profileStore.profileInfo.pfpUrl || pfpError">
													<svg fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="inline-block h-8 w-8 rounded-full text-nbpurple-400">
														<path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
													</svg>
												</template>
											</div>
											<div class="sm:col-span-2">
												<input type="text" name="pfpUrl" id="pfpUrl" x-model="profileStore.profileInfo.pfpUrl" @input.debounce.500ms="pfpError = false" autocomplete="url" autocapitalize="off" placeholder="https://" class="block w-full rounded-md border-0 py-1.5 bg-nbpurple-600 text-nbpurple-100 font-medium shadow-sm ring-1 ring-inset ring-nbpurple-200 placeholder:text-nbpurple-400 focus:ring-2 focus:ring-inset focus:ring-nbpurple-600 sm:text-sm sm:leading-6">
											</div>
										</div>

										<!-- Name -->
										<div class="space-y-2 px-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:space-y-0 sm:px-6 sm:py-5">
											<div>
												<label for="name" class="block text-sm font-medium leading-6 text-nbpurple-200 sm:mt-1.5">Name</label>
											</div>
											<div class="sm:col-span-2">
												<input type="text" name="name" id="name" x-model="profileStore.profileInfo.name" autocomplete="off" class="block w-full rounded-md border-0 py-1.5 bg-nbpurple-600 text-nbpurple-100 font-medium shadow-sm ring-1 ring-inset ring-nbpurple-300 placeholder:text-nbpurple-400 focus:ring-2 focus:ring-inset focus:ring-nbpurple-600 sm:text-sm sm:leading-6">
											</div>
										</div>

										<!-- Wallet -->
										<div class="space-y-2 px-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:space-y-0 sm:px-6 sm:py-5">
											<div>
												<label for="wallet" class="block text-sm font-medium leading-6 text-nbpurple-200 sm:mt-1.5">Wallet Address</label>
											</div>
											<div class="sm:col-span-2">
												<input type="text" name="wallet" id="wallet" x-model="profileStore.profileInfo.wallet" autocomplete="off" class="block w-full rounded-md border-0 py-1.5 bg-nbpurple-600 text-nbpurple-100 font-medium shadow-sm ring-1 ring-inset ring-nbpurple-300 placeholder:text-nbpurple-400 focus:ring-2 focus:ring-inset focus:ring-nbpurple-600 sm:text-sm sm:leading-6">
											</div>
										</div>

										<!-- Default Folder -->
										<div x-effect="selected = menuStore.getFolderObjByName(profileStore.profileInfo.defaultFolder) || menuStore.folders.find(folder => folder.id === 0)" x-data="{ 
													open: false, 
													selected: {},
													setDefaultFolder(folder) {
														profileStore.profileInfo.defaultFolder = folder.id === 0 ? '' : folder.name;
													}
												}" class="space-y-2 px-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:space-y-0 sm:px-6 sm:py-5">
											<div>
												<label id="listbox-label" for="wallet" class="block text-sm font-medium leading-6 text-nbpurple-200 sm:mt-1.5">Default Folder</label>
											</div>
											<div class="relative sm:col-span-2">
												<button type="button" @click="open = !open" :aria-expanded="open" aria-haspopup="listbox" aria-labelledby="listbox-label" class="relative w-full cursor-default rounded-md bg-nbpurple-600 text-nbpurple-100 py-1.5 pl-3 pr-10 text-left shadow-sm ring-1 ring-inset ring-nbpurple-300 focus:outline-none focus:ring-2 focus:ring-nbpurple-600 sm:text-sm sm:leading-6">
													<span class="block truncate" x-text="selected ? selected.name : 'Select default folder'"></span>
													<span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
														<svg class="h-5 w-5 text-nbpurple-200" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
															<path fill-rule="evenodd" d="M10 3a.75.75 0 01.55.24l3.25 3.5a.75.75 0 11-1.1 1.02L10 4.852 7.3 7.76a.75.75 0 01-1.1-1.02l3.25-3.5A.75.75 0 0110 3zm-3.76 9.2a.75.75 0 011.06.04l2.7 2.908 2.7-2.908a.75.75 0 111.1 1.02l-3.25 3.5a.75.75 0 01-1.1 0l-3.25-3.5a.75.75 0 01.04-1.06z" clip-rule="evenodd" />
														</svg>
													</span>
												</button>
												<ul x-show="open" @click.outside="open = false" class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md bg-nbpurple-600 py-1 text-base shadow-lg ring-1 ring-nbpurple-950 ring-opacity-5 focus:outline-none sm:text-sm" tabindex="-1" role="listbox" aria-labelledby="listbox-label" :aria-activedescendant="selected ? 'listbox-option-' + selected.id : null">
													<template x-for="folder in menuStore.folders" :key="folder.id">
														<li :id="'listbox-option-' + folder.id" role="option" @click="setDefaultFolder(folder); open = false;" :class="{ 'bg-nbpurple-700 text-nbpurple-50': folder === selected, 'text-nbpurple-200': folder !== selected }" class="hover:bg-nbpurple-700 relative cursor-default select-none py-2 pl-3 pr-9">
															<span x-text="folder.name" :class="{ 'font-semibold': folder === selected, 'font-normal': folder !== selected }" class="block truncate"></span>
															<span x-show="folder === selected" :class="{ 'text-nbpurple-50': folder === selected, 'text-nbpurple-200': folder !== selected }" class="absolute inset-y-0 right-0 flex items-center pr-4">
																<svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
																	<path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
																</svg>
															</span>
														</li>
													</template>
												</ul>
											</div>
										</div>

										<!-- Action buttons -->
										<div class="flex-shrink-0 border-t border-nbpurple-200 px-4 py-5 sm:px-6">
											<div class="flex justify-end space-x-3">
												<button type="button" class="rounded-md bg-nbpurple-100 px-3 py-2 text-sm font-semibold text-nbpurple-800 shadow-sm ring-1 ring-inset ring-nbpurple-300 hover:bg-nbpurple-50" @click="profileStore.closeDialog()">Cancel</button>
												<button type="submit" class="inline-flex justify-center rounded-md bg-nbpurple-600 px-3 py-2 text-sm font-semibold text-nbpurple-50 shadow-sm hover:bg-nbpurple-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-nbpurple-600" :disabled="profileStore.dialogLoading">
													<span x-show="!profileStore.dialogLoading">Save</span>
													<span class="animate-pulse" x-show="profileStore.dialogLoading">Saving...</span>
												</button>
											</div>
										</div>

										<!-- Password -->
										<div class="space-y-2 px-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:space-y-0 sm:px-6 sm:py-5">
											<div>
												<h3 class="text-sm font-medium leading-6 text-nbpurple-200">Password</h3>
											</div>
											<div class="space-y-5 sm:col-span-2">
												<div class="space-y-2">
													<div>
														<label for="current-password" class="block text-sm font-medium leading-6 text-nbpurple-200">Current Password</label>
														<input type="password" name="current-password" id="current-password" x-ref="currentPassword" autocomplete="current-password" class="block w-full rounded-md border-0 py-1.5 bg-nbpurple-600 text-nbpurple-100 font-medium shadow-sm ring-1 ring-inset ring-nbpurple-300 placeholder:text-nbpurple-400 focus:ring-2 focus:ring-inset focus:ring-nbpurple-600 sm:text-sm sm:leading-6">
													</div>
													<div>
														<label for="new-password" class="block text-sm font-medium leading-6 text-nbpurple-200">New Password</label>
														<input type="password" name="new-password" id="new-password" x-ref="newPassword" autocomplete="new-password" class="block w-full rounded-md border-0 py-1.5 bg-nbpurple-600 text-nbpurple-100 font-medium shadow-sm ring-1 ring-inset ring-nbpurple-300 placeholder:text-nbpurple-400 focus:ring-2 focus:ring-inset focus:ring-nbpurple-600 sm:text-sm sm:leading-6">
													</div>
													<div>
														<label for="confirm-password" class="block text-sm font-medium leading-6 text-nbpurple-200">Confirm Password</label>
														<input type="password" name="confirm-password" id="confirm-password" x-ref="confirmPassword" autocomplete="new-password" class="block w-full rounded-md border-0 py-1.5 bg-nbpurple-600 text-nbpurple-100 font-medium shadow-sm ring-1 ring-inset ring-nbpurple-300 placeholder:text-nbpurple-400 focus:ring-2 focus:ring-inset focus:ring-nbpurple-600 sm:text-sm sm:leading-6">
													</div>
												</div>
												<button :diabled="profileStore.dialogLoading || !$refs.currentPassword?.value || !$refs.newPassword || !$refs.confirmPassword " type="button" class="rounded-md bg-nbpurple-200 px-3 py-2 text-sm font-semibold text-nbpurple-900 shadow-sm ring-1 ring-inset ring-nbpurple-300 hover:bg-nbpurple-50" @click.throttle.250ms="await profileStore.updatePassword($refs.currentPassword, $refs.newPassword, $refs.confirmPassword)">
													<span>Update Password</span>
												</button>
											</div>
										</div>
									</div>
								</div>

							</form>
						</div>
					</div>
				</div>
			</div>

		</div>

		<!-- Toast notification -->
		<div x-cloak x-show="showToast" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-x-8" x-transition:enter-end="opacity-100 transform translate-x-0" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 transform translate-x-0" x-transition:leave-end="opacity-0 transform translate-x-8" class="z-50 fixed top-6 right-6 bg-orange-500 text-nbpurple-50 px-4 py-2 rounded-md flex items-center">
			<span class="mr-2 text-xs">Link Copied</span>
			<svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
				<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
			</svg>
		</div>

	</main>

</body>

</html>