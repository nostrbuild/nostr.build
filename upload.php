<?php
// Include config and session files
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/MultimediaUpload.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/S3Service.class.php';

// TODO: THIS WILL BE GONE IN THE FUTURE RELEASES
// Fetch statistics
$uploadsData = new UploadsData($link);
$stats = $uploadsData->getStats();

$total_files = $stats['total_files'];
$total_size_gb = round($stats['total_size'] / (1024 * 1024 * 1024), 2); // Convert bytes to GB

?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="description" content="nostr.build" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />

	<link rel="stylesheet" href="/styles/index.css?v=d92cc716e5a959e5720d593defd68e21" />
	<link rel="stylesheet" href="/styles/header.css?v=19cde718a50bd676387bbe7e9e24c639" />
	<link rel="icon" href="i/p/0.png">

	<title>nostr.build - Uploaded!</title>
</head>

<body>
	<header class="header">
		<?php include $_SERVER['DOCUMENT_ROOT'] . '/components/mainnav.php'; ?>
	</header>
	<?php
	// TODO: Migrate to a new APIv2 (still in development)
	global $awsConfig;
	// Instantiates S3Service class
	$s3 = new S3Service($awsConfig);
	// Grab User npub if logged in
	$usernpub = $perm->validateLoggedin() ? $_SESSION['usernpub'] : '';
	// Instantiates MultimediaUpload class
	$upload = new MultimediaUpload($link, $s3, false, $usernpub);
	// Set the $_FILES array or initiate file download from the URL
	$error = "Success";
	try {
		if (isset($_POST['img_url']) && !empty($_POST['img_url'])) {
			[$s, $c, $m] = $upload->uploadFileFromUrl($_POST['img_url'], isset($_POST["submit_ppic"]));
		} elseif (isset($_POST["submit_ppic"])) {
			$upload->setFiles($_FILES);
			[$s, $c, $m] = $upload->uploadProfilePicture();
		} else {
			$upload->setFiles($_FILES);
			[$s, $c, $m] = $upload->uploadFiles();
		}
		$result = $s;
	} catch (Exception $e) {
		$erro = $e->getMessage();
		$result = false;
	}
	// Even if result is true, it doesn't mean that the file was uploaded successfully
	$uploadData = $upload->getUploadedFiles();
	// Exemine the result to determine if the file was uploaded successfully
	if (sizeof($uploadData) > 0 && $uploadData[0]['url'] != null) {
		// We only allow single file upload for a free account
		$uploadData = $uploadData[0];
	} else {
		$result = false;
	}

	// Check if $uploadOk is set to 0 by an error
	if ($result === false) :
	?>
		&emsp;<span style="color:#F0F0F0">Sorry, your file was not uploaded! Make sure it is supported media or purchase an account for large files.</span>
		&nbsp;<a style="color:#F0F0F0" href="https://nostr.build/plans/">Purchase a nostr.build account HERE</a>
		<div style="color:#F0F0F0"><?= $m ?></div>
	<?php
	// if everything is ok, try to upload file
	else :
	?>
		<main>
			<div class="title_container">
				<h1>Nostr media uploader</h1>
				<p>Removes metadata, free, nostr focused</p>
				<div class="info_cards">
					<div class="info"><span><?= $total_size_gb ?></span>GB used</div>
					<div class="info"><span><?= number_format($total_files) ?></span> total uploads</div>
				</div>
			</div>
			<div class="drag-area">
				<div class="drag-area_sharing">
					<div class="sharing_container">
						<svg width="36" height="34" viewBox="0 0 36 34" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path fill-rule="evenodd" clip-rule="evenodd" d="M34.8452 0.821524C35.496 1.47241 35.496 2.52767 34.8452 3.17856L28.1785 9.84522C27.5277 10.4961 26.4723 10.4961 25.8215 9.84522L22.4882 6.51189C21.8373 5.86101 21.8373 4.80574 22.4882 4.15486C23.139 3.50399 24.1943 3.50399 24.8452 4.15486L27 6.30969L32.4882 0.821524C33.139 0.170657 34.1943 0.170657 34.8452 0.821524Z" fill="url(#paint0_linear_35_374)" />
							<path fill-rule="evenodd" clip-rule="evenodd" d="M6.16669 11.1666C6.16669 8.40521 8.40527 6.16663 11.1667 6.16663C13.9282 6.16663 16.1667 8.40521 16.1667 11.1666C16.1667 13.9281 13.9282 16.1666 11.1667 16.1666C8.40527 16.1666 6.16669 13.9281 6.16669 11.1666Z" fill="url(#paint1_linear_35_374)" />
							<path fill-rule="evenodd" clip-rule="evenodd" d="M17.8333 0.333375H9.93118C8.58958 0.333358 7.48229 0.333341 6.58031 0.407024C5.64348 0.483574 4.78229 0.647841 3.97341 1.06001C2.71899 1.69916 1.69913 2.71902 1.05998 3.97344C0.647811 4.78232 0.483544 5.64351 0.406994 6.58034C0.333311 7.48232 0.333327 8.58957 0.333344 9.93119V24.0689C0.333327 25.4105 0.333311 26.5177 0.406994 27.4197C0.483544 28.3565 0.647811 29.2177 1.05998 30.0267C1.69913 31.281 2.71899 32.3009 3.97341 32.94C4.78229 33.3522 5.64348 33.5165 6.58031 33.593C6.87261 33.6169 7.18648 33.633 7.52291 33.644C7.87176 33.6672 8.28901 33.667 8.71908 33.667C14.2572 33.667 19.7952 33.6667 25.3333 33.6667C25.4108 33.6667 25.4873 33.6667 25.5627 33.6667C26.8883 33.6675 27.8727 33.668 28.7255 33.4395C31.026 32.823 32.823 31.026 33.4395 28.7255C33.7317 27.635 33.667 26.4675 33.6667 25.3492C33.6697 25.0337 33.6668 24.718 33.6668 24.4025C33.6677 23.621 33.6685 22.9324 33.4872 22.2745C33.3282 21.697 33.0665 21.1529 32.715 20.6679C32.3145 20.1154 31.7763 19.6857 31.1655 19.1982L26.4472 15.4235C26.164 15.197 25.884 14.973 25.6298 14.7995C25.3472 14.6065 24.9992 14.406 24.5637 14.2944C23.9515 14.1374 23.3072 14.1577 22.7062 14.353C22.2785 14.4919 21.9438 14.7139 21.6738 14.9242C21.431 15.1135 21.1658 15.3547 20.8975 15.5987L6.71744 28.4897C6.36164 28.813 6.01769 29.1257 5.76484 29.3992C5.66346 29.509 5.50946 29.6795 5.36549 29.905C4.79379 29.5835 4.32873 29.0997 4.02999 28.5134C3.89718 28.2527 3.78896 27.879 3.72926 27.1484C3.66798 26.3982 3.66668 25.4277 3.66668 24V10C3.66668 8.57241 3.66798 7.60192 3.72926 6.85177C3.78896 6.12107 3.89718 5.74741 4.02999 5.48674C4.34956 4.85954 4.85951 4.34959 5.48671 4.03002C5.74738 3.89721 6.12104 3.78899 6.85174 3.72929C7.60189 3.66801 8.57238 3.66671 10 3.66671H17.8333C18.7538 3.66671 19.5 2.92052 19.5 2.00004C19.5 1.07957 18.7538 0.333375 17.8333 0.333375Z" fill="url(#paint2_linear_35_374)" />
							<defs>
								<linearGradient id="paint0_linear_35_374" x1="28.6667" y1="0.333374" x2="28.6667" y2="10.3334" gradientUnits="userSpaceOnUse">
									<stop stop-color="#2EDF95" />
									<stop offset="1" stop-color="#07847C" />
								</linearGradient>
								<linearGradient id="paint1_linear_35_374" x1="11.1667" y1="6.16663" x2="11.1667" y2="16.1666" gradientUnits="userSpaceOnUse">
									<stop stop-color="#2EDF95" />
									<stop offset="1" stop-color="#07847C" />
								</linearGradient>
								<linearGradient id="paint2_linear_35_374" x1="17.0032" y1="0.333374" x2="17.0032" y2="33.667" gradientUnits="userSpaceOnUse">
									<stop stop-color="#2EDF95" />
									<stop offset="1" stop-color="#07847C" />
								</linearGradient>
							</defs>
						</svg>

						<div class="sharing_info">
							<p style="text-align:left">Your media is ready to share</p>
							<a id="theList"><?= $uploadData['url'] ?></a>
						</div>
					</div>
				</div>

				<?php if ($perm->isGuest()) { ?>
				<button id="createAccount" onclick="myCreateAccount()" class="image_address"> <img src="https://cdn.nostr.build/assets/primo_nostr_icon.png">Create Account</button>
				<?php } ?>
				<button id="copyButton" onclick="myCopyFunction()" class="image_address"> <img src="https://cdn.nostr.build/assets/copy.png">Copy Media Link</button>

				<script>
					function myCopyFunction() {
						let myText = document.createElement("textarea");
						myText.value = document.getElementById("theList").innerHTML.replace(/&lt;/g, "<").replace(/&gt;/g, ">");
						document.body.appendChild(myText).select();
						document.execCommand('copy');
						document.body.removeChild(myText);
						document.getElementById("copyButton").innerHTML = "Copied!";
					}

					function myCreateAccount() {
						window.location.href = "https://nostr.build/plans/";
					}
				</script>

				<div class="media_container" style="flex-grow: 1">
					<?php
					if ($uploadData['type'] == 'video') :
					?>
						<video class="uploaded_video" height="240" width="320" controls>
							<source src="<?= $uploadData['url'] ?>" type="video/mp4">
						</video>
					<?php
					elseif (isset($_POST["submit_ppic"])) :
					?>
						<img style="max-width: 100%" height="250" src="<?= $uploadData['url'] ?>" alt="Profile image">
					<?php
					else :
						// Map dimentions to API resolutions
						$images = $uploadData['responsive'];
						$resolutionToWidth = [
							"240p"  => "426",
							"360p"  => "640",
							"480p"  => "854",
							"720p"  => "1280",
							"1080p" => "1920",
						];
						$srcset = [];
						foreach ($images as $resolution => $url) {
							$width = $resolutionToWidth[$resolution];
							$srcset[] = "$url {$width}w";
						}
						$srcset = implode(", ", $srcset);

						$sizes = '(max-width: 426px) 100vw, (max-width: 640px) 100vw, (max-width: 854px) 100vw, (max-width: 1280px) 50vw, 33vw';
					?>
						<img style="max-width: 100%" height="250" src="<?= $images['240p'] ?>" srcset="<?= $srcset ?>" sizes="<?= $sizes ?>" alt="Responsive image">
					<?php
					endif;
					?>
				</div>
			</div>

			<div class="metadata_container">
				<div class="metadata_image_container">
					<img src="assets/folder.png" alt="metadata image" />
				</div>
				<div class="metadata_info">
					<h3>Remaining metadata</h3>
					<p class="metadata">
						<?= json_encode($uploadData['metadata']) ?>
					</p>
				</div>
			</div>
			<div class="toast hidden_element">
				<div class="import_icon">
					<img src="https://cdn.nostr.build/assets/import_icon.png" alt="" />
				</div>
				<div class="toast_info">
					Copied
					<p>Link of your media copied to clipboard</p>
				</div>
			</div>
		</main>

	<?php
	endif; // End of if upload is successful
	?>

	<?= include $_SERVER['DOCUMENT_ROOT'] . '/components/footer.php'; ?>
	<script src="/scripts/upload.js?v=35dd51e3d05594f84cee3888f06952a7"></script>
</body>

</html>
