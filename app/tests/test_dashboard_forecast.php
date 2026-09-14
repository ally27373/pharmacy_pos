<?php

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