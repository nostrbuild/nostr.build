<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class Store extends AbstractResult
{
    public function getName(): string
    {
        $data = $this->getData();
        return $data['name'];
    }

    public function getWebsite(): string
    {
        $data = $this->getData();
        return $data['website'];
    }

    public function getDefaultCurrency(): string
    {
        $data = $this->getData();
        return $data['defaultCurrency'];
    }

    public function getInvoiceExpiration(): int
    {
        $data = $this->getData();
        return $data['invoiceExpiration'];
    }

    public function getMonitoringExpiration(): int
    {
        $data = $this->getData();
        return $data['monitoringExpiration'];
    }

    public function getSpeedPolicy(): string
    {
        $data = $this->getData();
        return $data['speedPolicy'];
    }

    public function getLightningDescriptionTemplate(): string
    {
        $data = $this->getData();
        return $data['lightningDescriptionTemplate'];
    }

    public function getPaymentTolerance(): int
    {
        $data = $this->getData();
        return $data['paymentTolerance'];
    }

    public function anyoneCanCreateInvoice(): bool
    {
        $data = $this->getData();
        return $data['anyoneCanCreateInvoice'];
    }

    public function requiresRefundEmail(): bool
    {
        $data = $this->getData();
        return $data['requiresRefundEmail'];
    }

    public function lightningAmountInSatoshi(): bool
    {
        $data = $this->getData();
        return $data['lightningAmountInSatoshi'];
    }

    public function lightningPrivateRouteHints(): bool
    {
        $data = $this->getData();
        return $data['lightningPrivateRouteHints'];
    }

    public function onChainWithLnInvoiceFallback(): bool
    {
        $data = $this->getData();
        return $data['onChainWithLnInvoiceFallback'];
    }

    public function redirectAutomatically(): bool
    {
        $data = $this->getData();
        return $data['redirectAutomatically'];
    }

    public function showRecommendedFee(): bool
    {
        $data = $this->getData();
        return $data['showRecommendedFee'];
    }

    public function getRecommendedFeeBlockTarget(): int
    {
        $data = $this->getData();
        return $data['recommendedFeeBlockTarget'];
    }

    public function getDefaultLang(): string
    {
        $data = $this->getData();
        return $data['defaultLang'];
    }

    public function getCustomLogo(): string
    {
        $data = $this->getData();
        return $data['customLogo'];
    }

    public function getCustomCSS(): string
    {
        $data = $this->getData();
        return $data['customCSS'];
    }

    public function getHtmlTitle(): string
    {
        $data = $this->getData();
        return $data['htmlTitle'];
    }

    public function getNetworkFeeMode(): string
    {
        $data = $this->getData();
        return $data['networkFeeMode'];
    }

    public function payJoinEnabled(): bool
    {
        $data = $this->getData();
        return $data['payJoinEnabled'];
    }

    public function lazyPaymentMethods(): bool
    {
        $data = $this->getData();
        return $data['lazyPaymentMethods'];
    }

    public function getDefaultPaymentMethod(): string
    {
        $data = $this->getData();
        return $data['defaultPaymentMethod'];
    }

    public function getId(): string
    {
        $data = $this->getData();
        return $data['id'];
    }
}
