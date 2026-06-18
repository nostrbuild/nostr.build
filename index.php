<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/UploadsData.class.php';

// Globals
global $link;

// Instantiate permissions class (used to tailor the call-to-action for
// signed-in subscribers vs. guests)
$perm = new Permission();
$isGuest = $perm->isGuest();

// Signed-in user details for the "Open my account" block
$ppic = (isset($_SESSION["ppic"]) && !empty($_SESSION["ppic"])) ? $_SESSION["ppic"] : "https://nostr.build/assets/temp_ppic.png";
$nym  = (isset($_SESSION["nym"]) && !empty($_SESSION["nym"])) ? $_SESSION["nym"] : "";

// Fetch statistics
$uploadsData = new UploadsData($link);
$stats = $uploadsData->getStats();

$total_files = $stats['total_files'];
$total_size_gb = round($stats['total_size'] / (1024 * 1024 * 1024), 2); // Convert bytes to GB

header("Expires: Thu, 19 Nov 1981 08:52:00 GMT"); //Date in the past
header("Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0"); //HTTP/1.1
header("Pragma: no-cache");

// Inline icons for the value-prop cards
$svg_icon_api = <<<SVG
<svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 8l-4 4 4 4M15 8l4 4-4 4" stroke="#2EDF95" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
SVG;

$svg_icon_privacy = <<<SVG
<svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 3l7 3v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3z" stroke="#B098BB" stroke-width="2" stroke-linejoin="round"/><path d="M9 12l2 2 4-4" stroke="#2EDF95" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
SVG;

$svg_icon_nostr = <<<SVG
<svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M13 2L4 14h6l-1 8 9-12h-6l1-8z" stroke="#F78533" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/></svg>
SVG;

// Generic account icon for the guest "Go to your account" banner
$svg_icon_account = <<<SVG
<svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="8" r="4" stroke="#B098BB" stroke-width="2"/><path d="M4 20c0-4 3.6-6 8-6s8 2 8 6" stroke="#B098BB" stroke-width="2" stroke-linecap="round"/></svg>
SVG;
?>

<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="keywords" content="Nostr, Damus, Primal, noStrudel, Coracle.social, YakiHonne, Amethyst, snort.social, Iris.to, astril.ninja, media uploader, bitcoin media uploader, nostr videos, image uploader, image link, image, uploader, media upload, damus pictures, video uploader, nostr repository, Bitcoin ">
	<meta name="description" content="nostr.build powers image, video and media uploads for Nostr — Damus, Primal, YakiHonne, noStrudel, Amethyst, snort.social, Coracle and more. Free uploads are built right into your favorite apps through the nostr.build API. Bitcoin only, no ads, metadata stripped.">
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />

	<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
	<link rel="manifest" href="/site.webmanifest">
	<link rel="mask-icon" href="/safari-pinned-tab.svg" color="#5bbad5">
	<meta name="msapplication-TileColor" content="#9f00a7">
	<meta name="theme-color" content="#ffffff">

	<link rel="stylesheet" href="/styles/index.css?v=5b9f346f2037f65228c8d5b6f42ee2aa" />
	<link rel="stylesheet" href="/styles/header.css?v=19cde718a50bd676387bbe7e9e24c639" />

	<title>nostr.build media uploader</title>
</head>

<body>
	<header class="header">
		<?php include $_SERVER['DOCUMENT_ROOT'] . '/components/mainnav.php'; ?>
	</header>

	<main>
		<?php if (!$isGuest): ?>
			<!-- Signed-in subscribers: obvious, one-click path to their account -->
			<section class="account_banner">
				<span class="account_banner_avatar">
					<img class="account_banner_pfp" src="<?= htmlspecialchars($ppic) ?>" alt="Your profile picture">
				</span>
				<div class="account_banner_text">
					<h2>Welcome back<?= $nym ? ', ' . htmlspecialchars($nym) : '' ?></h2>
					<p>Manage your media, storage, and subscription.</p>
				</div>
				<a class="cta_button account_banner_cta" href="https://account.nostr.build/">Open my account <span class="cta_arrow">→</span></a>
			</section>
		<?php else: ?>
			<!--
				Not signed in on THIS site — but the account.nostr.build session is
				separate and much longer-lived, so the user may still be logged in there.
				Always offer the path; account.nostr.build resolves the real session
				(straight to the dashboard if active, otherwise login).
			-->
			<section class="account_banner account_banner_guest">
				<div class="account_banner_icon"><?= $svg_icon_account ?></div>
				<div class="account_banner_text">
					<h2>Already have an account?</h2>
					<p>Still signed in on account.nostr.build? You'll go straight to your dashboard — no need to log in again.</p>
				</div>
				<a class="cta_button account_banner_cta" href="https://account.nostr.build/">Go to your account <span class="cta_arrow">→</span></a>
			</section>
		<?php endif; ?>

		<div class="title_container">
			<h1>nostr media uploader</h1>
			<p>removes metadata, free, nostr focused</p>
			<div class="info_cards">
				<div class="info"><span><?= $total_size_gb ?></span>GB used</div>
				<div class="info"><span><?= number_format($total_files) ?></span> total uploads</div>
			</div>
		</div>

		<section class="hero_content">
			<h2 class="hero_headline">Free media uploads, now built right into your favorite apps</h2>
			<p class="hero_sub">
				nostr.build powers image, video, and audio for the Nostr ecosystem. Free uploads now happen straight
				from your client through the nostr.build API — no browser upload box needed.
			</p>
			<div class="cta_row">
				<?php if ($isGuest): ?>
					<a class="cta_button" href="https://account.nostr.build/plans">Get started</a>
					<a class="cta_button_secondary" href="https://account.nostr.build/features">Explore features</a>
				<?php else: ?>
					<a class="cta_button_secondary" href="https://account.nostr.build/features">Explore features</a>
				<?php endif; ?>
			</div>
		</section>

		<section class="value_props">
			<div class="value_prop">
				<div class="value_prop_icon"><?= $svg_icon_api ?></div>
				<h3>Powered by our API</h3>
				<p>Damus, Amethyst, Primal, Snort, YakiHonne, noStrudel, Coracle and more upload directly to nostr.build. Free uploads, baked into the apps you already use.</p>
			</div>
			<div class="value_prop">
				<div class="value_prop_icon"><?= $svg_icon_privacy ?></div>
				<h3>Privacy by default</h3>
				<p>Every file is stripped of EXIF and location metadata before it goes live. Your media, not your whereabouts.</p>
			</div>
			<div class="value_prop">
				<div class="value_prop_icon"><?= $svg_icon_nostr ?></div>
				<h3>Built for Nostr — Bitcoin only</h3>
				<p>Purpose-built media hosting for Nostr. No ads, ever. Upgrade for more storage and pro features, paid in Bitcoin.</p>
			</div>
		</section>

		<p class="clients_strip">
			Trusted across <span>Damus · Primal · Amethyst · Snort · YakiHonne · noStrudel · Coracle · Blossom</span>
		</p>

		<section class="dev_callout">
			<h2>Building a Nostr client?</h2>
			<p>Integrate free and pro uploads with NIP-98 auth and Blossom protocol support — straight from your app.</p>
			<a class="cta_button_secondary" href="https://account.nostr.build/features">See the features</a>
		</section>

		<div class="terms">
			By using nostr.build you agree to our <a href="https://account.nostr.build/tos"><span>Terms of Service</span></a> and <a href="https://account.nostr.build/privacy"><span>Privacy Policy</span></a>
		</div>
	</main>

	<?php include $_SERVER['DOCUMENT_ROOT'] . '/components/footer.php'; ?>

</body>

</html>
