<?php

use Respect\Validation\Rules\Uppercase;

require_once $_SERVER['DOCUMENT_ROOT'] . "/config.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/SiteConfig.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/functions/session.php";
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/UsersImages.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/UsersImagesFolders.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/utils.funcs.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Account.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/MultimediaUpload.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/S3Service.class.php';

global $link;
global $awsConfig;

$perm = new Permission();

// Send JSON content headers
header('Content-Type: application/json');

if (!$perm->validateLoggedin()  || !isset($_SESSION["usernpub"])) {
	// Return error message
	echo json_encode(array("error" => "You are not logged in"));
	$link->close();
	exit;
}

// If account is not verified, redirect to signup page
if ($perm->validatePermissionsLevelEqual(0)) {
	echo json_encode(array("error" => "Please verify your account"));
	$link->close();
	exit;
}

// Instanciate account class
$account = new Account($_SESSION['usernpub'], $link);
$daysRemaining = 0;
try {
	// Handle cases for users who has no subscription yet
	$daysRemaining = $account->getRemainingSubscriptionDays();
} catch (Exception $e) {
	error_log($e->getMessage());
}

// If user has no subscription, redirect to plans page
if ($daysRemaining <= 0) {
	echo json_encode(array("error" => "Please subscribe to a plan"));
	$link->close();
	exit;
}

// Setup S3 service
$s3 = new S3Service($awsConfig);


function listImagesByFolderName($folderName, $link)
{
	error_log("listImagesByFolderName: " . $folderName);
	$folders = new UsersImagesFolders($link);
	$images = new UsersImages($link);
	// Treat "Home: Main Folder" as the root folder
	if ($folderName !== "Home: Main Folder") {
		$folderId = $folders->findFolderByNameOrCreate($_SESSION['usernpub'], $folderName);
	} else {
		$folderId = null;
	}
	$imgArray = $images->getFiles($_SESSION['usernpub'], $folderId);

	$jsonArray = array();

	foreach ($imgArray as $images_row) {
		// Get mime type and image URL
		$type = explode('/', $images_row['mime_type'])[0];
		if ($type != 'image') continue;

		$image = $images_row['image'];

		// Parse URL and get only the filename
		$parsed_url = parse_url($image);
		$filename = pathinfo($parsed_url['path'], PATHINFO_BASENAME);

		// Add 'professional_account_' prefix to the $type
		$professional_type = 'professional_account_' . $type;

		// Use SiteConfig to get the base URL for this type
		try {
			$base_url = SiteConfig::getFullyQualifiedUrl($professional_type);
		} catch (Exception $e) {
			error_log($e->getMessage());
			// Handle exception or use a default URL
			$base_url = SiteConfig::ACCESS_SCHEME . "://" . SiteConfig::DOMAIN_NAME . "/p/"; // default URL in case of error
		}

		$new_url = $base_url . $filename;
		$image_url = $new_url;
		$thumb_url = SiteConfig::getThumbnailUrl($professional_type) . $filename;
		$size = formatSizeUnits($images_row['file_size']);
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
			$srcset[] = htmlspecialchars(SiteConfig::getResponsiveUrl('professional_account_image', $resolution) . $filename . " {$width}w");
		}
		$srcset = implode(", ", $srcset);
		$sizes = '(max-width: 426px) 100vw, (max-width: 640px) 100vw, (max-width: 854px) 100vw, (max-width: 1280px) 50vw, 33vw';
		//$responsiveTag = "<img height="$media_height" width="$media_width" loading="lazy" src="$bh_dataUrl" data-src="$thumbnail_path" data-srcset="$srcset" data-sizes="$sizes" alt="image" />

		array_push($jsonArray, array(
			"id" => $images_row['id'],
			"name" => $filename,
			"url" => $image_url,
			"thumb" => $thumb_url,
			"size" => $size,
			"sizes" => $sizes,
			"srcset" => $srcset,
			"width" => $images_row['media_width'],
			"height" => $images_row['media_height'],
			"blurhash" => $images_row['blurhash'],
			"sha256_hash" => $images_row['sha256_hash'],
			"created_at" => $images_row['created_at'],
			"title" => $images_row['title'],
			"ai_prompt" => $images_row['ai_prompt'],
			"loaded" => false,
		));
	}
	return $jsonArray;
}

function getAndStoreAIGeneratedImage(string $model, string $prompt, string $title): array
{
	global $link;
	global $s3;

	$apiUrl = $_SERVER['AI_GEN_API_ENDPOINT'];

	$requestBody = json_encode([
		"prompt" => $prompt,
		"model" => $model,
		"npub" => $_SESSION['usernpub'],
	]);

	// Generate SHA-256 hash for the request body bytes
	$bodySha256 = hash('sha256', $requestBody);
	error_log("Body SHA256: " . $bodySha256 . PHP_EOL);
	$payload = "POST|{$apiUrl}|{$bodySha256}|" . time();
	error_log("Payload: " . $payload . PHP_EOL);
	// Generate HMAC signature
	$key = hex2bin($_SERVER['AI_GEN_API_HMAC_KEY']);
	$hmac = hash_hmac('sha256', $payload, $_SERVER['AI_GEN_API_HMAC_KEY'], true);
	$base64Hmac = base64_encode($hmac);
	$bearer = "HMAC|SHA256|" . time() . "|" . $base64Hmac;

	// Initialize cURL
	$ch = curl_init($apiUrl);

	// Set cURL options
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
	curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', "Authorization: Bearer {$bearer}"]);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	// Execute the cURL request
	$response = curl_exec($ch);

	// Check for cURL errors
	if ($response === false) {
		$error = curl_error($ch);
		curl_close($ch);
		throw new Exception("cURL request failed: {$error}");
	}

	// Close the cURL handle
	curl_close($ch);

	$responseJson = json_decode($response, true);
	$aiImageURL = $responseJson['URL'];

	// Store the AI generated image in the user's account
	$upload = new MultimediaUpload($link, $s3, true, $_SESSION['usernpub']);
	$upload->setDefaultFolderName("AI: Generated Images");

	try {
		$upload->uploadFileFromUrl(
			url: $aiImageURL,
			title: $title,
			ai_prompt: $prompt,
		);
	} catch (Exception $e) {
		error_log($e->getMessage());
		throw new Exception("Failed to store AI generated image in the user's account");
	}

	// Return the AI generated image metadata
	$fileData = $upload->getUploadedFiles();
	/*
	blurhash : "LWM%_@~q.9%N.8xuWAoIWYxujYay",
	dimensions : {height : 512, width : 512 }
	dimensionsString : "512x512",
	mime : "image/png",
	name : "Z53yk.png",
	original_sha256 : "8226130f4fce8dc2a01789dcf5e07d33a496a98f43650358c2bf7bb84a44332a",
	responsive : {
	240p : "https://i.nostr.build/resp/240p/Z53yk.png",
	360p : "https://i.nostr.build/resp/360p/Z53yk.png",
	480p : "https://i.nostr.build/resp/480p/Z53yk.png",
	720p : "https://i.nostr.build/resp/720p/Z53yk.png",
	1080p : "https://i.nostr.build/resp/1080p/Z53yk.png"
	},
	sha256 : "5bdf421a39b95cb1f723538f50a87872eb9ec4541e2e4bdac80425981de5192c",
	size : 90981,
	thumbnail : "https://i.nostr.build/thumb/Z53yk.png",
	type : "picture",
	url : "https://i.nostr.build/Z53yk.png"
*/
	// Construct returned data
	$resolutionToWidth = [
		"240p"  => "426",
		"360p"  => "640",
		"480p"  => "854",
		"720p"  => "1280",
		"1080p" => "1920",
	];
	$fileNameWithoutExtension = pathinfo($fileData[0]['name'], PATHINFO_FILENAME);
	$aiImage = [
		"id" => $fileNameWithoutExtension,
		"name" => $fileData[0]['name'],
		"url" => $fileData[0]['url'],
		"thumb" => $fileData[0]['thumbnail'],
		"size" => formatSizeUnits($fileData[0]['size']),
		"sizes" => '(max-width: 426px) 100vw, (max-width: 640px) 100vw, (max-width: 854px) 100vw, (max-width: 1280px) 50vw, 33vw',
		"srcset" => implode(", ", array_map(function ($resolution) use ($fileData, $resolutionToWidth) {
			return htmlspecialchars($fileData[0]['responsive'][$resolution] . " {$resolutionToWidth[$resolution]}w");
		}, array_keys($fileData[0]['responsive']))),
		"width" => $fileData[0]['dimensions']['width'],
		"height" => $fileData[0]['dimensions']['height'],
		"blurhash" => $fileData[0]['blurhash'],
		"sha256_hash" => $fileData[0]['original_sha256'],
		"created_at" => date('Y-m-d H:i:s'),
		"title" => $fileData[0]['title'] ?? '',
		"ai_prompt" => $fileData[0]['ai_prompt'] ?? '',
		"loaded" => false,
	];

	return $aiImage;
}


// Identify what action is being requested, and reply accordingly
// Actions: GET - list_files, GET - list_folders

if (isset($_GET["action"])) {
	$action = $_GET["action"];
	if ($action == "list_files" && isset($_GET["folder"]) && !empty($_GET["folder"])) {
		// List all files in the user's account
		$files = listImagesByFolderName($_GET["folder"], $link);
		echo json_encode($files);
	} else if ($action == "list_folders") {
		// List all folders in the user's account
		$folders = new UsersImagesFolders($link);
		$folderList = $folders->getFolders($_SESSION['usernpub']);
		// Process the folder list so it returns array of assoc arrays: name: <name>, icon: <icon>, route: <route>, id: <id>
		$folderList = array_map(function ($folder) {
			$folderName = $folder['folder'];
			$folderId = $folder['id'];
			$folderRoute = "#f=" . urlencode($folderName);
			$folderIcon = strtoupper(substr($folderName, 0, 1));
			return array("name" => $folderName, "icon" => $folderIcon, "route" => $folderRoute, "id" => $folderId);
		}, $folderList);
		echo json_encode($folderList);
	} else {
		echo json_encode(array("error" => "Invalid action"));
	}
} elseif (isset($_POST['action'])) {
	// Handle AI image generation
	if ($_POST['action'] == "generate_ai_image") {
		// Check if the user has permission to generate AI images
		// TODO: Implement per model permissions
		if (!$perm->validatePermissionsLevelAny(10, 99)) {
			echo json_encode(array("error" => "You do not have permission to generate AI images"));
			$link->close();
			exit;
		}
		/*
		// Check if the user has enough credits to generate AI images
		// TODO: Implement a proper credit system
		if ($account->getRemainingAICredits() <= 0) {
			echo json_encode(array("error" => "You do not have enough credits to generate AI images"));
			$link->close();
			exit;
		}
		*/
		// Check if the user has provided the required parameters
		if (!isset($_POST['model']) || !isset($_POST['prompt']) || !isset($_POST['title'])) {
			echo json_encode(array("error" => "Missing required parameters"));
			$link->close();
			exit;
		}
		$model = $_POST['model'];
		$prompt = $_POST['prompt'];
		$title = $_POST['title'];
		// Generate and store the AI image
		try {
			$aiImage = getAndStoreAIGeneratedImage($model, $prompt, $title);
			echo json_encode($aiImage);
		} catch (Exception $e) {
			error_log($e->getMessage());
			echo json_encode(array("error" => "Failed to generate AI image"));
		}
	} else {
		echo json_encode(array("error" => "Invalid action"));
	}
} else {
	echo json_encode(array("error" => "No action or folder specified"));
}
