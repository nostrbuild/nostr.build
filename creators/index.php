<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/SiteConfig.php';

global $link;
?>

<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="description" content="nostr.build" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />

	<link rel="stylesheet" href="/styles/index.css?v=6" />
	<link rel="stylesheet" href="/styles/builders.css?v=6" />
	<link rel="stylesheet" href="/styles/header.css?v=7" />
	<link rel="icon" href="/assets/0.png">

	<title>nostr.build - creators and artists</title>
</head>

<body>
	<header class="header">
		<?php include $_SERVER['DOCUMENT_ROOT'] . '/components/mainnav.php'; ?>
	</header>

	<main>
		<h1>Creators</h1>
		<div class="builders_container">
			<?php
			$stmt = $link->prepare("
			SELECT u.*, i.image, i.mime_type, i.total_images
			FROM users AS u INNER JOIN
			(
				SELECT users_images.*, ROW_NUMBER()
				OVER(PARTITION BY usernpub ORDER BY RAND()) as rn,
				COUNT(*) OVER(PARTITION BY usernpub) as total_images
				FROM users_images WHERE flag=1
				) AS i ON u.usernpub = i.usernpub WHERE i.rn = 1 ORDER BY RAND()
			");
			$stmt->execute();
			$result = $stmt->get_result();


			while ($row = $result->fetch_assoc()) :
				// Parse URL and get only the filename
				$parsed_url = parse_url($row['image']);
				$filename = pathinfo($parsed_url['path'], PATHINFO_BASENAME);
				// Extract the main type from the mime_type
				$mime_main_type = explode('/', $row['mime_type'])[0];

				// Construct new URL based on the main mime_type
				$new_url = SiteConfig::getThumbnailUrl('professional_account_' . $mime_main_type);
				$new_url .= $filename;

				$src = htmlspecialchars($new_url);
				$userId = htmlspecialchars($row['id']);
				$usernpub = htmlspecialchars($row['usernpub']);
				$title = htmlspecialchars($row['nym']);
			?>

				<a href="/creators/creator/?user=<?= $userId ?>">
					<figure class="builder_card">
						<div class="card_header">
							<figcaption class="card_title"><?= $title ?></figcaption>
							<div class="info"><?= htmlspecialchars($row['total_images']) ?> images</div>
						</div>

						<?php if ($mime_main_type === 'video') : ?>
							<video style="max-height: 325px; max-width: 350px; vertical-align: middle" controls preload="metadata">
								<!-- Fake mime type to force the browser to use the video player -->
								<source id="<?= $usernpub ?>" src="<?= $src ?>" type="video/mp4">
							</video>
						<?php elseif ($mime_main_type === 'audio') : ?>
							<audio style="max-height: 325px; max-width: 350px; vertical-align: middle" controls>
								<source id="<?= $usernpub ?>" src="<?= $src ?>" type="<?= $row['mime_type'] ?>">
							</audio>
						<?php else : ?>
							<!-- default to image if the type is not recognized -->
							<img loading="lazy" style="max-height: 325px; max-width: 414px; align: middle" id="<?= $usernpub ?>" src="<?= $src ?>">
						<?php endif; ?>
					</figure>
				</a>
			<?php
			endwhile;
			$stmt->close();
			?>
		</div>
	</main>

	<?php include $_SERVER['DOCUMENT_ROOT'] . '/components/footer.php'; ?>
	<script src="/scripts/index.js?v=5"></script>

</body>

</html>