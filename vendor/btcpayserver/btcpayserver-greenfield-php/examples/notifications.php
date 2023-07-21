<?php

require __DIR__ . '/../vendor/autoload.php';

use BTCPayServer\Client\Notification;

class Notifications
{
    public $apiKey;
    public $host;

    public function __construct()
    {
        $this->apiKey = '';
        $this->host = '';
    }

    public function getNotifications()
    {
        $seen = 'true';
        $skip = 2;
        $take = 5;

        try {
            $client = new Notification($this->host, $this->apiKey);
            var_dump($client->getNotifications($seen, $skip, $take));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function getNotification()
    {
        $id = 'alsjkdflkajsdf';
        try {
            $client = new Notification($this->host, $this->apiKey);
            var_dump($client->getNotification($id));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function updateNotification()
    {
        $id = 'alsjkdflkajsdf';
        $seen = 'true';

        try {
            $client = new Notification($this->host, $this->apiKey);
            var_dump($client->updateNotification($id, $seen));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function removeNotification()
    {
        $id = 'alsjkdflkajsdf';

        try {
            $client = new Notification($this->host, $this->apiKey);
            var_dump($client->removeNotification($id));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }
}

$notification = new Notifications();
//$notification->getNotifications();
//$notification->getNotification();
//$notification->updateNotification();
//$notification->removeNotification();
