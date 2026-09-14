<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once '../../../../config/session.php';
require_once '../../../Middleware/AuthMiddleware.php';
require_once '../../../Controllers/NotificationController.php';

try {
    AuthMiddleware::check();

    $controller = new NotificationController();
    $result = $controller->getNotifications();

    echo json_encode([
        'success' => true,
        ...$result,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to load notifications.',
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
