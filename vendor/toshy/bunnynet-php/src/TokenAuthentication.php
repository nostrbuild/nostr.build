<?php

declare(strict_types=1);

namespace ToshY\BunnyNet;

class TokenAuthentication
{
    /**
     * @param string $token
     * @param string $hostname
     */
    public function __construct(
        private readonly string $token,
        private readonly string $hostname,
    ) {
    }

    /**
     * @param string $file
     * @param int $expirationTime
     * @param string|null $userIp
     * @param bool $isDirectoryToken
     * @param string|null $pathAllowed
     * @param string|null $countriesAllowed
     * @param string|null $countriesBlocked
     * @param string|null $referrersAllowed
     * @param int|null $speedLimit
     * @param bool $allowSubnet
     * @return string
     */
    public function sign(
        string $file,
        int $expirationTime = 3600,
        string|null $userIp = null,
        bool $isDirectoryToken = false,
        string|null $pathAllowed = null,
        string|null $countriesAllowed = null,
        string|null $countriesBlocked = null,
        string|null $referrersAllowed = null,
        int|null $speedLimit = null,
        bool $allowSubnet = true,
    ): string {
        $url = sprintf('%s%s', $this->hostname, $file);

        $this->parseOptionalPathParameter($url, 'token_countries', $countriesAllowed);
        $this->parseOptionalPathParameter($url, 'token_countries_blocked', $countriesBlocked);
        $this->parseOptionalPathParameter($url, 'token_referer', $referrersAllowed);
        $this->parseOptionalPathParameter($url, 'limit', $speedLimit);

        $urlScheme = parse_url($url, PHP_URL_SCHEME);
        $urlHost = parse_url($url, PHP_URL_HOST);
        $urlPath = parse_url($url, PHP_URL_PATH);
        $urlQuery = parse_url($url, PHP_URL_QUERY) ?? '';

        $parameters = [];
        parse_str($urlQuery, $parameters);

        $signaturePath = $urlPath;
        if ($pathAllowed !== null) {
            $signaturePath = $pathAllowed;
            $parameters['token_path'] = $signaturePath;
        }

        ksort($parameters);
        $parameterData = '';
        $parameterDataUrl = '';
        if (sizeof($parameters) > 0) {
            foreach ($parameters as $key => $value) {
                if (strlen($parameterData) > 0) {
                    $parameterData .= '&';
                }

                $parameterDataUrl .= '&';
                $parameterData .= sprintf('%s=%s', $key, $value);
                $parameterDataUrl .= sprintf('%s=%s', $key, urlencode($value));
            }
        }

        $expires = time() + $expirationTime;
        $hashableBase = sprintf('%s%s%s', $this->token, $signaturePath, $expires);

        // Check for IP validation; Additional check to allow subnet to reduce false negatives (IPv4).
        if (null !== $userIp) {
            $ipBase = $userIp;
            if (true === $allowSubnet) {
                $ipBase = preg_replace('/^(\d+.\d+.\d+).\d+$/', '$1.0', $userIp);
            }
            $hashableBase .= $ipBase;
        }
        $hashableBase .= $parameterData;

        // Generate the token
        $token = hash('sha256', $hashableBase, true);
        $token = base64_encode($token);
        $token = strtr($token, '+/', '-_');
        $token = str_replace('=', '', $token);

        if (true === $isDirectoryToken) {
            return sprintf(
                '%s://%s/bcdn_token=%s&expires=%d%s%s',
                $urlScheme,
                $urlHost,
                $token,
                $expires,
                $parameterDataUrl,
                $urlPath,
            );
        }

        return sprintf(
            '%s://%s%s?token=%s%s&expires=%d',
            $urlScheme,
            $urlHost,
            $urlPath,
            $token,
            $parameterDataUrl,
            $expires,
        );
    }

    /**
     * @param string $url
     * @param string|null $pathParameterKey
     * @param mixed $pathParameterValue
     * @return void
     */
    private function parseOptionalPathParameter(
        string &$url,
        string|null $pathParameterKey,
        mixed $pathParameterValue,
    ): void {
        if (null === $pathParameterValue) {
            return;
        }

        $url .= empty(parse_url($url, PHP_URL_QUERY)) === true ? '?' : '&';
        $url .= sprintf('%s=%s', $pathParameterKey, $pathParameterValue);
    }
}
