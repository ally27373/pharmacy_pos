<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Support/MocksPdo.php';
require_once __DIR__ . '/../../../app/Controllers/SalesController.php';

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for app/Controllers/SalesController.php.
 *
 * Sales itself is mocked out via the constructor-injection seam.
 */
final class SalesControllerTest extends TestCase
{
    use MocksPdo;

    public function testGetTransactionsUsesDefaultArgsAndReturnsModelResult(): void
    {
        $sales = $this->createMock(Sales::class);
        $sales->expects($this->once())
            ->method('getAllSales')
            ->with(1, 20, '')
            ->willReturn(['transactions' => [], 'pagination' => []]);

        $controller = new SalesController($sales);
        $result = $controller->getTransactions();

        $this->assertSame(['transactions' => [], 'pagination' => []], $result);
    }

    public function testGetTransactionsPassesThroughExplicitArgs(): void
    {
        $sales = $this->createMock(Sales::class);
        $sales->expects($this->once())
            ->method('getAllSales')
            ->with(2, 50, 'amox')
            ->willReturn(['transactions' => [['sale_id' => 1]], 'pagination' => []]);

        $controller = new SalesController($sales);
        $result = $controller->getTransactions(2, 50, 'amox');

        $this->assertSame(['transactions' => [['sale_id' => 1]], 'pagination' => []], $result);
    }

    public function testGetSaleDetailsDelegatesToModel(): void
    {
        $sales = $this->createMock(Sales::class);
        $sales->expects($this->once())
            ->method('getSaleDetails')
            ->with(42)
            ->willReturn([['sale_id' => 42]]);

        $controller = new SalesController($sales);
        $result = $controller->getSaleDetails(42);

        $this->assertSame([['sale_id' => 42]], $result);
    }
}
