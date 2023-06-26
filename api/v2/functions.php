<?php
// include config
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');

// Function to create user folder
function create_folder(string $npub, string $folderName): bool
{
  global $link;

  // Validate parameters
  if (empty($npub) || empty($folderName)) {
    error_log('Invalid parameters for creation of a folder');
    return false;
  }

  $stmt = $link->prepare("INSERT INTO users_images_folders (usernpub, folder) VALUES (?, ?)");
  $stmt->bind_param("ss", $npub, $folderName);

  try {
    $stmt->execute();
  } catch (Exception $e) {
    error_log('Caught exception when creating folder: ' . $e->getMessage());
    return false;
  } finally {
    $stmt->close();
    $link->close(); // Close the connection
  }

  return true;
}


// Function to delete user folder
function delete_folder(string $npub, int $id): bool {
  global $link;

  // Validate parameters
  if(empty($npub) || $id <= 0) {
    error_log('Invalid parameters for deleting folder');
    return false;
  }

  // Prepare the query
  $stmt = $link->prepare("DELETE FROM users_images_folders WHERE usernpub = ? AND id = ?");

  // Bind parameters
  $stmt->bind_param("si", $npub, $id);

  // Execute the query
  try {
    $stmt->execute();
  } catch (Exception $e) {
    error_log('Caught exception when deleting folder: ' . $e->getMessage());
    return false;
  } finally {
    // Close the statement. This is always executed, even if an exception occurs.
    $stmt->close();
    $link->close(); // Close the connection
  }

  return true;
}

// Function to get content of the folder, or return content of the root folder if no folder is specified
function get_media_in_folder(string $npub, int $folderId = 0): array
{
  global $link;

  // Validate parameters
  if (empty($npub) || $folderId < 0) {
    error_log('Invalid parameters.');
    return array();
  }

  // Specify the columns you need
  if ($folderId == 0) {
    $stmt = $link->prepare("SELECT * FROM users_images WHERE usernpub = ? AND folder_id IS NULL");
    $stmt->bind_param("s", $npub);
  } else {
    $stmt = $link->prepare("SELECT * FROM users_images WHERE usernpub = ? AND folder_id = ?");
    $stmt->bind_param("si", $npub, $folderId);
  }

  try {
    $stmt->execute();
    $result = $stmt->get_result();
  } catch (Exception $e) {
    error_log('Caught exception when fetching media in folder: ' . $e->getMessage());
    return [];
  } finally {
    $stmt->close();
    $link->close(); // Close the connection
  }

  return $result->fetch_all(MYSQLI_ASSOC);
}
