<?php

declare(strict_types=1);

namespace Tests\Unit\ReportsForecasting;

require_once __DIR__ . '/../../../app/Controllers/DashboardController.php';

use Dashboard;
use DashboardController;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * DashboardController::getDashboardData() orchestrates ~25 Dashboard model
 * calls plus a filesystem-backed "annual forecast" artifact. The DI seam
 * (optional ?Dashboard $dashboard, optional ?string $projectRoot) lets us
 * inject a mock model and point the annual-forecast file read at an
 * isolated temp directory instead of the real sarima_forecasting/ tree.
 *
 * These tests focus on: the $_GET-driven parameter wiring (dates/limits
 * passed through to the model), the best/least-seller "mode" branching,
 * and the annual-forecast artifact's missing/empty/invalid-data handling.
 */
final class DashboardControllerTest extends TestCase
{
    private array $originalGet;
    private ?string $tempRoot = null;

    protected function setUp(): void
    {
        $this->originalGet = $_GET;
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_GET = $this->originalGet;
        if ($this->tempRoot !== null) {
            $this->removeDir($this->tempRoot);
            $this->tempRoot = null;
        }
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

    private function deploymentDir(): string
    {
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pp_dashctrl_' . bin2hex(random_bytes(6));
        $dir = $this->tempRoot
            . DIRECTORY_SEPARATOR . 'sarima_forecasting'
            . DIRECTORY_SEPARATOR . 'outputs'
            . DIRECTORY_SEPARATOR . 'deployment';
        mkdir($dir, 0777, true);
        return $dir;
    }

    private function currentManilaYear(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y');
    }

    public function test_default_mode_calls_best_sellers_once_and_least_sellers_once(): void
    {
        $dashboard = $this->createMock(Dashboard::class);
        $dashboard->expects($this->once())
            ->method('getBestSellingProducts')
            ->with(date('n'), date('Y'), 5)
            ->willReturn(['best-product']);
        $dashboard->expects($this->once())
            ->method('getLeastSellingProducts')
            ->with(date('n'), date('Y'), 5)
            ->willReturn(['least-product']);

        $controller = new DashboardController($dashboard);
        $data = $controller->getDashboardData('7days');

        $this->assertSame(['best-product'], $data['best_sellers']);
        $this->assertSame(['least-product'], $data['least_sellers']);
    }

    public function test_least_mode_uses_least_selling_products_for_best_sellers_key_too(): void
    {
        $_GET['mode'] = 'least';

        $dashboard = $this->createMock(Dashboard::class);
        $dashboard->expects($this->never())->method('getBestSellingProducts');
        $dashboard->expects($this->exactly(2))
            ->method('getLeastSellingProducts')
            ->with(date('n'), date('Y'), 5)
            ->willReturn(['least-product']);

        $controller = new DashboardController($dashboard);
        $data = $controller->getDashboardData('7days');

        $this->assertSame(['least-product'], $data['best_sellers']);
        $this->assertSame(['least-product'], $data['least_sellers']);
    }

    public function test_best_seller_get_params_override_month_year_and_limit(): void
    {
        $_GET['best_month'] = 3;
        $_GET['best_year'] = 2022;
        $_GET['best_limit'] = 10;

        $dashboard = $this->createMock(Dashboard::class);
        $dashboard->expects($this->once())
            ->method('getBestSellingProducts')
            ->with(3, 2022, 10)
            ->willReturn([]);

        $controller = new DashboardController($dashboard);
        $controller->getDashboardData('7days');
    }

    public function test_period_argument_flows_into_kpi_card_calls(): void
    {
        $dashboard = $this->createMock(Dashboard::class);
        $dashboard->expects($this->once())->method('getTotalSales')->with('30days')->willReturn(1000);
        $dashboard->expects($this->once())->method('getItemsSold')->with('30days')->willReturn(50);
        $dashboard->expects($this->once())->method('getTransactions')->with('30days')->willReturn(20);
        $dashboard->expects($this->once())->method('getSalesGrowth')->with('30days')->willReturn(5.0);
        $dashboard->expects($this->once())->method('getSalesGrowthDetails')->with('30days')->willReturn([]);

        $controller = new DashboardController($dashboard);
        $data = $controller->getDashboardData('30days');

        $this->assertSame(1000, $data['total_sales']);
        $this->assertSame(50, $data['items_sold']);
        $this->assertSame(20, $data['transactions']);
    }

    public function test_sales_year_get_param_overrides_monthly_sales_year(): void
    {
        $_GET['sales_year'] = 2021;

        $dashboard = $this->createMock(Dashboard::class);
        $dashboard->expects($this->once())->method('getMonthlySales')->with(2021)->willReturn([]);

        $controller = new DashboardController($dashboard);
        $controller->getDashboardData();
    }

    public function test_demand_month_and_year_get_params_flow_into_monthly_demand_calls(): void
    {
        $_GET['demand_month'] = 6;
        $_GET['demand_year'] = 2020;

        $dashboard = $this->createMock(Dashboard::class);
        // Called twice in getDashboardData(): once for 'monthly_demand', once
        // for 'demand_quantity' — both are the same call with the same args.
        $dashboard->expects($this->exactly(2))
            ->method('getMonthlyDemand')
            ->with(6, 2020)
            ->willReturn(['day' => 1, 'total_quantity' => 3]);

        $controller = new DashboardController($dashboard);
        $data = $controller->getDashboardData();

        $this->assertSame($data['monthly_demand'], $data['demand_quantity']);
    }

    public function test_fast_moving_get_params_override_month_year_and_limit(): void
    {
        $_GET['fast_month'] = 9;
        $_GET['fast_year'] = 2019;
        $_GET['fast_limit'] = 8;

        $dashboard = $this->createMock(Dashboard::class);
        $dashboard->expects($this->once())
            ->method('getFastMovingProducts')
            ->with(9, 2019, 8)
            ->willReturn([]);

        $controller = new DashboardController($dashboard);
        $controller->getDashboardData();
    }

    public function test_export_sales_history_delegates_to_dashboard_model(): void
    {
        $rows = [['sale_date' => '2026-01-01']];
        $dashboard = $this->createMock(Dashboard::class);
        $dashboard->expects($this->once())->method('exportSalesHistory')->willReturn($rows);

        $controller = new DashboardController($dashboard);

        $this->assertSame($rows, $controller->exportSalesHistory());
    }

    public function test_get_demand_analytics_delegates_to_get_demand_quantity(): void
    {
        $rows = [['product_name' => 'X', 'total_quantity' => 5]];
        $dashboard = $this->createMock(Dashboard::class);
        $dashboard->expects($this->once())
            ->method('getDemandQuantity')
            ->with(2026, 3)
            ->willReturn($rows);

        $controller = new DashboardController($dashboard);

        $this->assertSame($rows, $controller->getDemandAnalytics(2026, 3));
    }

    // -----------------------------------------------------------------
    // getAnnualForecastArtifact() (private, exercised via 'annual_forecast')
    // -----------------------------------------------------------------

    public function test_annual_forecast_is_empty_array_when_file_missing(): void
    {
        $deploymentDir = $this->deploymentDir(); // creates temp dir, no file written

        $dashboard = $this->createMock(Dashboard::class);
        $controller = new DashboardController($dashboard, $this->tempRoot);

        $data = $controller->getDashboardData();

        $this->assertSame([], $data['annual_forecast']);
    }

    public function test_annual_forecast_returns_decoded_json_when_file_present(): void
    {
        $deploymentDir = $this->deploymentDir();
        $year = $this->currentManilaYear();
        file_put_contents(
            $deploymentDir . DIRECTORY_SEPARATOR . "annual_forecast_{$year}.json",
            json_encode(['year' => (int) $year, 'total_forecast' => 12345])
        );

        $dashboard = $this->createMock(Dashboard::class);
        $controller = new DashboardController($dashboard, $this->tempRoot);

        $data = $controller->getDashboardData();

        $this->assertSame(['year' => (int) $year, 'total_forecast' => 12345], $data['annual_forecast']);
    }

    public function test_annual_forecast_is_empty_array_when_file_is_empty(): void
    {
        $deploymentDir = $this->deploymentDir();
        $year = $this->currentManilaYear();
        file_put_contents($deploymentDir . DIRECTORY_SEPARATOR . "annual_forecast_{$year}.json", '');

        $dashboard = $this->createMock(Dashboard::class);
        $controller = new DashboardController($dashboard, $this->tempRoot);

        $data = $controller->getDashboardData();

        $this->assertSame([], $data['annual_forecast']);
    }

    public function test_annual_forecast_is_empty_array_when_json_is_invalid(): void
    {
        $deploymentDir = $this->deploymentDir();
        $year = $this->currentManilaYear();
        file_put_contents($deploymentDir . DIRECTORY_SEPARATOR . "annual_forecast_{$year}.json", '{not valid json');

        $dashboard = $this->createMock(Dashboard::class);
        $controller = new DashboardController($dashboard, $this->tempRoot);

        $data = $controller->getDashboardData();

        $this->assertSame([], $data['annual_forecast']);
    }

    public function test_annual_forecast_is_empty_array_when_decoded_json_is_not_an_array(): void
    {
        $deploymentDir = $this->deploymentDir();
        $year = $this->currentManilaYear();
        // A bare JSON scalar decodes successfully but is not an array.
        file_put_contents($deploymentDir . DIRECTORY_SEPARATOR . "annual_forecast_{$year}.json", '"just a string"');

        $dashboard = $this->createMock(Dashboard::class);
        $controller = new DashboardController($dashboard, $this->tempRoot);

        $data = $controller->getDashboardData();

        $this->assertSame([], $data['annual_forecast']);
    }
}
