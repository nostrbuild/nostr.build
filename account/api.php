<?php
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
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/ImageCatalogManager.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/NostrClient.class.php';

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
/*
if ($daysRemaining <= 0) {
	echo json_encode(array("error" => "Please subscribe to a plan"));
	$link->close();
	exit;
}
*/

// Setup S3 service
$s3 = new S3Service($awsConfig);


function listImagesByFolderName($folderName, $link, $start = null, $limit = null)
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
	$imgArray = $images->getFiles($_SESSION['usernpub'], $folderId, $start, $limit);

	$jsonArray = array();

	foreach ($imgArray as $images_row) {
		// Get mime type and image URL
		$type = explode('/', $images_row['mime_type'])[0];
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
			$srcset[] = htmlspecialchars(SiteConfig::getResponsiveUrl('professional_account_image', $resolution) . $filename . " {$width}w");
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
			"mime" => $images_row['mime_type'],
			"size" => $size,
			"sizes" => $type === 'image' ? $sizes : null,
			"srcset" => $type === 'image' ? $srcset : null,
			"width" => $images_row['media_width'],
			"height" => $images_row['media_height'],
			"blurhash" => $type === 'image' ? $images_row['blurhash'] : null,
			"sha256_hash" => $images_row['sha256_hash'],
			"created_at" => $images_row['created_at'],
			"title" => $images_row['title'],
			"ai_prompt" => $type === 'image' ? $images_row['ai_prompt'] : null,
			"loaded" => false,
			"show" => true,
			"associated_notes" => $images_row['associated_notes'] ?? null,
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

	// Import the generate image and return the metadata
	return importMediaFromURL($aiImageURL, "AI: Generated Images", $title, $prompt);
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

	// Construct returned data
	$resolutionToWidth = [
		"240p"  => "426",
		"360p"  => "640",
		"480p"  => "854",
		"720p"  => "1280",
		"1080p" => "1920",
	];
	$res = [
		"id" => $fileData[0]['id'],
		"flag" => 0, // Not shared by default
		"name" => $fileData[0]['name'],
		"mime" => $fileData[0]['mime'],
		"url" => $fileData[0]['url'],
		"thumb" => $fileData[0]['thumbnail'],
		"size" => $fileData[0]['size'],
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
		"show" => true,
		"associated_notes" => null,
	];

	error_log("Imported URL: " . json_encode($res));
	return $res;
}

function getAccountData(): array
{
	global $account;
	$info = $account->getAccount();
	$data = [
		"userId" => $info['id'],
		"name" => $info['nym'],
		"npub" => $info['usernpub'],
		"pfpUrl" => $info['ppic'],
		"wallet" => $info['wallet'],
		"allowNostrLogin" => $info['allow_npub_login'],
		"npubVerified" => $info['npub_verified'],
		"accountLevel" => $info['acctlevel'],
		"accountFlags" => $info['accflags'],
		"remainingDays" => $account->getRemainingSubscriptionDays(),
		"storageUsed" => $account->getUsedStorageSpace(),
		"storageLimit" => $account->getStorageSpaceLimit(),
		"totalStorageLimit" => $account->getStorageSpaceLimit() === PHP_INT_MAX ? "Unlimited" : formatSizeUnits($account->getStorageSpaceLimit()),
	];
	return $data;
}


// Identify what action is being requested, and reply accordingly
// Actions: GET - list_files, GET - list_folders

if (isset($_GET["action"])) {
	$action = $_GET["action"];
	if ($action == "list_files" && isset($_GET["folder"]) && !empty($_GET["folder"])) {
		$start = isset($_GET["start"]) ? intval($_GET["start"]) : null;
		$limit = isset($_GET["limit"]) ? intval($_GET["limit"]) : null;
		// List all files in the user's account
		$files = listImagesByFolderName($_GET["folder"], $link, $start, $limit);
		http_response_code(200);
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
			$firstChar = mb_substr($folderName, 0, 1, 'UTF-8');
			$folderIcon = mb_strlen($firstChar, 'UTF-8') === 1 ? strtoupper($firstChar) : '#';
			return array("name" => $folderName, "icon" => $folderIcon, "route" => $folderRoute, "id" => $folderId, "allowDelete" => true);
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
			$usersFoldersStats = $usersFoldersTable->getFoldersStats($_SESSION['usernpub']);
			// Repackage for JSON response
			$foldersStats = [
				'foldersStats' => $usersFoldersStats['FOLDERS'],
				'totalStats' => $usersFoldersStats['TOTAL'],
			];
			http_response_code(200);
			echo json_encode($foldersStats);
		} catch (Exception $e) {
			error_log($e->getMessage());
			http_response_code(500);
			echo json_encode(array("error" => "Failed to get folders stats"));
		}
	} else {
		http_response_code(400);
		echo json_encode(array("error" => "Invalid action"));
	}
} elseif (isset($_POST['action'])) {
	// Handle AI image generation
	if ($_POST['action'] == "generate_ai_image") {
		// Check if account is expired
		if ($daysRemaining <= 0 || $account->getRemainingStorageSpace() <= 0){
			http_response_code(403);
			echo json_encode(array("error" => "Your account has expired"));
			$link->close();
			exit;
		}
		// Check if the user has permission to generate AI images
		// TODO: Implement per model permissions
		if (!$perm->validatePermissionsLevelAny(1, 10, 99)) {
			http_response_code(403);
			echo json_encode(array("error" => "You do not have permission to generate AI images"));
			$link->close();
			exit;
		}
		// Check if the user has permissions for specific models
		$advancedModels = ["@cf/bytedance/stable-diffusion-xl-lightning", "@cf/stabilityai/stable-diffusion-xl-base-1.0"];
		if (isset($_POST['model']) && in_array($_POST['model'], $advancedModels)) {
			if (!$perm->validatePermissionsLevelAny(10, 99)) {
				http_response_code(403);
				echo json_encode(array("error" => "You do not have permission to generate AI images using the {$_POST['model']} model"));
				$link->close();
				exit;
			}
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
			http_response_code(400);
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
			$link->close();
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
		// Return the list of deleted folders and images
		http_response_code(200);
		echo json_encode(array("action" => "delete", "deletedFolders" => $deletedFolders, "deletedImages" => $deletedImages));
	} elseif ($_POST['action'] === 'share_creator_page') {
		// Check if account is expired
		if ($daysRemaining <= 0) {
			http_response_code(403);
			echo json_encode(array("error" => "Your account has expired"));
			$link->close();
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
		http_response_code(200);
		echo json_encode(array("action" => "move_to_folder", "movedImages" => $movedImages));
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
		error_log("Deleted folders: " . json_encode($deletedFolders));
		http_response_code(200);
		echo json_encode(array("action" => "delete_folders", "deletedFolders" => $deletedFolders));
	} elseif ($_POST['action'] === 'publish_nostr_event') {
		// Check if account is expired
		if ($daysRemaining <= 0) {
			http_response_code(403);
			echo json_encode(array("error" => "Your account has expired"));
			$link->close();
			exit;
		}
		// Check if account level is eligible to publish Nostr events
		if (!$perm->validatePermissionsLevelAny(1, 2, 10, 99)) {
			http_response_code(403);
			echo json_encode(array("error" => "You do not have permission to publish Nostr events"));
			$link->close();
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

		if (!$signedEvent || (empty($mediaIds) && $eventKind !== 5) || ($eventId === 5 && empty($eventIdsToDelete)) || !$eventId || !$eventCreatedAt || !$eventContent) {
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
					// Delete the Nostr event from the database
					$sql = "DELETE FROM users_nostr_notes WHERE usernpub = ? AND note_id = ?";
					$stmt = $link->prepare($sql);
					$stmt->bind_param("ss", $_SESSION['usernpub'], $eventId);
					$stmt->execute();
					$stmt->close();
					// Delete the images associated with the event
					$sql = "DELETE FROM users_nostr_images WHERE usernpub = ? AND note_id = ?";
					$stmt = $link->prepare($sql);
					$stmt->bind_param("ss", $_SESSION['usernpub'], $eventId);
					$stmt->execute();
					$stmt->close();
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
		];
		try {
			$account->updateAccount(
				nym: $profileData['name'] ?? null,
				ppic: $profileData['pfpUrl'] ?? null,
				wallet: $profileData['wallet'] ?? null,
			);
			$account->allowNpubLogin($profileData['allowNostrLogin']);
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
	} else {
		http_response_code(400);
		echo json_encode(array("error" => "Invalid action"));
	}
} else {
	http_response_code(400);
	echo json_encode(array("error" => "No action or folder specified"));
}
