<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Model\Client;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use ToshY\BunnyNet\Model\Client\Interface\BunnyClientResponseInterface;

class BunnyClientResponse implements BunnyClientResponseInterface
{
    /**
     * @param ResponseInterface $response
     * @param mixed $contents
     */
    public function __construct(
        private readonly ResponseInterface $response,
        private readonly mixed $contents,
    ) {
    }

    /**
     * @return ResponseInterface
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    /**
     * @return mixed
     */
    public function getContents(): mixed
    {
        return $this->contents;
    }

    /**
     * @return StreamInterface
     */
    public function getBody(): StreamInterface
    {
        return $this->response->getBody();
    }

    /**
     * @return array<string,array<string>>
     */
    public function getHeaders(): array
    {
        return $this->response->getHeaders();
    }

    /**
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    /**
     * @return string
     */
    public function getReasonPhrase(): string
    {
        return $this->response->getReasonPhrase();
    }
}
