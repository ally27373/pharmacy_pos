<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/NotificationController.php';

try {

    $controller = new NotificationController();

    $result = $controller->getNotifications();

    echo json_encode(
        [
            'success' => true,
            'summary' => $result['summary'] ?? [
                'all' => 0,
                'out_of_stock' => 0,
                'low_stock' => 0,
                'near_expiry' => 0
            ],
            'notifications' => $result['notifications'] ?? []
        ],
        JSON_UNESCAPED_UNICODE
    );

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode(
        [
            'success' => false,
            'message' => $e->getMessage(),
            'summary' => [
                'all' => 0,
                'out_of_stock' => 0,
                'low_stock' => 0,
                'near_expiry' => 0
            ],
            'notifications' => []
        ],
        JSON_UNESCAPED_UNICODE
    );
}