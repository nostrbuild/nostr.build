<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class Offering extends AbstractResult
{
    public function getId(): string
    {
        return $this->getData()['id'];
    }

    public function getStoreId(): string
    {
        return $this->getData()['storeId'];
    }

    public function getAppId(): ?string
    {
        return $this->getData()['appId'] ?? null;
    }

    public function getAppName(): ?string
    {
        return $this->getData()['appName'] ?? null;
    }

    public function getSuccessRedirectUrl(): ?string
    {
        return $this->getData()['successRedirectUrl'] ?? null;
    }

    public function getMetadata(): ?array
    {
        return $this->getData()['metadata'] ?? null;
    }

    /**
     * @return OfferingPlan[]
     */
    public function getPlans(): array
    {
        $plans = [];
        if (isset($this->getData()['plans']) && is_array($this->getData()['plans'])) {
            foreach ($this->getData()['plans'] as $plan) {
                $plans[] = new OfferingPlan($plan);
            }
        }
        return $plans;
    }

    /**
     * @return Feature[]
     */
    public function getFeatures(): array
    {
        $features = [];
        if (isset($this->getData()['features']) && is_array($this->getData()['features'])) {
            foreach ($this->getData()['features'] as $feature) {
                $features[] = new Feature($feature);
            }
        }
        return $features;
    }
}
