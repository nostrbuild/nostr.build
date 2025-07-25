<?php
require_once __DIR__ . '/../vendor/autoload.php';

use kornrunner\Blurhash\Blurhash;
// Include config, session, and Permission class files
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/SiteConfig.php';

global $link;

// Filter based on what we allow in views, potentially extending to allow adult content in the future
$allowed_views = array('img', 'gif', 'vid');

$view_type = isset($_GET['k']) && in_array($_GET['k'], $allowed_views) ? $_GET['k'] : 'img';

$sql = match ($view_type) {
	'gif' => "SELECT * FROM uploads_data WHERE approval_status = 'approved' AND file_extension = 'gif' AND type = 'picture' ORDER BY upload_date DESC LIMIT 5",
	'vid' => "SELECT * FROM uploads_data WHERE approval_status='approved' AND type='video' ORDER BY upload_date DESC LIMIT 1",
	default => "SELECT * FROM uploads_data WHERE approval_status = 'approved' AND file_extension IN ('jpg', 'jpeg', 'png', 'webp') AND type = 'picture' ORDER BY upload_date DESC LIMIT 10",
};
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

	<link rel="stylesheet" href="/styles/index.css?v=16013407201d48c976a65d9ea88a77a3" />
	<link rel="stylesheet" href="/styles/profile.css?v=ded26f9ac31e7492f67e6da9c95a14e2" />
	<link rel="stylesheet" href="/styles/header.css?v=19cde718a50bd676387bbe7e9e24c639" />
	<link rel="stylesheet" href="/styles/twbuild.css?v=fa2c25f08b9c806910959aa9408f8666" />

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

	<title>nostr.build - Free View</title>
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
				<span>Free View</span>
			</h1>
			<a href='?k=gif'><button class="donate_button">GIFs</button></a>
			<a href='?k=img'><button class="donate_button">Images</button></a>
			<a href='?k=vid'><button class="donate_button">Videos</button></a><BR>
		</section>

		<?php
		$stmt = $link->prepare($sql);
		$stmt->execute();

		$result = $stmt->get_result();
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
		$stmt->close();
		$link->close(); // CLOSE MYSQL LINK
		?>
	</main>
	<a class="ref_link pb-4" style="font-size: x-large;" href="https://nostr.build/plans/"> Get access to all 2 Million+ Videos, Gifs and images HERE!</a>
	<?php include $_SERVER['DOCUMENT_ROOT'] . '/components/footer.php'; ?>
</body>

</html>
