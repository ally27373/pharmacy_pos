<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../app/Controllers/InventoryHistoryController.php';
require_once __DIR__ . '/../../Support/MocksPdo.php';

use PHPUnit\Framework\TestCase;

final class InventoryHistoryControllerTest extends TestCase
{
    use MocksPdo;

    private function makeHistoryModelMock(): InventoryHistory&\PHPUnit\Framework\MockObject\MockObject
    {
        return $this->getMockBuilder(InventoryHistory::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testGetHistoryDelegatesArgumentsAndReturnsResult(): void
    {
        $expected = ['history' => [], 'pagination' => []];

        $model = $this->makeHistoryModelMock();
        $model->expects(self::once())
            ->method('getHistory')
            ->with(2, 10, 'amoxi', 'IN', '2026-01-01', '2026-01-31')
            ->willReturn($expected);

        $controller = new InventoryHistoryController($model);
        $result = $controller->getHistory(2, 10, 'amoxi', 'IN', '2026-01-01', '2026-01-31');

        self::assertSame($expected, $result);
    }

    public function testGetHistoryDelegatesWithDefaultArguments(): void
    {
        $expected = ['history' => [['history_id' => 1]], 'pagination' => ['page' => 1]];

        $model = $this->makeHistoryModelMock();
        $model->expects(self::once())
            ->method('getHistory')
            ->with(1, 20, '', '', '', '')
            ->willReturn($expected);

        $controller = new InventoryHistoryController($model);
        self::assertSame($expected, $controller->getHistory());
    }
}
