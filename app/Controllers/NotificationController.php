<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/Notification.php';

class NotificationController
{
    private Notification $notification;

    public function __construct()
    {
        $this->notification = new Notification();
    }

    public function getNotifications(): array
    {
        return $this->notification->getLiveNotifications();
    }
}