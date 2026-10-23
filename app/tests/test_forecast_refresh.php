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


require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__) . '/Services/ForecastRefreshService.php';

use App\Services\ForecastRefreshService;

try {
    $database = new Database();
    $pdo = $database->connect();

    $service = new ForecastRefreshService($pdo);
    $result = $service->refresh();

    echo "LIVE SARIMA REFRESH TEST\n";
    echo "Input records: {$result['input']['records']}\n";
    echo "First date: {$result['input']['first_date']}\n";
    echo "Last date: {$result['input']['last_date']}\n";
    echo "CSV: {$result['input']['path']}\n";
    echo "Status: " . ($result['status']['status'] ?? 'unknown') . "\n";
    echo "Ready: " . (($result['status']['ready'] ?? false) ? 'YES' : 'NO') . "\n";
    if (!empty($result['status']['reason'])) {
        echo "Reason: {$result['status']['reason']}\n";
    }

    // Validate that the deployed artifact is now anchored to the latest
    // legitimate live POS observation. This is required for both baseline
    // and updated forecasts.
    $forecastPath = dirname(__DIR__, 2)
        . DIRECTORY_SEPARATOR . 'sarima_forecasting'
        . DIRECTORY_SEPARATOR . 'outputs'
        . DIRECTORY_SEPARATOR . 'deployment'
        . DIRECTORY_SEPARATOR . 'forecast_30_day.json';

    if (!is_file($forecastPath)) {
        throw new RuntimeException('Forecast artifact was not generated.');
    }

    $forecastPayload = json_decode(file_get_contents($forecastPath), true);
    $forecastRecords = $forecastPayload['forecast'] ?? [];
    $forecastMetadata = $forecastPayload['metadata'] ?? [];

    if (!is_array($forecastRecords) || count($forecastRecords) !== 30) {
        throw new RuntimeException('Expected exactly 30 deployed forecast records.');
    }

    $firstForecastDate = (string) ($forecastRecords[0]['date'] ?? $forecastRecords[0]['Date'] ?? '');
    if ($firstForecastDate === '' || $firstForecastDate <= $result['input']['last_date']) {
        throw new RuntimeException('Forecast does not begin after the latest live POS observation.');
    }

    // training_start must always keep the 2025 origin. training_end stays at
    // '2025-12-31' for the pristine baseline and extends to the newest live
    // observation after a successful refit (both must be valid ISO dates).
    $trainingStart = (string) ($forecastMetadata['training_start'] ?? '');
    $trainingEnd = (string) ($forecastMetadata['training_end'] ?? '');
    if ($trainingStart !== '2025-01-01' || $trainingEnd < '2025-12-31') {
        throw new RuntimeException('Forecast metadata does not preserve the 2025 training origin.');
    }

    echo "Forecast artifact: PASSED\n";
    echo "Forecast first date: {$firstForecastDate}\n";
    echo "Forecast records: " . count($forecastRecords) . "\n";
    echo "Training period: "
        . ($forecastMetadata['training_start'] ?? 'N/A')
        . " to "
        . ($forecastMetadata['training_end'] ?? 'N/A')
        . "\n";
    echo "PASSED (refresh pipeline executed safely)\n";
} catch (Throwable $e) {
    fwrite(STDERR, "BLOCKED/FAILED: {$e->getMessage()}\n");
    exit(1);
}
