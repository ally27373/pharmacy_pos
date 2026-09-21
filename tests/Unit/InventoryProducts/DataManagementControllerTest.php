<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../app/Controllers/DataManagementController.php';
require_once __DIR__ . '/../../Support/MocksPdo.php';

use PHPUnit\Framework\TestCase;

final class DataManagementControllerTest extends TestCase
{
    use MocksPdo;

    private function makeDataManagementMock(): DataManagement&\PHPUnit\Framework\MockObject\MockObject
    {
        return $this->getMockBuilder(DataManagement::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testExportInventoryDelegatesToModel(): void
    {
        $expected = [['product_id' => 1]];

        $model = $this->makeDataManagementMock();
        $model->expects(self::once())->method('getInventoryForExport')->willReturn($expected);

        $controller = new DataManagementController($model);
        self::assertSame($expected, $controller->exportInventory());
    }

    public function testExportSalesDelegatesToModel(): void
    {
        $expected = [['sale_id' => 1]];

        $model = $this->makeDataManagementMock();
        $model->expects(self::once())->method('getSalesForExport')->willReturn($expected);

        $controller = new DataManagementController($model);
        self::assertSame($expected, $controller->exportSales());
    }
}
