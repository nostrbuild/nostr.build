<?php
// Include config and session files
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/MultimediaUpload.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/S3Service.class.php';

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

	<link rel="stylesheet" href="/styles/index.css" />
	<link rel="stylesheet" href="/styles/header.css" />
	<link rel="icon" href="i/p/0.png">

	<title>nostr.build - Uploaded!</title>
</head>

<body>
	<header class="header">
		<nav class="navigation_header">
			<a href="/" class="nav_button active_button">
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
			<a href="/builders" class="nav_button">
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
			<a href="/creators" class="nav_button">
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

			<a href="/login" class="nav_button">
				<span><img src="assets/nav/login.png" alt="login image" /> </span>
				Login
			</a>
		</nav>
	</header>



	<?php
	// TODO: Migrate to a new APIv2 (still in development)
	global $awsConfig;
	// Instantiates S3Service class
	$s3 = new S3Service($awsConfig);
	// Instantiates MultimediaUpload class
	$upload = new MultimediaUpload($link, $s3);
	// Set the $_FILES array or initiate file download from the URL
	if (isset($_POST['img_url']) && !empty($_POST['img_url'])) {
		$result = $upload->uploadFileFromUrl($_POST['img_url']);
	} else {
		$upload->setFiles($_FILES);
		$result = $upload->uploadFiles();
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
		<BR>&emsp;<a style="color:#F0F0F0">Sorry, your file was not uploaded, try a different media type.</a>
	<?php
	// if everything is ok, try to upload file
	else :
	?>
	<!--
		<?= print_r($uploadData, true) ?>
	-->
		<main>
			<div class="title_container">
				<h1>Nostr media uploader</h1>
				<p>Removes metadata, free, nostr focused</p>
				<div class="info_cards">
					<div class="info"><span><?= $total_size_gb ?></span>GB used</div>
					<div class="info"><span><?= $total_files ?></span> total uploads</div>
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

				<button id="copyButton" onclick="myCopyFunction()" class="image_address"> <img src="/assets/copy.png">Copy Media Link</button>

				<script>
					function myCopyFunction() {
						let myText = document.createElement("textarea");
						myText.value = document.getElementById("theList").innerHTML.replace(/&lt;/g, "<").replace(/&gt;/g, ">");
						document.body.appendChild(myText).select();
						document.execCommand('copy');
						document.body.removeChild(myText);
						document.getElementById("copyButton").innerHTML = "Copied!";
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
					else :
					?>
						<img class="uploaded_img" style="max-width: 100%" height="250" src="<?= $uploadData['thumbnail'] ?>" alt="uploaded image" />' . "&nbsp; &nbsp;
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
					<img src="/assets/import_icon.png" alt="" />
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
	<script src="/scripts/upload.js"></script>
</body>

</html>