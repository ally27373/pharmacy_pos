<?php

namespace App\Services;

class ProductDemandForecastService
{
    private string $forecastFile;

    public function __construct(?string $projectRoot = null)
    {
        $projectRoot = $projectRoot ?: dirname(__DIR__, 2);
        $this->forecastFile = $projectRoot
            . DIRECTORY_SEPARATOR . 'sarima_forecasting'
            . DIRECTORY_SEPARATOR . 'outputs'
            . DIRECTORY_SEPARATOR . 'deployment'
            . DIRECTORY_SEPARATOR . 'product_demand_forecast_30_day.json';
    }

    public function isAvailable(): bool
    {
        return is_file($this->forecastFile) && filesize($this->forecastFile) > 0;
    }

    public function getForecast(): array
    {
        if (!$this->isAvailable()) {
            return [
                'success' => false,
                'message' => 'Product demand forecast file is not available.',
                'data' => [],
                'metadata' => []
            ];
        }

        $json = file_get_contents($this->forecastFile);
        if ($json === false) {
            return [
                'success' => false,
                'message' => 'Unable to read product demand forecast file.',
                'data' => [],
                'metadata' => []
            ];
        }

        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            return [
                'success' => false,
                'message' => 'Product demand forecast JSON is invalid.',
                'data' => [],
                'metadata' => []
            ];
        }

        $products = $payload['products'] ?? [];
        if (!is_array($products)) {
            $products = [];
        }

        $data = [];
        foreach ($products as $row) {
            if (!is_array($row) || empty($row['product_name'])) {
                continue;
            }

            $data[] = [
                'rank' => (int) ($row['rank'] ?? count($data) + 1),
                'product_name' => (string) $row['product_name'],
                'historical_units_sold' => (int) ($row['historical_units_sold'] ?? 0),
                'observed_sale_days' => (int) ($row['observed_sale_days'] ?? 0),
                'forecast_30_day_units' => max(0, (float) ($row['forecast_30_day_units'] ?? 0)),
                'average_daily_forecast' => max(0, (float) ($row['average_daily_forecast'] ?? 0)),
                'demand_level' => (string) ($row['demand_level'] ?? 'Unknown'),
                'peak_month' => (string) ($row['peak_month'] ?? ''),
                'peak_month_units' => max(0, (float) ($row['peak_month_units'] ?? 0)),
                'monthly_forecast_2026' => is_array($row['monthly_forecast_2026'] ?? null) ? $row['monthly_forecast_2026'] : [],
                'forecast' => is_array($row['forecast'] ?? null) ? $row['forecast'] : [],
            ];
        }

        return [
            'success' => !empty($data),
            'message' => !empty($data)
                ? 'Product demand forecasts loaded successfully.'
                : 'No product demand forecast records found.',
            'data' => $data,
            'metadata' => is_array($payload['metadata'] ?? null) ? $payload['metadata'] : []
        ];
    }

    public function getProducts(): array
    {
        return $this->getForecast()['data'] ?? [];
    }
}
