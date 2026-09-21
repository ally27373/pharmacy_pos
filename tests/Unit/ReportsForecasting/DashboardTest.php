<?php

declare(strict_types=1);

namespace Tests\Unit\ReportsForecasting;

require_once __DIR__ . '/../../Support/MocksPdo.php';
require_once __DIR__ . '/../../../app/Models/Dashboard.php';

use App\Services\ForecastService;
use Dashboard;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Dashboard aggregates a large number of KPI/analytics queries. These
 * tests focus on: the date-range condition builder (getDateCondition, via
 * getTotalSales), the sales-growth percentage math (getSalesGrowthDetails
 * — the most complex aggregation in this class), the analytics-years
 * windowing logic, conditional WHERE assembly (getDemandQuantity), the
 * "empty result" fallbacks (getBestSeller & friends), and the two
 * SARIMA-forecast integration points (which are stubbed per the task
 * brief, never touching Python or the real deployed forecast files).
 */
final class DashboardTest extends TestCase
{
    use \MocksPdo;

    private ?string $tempFile = null;

    protected function tearDown(): void
    {
        if ($this->tempFile !== null && is_file($this->tempFile)) {
            unlink($this->tempFile);
        }
        $this->tempFile = null;
    }

    /**
     * A PDO mock whose query() call is captured (SQL text, in call order)
     * and whose statement answers a single canned fetch()/fetchAll()/
     * fetchColumn() result — matching Dashboard's widespread
     * `$this->conn->query($sql)->fetchX()` pattern.
     */
    private function pdoCapturingQuery(string $fetchMethod, $fetchReturn, stdClass $capture): PDO&MockObject
    {
        $statement = $this->createStatementMock();
        $statement->method($fetchMethod)->willReturn($fetchReturn);

        $capture->queries = [];
        $pdo = $this->createPdoMock();
        $pdo->method('query')->willReturnCallback(function (string $sql) use ($capture, $statement) {
            $capture->queries[] = $sql;
            return $statement;
        });

        return $pdo;
    }

    // -----------------------------------------------------------------
    // Simple scalar aggregate getters (COUNT/SUM ... ['key'])
    // -----------------------------------------------------------------

    public static function scalarAggregateMethodsProvider(): array
    {
        return [
            'getTotalProducts' => ['getTotalProducts', 'total_products', 120],
            'getLowStockProducts' => ['getLowStockProducts', 'low_stock', 7],
            'getTotalUsers' => ['getTotalUsers', 'total_users', 3],
            'getTotalCategories' => ['getTotalCategories', 'total_categories', 12],
            'getOutOfStockProducts' => ['getOutOfStockProducts', 'out_of_stock', 4],
        ];
    }

    #[DataProvider('scalarAggregateMethodsProvider')]
    public function test_scalar_aggregate_getters_return_value_from_query(string $method, string $key, $value): void
    {
        $statement = $this->createStatementMock();
        $statement->method('fetch')->willReturn([$key => $value]);
        $pdo = $this->createPdoMock();
        $pdo->method('query')->willReturn($statement);

        $dashboard = new Dashboard($pdo);

        $this->assertSame($value, $dashboard->$method());
    }

    // -----------------------------------------------------------------
    // getDateCondition() via getTotalSales($period)
    // -----------------------------------------------------------------

    public static function periodConditionProvider(): array
    {
        return [
            'today' => ['today', 'DATE(created_at)=CURDATE()'],
            '7days' => ['7days', 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'],
            '30days' => ['30days', 'created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'],
            '3months' => ['3months', 'created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)'],
            '1year' => ['1year', 'created_at >= MAKEDATE(YEAR(CURDATE()), 1)'],
            'all' => ['all', '1=1'],
            'unrecognized period falls back to 7 days' => ['bogus', 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'],
        ];
    }

    #[DataProvider('periodConditionProvider')]
    public function test_get_total_sales_builds_expected_date_condition(string $period, string $expectedFragment): void
    {
        $capture = new stdClass();
        $pdo = $this->pdoCapturingQuery('fetch', ['total_sales' => 500], $capture);
        $dashboard = new Dashboard($pdo);

        $result = $dashboard->getTotalSales($period);

        $this->assertSame(500, $result);
        $this->assertStringContainsString($expectedFragment, $capture->queries[0]);
    }

    // -----------------------------------------------------------------
    // getSalesGrowthDetails() — the core aggregation/summary math
    // -----------------------------------------------------------------

    public function test_sales_growth_percent_computed_and_rounded(): void
    {
        $capture = new stdClass();
        $pdo = $this->pdoCapturingQuery(
            'fetch',
            ['current_sales' => '133.333', 'previous_sales' => '100'],
            $capture
        );
        $dashboard = new Dashboard($pdo);

        $details = $dashboard->getSalesGrowthDetails('7days');

        $this->assertSame(33.33, $details['growth_percent']);
        $this->assertTrue($details['has_previous_period']);
        $this->assertTrue($details['has_current_period']);
        $this->assertSame('vs previous 7 days', $details['comparison_label']);
    }

    public function test_sales_growth_negative_when_current_below_previous(): void
    {
        $capture = new stdClass();
        $pdo = $this->pdoCapturingQuery(
            'fetch',
            ['current_sales' => '50', 'previous_sales' => '200'],
            $capture
        );
        $dashboard = new Dashboard($pdo);

        $details = $dashboard->getSalesGrowthDetails('30days');

        $this->assertSame(-75.0, $details['growth_percent']);
    }

    public function test_sales_growth_null_when_no_previous_period_baseline(): void
    {
        $capture = new stdClass();
        $pdo = $this->pdoCapturingQuery(
            'fetch',
            ['current_sales' => '100', 'previous_sales' => '0'],
            $capture
        );
        $dashboard = new Dashboard($pdo);

        $details = $dashboard->getSalesGrowthDetails('7days');

        $this->assertNull($details['growth_percent']);
        $this->assertFalse($details['has_previous_period']);
        $this->assertTrue($details['has_current_period']);
    }

    public function test_sales_growth_has_current_period_false_when_both_zero(): void
    {
        $capture = new stdClass();
        $pdo = $this->pdoCapturingQuery(
            'fetch',
            ['current_sales' => '0', 'previous_sales' => '0'],
            $capture
        );
        $dashboard = new Dashboard($pdo);

        $details = $dashboard->getSalesGrowthDetails('7days');

        $this->assertNull($details['growth_percent']);
        $this->assertFalse($details['has_current_period']);
    }

    public function test_sales_growth_today_label_and_condition(): void
    {
        $capture = new stdClass();
        $pdo = $this->pdoCapturingQuery('fetch', ['current_sales' => '10', 'previous_sales' => '5'], $capture);
        $dashboard = new Dashboard($pdo);

        $details = $dashboard->getSalesGrowthDetails('today');

        $this->assertSame('vs yesterday', $details['comparison_label']);
    }

    public function test_sales_growth_all_period_overrides_current_condition_to_current_year(): void
    {
        $capture = new stdClass();
        $pdo = $this->pdoCapturingQuery('fetch', ['current_sales' => '10', 'previous_sales' => '5'], $capture);
        $dashboard = new Dashboard($pdo);

        $details = $dashboard->getSalesGrowthDetails('all');

        $this->assertSame('vs previous year', $details['comparison_label']);
        // 'all' redefines $currentCondition (not the bare "1=1" that
        // getDateCondition('all', ...) would produce) to scope the
        // "current" bucket to the current calendar year.
        $this->assertStringContainsString('MAKEDATE(YEAR(CURDATE()), 1)', $capture->queries[0]);
        $this->assertStringNotContainsString('WHEN 1=1', $capture->queries[0]);
    }

    public function test_sales_growth_unrecognized_period_falls_back_to_seven_day_comparison(): void
    {
        $capture = new stdClass();
        $pdo = $this->pdoCapturingQuery('fetch', ['current_sales' => '10', 'previous_sales' => '5'], $capture);
        $dashboard = new Dashboard($pdo);

        $details = $dashboard->getSalesGrowthDetails('bogus-period');

        $this->assertSame('vs previous 7 days', $details['comparison_label']);
    }

    public function test_get_sales_growth_returns_just_the_percent(): void
    {
        $capture = new stdClass();
        $pdo = $this->pdoCapturingQuery('fetch', ['current_sales' => '150', 'previous_sales' => '100'], $capture);
        $dashboard = new Dashboard($pdo);

        $this->assertSame(50.0, $dashboard->getSalesGrowth('7days'));
    }

    // -----------------------------------------------------------------
    // getAvailableSalesYears() / getAnalyticsYears()
    // -----------------------------------------------------------------

    public function test_get_available_sales_years_casts_to_int(): void
    {
        $statement = $this->createStatementMock();
        $statement->method('fetchAll')->willReturn(['2026', '2025', '2024']);
        $pdo = $this->createPdoMock();
        $pdo->method('query')->willReturn($statement);

        $dashboard = new Dashboard($pdo);

        $this->assertSame([2026, 2025, 2024], $dashboard->getAvailableSalesYears());
    }

    public function test_analytics_years_includes_current_year_even_if_absent_from_data(): void
    {
        $currentYear = (int) date('Y');
        $statement = $this->createStatementMock();
        $statement->method('fetchAll')->willReturn([(string) ($currentYear - 1), (string) ($currentYear - 4)]);
        $pdo = $this->createPdoMock();
        $pdo->method('query')->willReturn($statement);

        $dashboard = new Dashboard($pdo);

        // currentYear-4 is outside the [currentYear-2, currentYear] window
        // and must be dropped; currentYear must be injected even though
        // the query never returned it.
        $this->assertSame([$currentYear, $currentYear - 1], $dashboard->getAnalyticsYears());
    }

    public function test_analytics_years_filters_to_last_three_years_and_sorts_descending(): void
    {
        $currentYear = (int) date('Y');
        $statement = $this->createStatementMock();
        $statement->method('fetchAll')->willReturn([
            (string) $currentYear,
            (string) ($currentYear - 2),
            (string) ($currentYear - 5),
        ]);
        $pdo = $this->createPdoMock();
        $pdo->method('query')->willReturn($statement);

        $dashboard = new Dashboard($pdo);

        $this->assertSame([$currentYear, $currentYear - 2], $dashboard->getAnalyticsYears());
    }

    public function test_analytics_years_with_no_sales_data_returns_only_current_year(): void
    {
        $statement = $this->createStatementMock();
        $statement->method('fetchAll')->willReturn([]);
        $pdo = $this->createPdoMock();
        $pdo->method('query')->willReturn($statement);

        $dashboard = new Dashboard($pdo);

        $this->assertSame([(int) date('Y')], $dashboard->getAnalyticsYears());
    }

    // -----------------------------------------------------------------
    // getMonthlySales() — prepared statement + :year binding
    // -----------------------------------------------------------------

    public function test_get_monthly_sales_defaults_year_to_current_year(): void
    {
        $rows = [['month' => 1, 'total_sales' => '100']];
        $statement = $this->createStatementMock();
        $statement->expects($this->once())->method('execute')->with([':year' => date('Y')]);
        $statement->method('fetchAll')->willReturn($rows);

        $pdo = $this->createPdoMock();
        $pdo->method('prepare')->willReturn($statement);

        $dashboard = new Dashboard($pdo);

        $this->assertSame($rows, $dashboard->getMonthlySales());
    }

    public function test_get_monthly_sales_uses_explicit_year(): void
    {
        $statement = $this->createStatementMock();
        $statement->expects($this->once())->method('execute')->with([':year' => 2022]);
        $statement->method('fetchAll')->willReturn([]);

        $pdo = $this->createPdoMock();
        $pdo->method('prepare')->willReturn($statement);

        $dashboard = new Dashboard($pdo);

        $dashboard->getMonthlySales(2022);
    }

    // -----------------------------------------------------------------
    // getDemandQuantity() — conditional WHERE assembly
    // -----------------------------------------------------------------

    public function test_get_demand_quantity_with_no_filters_binds_no_params(): void
    {
        $capture = new stdClass();
        $statement = $this->createStatementMock();
        $statement->expects($this->once())->method('execute')->with($this->callback(function ($params) use ($capture) {
            $capture->params = $params;
            return true;
        }));
        $statement->method('fetchAll')->willReturn([]);

        $capture->sql = null;
        $pdo = $this->createPdoMock();
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use ($capture, $statement) {
            $capture->sql = $sql;
            return $statement;
        });

        $dashboard = new Dashboard($pdo);
        $dashboard->getDemandQuantity(null, null);

        $this->assertSame([], $capture->params);
        $this->assertStringNotContainsString(':year', $capture->sql);
        $this->assertStringNotContainsString(':month', $capture->sql);
    }

    public function test_get_demand_quantity_with_year_and_month_binds_both_params(): void
    {
        $capture = new stdClass();
        $statement = $this->createStatementMock();
        $statement->expects($this->once())->method('execute')->with($this->callback(function ($params) use ($capture) {
            $capture->params = $params;
            return true;
        }));
        $statement->method('fetchAll')->willReturn([]);

        $capture->sql = null;
        $pdo = $this->createPdoMock();
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use ($capture, $statement) {
            $capture->sql = $sql;
            return $statement;
        });

        $dashboard = new Dashboard($pdo);
        $dashboard->getDemandQuantity(2026, 3);

        $this->assertSame([':year' => 2026, ':month' => 3], $capture->params);
        $this->assertStringContainsString('YEAR(s.created_at) = :year', $capture->sql);
        $this->assertStringContainsString('MONTH(s.created_at) = :month', $capture->sql);
    }

    // -----------------------------------------------------------------
    // getBestSellingProducts() / getLeastSellingProducts()
    // -----------------------------------------------------------------

    public function test_best_selling_products_orders_descending_and_applies_integer_limit(): void
    {
        $capture = new stdClass();
        $statement = $this->createStatementMock();
        $statement->method('execute')->willReturn(true);
        $statement->method('fetchAll')->willReturn([]);

        $capture->sql = null;
        $pdo = $this->createPdoMock();
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use ($capture, $statement) {
            $capture->sql = $sql;
            return $statement;
        });

        $dashboard = new Dashboard($pdo);
        $dashboard->getBestSellingProducts(null, null, 3);

        $this->assertStringContainsString('total_quantity DESC', $capture->sql);
        $this->assertStringContainsString('LIMIT 3', $capture->sql);
    }

    public function test_least_selling_products_orders_ascending(): void
    {
        $capture = new stdClass();
        $statement = $this->createStatementMock();
        $statement->method('execute')->willReturn(true);
        $statement->method('fetchAll')->willReturn([]);

        $capture->sql = null;
        $pdo = $this->createPdoMock();
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use ($capture, $statement) {
            $capture->sql = $sql;
            return $statement;
        });

        $dashboard = new Dashboard($pdo);
        $dashboard->getLeastSellingProducts(5, 2026, 5);

        $this->assertStringContainsString('total_quantity ASC', $capture->sql);
    }

    public function test_best_selling_products_binds_month_and_year_when_provided(): void
    {
        $capture = new stdClass();
        $statement = $this->createStatementMock();
        $statement->expects($this->once())->method('execute')->with($this->callback(function ($params) use ($capture) {
            $capture->params = $params;
            return true;
        }));
        $statement->method('fetchAll')->willReturn([]);

        $pdo = $this->createPdoMock();
        $pdo->method('prepare')->willReturn($statement);

        $dashboard = new Dashboard($pdo);
        $dashboard->getBestSellingProducts(6, 2025, 5);

        $this->assertSame([':month' => 6, ':year' => 2025], $capture->params);
    }

    // -----------------------------------------------------------------
    // getFastMovingProducts() — explicit int-typed bindValue()s
    // -----------------------------------------------------------------

    public function test_fast_moving_products_binds_month_year_limit_as_integers(): void
    {
        $bound = [];
        $statement = $this->createStatementMock();
        $statement->method('bindValue')->willReturnCallback(function ($key, $value, $type) use (&$bound) {
            $bound[$key] = [$value, $type];
            return true;
        });
        $statement->method('execute')->willReturn(true);
        $statement->method('fetchAll')->willReturn([]);

        $pdo = $this->createPdoMock();
        $pdo->method('prepare')->willReturn($statement);

        $dashboard = new Dashboard($pdo);
        $dashboard->getFastMovingProducts('7', '2026', '5');

        $this->assertSame([7, PDO::PARAM_INT], $bound[':month']);
        $this->assertSame([2026, PDO::PARAM_INT], $bound[':year']);
        $this->assertSame([5, PDO::PARAM_INT], $bound[':limit']);
    }

    // -----------------------------------------------------------------
    // getBestSeller() / getHighestStock() / getLowestStock() / getNearestExpiry()
    // — empty-result fallbacks
    // -----------------------------------------------------------------

    public static function fallbackMethodsProvider(): array
    {
        return [
            'getBestSeller' => ['getBestSeller', 'No Sales Yet'],
            'getHighestStock' => ['getHighestStock', 'No Products'],
            'getLowestStock' => ['getLowestStock', 'None'],
            'getNearestExpiry' => ['getNearestExpiry', 'None'],
        ];
    }

    #[DataProvider('fallbackMethodsProvider')]
    public function test_returns_fallback_label_when_no_matching_row(string $method, string $expectedFallback): void
    {
        $statement = $this->createStatementMock();
        $statement->method('fetchColumn')->willReturn(false);
        $pdo = $this->createPdoMock();
        $pdo->method('query')->willReturn($statement);

        $dashboard = new Dashboard($pdo);

        $this->assertSame($expectedFallback, $dashboard->$method());
    }

    #[DataProvider('fallbackMethodsProvider')]
    public function test_returns_real_value_when_a_row_exists(string $method): void
    {
        $statement = $this->createStatementMock();
        $statement->method('fetchColumn')->willReturn('Paracetamol 500mg');
        $pdo = $this->createPdoMock();
        $pdo->method('query')->willReturn($statement);

        $dashboard = new Dashboard($pdo);

        $this->assertSame('Paracetamol 500mg', $dashboard->$method());
    }

    public function test_inventory_insights_aggregates_all_four_lookups_in_order(): void
    {
        $stmts = [];
        foreach (['Best Seller Co', 'Highest Stock Co', 'Low Stock Co', 'Expiring Co'] as $value) {
            $stmt = $this->createStatementMock();
            $stmt->method('fetchColumn')->willReturn($value);
            $stmts[] = $stmt;
        }

        $pdo = $this->createPdoMock();
        $pdo->method('query')->willReturnOnConsecutiveCalls(...$stmts);

        $dashboard = new Dashboard($pdo);

        $this->assertSame([
            'best_seller' => 'Best Seller Co',
            'highest_stock' => 'Highest Stock Co',
            'low_stock_product' => 'Low Stock Co',
            'expiring_product' => 'Expiring Co',
        ], $dashboard->getInventoryInsights());
    }

    // -----------------------------------------------------------------
    // getForecastData() — SARIMA integration, stubbed (no Python/disk I/O)
    // -----------------------------------------------------------------

    public function test_get_forecast_data_returns_data_when_forecast_service_reports_success(): void
    {
        $pdo = $this->createPdoMock();
        $forecastService = $this->createMock(ForecastService::class);
        $forecastService->method('getForecast')->willReturn([
            'success' => true,
            'data' => [['date' => '2026-01-01', 'forecast_quantity' => 10.0]],
        ]);

        $dashboard = new Dashboard($pdo, $forecastService);

        $this->assertSame(
            [['date' => '2026-01-01', 'forecast_quantity' => 10.0]],
            $dashboard->getForecastData()
        );
    }

    public function test_get_forecast_data_returns_empty_array_when_forecast_service_reports_failure(): void
    {
        $pdo = $this->createPdoMock();
        $forecastService = $this->createMock(ForecastService::class);
        $forecastService->method('getForecast')->willReturn([
            'success' => false,
            'data' => [],
        ]);

        $dashboard = new Dashboard($pdo, $forecastService);

        $this->assertSame([], $dashboard->getForecastData());
    }

    // -----------------------------------------------------------------
    // getProductDemandForecast() — file-based, insufficient/missing data
    // -----------------------------------------------------------------

    public function test_product_demand_forecast_returns_empty_shape_when_file_missing(): void
    {
        $pdo = $this->createPdoMock();
        $missingPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'does_not_exist_' . bin2hex(random_bytes(6)) . '.json';

        $dashboard = new Dashboard($pdo, null, $missingPath);

        $this->assertSame(['metadata' => [], 'products' => []], $dashboard->getProductDemandForecast());
    }

    public function test_product_demand_forecast_returns_metadata_and_products_from_file(): void
    {
        $pdo = $this->createPdoMock();
        $this->tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pdf_' . bin2hex(random_bytes(6)) . '.json';
        file_put_contents($this->tempFile, json_encode([
            'metadata' => ['generated_at' => '2026-01-01'],
            'products' => [['product_name' => 'Amoxicillin']],
        ]));

        $dashboard = new Dashboard($pdo, null, $this->tempFile);

        $this->assertSame([
            'metadata' => ['generated_at' => '2026-01-01'],
            'products' => [['product_name' => 'Amoxicillin']],
        ], $dashboard->getProductDemandForecast());
    }

    public function test_product_demand_forecast_defaults_non_array_metadata_and_products(): void
    {
        $pdo = $this->createPdoMock();
        $this->tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pdf_' . bin2hex(random_bytes(6)) . '.json';
        file_put_contents($this->tempFile, json_encode([
            'metadata' => 'not-an-array',
            'products' => 'not-an-array-either',
        ]));

        $dashboard = new Dashboard($pdo, null, $this->tempFile);

        $this->assertSame(['metadata' => [], 'products' => []], $dashboard->getProductDemandForecast());
    }

    public function test_product_demand_forecast_returns_empty_shape_for_empty_file(): void
    {
        $pdo = $this->createPdoMock();
        $this->tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pdf_' . bin2hex(random_bytes(6)) . '.json';
        file_put_contents($this->tempFile, '');

        $dashboard = new Dashboard($pdo, null, $this->tempFile);

        $this->assertSame(['metadata' => [], 'products' => []], $dashboard->getProductDemandForecast());
    }

    // -----------------------------------------------------------------
    // getMonthlyDemand() — defaults + pass-through
    // -----------------------------------------------------------------

    public function test_get_monthly_demand_defaults_to_current_month_and_year(): void
    {
        $statement = $this->createStatementMock();
        $statement->expects($this->once())->method('execute')->with([
            ':month' => date('n'),
            ':year' => date('Y'),
        ]);
        $statement->method('fetchAll')->willReturn([]);

        $pdo = $this->createPdoMock();
        $pdo->method('prepare')->willReturn($statement);

        $dashboard = new Dashboard($pdo);
        $dashboard->getMonthlyDemand();
    }

    public function test_get_monthly_demand_uses_explicit_month_and_year(): void
    {
        $rows = [['day' => 5, 'total_quantity' => 20]];
        $statement = $this->createStatementMock();
        $statement->expects($this->once())->method('execute')->with([':month' => 3, ':year' => 2024]);
        $statement->method('fetchAll')->willReturn($rows);

        $pdo = $this->createPdoMock();
        $pdo->method('prepare')->willReturn($statement);

        $dashboard = new Dashboard($pdo);

        $this->assertSame($rows, $dashboard->getMonthlyDemand(3, 2024));
    }

    // -----------------------------------------------------------------
    // Simple list pass-throughs
    // -----------------------------------------------------------------

    public function test_get_historical_demand_returns_rows_as_is(): void
    {
        $rows = [['date' => '2026-01-01', 'quantity_sold' => 5]];
        $statement = $this->createStatementMock();
        $statement->method('fetchAll')->willReturn($rows);
        $pdo = $this->createPdoMock();
        $pdo->method('query')->willReturn($statement);

        $dashboard = new Dashboard($pdo);

        $this->assertSame($rows, $dashboard->getHistoricalDemand());
    }

    public function test_export_sales_history_returns_rows_as_is(): void
    {
        $rows = [['sale_date' => '2026-01-01', 'product_name' => 'X', 'quantity_sold' => 2]];
        $statement = $this->createStatementMock();
        $statement->method('fetchAll')->willReturn($rows);
        $pdo = $this->createPdoMock();
        $pdo->method('query')->willReturn($statement);

        $dashboard = new Dashboard($pdo);

        $this->assertSame($rows, $dashboard->exportSalesHistory());
    }
}
