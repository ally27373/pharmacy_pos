<?php

declare(strict_types=1);

/*
| Path depths from /app/export:
|   ../../  -> project root   (config/ lives here)
|   ../     -> app/            (Controllers/ lives here)
*/

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

/*
| This writes sales_history.csv to disk, so it must not be reachable
| anonymously: an unauthenticated POST could overwrite the forecasting
| dataset that the SARIMA pipeline reads. Administrator-only, and there is
| no caller for it in the UI -- it is a maintenance script.
*/
AuthMiddleware::requireAdminApi();

require_once __DIR__ . '/../Controllers/DashboardController.php';

$dashboard = new DashboardController();

$data = $dashboard->exportSalesHistory();

$csvPath = __DIR__ . '/../../python/data/sales_history.csv';

$file = @fopen($csvPath, 'w');

if ($file === false) {

    http_response_code(500);

    echo "<h2>Export failed</h2>";

    echo "<p>Could not open <code>" . htmlspecialchars($csvPath) . "</code> for writing.</p>";

    exit;

}

fputcsv($file, [
    'date',
    'product_name',
    'quantity_sold'
]);

foreach ($data as $row)
{
    fputcsv($file, [
        $row['sale_date'],
        $row['product_name'],
        $row['quantity_sold']
    ]);
}

fclose($file);

echo "<h2>✅ Sales History Exported Successfully</h2>";

echo "<p>Total Records: <strong>" . count($data) . "</strong></p>";