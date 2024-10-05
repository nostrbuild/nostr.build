<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class InvoicePaymentMethod extends AbstractResult
{
    /**
     * @return InvoicePayment[]
     */
    public function getPayments(): array
    {
        $r = [];
        $data = $this->getData();
        foreach ($data['payments'] as $payment) {
            $r[] = new \BTCPayServer\Result\InvoicePayment($payment);
        }

        return $r;
    }

    public function getDestination(): string
    {
        $data = $this->getData();
        return $data['destination'];
    }

    public function getRate(): string
    {
        $data = $this->getData();
        return $data['rate'];
    }

    public function getPaymentMethodPaid(): string
    {
        $data = $this->getData();
        return $data['paymentMethodPaid'];
    }

    public function getTotalPaid(): string
    {
        $data = $this->getData();
        return $data['totalPaid'];
    }

    public function getDue(): string
    {
        $data = $this->getData();
        return $data['due'];
    }

    public function getAmount(): string
    {
        $data = $this->getData();
        return $data['amount'];
    }

    public function getNetworkFee(): string
    {
        $data = $this->getData();
        // BTCPay 2.0.0 compatibility: networkFee was renamed to paymentMethodFee.
        return $data['networkFee'] ?? $data['paymentMethodFee'];
    }

    public function getPaymentMethod(): string
    {
        $data = $this->getData();
        // BTCPay 2.0.0 compatibility: paymentMethod was renamed to paymentMethodId.
        return $data['paymentMethod'] ?? $data['paymentMethodId'];
    }

    public function getCryptoCode(): string
    {
        $data = $this->getData();

        // For future compatibility check if cryptoCode exists.
        if (isset($data['cryptoCode'])) {
            return $data['cryptoCode'];
        } else {
            // Extract cryptoCode from paymentMethod string.
            $parts = explode('-', $this->getPaymentMethod());
            return $parts[0];
        }
    }

    /**
     * New field as of BTCPay 2.0.0.
     */
    public function getCurrency(): ?string
    {
        $data = $this->getData();
        return $data['currency'] ?? null;
    }
}
