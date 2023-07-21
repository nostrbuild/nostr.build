<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class User extends AbstractResult
{
    public function getId(): string
    {
        $data = $this->getData();
        return $data['id'];
    }

    public function getEmail(): string
    {
        $data = $this->getData();
        return $data['email'];
    }

    public function emailedConfirmed(): bool
    {
        $data = $this->getData();
        return $data['emailedConfirmed'];
    }

    public function requiresEmailConfirmation(): bool
    {
        $data = $this->getData();
        return $data['requiresEmailConfirmation'];
    }

    public function getCreated(): int
    {
        $data = $this->getData();
        return $data['created'];
    }

    public function getRoles(): array
    {
        $data = $this->getData();
        return $data['roles'];
    }
}
