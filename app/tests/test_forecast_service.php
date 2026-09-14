<?php

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Services\ForecastService;

echo "========================================\n";
echo "SARIMA FORECAST SERVICE FINAL TEST\n";
echo "NICA XANDRA PHARMACY POS\n";
echo "========================================\n\n";

$service = new ForecastService();


// --------------------------------------------------
// 1. Forecast file availability
// --------------------------------------------------

echo "[1] Checking forecast artifact...\n";

if (!$service->isAvailable()) {

    echo "RESULT: FAILED\n";
    echo "SARIMA forecast artifact is unavailable.\n";

    exit(1);
}

echo "RESULT: PASSED\n";
echo "Forecast artifact: AVAILABLE\n\n";


// --------------------------------------------------
// 2. Load forecast
// --------------------------------------------------

echo "[2] Loading SARIMA forecast...\n";

$result = $service->getForecast();

if (!$result['success']) {

    echo "RESULT: FAILED\n";
    echo "Message: " . ($result['message'] ?? 'Unknown error') . "\n";

    exit(1);
}

$records = $result['data'] ?? [];

echo "RESULT: PASSED\n";
echo "Records loaded: " . count($records) . "\n\n";


// --------------------------------------------------
// 3. Validate forecast count
// --------------------------------------------------

echo "[3] Validating forecast horizon...\n";

if (count($records) !== 30) {

    echo "RESULT: FAILED\n";
    echo "Expected 30 forecast records.\n";
    echo "Actual: " . count($records) . "\n";

    exit(1);
}

echo "RESULT: PASSED\n";
echo "Forecast horizon: 30 days\n\n";


// --------------------------------------------------
// 4. Validate forecast records
// --------------------------------------------------

echo "[4] Validating forecast records...\n";

$invalid = 0;

foreach ($records as $row) {

    if (
        empty($row['date']) ||
        !isset($row['forecast_quantity']) ||
        !is_numeric($row['forecast_quantity'])
    ) {
        $invalid++;
        continue;
    }

    if ((float) $row['forecast_quantity'] < 0) {
        $invalid++;
    }

}

if ($invalid > 0) {

    echo "RESULT: FAILED\n";
    echo "Invalid records: {$invalid}\n";

    exit(1);
}

echo "RESULT: PASSED\n";
echo "All forecast records are valid.\n\n";


// --------------------------------------------------
// 5. Forecast summary
// --------------------------------------------------

echo "[5] Forecast summary\n";
echo "----------------------------------------\n";

$values = array_map(
    fn($row) => (float) $row['forecast_quantity'],
    $records
);

$average = array_sum($values) / count($values);
$minimum = min($values);
$maximum = max($values);

echo "Forecast days    : " . count($records) . "\n";
echo "Average forecast : " . number_format($average, 2) . "\n";
echo "Minimum forecast : " . number_format($minimum, 2) . "\n";
echo "Maximum forecast : " . number_format($maximum, 2) . "\n\n";


// --------------------------------------------------
// 6. First / last forecast
// --------------------------------------------------

echo "[6] Forecast range\n";
echo "----------------------------------------\n";

echo "First date       : " . $records[0]['date'] . "\n";
echo "First forecast   : "
    . number_format($records[0]['forecast_quantity'], 2)
    . "\n";

echo "Last date        : "
    . $records[count($records) - 1]['date']
    . "\n";

echo "Last forecast    : "
    . number_format(
        $records[count($records) - 1]['forecast_quantity'],
        2
    )
    . "\n\n";


// --------------------------------------------------
// 7. Metadata
// --------------------------------------------------

echo "[7] Model metadata\n";
echo "----------------------------------------\n";

$metadata = $result['metadata'] ?? [];

$modelName = $metadata['model_name'] ?? 'Not specified';
$modelOrder = $metadata['model_order'] ?? null;
$seasonalOrder = $metadata['seasonal_order'] ?? null;

echo "Model            : " . $modelName . "\n";
echo "Training start   : "
    . ($metadata['training_start'] ?? 'Not specified')
    . "\n";
echo "Training end     : "
    . ($metadata['training_end'] ?? 'Not specified')
    . "\n";
echo "Seasonality      : "
    . ($metadata['seasonal_period'] ?? 'Not specified')
    . " days\n";
if (is_array($modelOrder) && is_array($seasonalOrder)) {
    echo "Configuration    : SARIMA("
        . implode(',', $modelOrder)
        . ")("
        . implode(',', $seasonalOrder)
        . ")\n";
}
echo "Forecast type    : "
    . ($metadata['forecast_type'] ?? 'Not specified')
    . "\n";
echo "Live start       : "
    . ($metadata['live_operational_start'] ?? 'Not specified')
    . "\n\n";


echo "========================================\n";
echo "SARIMA FORECAST SERVICE TEST: PASSED\n";
echo "========================================\n";