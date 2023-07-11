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

	<link rel="stylesheet" href="/styles/index.css" />
	<link rel="stylesheet" href="/styles/profile.css" />
	<link rel="stylesheet" href="/styles/header.css" />
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
		}
	</style>
</head>

<body>
	<header class="header">
		<nav class="navigation_header">
			<a href="/" class="nav_button">
				<span>
					<svg width="21" height="20" viewBox="0 0 21 20" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path opacity="0.12" d="M8 17.5V10H13V17.5" fill="url(#paint0_linear_220_743)" />
						<path d="M7.99999 17.5V11.3333C7.99999 10.8666 7.99999 10.6332 8.09082 10.455C8.17071 10.2982 8.2982 10.1707 8.455 10.0908C8.63326 10 8.86666 10 9.33332 10H11.6667C12.1334 10 12.3667 10 12.545 10.0908C12.7018 10.1707 12.8292 10.2982 12.9092 10.455C13 10.6332 13 10.8666 13 11.3333V17.5M2.16666 7.91667L9.69999 2.26667C9.98691 2.05151 10.1303 1.94392 10.2878 1.90245C10.4269 1.86585 10.5731 1.86585 10.7122 1.90246C10.8697 1.94392 11.0131 2.05151 11.3 2.26667L18.8333 7.91667M3.83332 6.66667V14.8333C3.83332 15.7667 3.83332 16.2335 4.01498 16.59C4.17477 16.9036 4.42974 17.1586 4.74334 17.3183C5.09986 17.5 5.56657 17.5 6.49999 17.5H14.5C15.4334 17.5 15.9002 17.5 16.2567 17.3183C16.5702 17.1586 16.8252 16.9036 16.985 16.59C17.1667 16.2335 17.1667 15.7667 17.1667 14.8333V6.66667L12.1 2.86667C11.5262 2.43634 11.2393 2.22118 10.9242 2.13824C10.6462 2.06503 10.3538 2.06503 10.0757 2.13824C9.76066 2.22118 9.47374 2.43634 8.89999 2.86667L3.83332 6.66667Z" stroke="url(#paint1_linear_220_743)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
						<defs>
							<linearGradient id="paint0_linear_220_743" x1="10.5033" y1="12.1603" x2="9.92909" y2="17.4375" gradientUnits="userSpaceOnUse">
								<stop stop-color="white" />
								<stop offset="1" stop-color="#884EA4" />
							</linearGradient>
							<linearGradient id="paint1_linear_220_743" x1="10.5111" y1="6.37568" x2="9.75801" y2="17.4488" gradientUnits="userSpaceOnUse">
								<stop stop-color="white" />
								<stop offset="1" stop-color="#884EA4" />
							</linearGradient>
						</defs>
					</svg>
				</span>
				Home
			</a>
			<a href="/builders/" class="nav_button">
				<span>
					<svg width="21" height="20" viewBox="0 0 21 20" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path fill-rule="evenodd" clip-rule="evenodd" d="M14.0774 5.24426C14.4029 4.91882 14.9305 4.91882 15.2559 5.24426L19.4226 9.41092C19.748 9.73633 19.748 10.264 19.4226 10.5894L15.2559 14.7561C14.9305 15.0815 14.4029 15.0815 14.0774 14.7561C13.752 14.4307 13.752 13.903 14.0774 13.5776L17.6549 10.0002L14.0774 6.42277C13.752 6.09733 13.752 5.56969 14.0774 5.24426Z" fill="url(#paint0_linear_220_715)" />
						<path fill-rule="evenodd" clip-rule="evenodd" d="M6.92257 5.24426C7.24801 5.56969 7.24801 6.09733 6.92257 6.42277L3.34516 10.0002L6.92257 13.5776C7.24801 13.903 7.24801 14.4307 6.92257 14.7561C6.59713 15.0815 6.0695 15.0815 5.74406 14.7561L1.57739 10.5894C1.25195 10.264 1.25195 9.73633 1.57739 9.41092L5.74406 5.24426C6.0695 4.91882 6.59713 4.91882 6.92257 5.24426Z" fill="url(#paint1_linear_220_715)" />
						<path fill-rule="evenodd" clip-rule="evenodd" d="M12.3474 1.68669C12.7967 1.78652 13.08 2.23167 12.9802 2.68095L9.64683 17.6809C9.547 18.1302 9.10183 18.4135 8.65256 18.3137C8.20328 18.2138 7.92 17.7687 8.01984 17.3194L11.3532 2.3194C11.453 1.87012 11.8982 1.58685 12.3474 1.68669Z" fill="url(#paint2_linear_220_715)" />
						<defs>
							<linearGradient id="paint0_linear_220_715" x1="16.75" y1="5.00018" x2="16.75" y2="15.0001" gradientUnits="userSpaceOnUse">
								<stop stop-color="#2EDF95" />
								<stop offset="1" stop-color="#07847C" />
							</linearGradient>
							<linearGradient id="paint1_linear_220_715" x1="4.24998" y1="5.00018" x2="4.24998" y2="15.0001" gradientUnits="userSpaceOnUse">
								<stop stop-color="#2EDF95" />
								<stop offset="1" stop-color="#07847C" />
							</linearGradient>
							<linearGradient id="paint2_linear_220_715" x1="10.5" y1="1.66667" x2="10.5" y2="18.3337" gradientUnits="userSpaceOnUse">
								<stop stop-color="#2EDF95" />
								<stop offset="1" stop-color="#07847C" />
							</linearGradient>
						</defs>
					</svg>
				</span>
				Builders
			</a>
			<a href="/creators" class="nav_button active_button">
				<span>
					<svg width="21" height="20" viewBox="0 0 21 20" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path opacity="0.12" d="M2.16669 10C2.16669 14.6023 5.89765 18.3333 10.5 18.3333C11.8808 18.3333 13 17.2141 13 15.8333V15.4167C13 15.0297 13 14.8362 13.0214 14.6737C13.1691 13.5518 14.0519 12.6691 15.1737 12.5214C15.3362 12.5 15.5297 12.5 15.9167 12.5H16.3334C17.7141 12.5 18.8334 11.3807 18.8334 10C18.8334 5.39763 15.1024 1.66667 10.5 1.66667C5.89765 1.66667 2.16669 5.39763 2.16669 10Z" fill="url(#paint0_linear_220_726)" />
						<path d="M2.16669 10C2.16669 14.6023 5.89765 18.3333 10.5 18.3333C11.8808 18.3333 13 17.2141 13 15.8333V15.4167C13 15.0297 13 14.8362 13.0214 14.6737C13.1691 13.5518 14.0519 12.6691 15.1737 12.5214C15.3362 12.5 15.5297 12.5 15.9167 12.5H16.3334C17.7141 12.5 18.8334 11.3808 18.8334 10C18.8334 5.39763 15.1024 1.66667 10.5 1.66667C5.89765 1.66667 2.16669 5.39763 2.16669 10Z" stroke="url(#paint1_linear_220_726)" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
						<path d="M6.33333 10.8333C6.79357 10.8333 7.16667 10.4603 7.16667 10C7.16667 9.53975 6.79357 9.16667 6.33333 9.16667C5.8731 9.16667 5.5 9.53975 5.5 10C5.5 10.4603 5.8731 10.8333 6.33333 10.8333Z" stroke="url(#paint2_linear_220_726)" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
						<path d="M13.8333 7.5C14.2936 7.5 14.6667 7.1269 14.6667 6.66667C14.6667 6.20643 14.2936 5.83333 13.8333 5.83333C13.3731 5.83333 13 6.20643 13 6.66667C13 7.1269 13.3731 7.5 13.8333 7.5Z" stroke="url(#paint3_linear_220_726)" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
						<path d="M8.83333 6.66667C9.29358 6.66667 9.66667 6.29357 9.66667 5.83333C9.66667 5.3731 9.29358 5 8.83333 5C8.3731 5 8 5.3731 8 5.83333C8 6.29357 8.3731 6.66667 8.83333 6.66667Z" stroke="url(#paint4_linear_220_726)" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
						<defs>
							<linearGradient id="paint0_linear_220_726" x1="2.16669" y1="1.66667" x2="21.7341" y2="6.43848" gradientUnits="userSpaceOnUse">
								<stop stop-color="#DABD55" />
								<stop offset="1" stop-color="#F78533" />
							</linearGradient>
							<linearGradient id="paint1_linear_220_726" x1="2.16669" y1="1.66667" x2="21.7341" y2="6.43848" gradientUnits="userSpaceOnUse">
								<stop stop-color="#DABD55" />
								<stop offset="1" stop-color="#F78533" />
							</linearGradient>
							<linearGradient id="paint2_linear_220_726" x1="5.5" y1="9.16667" x2="7.45674" y2="9.64385" gradientUnits="userSpaceOnUse">
								<stop stop-color="#DABD55" />
								<stop offset="1" stop-color="#F78533" />
							</linearGradient>
							<linearGradient id="paint3_linear_220_726" x1="13" y1="5.83333" x2="14.9567" y2="6.31052" gradientUnits="userSpaceOnUse">
								<stop stop-color="#DABD55" />
								<stop offset="1" stop-color="#F78533" />
							</linearGradient>
							<linearGradient id="paint4_linear_220_726" x1="8" y1="5" x2="9.95674" y2="5.47718" gradientUnits="userSpaceOnUse">
								<stop stop-color="#DABD55" />
								<stop offset="1" stop-color="#F78533" />
							</linearGradient>
						</defs>
					</svg>
				</span>
				Creators
			</a>

			<a href="/login/" class="nav_button">
				<span><img src="/assets/nav/login.png" alt="login image" /> </span>
				Login
			</a>
		</nav>
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
			$stmt->close();
			?>

		</div>
	</main>
	<?= include $_SERVER['DOCUMENT_ROOT'] . '/components/footer.php'; ?>
	<script src="/scripts/images.js"></script>
</body>

</html>