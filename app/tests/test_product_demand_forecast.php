<?php
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
