<?php
// Include config, session, and Permission class files
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/session.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';

// Create new Permission object
$perm = new Permission();

// Redirect users to the correct page depending on their login status and permissions
if ($perm->isGuest() || $perm->validatePermissionsLevelEqual(0)) {
  header('Location: /signup/new/');
  exit;
} else {
  header('Location: /signup/upgrade/');
  exit;
}
