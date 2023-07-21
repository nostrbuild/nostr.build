<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class NotificationList extends AbstractListResult
{
    /**
     * @return \BTCPayServer\Result\Notification[]
     */
    public function all(): array
    {
        $notifications = [];
        foreach ($this->getData() as $notification) {
            $notifications[] = new \BTCPayServer\Result\Notification($notification);
        }
        return $notifications;
    }
}
