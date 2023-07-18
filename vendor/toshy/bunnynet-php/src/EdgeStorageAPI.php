<?php

declare(strict_types=1);

namespace ToshY\BunnyNet;

use Psr\Http\Client\ClientExceptionInterface;
use ToshY\BunnyNet\Client\BunnyClient;
use ToshY\BunnyNet\Enum\Region;
use ToshY\BunnyNet\Exception\FileDoesNotExistException;
use ToshY\BunnyNet\Helper\BodyContentHelper;
use ToshY\BunnyNet\Model\API\EdgeStorage\BrowseFiles\ListFiles;
use ToshY\BunnyNet\Model\API\EdgeStorage\ManageFiles\DeleteFile;
use ToshY\BunnyNet\Model\API\EdgeStorage\ManageFiles\DownloadFile;
use ToshY\BunnyNet\Model\API\EdgeStorage\ManageFiles\UploadFile;
use ToshY\BunnyNet\Model\Client\Interface\BunnyClientResponseInterface;

class EdgeStorageAPI
{
    /**
     * @param string $apiKey
     * @param BunnyClient $client
     * @param Region $region
     */
    public function __construct(
        protected readonly string $apiKey,
        protected readonly BunnyClient $client,
        Region $region = Region::FS,
    ) {
        $this->client
            ->setApiKey($this->apiKey)
            ->setBaseUrl($region->host());
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @param string $fileName
     * @param string $path
     * @return BunnyClientResponseInterface
     * @param string $storageZoneName
     */
    public function downloadFile(
        string $storageZoneName,
        string $fileName,
        string $path = '',
    ): BunnyClientResponseInterface {
        $endpoint = new DownloadFile();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$storageZoneName, $path, $fileName],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws FileDoesNotExistException
     * @param string $localFilePath
     * @param string $path
     * @param array<string,mixed> $headers
     * @return BunnyClientResponseInterface
     * @param string $storageZoneName
     * @param string $fileName
     */
    public function uploadFile(
        string $storageZoneName,
        string $fileName,
        string $localFilePath,
        string $path = '',
        array $headers = [],
    ): BunnyClientResponseInterface {
        $endpoint = new UploadFile();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$storageZoneName, $path, $fileName],
            body: BodyContentHelper::openFileStream($localFilePath),
            headers: $headers,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @param string $fileName
     * @param string $path
     * @return BunnyClientResponseInterface
     * @param string $storageZoneName
     */
    public function deleteFile(
        string $storageZoneName,
        string $fileName,
        string $path = '',
    ): BunnyClientResponseInterface {
        $endpoint = new DeleteFile();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$storageZoneName, $path, $fileName],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @param string $path
     * @return BunnyClientResponseInterface
     * @param string $storageZoneName
     */
    public function listFiles(
        string $storageZoneName,
        string $path = '',
    ): BunnyClientResponseInterface {
        $endpoint = new ListFiles();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$storageZoneName, $path],
        );
    }
}
