<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/UploadsData.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/PublicPlans.class.php';

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

// Headline social proof. These are the strongest numbers on the page, so they
// read as a brag rather than a readout: "3.3M" beats "3,349,212" at a glance,
// and "4.9 TB" beats "5021.58 GB".
$proof_files = $total_files >= 1000000
  ? round($total_files / 1000000, 1) . 'M'
  : ($total_files >= 1000 ? round($total_files / 1000) . 'K' : (string) $total_files);
$proof_size = $total_size_gb >= 1024
  ? round($total_size_gb / 1024, 1) . ' TB'
  : round($total_size_gb) . ' GB';

// Plan catalog, fetched from account.nostr.build (the owner of prices, tiers
// and feature copy) and cached on disk. Unavailable = the pricing section is
// skipped and the page falls back to a plain CTA; it never blocks the render.
$publicPlans = new PublicPlans();
$plans = $publicPlans->plans();
$fromPrice = $publicPlans->fromPriceUsd();
$included = $publicPlans->get()['includedWithEveryPlan'] ?? [];
if (!is_array($included)) {
  $included = [];
}

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

// Icons for the three "what you get" stack cards
$svg_icon_media = <<<SVG
<svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="5" width="18" height="14" rx="2" stroke="#B098BB" stroke-width="2"/><path d="M3 15l4.5-4.5L12 15l3-3 6 6" stroke="#2EDF95" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="8.5" cy="9.5" r="1.5" fill="#F78533"/></svg>
SVG;

$svg_icon_relay = <<<SVG
<svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="2.5" fill="#2EDF95"/><path d="M7.5 7.5a6.4 6.4 0 000 9M16.5 7.5a6.4 6.4 0 010 9" stroke="#B098BB" stroke-width="2" stroke-linecap="round"/><path d="M4.5 4.5a10.6 10.6 0 000 15M19.5 4.5a10.6 10.6 0 010 15" stroke="#F78533" stroke-width="2" stroke-linecap="round"/></svg>
SVG;

$svg_icon_blossom = <<<SVG
<svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="2.2" fill="#F78533"/><path d="M12 4.2a3.1 3.1 0 013.1 3.1c0 1-.5 1.9-1.2 2.4M19.8 12a3.1 3.1 0 01-3.1 3.1c-1 0-1.9-.5-2.4-1.2M12 19.8a3.1 3.1 0 01-3.1-3.1c0-1 .5-1.9 1.2-2.4M4.2 12a3.1 3.1 0 013.1-3.1c1 0 1.9.5 2.4 1.2" stroke="#2EDF95" stroke-width="2" stroke-linecap="round"/></svg>
SVG;

?>

<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="keywords" content="Nostr, Damus, Primal, noStrudel, Coracle.social, YakiHonne, Amethyst, snort.social, Iris.to, astril.ninja, media uploader, bitcoin media uploader, nostr videos, image uploader, image link, image, uploader, media upload, damus pictures, video uploader, nostr repository, Bitcoin ">
	<meta name="description" content="Your media, your relay. nostr.build has hosted Nostr's media since 2022 — free uploads built into your favorite apps, and paid plans that add private storage, your own Nostr relay with unlimited history, and your own Blossom server. Bitcoin only, no ads, metadata stripped.">
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />

	<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
	<link rel="manifest" href="/site.webmanifest">
	<link rel="mask-icon" href="/safari-pinned-tab.svg" color="#5bbad5">
	<meta name="msapplication-TileColor" content="#9f00a7">
	<meta name="theme-color" content="#ffffff">

	<link rel="stylesheet" href="/styles/index.css?v=5f778fa5254a390824630c03c36a7c50" />
	<link rel="stylesheet" href="/styles/header.css?v=19cde718a50bd676387bbe7e9e24c639" />

	<title>nostr.build media uploader</title>
</head>

<body>
	<header class="header">
		<?php include $_SERVER['DOCUMENT_ROOT'] . '/components/mainnav.php'; ?>
	</header>

	<main>
		<!--
			Slim account bar. This used to be a full-width island above the
			headline, which cost the fold for something only returning users need.
			The account.nostr.build session is separate and longer-lived, so the
			path is offered to guests too — that site resolves the real session
			(dashboard if active, otherwise login).
		-->
		<div class="account_bar">
			<?php if (!$isGuest): ?>
				<img class="account_bar_pfp" src="<?= htmlspecialchars($ppic) ?>" alt="">
				<span class="account_bar_text">Welcome back<?= $nym ? ', ' . htmlspecialchars($nym) : '' ?></span>
				<a class="account_bar_link" href="https://account.nostr.build/">Account Page→</a>
			<?php else: ?>
				<span class="account_bar_text">Already have an account?</span>
				<a class="account_bar_link" href="https://account.nostr.build/">Go Here →</a>
			<?php endif; ?>
		</div>

		<!--
			Compact hero. Three deliberate CRO choices:
			  * ONE headline (the page used to stack an h1 "nostr media uploader"
			    above a competing hero h2) and a one-line subhead, so the plan
			    cards below start near the fold instead of a scroll away.
			  * Social proof sits ABOVE the CTA, not below it. 3.3M files is the
			    strongest asset this page has and it was buried as a raw
			    "5021.58 GB used" string under the buttons.
			  * One primary CTA. The secondary button competed for the same click,
			    so it is a quiet text link now.
		-->
		<section class="hero_content">
						<!-- Labels kept short so all three chips fit a 320px viewport in two
			     clean rows; each chip is atomic, so they wrap as whole units. -->
			<div class="info_cards">
				<div class="info"><span><?= $proof_files ?></span> files</div>
				<div class="info"><span><?= $proof_size ?></span> stored</div>
				<div class="info"><span>since 2022</span></div>
			</div>
			<h1 class="hero_headline">Your media, your relay</h1>
			<h2 class="band_title">One price, per year, in Bitcoin</h2>
			<p class="hero_sub">
				Private storage, your own Nostr relay, & Blossom server. No subscription to cancel, no card on file.
			</p>
			<!-- Handles the "is this a bait and switch" objection in one line,
			     right where the price lands. -->
		</section>

		<?php if ($plans !== []): ?>
		<!-- Rendered from account.nostr.build's public catalog, so prices and
		     feature bullets here can never drift from the checkout page. -->
		<section class="plans_band" id="plans">

			<div class="plan_cards">
				<?php foreach ($plans as $plan): ?>
					<?php
					if (!is_array($plan) || !isset($plan['name'], $plan['storage'], $plan['priceUsd'])) {
						continue;
					}
					$isPopular = ($plan['badge'] ?? null) === 'popular';
					$planFeatures = is_array($plan['features'] ?? null) ? $plan['features'] : [];
					$planUrl = is_string($plan['checkoutUrl'] ?? null)
						? $plan['checkoutUrl']
						: 'https://account.nostr.build/plans';
					?>
					<div class="plan_card<?= $isPopular ? ' plan_card_popular' : '' ?>">
						<?php if ($isPopular): ?><span class="plan_badge">Most popular</span><?php endif; ?>
						<h3><?= htmlspecialchars((string) $plan['name']) ?></h3>
						<?php if (isset($plan['tagline'])): ?>
							<p class="plan_tagline"><?= htmlspecialchars((string) $plan['tagline']) ?></p>
						<?php endif; ?>
						<p class="plan_price">
							<span class="plan_price_amount">$<?= (int) $plan['priceUsd'] ?></span>
							<span class="plan_price_term">/year</span>
						</p>
						<p class="plan_storage"><?= htmlspecialchars((string) $plan['storage']) ?> private storage</p>
						<ul class="plan_features">
							<?php foreach ($planFeatures as $feature): ?>
								<li><?= htmlspecialchars((string) $feature) ?></li>
							<?php endforeach; ?>
						</ul>
						<a class="<?= $isPopular ? 'cta_button' : 'cta_button_secondary' ?> plan_cta" href="<?= htmlspecialchars($planUrl) ?>">Choose <?= htmlspecialchars((string) $plan['name']) ?></a>
					</div>
				<?php endforeach; ?>
			</div>
			<?php if ($included !== []): ?>
				<!-- Reassurance, so it sits AFTER the cards as a compact chip row.
				     Nothing goes between the heading and the buy buttons. -->
				<ul class="included_chips">
					<li class="included_chips_label">Every plan includes</li>
					<?php foreach ($included as $item): ?>
						<?php if (!is_array($item) || !isset($item['title'], $item['description'])) { continue; } ?>
						<li><span title="<?= htmlspecialchars((string) $item['description']) ?>"><?= htmlspecialchars((string) $item['title']) ?></span></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<p class="plans_footnote">Prices are for 1-year. 2 and 3-year terms cost less. Upgrades and renew at <a href="https://account.nostr.build/plans">account.nostr.build</a>.</p>
		</section>
		<?php endif; ?>

		<!-- The three things a plan actually gives you. Most visitors arrive
		     knowing only the uploader, so name the relay and Blossom explicitly. -->
		<section class="stack_props">
			<div class="stack_prop">
				<div class="value_prop_icon"><?= $svg_icon_media ?></div>
				<h3>Media hosting</h3>
				<p>Images, audio and video up to 450MB a file, on private storage that is yours — organized in folders, shared straight to Nostr, delivered from a global CDN.</p>
			</div>
			<div class="stack_prop">
				<div class="value_prop_icon"><?= $svg_icon_relay ?></div>
				<h3>Your own Nostr relay</h3>
				<p>Every plan includes your relay at relay.nostr.build. Unlimited events with no expiry, one-click import of your history, and your public notes pushed out to the wider network for you.</p>
				<a class="stack_link" href="https://relay.nostr.build" rel="noopener">relay.nostr.build →</a>
			</div>
			<div class="stack_prop">
				<div class="value_prop_icon"><?= $svg_icon_blossom ?></div>
				<h3>Your own Blossom server</h3>
				<p>A dedicated <span class="nowrap">name.blossom.band</span> domain for your media, not shared with anyone else, speaking the Blossom protocol your client already knows.</p>
				<a class="stack_link" href="https://blossom.band" rel="noopener">blossom.band →</a>
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

		<?php if ($plans !== []): ?>

			<!-- Honest free-vs-paid. Free really does stay free and generous; say
			     exactly what money buys instead of implying free is crippled. -->
			<section class="compare_band">
				<h2 class="band_title">Free v. Paid Plans</h2>
				<div class="compare_grid">
					<div class="compare_col">
						<h3>Free, forever</h3>
						<ul>
							<li>Uploads from any Nostr client through our API</li>
							<li>Images, audio and video</li>
							<li>EXIF and location metadata stripped</li>
							<li>Global CDN delivery</li>
							<li>No ads, no trackers, no account required</li>
						</ul>
					</div>
					<div class="compare_col compare_col_paid">
						<h3>With a plan</h3>
						<ul>
							<li>Private storage you own and manage, in folders</li>
							<li>Files up to 450MB, plus PDF, SVG and ZIP on higher tiers</li>
							<li>Your own Nostr relay — unlimited history, no expiry</li>
							<li>Your own <span class="nowrap">name.blossom.band</span> server</li>
							<li>Detailed stats, backups and AI Studio credits</li>
						</ul>
					</div>
				</div>
				<p class="compare_note">*Free uploads are not going away. Paid plans are how nostr.build covers costs, not frpm ads!</p>
			</section>
		<?php else: ?>
			<!-- Catalog unavailable: never block the page on it, just point at the
			     canonical pricing page. -->
			<section class="dev_callout">
				<h2>Ready for storage that is yours?</h2>
				<p>Private media storage, your own Nostr relay and your own Blossom server — one payment a year, in Bitcoin.</p>
				<a class="cta_button" href="https://account.nostr.build/plans">See the plans <span class="cta_arrow">→</span></a>
			</section>
		<?php endif; ?>

		<section class="dev_callout">
			<h2>Building a Nostr client?</h2>
			<p>Integrate free and pro uploads with NIP-98 and Blossom — straight from your app.</p>
			<a class="cta_button_secondary" href="https://account.nostr.build/features">See the features</a>
		</section>

		<div class="terms">
			By using nostr.build you agree to our <a href="https://account.nostr.build/tos"><span>Terms of Service</span></a> and <a href="https://account.nostr.build/privacy"><span>Privacy Policy</span></a>
		</div>
	</main>

	<?php include $_SERVER['DOCUMENT_ROOT'] . '/components/footer.php'; ?>

</body>

</html>
