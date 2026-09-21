<?php

declare(strict_types=1);

namespace Tests\Unit\ReportsForecasting;

require_once __DIR__ . '/../../Support/MocksPdo.php';
require_once __DIR__ . '/Support/ForecastExecStub.php';

use App\Services\ForecastRefreshService;
use PHPUnit\Framework\TestCase;
use PDO;
use RuntimeException;

/**
 * ForecastRefreshService talks to two external systems: a PDO connection
 * (mocked via MocksPdo, per tests/TESTING.md) and a shelled-out Python
 * process (out of scope per the task brief — stubbed via the
 * App\Services\exec() override in Support/ForecastExecStub.php so no real
 * Python interpreter is ever invoked).
 *
 * A minimal DI seam (optional $projectRoot constructor param, see
 * app/Services/ForecastRefreshService.php) points the service at an
 * isolated temp directory instead of the real sarima_forecasting/ tree.
 */
final class ForecastRefreshServiceTest extends TestCase
{
    use \MocksPdo;

    private string $tempRoot;

    protected function setUp(): void
    {
        \ForecastExecStub::reset();
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pp_refresh_' . bin2hex(random_bytes(6));
        mkdir($this->scriptsDir(), 0777, true);
        mkdir($this->deploymentDir(), 0777, true);
    }

    protected function tearDown(): void
    {
        \ForecastExecStub::reset();
        $this->removeDir($this->tempRoot);
    }

    private function sarimaRoot(): string
    {
        return $this->tempRoot . DIRECTORY_SEPARATOR . 'sarima_forecasting';
    }

    private function scriptsDir(): string
    {
        return $this->sarimaRoot() . DIRECTORY_SEPARATOR . 'scripts';
    }

    private function deploymentDir(): string
    {
        return $this->sarimaRoot() . DIRECTORY_SEPARATOR . 'outputs' . DIRECTORY_SEPARATOR . 'deployment';
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

    private function writeMainScript(): void
    {
        file_put_contents($this->scriptsDir() . DIRECTORY_SEPARATOR . '07_forecast_generation.py', "# stub\n");
    }

    private function writeAnnualScript(): void
    {
        file_put_contents($this->scriptsDir() . DIRECTORY_SEPARATOR . '09_annual_forecast.py', "# stub\n");
    }

    private function pdoWithRows(array $rows): PDO&\PHPUnit\Framework\MockObject\MockObject
    {
        $statement = $this->createStatementMock();
        $statement->method('fetchAll')->willReturn($rows);

        $pdo = $this->createPdoMock();
        $pdo->expects($this->once())->method('query')->willReturn($statement);

        return $pdo;
    }

    public function test_export_latest_daily_demand_writes_csv_and_returns_summary(): void
    {
        $pdo = $this->pdoWithRows([
            ['date' => '2026-01-01', 'quantity_sold' => '10'],
            ['date' => '2026-01-02', 'quantity_sold' => '15'],
        ]);
        $service = new ForecastRefreshService($pdo, $this->tempRoot);

        $result = $service->exportLatestDailyDemand();

        $this->assertSame(2, $result['records']);
        $this->assertSame('2026-01-01', $result['first_date']);
        $this->assertSame('2026-01-02', $result['last_date']);

        $csvPath = $this->deploymentDir() . DIRECTORY_SEPARATOR . 'latest_daily_demand.csv';
        $this->assertFileExists($csvPath);
        $this->assertSame($csvPath, $result['path']);

        $lines = array_map('str_getcsv', file($csvPath));
        $this->assertSame(['date', 'quantity_sold'], $lines[0]);
        $this->assertSame(['2026-01-01', '10'], $lines[1]);
        $this->assertSame(['2026-01-02', '15'], $lines[2]);
    }

    public function test_export_latest_daily_demand_throws_when_no_sales_rows(): void
    {
        $pdo = $this->pdoWithRows([]);
        $service = new ForecastRefreshService($pdo, $this->tempRoot);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No completed POS sales are available for SARIMA demand input.');

        $service->exportLatestDailyDemand();
    }

    public function test_refresh_throws_when_main_script_missing(): void
    {
        // scriptsDir() exists but the 07_forecast_generation.py file does not.
        $pdo = $this->pdoWithRows([['date' => '2026-01-01', 'quantity_sold' => '1']]);
        $service = new ForecastRefreshService($pdo, $this->tempRoot);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SARIMA forecast generation script is missing or empty.');

        $service->refresh();
    }

    public function test_refresh_throws_when_main_exec_exits_non_zero(): void
    {
        $this->writeMainScript();
        \ForecastExecStub::queueResult(1, ['boom: training failed']);

        $pdo = $this->pdoWithRows([['date' => '2026-01-01', 'quantity_sold' => '1']]);
        $service = new ForecastRefreshService($pdo, $this->tempRoot);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom: training failed');

        $service->refresh();
    }

    public function test_refresh_succeeds_and_reports_missing_annual_script_without_throwing(): void
    {
        $this->writeMainScript();
        // No annual script written: refresh() must still return successfully.
        \ForecastExecStub::queueResult(0, ['main forecast ok']);

        $pdo = $this->pdoWithRows([['date' => '2026-01-01', 'quantity_sold' => '1']]);
        $service = new ForecastRefreshService($pdo, $this->tempRoot);

        $result = $service->refresh();

        $this->assertTrue($result['success']); // no status.json -> defaults to true
        $this->assertFalse($result['annual_forecast']['success']);
        $this->assertSame(
            'Annual SARIMA forecast generation script is missing or empty.',
            $result['annual_forecast']['error']
        );
        $this->assertSame('main forecast ok', $result['output']);
    }

    public function test_refresh_reports_annual_failure_without_blocking_main_result(): void
    {
        $this->writeMainScript();
        $this->writeAnnualScript();
        \ForecastExecStub::queueResult(0, ['main ok']);
        \ForecastExecStub::queueResult(2, ['annual failed: insufficient data']);

        $pdo = $this->pdoWithRows([['date' => '2026-01-01', 'quantity_sold' => '1']]);
        $service = new ForecastRefreshService($pdo, $this->tempRoot);

        $result = $service->refresh();

        $this->assertTrue($result['success']);
        $this->assertFalse($result['annual_forecast']['success']);
        $this->assertSame('annual failed: insufficient data', $result['annual_forecast']['error']);
    }

    public function test_refresh_reads_ready_flag_from_status_file(): void
    {
        $this->writeMainScript();
        $this->writeAnnualScript();
        file_put_contents(
            $this->deploymentDir() . DIRECTORY_SEPARATOR . 'forecast_status.json',
            json_encode(['status' => 'updated', 'ready' => false])
        );
        \ForecastExecStub::queueResult(0, ['main ok']);
        \ForecastExecStub::queueResult(0, ['annual ok']);

        $pdo = $this->pdoWithRows([['date' => '2026-01-01', 'quantity_sold' => '1']]);
        $service = new ForecastRefreshService($pdo, $this->tempRoot);

        $result = $service->refresh();

        $this->assertFalse($result['success']);
        $this->assertSame(['status' => 'updated', 'ready' => false], $result['status']);
    }

    public function test_refresh_uses_python_bin_override_in_command(): void
    {
        $this->writeMainScript();
        putenv('PYTHON_BIN=/custom/python3.11');
        \ForecastExecStub::queueResult(0, ['ok']);

        $pdo = $this->pdoWithRows([['date' => '2026-01-01', 'quantity_sold' => '1']]);
        $service = new ForecastRefreshService($pdo, $this->tempRoot);

        try {
            $service->refresh();
        } finally {
            putenv('PYTHON_BIN');
        }

        $calls = \ForecastExecStub::calls();
        $this->assertNotEmpty($calls);
        $this->assertStringContainsString('/custom/python3.11', $calls[0]);
    }
}
