<?php
require_once $_SERVER['DOCUMENT_ROOT'] . "/config.php";
require_once $_SERVER['DOCUMENT_ROOT'] . "/SiteConfig.php";
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/UsersImages.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/UsersImagesFolders.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/utils.funcs.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Account.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/MultimediaUpload.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/S3Service.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/ImageCatalogManager.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/imageproc.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/NostrClient.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Credits.class.php';

global $link;
global $awsConfig;

$perm = new Permission();

// Send JSON content headers
header('Content-Type: application/json');

if (!$perm->validateLoggedin()  || !isset($_SESSION["usernpub"])) {
	// Return error message
	http_response_code(401);
	echo json_encode(array("error" => "You are not logged in"));
	$link->close(); // CLOSE MYSQL LINK
	exit;
}

// If account is not verified, redirect to signup page
if ($perm->validatePermissionsLevelEqual(0)) {
	echo json_encode(array("error" => "Please verify your account"));
	$link->close(); // CLOSE MYSQL LINK
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
/*
if ($daysRemaining <= 0) {
	echo json_encode(array("error" => "Please subscribe to a plan"));
	$link->close(); // CLOSE MYSQL LINK
	exit;
}
*/

// Setup S3 service
$s3 = new S3Service($awsConfig);


function listImagesByFolderName($folderName, $link, $start = null, $limit = null, $filter = null)
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
	$imgArray = $images->getFiles($_SESSION['usernpub'], $folderId, $start, $limit, $filter);

	$jsonArray = array();

	foreach ($imgArray as $images_row) {
		// Get mime type and image URL
		$type = getFileTypeFromName($images_row['image']);
		if ($type === 'unknown') {
			// Use mime type from database and get the first part of it
			$type = explode('/', $images_row['mime_type'])[0];
		}
		// if ($type != 'image' && $type != 'video' && $type != 'audio') continue;

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
		$size = $images_row['file_size'];
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
			$srcset[] = htmlspecialchars(SiteConfig::getResponsiveUrl($professional_type, $resolution) . $filename . " {$width}w");
		}
		$responsive = [];
		foreach ($resolutionToWidth as $resolution => $width) {
			$responsive[$resolution] = htmlspecialchars(SiteConfig::getResponsiveUrl($professional_type, $resolution) . $filename);
		}
		$srcset = implode(", ", $srcset);
		$sizes = '(max-width: 426px) 100vw, (max-width: 640px) 100vw, (max-width: 854px) 100vw, (max-width: 1280px) 50vw, 33vw';
		//$responsiveTag = "<img height="$media_height" width="$media_width" loading="lazy" src="$bh_dataUrl" data-src="$thumbnail_path" data-srcset="$srcset" data-sizes="$sizes" alt="image" />

		array_push($jsonArray, array(
			"id" => $images_row['id'],
			"flag" => ($images_row['flag'] === '1') ? 1 : 0,
			"name" => $filename,
			"url" => $image_url,
			"thumb" => $type === 'image' ? $thumb_url : null,
			"responsive" => $responsive,
			"mime" => $images_row['mime_type'],
			"size" => $size,
			"sizes" => $type === 'image' ? $sizes : null,
			"srcset" => $type === 'image' ? $srcset : null,
			"width" => $images_row['media_width'] ?? null,
			"height" => $images_row['media_height'] ?? null,
			"media_type" => $type,
			"blurhash" => $type === 'image' ? $images_row['blurhash'] : null,
			"sha256_hash" => $images_row['sha256_hash'],
			"created_at" => $images_row['created_at'],
			"title" => $images_row['title'],
			"ai_prompt" => $type === 'image' ? $images_row['ai_prompt'] : null,
			"description" => $images_row['description'],
			"loaded" => false,
			"show" => true,
			"associated_notes" => $images_row['associated_notes'] ?? null,
		));
	}
	return $jsonArray;
}

function getSDUserCredits(): array
{
	global $link;
	// Remove the last path component from the URL
	$apiBase = substr($_SERVER['AI_GEN_API_ENDPOINT'], 0, strrpos($_SERVER['AI_GEN_API_ENDPOINT'], '/'));

	// Get user npub, level and subscription period
	$credits = new Credits($_SESSION['usernpub'], $apiBase, $_SERVER['AI_GEN_API_HMAC_KEY'], $link);
	$balance = $credits->getCreditsBalance();
	error_log("SD User Credits: " . json_encode($balance));
	// Update session with available credits balance
	$_SESSION['sd_credits'] = $balance['available'];
	return $balance;
}

// SD Core Model API
/*
    prompt: string;
    aspect_ratio?: "21:9" | "16:9" | "3:2" | "5:4" | "1:1" | "4:5" | "2:3" | "9:16" | "9:21";
    negative_prompt?: string;
    seed?: number;
    style_preset?: "enhance" | "anime" | "photographic" | "digital-art" | "comic-book" | "fantasy-art" | "line-art" | "analog-film" | "neon-punk" | "isometric" | "low-poly" | "origami" | "modeling-compound" | "cinematic" | "3d-model" | "pixel-art" | "tile-texture";
*/
function getAndStoreSDCoreGeneratedImage(string $prompt, string $negativePrompt = '', string $ar = '', string $preset = '', int $seed = 0): array
{
	// Validate parameters
	if (empty($prompt) || /* length */ strlen($prompt) > 10000) {
		throw new Exception("Prompt is required and must be less than 10000 characters");
	}
	if (!empty($negativePrompt) && /* length */ strlen($negativePrompt) > 10000) {
		throw new Exception("Negative prompt must be less than 10000 characters");
	}
	if (!empty($ar) && !in_array($ar, ["21:9", "16:9", "3:2", "5:4", "1:1", "4:5", "2:3", "9:16", "9:21"])) {
		throw new Exception("Invalid aspect ratio");
	}
	if (!empty($preset) && !in_array($preset, ["enhance", "anime", "photographic", "digital-art", "comic-book", "fantasy-art", "line-art", "analog-film", "neon-punk", "isometric", "low-poly", "origami", "modeling-compound", "cinematic", "3d-model", "pixel-art", "tile-texture"])) {
		throw new Exception("Invalid style preset");
	}
	// Seed 0 .. 4294967294
	if ($seed < 0 || $seed > 4294967294) {
		throw new Exception("Invalid seed value");
	}
	global $account;
	global $link;
	global $s3;
	if (is_null($account)) {
		$account = new Account($_SESSION['usernpub'], $link);
	}
	// Remove the last path component from the URL
	$apiBase = substr($_SERVER['AI_GEN_API_ENDPOINT'], 0, strrpos($_SERVER['AI_GEN_API_ENDPOINT'], '/'));
	$apiUrl = $apiBase . '/sd/core';

	// Get user npub, level and subscription period
	$usernpub = $account->getNpub();
	$level = $account->getAccountLevelInt();
	$subscriptionPeriod = $account->getSubscriptionPeriod();
	// Construct the request body
	$requestBodyArray = [
		"user_npub" => $usernpub,
		"app_id" => "nostr.build",
		"app_version" => "1.0.0-beta",
		"user_level" => $level,
		"user_sub_period" => $subscriptionPeriod,
		"prompt" => $prompt,
		//"negative_prompt" => $negativePrompt,
		//"aspect_ratio" => $ar,
		//"style_preset" => $preset,
		//"seed" => $seed,
	];
	// Add optional parameters if provided
	if (!empty($negativePrompt)) {
		$requestBodyArray['negative_prompt'] = $negativePrompt;
	}
	if (!empty($ar)) {
		$requestBodyArray['aspect_ratio'] = $ar;
	}
	if (!empty($preset)) {
		$requestBodyArray['style_preset'] = $preset;
	}
	if ($seed > 0) {
		$requestBodyArray['seed'] = $seed;
	}
	$requestBody = json_encode($requestBodyArray);
	$bearer = signApiRequest($_SERVER['AI_GEN_API_HMAC_KEY'], $apiUrl, 'POST', $requestBody);

	// Initialize cURL
	$ch = curl_init($apiUrl);

	$customHeaders = [];  // Array to store custom headers

	// Define a callback function to capture headers
	curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $headerLine) use (&$customHeaders) {
		// Extract the header name and value
		$parts = explode(':', $headerLine, 2);

		if (count($parts) === 2) {
			$headerName = strtolower(trim($parts[0]));
			$headerValue = trim($parts[1]);
			// Add the custom headers you care about
			if (in_array($headerName, ['x-sd-finish-reason', 'x-sd-seed', 'x-sd-available-balance', 'x-sd-debited', 'x-sd-transaction-id'])) {
				$customHeaders[$headerName] = $headerValue;
			}
		}
		return strlen($headerLine);  // This is required for the callback to work
	});


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

	// Depending on the returned HTTP status code, handle the response
	$httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
	if ($httpCode !== 200 && $contentType === 'application/json') {
		throw new Exception("SD Core Image generation failed: HTTP {$httpCode} - {$response}");
	}

	// Close the cURL handle
	curl_close($ch);

	// Get response headers: 
	/* x-sd-finish-reason, x-sd-seed, x-sd-available-balance, x-sd-debited, x-sd-transaction-id */
	// Get the response headers
	error_log("SD Core Image generation response headers: " . json_encode($customHeaders));
	$finishReason = $customHeaders['x-sd-finish-reason'] ?? null;
	$responseSeed = $customHeaders['x-sd-seed'] ?? null;
	$availableBalance = $customHeaders['x-sd-available-balance'] ?? null;
	$responseDebited = $customHeaders['x-sd-debited'] ?? null;
	$transactionId = $customHeaders['x-sd-transaction-id'] ?? '';


	// If we get image/png content type, store it in temporary file
	$tempFile = generateUniqueFilename("ai_image_", sys_get_temp_dir());
	if ($contentType === 'image/png' || $contentType === 'image/jpeg' || $contentType === 'image/webp') {
		file_put_contents($tempFile, $response);
	} else {
		error_log("SD Core Image generation failed: Unexpected content type: {$contentType}: {$response}");
		throw new Exception("SD Core Image generation failed: Unexpected content type: {$contentType}");
	}

	// Import the generate image and return the metadata
	//return importMediaFromURL($aiImageURL, "AI: Generated Images", $title, $prompt);
	$upload = new MultimediaUpload($link, $s3, true, $_SESSION['usernpub']);
	$upload->setDefaultFolderName("AI: Generated Images");
	$aiImages[] = [
		'input_name' => 'ai_image',
		'name' => basename($tempFile),
		'type' => 'image/png',
		'tmp_name' => realpath($tempFile),
		'error' => UPLOAD_ERR_OK, // No error
		'size' => filesize($tempFile),
		'title' => $title ?? '',
		'ai_prompt' => $prompt ?? '',
	];

	$upload->setRawFiles($aiImages);

	// Upload the file
	try {
		[$status, $code, $message] = $upload->uploadFiles(); // Optimize size and generate responsive images
		if (!$status) {
			throw new Exception("Failed to upload SD Core generated image: {$message} {$code}");
		}
	} catch (Exception $e) {
		error_log($e->getMessage());
		throw $e;
	}

	// Return the AI generated image metadata
	$fileData = $upload->getUploadedFiles();
	// If result is empty, return an error
	if (empty($fileData)) {
		throw new Exception("Failed to import media from URL");
	}
	// Extract mediaId from the $fileData
	$mediaId = $fileData[0]['name'];
	// Update transaction with mediaId
	$credits = new Credits($_SESSION['usernpub'], $apiBase, $_SERVER['AI_GEN_API_HMAC_KEY'], $link);
	$credits->updateTransactionWithMediaId($transactionId, $mediaId);

	return getReturnFilesArray($fileData);
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

	// Generate signed bearer token
	$bearer = signApiRequest($_SERVER['AI_GEN_API_HMAC_KEY'], $apiUrl, 'POST', $requestBody);

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

	// Depending on the returned HTTP status code, handle the response
	$httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
	if ($httpCode !== 200 && $contentType === 'application/json') {
		$responseJson = json_decode($response, true);
		throw new Exception("AI Image generation failed: HTTP {$httpCode} - {$responseJson['message']}");
	}

	// If we get image/png content type, store it in temporary file
	$tempFile = generateUniqueFilename("ai_image_", sys_get_temp_dir());
	if ($contentType === 'image/png' || $contentType === 'image/jpeg' || $contentType === 'image/webp') {
		file_put_contents($tempFile, $response);
	} else {
		error_log("AI Image generation failed: Unexpected content type: {$contentType}: {$response}");
		throw new Exception("AI Image generation failed: Unexpected content type: {$contentType}");
	}

	// Import the generate image and return the metadata
	//return importMediaFromURL($aiImageURL, "AI: Generated Images", $title, $prompt);
	$upload = new MultimediaUpload($link, $s3, true, $_SESSION['usernpub']);
	$upload->setDefaultFolderName("AI: Generated Images");
	$aiImages[] = [
		'input_name' => 'ai_image',
		'name' => basename($tempFile),
		'type' => 'image/png',
		'tmp_name' => realpath($tempFile),
		'error' => UPLOAD_ERR_OK, // No error
		'size' => filesize($tempFile),
		'title' => $title ?? '',
		'ai_prompt' => $prompt ?? '',
	];

	$upload->setRawFiles($aiImages);

	// Upload the file
	try {
		[$status, $code, $message] = $upload->uploadFiles(true); // Do not transform AI images
		if (!$status) {
			throw new Exception("Failed to upload AI generated image: {$message} {$code}");
		}
	} catch (Exception $e) {
		error_log($e->getMessage());
		throw $e;
	}

	// Return the AI generated image metadata
	$fileData = $upload->getUploadedFiles();
	// If result is empty, return an error
	if (empty($fileData)) {
		throw new Exception("Failed to import media from URL");
	}

	return getReturnFilesArray($fileData);
}

function importMediaFromURL(string $url, string $folder = '', string $title = '', string $prompt = ''): array
{
	global $link;
	global $s3;

	$upload = new MultimediaUpload($link, $s3, true, $_SESSION['usernpub']);
	if (!empty($folder)) {
		$upload->setDefaultFolderName($folder);
	}

	try {
		$upload->uploadFileFromUrl(
			url: $url,
			title: $title,
			ai_prompt: $prompt,
		);
	} catch (Exception $e) {
		error_log($e->getMessage());
		throw $e;
	}

	// Return the AI generated image metadata
	$fileData = $upload->getUploadedFiles();
	// If result is empty, return an error
	if (empty($fileData)) {
		throw new Exception("Failed to import media from URL");
	}

	return getReturnFilesArray($fileData);
}

function getReturnFilesArray($fileData)
{
	$resolutionToWidth = [
		"240p"  => "426",
		"360p"  => "640",
		"480p"  => "854",
		"720p"  => "1280",
		"1080p" => "1920",
	];
	// Construct returned data
	$res = [
		"id" => $fileData[0]['id'],
		"flag" => 0, // Not shared by default
		"name" => $fileData[0]['name'],
		"mime" => $fileData[0]['mime'],
		"url" => $fileData[0]['url'],
		"thumb" => $fileData[0]['thumbnail'],
		"responsive" => $fileData[0]['responsive'],
		"size" => $fileData[0]['size'],
		"sizes" => '(max-width: 426px) 100vw, (max-width: 640px) 100vw, (max-width: 854px) 100vw, (max-width: 1280px) 50vw, 33vw',
		"srcset" => implode(", ", array_map(function ($resolution) use ($fileData, $resolutionToWidth) {
			return htmlspecialchars($fileData[0]['responsive'][$resolution] . " {$resolutionToWidth[$resolution]}w");
		}, array_keys($fileData[0]['responsive']))),
		"width" => $fileData[0]['dimensions']['width'] ?? null,
		"height" => $fileData[0]['dimensions']['height'] ?? null,
		"media_type" => $fileData[0]['media_type'], // "image", "video", "audio", "document", "archive", "text", "other"
		"blurhash" => $fileData[0]['blurhash'],
		"sha256_hash" => $fileData[0]['original_sha256'],
		"created_at" => date('Y-m-d H:i:s'),
		"title" => $fileData[0]['title'] ?? '',
		"ai_prompt" => $fileData[0]['ai_prompt'] ?? '',
		"description" => $fileData[0]['description'] ?? '',
		"loaded" => false,
		"show" => true,
		"associated_notes" => null,
	];

	return $res;
}

function getAccountData(): array
{
	global $link;
	global $account;
	if (is_null($account)) {
		$account = new Account($_SESSION['usernpub'], $link);
	}
	$info = $account->getAccount();
	$credits = getSDUserCredits();
	$data = [
		"userId" => $info['id'],
		"name" => $info['nym'],
		"npub" => $info['usernpub'],
		"pfpUrl" => $info['ppic'],
		"wallet" => $info['wallet'],
		"defaultFolder" => $info['default_folder'] ?? "",
		"allowNostrLogin" => $info['allow_npub_login'],
		"npubVerified" => $info['npub_verified'],
		"accountLevel" => $info['acctlevel'],
		"accountFlags" => $info['accflags'],
		"remainingDays" => $account->getRemainingSubscriptionDays(),
		"storageUsed" => $account->getUsedStorageSpace(),
		"storageLimit" => $account->getStorageSpaceLimit(),
		"totalStorageLimit" => $account->getStorageSpaceLimit() === PHP_INT_MAX ? "Unlimited" : formatSizeUnits($account->getStorageSpaceLimit()),
		"availableCredits" => $credits['available'],
		"debitedCredits" => $credits['debited'] ?? 0,
		"creditedCredits" => $credits['credited'] ?? 0,
		"referralCode" => $account->getAccountReferralCode()
	];
	return $data;
}

function getMediaStats(string $mediaId, string $period, string $interval, string $groupBy): string
{
	global $link;
	$userNpub = $_SESSION['usernpub'];
	$mediaIdInt = intval($mediaId);
	$sql = "SELECT * FROM users_images WHERE id = ? AND usernpub = ?";
	$stmt = $link->prepare($sql);
	$stmt->bind_param('is', $mediaIdInt, $userNpub);
	$stmt->execute();
	$result = $stmt->get_result();
	if ($result->num_rows === 0) {
		return json_encode(array("error" => "Media not found"));
	}
	$row = $result->fetch_assoc();
	$type = getFileTypeFromName($row['image']);
	if ($type === 'unknown') {
		// Use mime type from database and get the first part of it
		$type = explode('/', $row['mime_type'])[0];
	}
	$mediaURL = SiteConfig::getFullyQualifiedUrl("professional_account_{$type}") . $row['image'];
	$statsURL = "{$mediaURL}/stats";
	// Construct the request URL with query parameters
	$statsURL .= "?period={$period}&interval={$interval}&group_by={$groupBy}";
	error_log("\nGetting media stats: {$statsURL}\n");
	$bearer = signApiRequest($_SERVER['NB_HMAC_SECRETS'], $statsURL, 'GET');
	// Initialize cURL
	$ch = curl_init($statsURL);
	// Set cURL options
	curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$bearer}"]);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	// Execute the cURL request
	$response = curl_exec($ch);
	// Check for cURL errors
	if ($response === false) {
		$error = curl_error($ch);
		curl_close($ch);
		return json_encode(array("error" => "cURL request failed: {$error}"));
	}
	// Close the cURL handle
	curl_close($ch);
	// Return the response
	return $response;
}

// Identify what action is being requested, and reply accordingly
// Actions: GET - list_files, GET - list_folders

if (isset($_GET["action"])) {
	$action = $_GET["action"];
	if ($action == "list_files" && isset($_GET["folder"]) && !empty($_GET["folder"])) {
		$start = isset($_GET["start"]) ? intval($_GET["start"]) : null;
		$limit = isset($_GET["limit"]) ? intval($_GET["limit"]) : null;
		$filter = isset($_GET["filter"]) ? $_GET["filter"] : null;
		// Check if the filter one of the allowed values
		if ($filter !== null && !in_array($filter, ["all", "images", "videos", "audio", 'gifs', 'documents', 'archives', 'others'])) {
			http_response_code(400);
			echo json_encode(array("error" => "Invalid filter value"));
			exit;
		}
		// List all files in the user's account
		$files = listImagesByFolderName($_GET["folder"], $link, $start, $limit, $filter);
		http_response_code(200);
		echo json_encode($files);
	} else if ($action == "list_folders") {
		// List all folders in the user's account
		$folders = new UsersImagesFolders($link);
		$folderList = $folders->getFoldersWithStats($_SESSION['usernpub']);
		// Process the folder list so it returns array of assoc arrays: name: <name>, icon: <icon>, route: <route>, id: <id>
		// | folder               | id   | allSize    | all | imageSize | images | gifSize   | gifs | videoSize  | videos | audioSize | audio | publicCount |
		$folderList = array_map(function ($folder) {
			$folderName = $folder['folder'];
			$folderId = $folder['id'];
			$folderRoute = "#f=" . urlencode($folderName);
			$firstChar = mb_substr($folderName, 0, 1, 'UTF-8');
			$folderIcon = mb_strlen($firstChar, 'UTF-8') === 1 ? strtoupper($firstChar) : '#';
			return [
				"name" => $folderName,
				"icon" => $folderIcon,
				"route" => $folderRoute,
				"id" => $folderId,
				"allowDelete" => true,
				"stats" => [
					"allSize" => (int) $folder['allSize'] ?? 0,
					"all" => (int) $folder['all']	?? 0,
					"imagesSize" => (int) $folder['imageSize'] ?? 0,
					"images" => (int) $folder['images'] ?? 0,
					"gifsSize" => (int) $folder['gifSize'] ?? 0,
					"gifs" => (int) $folder['gifs'] ?? 0,
					"videosSize" => (int) $folder['videoSize'] ?? 0,
					"videos" => (int) $folder['videos'] ?? 0,
					"audioSize" => (int) $folder['audioSize'] ?? 0,
					"audio" => (int) $folder['audio'] ?? 0,
					"documentsSize" => (int) $folder['documentSize'] ?? 0,
					"documents" => (int) $folder['documents'] ?? 0,
					"archivesSize" => (int) $folder['archiveSize'] ?? 0,
					"archives" => (int) $folder['archives'] ?? 0,
					"othersSize" => (int) $folder['otherSize'] ?? 0,
					"others" => (int) $folder['others'] ?? 0,
					"publicCount" => (int) $folder['publicCount'] ?? 0,
				],
			];
		}, $folderList);
		http_response_code(200);
		echo json_encode($folderList);
	} else if ($action == "get_npub_profile") {
		error_log("Getting npub profile");
		http_response_code(200);
		echo json_encode(array("error" => "Not implemented yet"));
	} else if ($action == "get_profile_info") {
		error_log("Getting profile info");
		$data = getAccountData();
		http_response_code(200);
		echo json_encode($data);
	} else if ($action == "get_folders_stats") {
		error_log("Getting folders stats");
		// Fetch user's folder statistics and storage statistics
		try {
			$usersFoldersTable = new UsersImagesFolders($link);
			$usersFoldersStats = $usersFoldersTable->getTotalStats($_SESSION['usernpub']);
			// Repackage for JSON response
			$foldersStats = [
				'totalStats' => $usersFoldersStats,
			];
			http_response_code(200);
			echo json_encode($foldersStats);
		} catch (Exception $e) {
			error_log($e->getMessage());
			http_response_code(500);
			echo json_encode(array("error" => "Failed to get folders stats"));
		}
	} else if ($action == "get_credits_tx_history") {
		// Get the transaction history for the user's credits
		try {
			$apiBase = substr($_SERVER['AI_GEN_API_ENDPOINT'], 0, strrpos($_SERVER['AI_GEN_API_ENDPOINT'], '/'));
			$credits = new Credits($_SESSION['usernpub'], $apiBase, $_SERVER['AI_GEN_API_HMAC_KEY'], $link);
			// Check if type is set
			$txType = isset($_GET['type']) ? $_GET['type'] : "all";
			$txLimit = isset($_GET['limit']) ? intval($_GET['limit']) : null;
			$txOffset = isset($_GET['offset']) ? intval($_GET['offset']) : null;
			$txHistory = $credits->getTransactionsHistory($txType, $txLimit, $txOffset);
			http_response_code(200);
			echo json_encode($txHistory);
		} catch (Exception $e) {
			error_log($e->getMessage());
			http_response_code(500);
			echo json_encode(array("error" => "Failed to get credits transaction history"));
		}
	} else if ($action == "get_credits_invoice") {
		// Get the invoice for the user's credits
		$creditsAmount = isset($_GET['credits']) ? intval($_GET['credits']) : 0;
		// If no ammount is provided, return an error, http-400
		if ($creditsAmount <= 0) {
			http_response_code(400);
			echo json_encode(array("error" => "Invalid credits amount"));
			exit;
		}
		try {
			$apiBase = substr($_SERVER['AI_GEN_API_ENDPOINT'], 0, strrpos($_SERVER['AI_GEN_API_ENDPOINT'], '/'));
			$credits = new Credits($_SESSION['usernpub'], $apiBase, $_SERVER['AI_GEN_API_HMAC_KEY'], $link);
			$invoice = $credits->getInvoice($creditsAmount);
			http_response_code(200);
			echo json_encode($invoice);
		} catch (Exception $e) {
			error_log($e->getMessage());
			http_response_code(500);
			echo json_encode(array("error" => "Failed to get credits invoice"));
		}
	} else if ($action == "get_credits_balance") {
		try {
			$credits = getSDUserCredits();
			http_response_code(200);
			echo json_encode($credits);
		} catch (Exception $e) {
			error_log($e->getMessage());
			http_response_code(500);
			echo json_encode(array("error" => "Failed to get credits balance"));
		}
	} else if ($action == "get_media_stats") {
		// Check if account is expired
		if ($daysRemaining <= 0) {
			http_response_code(403);
			echo json_encode(array("error" => "Your account has expired"));
			$link->close(); // CLOSE MYSQL LINK
			exit;
		}
		// Check user level and only allow 1, 10, 99
		if (!$perm->validatePermissionsLevelAny(1, 2, 10, 99)) {
			http_response_code(403);
			echo json_encode(array("error" => "You do not have permission to get media stats"));
			exit;
		}
		$mediaId = isset($_GET['media_id']) ? $_GET['media_id'] : null;
		$period = isset($_GET['period']) ? $_GET['period'] : "1h";
		$interval = isset($_GET['interval']) ? $_GET['interval'] : "1m";
		$groupBy = isset($_GET['group_by']) ? $_GET['group_by'] : "time";
		if (empty($mediaId) || !is_numeric($mediaId) || intval($mediaId) <= 0) {
			http_response_code(400);
			echo json_encode(array("error" => "Missing media_id parameter"));
			exit;
		}
		try {
			$mediaStats = getMediaStats($mediaId, $period, $interval, $groupBy);
			// Parse JSON and throw error if it's not valid
			$json = json_decode($mediaStats, true, 512, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_IGNORE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			// Extract results from the JSON if success is true and status is 200
			if ($json['success'] !== true || $json['status'] !== 200) {
				throw new Exception("Failed to get media stats");
			}
			$resultString = json_encode($json['results'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
			http_response_code(200);
			echo json_encode($resultString);
		} catch (Exception $e) {
			error_log($e->getMessage());
			http_response_code(500);
			echo json_encode(array("error" => "Failed to get media stats"));
		}
	} else {
		http_response_code(400);
		echo json_encode(array("error" => "Invalid action"));
	}
} elseif (isset($_POST['action'])) {
	// Handle AI image generation
	if ($_POST['action'] == "generate_ai_image") {
		// Check if account is expired
		if ($daysRemaining <= 0 || $account->getRemainingStorageSpace() <= 0) {
			http_response_code(403);
			echo json_encode(array("error" => "Your account has expired"));
			$link->close(); // CLOSE MYSQL LINK
			exit;
		}
		// Check if the user has permission to generate AI images
		// TODO: Implement per model permissions
		if (!$perm->validatePermissionsLevelAny(2, 1, 10, 99)) {
			http_response_code(403);
			echo json_encode(array("error" => "You do not have permission to generate AI images"));
			$link->close(); // CLOSE MYSQL LINK
			exit;
		}
		// Check for Creators Account Models access
		$creatorsModels = ["@cf/bytedance/stable-diffusion-xl-lightning", "@cf/stabilityai/stable-diffusion-xl-base-1.0"];
		if (isset($_POST['model']) && in_array($_POST['model'], $creatorsModels)) {
			if (!$perm->validatePermissionsLevelAny(2, 1, 10, 99)) { // All current plans
				http_response_code(403);
				echo json_encode(array("error" => "You do not have permission to generate AI images using the {$_POST['model']} model"));
				$link->close(); // CLOSE MYSQL LINK
				exit;
			}
		}
		// Check for Advanced Models access
		$advancedModels = ["@cf/black-forest-labs/flux-1-schnell"];
		if (isset($_POST['model']) && in_array($_POST['model'], $advancedModels)) {
			if (!$perm->validatePermissionsLevelAny(1, 10, 99)) {
				http_response_code(403);
				echo json_encode(array("error" => "You do not have permission to generate AI images using the {$_POST['model']} model"));
				$link->close(); // CLOSE MYSQL LINK
				exit;
			}
		}

		// Check if the user has provided the required parameters
		if (!isset($_POST['model']) || !isset($_POST['prompt']) || !isset($_POST['title'])) {
			http_response_code(400);
			echo json_encode(array("error" => "Missing required parameters"));
			$link->close(); // CLOSE MYSQL LINK
			exit;
		}
		error_log(('POST: ' . json_encode($_POST)));
		$model = $_POST['model'];
		$prompt = $_POST['prompt'];
		$title = $_POST['title'];
		$negativePrompt = isset($_POST['negative_prompt']) ? $_POST['negative_prompt'] : '';
		$ar = isset($_POST['aspect_ratio']) ? $_POST['aspect_ratio'] : '';
		$preset = isset($_POST['style_preset']) ? $_POST['style_preset'] : '';
		// Check if the user has enough credits to generate AI images
		// Only for @sd/core model, requires 3 credits
		if ($model === "@sd/core" && intval($_SESSION['sd_credits']) <= 3) {
			echo json_encode(array("error" => "You do not have enough credits to generate AI images"));
			exit;
		}
		// Generate and store the AI image
		try {
			if ($model === "@sd/core") {
				$aiImage = getAndStoreSDCoreGeneratedImage($prompt, $negativePrompt, $ar, $preset);
				$_SESSION['sd_credits'] -= 3;
			} else {
				$aiImage = getAndStoreAIGeneratedImage($model, $prompt, $title);
			}
			http_response_code(200);
			echo json_encode($aiImage);
		} catch (Exception $e) {
			error_log($e->getMessage());
			http_response_code(500);
			echo json_encode(array("error" => "Failed to generate AI image"));
		}
	} elseif ($_POST['action'] === 'import_from_url') {
		// Check if account is expired
		if ($daysRemaining <= 0 || $account->getRemainingStorageSpace() <= 0) {
			http_response_code(403);
			echo json_encode(array("error" => "Your account has expired"));
			$link->close(); // CLOSE MYSQL LINK
			exit;
		}
		// Check if we got the required parameters
		if (!isset($_POST['url']) || !isset($_POST['folder'])) {
			http_response_code(400);
			echo json_encode(array("error" => "Missing required parameters"));
			exit;
		}
		// Validate that the URL is a valid URL
		$url = $_POST['url'];
		if (!filter_var($url, FILTER_VALIDATE_URL)) {
			http_response_code(400);
			echo json_encode(array("error" => "Invalid URL"));
			exit;
		}
		try {
			// Import the media from the URL
			$media = importMediaFromURL($url, $_POST['folder']);
			http_response_code(200);
			echo json_encode($media);
		} catch (Exception $e) {
			error_log($e->getMessage());
			http_response_code(500);
			echo json_encode(array("error" => "Failed to import media from URL"));
		}
	} elseif ($_POST['action'] === 'delete') {
		// Get the lists of folders and images to delete
		$foldersToDelete = !empty($_POST['foldersToDelete']) ? json_decode($_POST['foldersToDelete']) : [];
		$imagesToDelete = !empty($_POST['imagesToDelete']) ? json_decode($_POST['imagesToDelete']) : [];
		error_log("Folders to delete: " . json_encode($foldersToDelete));
		error_log("Images to delete: " . json_encode($imagesToDelete));
		// instantiate ImageCatalogManager class
		$icm = new ImageCatalogManager($link, $s3, $_SESSION['usernpub']);
		$deletedFolders = $icm->deleteFolders($foldersToDelete);
		$deletedImages = $icm->deleteImages($imagesToDelete);
		// Convert Ids to integers
		$deletedFolders = array_map('intval', $deletedFolders);
		$deletedImages = array_map('intval', $deletedImages);
		// Return the list of deleted folders and images
		http_response_code(200);
		echo json_encode(array("action" => "delete", "deletedFolders" => $deletedFolders, "deletedImages" => $deletedImages));
	} elseif ($_POST['action'] === 'share_creator_page') {
		// Check if account is expired
		if ($daysRemaining <= 0) {
			http_response_code(403);
			echo json_encode(array("error" => "Your account has expired"));
			$link->close(); // CLOSE MYSQL LINK
			exit;
		}
		// Check permissions
		if (!$perm->validatePermissionsLevelAny(1, 10, 99)) {
			http_response_code(403);
			echo json_encode(array("error" => "You do not have permission to share images"));
			exit;
		}
		$imagesToShare = !empty($_POST['imagesToShare']) ? (array)json_decode($_POST['imagesToShare']) : [];
		$shareFlag = !empty($_POST['shareFlag']) ? $_POST['shareFlag'] === 'true' : true;
		if (empty($imagesToShare)) {
			http_response_code(400);
			echo json_encode(array("error" => "No images to share"));
			exit;
		}
		// Instantiate ImageCatalogManager class
		$icm = new ImageCatalogManager($link, $s3, $_SESSION['usernpub']);
		$sharedImages = $icm->shareImage($imagesToShare, (bool)$shareFlag);
		// Convert Ids to integers
		$sharedImages = array_map('intval', $sharedImages);
		http_response_code(200);
		echo json_encode(array("action" => "share_creator_page", "sharedImages" => $sharedImages));
	} elseif ($_POST['action'] === 'move_to_folder') {
		$imagesToMove = !empty($_POST['imagesToMove']) ? json_decode($_POST['imagesToMove']) : [];
		$destinationFolderId = !empty($_POST['destinationFolderId']) ? $_POST['destinationFolderId'] : null;
		if (empty($imagesToMove)) {
			http_response_code(400);
			echo json_encode(array("error" => "Missing required parameters"));
			exit;
		}
		// Convert destinationFolderId to integer
		$destinationFolderId = (int)$destinationFolderId;
		// Instantiate ImageCatalogManager class
		$icm = new ImageCatalogManager($link, $s3, $_SESSION['usernpub']);
		$movedImages = $icm->moveImages($imagesToMove, $destinationFolderId);
		// Convert Ids to integers
		$movedImages = array_map('intval', $movedImages);
		http_response_code(200);
		echo json_encode(array("action" => "move_to_folder", "movedImages" => $movedImages));
	} elseif ($_POST['action'] === 'rename_folders') {
		error_log("Renaming folders");
		$folderToRename = !empty($_POST['foldersToRename']) ? json_decode($_POST['foldersToRename']) : null;
		$folderName = !empty($_POST['folderNames']) ? json_decode($_POST['folderNames']) : null;
		if (empty($folderToRename) || empty($folderName)) {
			http_response_code(400);
			echo json_encode(array("error" => "Missing required parameters"));
			exit;
		}
		// Convert folder IDs to integers
		$foldersToRename = intval($folderToRename);
		// Instantiate ImageCatalogManager class
		$icm = new ImageCatalogManager($link, $s3, $_SESSION['usernpub']);
		$renamedFolders = $icm->renameFolder($folderToRename, $folderNames);
		// Convert Ids to integers
		$renamedFolders = array_map('intval', $renamedFolders);
		http_response_code(200);
		echo json_encode(array("action" => "rename_folders", "renamedFolders" => $renamedFolders));
	} elseif ($_POST['action'] === 'delete_folders') {
		error_log("Deleting folders");
		$foldersToDelete = !empty($_POST['foldersToDelete']) ? json_decode($_POST['foldersToDelete']) : [];
		error_log("Folders to delete: " . json_encode($foldersToDelete));
		if (empty($foldersToDelete)) {
			http_response_code(400);
			echo json_encode(array("error" => "No folders to delete"));
			exit;
		}
		// Convert folder IDs to integers
		$foldersToDelete = array_map('intval', $foldersToDelete);
		// Instantiate ImageCatalogManager class
		$icm = new ImageCatalogManager($link, $s3, $_SESSION['usernpub']);
		$deletedFolders = $icm->deleteFolders($foldersToDelete);
		// Convert Ids to integers
		$deletedFolders = array_map('intval', $deletedFolders);
		error_log("Deleted folders: " . json_encode($deletedFolders));
		http_response_code(200);
		echo json_encode(array("action" => "delete_folders", "deletedFolders" => $deletedFolders));
	} elseif ($_POST['action'] === 'update_media_metadata') {
		error_log("Updating media metadata");
		$mediaId = !empty($_POST['mediaId']) ? intval($_POST['mediaId']) : null;
		// Allow empty title and description
		$title = !empty($_POST['title']) ? $_POST['title'] : '';
		$description = !empty($_POST['description']) ? $_POST['description'] : '';
		// We must have mediaId and at least one of title or description
		if ($mediaId === null) {
			http_response_code(400);
			echo json_encode(array("error" => "Missing required parameters"));
			exit;
		}
		// Instantiate ImageCatalogManager class
		$icm = new ImageCatalogManager($link, $s3, $_SESSION['usernpub']);
		$updatedMedia = $icm->updateMediaMetadata($mediaId, $title, $description);
		http_response_code(200);
		echo json_encode(array("action" => "update_media_metadata", "updatedMedia" => $updatedMedia));
	} elseif ($_POST['action'] === 'publish_nostr_event') {
		// Check if account is expired
		if ($daysRemaining <= 0) {
			http_response_code(403);
			echo json_encode(array("error" => "Your account has expired"));
			$link->close(); // CLOSE MYSQL LINK
			exit;
		}
		// Check if account level is eligible to publish Nostr events
		if (!$perm->validatePermissionsLevelAny(1, 2, 10, 99)) {
			http_response_code(403);
			echo json_encode(array("error" => "You do not have permission to publish Nostr events"));
			$link->close(); // CLOSE MYSQL LINK
			exit;
		}
		error_log("Publishing Nostr event");

		$signedEvent = !empty($_POST['event']) ? $_POST['event'] : null;
		$mediaIds = !empty($_POST['mediaIds']) ? json_decode($_POST['mediaIds']) : [];
		$eventId = !empty($_POST['eventId']) ? $_POST['eventId'] : null;
		$eventCreatedAt = !empty($_POST['eventCreatedAt']) ? $_POST['eventCreatedAt'] : null;
		$eventContent = !empty($_POST['eventContent']) ? $_POST['eventContent'] : null;
		// Grab event kind frim the signed event
		$event = json_decode($signedEvent, true);
		$eventKind = $event['kind'];
		// tags => [['e', '<event id to delete>'], ['e', '<event id to delete>'], ...]
		$eventIdsToDelete = $eventKind === 5 ? array_map(function ($tag) {
			return $tag[1];
		}, array_filter($event['tags'], function ($tag) {
			return $tag[0] === 'e';
		})) : [];

		if (!$signedEvent || (empty($mediaIds) && $eventKind !== 5) || ($eventKind === 5 && empty($eventIdsToDelete)) || !$eventId || !$eventCreatedAt || !$eventContent) {
			http_response_code(400);
			echo json_encode(array("error" => "No event to publish or delete"));
			exit;
		}
		error_log("Event: " . $signedEvent);
		// Instantiate NostrClient class
		$nc = new NostrClient($_SERVER['NB_API_NOSTR_CLIENT_SECRET'], $_SERVER['NB_API_NOSTR_CLIENT_URL']);
		// Publish the Nostr event
		$pubRes = $nc->sendPresignedNote($signedEvent);
		if (!$pubRes) {
			http_response_code(500);
			echo json_encode(array("error" => "Failed to publish Nostr event"));
			exit;
		}

		try {
			// Start the transaction
			$link->begin_transaction(MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);

			switch ($eventKind) {
				case 5:
					// Lopp through the event IDs to delete
					// Prepare the SQL statement for deleting Nostr events
					$sqlDeleteEvent = "DELETE FROM users_nostr_notes WHERE usernpub = ? AND note_id = ?";
					$stmtDeleteEvent = $link->prepare($sqlDeleteEvent);

					// Prepare the SQL statement for deleting images associated with Nostr events
					$sqlDeleteImage = "DELETE FROM users_nostr_images WHERE usernpub = ? AND note_id = ?";
					$stmtDeleteImage = $link->prepare($sqlDeleteImage);

					foreach ($eventIdsToDelete as $eventToDelete) {
						error_log("Deleting Nostr event from the database, event id {$eventToDelete}");

						// Bind parameters and execute for deleting the event
						$stmtDeleteEvent->bind_param("ss", $_SESSION['usernpub'], $eventToDelete);
						$stmtDeleteEvent->execute();

						// Bind parameters and execute for deleting associated images
						$stmtDeleteImage->bind_param("ss", $_SESSION['usernpub'], $eventToDelete);
						$stmtDeleteImage->execute();
					}

					// Close the statements after the loop
					$stmtDeleteEvent->close();
					$stmtDeleteImage->close();
					break;
				case 1:
					// Store the Nostr event in the database, or delete it if it is kind 5 (delete)
					$sql = "INSERT INTO users_nostr_notes (usernpub, note_id, created_at, content, full_json) VALUES (?, ?, FROM_UNIXTIME(?), ?, ?)";
					$stmt = $link->prepare($sql);
					$stmt->bind_param("ssiss", $_SESSION['usernpub'], $eventId, $eventCreatedAt, $eventContent, $signedEvent);
					$stmt->execute();
					$stmt->close();

					// Prepare the statement for the images
					$sql = "INSERT INTO users_nostr_images (usernpub, note_id, image_id) VALUES (?, ?, ?)";
					$stmt = $link->prepare($sql);
					// Bind the parameters and execute looping through the media IDs
					foreach ($mediaIds as $imageId) {
						$stmt->bind_param("ssi", $_SESSION['usernpub'], $eventId, $imageId);
						$stmt->execute();
					}
					$stmt->close();
					break;
				default:
					$link->rollback();
					http_response_code(400);
					echo json_encode(array("error" => "Invalid event kind"));
					exit;
			}
			// Commit the transaction
			$link->commit();
		} catch (Exception $e) {
			error_log($e->getMessage());
			// Rollback the transaction
			$link->rollback();
			// Set http-500 status code
			http_response_code(500); // This is a workaround until MySQL DB is gone, because
			echo json_encode(array("error" => "Failed to store Nostr event in the database"));
			exit;
		}

		// Construct the array that will index on each media ID, and have "<note_id>:<created_at>" as the value
		$mediaEvents = array_combine($mediaIds, array_fill(0, count($mediaIds), "{$eventId}:{$eventCreatedAt}"));

		http_response_code(200);
		echo json_encode(array(
			"action" => "publish_nostr_event",
			"success" => true,
			"noteId" => $eventId,
			"createdAt" => $eventCreatedAt,
			"mediaIds" => $mediaIds,
			"mediaEvents" => $mediaEvents,
			"deletedEvents" => $eventIdsToDelete,
		));
	} elseif ($_POST['action'] === 'update_profile') {
		error_log("Updating profile");
		//DEBUG
		error_log("POST: " . json_encode($_POST));
		// Extract the profile data from the POST request
		$profileData = [
			"name" => !empty($_POST['name']) ? $_POST['name'] : null,
			"pfpUrl" => !empty($_POST['pfpUrl']) ? $_POST['pfpUrl'] : null,
			"wallet" => !empty($_POST['wallet']) ? $_POST['wallet'] : null,
			"allowNostrLogin" => !empty($_POST['allowNostrLogin']) ? $_POST['allowNostrLogin'] === 'true' : false,
			"defaultFolder" => !empty($_POST['defaultFolder']) ? $_POST['defaultFolder'] : '',
		];
		try {
			$account->updateAccount(
				nym: $profileData['name'] ?? null,
				ppic: $profileData['pfpUrl'] ?? null,
				wallet: $profileData['wallet'] ?? null,
				default_folder: $profileData['defaultFolder'],
			);
			$account->allowNpubLogin($profileData['allowNostrLogin']); // This triggers Blossom API call
			$data = getAccountData();
			http_response_code(200);
			echo json_encode($data);
		} catch (Exception $e) {
			error_log($e->getMessage());
			// Set http-500 status code
			http_response_code(500); // This is a workaround until MySQL DB is gone, because it is shit.
			echo json_encode(array("error" => "Failed to update profile"));
		}
	} elseif ($_POST['action'] === 'update_password') {
		error_log("Updating password");
		// Get the password data from the POST request
		$currentPassword = !empty($_POST['password']) ? $_POST['password'] : null;
		$newPassword = !empty($_POST['newPassword']) ? $_POST['newPassword'] : null;
		$res = $account->changePasswordSafe($currentPassword, $newPassword);
		if ($res) {
			http_response_code(200);
			echo json_encode(array("action" => "update_password", "success" => $res));
		} else {
			http_response_code(400);
			echo json_encode(array("error" => "Failed to update password"));
		}
	} elseif ($_POST['action'] === 'upload_video_poster') {
		// Verify posted parameters
		$fileId = $_POST['fileId'] ?? 0;
		if (!$fileId) {
			http_response_code(400);
			echo json_encode(array("error" => "File ID is missing"));
			exit;
		}
		if (!isset($_FILES['file'])) {
			http_response_code(400);
			echo json_encode(array("error" => "No file uploaded"));
			exit;
		}
		$uploadedFile = $_FILES['file'];
		// Check if the file is an image
		if (!in_array($uploadedFile['type'], ['image/jpeg'])) {
			http_response_code(400);
			echo json_encode(array("error" => "Invalid file type"));
			exit;
		}
		// Get the info about the video file from the database
		// DEBUG logs
		error_log("File ID: " . $fileId);
		error_log("Uploaded file: " . json_encode($uploadedFile));

		try {
			global $link;
			global $awsConfig;
			$images = new UsersImages($link);
			$videoInfo = $images->getFile(npub: $_SESSION['usernpub'], fileId: $fileId);
			$videoURL = SiteConfig::getFullyQualifiedUrl("professional_account_video") . $videoInfo['image'];
			if (!$videoInfo) {
				http_response_code(404);
				echo json_encode(array("error" => "Video not found"));
				exit;
			}
			// Optimize the image
			$imageProcessor = new ImageProcessor($uploadedFile['tmp_name']);
			$imageProcessor->save();
			$posterDimensions = $imageProcessor->getImageDimensions();
			$imageProcessor->optimiseImage();
			// Update the video file dimensions in the database
			$images->update($fileId, ['media_width' => $posterDimensions['width'], 'media_height' => $posterDimensions['height']]);
			$sha256 = hash_file('sha256', $uploadedFile['tmp_name']);
			// Upload the image to the object storage
			$objectKey = "{$videoInfo['image']}/poster.jpg";
			$objectBucketSuffix = SiteConfig::getBucketSuffix("professional_account_video");
			$objectBucket = $awsConfig['r2']['bucket'] . $objectBucketSuffix;
			// DEBUG logs
			error_log("Object key: " . $objectKey . " | Object bucket: " . $objectBucket . " | SHA256: " . $sha256 . " | NPUB: " . $_SESSION['usernpub'] . " | Video URL: " . $videoURL . PHP_EOL);
			// Upload the image to the object storage
			$res = storeToR2Bucket(
				$uploadedFile['tmp_name'],
				$objectKey,
				$objectBucket,
				$awsConfig['r2']['endpoint'],
				$awsConfig['r2']['credentials']['key'],
				$awsConfig['r2']['credentials']['secret'],
				[
					'sha256' => $sha256,
					'npub' => $_SESSION['usernpub'],
					'videoUrl' => $videoURL,
				],
			);
			if (!$res) {
				error_log("Failed to upload video poster for: " . $videoURL);
				throw new Exception("Failed to upload video poster");
			}
			// Purge cache
			$purger = new CloudflarePurger($_SERVER['NB_API_SECRET'], $_SERVER['NB_API_PURGE_URL']);
			$purger->purgeFiles($objectKey, true);
			// Return the URL of the uploaded image
			$posterURL = $videoURL . "/poster.jpg";
			http_response_code(200);
			echo json_encode(array("posterURL" => $posterURL, "dimensions" => $posterDimensions));
		} catch (Exception $e) {
			error_log($e->getMessage());
			http_response_code(500);
			echo json_encode(array("error" => "Failed to upload video poster"));
			exit;
		}
	} else {
		http_response_code(400);
		echo json_encode(array("error" => "Invalid action"));
	}
} else {
	http_response_code(400);
	echo json_encode(array("error" => "No action or folder specified"));
}
