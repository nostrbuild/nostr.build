<?php

declare(strict_types=1);

namespace BTCPayServer\Client;

use BTCPayServer\Result\User as ResultUser;

class User extends AbstractClient
{
    public function getCurrentUserInformation(): ResultUser
    {
        $url = $this->getApiUrl() . 'users/me';
        $headers = $this->getRequestHeaders();
        $method = 'GET';
        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return new ResultUser(
                json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR)
            );
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function deleteCurrentUserProfile(): bool
    {
        $url = $this->getApiUrl() . 'users/me';
        $headers = $this->getRequestHeaders();
        $method = 'DELETE';
        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return true;
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function createUser(
        string $email,
        string $password,
        ?bool $isAdministrator = false
    ): ResultUser {
        $url = $this->getApiUrl() . 'users';

        $headers = $this->getRequestHeaders();
        $method = 'POST';

        $body = json_encode(
            [
                'email' => $email,
                'password' => $password,
                'isAdministrator' => $isAdministrator
            ],
            JSON_THROW_ON_ERROR
        );

        $response = $this->getHttpClient()->request($method, $url, $headers, $body);

        if ($response->getStatus() === 201) {
            return new ResultUser(
                json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR)
            );
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function deleteUser(string $idOrMail): bool
    {
        $url = $this->getApiUrl() . 'users/' . urlencode($idOrMail);
        $headers = $this->getRequestHeaders();
        $method = 'DELETE';
        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return true;
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function setUserLock(string $idOrMail, bool $locked): bool
    {
        $url = $this->getApiUrl() . 'users/' . urlencode($idOrMail) . '/lock';
        $headers = $this->getRequestHeaders();
        $method = 'POST';

        $body = json_encode(
            [
                'locked' => $locked,
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
}
