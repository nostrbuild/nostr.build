<?php

declare(strict_types=1);

namespace BTCPayServer\Client;

use BTCPayServer\Result\ApiKey as ResultApiKey;

class ApiKey extends AbstractClient
{
    /**
     * Create a URL you can send the user to. He/she will be prompted to create an API key that corresponds with your needs.
     */
    public static function getAuthorizeUrl(string $baseUrl, array $permissions, ?string $applicationName, ?bool $strict, ?bool $selectiveStores, ?string $redirectToUrlAfterCreation, ?string $applicationIdentifier): string
    {
        $url = rtrim($baseUrl, '/') . '/api-keys/authorize';

        $params = [];
        $params['permissions'] = $permissions;
        $params['applicationName'] = $applicationName;
        $params['strict'] = $strict;
        $params['selectiveStores'] = $selectiveStores;
        $params['redirect'] = $redirectToUrlAfterCreation;
        $params['applicationIdentifier'] = $applicationIdentifier;

        // Take out NULL values
        $params = array_filter($params, function ($value) {
            return $value !== null;
        });

        $queryParams = [];

        foreach ($params as $param => $value) {
            if ($value === true) {
                $value = 'true';
            }
            if ($value === false) {
                $value = 'false';
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    if ($item === true) {
                        $item = 'true';
                    }
                    if ($item === false) {
                        $item = 'false';
                    }
                    $queryParams[] = $param . '=' . urlencode((string)$item);
                }
            } else {
                $queryParams[] = $param . '=' . urlencode((string)$value);
            }
        }

        $queryParams = implode("&", $queryParams);


        $url .= '?' . $queryParams;

        return $url;
    }

    /**
     * Get the current API key information
     */
    public function getCurrent(): ResultApiKey
    {
        $url = $this->getApiUrl() . 'api-keys/current';
        $headers = $this->getRequestHeaders();
        $method = 'GET';
        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return new ResultApiKey(json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    /**
     * Create a new API key for current user.
     *
     * @param string $label Visible label on API key overview
     * @param array $permissions The permissions array can contain specific store id
     * e.g. btcpay.server.canmanageusers:2KxSpc9V5zDWfUbvgYiZuAfka4wUhGF96F75Ao8y4zHP
     */
    public function createApikey(?string $label = null, ?array $permissions = null): ResultApiKey
    {
        $url = $this->getApiUrl() . 'api-keys';
        $headers = $this->getRequestHeaders();
        $method = 'POST';

        $body = json_encode(
            [
                'label' => $label,
                'permissions' => $permissions
            ],
            JSON_THROW_ON_ERROR
        );

        $response = $this->getHttpClient()->request($method, $url, $headers, $body);

        if ($response->getStatus() === 200) {
            return new ResultApiKey(json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    /**
     * Create a new API key for a user.
     *
     * @param string $userId Can be user id or email.
     * @param string $label Visible label on API key overview
     * @param array $permissions The permissions array can contain specific store id
     * e.g. btcpay.server.canmanageusers:2KxSpc9V5zDWfUbvgYiZuAfka4wUhGF96F75Ao8y4zHP
     */
    public function createApiKeyForUser(
        string $idOrMail,
        ?string $label = null,
        ?array $permissions = null
    ): ResultApiKey {
        $url = $this->getApiUrl() . 'users/' . urlencode($idOrMail) . '/api-keys';
        $headers = $this->getRequestHeaders();
        $method = 'POST';

        $body = json_encode(
            [
                'label' => $label,
                'permissions' => $permissions
            ],
            JSON_THROW_ON_ERROR
        );

        $response = $this->getHttpClient()->request($method, $url, $headers, $body);

        if ($response->getStatus() === 200) {
            return new ResultApiKey(json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }


    /**
     * Revokes the current API key.
     */
    public function revokeCurrentApiKey(): bool
    {
        $url = $this->getApiUrl() . 'api-keys/current';
        $headers = $this->getRequestHeaders();
        $method = 'DELETE';
        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return true;
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    /**
     * Revokes an API key for current user.
     */
    public function revokeApiKey(string $apiKey): bool
    {
        $url = $this->getApiUrl() . 'api-keys/' . urlencode($apiKey);
        $headers = $this->getRequestHeaders();
        $method = 'DELETE';
        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return true;
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }


    /**
     * Revokes the API key of target user.
     */
    public function revokeApiKeyForUser(string $idOrMail, string $apiKey): bool
    {
        $url = $this->getApiUrl() . 'users/' . urlencode($idOrMail) . '/api-keys/' . urlencode($apiKey) ;
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
