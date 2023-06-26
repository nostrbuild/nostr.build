<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/DatabaseTable.class.php';
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Respect\Validation\Validator as v;

class UsersImagesFolders extends DatabaseTable
{
  public function __construct(mysqli $db)
  {
    parent::__construct($db, 'users_images_folders');
    $this->validationRules = [
      'id' => v::optional(v::intVal()),
      'usernpub' => v::notEmpty()->stringType()->length(1, 70),
      'folder' => v::notEmpty()->stringType()->length(1, 255),
      'created_at' => v::optional(v::dateTime()),
    ];
  }
}
