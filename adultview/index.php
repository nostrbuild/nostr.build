<?php
require_once __DIR__ . '/../vendor/autoload.php';

use kornrunner\Blurhash\Blurhash;
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
if (!$perm->validatePermissionsLevelAny(1, 2, 3, 4, 10) && !$perm->hasPrivilege('canModerate') && !$perm->isAdmin()) {
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
		$sql = "SELECT * FROM uploads_data WHERE approval_status = 'adult' AND file_extension = 'gif' AND type = 'picture' ORDER BY upload_date DESC LIMIT ?, ?";
		break;
	case 'vid':
		$perpage = 12;
		$sql = "SELECT * FROM uploads_data WHERE approval_status='adult' AND type='video' ORDER BY upload_date DESC LIMIT ?, ?";
		break;
	default:
		$perpage = 200;
		$sql = "SELECT * FROM uploads_data WHERE approval_status = 'adult' AND file_extension IN ('jpg', 'jpeg', 'png', 'webp') AND type = 'picture' ORDER BY upload_date DESC LIMIT ?, ?";
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

	<link rel="stylesheet" href="/styles/index.css?v=7ff4472e93e9719e0eac60b75646b485" />
	<link rel="stylesheet" href="/styles/profile.css?v=ded26f9ac31e7492f67e6da9c95a14e2" />
	<link rel="stylesheet" href="/styles/header.css?v=19cde718a50bd676387bbe7e9e24c639" />
	<link rel="stylesheet" href="/styles/twbuild.css?v=aca4c92eb6c66e2007d9d11803f92184" />
	<link rel="icon" href="/assets/0.png">

	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.7.2/css/lightgallery-bundle.min.css" integrity="sha512-nUqPe0+ak577sKSMThGcKJauRI7ENhKC2FQAOOmdyCYSrUh0GnwLsZNYqwilpMmplN+3nO3zso8CWUgu33BDag==" crossorigin="anonymous" referrerpolicy="no-referrer" />

	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/video.js/8.6.1/alt/video-js-cdn.min.css" integrity="sha512-lByjBFPoRLnSCpB8YopnHGrqH1NKWff5fmtJ6z1ojUQE6ZQnhiw8T0L3FtezlyThDLViN4XwnKBaSCrglowvwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

	<script defer src="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.7.2/lightgallery.umd.min.js" integrity="sha512-VOQBxCIgNssJrB8+irZF7L8MvfpAshegc36C3H5QD7vmibXM4uCNaqJIaSNatD2z2ZQQJSx0k+q+m+xsSPp4Xw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
	<script defer src="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.7.2/plugins/autoplay/lg-autoplay.umd.min.js" integrity="sha512-GtFOSYOB4Gx5+0hQxi/nVFJk77Tvmmgs/Kdbl4PZLjiZ8RBRMiKU2r33gsdn19r4Nlnx9lDqKf8ZdOSNwdgUtw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
	<script defer src="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.7.2/plugins/fullscreen/lg-fullscreen.umd.min.js" integrity="sha512-xmNLmAH+RvR1Bbdq1hML9/Hqp3Uvf6++oZbc6h+KVw2CpJE0oOPIc0zV5nbuTLlOU+1pLOIPlBvcrVqUUXZh7w==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
	<script defer src="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.7.2/plugins/hash/lg-hash.umd.min.js" integrity="sha512-MQKJS2hbR8dmwpFNNsZ35od470xx/5FwNvyzqa1yc4fOHLpWVQUdMJWcRReqtmHiWNlP8DVwEBw2v2d67IfsMg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
	<script defer src="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.7.2/plugins/thumbnail/lg-thumbnail.umd.min.js" integrity="sha512-dc8xJSGs0ib9uo0fLT/v4wp2LG7+4OSzc+UpFiIKiv6QP/e4hZH/S8manUCTtO3tNVGzcje8uJjSdL+NH29blQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
	<script defer src="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.7.2/plugins/video/lg-video.umd.min.js" integrity="sha512-olksMIiITctwLVsKDH2fm9nylHHYzK2v/bIY+LzBO9GAw9A44MBjYaJGm/2eIbhTtXZXdXQUoS17HoV2rI+fFA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
	<script defer src="https://cdnjs.cloudflare.com/ajax/libs/lightgallery/2.7.2/plugins/zoom/lg-zoom.umd.min.js" integrity="sha512-OUF2jbRheQR5yXPCvXN71udWa5cvwPf+shcXM+5GrW1vtNurTn7az8LCP3hS50gm17ULXdh3cdkhiPa0Qqyczw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

	<script defer src="https://cdnjs.cloudflare.com/ajax/libs/video.js/8.6.1/video.min.js" integrity="sha512-19kPqSYAN3EiTxmPPFeInu0KiE6ZpYntGctkdtc2LGShfM1QcZQA2O8y25og2lufK5bE2gSnYn5PO2+9Iex4Bg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

	<script>
		document.addEventListener("DOMContentLoaded", function() {
			lightGallery(document.getElementById('lightgallery'), {
				plugins: [lgZoom, lgThumbnail, lgAutoplay, lgFullscreen, lgHash, /*lgPager, lgShare,*/ lgVideo /*, lgMediumZoom*/ ],
				speed: 500,
				thumbnail: true,
				videojs: true,
				videojsOptions: {
					muted: false,
				},
				autoplayVideoOnSlide: true,
				gotoNextSlideOnVideoEnd: true,
				exThumbImage: 'data-src',
			});

			const observer = new IntersectionObserver((entries, observer) => {
				entries.forEach(entry => {
					if (entry.isIntersecting) {
						const img = entry.target;
						const realSrc = img.getAttribute('data-src');
						const realSrcset = img.getAttribute('data-srcset');
						const realSizes = img.getAttribute('data-sizes');

						img.src = realSrc;
						if (realSrcset) {
							img.srcset = realSrcset;
						}
						if (realSizes) {
							img.sizes = realSizes;
						}

						observer.unobserve(img);
					}
				});
			}, {
				rootMargin: '0px 0px 200px 0px',
				threshold: 0.01
			});

			const images = document.querySelectorAll('img.blurhash-image');
			images.forEach(img => observer.observe(img));

		});
	</script>

	<title>nostr.build - Adult Content</title>
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
			<a href='?k=vid'><button class="donate_button">Videos</button></a>
			<a href='/viewall/'><button class="donate_button">Back Home</button></a><BR>
		</section>

		<?= handle_pagination($morePages, (int)$page, $perpage, '?k=' . $view_type . '&p=', false) ?>
		<?php
		?>
		<div id="lightgallery" class="gap-2 columns-2 md:columns-3 lg:columns-4 xl:columns-6 w-screen px-2 md:px-4">
			<?php while ($row = $result->fetch_assoc()) : ?>
				<?php
				$filename = $row['filename'];
				$ext = pathinfo($filename, PATHINFO_EXTENSION);
				$thumbnail_path = htmlspecialchars(SiteConfig::getThumbnailUrl($view_type === 'vid' ? 'video' : 'image') . $filename);
				$full_path = htmlspecialchars(SiteConfig::getFullyQualifiedUrl($view_type === 'vid' ? 'video' : 'image') . $filename);

				$media_width = empty($row['media_width']) || $row['media_width'] == 0 ? 4 : $row['media_width'];
				$media_height = empty($row['media_height']) || $row['media_height'] == 0 ? 6 : $row['media_height'];
				$aspect_ratio = "{$media_height}/{$media_width}";

				// Blurhash
				if ($view_type !== 'vid' && $view_type !== 'gif' && !empty($row['blurhash'])) {
					$bh_dataUrl = '';
					try {
						$bh_width = max((int) round($media_width / 40), 1); // Ensure width is at least 1
						$bh_height = max((int) round($media_height / 40), 1); // Ensure height is at least 1

						$pixels = Blurhash::decode($row['blurhash'], $bh_width, $bh_height);

						// Verify that pixels array is not empty and correctly structured
						if (!is_array($pixels) || empty($pixels) || !is_array($pixels[0])) {
							throw new Exception('Invalid pixel array from Blurhash decode.');
						}

						$bh_image = imagecreatetruecolor($bh_width, $bh_height);
						if (!$bh_image instanceof GdImage) {
							throw new Exception('Could not create a new true color image.');
						}

						for ($y = 0; $y < $bh_height; ++$y) {
							for ($x = 0; $x < $bh_width; ++$x) {
								if (!isset($pixels[$y][$x])) {
									throw new Exception('Missing pixel data.');
								}
								[$r, $g, $b] = $pixels[$y][$x];
								if (!imagesetpixel($bh_image, $x, $y, imagecolorallocate($bh_image, $r, $g, $b))) {
									throw new Exception('Could not set pixel.');
								}
							}
						}

						$bh_stream = fopen('php://memory', 'w+');
						if (!$bh_stream) {
							throw new Exception('Could not open memory stream.');
						}

						if (!imagepng($bh_image, $bh_stream)) {
							throw new Exception('Could not output image to stream.');
						}
						rewind($bh_stream);

						$streamContents = stream_get_contents($bh_stream);
						if ($streamContents === false) {
							throw new Exception('Could not get stream contents.');
						}
						$bh_dataUrl = 'data:image/png;base64,' . base64_encode($streamContents);
					} catch (Exception $e) {
						error_log($e->getMessage());
						$bh_dataUrl = ''; // In case of any error, set data URL to empty
					} finally {
						if (isset($bh_image) && ($bh_image instanceof GdImage || is_resource($bh_image))) {
							imagedestroy($bh_image); // Free image resource
						}
						if (isset($bh_stream) && is_resource($bh_stream)) {
							fclose($bh_stream); // Close stream
						}
					}
				} else {
					$bh_dataUrl = '';
				}

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
				// video poster placeholder, until we have real ones: https://cdn.nostr.build/assets/video/jpg/video-poster@0.25x.jpg
				$lgSrc = match ($view_type) {
					'vid' => 'data-video=\'{"source": [{"src":"' . $full_path . '", "type": "video/mp4"}], "attributes": {"preload": "auto", "playsinline": true, "controls": true}}\' data-poster="https://cdn.nostr.build/assets/video/jpg/video-poster@0.75x.jpg"',
					'gif' => 'data-src="' . $full_path . '"',
					default => 'data-responsive="' . $srcset . '" data-src="' . $full_path . '"',
				};
				?>
				<div class="relative group break-inside-avoid" <?= $lgSrc ?>>
					<a href="<?= $full_path ?>" target="_blank" rel="noopener noreferrer">
						<?php if ($view_type === 'vid') : ?>
							<!-- A video poster is required for lightgallery to work -->
							<img height="728" width="408" src="https://cdn.nostr.build/assets/video/jpg/video-poster@0.5x.jpg" alt="video poster" style="display: none;" />
							<video class="mb-2" controls preload="auto">
								<source src="<?= $thumbnail_path ?>" type="video/mp4">
							</video>
						<?php else : ?>
							<div class="image-container mb-2">
								<?php if ($view_type === 'gif') : ?>
									<img height="<?= $media_height ?>" width="<?= $media_width ?>" loading="lazy" class="w-full" src="<?= $thumbnail_path ?>" alt="image" />
								<?php else : ?>
									<img height="<?= $media_height ?>" width="<?= $media_width ?>" loading="lazy" class="w-full blurhash-image" src="<?= $bh_dataUrl ?>" data-src="<?= $thumbnail_path ?>" data-srcset="<?= $srcset ?>" data-sizes="<?= $sizes ?>" alt="image" />
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</a>
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
