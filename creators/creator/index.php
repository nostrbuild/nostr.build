<?php
// Public creator portfolio page: /creators/creator/?user=<id>&display=image|video|audio&page=N
// Self-contained on purpose (styles + scripts inline); this page is slated for a rehost.
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/SiteConfig.php';

const CR_PER_PAGE = 60;
const CR_TYPES = ['image', 'video', 'audio'];
const CR_FALLBACK_PPIC = 'https://nostr.build/assets/temp_ppic.png';

function e(?string $v): string
{
	return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cr_fmt_bytes(?int $bytes): string
{
	$bytes = (int) $bytes;
	if ($bytes <= 0) return '';
	$units = ['B', 'KB', 'MB', 'GB'];
	$i = (int) floor(log($bytes, 1024));
	$i = min($i, count($units) - 1);
	$n = $bytes / (1024 ** $i);
	return ($i === 0 ? (string) $n : number_format($n, $n >= 100 ? 0 : 1)) . ' ' . $units[$i];
}

function cr_fmt_date(?string $ts): string
{
	if (empty($ts)) return '';
	$t = strtotime($ts);
	return $t ? date('M j, Y', $t) : '';
}

function cr_page_url(int $userId, string $display, int $page): string
{
	$q = ['user' => $userId, 'display' => $display];
	if ($page > 1) $q['page'] = $page;
	return '/creators/creator/?' . http_build_query($q);
}

$userId  = (int) ($_GET['user'] ?? 0);
$display = $_GET['display'] ?? 'image';
if (!in_array($display, CR_TYPES, true)) $display = 'image';
$page    = max(1, (int) ($_GET['page'] ?? 1));

// Creator profile (only active pro accounts are public)
$stmt = $link->prepare("
	SELECT uuid_id, nym, ppic, wallet, usernpub
	FROM users
	WHERE id = ? AND plan_until_date > NOW() AND acctlevel IN (1, 10, 99)
	LIMIT 1
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$creator = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$creator) {
	$link->close();
	http_response_code(404);
	$notFound = true;
}

$counts = ['image' => 0, 'video' => 0, 'audio' => 0];
$rows = [];
$total = 0;
$pages = 1;

if (empty($notFound)) {
	// Per-type counts of media the creator shared to their public page (flag = 1)
	$stmt = $link->prepare("
		SELECT SUBSTRING_INDEX(mime_type, '/', 1) AS type, COUNT(*) AS count
		FROM users_images
		WHERE user_uuid = ? AND flag = 1
		GROUP BY type
	");
	$stmt->bind_param('s', $creator['uuid_id']);
	$stmt->execute();
	foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
		if (isset($counts[$r['type']])) $counts[$r['type']] = (int) $r['count'];
	}
	$stmt->close();

	// Selected tab is empty: jump to the first tab that has something
	if ($counts[$display] === 0) {
		foreach ($counts as $type => $count) {
			if ($count > 0) {
				$link->close();
				header('Location: ' . cr_page_url($userId, $type, 1));
				exit;
			}
		}
	}

	$total = $counts[$display];
	$pages = max(1, (int) ceil($total / CR_PER_PAGE));
	if ($page > $pages) {
		$link->close();
		header('Location: ' . cr_page_url($userId, $display, $pages));
		exit;
	}
	$offset = ($page - 1) * CR_PER_PAGE;
	$mimeLike = $display . '/%';

	$stmt = $link->prepare("
		SELECT id, image, mime_type, media_width, media_height, blurhash, title, description, file_size, created_at
		FROM users_images
		WHERE user_uuid = ? AND flag = 1 AND mime_type LIKE ?
		ORDER BY id DESC
		LIMIT ? OFFSET ?
	");
	$perPage = CR_PER_PAGE;
	$stmt->bind_param('ssii', $creator['uuid_id'], $mimeLike, $perPage, $offset);
	$stmt->execute();
	$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
	$link->close();
}

$nym     = trim((string) ($creator['nym'] ?? '')) ?: 'Creator';
$creatorPpic = !empty($creator['ppic']) ? $creator['ppic'] : CR_FALLBACK_PPIC;
$wallet  = trim((string) ($creator['wallet'] ?? ''));
$npub    = trim((string) ($creator['usernpub'] ?? ''));
$npubShort = $npub ? substr($npub, 0, 12) . '…' . substr($npub, -6) : '';
$firstOfPage = $total ? ($page - 1) * CR_PER_PAGE + 1 : 0;
$lastOfPage  = min($total, $page * CR_PER_PAGE);

$summaryParts = [];
foreach ($counts as $type => $count) {
	if ($count > 0) $summaryParts[] = $count . ' ' . $type . ($count === 1 ? '' : 's');
}
$summary = $summaryParts ? implode(', ', $summaryParts) : 'No shared media yet';
$ogTitle = $nym . ' · nostr.build';
$ogDescription = 'Creator portfolio on nostr.build · ' . $summary;

// Row -> URLs (mirrors the account app: posters for video and audio live at {url}/poster.jpg)
function cr_media(array $row): array
{
	$filename = pathinfo((string) parse_url($row['image'], PHP_URL_PATH), PATHINFO_BASENAME);
	$type = explode('/', $row['mime_type'])[0];
	$key = 'professional_account_' . $type;
	$full = SiteConfig::getFullyQualifiedUrl($key) . $filename;
	$thumb = SiteConfig::getThumbnailUrl($key) . $filename;
	$srcset = [];
	if ($type === 'image') {
		foreach (['240p' => 426, '360p' => 640, '480p' => 854, '720p' => 1280, '1080p' => 1920] as $res => $w) {
			$srcset[] = SiteConfig::getResponsiveUrl($key, $res) . $filename . " {$w}w";
		}
	}
	// Shareable player page on e.nostr.build: "<host prefix>_<url-encoded path>", see nostrmedia-stream-worker-embed
	$hostPrefix = explode('.', (string) parse_url($full, PHP_URL_HOST))[0];
	return [
		'type'     => $type,
		'filename' => $filename,
		'full'     => $full,
		'embed'    => 'https://e.nostr.build/' . $hostPrefix . '_' . rawurlencode($filename),
		'thumb'    => $thumb,
		'poster'   => $full . '/poster.jpg',
		'srcset'   => implode(', ', $srcset),
	];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="description" content="<?= e($ogDescription) ?>" />
	<meta property="og:type" content="profile" />
	<meta property="og:title" content="<?= e($ogTitle) ?>" />
	<meta property="og:description" content="<?= e($ogDescription) ?>" />
	<meta property="og:image" content="<?= e($creatorPpic) ?>" />
	<meta name="twitter:card" content="summary" />

	<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
	<link rel="manifest" href="/site.webmanifest">
	<link rel="mask-icon" href="/safari-pinned-tab.svg" color="#5bbad5">
	<meta name="msapplication-TileColor" content="#9f00a7">
	<meta name="theme-color" content="#24204b">

	<link rel="stylesheet" href="/styles/index.css?v=5f778fa5254a390824630c03c36a7c50" />
	<link rel="stylesheet" href="/styles/header.css?v=19cde718a50bd676387bbe7e9e24c639" />
	<?php if ($display !== 'audio' && $rows) : ?>
		<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5.4.4/dist/photoswipe.css" integrity="sha384-IfxC36XL/toUyJ939C73PcgMuRzAZuIzZxE38drsmO5p6jD7ei+Zx/1oA/0l8ysE" crossorigin="anonymous" />
	<?php endif; ?>
	<?php if ($display === 'video' && $rows) : ?>
		<!-- video.js 10 (web components); the entry pulls its content-hashed chunks from the same pinned version -->
		<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@videojs/cdn@10.0.0-rc.2/global.css" integrity="sha384-sFBM9ObyqV3UmK167VmRaSHqUDFMgg7tJukVLDainDgtCn2j5m5t8sgvobK5EqTc" crossorigin="anonymous" />
		<script type="module" src="https://cdn.jsdelivr.net/npm/@videojs/cdn@10.0.0-rc.2/video.js" integrity="sha384-abrZCKrwYhxBXf2wcYgNPCIBaKE/ALYmJyu3fqvAmSNOZq7XZKp/cHQZmS05BPs5" crossorigin="anonymous"></script>
	<?php endif; ?>

	<title><?= e($ogTitle) ?></title>

	<style>
		/* Tokens borrowed from account.nostr.build ("Still Water at Night") */
		:root {
			--cr-foam: #24204b;
			--cr-sand: #2e2961;
			--cr-surface: rgba(70, 63, 147, 0.42);
			--cr-surface-strong: rgba(70, 63, 147, 0.7);
			--cr-line: rgba(168, 163, 219, 0.18);
			--cr-line-strong: rgba(168, 163, 219, 0.34);
			--cr-lagoon: #a8a3db;
			--cr-lagoon-deep: #5a52a8;
			--cr-palm: #b66bd6;
			--cr-ink: #e4e2f3;
			--cr-ink-soft: #bebbe2;
			--cr-ink-mute: #8e89bf;
			--cr-orange: #ffa500;
			--cr-radius: 12px;
			--cr-row-h: 250px;
		}

		.cr-main {
			width: 100%;
			max-width: 1440px;
			margin: 0 auto;
			padding: 24px 16px 48px;
			color: var(--cr-ink);
			/* index.css centers main's children; sections here must span the full width */
			align-items: stretch;
		}

		@media (min-width: 768px) {
			.cr-main {
				padding: 32px 24px 64px;
			}
		}

		.cr-crumbs {
			display: flex;
			align-items: center;
			gap: 8px;
			font-size: 13px;
			color: var(--cr-ink-mute);
			margin-bottom: 20px;
		}

		.cr-crumbs a {
			color: var(--cr-lagoon);
			text-decoration: none;
			display: inline-flex;
			align-items: center;
			gap: 6px;
		}

		.cr-crumbs a:hover {
			color: #fff;
		}

		/* Glass island (hero) */
		.cr-hero {
			position: relative;
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			gap: 20px 24px;
			padding: 22px 24px;
			border-radius: 18px;
			background: var(--cr-surface);
			border: 1px solid var(--cr-line);
			box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08), 0 20px 50px -30px rgba(0, 0, 0, 0.7);
			-webkit-backdrop-filter: blur(8px);
			backdrop-filter: blur(8px);
		}

		.cr-avatar {
			width: 84px;
			height: 84px;
			border-radius: 50%;
			object-fit: cover;
			background: var(--cr-sand);
			border: 2px solid rgba(255, 255, 255, 0.12);
			box-shadow: 0 0 0 4px rgba(168, 163, 219, 0.12);
			flex-shrink: 0;
		}

		.cr-hero__body {
			flex: 1 1 200px;
			min-width: 0;
		}

		@media (max-width: 520px) {
			.cr-hero {
				padding: 18px;
				gap: 16px;
			}

			.cr-avatar {
				width: 64px;
				height: 64px;
			}
		}

		.cr-kicker {
			font-size: 11px;
			font-weight: 700;
			letter-spacing: 0.16em;
			text-transform: uppercase;
			color: var(--cr-ink-mute);
			margin-bottom: 4px;
		}

		.cr-name {
			font-size: clamp(1.5rem, 3.5vw, 2.25rem);
			font-weight: 700;
			line-height: 1.1;
			letter-spacing: -0.02em;
			color: #fff;
			overflow-wrap: anywhere;
		}

		.cr-hero__meta {
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			gap: 8px;
			margin-top: 10px;
			font-size: 13px;
			color: var(--cr-ink-soft);
		}

		.cr-chip {
			display: inline-flex;
			align-items: center;
			gap: 6px;
			padding: 4px 10px;
			border-radius: 999px;
			background: rgba(36, 32, 75, 0.55);
			border: 1px solid var(--cr-line);
			font-size: 12px;
			font-weight: 600;
			color: var(--cr-ink);
			text-decoration: none;
			font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
			cursor: pointer;
			transition: border-color .15s, color .15s;
		}

		.cr-chip:hover {
			border-color: var(--cr-line-strong);
			color: #fff;
		}

		.cr-chip svg {
			width: 14px;
			height: 14px;
			opacity: .8;
		}

		.cr-hero__actions {
			display: flex;
			align-items: center;
			gap: 10px;
			flex-wrap: wrap;
		}

		.cr-btn {
			display: inline-flex;
			align-items: center;
			gap: 8px;
			height: 40px;
			padding: 0 16px;
			border-radius: 10px;
			border: 1px solid transparent;
			font-size: 14px;
			font-weight: 600;
			text-decoration: none;
			cursor: pointer;
			color: #fff;
			background: var(--cr-lagoon-deep);
			transition: transform .12s, filter .15s, background .15s;
		}

		.cr-btn:hover {
			filter: brightness(1.12);
		}

		.cr-btn:active {
			transform: translateY(1px);
		}

		.cr-btn--zap {
			background: linear-gradient(135deg, #f7b733, #ffa500);
			color: #24204b;
		}

		.cr-btn--ghost {
			background: transparent;
			border-color: var(--cr-line-strong);
			color: var(--cr-ink);
		}

		.cr-btn--ghost:hover {
			background: rgba(255, 255, 255, 0.06);
		}

		.cr-btn svg {
			width: 16px;
			height: 16px;
		}

		/* Sticky tab bar */
		.cr-tabs-wrap {
			position: sticky;
			top: 8px;
			z-index: 5;
			margin: 20px 0 16px;
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			justify-content: space-between;
			gap: 10px;
			pointer-events: none;
		}

		.cr-tabs-wrap > * {
			pointer-events: auto;
		}

		.cr-tabs {
			display: inline-flex;
			gap: 4px;
			padding: 4px;
			border-radius: 12px;
			background: rgba(28, 24, 64, 0.82);
			border: 1px solid var(--cr-line);
			box-shadow: 0 10px 30px -18px rgba(0, 0, 0, 0.8);
			-webkit-backdrop-filter: blur(10px);
			backdrop-filter: blur(10px);
		}

		.cr-range {
			padding: 6px 10px;
			border-radius: 8px;
			background: rgba(28, 24, 64, 0.82);
			-webkit-backdrop-filter: blur(10px);
			backdrop-filter: blur(10px);
		}

		.cr-tab {
			display: inline-flex;
			align-items: center;
			gap: 8px;
			height: 34px;
			padding: 0 14px;
			border-radius: 9px;
			font-size: 13px;
			font-weight: 600;
			color: var(--cr-ink-soft);
			text-decoration: none;
			transition: background .15s, color .15s;
		}

		.cr-tab svg {
			width: 15px;
			height: 15px;
			opacity: .75;
		}

		.cr-tab:hover {
			color: #fff;
			background: rgba(255, 255, 255, 0.05);
		}

		.cr-tab[aria-current="page"] {
			color: #fff;
			background: var(--cr-lagoon-deep);
		}

		.cr-tab[aria-current="page"] svg {
			opacity: 1;
		}

		.cr-tab__count {
			font-size: 11px;
			font-weight: 700;
			padding: 1px 7px;
			border-radius: 999px;
			background: rgba(255, 255, 255, 0.1);
			color: var(--cr-ink);
			font-variant-numeric: tabular-nums;
		}

		.cr-tab--empty {
			opacity: .45;
			pointer-events: none;
		}

		@media (max-width: 520px) {
			.cr-tabs-wrap {
				justify-content: center;
				top: 0;
			}

			.cr-tabs {
				display: flex;
				width: 100%;
			}

			.cr-tab {
				flex: 1;
				justify-content: center;
				padding: 0 8px;
				gap: 6px;
				font-size: 12px;
			}

			.cr-tab svg {
				display: none;
			}
		}

		.cr-range {
			font-size: 13px;
			color: var(--cr-ink-mute);
			font-variant-numeric: tabular-nums;
		}

		/* Justified image/video grid: pure CSS flex-grow layout, no cropping, no layout shift */
		.cr-grid {
			display: flex;
			flex-wrap: wrap;
			gap: 6px;
		}

		.cr-grid::after {
			content: "";
			flex-grow: 1000000;
		}

		.cr-tile {
			--r: 1.3333;
			position: relative;
			display: block;
			flex-grow: calc(var(--r) * 100);
			flex-basis: calc(var(--r) * var(--cr-row-h));
			aspect-ratio: var(--r);
			max-width: 100%;
			overflow: hidden;
			border-radius: 10px;
			background: var(--cr-sand);
			text-decoration: none;
			color: inherit;
			outline-offset: 2px;
			isolation: isolate;
		}

		.cr-tile:focus-visible {
			outline: 2px solid var(--cr-lagoon);
		}

		.cr-tile canvas,
		.cr-tile img {
			position: absolute;
			inset: 0;
			width: 100%;
			height: 100%;
			object-fit: cover;
			display: block;
		}

		.cr-tile img {
			opacity: 0;
			transition: opacity .35s ease, transform .5s ease;
		}

		.cr-tile img.is-loaded {
			opacity: 1;
		}

		.cr-tile:hover img.is-loaded {
			transform: scale(1.03);
		}

		.cr-tile__meta {
			position: absolute;
			left: 0;
			right: 0;
			bottom: 0;
			padding: 28px 12px 10px;
			background: linear-gradient(180deg, transparent, rgba(12, 9, 30, 0.85));
			color: #fff;
			font-size: 12px;
			line-height: 1.35;
			opacity: 0;
			transform: translateY(6px);
			transition: opacity .2s, transform .2s;
			pointer-events: none;
		}

		.cr-tile:hover .cr-tile__meta,
		.cr-tile:focus-visible .cr-tile__meta {
			opacity: 1;
			transform: none;
		}

		.cr-tile__title {
			display: block;
			font-weight: 600;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}

		.cr-tile__sub {
			display: block;
			color: rgba(255, 255, 255, 0.72);
			font-variant-numeric: tabular-nums;
		}

		.cr-tile__play {
			position: absolute;
			left: 50%;
			top: 50%;
			width: 56px;
			height: 56px;
			margin: -28px 0 0 -28px;
			border-radius: 50%;
			background: rgba(12, 9, 30, 0.6);
			border: 1px solid rgba(255, 255, 255, 0.25);
			-webkit-backdrop-filter: blur(6px);
			backdrop-filter: blur(6px);
			display: grid;
			place-items: center;
			transition: transform .15s, background .15s;
		}

		.cr-tile__play svg {
			width: 22px;
			height: 22px;
			margin-left: 3px;
			fill: #fff;
		}

		.cr-tile:hover .cr-tile__play {
			transform: scale(1.06);
			background: rgba(90, 82, 168, 0.85);
		}

		.cr-tile__badge {
			position: absolute;
			top: 8px;
			left: 8px;
			padding: 2px 7px;
			border-radius: 6px;
			background: rgba(12, 9, 30, 0.65);
			color: #fff;
			font-size: 10px;
			font-weight: 700;
			letter-spacing: .08em;
			text-transform: uppercase;
		}

		@media (max-width: 640px) {
			:root {
				--cr-row-h: 150px;
			}

			.cr-grid {
				gap: 4px;
			}

			.cr-tile {
				border-radius: 6px;
			}

			.cr-tile__play {
				width: 40px;
				height: 40px;
				margin: -20px 0 0 -20px;
			}
		}

		/* Audio: track list with inline player */
		.cr-tracks {
			display: flex;
			flex-direction: column;
			gap: 8px;
		}

		.cr-track {
			display: grid;
			grid-template-columns: 64px 1fr auto;
			grid-template-areas: "cover body actions" "cover bar bar";
			gap: 4px 14px;
			align-items: center;
			padding: 10px;
			border-radius: var(--cr-radius);
			background: var(--cr-surface);
			border: 1px solid var(--cr-line);
			transition: border-color .15s, background .15s;
		}

		.cr-track:hover,
		.cr-track.is-playing {
			border-color: var(--cr-line-strong);
			background: var(--cr-surface-strong);
		}

		.cr-track__cover {
			grid-area: cover;
			position: relative;
			width: 64px;
			height: 64px;
			border-radius: 8px;
			overflow: hidden;
			background: var(--cr-sand);
			border: 0;
			padding: 0;
			cursor: pointer;
		}

		.cr-track__cover img {
			width: 100%;
			height: 100%;
			object-fit: cover;
			display: block;
		}

		.cr-track__cover .cr-play-glyph {
			position: absolute;
			inset: 0;
			display: grid;
			place-items: center;
			background: rgba(12, 9, 30, 0.45);
			opacity: 0;
			transition: opacity .15s;
		}

		.cr-track__cover:hover .cr-play-glyph,
		.cr-track.is-playing .cr-play-glyph {
			opacity: 1;
		}

		.cr-play-glyph svg {
			width: 22px;
			height: 22px;
			fill: #fff;
		}

		.cr-track__body {
			grid-area: body;
			min-width: 0;
		}

		.cr-track__title {
			font-size: 14px;
			font-weight: 600;
			color: #fff;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}

		.cr-track__sub {
			font-size: 12px;
			color: var(--cr-ink-mute);
			margin-top: 2px;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}

		.cr-track__actions {
			grid-area: actions;
			display: flex;
			gap: 6px;
		}

		.cr-icon-btn {
			width: 34px;
			height: 34px;
			display: grid;
			place-items: center;
			border-radius: 8px;
			border: 1px solid var(--cr-line);
			background: rgba(36, 32, 75, 0.5);
			color: var(--cr-ink-soft);
			cursor: pointer;
			text-decoration: none;
		}

		.cr-icon-btn:hover {
			color: #fff;
			border-color: var(--cr-line-strong);
		}

		.cr-icon-btn svg {
			width: 16px;
			height: 16px;
		}

		.cr-track__bar {
			grid-area: bar;
			display: flex;
			align-items: center;
			gap: 10px;
			font-size: 11px;
			color: var(--cr-ink-mute);
			font-variant-numeric: tabular-nums;
		}

		.cr-track__seek {
			-webkit-appearance: none;
			appearance: none;
			flex: 1;
			height: 4px;
			border-radius: 999px;
			background: linear-gradient(90deg, var(--cr-lagoon) var(--p, 0%), rgba(255, 255, 255, 0.14) var(--p, 0%));
			outline: none;
			cursor: pointer;
		}

		.cr-track__seek::-webkit-slider-thumb {
			-webkit-appearance: none;
			width: 12px;
			height: 12px;
			border-radius: 50%;
			background: #fff;
			border: 0;
			box-shadow: 0 0 0 3px rgba(168, 163, 219, 0.25);
		}

		.cr-track__seek::-moz-range-thumb {
			width: 12px;
			height: 12px;
			border-radius: 50%;
			background: #fff;
			border: 0;
		}

		@media (max-width: 520px) {
			.cr-track {
				grid-template-columns: 56px 1fr;
				grid-template-areas: "cover body" "bar bar";
			}

			.cr-track__cover {
				width: 56px;
				height: 56px;
			}

			.cr-track__actions {
				display: none;
			}
		}

		/* Pagination */
		.cr-pager {
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 6px;
			margin-top: 28px;
			flex-wrap: wrap;
		}

		.cr-pager a,
		.cr-pager span {
			min-width: 36px;
			height: 36px;
			padding: 0 12px;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			border-radius: 9px;
			font-size: 13px;
			font-weight: 600;
			text-decoration: none;
			color: var(--cr-ink-soft);
			border: 1px solid var(--cr-line);
			background: rgba(36, 32, 75, 0.5);
			font-variant-numeric: tabular-nums;
		}

		.cr-pager a:hover {
			color: #fff;
			border-color: var(--cr-line-strong);
		}

		.cr-pager [aria-current="page"] {
			color: #fff;
			background: var(--cr-lagoon-deep);
			border-color: transparent;
		}

		.cr-pager .cr-pager__gap {
			border: 0;
			background: transparent;
			min-width: 20px;
			padding: 0;
		}

		/* Empty / not found */
		.cr-empty {
			text-align: center;
			padding: 64px 16px;
			border-radius: 18px;
			border: 1px dashed var(--cr-line-strong);
			color: var(--cr-ink-soft);
		}

		.cr-empty h2 {
			color: #fff;
			font-size: 20px;
			font-weight: 700;
			margin-bottom: 8px;
		}

		.cr-empty p {
			font-size: 14px;
			max-width: 46ch;
			margin: 0 auto 20px;
		}

		/* Toast */
		.cr-toast {
			position: fixed;
			left: 50%;
			bottom: 24px;
			transform: translate(-50%, 12px);
			padding: 10px 16px;
			border-radius: 10px;
			background: #e4e2f3;
			color: #24204b;
			font-size: 13px;
			font-weight: 600;
			opacity: 0;
			pointer-events: none;
			transition: opacity .2s, transform .2s;
			z-index: 100000;
		}

		.cr-toast.is-visible {
			opacity: 1;
			transform: translate(-50%, 0);
		}


		/* Zap modal (ported from the globe zap flow) */
		.cr-zap {
			/* index.css zeroes every margin, which kills the UA's dialog centering; restore it */
			position: fixed;
			inset: 0;
			margin: auto;
			width: min(440px, calc(100vw - 32px));
			max-height: calc(100dvh - 32px);
			padding: 0;
			border: 1px solid var(--cr-line-strong);
			border-radius: 18px;
			background: #1c1840;
			color: var(--cr-ink);
			box-shadow: 0 30px 80px -30px rgba(0, 0, 0, 0.9);
			overflow: hidden;
		}

		.cr-zap::backdrop {
			background: rgba(8, 6, 20, 0.7);
			-webkit-backdrop-filter: blur(4px);
			backdrop-filter: blur(4px);
		}

		.cr-zap__head {
			display: flex;
			align-items: center;
			gap: 12px;
			padding: 16px 16px 12px;
			border-bottom: 1px solid var(--cr-line);
		}

		.cr-zap__head img {
			width: 40px;
			height: 40px;
			border-radius: 50%;
			object-fit: cover;
			background: var(--cr-sand);
		}

		.cr-zap__who {
			flex: 1;
			min-width: 0;
		}

		.cr-zap__title {
			font-size: 15px;
			font-weight: 700;
			color: #fff;
		}

		.cr-zap__lud16 {
			font-size: 12px;
			color: var(--cr-ink-mute);
			font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}

		.cr-zap__body {
			display: flex;
			flex-direction: column;
			gap: 12px;
			padding: 16px;
			overflow-y: auto;
			max-height: calc(100dvh - 32px - 70px);
		}

		.cr-zap__presets {
			display: grid;
			grid-template-columns: repeat(4, 1fr);
			gap: 8px;
		}

		.cr-zap__preset {
			height: 44px;
			border-radius: 10px;
			border: 1px solid var(--cr-line);
			background: rgba(36, 32, 75, 0.6);
			color: var(--cr-ink);
			font-size: 14px;
			font-weight: 700;
			cursor: pointer;
			font-variant-numeric: tabular-nums;
		}

		.cr-zap__preset[aria-pressed="true"] {
			background: var(--cr-lagoon-deep);
			border-color: transparent;
			color: #fff;
		}

		.cr-zap label {
			display: block;
			font-size: 12px;
			font-weight: 600;
			color: var(--cr-ink-soft);
			margin-bottom: 6px;
		}

		.cr-zap input,
		.cr-zap textarea,
		.cr-zap select {
			width: 100%;
			border-radius: 10px;
			border: 1px solid var(--cr-line);
			background: var(--cr-foam);
			color: var(--cr-ink);
			font-size: 14px;
			padding: 10px 12px;
			outline: none;
			font-family: inherit;
		}

		.cr-zap input:focus,
		.cr-zap textarea:focus,
		.cr-zap select:focus {
			border-color: var(--cr-lagoon);
		}

		.cr-zap textarea {
			resize: vertical;
			min-height: 64px;
		}

		.cr-zap__error {
			display: none;
			padding: 10px 12px;
			border-radius: 10px;
			background: rgba(242, 107, 119, 0.14);
			border: 1px solid rgba(242, 107, 119, 0.4);
			color: #ffb3ba;
			font-size: 13px;
		}

		.cr-zap__error:not(:empty) {
			display: block;
		}

		.cr-zap__pay {
			width: 100%;
			height: 48px;
			border-radius: 999px;
			border: 0;
			background: linear-gradient(135deg, #f7b733, #ffa500);
			color: #24204b;
			font-size: 15px;
			font-weight: 700;
			cursor: pointer;
		}

		.cr-zap__pay:disabled {
			opacity: .5;
			cursor: not-allowed;
		}

		.cr-zap__hint {
			font-size: 11px;
			color: var(--cr-ink-mute);
			text-align: center;
			line-height: 1.4;
		}

		.cr-zap__qr {
			display: block;
			width: 240px;
			height: 240px;
			margin: 0 auto;
			padding: 10px;
			border-radius: 14px;
			background: #fff;
		}

		.cr-zap__qr svg {
			display: block;
			width: 100%;
			height: 100%;
		}

		.cr-zap__row {
			display: flex;
			gap: 8px;
		}

		.cr-zap__row > * {
			flex: 1;
		}

		.cr-zap .cr-btn {
			justify-content: center;
		}

		.cr-zap__paid {
			text-align: center;
			padding: 24px 0 8px;
		}

		.cr-zap__paid strong {
			display: block;
			font-size: 22px;
			color: #fff;
			margin-bottom: 6px;
		}

		.cr-zap__close {
			width: 34px;
			height: 34px;
			display: grid;
			place-items: center;
			border-radius: 8px;
			border: 1px solid var(--cr-line);
			background: transparent;
			color: var(--cr-ink-soft);
			cursor: pointer;
			font-size: 18px;
			line-height: 1;
		}

		.cr-zap [hidden] {
			display: none !important;
		}

		@media (max-width: 520px) {
			.cr-zap {
				width: 100vw;
				max-width: 100vw;
				max-height: calc(100dvh - 24px);
				margin: auto 0 0;
				border-radius: 18px 18px 0 0;
				border-bottom: 0;
			}

			.cr-zap__body {
				max-height: calc(100dvh - 24px - 70px);
				padding-bottom: max(16px, env(safe-area-inset-bottom));
			}
		}

		/* PhotoSwipe theming */
		.pswp {
			--pswp-bg: #0c091e;
			--pswp-placeholder-bg: #2e2961;
			--pswp-icon-color: #fff;
			--pswp-icon-color-secondary: #a8a3db;
		}

		.pswp__custom-caption {
			position: absolute;
			left: 50%;
			bottom: 18px;
			transform: translateX(-50%);
			max-width: min(720px, calc(100% - 32px));
			padding: 10px 16px;
			border-radius: 12px;
			background: rgba(36, 32, 75, 0.78);
			border: 1px solid rgba(168, 163, 219, 0.25);
			-webkit-backdrop-filter: blur(10px);
			backdrop-filter: blur(10px);
			color: #e4e2f3;
			font-size: 13px;
			line-height: 1.45;
			text-align: center;
			pointer-events: none;
			transition: opacity .2s;
		}

		.pswp__custom-caption:empty,
		.pswp--ui-hidden .pswp__custom-caption {
			opacity: 0;
		}

		.pswp__custom-caption strong {
			display: block;
			color: #fff;
			font-weight: 600;
		}

		.pswp__custom-caption small {
			color: #bebbe2;
			font-size: 12px;
		}

		.pswp__button--open {
			display: grid;
			place-items: center;
		}

		.pswp__button--open svg {
			width: 22px;
			height: 22px;
			fill: none;
			stroke: #fff;
			stroke-width: 2;
			stroke-linecap: round;
			stroke-linejoin: round;
		}

		/* video.js 10 inside a PhotoSwipe slide: the skin owns layout, we own the box */
		.cr-pswp-video {
			position: absolute;
			left: 0;
			top: 0;
			background: #000;
			border-radius: 8px;
			overflow: hidden;
		}

		.cr-pswp-video video-skin {
			--media-accent-color: #a8a3db;
			--media-accent-text-color: #24204b;
			--media-border-color: transparent;
			--media-border-radius: 8px;
			--media-font-family: "Inter", ui-sans-serif, system-ui, sans-serif;
			--media-object-fit: contain;
			display: block;
			width: 100%;
			height: 100%;
		}

		.cr-pswp-video media-container {
			width: 100%;
			height: 100%;
		}
	</style>
</head>

<body>
	<header class="header">
		<?php include $_SERVER['DOCUMENT_ROOT'] . '/components/mainnav.php'; ?>
	</header>

	<main class="cr-main">
		<nav class="cr-crumbs" aria-label="Breadcrumb">
			<a href="/creators">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6" /></svg>
				Creators
			</a>
			<span aria-hidden="true">/</span>
			<span><?= e($nym) ?></span>
		</nav>

		<?php if (!empty($notFound)) : ?>
			<section class="cr-empty">
				<h2>Creator not found</h2>
				<p>This creator page does not exist or the account is no longer active.</p>
				<a class="cr-btn" href="/creators">Browse creators</a>
			</section>
		<?php else : ?>

			<section class="cr-hero">
				<img class="cr-avatar" src="<?= e($creatorPpic) ?>" alt="" width="84" height="84" loading="eager" decoding="async"
					onerror="this.onerror=null;this.src='<?= CR_FALLBACK_PPIC ?>'">
				<div class="cr-hero__body">
					<div class="cr-kicker">Creator</div>
					<h1 class="cr-name"><?= e($nym) ?></h1>
					<div class="cr-hero__meta">
						<span><?= e($summary) ?></span>
						<?php if ($npub) : ?>
							<button type="button" class="cr-chip" data-copy="<?= e($npub) ?>" title="Copy npub">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" /><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" /></svg>
								<?= e($npubShort) ?>
							</button>
						<?php endif; ?>
					</div>
				</div>
				<div class="cr-hero__actions">
					<?php if ($wallet) : ?>
						<a class="cr-btn cr-btn--zap" id="cr-zap-open" href="lightning:<?= e($wallet) ?>" title="<?= e($wallet) ?>">
							<svg viewBox="0 0 24 24" fill="currentColor"><path d="M13 2 3 14h7l-1 8 10-12h-7l1-8z" /></svg>
							Zap
						</a>
						<button type="button" class="cr-btn cr-btn--ghost" data-copy="<?= e($wallet) ?>" title="Copy lightning address">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" /><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" /></svg>
							Copy address
						</button>
					<?php endif; ?>
					<?php if ($npub) : ?>
						<a class="cr-btn cr-btn--ghost" href="https://jumble.social/users/<?= e($npub) ?>" target="_blank" rel="noopener">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" /><path d="M15 3h6v6" /><path d="M10 14 21 3" /></svg>
							Nostr profile
						</a>
					<?php endif; ?>
				</div>
			</section>

			<?php if (array_sum($counts) === 0) : ?>
				<section class="cr-empty" style="margin-top:24px">
					<h2>Nothing shared yet</h2>
					<p><?= e($nym) ?> has not shared any media to their creator page.</p>
					<a class="cr-btn" href="/creators">Browse other creators</a>
				</section>
			<?php else : ?>

				<div class="cr-tabs-wrap">
					<nav class="cr-tabs" aria-label="Media type">
						<?php
						$tabIcons = [
							'image' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>',
							'video' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 8-6 4 6 4V8Z"/><rect x="2" y="6" width="14" height="12" rx="2"/></svg>',
							'audio' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>',
						];
						$tabLabels = ['image' => 'Images', 'video' => 'Videos', 'audio' => 'Audio'];
						foreach (CR_TYPES as $type) :
							$isActive = $type === $display;
							$isEmpty = $counts[$type] === 0;
						?>
							<a class="cr-tab<?= $isEmpty ? ' cr-tab--empty' : '' ?>" href="<?= e(cr_page_url($userId, $type, 1)) ?>" <?= $isActive ? 'aria-current="page"' : '' ?> <?= $isEmpty ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
								<?= $tabIcons[$type] ?>
								<?= $tabLabels[$type] ?>
								<span class="cr-tab__count"><?= number_format($counts[$type]) ?></span>
							</a>
						<?php endforeach; ?>
					</nav>
					<?php if ($total > CR_PER_PAGE) : ?>
						<div class="cr-range">Showing <?= number_format($firstOfPage) ?>–<?= number_format($lastOfPage) ?> of <?= number_format($total) ?></div>
					<?php endif; ?>
				</div>

				<?php if ($display === 'audio') : ?>
					<section class="cr-tracks" id="cr-tracks">
						<?php foreach ($rows as $row) :
							$m = cr_media($row);
							$title = trim((string) $row['title']) ?: $m['filename'];
							$sub = array_filter([strtoupper(pathinfo($m['filename'], PATHINFO_EXTENSION)), cr_fmt_bytes($row['file_size']), cr_fmt_date($row['created_at'])]);
						?>
							<article class="cr-track" data-src="<?= e($m['full']) ?>" data-type="<?= e($row['mime_type']) ?>">
								<button type="button" class="cr-track__cover" data-toggle aria-label="Play <?= e($title) ?>">
									<img src="<?= e($m['poster']) ?>" alt="" loading="lazy" decoding="async" width="64" height="64">
									<span class="cr-play-glyph"><svg viewBox="0 0 24 24" data-glyph><path d="M8 5v14l11-7z" /></svg></span>
								</button>
								<div class="cr-track__body">
									<div class="cr-track__title" title="<?= e($title) ?>"><?= e($title) ?></div>
									<div class="cr-track__sub"><?= e(implode(' · ', $sub)) ?></div>
								</div>
								<div class="cr-track__actions">
									<a class="cr-icon-btn" href="<?= e($m['embed']) ?>" target="_blank" rel="noopener" title="Open player page">
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" /><path d="M15 3h6v6" /><path d="M10 14 21 3" /></svg>
									</a>
									<button type="button" class="cr-icon-btn" data-copy="<?= e($m['full']) ?>" title="Copy link">
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" /><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" /></svg>
									</button>
								</div>
								<div class="cr-track__bar">
									<span data-cur>0:00</span>
									<input class="cr-track__seek" type="range" min="0" max="1000" value="0" step="1" aria-label="Seek" data-seek>
									<span data-dur>–:––</span>
								</div>
							</article>
						<?php endforeach; ?>
					</section>

				<?php else : ?>
					<section class="cr-grid" id="cr-gallery">
						<?php foreach ($rows as $row) :
							$m = cr_media($row);
							$w = (int) $row['media_width'];
							$h = (int) $row['media_height'];
							$hasDims = $w > 0 && $h > 0;
							if (!$hasDims) {
								$w = $display === 'video' ? 1920 : 0;
								$h = $display === 'video' ? 1080 : 0;
							}
							$ratio = ($w > 0 && $h > 0) ? $w / $h : 4 / 3;
							$layoutRatio = max(0.45, min(3.0, $ratio));
							$title = trim((string) $row['title']) ?: $m['filename'];
							$sub = array_filter([$hasDims ? "{$w}×{$h}" : '', cr_fmt_bytes($row['file_size']), cr_fmt_date($row['created_at'])]);
							$isVideo = $display === 'video';
							$thumbSrc = $isVideo ? $m['poster'] : $m['thumb'];
						?>
							<a class="cr-tile" href="<?= e($m['full']) ?>" target="_blank" rel="noopener"
								style="--r:<?= number_format($layoutRatio, 4, '.', '') ?>"
								<?php if ($isVideo) : ?>
								data-pswp-type="video" data-pswp-video-src="<?= e($m['full']) ?>" data-pswp-mime="<?= e($row['mime_type']) ?>"
								<?php else : ?>
								data-pswp-src="<?= e($m['full']) ?>" <?= $m['srcset'] ? 'data-pswp-srcset="' . e($m['srcset']) . '"' : '' ?>
								<?php endif; ?>
								<?php if ($w > 0 && $h > 0) : ?>data-pswp-width="<?= $w ?>" data-pswp-height="<?= $h ?>" <?php endif; ?>
								<?php if (!empty($row['blurhash'])) : ?>data-blurhash="<?= e($row['blurhash']) ?>" <?php endif; ?>
								data-open-url="<?= e($m['full']) ?>">
								<canvas class="cr-ph" width="32" height="32" aria-hidden="true"></canvas>
								<img src="<?= e($thumbSrc) ?>" <?= (!$isVideo && $m['srcset']) ? 'srcset="' . e($m['srcset']) . '" sizes="(max-width: 640px) 50vw, (max-width: 1100px) 33vw, 25vw"' : '' ?>
									alt="<?= e($title) ?>" loading="lazy" decoding="async" <?= $hasDims ? "width=\"{$w}\" height=\"{$h}\"" : '' ?>>
								<?php if ($isVideo) : ?>
									<span class="cr-tile__badge">Video</span>
									<span class="cr-tile__play" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z" /></svg></span>
								<?php endif; ?>
								<span class="cr-tile__meta">
									<span class="cr-tile__title"><?= e($title) ?></span>
									<?php if ($sub) : ?><span class="cr-tile__sub"><?= e(implode(' · ', $sub)) ?></span><?php endif; ?>
								</span>
								<span class="cr-caption" hidden>
									<strong><?= e($title) ?></strong>
									<?php if (!empty($row['description'])) : ?><span><?= e($row['description']) ?></span><?php endif; ?>
									<?php if ($sub) : ?><small><?= e(implode(' · ', $sub)) ?></small><?php endif; ?>
								</span>
							</a>
						<?php endforeach; ?>
					</section>
				<?php endif; ?>

				<?php if ($pages > 1) :
					// Compact window: 1 … p-1 p p+1 … N
					$links = [];
					for ($i = 1; $i <= $pages; $i++) {
						if ($i === 1 || $i === $pages || abs($i - $page) <= 1) $links[] = $i;
					}
				?>
					<nav class="cr-pager" aria-label="Pagination">
						<?php if ($page > 1) : ?><a href="<?= e(cr_page_url($userId, $display, $page - 1)) ?>" rel="prev">‹</a><?php endif; ?>
						<?php $prev = 0;
						foreach ($links as $i) : ?>
							<?php if ($i - $prev > 1) : ?><span class="cr-pager__gap">…</span><?php endif; ?>
							<?php if ($i === $page) : ?>
								<span aria-current="page"><?= $i ?></span>
							<?php else : ?>
								<a href="<?= e(cr_page_url($userId, $display, $i)) ?>"><?= $i ?></a>
							<?php endif; ?>
							<?php $prev = $i; ?>
						<?php endforeach; ?>
						<?php if ($page < $pages) : ?><a href="<?= e(cr_page_url($userId, $display, $page + 1)) ?>" rel="next">›</a><?php endif; ?>
					</nav>
				<?php endif; ?>

			<?php endif; ?>
		<?php endif; ?>
	</main>

	<?php if (empty($notFound) && $wallet) : ?>
		<dialog class="cr-zap" id="cr-zap" aria-labelledby="cr-zap-title">
			<div class="cr-zap__head">
				<img src="<?= e($creatorPpic) ?>" alt="" width="40" height="40" onerror="this.onerror=null;this.src='<?= CR_FALLBACK_PPIC ?>'">
				<div class="cr-zap__who">
					<div class="cr-zap__title" id="cr-zap-title">Zap <?= e($nym) ?></div>
					<div class="cr-zap__lud16"><?= e($wallet) ?></div>
				</div>
				<button type="button" class="cr-zap__close" data-zap-close aria-label="Close">×</button>
			</div>
			<div class="cr-zap__body">
				<div data-zap-step="compose">
					<div class="cr-zap__presets" data-zap-presets>
						<button type="button" class="cr-zap__preset" data-sats="21" aria-pressed="true">21</button>
						<button type="button" class="cr-zap__preset" data-sats="100">100</button>
						<button type="button" class="cr-zap__preset" data-sats="500">500</button>
						<button type="button" class="cr-zap__preset" data-sats="1000">1k</button>
					</div>
					<div style="margin-top:12px">
						<label for="cr-zap-custom">Custom amount (sats)</label>
						<input id="cr-zap-custom" type="number" inputmode="numeric" min="1" step="1" placeholder="e.g. 2100" data-zap-custom>
					</div>
					<div style="margin-top:12px" data-zap-comment-wrap hidden>
						<label for="cr-zap-comment">Comment (optional)</label>
						<textarea id="cr-zap-comment" maxlength="280" placeholder="Say something nice" data-zap-comment></textarea>
					</div>
					<div class="cr-zap__error" style="margin-top:12px" data-zap-error></div>
					<button type="button" class="cr-zap__pay" style="margin-top:12px" data-zap-pay>⚡ Zap 21 sats</button>
					<p class="cr-zap__hint" style="margin-top:10px" data-zap-hint></p>
				</div>
				<div data-zap-step="invoice" hidden>
					<div class="cr-zap__qr" data-zap-qr></div>
					<p class="cr-zap__hint" style="margin-top:10px">Scan with any Lightning wallet, or open it below.</p>
					<div style="margin-top:12px">
						<label for="cr-zap-wallet">Wallet</label>
						<select id="cr-zap-wallet" data-zap-wallet></select>
					</div>
					<button type="button" class="cr-zap__pay" style="margin-top:12px" data-zap-webln hidden>⚡ Pay with browser wallet</button>
					<div class="cr-zap__row" style="margin-top:10px">
						<a class="cr-btn cr-btn--zap" href="#" data-zap-deeplink>Open in wallet</a>
						<button type="button" class="cr-btn cr-btn--ghost" data-zap-copy>Copy invoice</button>
					</div>
					<div class="cr-zap__error" style="margin-top:12px" data-zap-error2></div>
					<button type="button" class="cr-btn cr-btn--ghost" style="width:100%;margin-top:12px" data-zap-close>Done</button>
				</div>
				<div data-zap-step="paid" hidden>
					<div class="cr-zap__paid">
						<strong>Zapped ⚡</strong>
						<span data-zap-paid-text></span>
					</div>
					<button type="button" class="cr-btn cr-btn--ghost" style="width:100%;margin-top:12px" data-zap-close>Close</button>
				</div>
			</div>
		</dialog>
	<?php endif; ?>

	<div class="cr-toast" id="cr-toast" role="status" aria-live="polite"></div>

	<?php include $_SERVER['DOCUMENT_ROOT'] . '/components/footer.php'; ?>

	<script type="module">
		import { decode as decodeBlurhash } from 'https://cdn.jsdelivr.net/npm/blurhash@2.0.5/dist/index.mjs';

		const DISPLAY = <?= json_encode($display) ?>;

		/* Toast + copy buttons */
		const toast = document.getElementById('cr-toast');
		let toastTimer;
		function showToast(msg) {
			toast.textContent = msg;
			toast.classList.add('is-visible');
			clearTimeout(toastTimer);
			toastTimer = setTimeout(() => toast.classList.remove('is-visible'), 1800);
		}
		document.addEventListener('click', async (ev) => {
			const btn = ev.target.closest('[data-copy]');
			if (!btn) return;
			ev.preventDefault();
			try {
				await navigator.clipboard.writeText(btn.dataset.copy);
				showToast('Copied');
			} catch {
				showToast('Copy failed');
			}
		});

		/* Blurhash placeholders + fade-in */
		for (const tile of document.querySelectorAll('.cr-tile')) {
			const img = tile.querySelector('img');
			const canvas = tile.querySelector('canvas');
			const hash = tile.dataset.blurhash;
			if (hash && canvas && !(img.complete && img.naturalWidth)) {
				try {
					const px = decodeBlurhash(hash, 32, 32);
					const ctx = canvas.getContext('2d');
					const data = ctx.createImageData(32, 32);
					data.data.set(px);
					ctx.putImageData(data, 0, 0);
				} catch { /* bad hash: keep flat placeholder */ }
			}
			const reveal = () => img.classList.add('is-loaded');
			if (img.complete && img.naturalWidth) reveal();
			else img.addEventListener('load', reveal, { once: true });
			if (tile.dataset.pswpType === 'video') {
				// A poster can still be generating right after upload; re-fetch a few times, then give up quietly
				img.addEventListener('error', () => {
					const n = (+img.dataset.retry || 0) + 1;
					if (n > 3) return;
					img.dataset.retry = n;
					const base = img.src.split('?')[0];
					setTimeout(() => { img.src = `${base}?_r=${n}`; }, 2000 * n);
				});
			}
		}

		/* Lightbox (images + videos) */
		const gallery = document.getElementById('cr-gallery');
		if (gallery) {
			const { default: PhotoSwipeLightbox } = await import('https://cdn.jsdelivr.net/npm/photoswipe@5.4.4/dist/photoswipe-lightbox.esm.min.js');
			const lightbox = new PhotoSwipeLightbox({
				gallery: '#cr-gallery',
				children: 'a.cr-tile',
				pswpModule: () => import('https://cdn.jsdelivr.net/npm/photoswipe@5.4.4/dist/photoswipe.esm.min.js'),
				bgOpacity: 0.96,
				padding: { top: 24, bottom: 72, left: 16, right: 16 },
				wheelToZoom: true,
				closeTitle: 'Close (Esc)',
				zoomTitle: 'Zoom',
				arrowPrevTitle: 'Previous',
				arrowNextTitle: 'Next',
			});

			// Images without stored dimensions: derive from the loaded thumbnail (aspect is what matters)
			lightbox.addFilter('domItemData', (itemData, element) => {
				if (!itemData.width || !itemData.height) {
					const img = element.querySelector('img');
					if (img && img.naturalWidth) {
						itemData.width = itemData.w = img.naturalWidth * 4;
						itemData.height = itemData.h = img.naturalHeight * 4;
					}
				}
				if (element.dataset.pswpType === 'video') {
					itemData.type = 'video';
					itemData.videoSrc = element.dataset.pswpVideoSrc;
					itemData.videoMime = element.dataset.pswpMime || '';
				}
				return itemData;
			});

			// Caption + "open original" toolbar button
			lightbox.on('uiRegister', () => {
				const pswp = lightbox.pswp;
				pswp.ui.registerElement({
					name: 'open',
					order: 8,
					isButton: true,
					tagName: 'a',
					title: 'Open original in new tab',
					html: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/></svg>',
					onInit: (el) => {
						el.setAttribute('target', '_blank');
						el.setAttribute('rel', 'noopener');
						pswp.on('change', () => {
							el.href = pswp.currSlide?.data?.element?.dataset.openUrl || '#';
						});
					},
				});
				pswp.ui.registerElement({
					name: 'custom-caption',
					order: 9,
					isButton: false,
					appendTo: 'root',
					onInit: (el) => {
						pswp.on('change', () => {
							const cap = pswp.currSlide?.data?.element?.querySelector('.cr-caption');
							el.innerHTML = cap ? cap.innerHTML : '';
						});
					},
				});
			});

			/* Video slides: video.js mounted into a custom PhotoSwipe content element */
			const isVideo = (c) => c && c.data && c.data.type === 'video';

			lightbox.addFilter('isContentZoomable', (z, c) => isVideo(c) ? false : z);
			lightbox.addFilter('isKeepingPlaceholder', (k, c) => isVideo(c) ? false : k);
			lightbox.addFilter('useContentPlaceholder', (u, c) => isVideo(c) ? true : u);

			lightbox.on('contentLoad', (e) => {
				const { content } = e;
				if (!isVideo(content)) return;
				e.preventDefault();
				if (content.element) return;
				content.state = 'loading';
				content.type = 'video';
				const wrap = document.createElement('div');
				wrap.className = 'cr-pswp-video';
				const player = document.createElement('video-player');
				if (content.data.msrc) player.setAttribute('poster', content.data.msrc);
				const skin = document.createElement('video-skin');
				const video = document.createElement('video');
				video.setAttribute('playsinline', '');
				video.setAttribute('preload', 'metadata');
				video.src = content.data.videoSrc;
				skin.appendChild(video);
				player.appendChild(skin);
				wrap.appendChild(player);
				// The skin binds Space/arrows to playback while it has focus; hand Escape (and arrows off
				// the seek slider) back to the lightbox
				wrap.addEventListener('keydown', (ev) => {
					const pswp = lightbox.pswp;
					if (!pswp) return;
					const onSlider = ev.target.closest && ev.target.closest('[role="slider"]');
					if (ev.key === 'Escape') { ev.preventDefault(); ev.stopPropagation(); pswp.close(); }
					else if (!onSlider && ev.key === 'ArrowRight') { ev.preventDefault(); ev.stopPropagation(); pswp.next(); }
					else if (!onSlider && ev.key === 'ArrowLeft') { ev.preventDefault(); ev.stopPropagation(); pswp.prev(); }
				}, true);
				content._video = video;
				content.element = wrap;
				// Poster preload drives the "loaded" state, like the official video plugin does
				const img = new Image();
				img.onload = img.onerror = () => content.onLoaded();
				img.src = content.data.msrc || '';
				if (img.complete) content.onLoaded();
			});

			lightbox.on('contentAppend', (e) => {
				if (!isVideo(e.content)) return;
				e.preventDefault();
				e.content.isAttached = true;
				e.content.appendImage();
			});

			lightbox.on('contentResize', (e) => {
				if (!isVideo(e.content)) return;
				e.preventDefault();
				const { content, width, height } = e;
				if (content.element) {
					content.element.style.width = width + 'px';
					content.element.style.height = height + 'px';
				}
				if (content.slide && content.slide.placeholder) {
					const s = content.slide.placeholder.element.style;
					s.transform = 'none';
					s.width = width + 'px';
					s.height = height + 'px';
				}
			});

			lightbox.on('contentActivate', ({ content }) => {
				if (isVideo(content) && content._video) content._video.play()?.catch(() => { });
			});
			lightbox.on('contentDeactivate', ({ content }) => {
				if (isVideo(content) && content._video) content._video.pause();
			});
			lightbox.on('contentDestroy', ({ content }) => {
				if (!isVideo(content) || !content._video) return;
				// Stop the download too; the custom elements clean themselves up once detached
				content._video.pause();
				content._video.removeAttribute('src');
				content._video.load();
				content._video = null;
			});

			lightbox.on('init', () => {
				const pswp = lightbox.pswp;
				// Leave the player's controls to video.js; PhotoSwipe must not treat them as drag/tap
				pswp.on('pointerDown', (e) => {
					const t = e.originalEvent?.target;
					if (t && t.closest && t.closest('media-controls, media-play-button, media-menu, media-popover, media-dialog, [role="slider"], button')) e.preventDefault();
				});
				// A tap on the player belongs to the skin (play/controls), not to PhotoSwipe's UI toggle
				pswp.on('tapAction', (e) => {
					const t = e.originalEvent?.target;
					if (t && t.closest && t.closest('.cr-pswp-video')) e.preventDefault();
				});
				pswp.on('appendHeavy', (e) => {
					if (isVideo(e.slide) && !e.slide.isActive) e.preventDefault();
				});
				pswp.on('close', () => {
					if (isVideo(pswp.currSlide?.content)) {
						pswp.options.showHideAnimationType = 'fade';
						pswp.currSlide.content._video?.pause();
					}
				});
			});

			lightbox.init();
		}

		/* Audio: one shared element, per-row controls */
		const tracks = document.getElementById('cr-tracks');
		if (tracks) {
			const audio = new Audio();
			audio.preload = 'metadata';
			let current = null;
			const PLAY = 'M8 5v14l11-7z';
			const PAUSE = 'M6 5h4v14H6zM14 5h4v14h-4z';
			const fmt = (s) => {
				if (!isFinite(s)) return '–:––';
				s = Math.floor(s);
				const m = Math.floor(s / 60), r = s % 60;
				return `${m}:${r < 10 ? '0' : ''}${r}`;
			};
			const setGlyph = (row, playing) => {
				row.querySelector('[data-glyph] path').setAttribute('d', playing ? PAUSE : PLAY);
				row.classList.toggle('is-playing', playing);
			};

			function select(row) {
				if (current === row) return;
				if (current) {
					setGlyph(current, false);
					current.querySelector('[data-seek]').value = 0;
					current.querySelector('[data-seek]').style.setProperty('--p', '0%');
					current.querySelector('[data-cur]').textContent = '0:00';
				}
				current = row;
				audio.src = row.dataset.src;
			}

			tracks.addEventListener('click', (ev) => {
				const btn = ev.target.closest('[data-toggle]');
				if (!btn) return;
				const row = btn.closest('.cr-track');
				if (current !== row) {
					select(row);
					audio.play().catch(() => showToast('Playback failed'));
				} else if (audio.paused) {
					audio.play().catch(() => showToast('Playback failed'));
				} else {
					audio.pause();
				}
			});

			tracks.addEventListener('input', (ev) => {
				const seek = ev.target.closest('[data-seek]');
				if (!seek) return;
				const row = seek.closest('.cr-track');
				if (current !== row) select(row);
				if (isFinite(audio.duration)) {
					audio.currentTime = (seek.value / 1000) * audio.duration;
				}
			});

			audio.addEventListener('play', () => current && setGlyph(current, true));
			audio.addEventListener('pause', () => current && setGlyph(current, false));
			audio.addEventListener('ended', () => {
				if (!current) return;
				setGlyph(current, false);
				const next = current.nextElementSibling;
				if (next && next.classList.contains('cr-track')) {
					select(next);
					audio.play().catch(() => { });
				}
			});
			audio.addEventListener('loadedmetadata', () => {
				if (current) current.querySelector('[data-dur]').textContent = fmt(audio.duration);
			});
			audio.addEventListener('timeupdate', () => {
				if (!current || !isFinite(audio.duration)) return;
				const seek = current.querySelector('[data-seek]');
				const pct = (audio.currentTime / audio.duration) * 100;
				seek.value = Math.round(pct * 10);
				seek.style.setProperty('--p', pct + '%');
				current.querySelector('[data-cur]').textContent = fmt(audio.currentTime);
			});
			audio.addEventListener('error', () => {
				if (current) setGlyph(current, false);
				showToast('Could not play this track');
			});
		}

		/* Zap flow (ported from globe-rr): sign kind-9734 (NIP-07 or anonymous key), our PHP proxy
		   does the LNURL round-trips, then WebLN or QR / wallet deep link. No confirmation step. */
		const zapDialog = document.getElementById('cr-zap');
		const zapOpen = document.getElementById('cr-zap-open');
		if (zapDialog && zapOpen && typeof zapDialog.showModal === 'function') {
			const USER_ID = <?= (int) $userId ?>;
			const RELAYS = ['wss://relay.damus.io', 'wss://relay.primal.net', 'wss://nostr.land', 'wss://nostr.wine'];
			const WALLETS = [
				['system', 'System default', (b) => `lightning:${b}`],
				['phoenix', 'Phoenix', (b) => `phoenix://${b}`],
				['wos', 'Wallet of Satoshi', (b) => `walletofsatoshi:lightning:${b}`],
				['alby', 'Alby Go', (b) => `alby:${b}`],
				['cashapp', 'Cash App', (b) => `https://cash.app/launch/lightning/${b}`],
				['strike', 'Strike', (b) => `strike:${b}`],
				['blink', 'Blink', (b) => `blink://${b}`],
				['muun', 'Muun', (b) => `muun:${b}`],
				['zeus', 'Zeus', (b) => `zeusln:lightning:${b}`],
				['bluewallet', 'BlueWallet', (b) => `bluewallet:lightning:${b}`],
				['breez', 'Breez', (b) => `breez:${b}`],
				['zebedee', 'Zebedee', (b) => `zebedee:lightning:${b}`],
			];
			const WALLET_KEY = 'nb.zap.preferredWallet';
			const $ = (sel) => zapDialog.querySelector(sel);
			const steps = { compose: $('[data-zap-step="compose"]'), invoice: $('[data-zap-step="invoice"]'), paid: $('[data-zap-step="paid"]') };
			const custom = $('[data-zap-custom]');
			const comment = $('[data-zap-comment]');
			const payBtn = $('[data-zap-pay]');
			const errBox = $('[data-zap-error]');
			const hint = $('[data-zap-hint]');
			const walletSel = $('[data-zap-wallet]');
			const deeplink = $('[data-zap-deeplink]');
			let meta = null;
			let metaPromise = null;
			let picked = 21;
			let bolt11 = '';

			for (const [id, name] of WALLETS) {
				const o = document.createElement('option');
				o.value = id; o.textContent = name; walletSel.appendChild(o);
			}
			try { const w = localStorage.getItem(WALLET_KEY); if (w && WALLETS.some(([id]) => id === w)) walletSel.value = w; } catch { }

			const hasNip07 = () => !!(window.nostr && typeof window.nostr.signEvent === 'function');
			const hasWebln = () => !!(window.webln && typeof window.webln.sendPayment === 'function');
			const showStep = (name) => { for (const k in steps) steps[k].hidden = k !== name; };
			const sats = () => {
				const c = parseInt(custom.value, 10);
				return Number.isFinite(c) && c > 0 ? c : picked;
			};
			let busy = '';
			const refreshPay = () => {
				const n = sats();
				const ok = meta ? n >= meta.minSats && n <= meta.maxSats : n >= 1;
				payBtn.textContent = busy ? `⚡ ${busy}` : `⚡ Zap ${n.toLocaleString()} sat${n === 1 ? '' : 's'}`;
				payBtn.disabled = !!busy || !ok;
			};
			const withTimeout = (promise, ms, message) => new Promise((resolve, reject) => {
				const t = setTimeout(() => reject(new Error(message)), ms);
				promise.then((v) => { clearTimeout(t); resolve(v); }, (e) => { clearTimeout(t); reject(e); });
			});
			const setHint = () => {
				const who = hasNip07() ? 'Your Nostr extension signs the zap request.' : 'Signed with a one-time key (anonymous zap).';
				hint.textContent = 'You will get a QR code' + (hasWebln() ? ', a browser-wallet button' : '') + ' and wallet links. ' + (meta && !meta.allowsNostr ? 'This wallet takes plain Lightning payments.' : who);
			};

			async function api(body) {
				const r = await fetch('/creators/creator/zap.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ user: USER_ID, ...body }) });
				let j; try { j = await r.json(); } catch { throw new Error(`Zap server returned HTTP ${r.status}.`); }
				if (!j.ok) throw new Error(j.error || 'Zap failed.');
				return j;
			}
			function loadMeta() {
				metaPromise ??= api({ action: 'meta' }).then((m) => {
					meta = m;
					custom.min = m.minSats; custom.max = m.maxSats;
					$('[data-zap-comment-wrap]').hidden = !(m.commentAllowed > 0);
					comment.maxLength = Math.max(1, m.commentAllowed);
					refreshPay(); setHint();
					return m;
				}).catch((e) => { metaPromise = null; throw e; });
				return metaPromise;
			}

			// LUD-06 bech32 `lnurl` tag so the signature covers it (some providers reject its absence)
			function encodeLnurl(url) {
				const bytes = new TextEncoder().encode(url);
				const five = []; let acc = 0, bits = 0;
				for (const b of bytes) { acc = (acc << 8) | b; bits += 8; while (bits >= 5) { bits -= 5; five.push((acc >> bits) & 31); } }
				if (bits > 0) five.push((acc << (5 - bits)) & 31);
				const GEN = [0x3b6a57b2, 0x26508e6d, 0x1ea119fa, 0x3d4233dd, 0x2a1462b3];
				const polymod = (vals) => { let chk = 1; for (const v of vals) { const top = chk >> 25; chk = ((chk & 0x1ffffff) << 5) ^ v; for (let i = 0; i < 5; i++) if ((top >> i) & 1) chk ^= GEN[i]; } return chk; };
				const hrp = 'lnurl'; const exp = [];
				for (let i = 0; i < hrp.length; i++) exp.push(hrp.charCodeAt(i) >> 5);
				exp.push(0);
				for (let i = 0; i < hrp.length; i++) exp.push(hrp.charCodeAt(i) & 31);
				const mod = polymod([...exp, ...five, 0, 0, 0, 0, 0, 0]) ^ 1;
				const chk = []; for (let i = 0; i < 6; i++) chk.push((mod >> (5 * (5 - i))) & 31);
				const cs = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';
				return hrp + '1' + [...five, ...chk].map((v) => cs[v]).join('');
			}

			async function signZapRequest(amountSats, text) {
				const [user, domain] = meta.lud16.split('@');
				const template = {
					kind: 9734,
					created_at: Math.floor(Date.now() / 1000),
					tags: [['relays', ...RELAYS], ['amount', String(amountSats * 1000)], ['lnurl', encodeLnurl(`https://${domain}/.well-known/lnurlp/${user}`)], ['p', meta.pubkey]],
					content: text,
				};
				if (hasNip07()) {
					const pubkey = await window.nostr.getPublicKey();
					if (!/^[0-9a-f]{64}$/i.test(pubkey || '')) throw new Error('Could not read your Nostr public key; approve the request in your extension.');
					return window.nostr.signEvent({ ...template, pubkey: pubkey.toLowerCase() });
				}
				const { finalizeEvent, generateSecretKey } = await import('https://cdn.jsdelivr.net/npm/nostr-tools@2.25.2/+esm');
				return finalizeEvent(template, generateSecretKey());
			}

			async function renderQr(text) {
				const box = $('[data-zap-qr]');
				box.innerHTML = '';
				const { default: qrcode } = await import('https://cdn.jsdelivr.net/npm/qrcode-generator@2.0.4/+esm');
				const qr = qrcode(0, 'M');
				qr.addData(text.toUpperCase(), 'Alphanumeric');
				qr.make();
				box.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
			}

			function showInvoice(pr) {
				bolt11 = pr.toLowerCase();
				const updateLink = () => {
					const w = WALLETS.find(([id]) => id === walletSel.value) || WALLETS[0];
					deeplink.href = w[2](bolt11);
					deeplink.textContent = w[0] === 'system' ? 'Open in wallet' : `Open in ${w[1]}`;
				};
				walletSel.onchange = () => { try { localStorage.setItem(WALLET_KEY, walletSel.value); } catch { } updateLink(); };
				updateLink();
				weblnBtn.hidden = !hasWebln();
				$('[data-zap-error2]').textContent = '';
				showStep('invoice');
				renderQr(bolt11).catch(() => { $('[data-zap-error2]').textContent = 'Could not draw the QR code; copy the invoice instead.'; });
			}

			async function pay() {
				if (busy) return;
				errBox.textContent = '';
				try {
					const m = meta || await loadMeta();
					const n = sats();
					if (n < m.minSats || n > m.maxSats) throw new Error(`Pick an amount between ${m.minSats.toLocaleString()} and ${m.maxSats.toLocaleString()} sats.`);
					const text = (comment.value || '').trim().slice(0, m.commentAllowed || 0);
					const body = { action: 'invoice', sats: n, comment: text };
					if (m.allowsNostr) {
						busy = hasNip07() ? 'Waiting for your Nostr signer…' : 'Signing…'; refreshPay();
						body.nostrEvent = await withTimeout(signZapRequest(n, text), 90000, 'Your Nostr signer did not respond. Approve the prompt in your extension and try again.');
					}
					busy = 'Fetching invoice…'; refreshPay();
					const { pr } = await api(body);
					lastZap = { sats: n, nym: m.nym };
					showInvoice(pr);
				} catch (e) {
					errBox.textContent = e && e.message ? e.message : 'Zap failed.';
				} finally {
					busy = ''; refreshPay();
				}
			}

			let lastZap = { sats: 0, nym: '' };
			const weblnBtn = $('[data-zap-webln]');
			let weblnBusy = false;
			async function payWithWebln() {
				if (weblnBusy || !bolt11) return;
				const err2 = $('[data-zap-error2]');
				err2.textContent = '';
				weblnBusy = true;
				weblnBtn.disabled = true;
				weblnBtn.textContent = '⚡ Confirm in your wallet…';
				try {
					await withTimeout(window.webln.enable(), 60000, 'Your browser wallet did not respond.');
					const res = await withTimeout(window.webln.sendPayment(bolt11), 120000, 'Your browser wallet did not confirm the payment.');
					if (!res || !res.preimage) throw new Error('Wallet returned no proof of payment; check your wallet history.');
					$('[data-zap-paid-text]').textContent = `${lastZap.sats.toLocaleString()} sats sent to ${lastZap.nym}.`;
					showStep('paid');
				} catch (e) {
					err2.textContent = e && e.message ? `Browser wallet: ${e.message}` : 'Browser wallet payment failed.';
				} finally {
					weblnBusy = false;
					weblnBtn.disabled = false;
					weblnBtn.textContent = '⚡ Pay with browser wallet';
				}
			}
			weblnBtn.addEventListener('click', payWithWebln);

			zapOpen.addEventListener('click', (ev) => {
				ev.preventDefault();
				errBox.textContent = ''; $('[data-zap-error2]').textContent = ''; bolt11 = '';
				showStep('compose');
				zapDialog.showModal();
				setHint(); refreshPay();
				loadMeta().catch((e) => { errBox.textContent = e.message; });
			});
			zapDialog.addEventListener('click', (ev) => {
				if (ev.target.closest('[data-zap-close]') || ev.target === zapDialog) zapDialog.close();
				const preset = ev.target.closest('.cr-zap__preset');
				if (preset) {
					picked = +preset.dataset.sats; custom.value = '';
					for (const b of zapDialog.querySelectorAll('.cr-zap__preset')) b.setAttribute('aria-pressed', String(b === preset));
					refreshPay();
				}
			});
			$('[data-zap-copy]').addEventListener('click', async () => {
				try { await navigator.clipboard.writeText(bolt11); showToast('Invoice copied'); } catch { showToast('Copy failed'); }
			});
			custom.addEventListener('input', () => {
				for (const b of zapDialog.querySelectorAll('.cr-zap__preset')) b.setAttribute('aria-pressed', 'false');
				refreshPay();
			});
			comment.addEventListener('keydown', (ev) => { if (ev.key === 'Enter' && (ev.metaKey || ev.ctrlKey)) pay(); });
			payBtn.addEventListener('click', pay);
		}
	</script>
</body>

</html>
