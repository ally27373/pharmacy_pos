<?php

namespace App\Services;

class ForecastService
{
    private string $forecastFile;
    private string $statusFile;

    public function __construct(?string $projectRoot = null)
    {
        $projectRoot = $projectRoot ?: dirname(__DIR__, 2);
        $deploymentDir = $projectRoot
            . DIRECTORY_SEPARATOR . 'sarima_forecasting'
            . DIRECTORY_SEPARATOR . 'outputs'
            . DIRECTORY_SEPARATOR . 'deployment';

        $this->forecastFile = $deploymentDir . DIRECTORY_SEPARATOR . 'forecast_30_day.json';
        $this->statusFile = $deploymentDir . DIRECTORY_SEPARATOR . 'forecast_status.json';
    }

    public function isAvailable(): bool
    {
        return is_file($this->forecastFile) && filesize($this->forecastFile) > 0;
    }

    public function getStatus(): array
    {
        $status = [];
        if (is_file($this->statusFile) && filesize($this->statusFile) > 0) {
            $json = file_get_contents($this->statusFile);
            $decoded = $json !== false ? json_decode($json, true) : null;
            if (is_array($decoded)) {
                $status = $decoded;
            }
        }

        $forecast = $this->readForecastPayload();
        $metadata = $forecast['metadata'];
        $data = $forecast['data'];

        $dates = [];
        foreach ($data as $row) {
            if (!empty($row['date'])) {
                $dates[] = (string) $row['date'];
            }
        }
        sort($dates);

        $forecastStart = $dates[0] ?? null;
        $forecastEnd = $dates ? $dates[count($dates) - 1] : null;
        $today = date('Y-m-d');
        $isStale = $forecastEnd !== null && $forecastEnd < $today;

        $status['forecast_start'] = $forecastStart;
        $status['forecast_end'] = $forecastEnd;
        $status['forecast_generated_at'] = $metadata['forecast_generated_at'] ?? null;
        $status['model_order'] = $metadata['model_order'] ?? [1, 0, 2];
        $status['seasonal_order'] = $metadata['seasonal_order'] ?? [1, 0, 1, 7];
        $status['evaluation_metrics'] = $metadata['evaluation_metrics'] ?? [
            'MAE' => 54.1700,
            'RMSE' => 60.8193,
            'MAPE' => 14.53,
            'SMAPE' => 14.55,
        ];
        $status['today'] = $today;
        $status['is_stale'] = $isStale;

        if ($isStale && !empty($data)) {
            $status['display_status'] = 'stale';
            $status['display_label'] = 'Baseline Forecast • Update Pending';
            $status['display_reason'] = 'The deployed forecast period has ended. The validated baseline is retained until sufficient legitimate post-training POS demand is available for an update.';
        } elseif (($status['status'] ?? '') === 'updated' && ($status['ready'] ?? false) === true) {
            $status['display_status'] = 'current';
            $status['display_label'] = 'Forecast Updated';
            $status['display_reason'] = 'Forecast updated from the accumulated post-training demand history.';
        } elseif (!empty($data)) {
            $status['display_status'] = 'baseline';
            $status['display_label'] = 'Baseline Forecast';
            $status['display_reason'] = $status['reason'] ?? 'Validated baseline forecast is available.';
        } else {
            $status['display_status'] = 'unavailable';
            $status['display_label'] = 'Forecast Unavailable';
            $status['display_reason'] = 'No deployed SARIMA forecast is available.';
        }

        return $status;
    }

    public function getForecast(): array
    {
        if (!$this->isAvailable()) {
            return [
                'success' => false,
                'message' => 'SARIMA forecast file is not available.',
                'data' => [],
                'metadata' => [],
                'status' => $this->getStatus()
            ];
        }

        $payload = $this->readForecastPayload();
        if ($payload['error'] !== null) {
            return [
                'success' => false,
                'message' => $payload['error'],
                'data' => [],
                'metadata' => $payload['metadata'],
                'status' => $this->getStatus()
            ];
        }

        $data = $payload['data'];

        return [
            'success' => count($data) > 0,
            'message' => count($data) > 0 ? 'SARIMA forecast loaded successfully.' : 'No valid SARIMA forecast records found.',
            'data' => $data,
            'metadata' => $payload['metadata'],
            'status' => $this->getStatus()
        ];
    }

    public function getForecastRecords(): array
    {
        return $this->getForecast()['data'] ?? [];
    }

    private function readForecastPayload(): array
    {
        if (!$this->isAvailable()) {
            return ['data' => [], 'metadata' => [], 'error' => 'SARIMA forecast file is not available.'];
        }

        $json = file_get_contents($this->forecastFile);
        if ($json === false) {
            return ['data' => [], 'metadata' => [], 'error' => 'Unable to read SARIMA forecast file.'];
        }

        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            return ['data' => [], 'metadata' => [], 'error' => 'SARIMA forecast JSON is invalid.'];
        }

        $records = $payload['forecast'] ?? $payload['data'] ?? $payload;
        if (!is_array($records)) {
            return ['data' => [], 'metadata' => $payload['metadata'] ?? [], 'error' => 'SARIMA forecast JSON contains no forecast records.'];
        }

        $data = [];
        foreach ($records as $row) {
            if (!is_array($row)) continue;

            $date = $row['Date'] ?? $row['date'] ?? null;
            $forecast = $row['Forecast_Quantity'] ?? $row['forecast_quantity'] ?? $row['forecast'] ?? null;
            if ($date === null || $forecast === null) continue;

            $item = [
                'date' => (string) $date,
                'forecast_quantity' => max(0, (float) $forecast)
            ];

            if (isset($row['Lower_Bound']) || isset($row['lower_bound'])) {
                $item['lower_bound'] = max(0, (float) ($row['Lower_Bound'] ?? $row['lower_bound']));
            }
            if (isset($row['Upper_Bound']) || isset($row['upper_bound'])) {
                $item['upper_bound'] = max(0, (float) ($row['Upper_Bound'] ?? $row['upper_bound']));
            }

            $data[] = $item;
        }

        return ['data' => $data, 'metadata' => $payload['metadata'] ?? [], 'error' => null];
    }
}
