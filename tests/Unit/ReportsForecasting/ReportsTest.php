<?php

declare(strict_types=1);

namespace Tests\Unit\ReportsForecasting;

require_once __DIR__ . '/../../Support/MocksPdo.php';
require_once __DIR__ . '/../../../app/Models/Reports.php';

use PDO;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Reports;
use stdClass;

/**
 * Reports::getReportData()/getExportRows() build a UNION-ALL (sales +
 * inventory) SQL subquery, filter it (date range, source, category, type,
 * search), and paginate it. The filtering/pagination math lives in private
 * normalizeFilters()/buildWhere(), so it's exercised indirectly: through
 * the 'filters' key echoed back in the result, and by capturing the SQL
 * text handed to PDO::prepare().
 */
final class ReportsTest extends TestCase
{
    use \MocksPdo;

    /**
     * A PDO mock whose prepare() calls are recorded (in order) into
     * $capture->queries, and whose single returned statement answers a
     * fixed fetchColumn() (COUNT query) / fetchAll() (row query) result —
     * matching Reports::getReportData()'s two prepare() calls against the
     * same statement shape.
     */
    private function pdoCapturingSql(int $total, array $rows, stdClass $capture): PDO&MockObject
    {
        $statement = $this->createStatementMock();
        $statement->method('bindValue')->willReturn(true);
        $statement->method('execute')->willReturn(true);
        $statement->method('fetchColumn')->willReturn($total);
        $statement->method('fetchAll')->willReturn($rows);

        $capture->queries = [];
        $pdo = $this->createPdoMock();
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use ($capture, $statement) {
            $capture->queries[] = $sql;
            return $statement;
        });

        return $pdo;
    }

    public function test_default_filters_use_weekly_period_and_all_source(): void
    {
        $capture = new stdClass();
        $pdo = $this->pdoCapturingSql(0, [], $capture);
        $reports = new Reports($pdo);

        $result = $reports->getReportData([]);

        $this->assertSame('weekly', $result['filters']['period']);
        $this->assertSame('All', $result['filters']['source']);
        $this->assertSame(date('Y-m-d', strtotime('-6 days')), $result['filters']['from']);
        $this->assertSame(date('Y-m-d'), $result['filters']['to']);
        $this->assertSame(25, $result['filters']['limit']);
        $this->assertSame(1, $result['filters']['page']);
        $this->assertStringNotContainsString('r.source =', $capture->queries[0]);
    }

    public function test_monthly_period_computes_from_first_of_month(): void
    {
        $capture = new stdClass();
        $pdo = $this->pdoCapturingSql(0, [], $capture);
        $reports = new Reports($pdo);

        $result = $reports->getReportData(['period' => 'monthly']);

        $this->assertSame(date('Y-m-01'), $result['filters']['from']);
        $this->assertSame(date('Y-m-d'), $result['filters']['to']);
    }

    public function test_custom_period_swaps_from_and_to_when_reversed(): void
    {
        $capture = new stdClass();
        $pdo = $this->pdoCapturingSql(0, [], $capture);
        $reports = new Reports($pdo);

        $result = $reports->getReportData([
            'period' => 'custom',
            'from' => '2026-01-10',
            'to' => '2026-01-01',
        ]);

        $this->assertSame('2026-01-01', $result['filters']['from']);
        $this->assertSame('2026-01-10', $result['filters']['to']);
    }

    public function test_custom_period_keeps_valid_non_reversed_range(): void
    {
        $capture = new stdClass();
        $pdo = $this->pdoCapturingSql(0, [], $capture);
        $reports = new Reports($pdo);

        $result = $reports->getReportData([
            'period' => 'custom',
            'from' => '2026-01-01',
            'to' => '2026-01-10',
        ]);

        $this->assertSame('2026-01-01', $result['filters']['from']);
        $this->assertSame('2026-01-10', $result['filters']['to']);
    }

    public function test_custom_period_falls_back_to_weekly_defaults_on_malformed_dates(): void
    {
        $capture = new stdClass();
        $pdo = $this->pdoCapturingSql(0, [], $capture);
        $reports = new Reports($pdo);

        $result = $reports->getReportData([
            'period' => 'custom',
            'from' => 'not-a-date',
            'to' => 'also-not-a-date',
        ]);

        $this->assertSame(date('Y-m-d', strtotime('-6 days')), $result['filters']['from']);
        $this->assertSame(date('Y-m-d'), $result['filters']['to']);
    }

    public function test_unrecognized_period_falls_back_to_weekly(): void
    {
        $capture = new stdClass();
        $pdo = $this->pdoCapturingSql(0, [], $capture);
        $reports = new Reports($pdo);

        $result = $reports->getReportData(['period' => 'yearly']);

        $this->assertSame('weekly', $result['filters']['period']);
    }

    public function test_limit_is_clamped_to_minimum_of_one(): void
    {
        $capture = new stdClass();
        $pdo = $this->pdoCapturingSql(0, [], $capture);
        $reports = new Reports($pdo);

        $result = $reports->getReportData(['limit' => 0]);

        $this->assertSame(1, $result['filters']['limit']);
    }

    public function test_limit_is_clamped_to_maximum_of_hundred(): void
    {
        $capture = new stdClass();
        $pdo = $this->pdoCapturingSql(0, [], $capture);
        $reports = new Reports($pdo);

        $result = $reports->getReportData(['limit' => 500]);

        $this->assertSame(100, $result['filters']['limit']);
    }

    public function test_page_is_clamped_to_minimum_of_one(): void
    {
        $capture = new stdClass();
        $pdo = $this->pdoCapturingSql(0, [], $capture);
        $reports = new Reports($pdo);

        $result = $reports->getReportData(['page' => -5]);

        $this->assertSame(1, $result['filters']['page']);
    }

    public function test_page_beyond_total_pages_is_clamped_down(): void
    {
        $capture = new stdClass();
        // total=10, limit=5 -> total_pages=2; requesting page 99 must clamp to 2.
        $pdo = $this->pdoCapturingSql(10, [], $capture);
        $reports = new Reports($pdo);

        $result = $reports->getReportData(['page' => 99, 'limit' => 5]);

        $this->assertSame(2, $result['pagination']['total_pages']);
        $this->assertSame(2, $result['pagination']['page']);
        $this->assertSame(2, $result['filters']['page']);
        $this->assertTrue($result['pagination']['has_previous']);
        $this->assertFalse($result['pagination']['has_next']);
    }

    public function test_pagination_flags_for_a_middle_page(): void
    {
        $capture = new stdClass();
        // total=30, limit=10 -> 3 pages; page 2 has both previous and next.
        $pdo = $this->pdoCapturingSql(30, [], $capture);
        $reports = new Reports($pdo);

        $result = $reports->getReportData(['page' => 2, 'limit' => 10]);

        $this->assertSame(3, $result['pagination']['total_pages']);
        $this->assertTrue($result['pagination']['has_previous']);
        $this->assertTrue($result['pagination']['has_next']);
    }

    public function test_zero_total_produces_zero_total_pages_and_no_next(): void
    {
        $capture = new stdClass();
        $pdo = $this->pdoCapturingSql(0, [], $capture);
        $reports = new Reports($pdo);

        $result = $reports->getReportData(['page' => 1]);

        $this->assertSame(0, $result['pagination']['total_pages']);
        $this->assertFalse($result['pagination']['has_next']);
        $this->assertFalse($result['pagination']['has_previous']);
    }

    public function test_source_filter_adds_where_clause_when_not_all(): void
    {
        $capture = new stdClass();
        $pdo = $this->pdoCapturingSql(0, [], $capture);
        $reports = new Reports($pdo);

        $reports->getReportData(['source' => 'Sales']);

        $this->assertStringContainsString('r.source = :source', $capture->queries[0]);
    }

    public function test_invalid_source_value_normalizes_to_all_and_is_not_filtered(): void
    {
        $capture = new stdClass();
        $pdo = $this->pdoCapturingSql(0, [], $capture);
        $reports = new Reports($pdo);

        $result = $reports->getReportData(['source' => 'Bogus']);

        $this->assertSame('All', $result['filters']['source']);
        $this->assertStringNotContainsString('r.source =', $capture->queries[0]);
    }

    public function test_category_and_type_filters_add_where_clauses(): void
    {
        $capture = new stdClass();
        $pdo = $this->pdoCapturingSql(0, [], $capture);
        $reports = new Reports($pdo);

        $result = $reports->getReportData(['category' => '  Antibiotics  ', 'type' => ' Tablet ']);

        $this->assertSame('Antibiotics', $result['filters']['category']);
        $this->assertSame('Tablet', $result['filters']['type']);
        $this->assertStringContainsString('r.category_name = :category', $capture->queries[0]);
        $this->assertStringContainsString('r.type_name = :type', $capture->queries[0]);
    }

    public function test_search_filter_covers_product_barcode_transaction_invoice_and_payment(): void
    {
        $capture = new stdClass();
        $pdo = $this->pdoCapturingSql(0, [], $capture);
        $reports = new Reports($pdo);

        $reports->getReportData(['search' => 'amox']);

        $sql = $capture->queries[0];
        $this->assertStringContainsString('r.product_name LIKE :search_product', $sql);
        $this->assertStringContainsString('r.barcode LIKE :search_barcode', $sql);
        $this->assertStringContainsString(":search_transaction", $sql);
        $this->assertStringContainsString(":search_invoice", $sql);
        $this->assertStringContainsString(":search_payment", $sql);
    }

    public function test_no_search_clause_when_search_is_blank(): void
    {
        $capture = new stdClass();
        $pdo = $this->pdoCapturingSql(0, [], $capture);
        $reports = new Reports($pdo);

        $reports->getReportData([]);

        $this->assertStringNotContainsString('search_product', $capture->queries[0]);
    }

    public function test_get_report_data_returns_rows_from_statement(): void
    {
        $rows = [
            ['product_name' => 'Paracetamol', 'source' => 'Sales'],
            ['product_name' => 'Ibuprofen', 'source' => 'Inventory'],
        ];
        $capture = new stdClass();
        $pdo = $this->pdoCapturingSql(2, $rows, $capture);
        $reports = new Reports($pdo);

        $result = $reports->getReportData([]);

        $this->assertSame($rows, $result['rows']);
        $this->assertSame(2, $result['pagination']['total']);
    }

    public function test_get_report_data_query_paginates_with_limit_and_offset(): void
    {
        $capture = new stdClass();
        $pdo = $this->pdoCapturingSql(0, [], $capture);
        $reports = new Reports($pdo);

        $reports->getReportData([]);

        // capture->queries[1] is the row-fetching query (queries[0] is COUNT).
        $this->assertStringContainsString('LIMIT :limit OFFSET :offset', $capture->queries[1]);
    }

    public function test_get_export_rows_returns_plain_array_without_pagination(): void
    {
        $rows = [['product_name' => 'Paracetamol']];
        $capture = new stdClass();
        $statement = $this->createStatementMock();
        $statement->method('bindValue')->willReturn(true);
        $statement->method('execute')->willReturn(true);
        $statement->method('fetchAll')->willReturn($rows);

        $pdo = $this->createPdoMock();
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use ($capture, $statement) {
            $capture->queries[] = $sql;
            return $statement;
        });
        $capture->queries = [];

        $reports = new Reports($pdo);

        $result = $reports->getExportRows([]);

        $this->assertSame($rows, $result);
        $this->assertStringNotContainsString('LIMIT', $capture->queries[0]);
    }

    public function test_get_categories_returns_distinct_column_list(): void
    {
        $statement = $this->createStatementMock();
        $statement->method('fetchAll')->willReturn(['Antibiotics', 'Analgesics']);

        $pdo = $this->createPdoMock();
        $pdo->expects($this->once())->method('query')->willReturn($statement);

        $reports = new Reports($pdo);

        $this->assertSame(['Antibiotics', 'Analgesics'], $reports->getCategories());
    }

    public function test_get_types_returns_distinct_column_list(): void
    {
        $statement = $this->createStatementMock();
        $statement->method('fetchAll')->willReturn(['Tablet', 'Syrup']);

        $pdo = $this->createPdoMock();
        $pdo->expects($this->once())->method('query')->willReturn($statement);

        $reports = new Reports($pdo);

        $this->assertSame(['Tablet', 'Syrup'], $reports->getTypes());
    }
}
