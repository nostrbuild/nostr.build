<?php
// TODO: Migrate to use Table class
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/SiteConfig.php';

$userId = $_GET['user'];

$stmt = $link->prepare("
    SELECT users.id AS user_id, users_images.id AS image_id, users.*, users_images.* 
    FROM users 
    LEFT JOIN users_images ON users.usernpub = users_images.usernpub AND users_images.flag = 1
    WHERE users.id = ? 
    ORDER BY users_images.id DESC
");
$stmt->bind_param("s", $userId);

$stmt->execute();

$result = $stmt->get_result();

// Fetch all rows into an associative array
$rows = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();
$link->close();

if (!empty($rows)) {
	$nym = $rows[0]['nym'];
	$ppic = $rows[0]['ppic'];
	$wallet = $rows[0]['wallet'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="description" content="nostr.build" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />

	<link rel="stylesheet" href="/styles/index.css?v=2" />
	<link rel="stylesheet" href="/styles/profile.css?v=2" />
	<link rel="stylesheet" href="/styles/header.css?v=3" />
	<link rel="icon" href="/assets/01.png">

	<title>nostr.build - <?= htmlentities($nym) ?></title>
	<style>
		.image-container {
			margin: auto;
			margin-bottom: 1.5rem;
			min-height: 8rem;
			min-width: 8rem;
			align-content: center;
		}

		.media {
			height: 11.875rem;
			width: auto;
			margin: auto;
			min-height: 8rem;
			min-width: 8rem;
			vertical-align: middle
		}
	</style>
</head>

<body>
	<header class="header">
		<?php include $_SERVER['DOCUMENT_ROOT'] . '/components/mainnav.php'; ?>
	</header>
	<main>
		<section class="title_section">
			<h1>
				Creators
				<svg width="24" height="25" viewBox="0 0 24 25" fill="none" xmlns="http://www.w3.org/2000/svg">
					<g opacity="0.5">
						<path fill-rule="evenodd" clip-rule="evenodd" d="M8.29289 5.79289C8.68342 5.40237 9.31658 5.40237 9.70711 5.79289L15.7071 11.7929C16.0976 12.1834 16.0976 12.8166 15.7071 13.2071L9.70711 19.2071C9.31658 19.5976 8.68342 19.5976 8.29289 19.2071C7.90237 18.8166 7.90237 18.1834 8.29289 17.7929L13.5858 12.5L8.29289 7.20711C7.90237 6.81658 7.90237 6.18342 8.29289 5.79289Z" fill="url(#paint0_linear_216_2863)" />
					</g>
					<defs>
						<linearGradient id="paint0_linear_216_2863" x1="12.0053" y1="9.5326" x2="10.76" y2="19.3419" gradientUnits="userSpaceOnUse">
							<stop stop-color="white" />
							<stop offset="1" stop-color="#884EA4" />
						</linearGradient>
					</defs>
				</svg>
				<span><?= htmlentities($nym) ?></span>
			</h1>
			<a class="donate_button" href="lightning:<?= htmlentities($wallet) ?>">Donate ⚡</a>
		</section>

		<div style="display: flex; flex-flow: wrap;">
			<?php
			foreach ($rows as $row) :
				// Parse URL and get only the filename
				$parsed_url = parse_url($row['image']);
				$filename = pathinfo($parsed_url['path'], PATHINFO_BASENAME);
				// Extract the main type from the mime_type
				$mime_main_type = explode('/', $row['mime_type'])[0];

				// Construct new URL based on the main mime_type
				$new_url = SiteConfig::getThumbnailUrl('professional_account_' . $mime_main_type);
				$new_url .= $filename;
				$src = htmlspecialchars($new_url);

				// Construct the link for the image
				$media_link = SiteConfig::getFullyQualifiedUrl('professional_account_' . $mime_main_type);
				$media_link .= $filename;
				$media_link = htmlspecialchars($media_link);
			?>
				<div class="image-container">
					<a href="<?= $media_link ?>" target="_blank">
						<?php if ($mime_main_type === 'video') : ?>
							<video class="media" controls preload="metadata">
								<!-- Fake mime type to force the browser to use the video player -->
								<source src="<?= $src ?>" type="video/mp4">
							</video>
						<?php elseif ($mime_main_type === 'audio') : ?>
							<audio class="media" controls>
								<source src="<?= $src ?>" type="<?= $row['mime_type'] ?>">
							</audio>
						<?php else : ?>
							<!-- default to image if the type is not recognized -->
							<img loading="lazy" class="media" src="<?= $src ?>">
						<?php endif; ?>
					</a>
				</div>
			<?php
			endforeach;
			?>

		</div>
	</main>
	<?= include $_SERVER['DOCUMENT_ROOT'] . '/components/footer.php'; ?>
	<script src="/scripts/images.js?v=1"></script>
</body>

</html>