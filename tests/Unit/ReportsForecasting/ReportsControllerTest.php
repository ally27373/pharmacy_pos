<?php

declare(strict_types=1);

namespace Tests\Unit\ReportsForecasting;

require_once __DIR__ . '/../../../app/Controllers/ReportsController.php';

use PHPUnit\Framework\TestCase;
use Reports;
use ReportsController;

/**
 * ReportsController is a thin pass-through to the Reports model. The DI
 * seam (optional ?Reports $reports constructor param) lets us inject a
 * mock Reports instead of hitting a real database.
 */
final class ReportsControllerTest extends TestCase
{
    public function test_get_report_data_delegates_to_reports_model_with_filters(): void
    {
        $filters = ['source' => 'Sales', 'page' => 2];
        $expected = ['rows' => [], 'pagination' => [], 'filters' => $filters];

        $reports = $this->createMock(Reports::class);
        $reports->expects($this->once())
            ->method('getReportData')
            ->with($filters)
            ->willReturn($expected);

        $controller = new ReportsController($reports);

        $this->assertSame($expected, $controller->getReportData($filters));
    }

    public function test_get_report_data_defaults_to_empty_filters(): void
    {
        $reports = $this->createMock(Reports::class);
        $reports->expects($this->once())
            ->method('getReportData')
            ->with([])
            ->willReturn(['rows' => [], 'pagination' => [], 'filters' => []]);

        $controller = new ReportsController($reports);

        $controller->getReportData();
    }

    public function test_get_export_rows_delegates_to_reports_model(): void
    {
        $filters = ['category' => 'Antibiotics'];
        $expected = [['product_name' => 'Amoxicillin']];

        $reports = $this->createMock(Reports::class);
        $reports->expects($this->once())
            ->method('getExportRows')
            ->with($filters)
            ->willReturn($expected);

        $controller = new ReportsController($reports);

        $this->assertSame($expected, $controller->getExportRows($filters));
    }

    public function test_get_categories_delegates_to_reports_model(): void
    {
        $reports = $this->createMock(Reports::class);
        $reports->expects($this->once())
            ->method('getCategories')
            ->willReturn(['Antibiotics', 'Analgesics']);

        $controller = new ReportsController($reports);

        $this->assertSame(['Antibiotics', 'Analgesics'], $controller->getCategories());
    }

    public function test_get_types_delegates_to_reports_model(): void
    {
        $reports = $this->createMock(Reports::class);
        $reports->expects($this->once())
            ->method('getTypes')
            ->willReturn(['Tablet', 'Syrup']);

        $controller = new ReportsController($reports);

        $this->assertSame(['Tablet', 'Syrup'], $controller->getTypes());
    }
}
