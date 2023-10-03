<?php
// Include config file
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/S3Service.class.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/libs/MultimediaUpload.class.php');

// TODO: Migrate to a new APIv2 (still in development)
global $awsConfig;
// Instantiates S3Service class
$s3 = new S3Service($awsConfig);
// Instantiates MultimediaUpload class
$upload = new MultimediaUpload($link, $s3);
// Set the $_FILES array or initiate file download from the URL
$error = "Success";
if (isset($_POST['img_url']) && !empty($_POST['img_url'])) {
  $result = $upload->uploadFileFromUrl($_POST['img_url']);
} else {
  try {
    $upload->setFiles($_FILES);
    $result = $upload->uploadFiles();
  } catch (Exception $e) {
    $error = $e->getMessage();
    $result = false;
  }
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

header('Content-Type: application/json; charset=utf-8');
// Add CORS headers
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (!empty($origin) && $origin === 'https://audio.unknown') {
  header('Access-Control-Allow-Origin: ' . $origin);
  header('Access-Control-Allow-Headers: X-Requested-With, Content-Type, Accept, Origin, Authorization');
  header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
}
if ($result === false) {
  http_response_code(400);
  echo json_encode("Upload failed: " . $error);
  exit();
}
echo json_encode($uploadData['url']);
