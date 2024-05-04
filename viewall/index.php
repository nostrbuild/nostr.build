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
		$sql = "SELECT * FROM uploads_data WHERE approval_status = 'approved' AND file_extension = 'gif' AND type = 'picture' ORDER BY upload_date DESC LIMIT ?, ?";
		break;
	case 'vid':
		$perpage = 12;
		$sql = "SELECT * FROM uploads_data WHERE approval_status='approved' AND type='video' ORDER BY upload_date DESC LIMIT ?, ?";
		break;
	default:
		$perpage = 100;
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

	<link rel="stylesheet" href="/styles/index.css?v=7ff4472e93e9719e0eac60b75646b485" />
	<link rel="stylesheet" href="/styles/profile.css?v=ded26f9ac31e7492f67e6da9c95a14e2" />
	<link rel="stylesheet" href="/styles/header.css?v=19cde718a50bd676387bbe7e9e24c639" />
	<link rel="stylesheet" href="/styles/twbuild.css?v=404eff1cd815ac9115e1325ccf66ea0d" />
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
			<a href='?k=vid'><button class="donate_button">Videos</button></a>
			<a href='/adultview/'><button class="adult_button" style=" color: white;" onclick="return confirm('For Adults Only! This website contains age-restricted materials including nudity and explicit depictions of sexual activity. By clicking OK and entering, you affirm that you are at least 18 years of age or the age of majority in the jurisdiction you are accessing the website from and you consent to viewing sexually explicit content.')">
				<svg width="20px" height="20px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="#000" stroke-width="0.24000000000000005">
					<g stroke-width="0"></g>
					<g stroke-linecap="round" stroke-linejoin="round"></g>
					<g>
						<path fill-rule="evenodd" clip-rule="evenodd" d="M5.12404 8.58398C4.89427 8.23933 4.42862 8.1462 4.08397 8.37597C3.73933 8.60573 3.6462 9.07138 3.87596 9.41603L5.59861 12L3.87596 14.584C3.6462 14.9286 3.73933 15.3943 4.08397 15.624C4.42862 15.8538 4.89427 15.7607 5.12404 15.416L6.5 13.3521L7.87596 15.416C8.10573 15.7607 8.57138 15.8538 8.91603 15.624C9.12957 15.4817 9.24656 15.2487 9.25 15.0102C9.25344 15.2487 9.37043 15.4817 9.58397 15.624C9.92862 15.8538 10.3943 15.7607 10.624 15.416L12 13.3521L13.376 15.416C13.6057 15.7607 14.0714 15.8538 14.416 15.624C14.6296 15.4817 14.7466 15.2487 14.75 15.0101C14.7534 15.2487 14.8704 15.4817 15.084 15.624C15.4286 15.8538 15.8943 15.7607 16.124 15.416L17.5 13.3521L18.876 15.416C19.1057 15.7607 19.5714 15.8538 19.916 15.624C20.2607 15.3943 20.3538 14.9286 20.124 14.584L18.4014 12L20.124 9.41603C20.3538 9.07138 20.2607 8.60573 19.916 8.37597C19.5714 8.1462 19.1057 8.23933 18.876 8.58398L17.5 10.6479L16.124 8.58398C15.8943 8.23933 15.4286 8.1462 15.084 8.37597C14.8704 8.51833 14.7534 8.75127 14.75 8.98987C14.7466 8.75127 14.6296 8.51833 14.416 8.37597C14.0714 8.1462 13.6057 8.23933 13.376 8.58398L12 10.6479L10.624 8.58398C10.3943 8.23933 9.92862 8.1462 9.58397 8.37597C9.37043 8.51833 9.25344 8.75126 9.25 8.98986C9.24656 8.75126 9.12957 8.51833 8.91603 8.37597C8.57138 8.1462 8.10573 8.23933 7.87596 8.58398L6.5 10.6479L5.12404 8.58398ZM9.12404 14.584L7.40139 12L9.12404 9.41603C9.20713 9.29139 9.24799 9.15093 9.25 9.01153C9.25201 9.15093 9.29287 9.29139 9.37596 9.41603L11.0986 12L9.37596 14.584C9.29287 14.7086 9.25201 14.8491 9.25 14.9885C9.24799 14.8491 9.20713 14.7086 9.12404 14.584ZM14.624 14.584L12.9014 12L14.624 9.41603C14.7071 9.29139 14.748 9.15092 14.75 9.01151C14.752 9.15092 14.7929 9.29139 14.876 9.41603L16.5986 12L14.876 14.584C14.7929 14.7086 14.752 14.8491 14.75 14.9885C14.748 14.8491 14.7071 14.7086 14.624 14.584Z" fill="#fff"></path>
						<path fill-rule="evenodd" clip-rule="evenodd" d="M12.0574 1.25H11.9426C9.63424 1.24999 7.82519 1.24998 6.41371 1.43975C4.96897 1.63399 3.82895 2.03933 2.93414 2.93414C2.03933 3.82895 1.63399 4.96897 1.43975 6.41371C1.24998 7.82519 1.24999 9.63422 1.25 11.9426V12.0574C1.24999 14.3658 1.24998 16.1748 1.43975 17.5863C1.63399 19.031 2.03933 20.1711 2.93414 21.0659C3.82895 21.9607 4.96897 22.366 6.41371 22.5603C7.82519 22.75 9.63423 22.75 11.9426 22.75H12.0574C14.3658 22.75 16.1748 22.75 17.5863 22.5603C19.031 22.366 20.1711 21.9607 21.0659 21.0659C21.9607 20.1711 22.366 19.031 22.5603 17.5863C22.75 16.1748 22.75 14.3658 22.75 12.0574V11.9426C22.75 9.63423 22.75 7.82519 22.5603 6.41371C22.366 4.96897 21.9607 3.82895 21.0659 2.93414C20.1711 2.03933 19.031 1.63399 17.5863 1.43975C16.1748 1.24998 14.3658 1.24999 12.0574 1.25ZM3.9948 3.9948C4.56445 3.42514 5.33517 3.09825 6.61358 2.92637C7.91356 2.75159 9.62177 2.75 12 2.75C14.3782 2.75 16.0864 2.75159 17.3864 2.92637C18.6648 3.09825 19.4355 3.42514 20.0052 3.9948C20.5749 4.56445 20.9018 5.33517 21.0736 6.61358C21.2484 7.91356 21.25 9.62177 21.25 12C21.25 14.3782 21.2484 16.0864 21.0736 17.3864C20.9018 18.6648 20.5749 19.4355 20.0052 20.0052C19.4355 20.5749 18.6648 20.9018 17.3864 21.0736C16.0864 21.2484 14.3782 21.25 12 21.25C9.62177 21.25 7.91356 21.2484 6.61358 21.0736C5.33517 20.9018 4.56445 20.5749 3.9948 20.0052C3.42514 19.4355 3.09825 18.6648 2.92637 17.3864C2.75159 16.0864 2.75 14.3782 2.75 12C2.75 9.62177 2.75159 7.91356 2.92637 6.61358C3.09825 5.33517 3.42514 4.56445 3.9948 3.9948Z" fill="#fff"></path>
					</g>
				</svg>
				Adult</button></a><BR>
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
