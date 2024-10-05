<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class StorePaymentMethodCollection extends AbstractListResult
{
    /**
     * @return AbstractStorePaymentMethodResult[]
     */
    public function all(): array
    {
        $r = [];
        foreach ($this->getData() as $paymentMethod => $paymentMethodData) {
            // BTCPay 2.0 compatibility: List is not a keyed array anymore so fix it here.
            if (is_numeric($paymentMethod)) {
                $paymentMethod = $paymentMethodData['paymentMethodId'];
                // Extract the cryptoCode from the paymentMethodId. e.g. "BTC-CHAIN" -> "BTC"
                $parts = explode('-', $paymentMethod);
                $extractedCryptoCode = $parts[0];
            }

            // Consistency: Flatten the array to be consistent with the specific
            // payment method endpoints.
            if (isset($paymentMethodData['data'])) {
                $paymentMethodData += $paymentMethodData['data'];
                unset($paymentMethodData['data']);
            }

            // BTCPay 2.0 compatibility: Handle config data if exists.
            if (isset($paymentMethodData['config'])) {
                $paymentMethodData += $paymentMethodData['config'];
                unset($paymentMethodData['config']);
            }

            // BTCPay 2.0 compatibility: Check for renamed LN payment method id.
            if (preg_match('/(LightningNetwork|-LN$)/', $paymentMethod)) {
                // Consistency: Add back the cryptoCode missing on this endpoint
                // results until it is there.
                if (!isset($paymentMethodData['cryptoCode'])) {
                    $paymentMethodData['cryptoCode'] = str_replace('-LightningNetwork', '', $paymentMethod);
                }

                // BTCPay 2.0 compatibility: put the extracted cryptoCode in the cryptoCode field.
                if (isset($extractedCryptoCode)) {
                    $paymentMethodData['cryptoCode'] = $extractedCryptoCode;
                }

                $r[] = new StorePaymentMethodLightningNetwork($paymentMethodData, $paymentMethod);
            } else {
                // Consistency: Add back the cryptoCode missing on this endpoint
                // results until it is there.
                if (!isset($paymentMethodData['cryptoCode'])) {
                    $paymentMethodData['cryptoCode'] = $paymentMethod;
                }

                // BTCPay 2.0 compatibility: put the currency code in the cryptoCode field.
                if (isset($extractedCryptoCode)) {
                    $paymentMethodData['cryptoCode'] = $extractedCryptoCode;
                }

                $r[] = new StorePaymentMethodOnChain($paymentMethodData, $paymentMethod);
            }
        }
        return $r;
    }
}
