<?php
// TODO: Migrate to use Table class
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/SiteConfig.php';

$userId = $_GET['user'];
$display = $_GET['display'] ?? 'image';
$mime = $display === 'video' ? 'video/%' : ($display === 'audio' ? 'audio/%' : 'image/%');

// Check number for each media category
$stats_stmt = $link->prepare("
    SELECT COUNT(users_images.id) AS count, SUBSTRING(users_images.mime_type, 1, 5) as type
    FROM users 
    LEFT JOIN users_images ON users.usernpub = users_images.usernpub AND users_images.flag = 1
    WHERE users.id = ? AND users.plan_until_date > NOW() AND users.acctlevel IN (1, 10, 99)
    GROUP BY type
    ORDER BY count DESC
");
$stats_stmt->bind_param("s", $userId);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats_rows = $stats_result->fetch_all(MYSQLI_ASSOC);
$stats_stmt->close();

// Check if selected category is empty and redirect to the first non-empty category
$availableCategories = [];
foreach ($stats_rows as $row) {
	$availableCategories[$row['type']] = $row['count'];
}

// Check if the selected category has media
$selectedCategory = $display;
if (empty($availableCategories[$selectedCategory])) {
	// If selected category is empty, find the first non-empty category
	foreach ($availableCategories as $type => $count) {
		if ($count > 0) {
			$uid = urlencode($userId);
			$type = urlencode($type);
			// Redirect to the first non-empty category
			header("Location: /creators/creator/?user={$uid}&display={$type}");
			$link->close();
			exit;
		}
	}
}

$stmt = $link->prepare("
    SELECT users.id AS user_id, users_images.id AS image_id, users.*, users_images.* 
    FROM users 
    LEFT JOIN users_images ON users.usernpub = users_images.usernpub AND users_images.flag = 1
    WHERE users.id = ? AND users.plan_until_date > NOW() AND users.acctlevel IN (1, 10, 99)
		AND users_images.mime_type LIKE '{$mime}'
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

	<link rel="stylesheet" href="/styles/index.css?v=7ff4472e93e9719e0eac60b75646b485" />
	<link rel="stylesheet" href="/styles/profile.css?v=ded26f9ac31e7492f67e6da9c95a14e2" />
	<link rel="stylesheet" href="/styles/header.css?v=19cde718a50bd676387bbe7e9e24c639" />
	<link rel="stylesheet" href="/styles/twbuild.css?v=13cd88284199f638fdb4b445df454fad" />
	<link rel="icon" href="/assets/01.png">

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
					fluid: false,
					responsive: true,
					audioPosterMode: false,
				},
				autoplayVideoOnSlide: true,
				gotoNextSlideOnVideoEnd: true,
			});
		});
	</script>

	<title>nostr.build - <?= htmlentities($nym) ?></title>
</head>

<body>
	<header class="header">
		<?php include $_SERVER['DOCUMENT_ROOT'] . '/components/mainnav.php'; ?>
	</header>
	<main>
		<section class="title_section text-3xl">
			<h1>
				<a href="/creators">Creators</a>
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
			<a class="donate_button text-lg" href="lightning:<?= htmlentities($wallet) ?>">Donate ⚡</a>
			<div class="flex justify-center w-full">
				<span class="isolate inline-flex rounded-md shadow-sm">
					<button style="<?= $display === 'image' ? 'background-color: #efefef;' : '' ?>" type="button" class="relative inline-flex items-center rounded-l-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-10">
						<a href="/creators/creator/?user=<?= htmlentities($userId) ?>&display=image">Images (<?= $availableCategories['image'] ?? 0 ?>)</a>
					</button>
					<button style="<?= $display === 'video' ? 'background-color: #efefef;' : '' ?>" type="button" class="relative -ml-px inline-flex items-center bg-white px-3 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-10">
						<a href="/creators/creator/?user=<?= htmlentities($userId) ?>&display=video">Videos (<?= $availableCategories['video'] ?? 0 ?>)</a>
					</button>
					<button style="<?= $display === 'audio' ? 'background-color: #efefef;' : '' ?>" type="button" class="relative -ml-px inline-flex items-center rounded-r-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-10">
						<a href="/creators/creator/?user=<?= htmlentities($userId) ?>&display=audio">Audio (<?= $availableCategories['audio'] ?? 0 ?>)</a>
					</button>
				</span>
			</div>
		</section>

		<div id="lightgallery" class="gap-2 columns-1 md:columns-3 lg:columns-4 w-screen px-1 md:px-2 content-center">
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
				$resolutionToWidth = [
					"240p"  => "426",
					"360p"  => "640",
					"480p"  => "854",
					"720p"  => "1280",
					"1080p" => "1920",
				];
				$srcset = [];
				foreach ($resolutionToWidth as $resolution => $width) {
					$srcset[] = htmlspecialchars(SiteConfig::getResponsiveUrl('professional_account_' . $mime_main_type, $resolution) . $filename . " {$width}w");
				}
				$srcset = implode(", ", $srcset);
				$sizes = '(max-width: 426px) 100vw, (max-width: 640px) 100vw, (max-width: 854px) 100vw, (max-width: 1280px) 50vw, 33vw';
				// video poster placeholder, until we have real ones: https://cdn.nostr.build/assets/video/jpg/video-poster@0.25x.jpg
				$lgSrc = match ($mime_main_type) {
					'video' => 'data-video=\'{"source": [{"src":"' . $media_link . '", "type": "video/mp4"}], "attributes": {"preload": "auto", "playsinline": true, "controls": true}}\' data-poster="https://cdn.nostr.build/assets/video/jpg/video-poster@0.75x.jpg"',
					'audio' => 'data-video=\'{"source": [{"src":"' . $media_link . '", "type": "' . $mime_type . '"}], "attributes": {"preload": "auto", "playsinline": true, "controls": true}}\' data-poster="https://cdn.nostr.build/assets/audio/jpg/audio-wave@0.75x.jpg"',
					'image' => 'data-responsive="' . $srcset . '" data-src="' . $src . '"',
				};
			?>
				<div class="relative group break-inside-avoid image-container mb-2" <?= $lgSrc ?>>
					<a href="<?= $media_link ?>" target="_blank">
						<?php if ($mime_main_type === 'video') : ?>
							<!-- A video poster is required for lightgallery to work -->
							<img src="https://cdn.nostr.build/assets/video/jpg/video-poster@0.5x.jpg" alt="video poster" style="display: none;" />
							<video class="w-full" controls preload="auto">
								<!-- Fake mime type to force the browser to use the video player -->
								<source src="<?= $src ?>" type="video/mp4">
							</video>
						<?php elseif ($mime_main_type === 'audio') : ?>
							<img src="https://cdn.nostr.build/assets/audio/jpg/audio-wave@0.5x.jpg" alt="audio poster" style="display: none;" />
							<video class="w-full" controls preload="metadata" poster="https://cdn.nostr.build/assets/audio/jpg/audio-wave@0.5x.jpg">
								<!-- Fake mime type to force the browser to use the video player -->
								<source src="<?= $src ?>" type="<?= $mime_type ?>">
							</video>
							<!--
							<audio class="media" controls>
								<source src="<?= $src ?>" type="<?= $row['mime_type'] ?>">
							</audio>
						-->
						<?php else : ?>
							<!-- default to image if the type is not recognized -->
							<img loading="lazy" class="w-full" src="<?= $src ?>" srcset="<?= $srcset ?>" sizes="<?= $sizes ?>">
						<?php endif; ?>
					</a>
				</div>
			<?php
			endforeach;
			?>

		</div>
	</main>
	<?= include $_SERVER['DOCUMENT_ROOT'] . '/components/footer.php'; ?>
</body>

</html>