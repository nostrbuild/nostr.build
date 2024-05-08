<?php

declare(strict_types=1);

namespace BTCPayServer\Client;

use BTCPayServer\Result\StoreUserList;

class StoreUser extends AbstractClient
{
    public function getUsers(string $storeId): StoreUserList
    {
        $url = $this->getApiUrl() . 'stores/' . urlencode($storeId) . '/users';
        $headers = $this->getRequestHeaders();
        $method = 'GET';
        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return new StoreUserList(json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function addUser(
        string $storeId,
        string $userId,
        string $role
    ): bool {
        $url = $this->getApiUrl() . 'stores/' . urlencode($storeId) . '/users';
        $headers = $this->getRequestHeaders();
        $method = 'POST';

        $body = json_encode(
            [
                "userId" => $userId,
                "role" => $role,
            ],
            JSON_THROW_ON_ERROR
        );

        $response = $this->getHttpClient()->request($method, $url, $headers, $body);

        if ($response->getStatus() === 200) {
            return true;
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }


    public function deleteUser(string $storeId, string $idOrMail): bool
    {
        $url = $this->getApiUrl() . 'stores/' . urlencode($storeId) . '/users/' . urlencode($idOrMail);
        $headers = $this->getRequestHeaders();
        $method = 'DELETE';
        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return true;
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }
}
