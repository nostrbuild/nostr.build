<?php
// Include config, session, and Permission class files
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';

// Filter based on what we allow in views, potentially extending to allow adult content in the future
$allowed_views = array('img', 'gif', 'vid');

// Validate all user input and leave no room for interpretation or misinput
$view_type = isset($_GET['k']) && in_array($_GET['k'], $allowed_views) ? $_GET['k'] : 'img';

?>

<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="description" content="nostr.build" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />

	<link rel="stylesheet" href="/styles/index.css" />
	<link rel="stylesheet" href="/styles/profile.css" />
	<link rel="stylesheet" href="/styles/header.css" />
	<link rel="icon" href="https://cdn.nostr.build/assets/01.png" />

	<script defer src="/scripts/images.js"></script>
	<title>nostr.build - Free View</title>
	<style>
		.image-container {
			margin: auto;
			margin-bottom: 1.5rem;
			min-height: 8rem;
			min-width: 8rem;
			align-content: center;
		}

		.video-container {
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
				<span>Free View</span>
			</h1>
			<a href='?k=gif'><button class="donate_button">GIFs</button></a>
			<a href='?k=img'><button class="donate_button">Images</button></a>
			<a href='?k=vid'><button class="donate_button">Videos</button></a><BR>
		</section>

		<div style="display: flex; flex-flow: wrap;">
			<?php

			$sql = match ($view_type) {
				'gif' => "SELECT * FROM uploads_data WHERE approval_status = 'approved' AND file_extension = 'gif' AND type = 'picture' ORDER BY upload_date DESC LIMIT 50",
				'vid' => "SELECT * FROM uploads_data WHERE approval_status='approved' AND type='video' ORDER BY upload_date DESC LIMIT 12",
				default => "SELECT * FROM uploads_data WHERE approval_status = 'approved' AND file_extension IN ('jpg', 'jpeg', 'png', 'webp') AND type = 'picture' ORDER BY upload_date DESC LIMIT 200",
			};

			// selects images to display, confirms they are 'approved' before diplaying
			$stmt = $link->prepare($sql);
			$stmt->execute();

			$result = $stmt->get_result();
			while ($row = $result->fetch_assoc()) {
				$filename = $row['filename'];
				$ext = pathinfo($filename, PATHINFO_EXTENSION);

				switch ($view_type) {
					case 'gif':
						$thumbnail_path = '/thumbnail/i/' . $filename;
						echo '<div class="image-container">';
						echo '<a href="/i/' . $filename . '" target="_blank" rel="noopener noreferrer"><img loading="lazy" class="media" src="' . $thumbnail_path . '" alt="image" /></a>';
						echo '</div>';
						break;
					case 'vid':
						$thumbnail_path = '/av/' . $filename;
						echo '<div class="video-container">';
						echo '<a href="/av/' . $filename . '" target="_blank" rel="noopener noreferrer"><video class="media" controls><source src="' . $thumbnail_path . '" type="video/mp4"></video></a>';
						echo '</div>';
						break;
					default:
						$thumbnail_path = '/thumbnail/i/' . $filename;
						echo '<div class="image-container">';
						echo '<a href="/i/' . $filename . '" target="_blank" rel="noopener noreferrer"><img loading="lazy" class="media" src="' . $thumbnail_path . '" alt="image" /></a>';
						echo '</div>';
						break;
				}
			}

			echo "</div>";
			$link->close();

			?>

	</main>
	<a class="ref_link" style="font-size: x-large" href="https://nostr.build/signup"> Get access to all 500k+ Videos, Gifs and images HERE!</a><br /><br />
	<?php include $_SERVER['DOCUMENT_ROOT'] . '/components/footer.php'; ?>
</body>

</html>