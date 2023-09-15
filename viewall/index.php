<?php
// Include config, session, and Permission class files
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/components/pagination.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/SiteConfig.php';

global $link;
// Create new Permission object
$perm = new Permission();

// Check if the user is not logged in, if not then redirect him to login page
if (!$perm->validatePermissionsLevelAny(1, 2, 3, 4) && !$perm->hasPrivilege('canModerate') && !$perm->isAdmin()) {
	header("location: /login");
	$link->close();
	exit;
}

// Filter based on what we allow in views, potentially extending to allow adult content in the future
$allowed_views = array('img', 'gif', 'vid');

// Validate all user input and leave no room for interpretation or misinput
$page = isset($_GET['p']) && $_GET['p'] !== "" && is_numeric($_GET['p']) && $_GET['p'] >= 0 ? (int)$_GET['p'] : 0;
$view_type = isset($_GET['k']) && in_array($_GET['k'], $allowed_views) ? $_GET['k'] : 'img';

switch ($view_type) {
	case 'gif':
		$perpage = 50;
		$sql = "SELECT * FROM uploads_data WHERE approval_status = 'approved' AND file_extension = 'gif' AND type = 'picture' ORDER BY upload_date DESC LIMIT ?, ?";
		break;
	case 'vid':
		$perpage = 12;
		$sql = "SELECT * FROM uploads_data WHERE approval_status='approved' AND type='video' ORDER BY upload_date DESC LIMIT ?, ?";
		break;
	default:
		$perpage = 200;
		$sql = "SELECT * FROM uploads_data WHERE approval_status = 'approved' AND file_extension IN ('jpg', 'jpeg', 'png', 'webp') AND type = 'picture' ORDER BY upload_date DESC LIMIT ?, ?";
		break;
}
$start = $page * $perpage;
$end = $perpage + 1; // Add one to see if there are more pages
$stmt = $link->prepare($sql);
$stmt->bind_param('ii', $start, $end);
$stmt->execute();
$result = $stmt->get_result();
$morePages = $result->num_rows > $perpage ? true : false;
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
	<link rel="stylesheet" href="/styles/twbuild.css?v=45" />
	<link rel="icon" href="/assets/0.png">

	<script defer src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css" />
	<script defer type="module" src="/scripts/fw/blurhash-img.js?v=0.2.1"></script>
	<script>
		document.addEventListener("DOMContentLoaded", function() {
			Fancybox.bind('[data-fancybox="gallery"]', {
				// Custom options if needed
			});

			function observeImages() {
				const imageContainers = document.querySelectorAll(".image-container:not(.observed)");

				const observer = new IntersectionObserver((entries, observer) => {
					entries.forEach(entry => {
						if (entry.isIntersecting) {
							const imgContainer = entry.target;
							const img = imgContainer.querySelector("img.lazy-loaded");
							const loader = imgContainer.querySelector('[role="status"]'); // Assuming role="status" is unique within the container

							// Show the loader
							loader.style.opacity = "1";
							loader.style.zIndex = "2";

							const handleLoad = function() {
								const blurhashSibling = img.nextElementSibling;

								if (blurhashSibling && blurhashSibling.tagName.toLowerCase() === "blurhash-img") {
									//blurhashSibling.remove();
									blurhashSibling.style.height = "0";
									blurhashSibling.style.visibility = "hidden";
									blurhashSibling.style.opacity = "0";
									blurhashSibling.style.zIndex = "-1";

									img.style.height = "auto";
									img.style.visibility = "visible";
									img.style.opacity = "1";
									img.style.zIndex = "1"; // add this line to bring the image forward

									// Hide the loader
									loader.style.opacity = "0";
									loader.style.zIndex = "-1";
								}

								img.removeEventListener("load", handleLoad);
							};

							img.addEventListener("load", handleLoad);

							if (img.complete) {
								img.dispatchEvent(new Event("load"));
							}

							observer.unobserve(imgContainer);
							imgContainer.classList.add("observed"); // Mark this image as observed
						}
					});
				});

				imageContainers.forEach(img => {
					observer.observe(img);
				});
			}

			observeImages(); // Initial run
			// If you're dynamically loading new content, you can run `observeImages()` again
		});


		function copyToClipboard(event, text) {
			const button = event.target;
			navigator.clipboard.writeText(text).then(function() {
				button.textContent = 'Copied!';
				setTimeout(() => {
					button.textContent = 'Copy Link';
				}, 2000); // Change it back after 2 seconds
			}).catch(function(err) {
				button.textContent = 'Failed';
				setTimeout(() => {
					button.textContent = 'Copy Link';
				}, 2000);
			});
		}
	</script>

	<title>nostr.build - View All</title>
	<style>
		[role="status"] {
			opacity: 0;
			transition: opacity 0.3s ease;
			z-index: -1;
		}

		.lazy-loaded {
			height: 0;
			visibility: hidden;
			opacity: 0;
			transition: opacity 0.6s ease-in-out;
			z-index: -1;
		}

		blurhash-img {
			--aspect-ratio: 4/6;
			/* This is just a default, your PHP will override this */
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
				<span>View All</span>
			</h1>
			<a href='?k=gif'><button class="donate_button">GIFs</button></a>
			<a href='?k=img'><button class="donate_button">Images</button></a>
			<a href='?k=vid'><button class="donate_button">Videos</button></a><BR>
		</section>

		<?= handle_pagination($morePages, (int)$page, $perpage, '?k=' . $view_type . '&p=', false) ?>
		<?php
		?>
		<div class="columns-2 md:columns-3 lg:columns-4 xl:columns-6 w-screen px-2 md:px-4">
			<?php while ($row = $result->fetch_assoc()) : ?>
				<?php
				$filename = $row['filename'];
				$ext = pathinfo($filename, PATHINFO_EXTENSION);
				$thumbnail_path = htmlspecialchars(SiteConfig::getThumbnailUrl($view_type === 'vid' ? 'video' : 'image') . $filename);
				$full_path = htmlspecialchars(SiteConfig::getFullyQualifiedUrl($view_type === 'vid' ? 'video' : 'image') . $filename);
				$blurhash = htmlspecialchars($row['blurhash']);

				$media_width = empty($row['media_width']) || $row['media_width'] == 0 ? 4 : $row['media_width'];
				$media_height = empty($row['media_height']) || $row['media_height'] == 0 ? 6 : $row['media_height'];
				$aspect_ratio = "{$media_height}/{$media_width}";

				// Responsive image sizes
				$resolutionToWidth = [
					"240p"  => "426",
					"360p"  => "640",
					"480p"  => "854",
					"720p"  => "1280",
					"1080p" => "1920",
				];
				$srcset = [];
				foreach ($resolutionToWidth as $resolution => $width) {
					$srcset[] = htmlspecialchars(SiteConfig::getResponsiveUrl($view_type === 'vid' ? 'video' : 'image', $resolution) . $filename . " {$width}w");
				}
				$srcset = implode(", ", $srcset);
				$sizes = '(max-width: 426px) 100vw, (max-width: 640px) 100vw, (max-width: 854px) 100vw, (max-width: 1280px) 50vw, 33vw';
				?>
				<div class="relative group break-inside-avoid">
					<a href="<?= $full_path ?>" <?= $view_type === 'img' || $view_type === 'vid' ? 'data-fancybox="gallery"' : '' ?> target="_blank" rel="noopener noreferrer">
						<?php if ($view_type === 'vid') : ?>
							<video class="mb-2" controls preload="auto">
								<source src="<?= $thumbnail_path ?>" type="video/mp4">
							</video>
						<?php else : ?>
							<div class="image-container mb-2">
								<?php if ($view_type === 'gif') : ?>
									<img loading="lazy" class="w-full lazy-loaded" src="<?= $thumbnail_path ?>" alt="image" />
								<?php else : ?>
									<img loading="lazy" class="w-full lazy-loaded" src="<?= $thumbnail_path ?>" srcset="<?= $srcset ?>" sizes="<?= $sizes ?>" alt="image" />
								<?php endif; ?>
								<blurhash-img class="w-full" hash="<?= $blurhash ?>" style="--aspect-ratio: <?= $aspect_ratio ?>">
								</blurhash-img>
								<div role="status" class="absolute inset-0 grid place-items-center">
									<svg aria-hidden="true" class="w-8 h-8 mr-2 text-gray-200 animate-spin dark:text-gray-600 fill-blue-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor" />
										<path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill" />
									</svg>
									<span class="sr-only">Loading...</span>
								</div>
							</div>
						<?php endif; ?>
					</a>
					<div class="copy-link absolute top-2 right-2 bg-black bg-opacity-50 text-white rounded-sm py-2 px-1 group-hover:opacity-100 opacity-0 transition-opacity duration-300">
						<button onclick="copyToClipboard(event, '<?= $full_path ?>')">Copy Link</button>
					</div>
				</div>
			<?php endwhile; ?>
		</div>
		<?php
		$link->close();
		?>
	</main>
	<?= handle_pagination($morePages, (int)$page, $perpage, '?k=' . $view_type . '&p=') ?>
	<?php include $_SERVER['DOCUMENT_ROOT'] . '/components/footer.php'; ?>
</body>

</html>