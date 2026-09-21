<?php

declare(strict_types=1);

namespace Tests\Unit\ReportsForecasting;

use App\Services\ForecastService;
use PHPUnit\Framework\TestCase;

/**
 * ForecastService reads the deployed SARIMA 30-day forecast JSON (and its
 * companion status JSON) straight off disk. Its constructor already takes
 * an optional $projectRoot, so no DI seam is needed here: every test below
 * points it at an isolated temp directory instead of the real
 * sarima_forecasting/outputs/deployment tree, so results are deterministic
 * and independent of whatever the repo's actual forecast data looks like.
 */
final class ForecastServiceTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pp_forecastsvc_' . bin2hex(random_bytes(6));
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
            $this->deploymentDir() . DIRECTORY_SEPARATOR . 'forecast_30_day.json',
            is_string($payload) ? $payload : json_encode($payload)
        );
    }

    /** @param mixed $payload */
    private function writeStatus($payload): void
    {
        file_put_contents(
            $this->deploymentDir() . DIRECTORY_SEPARATOR . 'forecast_status.json',
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

    public function test_is_available_is_false_when_forecast_file_missing(): void
    {
        $service = new ForecastService($this->tempRoot);

        $this->assertFalse($service->isAvailable());
    }

    public function test_is_available_is_false_when_forecast_file_is_empty(): void
    {
        $this->writeForecast('');
        $service = new ForecastService($this->tempRoot);

        $this->assertFalse($service->isAvailable());
    }

    public function test_is_available_is_true_when_forecast_file_present_and_non_empty(): void
    {
        $this->writeForecast(['forecast' => []]);
        $service = new ForecastService($this->tempRoot);

        $this->assertTrue($service->isAvailable());
    }

    public function test_get_forecast_reports_failure_when_file_missing(): void
    {
        $service = new ForecastService($this->tempRoot);

        $result = $service->getForecast();

        $this->assertFalse($result['success']);
        $this->assertSame('SARIMA forecast file is not available.', $result['message']);
        $this->assertSame([], $result['data']);
    }

    public function test_get_forecast_maps_upper_case_keys_and_clamps_negative_bounds_to_zero(): void
    {
        $this->writeForecast([
            'metadata' => ['forecast_generated_at' => '2026-01-01T00:00:00+00:00'],
            'forecast' => [
                [
                    'Date' => '2026-01-02',
                    'Forecast_Quantity' => 12.5,
                    'Lower_Bound' => -3.0,
                    'Upper_Bound' => 20.0,
                ],
            ],
        ]);
        $service = new ForecastService($this->tempRoot);

        $result = $service->getForecast();

        $this->assertTrue($result['success']);
        $this->assertSame('SARIMA forecast loaded successfully.', $result['message']);
        $this->assertCount(1, $result['data']);
        $this->assertSame('2026-01-02', $result['data'][0]['date']);
        $this->assertSame(12.5, $result['data'][0]['forecast_quantity']);
        // max(0, -3.0) returns the int 0 in PHP (the original-typed operand
        // that compares highest), not a float — assert the actual type too.
        $this->assertSame(0, $result['data'][0]['lower_bound']);
        $this->assertSame(20.0, $result['data'][0]['upper_bound']);
    }

    public function test_get_forecast_accepts_lower_case_keys_and_negative_quantity(): void
    {
        $this->writeForecast([
            'data' => [
                ['date' => '2026-02-01', 'forecast_quantity' => -5],
            ],
        ]);
        $service = new ForecastService($this->tempRoot);

        $result = $service->getForecast();

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['data'][0]['forecast_quantity']);
        $this->assertArrayNotHasKey('lower_bound', $result['data'][0]);
        $this->assertArrayNotHasKey('upper_bound', $result['data'][0]);
    }

    public function test_get_forecast_falls_back_to_bare_array_payload(): void
    {
        // No 'forecast' or 'data' wrapper key: the whole decoded payload is
        // treated as the record list.
        $this->writeForecast([
            ['date' => '2026-03-01', 'forecast' => 7],
        ]);
        $service = new ForecastService($this->tempRoot);

        $result = $service->getForecast();

        $this->assertTrue($result['success']);
        $this->assertSame(7.0, $result['data'][0]['forecast_quantity']);
    }

    public function test_get_forecast_skips_rows_missing_date_or_forecast(): void
    {
        $this->writeForecast([
            'forecast' => [
                ['Date' => '2026-01-01'], // no forecast value
                ['Forecast_Quantity' => 5], // no date
                'not-an-array',
                ['Date' => '2026-01-03', 'Forecast_Quantity' => 9],
            ],
        ]);
        $service = new ForecastService($this->tempRoot);

        $result = $service->getForecast();

        $this->assertCount(1, $result['data']);
        $this->assertSame('2026-01-03', $result['data'][0]['date']);
    }

    public function test_get_forecast_reports_no_records_when_all_rows_invalid(): void
    {
        $this->writeForecast(['forecast' => [['foo' => 'bar']]]);
        $service = new ForecastService($this->tempRoot);

        $result = $service->getForecast();

        $this->assertFalse($result['success']);
        $this->assertSame('No valid SARIMA forecast records found.', $result['message']);
    }

    public function test_get_forecast_reports_invalid_json(): void
    {
        $this->writeForecast('{not valid json');
        $service = new ForecastService($this->tempRoot);

        $result = $service->getForecast();

        $this->assertFalse($result['success']);
        $this->assertSame('SARIMA forecast JSON is invalid.', $result['message']);
    }

    public function test_get_forecast_records_delegates_to_get_forecast_data(): void
    {
        $this->writeForecast(['forecast' => [['Date' => '2026-01-05', 'Forecast_Quantity' => 3]]]);
        $service = new ForecastService($this->tempRoot);

        $this->assertSame($service->getForecast()['data'], $service->getForecastRecords());
    }

    public function test_get_status_is_unavailable_when_no_forecast_data(): void
    {
        $service = new ForecastService($this->tempRoot);

        $status = $service->getStatus();

        $this->assertSame('unavailable', $status['display_status']);
        $this->assertSame('Forecast Unavailable', $status['display_label']);
        $this->assertNull($status['forecast_start']);
        $this->assertNull($status['forecast_end']);
        $this->assertFalse($status['is_stale']);
    }

    public function test_get_status_is_stale_when_forecast_end_is_in_the_past(): void
    {
        $this->writeForecast([
            'forecast' => [
                ['Date' => '1999-12-30', 'Forecast_Quantity' => 1],
                ['Date' => '1999-12-31', 'Forecast_Quantity' => 2],
            ],
        ]);
        $service = new ForecastService($this->tempRoot);

        $status = $service->getStatus();

        $this->assertSame('1999-12-30', $status['forecast_start']);
        $this->assertSame('1999-12-31', $status['forecast_end']);
        $this->assertTrue($status['is_stale']);
        $this->assertSame('stale', $status['display_status']);
        $this->assertSame('Baseline Forecast • Update Pending', $status['display_label']);
    }

    public function test_get_status_is_current_when_status_file_says_updated_and_ready(): void
    {
        $future = date('Y-m-d', strtotime('+5 years'));
        $this->writeForecast(['forecast' => [['Date' => $future, 'Forecast_Quantity' => 1]]]);
        $this->writeStatus(['status' => 'updated', 'ready' => true]);
        $service = new ForecastService($this->tempRoot);

        $status = $service->getStatus();

        $this->assertFalse($status['is_stale']);
        $this->assertSame('current', $status['display_status']);
        $this->assertSame('Forecast Updated', $status['display_label']);
    }

    public function test_get_status_is_baseline_when_not_stale_and_not_updated(): void
    {
        $future = date('Y-m-d', strtotime('+5 years'));
        $this->writeForecast(['forecast' => [['Date' => $future, 'Forecast_Quantity' => 1]]]);
        $service = new ForecastService($this->tempRoot);

        $status = $service->getStatus();

        $this->assertSame('baseline', $status['display_status']);
        $this->assertSame('Baseline Forecast', $status['display_label']);
        $this->assertSame('Validated baseline forecast is available.', $status['display_reason']);
    }

    public function test_get_status_uses_default_model_metadata_when_absent(): void
    {
        $future = date('Y-m-d', strtotime('+5 years'));
        $this->writeForecast(['forecast' => [['Date' => $future, 'Forecast_Quantity' => 1]]]);
        $service = new ForecastService($this->tempRoot);

        $status = $service->getStatus();

        $this->assertSame([1, 0, 2], $status['model_order']);
        $this->assertSame([1, 0, 1, 7], $status['seasonal_order']);
        $this->assertSame(54.1700, $status['evaluation_metrics']['MAE']);
    }

    public function test_get_status_prefers_metadata_from_forecast_file_over_defaults(): void
    {
        $future = date('Y-m-d', strtotime('+5 years'));
        $this->writeForecast([
            'metadata' => [
                'forecast_generated_at' => '2026-01-01T00:00:00+00:00',
                'model_order' => [2, 1, 1],
            ],
            'forecast' => [['Date' => $future, 'Forecast_Quantity' => 1]],
        ]);
        $service = new ForecastService($this->tempRoot);

        $status = $service->getStatus();

        $this->assertSame('2026-01-01T00:00:00+00:00', $status['forecast_generated_at']);
        $this->assertSame([2, 1, 1], $status['model_order']);
    }
}
