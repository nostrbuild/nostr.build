<?php

declare(strict_types=1);

namespace BTCPayServer\Http;

use BTCPayServer\Exception\ConnectException;

/**
 * HTTP Client using cURL to communicate.
 */
class CurlClient implements ClientInterface
{
    protected $curlOptions = [];

    /**
     * Inits curl session adding any additional curl options set.
     * @return \CurlHandle|false
     */
    protected function initCurl()
    {
        // We cannot set a return type here as it is "resource" for PHP < 8 and CurlHandle for PHP >= 8.
        $ch = curl_init();
        if ($ch && count($this->curlOptions)) {
            curl_setopt_array($ch, $this->curlOptions);
        }
        return $ch;
    }

    /**
     * Use this method if you need to set any special parameters like disable CURLOPT_SSL_VERIFYHOST and CURLOPT_SSL_VERIFYPEER.
     * @return void
     */
    public function setCurlOptions(array $options)
    {
        $this->curlOptions = $options;
    }

    /**
     * @inheritdoc
     */
    public function request(
        string $method,
        string $url,
        array  $headers = [],
        string $body = ''
    ): ResponseInterface {
        $flatHeaders = [];
        foreach ($headers as $key => $value) {
            $flatHeaders[] = $key . ': ' . $value;
        }

        $ch = $this->initCurl();
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        if ($body !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $flatHeaders);

        $response = curl_exec($ch);

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        $responseHeaders = [];
        $responseBody = '';

        if ($response) {
            $responseString = is_string($response) ? $response : '';
            if ($responseString && $headerSize) {
                $responseBody = substr($responseString, $headerSize);
                $headerPart = substr($responseString, 0, $headerSize);
                $headerParts = explode("\n", $headerPart);
                foreach ($headerParts as $headerLine) {
                    $headerLine = trim($headerLine);
                    if ($headerLine) {
                        $parts = explode(':', $headerLine);
                        if (count($parts) === 2) {
                            $key = $parts[0];
                            $value = $parts[1];
                            $responseHeaders[$key] = $value;
                        }
                    }
                }
            }
        } else {
            $errorMessage = curl_error($ch);
            $errorCode = curl_errno($ch);
            throw new ConnectException($errorMessage, $errorCode);
        }

        return new Response($status, $responseBody, $responseHeaders);
    }
}
