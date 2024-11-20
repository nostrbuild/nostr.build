<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
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

	<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
	<link rel="manifest" href="/site.webmanifest">
	<link rel="mask-icon" href="/safari-pinned-tab.svg" color="#5bbad5">
	<meta name="msapplication-TileColor" content="#9f00a7">
	<meta name="theme-color" content="#ffffff">

	<link rel="stylesheet" href="/styles/index.css?v=290253d31f2fde0932483cb54581766b" />
	<link rel="stylesheet" href="/styles/builders.css?v=0dd633698255982f3df87df8e3e2697e" />
	<link rel="stylesheet" href="/styles/header.css?v=19cde718a50bd676387bbe7e9e24c639" />

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
				) AS i ON u.usernpub = i.usernpub WHERE i.rn = 1
				AND u.plan_until_date > NOW()
				AND u.acctlevel IN (1, 10, 99)
				ORDER BY RAND()
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
			$link->close(); // CLOSE MYSQL LINK
			?>
		</div>
	</main>

	<?php include $_SERVER['DOCUMENT_ROOT'] . '/components/footer.php'; ?>

</body>

</html>