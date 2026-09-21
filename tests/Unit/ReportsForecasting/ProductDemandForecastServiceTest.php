<?php

declare(strict_types=1);

namespace Tests\Unit\ReportsForecasting;

use App\Services\ProductDemandForecastService;
use PHPUnit\Framework\TestCase;

/**
 * ProductDemandForecastService reads the deployed per-product SARIMA
 * forecast JSON straight off disk. Its constructor already accepts an
 * optional $projectRoot, so tests point it at an isolated temp directory
 * rather than the real sarima_forecasting/outputs/deployment tree.
 */
final class ProductDemandForecastServiceTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pp_proddemand_' . bin2hex(random_bytes(6));
        mkdir($this->deploymentDir(), 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempRoot);
    }

    private function deploymentDir(): string
    {
        return $this->tempRoot
            . DIRECTORY_SEPARATOR . 'sarima_forecasting'
            . DIRECTORY_SEPARATOR . 'outputs'
            . DIRECTORY_SEPARATOR . 'deployment';
    }

    /** @param mixed $payload */
    private function writeForecast($payload): void
    {
        file_put_contents(
            $this->deploymentDir() . DIRECTORY_SEPARATOR . 'product_demand_forecast_30_day.json',
            is_string($payload) ? $payload : json_encode($payload)
        );
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function test_is_available_false_when_file_missing(): void
    {
        $service = new ProductDemandForecastService($this->tempRoot);

        $this->assertFalse($service->isAvailable());
    }

    public function test_get_forecast_reports_failure_when_file_missing(): void
    {
        $service = new ProductDemandForecastService($this->tempRoot);

        $result = $service->getForecast();

        $this->assertFalse($result['success']);
        $this->assertSame('Product demand forecast file is not available.', $result['message']);
        $this->assertSame([], $result['data']);
        $this->assertSame([], $result['metadata']);
    }

    public function test_get_forecast_reports_invalid_json(): void
    {
        $this->writeForecast('{not valid');
        $service = new ProductDemandForecastService($this->tempRoot);

        $result = $service->getForecast();

        $this->assertFalse($result['success']);
        $this->assertSame('Product demand forecast JSON is invalid.', $result['message']);
    }

    public function test_get_forecast_reports_no_records_when_products_missing(): void
    {
        $this->writeForecast(['metadata' => ['generated_at' => '2026-01-01']]);
        $service = new ProductDemandForecastService($this->tempRoot);

        $result = $service->getForecast();

        $this->assertFalse($result['success']);
        $this->assertSame('No product demand forecast records found.', $result['message']);
        $this->assertSame(['generated_at' => '2026-01-01'], $result['metadata']);
    }

    public function test_get_forecast_maps_full_row_with_defaults_for_missing_fields(): void
    {
        $this->writeForecast([
            'metadata' => ['model' => 'SARIMA'],
            'products' => [
                [
                    'rank' => 1,
                    'product_name' => 'Paracetamol 500mg',
                    'historical_units_sold' => 120,
                    'observed_sale_days' => 30,
                    'forecast_30_day_units' => 45.5,
                    'average_daily_forecast' => 1.5,
                    'demand_level' => 'High',
                    'peak_month' => 'January',
                    'peak_month_units' => 20,
                    'monthly_forecast_2026' => ['Jan' => 10],
                    'forecast' => [['date' => '2026-01-01', 'quantity' => 2]],
                ],
                [
                    // Only the required field is present; everything else
                    // should fall back to its documented default.
                    'product_name' => 'Amoxicillin 500mg',
                ],
            ],
        ]);
        $service = new ProductDemandForecastService($this->tempRoot);

        $result = $service->getForecast();

        $this->assertTrue($result['success']);
        $this->assertSame('Product demand forecasts loaded successfully.', $result['message']);
        $this->assertCount(2, $result['data']);

        $first = $result['data'][0];
        $this->assertSame(1, $first['rank']);
        $this->assertSame('Paracetamol 500mg', $first['product_name']);
        $this->assertSame(120, $first['historical_units_sold']);
        $this->assertSame(30, $first['observed_sale_days']);
        $this->assertSame(45.5, $first['forecast_30_day_units']);
        $this->assertSame(1.5, $first['average_daily_forecast']);
        $this->assertSame('High', $first['demand_level']);
        $this->assertSame('January', $first['peak_month']);
        $this->assertSame(20.0, $first['peak_month_units']);
        $this->assertSame(['Jan' => 10], $first['monthly_forecast_2026']);
        $this->assertSame([['date' => '2026-01-01', 'quantity' => 2]], $first['forecast']);

        $second = $result['data'][1];
        // rank falls back to 1-based position in the *output* array when
        // absent from the source row.
        $this->assertSame(2, $second['rank']);
        $this->assertSame('Amoxicillin 500mg', $second['product_name']);
        $this->assertSame(0, $second['historical_units_sold']);
        $this->assertSame(0, $second['observed_sale_days']);
        $this->assertSame(0, $second['forecast_30_day_units']);
        $this->assertSame(0, $second['average_daily_forecast']);
        $this->assertSame('Unknown', $second['demand_level']);
        $this->assertSame('', $second['peak_month']);
        $this->assertSame([], $second['monthly_forecast_2026']);
        $this->assertSame([], $second['forecast']);
    }

    public function test_get_forecast_skips_rows_without_product_name(): void
    {
        $this->writeForecast([
            'products' => [
                ['rank' => 1],
                ['product_name' => ''],
                'not-an-array',
                ['product_name' => 'Valid Product'],
            ],
        ]);
        $service = new ProductDemandForecastService($this->tempRoot);

        $result = $service->getForecast();

        $this->assertCount(1, $result['data']);
        $this->assertSame('Valid Product', $result['data'][0]['product_name']);
    }

    public function test_get_forecast_clamps_negative_forecast_values_to_zero(): void
    {
        $this->writeForecast([
            'products' => [
                [
                    'product_name' => 'Negative Demand Product',
                    'forecast_30_day_units' => -10,
                    'average_daily_forecast' => -1,
                    'peak_month_units' => -5,
                ],
            ],
        ]);
        $service = new ProductDemandForecastService($this->tempRoot);

        $result = $service->getForecast();

        $this->assertSame(0, $result['data'][0]['forecast_30_day_units']);
        $this->assertSame(0, $result['data'][0]['average_daily_forecast']);
        $this->assertSame(0, $result['data'][0]['peak_month_units']);
    }

    public function test_get_products_delegates_to_get_forecast_data(): void
    {
        $this->writeForecast(['products' => [['product_name' => 'X']]]);
        $service = new ProductDemandForecastService($this->tempRoot);

        $this->assertSame($service->getForecast()['data'], $service->getProducts());
    }

    public function test_get_products_empty_when_file_missing(): void
    {
        $service = new ProductDemandForecastService($this->tempRoot);

        $this->assertSame([], $service->getProducts());
    }
}
