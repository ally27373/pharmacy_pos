<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once '../../../../config/session.php';
require_once '../../../../app/Middleware/AuthMiddleware.php';
require_once '../../../../app/Controllers/ReportsController.php';

AuthMiddleware::check();

try {
    $controller = new ReportsController();

    $result = $controller->getReportData([
        'page' => (int) ($_GET['page'] ?? 1),
        'limit' => (int) ($_GET['limit'] ?? 25),
        'search' => (string) ($_GET['search'] ?? ''),
        'source' => (string) ($_GET['source'] ?? 'All'),
        'category' => (string) ($_GET['category'] ?? ''),
        'type' => (string) ($_GET['type'] ?? ''),
        'period' => (string) ($_GET['period'] ?? 'weekly'),
        'from' => (string) ($_GET['from'] ?? ''),
        'to' => (string) ($_GET['to'] ?? ''),
    ]);

    echo json_encode([
        'success' => true,
        'rows' => $result['rows'],
        'pagination' => $result['pagination'],
        'filters' => $result['filters'],
        'categories' => $controller->getCategories(),
        'types' => $controller->getTypes(),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);

    error_log('Reports AJAX error: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Unable to load report data. Please check the server error log.',
    ]);
}
