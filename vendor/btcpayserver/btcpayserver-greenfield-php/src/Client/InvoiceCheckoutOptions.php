<?php

declare(strict_types=1);

namespace BTCPayServer\Client;

class InvoiceCheckoutOptions
{
    public const SPEED_HIGH = 'HighSpeed';

    public const SPEED_MEDIUM = 'MediumSpeed';

    public const SPEED_LOW = 'LowSpeed';

    public const SPEED_LOWMEDIUM = 'LowMediumSpeed';

    /** @var string */
    protected $speedPolicy;

    /** @var array */
    protected $paymentMethods;

    /** @var int */
    protected $expirationMinutes;

    /** @var int */
    protected $monitoringMinutes;

    /** @var int */
    protected $paymentTolerance;

    /** @var string */
    protected $redirectURL;

    /** @var bool */
    protected $redirectAutomatically;

    /** @var string */
    protected $defaultLanguage;

    public static function create(
        ?string $speedPolicy,
        ?array $paymentMethods,
        ?int $expirationMinutes,
        ?int $monitoringMinutes,
        ?int $paymentTolerance,
        ?string $redirectURL,
        ?bool $redirectAutomatically,
        ?string $defaultLanguage
    ) {
        $options = new InvoiceCheckoutOptions();
        $options->setSpeedPolicy($speedPolicy);
        $options->paymentMethods = $paymentMethods;
        $options->expirationMinutes = $expirationMinutes;
        $options->monitoringMinutes = $monitoringMinutes;
        $options->paymentTolerance = $paymentTolerance;
        $options->redirectURL = $redirectURL;
        $options->redirectAutomatically = $redirectAutomatically;
        $options->defaultLanguage = $defaultLanguage;
        return $options;
    }

    public function getSpeedPolicy(): ?string
    {
        return $this->speedPolicy;
    }

    public function setSpeedPolicy(?string $speedPolicy): self
    {
        if ($speedPolicy) {
            if (!in_array(
                $speedPolicy,
                [self::SPEED_HIGH, self::SPEED_MEDIUM, self::SPEED_LOW, self::SPEED_LOWMEDIUM],
                true
            )) {
                throw new \InvalidArgumentException('Passed value for speedPolicy is not allowed.');
            }
        }
        $this->speedPolicy = $speedPolicy;
        return $this;
    }

    public function getPaymentMethods(): ?array
    {
        return $this->paymentMethods;
    }

    public function setPaymentMethods(?array $paymentMethods): self
    {
        $this->paymentMethods = $paymentMethods;
        return $this;
    }

    public function getExpirationMinutes(): ?int
    {
        return $this->expirationMinutes;
    }

    public function setExpirationMinutes(?int $expirationMinutes): self
    {
        $this->expirationMinutes = $expirationMinutes;
        return $this;
    }

    public function getMonitoringMinutes(): ?int
    {
        return $this->monitoringMinutes;
    }

    public function setMonitoringMinutes(?int $monitoringMinutes): self
    {
        $this->monitoringMinutes = $monitoringMinutes;
        return $this;
    }

    public function getPaymentTolerance(): ?int
    {
        return $this->paymentTolerance;
    }

    public function setPaymentTolerance(?int $paymentTolerance): self
    {
        $this->paymentTolerance = $paymentTolerance;
        return $this;
    }

    public function getRedirectURL(): ?string
    {
        return $this->redirectURL;
    }

    public function setRedirectURL(?string $redirectURL): self
    {
        $this->redirectURL = $redirectURL;
        return $this;
    }

    public function isRedirectAutomatically(): ?bool
    {
        return $this->redirectAutomatically;
    }

    public function setRedirectAutomatically(?bool $redirectAutomatically): self
    {
        $this->redirectAutomatically = $redirectAutomatically;
        return $this;
    }

    public function getDefaultLanguage(): ?string
    {
        return $this->defaultLanguage;
    }

    public function setDefaultLanguage(?string $defaultLanguage): self
    {
        $this->defaultLanguage = $defaultLanguage;
        return $this;
    }

    /**
     * Converts the whole object incl. protected and private properties to an array.
     */
    public function toArray(): array
    {
        $array = [];
        $objAsArray = (array) $this;
        foreach ($objAsArray as $k => $v) {
            $separator = "\0";
            $k = rtrim($k, $separator);

            $lastIndex = strrpos($k, $separator);
            if ($lastIndex !== false) {
                $k = substr($k, $lastIndex + 1);
            }
            $array[$k] = $v;
        }

        return $array;
    }
}
