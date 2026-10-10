<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../config/session.php';
require_once __DIR__ . '/../../../Middleware/AuthMiddleware.php';

header('Content-Type: application/json; charset=utf-8');

/*
|--------------------------------------------------------------------------
| Access control
|--------------------------------------------------------------------------
|
| The Analytics Dashboard is Administrator-only, and this endpoint exposes
| the same figures it does. admin() is not usable here: it echoes an HTML
| page on failure, so a refused request would answer a JSON caller with
| markup. This was a hand-rolled role check before AuthMiddleware grew the
| JSON-native guards; it now uses requireAdminApi(), which also re-checks
| that the account still exists and is still Active.
|
*/
AuthMiddleware::requireAdminApi();

require_once __DIR__ . '/../../../Controllers/DashboardController.php';

/*
|--------------------------------------------------------------------------
| Inputs
|--------------------------------------------------------------------------
|
| period is whitelisted rather than trusted: getDateCondition() would fall
| back to a safe default for anything unknown, but an explicit list keeps
| the modal and the card it was opened from guaranteed to agree.
|
*/
$allowedPeriods = [
    'today',
    '7days',
    '30days',
    '3months',
    '1year',
    'all'
];

$allowedMetrics = [
    'sales',
    'items',
    'transactions',
    'growth'
];

$period = (string) ($_GET['period'] ?? '7days');
$metric = (string) ($_GET['metric'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));

if (!in_array($period, $allowedPeriods, true)) {
    $period = '7days';
}

if (!in_array($metric, $allowedMetrics, true)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unknown KPI metric.'
    ]);
    exit;
}

$perPage = 20;

try {

    $controller = new DashboardController();

    $detail = $controller->getKpiDetail(
        $metric,
        $period,
        $page,
        $perPage
    );

    echo json_encode([
        'status' => 'success',
        'period' => $period,
        'detail' => $detail
    ]);

} catch (Throwable $e) {

    http_response_code(400);

    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);

}