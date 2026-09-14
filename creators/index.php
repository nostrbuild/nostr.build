<?php
// Creators directory: one card per active pro account with public (flag = 1) media.
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/SiteConfig.php';

const CR_FALLBACK_PPIC = 'https://nostr.build/assets/temp_ppic.png';

function e(?string $v): string
{
	return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// One random public item per creator as the cover, plus per-type counts
$stmt = $link->prepare("
	SELECT u.id, u.nym, u.ppic, u.usernpub,
	       i.image, i.mime_type, i.blurhash,
	       i.total, i.images, i.videos, i.audios
	FROM users AS u
	INNER JOIN (
		SELECT user_uuid, image, mime_type, blurhash,
		       ROW_NUMBER() OVER (PARTITION BY user_uuid ORDER BY RAND()) AS rn,
		       COUNT(*) OVER (PARTITION BY user_uuid) AS total,
		       SUM(mime_type LIKE 'image/%') OVER (PARTITION BY user_uuid) AS images,
		       SUM(mime_type LIKE 'video/%') OVER (PARTITION BY user_uuid) AS videos,
		       SUM(mime_type LIKE 'audio/%') OVER (PARTITION BY user_uuid) AS audios
		FROM users_images
		WHERE flag = 1
	) AS i ON u.uuid_id = i.user_uuid
	WHERE i.rn = 1
	  AND u.plan_until_date > NOW()
	  AND u.acctlevel IN (1, 10, 99)
	ORDER BY RAND()
");
$stmt->execute();
$creators = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$link->close();

$totalMedia = array_sum(array_column($creators, 'total'));

function cr_cover(array $row): array
{
	$filename = pathinfo((string) parse_url($row['image'], PHP_URL_PATH), PATHINFO_BASENAME);
	$type = explode('/', $row['mime_type'])[0];
	$key = 'professional_account_' . $type;
	if (!array_key_exists($key, SiteConfig::CDN_CONFIGS)) {
		return ['type' => 'other', 'src' => '', 'srcset' => ''];
	}
	$full = SiteConfig::getFullyQualifiedUrl($key) . $filename;
	if ($type === 'image') {
		$srcset = [];
		foreach (['360p' => 640, '480p' => 854, '720p' => 1280] as $res => $w) {
			$srcset[] = SiteConfig::getResponsiveUrl($key, $res) . $filename . " {$w}w";
		}
		return ['type' => 'image', 'src' => SiteConfig::getThumbnailUrl($key) . $filename, 'srcset' => implode(', ', $srcset)];
	}
	// video + audio covers live beside the file
	return ['type' => $type, 'src' => $full . '/poster.jpg', 'srcset' => ''];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="description" content="Pro creators and artists hosting their images, video, and audio on nostr.build." />
	<meta property="og:title" content="Creators · nostr.build" />
	<meta property="og:description" content="Pro creators and artists hosting their images, video, and audio on nostr.build." />

	<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
	<link rel="manifest" href="/site.webmanifest">
	<link rel="mask-icon" href="/safari-pinned-tab.svg" color="#5bbad5">
	<meta name="msapplication-TileColor" content="#9f00a7">
	<meta name="theme-color" content="#24204b">

	<link rel="stylesheet" href="/styles/index.css?v=5f778fa5254a390824630c03c36a7c50" />
	<link rel="stylesheet" href="/styles/header.css?v=19cde718a50bd676387bbe7e9e24c639" />

	<title>Creators · nostr.build</title>

	<style>
		:root {
			--cr-sand: #2e2961;
			--cr-surface: rgba(70, 63, 147, 0.42);
			--cr-surface-strong: rgba(70, 63, 147, 0.7);
			--cr-line: rgba(168, 163, 219, 0.18);
			--cr-line-strong: rgba(168, 163, 219, 0.4);
			--cr-lagoon: #a8a3db;
			--cr-lagoon-deep: #5a52a8;
			--cr-ink: #e4e2f3;
			--cr-ink-soft: #bebbe2;
			--cr-ink-mute: #8e89bf;
		}

		.cr-main {
			width: 100%;
			max-width: 1440px;
			margin: 0 auto;
			padding: 32px 16px 64px;
			color: var(--cr-ink);
			align-items: stretch;
		}

		@media (min-width: 768px) {
			.cr-main {
				padding: 48px 24px 80px;
			}
		}

		.cr-head {
			display: flex;
			flex-wrap: wrap;
			align-items: flex-end;
			justify-content: space-between;
			gap: 16px;
			margin-bottom: 28px;
		}

		.cr-kicker {
			font-size: 11px;
			font-weight: 700;
			letter-spacing: 0.16em;
			text-transform: uppercase;
			color: var(--cr-ink-mute);
			margin-bottom: 6px;
		}

		.cr-title {
			font-size: clamp(2rem, 5vw, 3rem);
			font-weight: 700;
			letter-spacing: -0.02em;
			line-height: 1.05;
			color: #fff;
		}

		.cr-sub {
			margin-top: 10px;
			font-size: 15px;
			color: var(--cr-ink-soft);
			max-width: 56ch;
		}

		.cr-stats {
			display: flex;
			gap: 8px;
			flex-wrap: wrap;
		}

		.cr-stat {
			display: inline-flex;
			align-items: baseline;
			gap: 6px;
			padding: 8px 14px;
			border-radius: 10px;
			background: rgba(36, 32, 75, 0.6);
			border: 1px solid var(--cr-line);
			font-size: 13px;
			color: var(--cr-ink-soft);
			white-space: nowrap;
		}

		.cr-stat strong {
			color: #fff;
			font-size: 16px;
			font-variant-numeric: tabular-nums;
		}

		.cr-cards {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(min(100%, 260px), 1fr));
			gap: 16px;
		}

		.cr-card {
			position: relative;
			display: flex;
			flex-direction: column;
			border-radius: 16px;
			overflow: hidden;
			background: var(--cr-surface);
			border: 1px solid var(--cr-line);
			box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.06);
			text-decoration: none;
			color: inherit;
			transition: transform .18s ease, border-color .18s, box-shadow .18s;
		}

		.cr-card:hover,
		.cr-card:focus-visible {
			transform: translateY(-2px);
			border-color: var(--cr-line-strong);
			box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08), 0 24px 40px -28px rgba(0, 0, 0, 0.8);
			outline: none;
		}

		.cr-card__cover {
			position: relative;
			aspect-ratio: 4 / 3;
			background: var(--cr-sand);
			overflow: hidden;
		}

		.cr-card__cover canvas,
		.cr-card__cover img {
			position: absolute;
			inset: 0;
			width: 100%;
			height: 100%;
			object-fit: cover;
			display: block;
		}

		.cr-card__cover img {
			opacity: 0;
			transition: opacity .35s ease, transform .5s ease;
		}

		.cr-card__cover img.is-loaded {
			opacity: 1;
		}

		.cr-card:hover .cr-card__cover img.is-loaded {
			transform: scale(1.04);
		}

		.cr-card__cover--audio img {
			object-fit: cover;
		}

		.cr-card__badge {
			position: absolute;
			top: 10px;
			left: 10px;
			padding: 3px 8px;
			border-radius: 6px;
			background: rgba(12, 9, 30, 0.65);
			-webkit-backdrop-filter: blur(6px);
			backdrop-filter: blur(6px);
			color: #fff;
			font-size: 10px;
			font-weight: 700;
			letter-spacing: .08em;
			text-transform: uppercase;
		}

		.cr-card__body {
			display: flex;
			align-items: center;
			gap: 12px;
			padding: 12px 14px 14px;
		}

		.cr-card__avatar {
			position: relative;
			z-index: 1;
			width: 44px;
			height: 44px;
			border-radius: 50%;
			object-fit: cover;
			flex-shrink: 0;
			background: var(--cr-sand);
			border: 2px solid rgba(255, 255, 255, 0.12);
			margin-top: -34px;
			box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
		}

		.cr-card__text {
			min-width: 0;
			flex: 1;
		}

		.cr-card__name {
			font-size: 15px;
			font-weight: 600;
			color: #fff;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}

		.cr-card__counts {
			display: flex;
			gap: 10px;
			margin-top: 3px;
			font-size: 12px;
			color: var(--cr-ink-mute);
			font-variant-numeric: tabular-nums;
		}

		.cr-card__counts span {
			display: inline-flex;
			align-items: center;
			gap: 4px;
		}

		.cr-card__counts svg {
			width: 13px;
			height: 13px;
			opacity: .8;
		}

		.cr-empty {
			text-align: center;
			padding: 64px 16px;
			border-radius: 18px;
			border: 1px dashed var(--cr-line-strong);
			color: var(--cr-ink-soft);
		}
	</style>
</head>

<body>
	<header class="header">
		<?php include $_SERVER['DOCUMENT_ROOT'] . '/components/mainnav.php'; ?>
	</header>

	<main class="cr-main">
		<div class="cr-head">
			<div>
				<div class="cr-kicker">nostr.build</div>
				<h1 class="cr-title">Creators</h1>
				<p class="cr-sub">Pro creators and artists hosting their images, video, and audio on nostr.build. Every page is a public portfolio, straight from their account.</p>
			</div>
			<div class="cr-stats">
				<span class="cr-stat"><strong><?= number_format(count($creators)) ?></strong> creators</span>
				<span class="cr-stat"><strong><?= number_format($totalMedia) ?></strong> shared files</span>
			</div>
		</div>

		<?php if (!$creators) : ?>
			<section class="cr-empty">No creators have shared media yet.</section>
		<?php else : ?>
			<section class="cr-cards">
				<?php
				$icons = [
					'image' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>',
					'video' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 8-6 4 6 4V8Z"/><rect x="2" y="6" width="14" height="12" rx="2"/></svg>',
					'audio' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>',
				];
				foreach ($creators as $row) :
					$cover = cr_cover($row);
					$nym = trim((string) $row['nym']) ?: 'Creator';
					$creatorPpic = !empty($row['ppic']) ? $row['ppic'] : CR_FALLBACK_PPIC;
					$counts = ['image' => (int) $row['images'], 'video' => (int) $row['videos'], 'audio' => (int) $row['audios']];
				?>
					<a class="cr-card" href="/creators/creator/?user=<?= (int) $row['id'] ?>">
						<div class="cr-card__cover cr-card__cover--<?= e($cover['type']) ?>" <?= !empty($row['blurhash']) ? 'data-blurhash="' . e($row['blurhash']) . '"' : '' ?>>
							<canvas width="32" height="32" aria-hidden="true"></canvas>
							<?php if ($cover['src']) : ?>
								<img src="<?= e($cover['src']) ?>" <?= $cover['srcset'] ? 'srcset="' . e($cover['srcset']) . '" sizes="(max-width: 640px) 100vw, 33vw"' : '' ?> alt="" loading="lazy" decoding="async">
							<?php endif; ?>
							<?php if ($cover['type'] !== 'image') : ?><span class="cr-card__badge"><?= e($cover['type']) ?></span><?php endif; ?>
						</div>
						<div class="cr-card__body">
							<img class="cr-card__avatar" src="<?= e($creatorPpic) ?>" alt="" width="44" height="44" loading="lazy" decoding="async"
								onerror="this.onerror=null;this.src='<?= CR_FALLBACK_PPIC ?>'">
							<div class="cr-card__text">
								<div class="cr-card__name"><?= e($nym) ?></div>
								<div class="cr-card__counts">
									<?php foreach ($counts as $type => $n) : if ($n > 0) : ?>
											<span title="<?= $n ?> <?= $type ?>"><?= $icons[$type] ?><?= number_format($n) ?></span>
									<?php endif;
									endforeach; ?>
								</div>
							</div>
						</div>
					</a>
				<?php endforeach; ?>
			</section>
		<?php endif; ?>
	</main>

	<?php include $_SERVER['DOCUMENT_ROOT'] . '/components/footer.php'; ?>

	<script type="module">
		import { decode as decodeBlurhash } from 'https://cdn.jsdelivr.net/npm/blurhash@2.0.5/dist/index.mjs';

		for (const cover of document.querySelectorAll('.cr-card__cover')) {
			const img = cover.querySelector('img');
			const canvas = cover.querySelector('canvas');
			if (!img) continue;
			const hash = cover.dataset.blurhash;
			if (hash && !(img.complete && img.naturalWidth)) {
				try {
					const px = decodeBlurhash(hash, 32, 32);
					const ctx = canvas.getContext('2d');
					const data = ctx.createImageData(32, 32);
					data.data.set(px);
					ctx.putImageData(data, 0, 0);
				} catch { /* keep flat placeholder */ }
			}
			const reveal = () => img.classList.add('is-loaded');
			if (img.complete && img.naturalWidth) reveal();
			else img.addEventListener('load', reveal, { once: true });
		}
	</script>
</body>

</html>
