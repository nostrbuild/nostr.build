<?php
// Include config, session, and Permission class files
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/SiteConfig.php';

// Create new Permission object
$perm = new Permission();

// Check if the user is not logged in, if not then redirect him to login page
if (!$perm->validatePermissionsLevelAny(1) && !$perm->hasPrivilege('canModerate') && !$perm->isAdmin()) {
	header("location: /login");
	$link->close();
	exit;
}


// Filter based on what we allow in views, potentially extending to allow adult content in the future
$allowed_views = array('img', 'gif', 'vid');

// Validate all user input and leave no room for interpretation or misinput
$page = isset($_GET['p']) && $_GET['p'] !== "" && is_numeric($_GET['p']) ? (int)$_GET['p'] : 0;
$view_type = isset($_GET['k']) && in_array($_GET['k'], $allowed_views) ? $_GET['k'] : 'img';

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
	<link rel="icon" href="/assets/0.png">

	<title>nostr.build - Adult Content</title>
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
				Accounts
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
				<span>Adult Content</span>
			</h1>
			<a href='?k=gif'><button class="donate_button">GIFs</button></a>
			<a href='?k=img'><button class="donate_button">Images</button></a>
			<a href='?k=vid'><button class="donate_button">Videos</button></a><BR>
		</section>

		<div style="display: flex; flex-flow: wrap;">
			<?php

			switch ($view_type) {
				case 'gif':
					// GIFs
					$perpage = 50;
					$sql = "SELECT * FROM uploads_data WHERE approval_status = 'adult' AND file_extension = 'gif' AND type = 'picture' ORDER BY upload_date DESC LIMIT ?, ?";
					$sql_count = "SELECT COUNT(*) as total FROM uploads_data WHERE approval_status = 'adult' AND file_extension = 'gif' AND type = 'picture'";
					break;
				case 'vid':
					// Videos
					$perpage = 12;
					$sql = "SELECT * FROM uploads_data WHERE approval_status='adult' AND type='video' ORDER BY upload_date DESC LIMIT ?, ?";
					$sql_count = "SELECT COUNT(*) as total FROM uploads_data WHERE approval_status='adult' AND type='video'";
					break;
				default:
					// Images
					$perpage = 200;
					$sql = "SELECT * FROM uploads_data WHERE approval_status = 'adult' AND file_extension IN ('jpg', 'jpeg', 'png', 'webp') AND type = 'picture' ORDER BY upload_date DESC LIMIT ?, ?";
					$sql_count = "SELECT COUNT(*) as total FROM uploads_data WHERE approval_status = 'adult' AND file_extension IN ('jpg', 'jpeg', 'png', 'webp') AND type = 'picture'";
					break;
			}

			$start = $page * $perpage;
			$end = $perpage;


			// selects images to display, confirms they are 'approved' before diplaying
			$stmt = $link->prepare($sql);
			$stmt->bind_param('ii', $start, $end);
			$stmt->execute();

			$result = $stmt->get_result();
			while ($row = $result->fetch_assoc()) {
				$filename = $row['filename'];
				$ext = pathinfo($filename, PATHINFO_EXTENSION);

				switch ($view_type) {
					case 'gif':
						$thumbnail_path = htmlspecialchars(SiteConfig::getThumbnailUrl('image') . $filename);
						$full_path = htmlspecialchars(SiteConfig::getFullyQualifiedUrl('image') . $filename);
						echo '<div class="image-container">';
						echo '<a href="' . $full_path . '" target="_blank" rel="noopener noreferrer"><img loading="lazy" class="media" src="' . $thumbnail_path . '" alt="image" /></a>';
						echo '</div>';
						break;
					case 'vid':
						$thumbnail_path = htmlspecialchars(SiteConfig::getThumbnailUrl('video') . $filename);
						$full_path = htmlspecialchars(SiteConfig::getFullyQualifiedUrl('video') . $filename);
						echo '<div class="video-container">';
						echo '<a href="' . $full_path . '" target="_blank" rel="noopener noreferrer"><video class="media" controls><source src="' . $thumbnail_path . '" type="video/mp4"></video></a>';
						echo '</div>';
						break;
					default:
						$thumbnail_path = htmlspecialchars(SiteConfig::getThumbnailUrl('image') . $filename);
						$full_path = htmlspecialchars(SiteConfig::getFullyQualifiedUrl('image') . $filename);
						echo '<div class="image-container">';
						echo '<a href="' . $full_path . '" target="_blank" rel="noopener noreferrer"><img loading="lazy" class="media" src="' . $thumbnail_path . '" alt="image" /></a>';
						echo '</div>';
						break;
				}
			}

			echo "</div>";
			$stmt = $link->prepare($sql_count);
			$stmt->execute();
			$total = $stmt->get_result()->fetch_assoc()['total'];
			echo '<p style="text-align:center;" color=#C58FF7><big>' . handle_pagination($total, (int)$page, $perpage, '?k=' . $view_type . '&p=') . "</p></big>";

			$link->close();

			function handle_pagination($total, $page, $shown, $url)
			{
				$pages = ceil($total / $shown);
				$range_start = (($page >= 5) ? ($page - 3) : 1);
				$range_end = ((($page + 5) > $pages) ? $pages : ($page + 5));

				if ($page >= 1) {
					$r[] = '<span><a href="' . $url . '">&laquo; first</a></span>';
					$r[] = '<span><a href="' . $url . ($page - 1) . '">&lsaquo; previous</a></span>';
					$r[] = (($range_start > 1) ? ' ... ' : '');
				}

				if ($range_end > 1) {
					foreach (range($range_start, $range_end) as $key => $value) {
						if ($value == ($page + 1)) $r[] = '<span>' . $value . '</span>';
						else $r[] = '<span><a href="' . $url . ($value - 1) . '">' . $value . '</a></span>';
					}
				}

				if (($page + 1) < $pages) {
					$r[] = (($range_end < $pages) ? ' ... ' : '');
					$r[] = '<span><a href="' . $url . ($page + 1) . '">next &rsaquo;</a></span>';
					$r[] = '<span><a href="' . $url . ($pages - 1) . '">last &raquo;</a></span>';
				}

				return ((isset($r)) ? '<div>' . implode("\r\n", $r) . '</div>' : '');
			}

			?>

	</main>
	<?php include $_SERVER['DOCUMENT_ROOT'] . '/components/footer.php'; ?>
	<script src="/scripts/images.js?v=1"></script>
</body>

</html>