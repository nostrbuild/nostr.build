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

header('Content-Type: application/json; charset=utf-8');
if ($result === false) {
  http_response_code(400);
  echo json_encode("Upload failed!");
  exit();
}
echo json_encode($uploadData['url']);
