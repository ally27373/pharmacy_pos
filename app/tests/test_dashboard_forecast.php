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


require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/app/Models/Dashboard.php';

$dashboard = new Dashboard();

echo "========================================\n";
echo "DASHBOARD SARIMA INTEGRATION TEST\n";
echo "NICA XANDRA PHARMACY POS\n";
echo "========================================\n\n";

echo "[1] Loading forecast through Dashboard model...\n";

$forecast = $dashboard->getForecastData();

if (empty($forecast)) {
    echo "Forecast loading: FAILED\n";
    exit(1);
}

echo "Forecast loading: PASSED\n";
echo "Records loaded: " . count($forecast) . "\n\n";

echo "[2] First forecast record\n";
echo "----------------------------------------\n";

$firstForecast = reset($forecast);
print_r($firstForecast);

echo "\n[3] Last forecast record\n";
echo "----------------------------------------\n";

$lastForecast = end($forecast);
print_r($lastForecast);

echo "\n========================================\n";
echo "DASHBOARD SARIMA INTEGRATION TEST COMPLETE\n";
echo "========================================\n";
