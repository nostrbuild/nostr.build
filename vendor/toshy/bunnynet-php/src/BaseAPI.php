<?php

declare(strict_types=1);

namespace ToshY\BunnyNet;

use Psr\Http\Client\ClientExceptionInterface;
use ToshY\BunnyNet\Client\BunnyClient;
use ToshY\BunnyNet\Enum\Host;
use ToshY\BunnyNet\Helper\BodyContentHelper;
use ToshY\BunnyNet\Model\API\Base\AbuseCase\CheckAbuseCase;
use ToshY\BunnyNet\Model\API\Base\AbuseCase\GetAbuseCase;
use ToshY\BunnyNet\Model\API\Base\AbuseCase\GetDMCACase;
use ToshY\BunnyNet\Model\API\Base\AbuseCase\ListAbuseCases;
use ToshY\BunnyNet\Model\API\Base\AbuseCase\ResolveAbuseCase;
use ToshY\BunnyNet\Model\API\Base\AbuseCase\ResolveDMCACase;
use ToshY\BunnyNet\Model\API\Base\APIKeys\ListAPIKeys;
use ToshY\BunnyNet\Model\API\Base\Billing\ApplyPromoCode;
use ToshY\BunnyNet\Model\API\Base\Billing\ClaimAffiliateCredits;
use ToshY\BunnyNet\Model\API\Base\Billing\ConfigureAutoRecharge;
use ToshY\BunnyNet\Model\API\Base\Billing\CreateCoinifyPayment;
use ToshY\BunnyNet\Model\API\Base\Billing\CreatePaymentCheckout;
use ToshY\BunnyNet\Model\API\Base\Billing\GetAffiliateDetails;
use ToshY\BunnyNet\Model\API\Base\Billing\GetBillingDetails;
use ToshY\BunnyNet\Model\API\Base\Billing\GetBillingSummary;
use ToshY\BunnyNet\Model\API\Base\Billing\GetBillingSummaryPDF;
use ToshY\BunnyNet\Model\API\Base\Billing\GetCoinifyBitcoinExchangeRate;
use ToshY\BunnyNet\Model\API\Base\Billing\PreparePaymentAuthorization;
use ToshY\BunnyNet\Model\API\Base\Compute\AddComputeScript;
use ToshY\BunnyNet\Model\API\Base\Compute\AddComputeScriptVariable;
use ToshY\BunnyNet\Model\API\Base\Compute\DeleteComputeScript;
use ToshY\BunnyNet\Model\API\Base\Compute\DeleteComputeScriptVariable;
use ToshY\BunnyNet\Model\API\Base\Compute\GetComputeScript;
use ToshY\BunnyNet\Model\API\Base\Compute\GetComputeScriptCode;
use ToshY\BunnyNet\Model\API\Base\Compute\GetComputeScriptVariable;
use ToshY\BunnyNet\Model\API\Base\Compute\ListComputeScriptReleases;
use ToshY\BunnyNet\Model\API\Base\Compute\ListComputeScripts;
use ToshY\BunnyNet\Model\API\Base\Compute\PublishComputeScript;
use ToshY\BunnyNet\Model\API\Base\Compute\PublishComputeScriptByPathParameter;
use ToshY\BunnyNet\Model\API\Base\Compute\UpdateComputeScript;
use ToshY\BunnyNet\Model\API\Base\Compute\UpdateComputeScriptCode;
use ToshY\BunnyNet\Model\API\Base\Compute\UpdateComputeScriptVariable;
use ToshY\BunnyNet\Model\API\Base\Countries\ListCountries;
use ToshY\BunnyNet\Model\API\Base\DNSZone\AddDNSRecord;
use ToshY\BunnyNet\Model\API\Base\DNSZone\AddDNSZone;
use ToshY\BunnyNet\Model\API\Base\DNSZone\CheckDNSZoneAvailability;
use ToshY\BunnyNet\Model\API\Base\DNSZone\DeleteDNSRecord;
use ToshY\BunnyNet\Model\API\Base\DNSZone\DeleteDNSZone;
use ToshY\BunnyNet\Model\API\Base\DNSZone\DismissDNSConfigurationNotice;
use ToshY\BunnyNet\Model\API\Base\DNSZone\ExportDNSRecords;
use ToshY\BunnyNet\Model\API\Base\DNSZone\GetDNSZone;
use ToshY\BunnyNet\Model\API\Base\DNSZone\GetDNSZoneQueryStatistics;
use ToshY\BunnyNet\Model\API\Base\DNSZone\ImportDNSRecords;
use ToshY\BunnyNet\Model\API\Base\DNSZone\ListDNSZones;
use ToshY\BunnyNet\Model\API\Base\DNSZone\RecheckDNSConfiguration;
use ToshY\BunnyNet\Model\API\Base\DNSZone\UpdateDNSRecord;
use ToshY\BunnyNet\Model\API\Base\DNSZone\UpdateDNSZone;
use ToshY\BunnyNet\Model\API\Base\DRMCertificate\ListDRMCertificates;
use ToshY\BunnyNet\Model\API\Base\PullZone\AddCustomCertificate;
use ToshY\BunnyNet\Model\API\Base\PullZone\AddCustomHostname;
use ToshY\BunnyNet\Model\API\Base\PullZone\AddOrUpdateEdgeRule;
use ToshY\BunnyNet\Model\API\Base\PullZone\AddPullZone;
use ToshY\BunnyNet\Model\API\Base\PullZone\CheckPullZoneAvailability;
use ToshY\BunnyNet\Model\API\Base\PullZone\DeleteCertificate;
use ToshY\BunnyNet\Model\API\Base\PullZone\DeleteCustomHostname;
use ToshY\BunnyNet\Model\API\Base\PullZone\DeleteEdgeRule;
use ToshY\BunnyNet\Model\API\Base\PullZone\DeletePullZone;
use ToshY\BunnyNet\Model\API\Base\PullZone\GetOptimizerStatistics;
use ToshY\BunnyNet\Model\API\Base\PullZone\GetOriginShieldQueueStatistics;
use ToshY\BunnyNet\Model\API\Base\PullZone\GetPullZone;
use ToshY\BunnyNet\Model\API\Base\PullZone\GetSafeHopStatistics;
use ToshY\BunnyNet\Model\API\Base\PullZone\GetWAFStatistics;
use ToshY\BunnyNet\Model\API\Base\PullZone\ListPullZones;
use ToshY\BunnyNet\Model\API\Base\PullZone\LoadFreeCertificate;
use ToshY\BunnyNet\Model\API\Base\PullZone\PurgeCache;
use ToshY\BunnyNet\Model\API\Base\PullZone\ResetTokenKey;
use ToshY\BunnyNet\Model\API\Base\PullZone\SetEdgeRuleEnabled;
use ToshY\BunnyNet\Model\API\Base\PullZone\SetForceSSL;
use ToshY\BunnyNet\Model\API\Base\PullZone\SetZoneSecurityEnabled;
use ToshY\BunnyNet\Model\API\Base\PullZone\SetZoneSecurityIncludeHashRemoteIPEnabled;
use ToshY\BunnyNet\Model\API\Base\PullZone\UpdatePullZone;
use ToshY\BunnyNet\Model\API\Base\Purge\PurgeURL;
use ToshY\BunnyNet\Model\API\Base\Purge\PurgeURLByHeader;
use ToshY\BunnyNet\Model\API\Base\Region\ListRegions;
use ToshY\BunnyNet\Model\API\Base\Search\GlobalSearch;
use ToshY\BunnyNet\Model\API\Base\Statistics\GetStatistics;
use ToshY\BunnyNet\Model\API\Base\StorageZone\AddStorageZone;
use ToshY\BunnyNet\Model\API\Base\StorageZone\CheckStorageZoneAvailability;
use ToshY\BunnyNet\Model\API\Base\StorageZone\DeleteStorageZone;
use ToshY\BunnyNet\Model\API\Base\StorageZone\GetStorageZone;
use ToshY\BunnyNet\Model\API\Base\StorageZone\GetStorageZoneConnections;
use ToshY\BunnyNet\Model\API\Base\StorageZone\GetStorageZoneStatistics;
use ToshY\BunnyNet\Model\API\Base\StorageZone\ListStorageZones;
use ToshY\BunnyNet\Model\API\Base\StorageZone\UpdateStorageZone;
use ToshY\BunnyNet\Model\API\Base\StreamVideoLibrary\AddVideoLibrary;
use ToshY\BunnyNet\Model\API\Base\StreamVideoLibrary\AddWatermark;
use ToshY\BunnyNet\Model\API\Base\StreamVideoLibrary\DeleteVideoLibrary;
use ToshY\BunnyNet\Model\API\Base\StreamVideoLibrary\DeleteWatermark;
use ToshY\BunnyNet\Model\API\Base\StreamVideoLibrary\GetLanguages;
use ToshY\BunnyNet\Model\API\Base\StreamVideoLibrary\GetVideoLibrary;
use ToshY\BunnyNet\Model\API\Base\StreamVideoLibrary\ListVideoLibraries;
use ToshY\BunnyNet\Model\API\Base\StreamVideoLibrary\UpdateVideoLibrary;
use ToshY\BunnyNet\Model\API\Base\Support\CloseTicket;
use ToshY\BunnyNet\Model\API\Base\Support\CreateTicket;
use ToshY\BunnyNet\Model\API\Base\Support\GetTicketDetails;
use ToshY\BunnyNet\Model\API\Base\Support\ListTickets;
use ToshY\BunnyNet\Model\API\Base\Support\ReplyTicket;
use ToshY\BunnyNet\Model\API\Base\User\AcceptDPA;
use ToshY\BunnyNet\Model\API\Base\User\CloseAccount;
use ToshY\BunnyNet\Model\API\Base\User\DisableTwoFactorAuthentication;
use ToshY\BunnyNet\Model\API\Base\User\EnableTwoFactorAuthentication;
use ToshY\BunnyNet\Model\API\Base\User\GenerateTwoFactorAuthenticationVerification;
use ToshY\BunnyNet\Model\API\Base\User\GetDPADetails;
use ToshY\BunnyNet\Model\API\Base\User\GetDPADetailsHTML;
use ToshY\BunnyNet\Model\API\Base\User\GetHomeFeed;
use ToshY\BunnyNet\Model\API\Base\User\GetMarketingDetails;
use ToshY\BunnyNet\Model\API\Base\User\GetUserDetails;
use ToshY\BunnyNet\Model\API\Base\User\GetWhatsNewItems;
use ToshY\BunnyNet\Model\API\Base\User\ListCloseAccountReasons;
use ToshY\BunnyNet\Model\API\Base\User\ListNotifications;
use ToshY\BunnyNet\Model\API\Base\User\ResendEmailConfirmation;
use ToshY\BunnyNet\Model\API\Base\User\ResetAPIKey;
use ToshY\BunnyNet\Model\API\Base\User\ResetWhatsNew;
use ToshY\BunnyNet\Model\API\Base\User\SetNotificationsOpened;
use ToshY\BunnyNet\Model\API\Base\User\UpdateUserDetails;
use ToshY\BunnyNet\Model\API\Base\User\VerifyTwoFactorAuthenticationCode;
use ToshY\BunnyNet\Model\Client\Interface\BunnyClientResponseInterface;
use ToshY\BunnyNet\Validator\ParameterValidator;

class BaseAPI
{
    /**
     * @param string $apiKey
     * @param BunnyClient $client
     */
    public function __construct(
        protected readonly string $apiKey,
        protected readonly BunnyClient $client,
    ) {
        $this->client
            ->setApiKey($this->apiKey)
            ->setBaseUrl(Host::API_ENDPOINT);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @param array<string,mixed> $query
     * @return BunnyClientResponseInterface
     */
    public function listAbuseCases(array $query = []): BunnyClientResponseInterface
    {
        $endpoint = new ListAbuseCases();

        ParameterValidator::validate($query, $endpoint->getQuery());

        return $this->client->request(
            endpoint: $endpoint,
            query: $query,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function getDmcaCase(int $id): BunnyClientResponseInterface
    {
        $endpoint = new GetDMCACase();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function getAbuseCase(int $id): BunnyClientResponseInterface
    {
        $endpoint = new GetAbuseCase();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function resolveDmcaCase(int $id): BunnyClientResponseInterface
    {
        $endpoint = new ResolveDMCACase();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function resolveAbuseCase(int $id): BunnyClientResponseInterface
    {
        $endpoint = new ResolveAbuseCase();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function checkAbuseCase(int $id): BunnyClientResponseInterface
    {
        $endpoint = new CheckAbuseCase();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     */
    public function listCountries(): BunnyClientResponseInterface
    {
        $endpoint = new ListCountries();

        return $this->client->request(
            endpoint: $endpoint,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\JSONException
     * @throws Exception\ParameterIsRequiredException
     * @param array<string,mixed> $query
     * @return BunnyClientResponseInterface
     */
    public function listApiKeys(array $query): BunnyClientResponseInterface
    {
        $endpoint = new ListAPIKeys();

        ParameterValidator::validate($query, $endpoint->getQuery());

        return $this->client->request(
            endpoint: $endpoint,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     */
    public function getBillingDetails(): BunnyClientResponseInterface
    {
        $endpoint = new GetBillingDetails();

        return $this->client->request(
            endpoint: $endpoint,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param array<string,mixed> $body
     */
    public function configureAutoRecharge(array $body): BunnyClientResponseInterface
    {
        $endpoint = new ConfigureAutoRecharge();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param array<string,mixed> $body
     */
    public function createPaymentCheckout(array $body): BunnyClientResponseInterface
    {
        $endpoint = new CreatePaymentCheckout();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     */
    public function preparePaymentAuthorization(): BunnyClientResponseInterface
    {
        $endpoint = new PreparePaymentAuthorization();

        return $this->client->request(
            endpoint: $endpoint,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     */
    public function getAffiliateDetails(): BunnyClientResponseInterface
    {
        $endpoint = new GetAffiliateDetails();

        return $this->client->request(
            endpoint: $endpoint,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     */
    public function claimAffiliateCredits(): BunnyClientResponseInterface
    {
        $endpoint = new ClaimAffiliateCredits();

        return $this->client->request(
            endpoint: $endpoint,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     */
    public function getCoinifyBitcoinExchangeRate(): BunnyClientResponseInterface
    {
        $endpoint = new GetCoinifyBitcoinExchangeRate();

        return $this->client->request(
            endpoint: $endpoint,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @param array<string,mixed> $query
     * @return BunnyClientResponseInterface
     */
    public function createCoinifyPayment(array $query): BunnyClientResponseInterface
    {
        $endpoint = new CreateCoinifyPayment();

        ParameterValidator::validate($query, $endpoint->getQuery());

        return $this->client->request(
            endpoint: $endpoint,
            query: $query,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     */
    public function getBillingSummary(): BunnyClientResponseInterface
    {
        $endpoint = new GetBillingSummary();

        return $this->client->request(
            endpoint: $endpoint,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     * @param int $billingRecordId
     */
    public function getBillingSummaryPdf(int $billingRecordId): BunnyClientResponseInterface
    {
        $endpoint = new GetBillingSummaryPDF();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$billingRecordId],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @param array<string,mixed> $query
     * @return BunnyClientResponseInterface
     */
    public function applyPromoCode(array $query): BunnyClientResponseInterface
    {
        $endpoint = new ApplyPromoCode();

        ParameterValidator::validate($query, $endpoint->getQuery());

        return $this->client->request(
            endpoint: $endpoint,
            query: $query,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @param array<string,mixed> $query
     * @return BunnyClientResponseInterface
     */
    public function listComputeScripts(array $query = []): BunnyClientResponseInterface
    {
        $endpoint = new ListComputeScripts();

        ParameterValidator::validate($query, $endpoint->getQuery());

        return $this->client->request(
            endpoint: $endpoint,
            query: $query,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param array<string,mixed> $body
     */
    public function addComputeScript(array $body): BunnyClientResponseInterface
    {
        $endpoint = new AddComputeScript();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function getComputeScript(int $id): BunnyClientResponseInterface
    {
        $endpoint = new GetComputeScript();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param int $id
     * @param array<string,mixed> $body
     */
    public function updateComputeScript(int $id, array $body): BunnyClientResponseInterface
    {
        $endpoint = new UpdateComputeScript();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function deleteComputeScript(int $id): BunnyClientResponseInterface
    {
        $endpoint = new DeleteComputeScript();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function getComputeScriptCode(int $id): BunnyClientResponseInterface
    {
        $endpoint = new GetComputeScriptCode();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param int $id
     * @param array<string,mixed> $body
     */
    public function updateComputeScriptCode(
        int $id,
        array $body = [],
    ): BunnyClientResponseInterface {
        $endpoint = new UpdateComputeScriptCode();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @param array<string,mixed> $query
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function listComputeScriptReleases(
        int $id,
        array $query = [],
    ): BunnyClientResponseInterface {
        $endpoint = new ListComputeScriptReleases();

        ParameterValidator::validate($query, $endpoint->getQuery());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
            query: $query,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @param array<string,mixed> $query
     * @param array<string,mixed> $body
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function publishComputeScript(
        int $id,
        array $query,
        array $body = [],
    ): BunnyClientResponseInterface {
        $endpoint = new PublishComputeScript();

        ParameterValidator::validate($query, $endpoint->getQuery());
        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
            query: $query,
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @param string $uuid
     * @param array<string,mixed> $body
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function publishComputeScriptByPathParameter(
        int $id,
        string $uuid,
        array $body = [],
    ): BunnyClientResponseInterface {
        $endpoint = new PublishComputeScriptByPathParameter();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id, $uuid],
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param int $id
     * @param array<string,mixed> $body
     */
    public function addComputeScriptVariable(
        int $id,
        array $body,
    ): BunnyClientResponseInterface {
        $endpoint = new AddComputeScriptVariable();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @param int $variableId
     * @param array<string,mixed> $body
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function updateComputeScriptVariable(
        int $id,
        int $variableId,
        array $body,
    ): BunnyClientResponseInterface {
        $endpoint = new UpdateComputeScriptVariable();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id, $variableId],
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @param int $variableId
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function getComputeScriptVariable(
        int $id,
        int $variableId,
    ): BunnyClientResponseInterface {
        $endpoint = new GetComputeScriptVariable();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id, $variableId],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @param int $variableId
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function deleteComputeScriptVariable(
        int $id,
        int $variableId,
    ): BunnyClientResponseInterface {
        $endpoint = new DeleteComputeScriptVariable();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id, $variableId],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @param array<string,mixed> $query
     * @return BunnyClientResponseInterface
     */
    public function listTickets(array $query = []): BunnyClientResponseInterface
    {
        $endpoint = new ListTickets();

        ParameterValidator::validate($query, $endpoint->getQuery());

        return $this->client->request(
            endpoint: $endpoint,
            query: $query,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function getTicketDetails(int $id): BunnyClientResponseInterface
    {
        $endpoint = new GetTicketDetails();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function closeTicket(int $id): BunnyClientResponseInterface
    {
        $endpoint = new CloseTicket();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param int $id
     * @param array<string,mixed> $body
     */
    public function replyTicket(
        int $id,
        array $body = [],
    ): BunnyClientResponseInterface {
        $endpoint = new ReplyTicket();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
            body: BodyContentHelper::getBody($body),
        );
    }


    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param array<string,mixed> $body
     */
    public function createTicket(array $body): BunnyClientResponseInterface
    {
        $endpoint = new CreateTicket();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @param array<string,mixed> $query
     * @return BunnyClientResponseInterface
     */
    public function listDrmCertificates(array $query = []): BunnyClientResponseInterface
    {
        $endpoint = new ListDRMCertificates();

        ParameterValidator::validate($query, $endpoint->getQuery());

        return $this->client->request(
            endpoint: $endpoint,
            query: $query,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     */
    public function listRegions(): BunnyClientResponseInterface
    {
        $endpoint = new ListRegions();

        return $this->client->request(
            endpoint: $endpoint,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @param array<string,mixed> $query
     * @return BunnyClientResponseInterface
     */
    public function listVideoLibraries(array $query = []): BunnyClientResponseInterface
    {
        $endpoint = new ListVideoLibraries();

        ParameterValidator::validate($query, $endpoint->getQuery());

        return $this->client->request(
            endpoint: $endpoint,
            query: $query,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param array<string,mixed> $body
     */
    public function addVideoLibrary(array $body): BunnyClientResponseInterface
    {
        $endpoint = new AddVideoLibrary();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @param array<string,mixed> $query
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function getVideoLibrary(
        int $id,
        array $query = [],
    ): BunnyClientResponseInterface {
        $endpoint = new GetVideoLibrary();

        ParameterValidator::validate($query, $endpoint->getQuery());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
            query: $query,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param int $id
     * @param array<string,mixed> $body
     */
    public function updateVideoLibrary(
        int $id,
        array $body,
    ): BunnyClientResponseInterface {
        $endpoint = new UpdateVideoLibrary();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function deleteVideoLibrary(int $id): BunnyClientResponseInterface
    {
        $endpoint = new DeleteVideoLibrary();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     */
    public function getLanguages(): BunnyClientResponseInterface
    {
        $endpoint = new GetLanguages();

        return $this->client->request(
            endpoint: $endpoint,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @param array<string,mixed> $query
     * @return BunnyClientResponseInterface
     */
    public function resetVideoLibraryPassword(array $query): BunnyClientResponseInterface
    {
        $endpoint = new Model\API\Base\StreamVideoLibrary\ResetPassword();

        ParameterValidator::validate($query, $endpoint->getQuery());

        return $this->client->request(
            endpoint: $endpoint,
            query: $query,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function resetVideoLibraryPasswordByPathParameter(int $id): BunnyClientResponseInterface
    {
        $endpoint = new Model\API\Base\StreamVideoLibrary\ResetPasswordByPathParameter();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function addWatermark(int $id): BunnyClientResponseInterface
    {
        $endpoint = new AddWatermark();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function deleteWatermark(int $id): BunnyClientResponseInterface
    {
        $endpoint = new DeleteWatermark();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param int $id
     * @param array<string,mixed> $body
     */
    public function addVideoLibraryAllowedReferer(
        int $id,
        array $body,
    ): BunnyClientResponseInterface {
        $endpoint = new Model\API\Base\StreamVideoLibrary\AddAllowedReferer();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param int $id
     * @param array<string,mixed> $body
     */
    public function removeVideoLibraryAllowedReferer(
        int $id,
        array $body,
    ): BunnyClientResponseInterface {
        $endpoint = new Model\API\Base\StreamVideoLibrary\DeleteAllowedReferer();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param int $id
     * @param array<string,mixed> $body
     */
    public function addVideoLibraryBlockedReferer(
        int $id,
        array $body,
    ): BunnyClientResponseInterface {
        $endpoint = new Model\API\Base\StreamVideoLibrary\AddBlockedReferer();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param int $id
     * @param array<string,mixed> $body
     */
    public function removeVideoLibraryBlockedReferer(
        int $id,
        array $body,
    ): BunnyClientResponseInterface {
        $endpoint = new Model\API\Base\StreamVideoLibrary\DeleteBlockedReferer();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @param array<string,mixed> $query
     * @return BunnyClientResponseInterface
     */
    public function listDnsZones(array $query = []): BunnyClientResponseInterface
    {
        $endpoint = new ListDNSZones();

        ParameterValidator::validate($query, $endpoint->getQuery());

        return $this->client->request(
            endpoint: $endpoint,
            query: $query,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param array<string,mixed> $body
     */
    public function addDnsZone(array $body): BunnyClientResponseInterface
    {
        $endpoint = new AddDNSZone();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function getDnsZone(int $id): BunnyClientResponseInterface
    {
        $endpoint = new GetDNSZone();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param int $id
     * @param array<string,mixed> $body
     */
    public function updateDnsZone(
        int $id,
        array $body = [],
    ): BunnyClientResponseInterface {
        $endpoint = new UpdateDNSZone();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function deleteDnsZone(int $id): BunnyClientResponseInterface
    {
        $endpoint = new DeleteDNSZone();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function exportDnsZone(int $id): BunnyClientResponseInterface
    {
        $endpoint = new ExportDNSRecords();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @param array<string,mixed> $query
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function getDnsZoneQueryStatistics(
        int $id,
        array $query = [],
    ): BunnyClientResponseInterface {
        $endpoint = new GetDNSZoneQueryStatistics();

        ParameterValidator::validate($query, $endpoint->getQuery());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
            query: $query,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param array<string,mixed> $body
     */
    public function checkDnsZoneAvailability(array $body): BunnyClientResponseInterface
    {
        $endpoint = new CheckDNSZoneAvailability();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param int $zoneId
     * @param array<string,mixed> $body
     */
    public function addDnsRecord(
        int $zoneId,
        array $body,
    ): BunnyClientResponseInterface {
        $endpoint = new AddDNSRecord();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$zoneId],
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @param int $id
     * @param array<string,mixed> $body
     * @return BunnyClientResponseInterface
     * @param int $zoneId
     */
    public function updateDnsRecord(
        int $zoneId,
        int $id,
        array $body,
    ): BunnyClientResponseInterface {
        $endpoint = new UpdateDNSRecord();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$zoneId, $id],
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @param int $id
     * @return BunnyClientResponseInterface
     * @param int $zoneId
     */
    public function deleteDnsRecord(
        int $zoneId,
        int $id,
    ): BunnyClientResponseInterface {
        $endpoint = new DeleteDNSRecord();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$zoneId, $id],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function recheckDnsConfiguration(int $id): BunnyClientResponseInterface
    {
        $endpoint = new RecheckDNSConfiguration();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function dismissDnsConfigurationNotice(int $id): BunnyClientResponseInterface
    {
        $endpoint = new DismissDNSConfigurationNotice();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\FileDoesNotExistException
     * @return BunnyClientResponseInterface
     * @param int $zoneId
     * @param string $localFilePath
     */
    public function importDnsRecords(
        int $zoneId,
        string $localFilePath,
    ): BunnyClientResponseInterface {
        $endpoint = new ImportDNSRecords();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$zoneId],
            body: BodyContentHelper::openFileStream($localFilePath),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @param array<string,mixed> $query
     * @return BunnyClientResponseInterface
     */
    public function listPullZones(array $query = []): BunnyClientResponseInterface
    {
        $endpoint = new ListPullZones();

        ParameterValidator::validate($query, $endpoint->getQuery());

        return $this->client->request(
            endpoint: $endpoint,
            query: $query,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param array<string,mixed> $body
     */
    public function addPullZone(array $body): BunnyClientResponseInterface
    {
        $endpoint = new AddPullZone();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @param array<string,mixed> $query
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function getPullZone(
        int $id,
        array $query = [],
    ): BunnyClientResponseInterface {
        $endpoint = new GetPullZone();

        ParameterValidator::validate($query, $endpoint->getQuery());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
            query: $query,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param int $id
     * @param array<string,mixed> $body
     */
    public function updatePullZone(
        int $id,
        array $body,
    ): BunnyClientResponseInterface {
        $endpoint = new UpdatePullZone();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function deletePullZone(int $id): BunnyClientResponseInterface
    {
        $endpoint = new DeletePullZone();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @param string $edgeRuleId
     * @return BunnyClientResponseInterface
     * @param int $pullZoneId
     */
    public function deleteEdgeRule(
        int $pullZoneId,
        string $edgeRuleId,
    ): BunnyClientResponseInterface {
        $endpoint = new DeleteEdgeRule();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$pullZoneId, $edgeRuleId],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param int $pullZoneId
     * @param array<string,mixed> $body
     */
    public function addOrUpdateEdgeRule(
        int $pullZoneId,
        array $body,
    ): BunnyClientResponseInterface {
        $endpoint = new AddOrUpdateEdgeRule();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$pullZoneId],
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @param string $edgeRuleId
     * @param array<string,mixed> $body
     * @return BunnyClientResponseInterface
     * @param int $pullZoneId
     */
    public function setEdgeRuleEnabled(
        int $pullZoneId,
        string $edgeRuleId,
        array $body,
    ): BunnyClientResponseInterface {
        $endpoint = new SetEdgeRuleEnabled();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$pullZoneId, $edgeRuleId],
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @param bool $enabled
     * @return BunnyClientResponseInterface
     * @param int $pullZoneId
     */
    public function setZoneSecurityEnabled(
        int $pullZoneId,
        bool $enabled,
    ): BunnyClientResponseInterface {
        $endpoint = new SetZoneSecurityEnabled();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$pullZoneId, $enabled],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @param bool $enabled
     * @return BunnyClientResponseInterface
     * @param int $pullZoneId
     */
    public function setZoneSecurityIncludeHashRemoteIPEnabled(
        int $pullZoneId,
        bool $enabled,
    ): BunnyClientResponseInterface {
        $endpoint = new SetZoneSecurityIncludeHashRemoteIPEnabled();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$pullZoneId, $enabled],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @param array<string,mixed> $query
     * @return BunnyClientResponseInterface
     * @param int $pullZoneId
     */
    public function getOriginShieldQueueStatistics(
        int $pullZoneId,
        array $query = [],
    ): BunnyClientResponseInterface {
        $endpoint = new GetOriginShieldQueueStatistics();

        ParameterValidator::validate($query, $endpoint->getQuery());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$pullZoneId],
            query: $query,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @param array<string,mixed> $query
     * @return BunnyClientResponseInterface
     * @param int $pullZoneId
     */
    public function getSafeHopStatistics(
        int $pullZoneId,
        array $query = [],
    ): BunnyClientResponseInterface {
        $endpoint = new GetSafeHopStatistics();

        ParameterValidator::validate($query, $endpoint->getQuery());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$pullZoneId],
            query: $query,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @param array<string,mixed> $query
     * @return BunnyClientResponseInterface
     * @param int $pullZoneId
     */
    public function getOptimizerStatistics(
        int $pullZoneId,
        array $query = [],
    ): BunnyClientResponseInterface {
        $endpoint = new GetOptimizerStatistics();

        ParameterValidator::validate($query, $endpoint->getQuery());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$pullZoneId],
            query: $query,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @param array<string,mixed> $query
     * @return BunnyClientResponseInterface
     * @param int $pullZoneId
     */
    public function getWafStatistics(
        int $pullZoneId,
        array $query = [],
    ): BunnyClientResponseInterface {
        $endpoint = new GetWAFStatistics();

        ParameterValidator::validate($query, $endpoint->getQuery());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$pullZoneId],
            query: $query,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @param array<string,mixed> $query
     * @return BunnyClientResponseInterface
     */
    public function loadFreeCertificate(array $query): BunnyClientResponseInterface
    {
        $endpoint = new LoadFreeCertificate();

        ParameterValidator::validate($query, $endpoint->getQuery());

        return $this->client->request(
            endpoint: $endpoint,
            query: $query,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param int $id
     * @param array<string,mixed> $body
     */
    public function purgePullZoneCache(
        int $id,
        array $body = [],
    ): BunnyClientResponseInterface {
        $endpoint = new PurgeCache();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param array<string,mixed> $body
     */
    public function checkPullZoneAvailability(array $body): BunnyClientResponseInterface
    {
        $endpoint = new CheckPullZoneAvailability();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param int $id
     * @param array<string,mixed> $body
     */
    public function addCertificate(
        int $id,
        array $body,
    ): BunnyClientResponseInterface {
        $endpoint = new AddCustomCertificate();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param int $id
     * @param array<string,mixed> $body
     */
    public function removeCertificate(
        int $id,
        array $body,
    ): BunnyClientResponseInterface {
        $endpoint = new DeleteCertificate();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param int $id
     * @param array<string,mixed> $body
     */
    public function addCustomHostname(
        int $id,
        array $body,
    ): BunnyClientResponseInterface {
        $endpoint = new AddCustomHostname();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param int $id
     * @param array<string,mixed> $body
     */
    public function removeCustomHostname(
        int $id,
        array $body,
    ): BunnyClientResponseInterface {
        $endpoint = new DeleteCustomHostname();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param int $id
     * @param array<string,mixed> $body
     */
    public function setForceSsl(
        int $id,
        array $body,
    ): BunnyClientResponseInterface {
        $endpoint = new SetForceSSL();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function resetPullZoneTokenKey(int $id): BunnyClientResponseInterface
    {
        $endpoint = new ResetTokenKey();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param int $id
     * @param array<string,mixed> $body
     */
    public function addPullZoneAllowedReferer(
        int $id,
        array $body,
    ): BunnyClientResponseInterface {
        $endpoint = new Model\API\Base\PullZone\AddAllowedReferer();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param int $id
     * @param array<string,mixed> $body
     */
    public function removePullZoneAllowedReferer(
        int $id,
        array $body,
    ): BunnyClientResponseInterface {
        $endpoint = new Model\API\Base\PullZone\DeleteAllowedReferer();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param int $id
     * @param array<string,mixed> $body
     */
    public function addPullZoneBlockedReferer(
        int $id,
        array $body,
    ): BunnyClientResponseInterface {
        $endpoint = new Model\API\Base\PullZone\AddBlockedReferer();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param int $id
     * @param array<string,mixed> $body
     */
    public function removePullZoneBlockedReferer(
        int $id,
        array $body,
    ): BunnyClientResponseInterface {
        $endpoint = new Model\API\Base\PullZone\DeleteBlockedReferer();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param int $id
     * @param array<string,mixed> $body
     */
    public function addPullZoneBlockedIp(
        int $id,
        array $body,
    ): BunnyClientResponseInterface {
        $endpoint = new Model\API\Base\PullZone\AddBlockedIP();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param int $id
     * @param array<string,mixed> $body
     */
    public function removePullZoneBlockedIp(
        int $id,
        array $body,
    ): BunnyClientResponseInterface {
        $endpoint = new Model\API\Base\PullZone\DeleteBlockedIP();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @param array<string,mixed> $query
     * @return BunnyClientResponseInterface
     */
    public function purgeUrl(array $query): BunnyClientResponseInterface
    {
        $endpoint = new PurgeURL();

        ParameterValidator::validate($query, $endpoint->getQuery());

        return $this->client->request(
            endpoint: $endpoint,
            query: $query,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @param array<string,mixed> $query
     * @return BunnyClientResponseInterface
     */
    public function purgeUrlByHeader(array $query): BunnyClientResponseInterface
    {
        $endpoint = new PurgeURLByHeader();

        ParameterValidator::validate($query, $endpoint->getQuery());

        return $this->client->request(
            endpoint: $endpoint,
            query: $query,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @param array<string,mixed> $query
     * @return BunnyClientResponseInterface
     */
    public function getStatistics(array $query = []): BunnyClientResponseInterface
    {
        $endpoint = new GetStatistics();

        ParameterValidator::validate($query, $endpoint->getQuery());

        return $this->client->request(
            endpoint: $endpoint,
            query: $query,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\JSONException
     * @throws Exception\ParameterIsRequiredException
     * @param array<string,mixed> $query
     * @return BunnyClientResponseInterface
     */
    public function getGlobalSearch(array $query = [])
    {
        $endpoint = new GlobalSearch();

        ParameterValidator::validate($query, $endpoint->getQuery());

        return $this->client->request(
            endpoint: $endpoint,
            query: $query,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @param array<string,mixed> $query
     * @return BunnyClientResponseInterface
     */
    public function listStorageZones(array $query = []): BunnyClientResponseInterface
    {
        $endpoint = new ListStorageZones();

        ParameterValidator::validate($query, $endpoint->getQuery());

        return $this->client->request(
            endpoint: $endpoint,
            query: $query,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param array<string,mixed> $body
     */
    public function addStorageZone(array $body): BunnyClientResponseInterface
    {
        $endpoint = new AddStorageZone();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param array<string,mixed> $body
     */
    public function checkStorageZoneAvailability(array $body): BunnyClientResponseInterface
    {
        $endpoint = new CheckStorageZoneAvailability();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function getStorageZone(int $id): BunnyClientResponseInterface
    {
        $endpoint = new GetStorageZone();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param int $id
     * @param array<string,mixed> $body
     */
    public function updateStorageZone(
        int $id,
        array $body,
    ): BunnyClientResponseInterface {
        $endpoint = new UpdateStorageZone();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function deleteStorageZone(int $id): BunnyClientResponseInterface
    {
        $endpoint = new DeleteStorageZone();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\JSONException
     * @throws Exception\ParameterIsRequiredException
     * @param int $id
     * @param array<string,mixed> $query
     * @return BunnyClientResponseInterface
     */
    public function getStorageZoneStatistics(int $id, array $query = []): BunnyClientResponseInterface
    {
        $endpoint = new GetStorageZoneStatistics();

        ParameterValidator::validate($query, $endpoint->getQuery());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
            query: $query,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function getStorageZoneConnections(int $id): BunnyClientResponseInterface
    {
        $endpoint = new GetStorageZoneConnections();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     * @param int $id
     */
    public function resetStorageZonePassword(int $id): BunnyClientResponseInterface
    {
        $endpoint = new Model\API\Base\StorageZone\ResetPassword();

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$id],
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @param array<string,mixed> $query
     * @return BunnyClientResponseInterface
     */
    public function resetStorageZoneReadOnlyPassword(array $query): BunnyClientResponseInterface
    {
        $endpoint = new Model\API\Base\StorageZone\ResetReadOnlyPassword();

        ParameterValidator::validate($query, $endpoint->getQuery());

        return $this->client->request(
            endpoint: $endpoint,
            query: $query,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     */
    public function getHomeFeed(): BunnyClientResponseInterface
    {
        $endpoint = new GetHomeFeed();

        return $this->client->request(
            endpoint: $endpoint,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     */
    public function getUserDetails(): BunnyClientResponseInterface
    {
        $endpoint = new GetUserDetails();

        return $this->client->request(
            endpoint: $endpoint,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param array<string,mixed> $body
     */
    public function updateUserDetails(array $body): BunnyClientResponseInterface
    {
        $endpoint = new UpdateUserDetails();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     */
    public function resendEmailConfirmation(): BunnyClientResponseInterface
    {
        $endpoint = new ResendEmailConfirmation();

        return $this->client->request(
            endpoint: $endpoint,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     */
    public function resetUserApiKey(): BunnyClientResponseInterface
    {
        $endpoint = new ResetAPIKey();

        return $this->client->request(
            endpoint: $endpoint,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     */
    public function listCloseAccountReasons(): BunnyClientResponseInterface
    {
        $endpoint = new ListCloseAccountReasons();

        return $this->client->request(
            endpoint: $endpoint,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param array<string,mixed> $body
     */
    public function closeAccount(array $body): BunnyClientResponseInterface
    {
        $endpoint = new CloseAccount();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     */
    public function getDpaDetails(): BunnyClientResponseInterface
    {
        $endpoint = new GetDPADetails();

        return $this->client->request(
            endpoint: $endpoint,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     */
    public function acceptDpa(): BunnyClientResponseInterface
    {
        $endpoint = new AcceptDPA();

        return $this->client->request(
            endpoint: $endpoint,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     */
    public function getDpaDetailsHtml(): BunnyClientResponseInterface
    {
        $endpoint = new GetDPADetailsHTML();

        return $this->client->request(
            endpoint: $endpoint,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     */
    public function listNotifications(): BunnyClientResponseInterface
    {
        $endpoint = new ListNotifications();

        return $this->client->request(
            endpoint: $endpoint,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     */
    public function setNotificationsOpened(): BunnyClientResponseInterface
    {
        $endpoint = new SetNotificationsOpened();

        return $this->client->request(
            endpoint: $endpoint,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     */
    public function getMarketingDetails(): BunnyClientResponseInterface
    {
        $endpoint = new GetMarketingDetails();

        return $this->client->request(
            endpoint: $endpoint,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     */
    public function getWhatsNewItems(): BunnyClientResponseInterface
    {
        $endpoint = new GetWhatsNewItems();

        return $this->client->request(
            endpoint: $endpoint,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     */
    public function resetWhatsNew(): BunnyClientResponseInterface
    {
        $endpoint = new ResetWhatsNew();

        return $this->client->request(
            endpoint: $endpoint,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @return BunnyClientResponseInterface
     */
    public function generateTwoFactorAuthenticationVerification(): BunnyClientResponseInterface
    {
        $endpoint = new GenerateTwoFactorAuthenticationVerification();

        return $this->client->request(
            endpoint: $endpoint,
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param array<string,mixed> $body
     */
    public function disableTwoFactorAuthentication(array $body): BunnyClientResponseInterface
    {
        $endpoint = new DisableTwoFactorAuthentication();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param array<string,mixed> $body
     */
    public function enableTwoFactorAuthentication(array $body): BunnyClientResponseInterface
    {
        $endpoint = new EnableTwoFactorAuthentication();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            body: BodyContentHelper::getBody($body),
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @return BunnyClientResponseInterface
     * @param array<string,mixed> $body
     */
    public function verifyTwoFactorAuthenticationCode(array $body): BunnyClientResponseInterface
    {
        $endpoint = new VerifyTwoFactorAuthenticationCode();

        ParameterValidator::validate($body, $endpoint->getBody());

        return $this->client->request(
            endpoint: $endpoint,
            body: BodyContentHelper::getBody($body),
        );
    }
}
