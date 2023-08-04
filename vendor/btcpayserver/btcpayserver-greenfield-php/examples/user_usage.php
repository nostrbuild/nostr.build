<?php

require __DIR__ . '/../vendor/autoload.php';

use BTCPayServer\Client\User;

class Users
{
    public $apiKey;
    public $host;

    public function __construct()
    {
        $this->apiKey = '';
        $this->host = '';
    }

    public function getCurrentUserInformation()
    {
        try {
            $client = new User($this->host, $this->apiKey);
            var_dump($client->getCurrentUserInformation());
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function deleteCurrentUserProfile()
    {
        try {
            $client = new User($this->host, $this->apiKey);
            var_dump($client->deleteCurrentUserProfile());
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function createUser()
    {
        $email = 'test@test.com';
        $password = 'Testing##123';
        $isAdministrator = false;

        try {
            $client = new User($this->host, $this->apiKey);
            var_dump($client->createUser($email, $password, $isAdministrator));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function deleteUser($userId)
    {
        try {
            $client = new User($this->host, $this->apiKey);
            var_dump($client->deleteUser($userId));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function setUserLock($userId, $toggle)
    {
        try {
            $client = new User($this->host, $this->apiKey);
            var_dump($client->setUserLock($userId, $toggle));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }
}

$users = new Users();
//$users->getCurrentUserInformation();
//$users->deleteCurrentUserProfile();
//$users->createUser();
//$users->deleteUser("test@example.com");
//$users->setUserLock("test@example.com", true);
