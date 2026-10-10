<?php

/*
| These are CLI diagnostics kept alongside the app they exercise. Serving
| them from the document root let any visitor trigger a live SARIMA refresh
| or dump forecast internals and database structure in a PHP error page.
| Refuse every non-CLI SAPI, and answer 404 so the path is not advertised.
*/
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Services\ProductDemandForecastService;

echo "PRODUCT DEMAND FORECAST TEST\n";

$service = new ProductDemandForecastService();
$result = $service->getForecast();

if (!$result['success']) {
    echo "Status: FAIL\n";
    echo "Message: {$result['message']}\n";
    exit(1);
}

$count = count($result['data']);
echo "Products: {$count}\n";
echo "Model: SARIMA(" . implode(',', $result['metadata']['model_order'] ?? []) . ")(" . implode(',', $result['metadata']['seasonal_order'] ?? []) . ")\n";
echo "Baseline: " . (($result['metadata']['baseline'] ?? false) ? 'YES' : 'NO') . "\n";
echo "Status: PASSED\n";
