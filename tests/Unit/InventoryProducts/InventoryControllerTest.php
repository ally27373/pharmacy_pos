<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../app/Controllers/InventoryController.php';
require_once __DIR__ . '/../../Support/MocksPdo.php';

use PHPUnit\Framework\TestCase;

/**
 * InventoryController is a thin pass-through facade over the Inventory
 * model. These tests verify each public method delegates to the injected
 * model with the exact arguments received, and returns its result
 * unmodified — using the DI seam (optional Inventory $inventory param)
 * rather than a real PDO connection.
 */
final class InventoryControllerTest extends TestCase
{
    use MocksPdo;

    private function makeInventoryMock(): Inventory&\PHPUnit\Framework\MockObject\MockObject
    {
        return $this->getMockBuilder(Inventory::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testGetProductsDelegatesWithGivenArguments(): void
    {
        $expected = ['products' => [], 'pagination' => []];

        $model = $this->makeInventoryMock();
        $model->expects(self::once())
            ->method('getProducts')
            ->with(2, 10, 'amoxi', 'Antibiotics', 'Tablet')
            ->willReturn($expected);

        $controller = new InventoryController($model);
        self::assertSame($expected, $controller->getProducts(2, 10, 'amoxi', 'Antibiotics', 'Tablet'));
    }

    public function testGetProductDetailsDelegates(): void
    {
        $model = $this->makeInventoryMock();
        $model->expects(self::once())->method('getProductDetails')->with(5)->willReturn(['product_id' => 5]);

        $controller = new InventoryController($model);
        self::assertSame(['product_id' => 5], $controller->getProductDetails(5));
    }

    public function testGetProductBatchesDelegates(): void
    {
        $model = $this->makeInventoryMock();
        $model->expects(self::once())->method('getProductBatches')->with(7)->willReturn([['batch_id' => 1]]);

        $controller = new InventoryController($model);
        self::assertSame([['batch_id' => 1]], $controller->getProductBatches(7));
    }

    public function testGetCategoriesDelegates(): void
    {
        $model = $this->makeInventoryMock();
        $model->expects(self::once())->method('getCategories')->willReturn([['category_id' => 1]]);

        $controller = new InventoryController($model);
        self::assertSame([['category_id' => 1]], $controller->getCategories());
    }

    public function testGetTypesDelegates(): void
    {
        $model = $this->makeInventoryMock();
        $model->expects(self::once())->method('getTypes')->willReturn([['type_id' => 1]]);

        $controller = new InventoryController($model);
        self::assertSame([['type_id' => 1]], $controller->getTypes());
    }

    public function testGetSuppliersDelegates(): void
    {
        $model = $this->makeInventoryMock();
        $model->expects(self::once())->method('getSuppliers')->willReturn([['supplier_id' => 1]]);

        $controller = new InventoryController($model);
        self::assertSame([['supplier_id' => 1]], $controller->getSuppliers());
    }

    public function testSaveProductDelegatesDataAndReturnsResult(): void
    {
        $data = ['product_name' => 'Test'];
        $expected = ['success' => true, 'message' => 'ok', 'product_id' => 1, 'barcode' => 'X'];

        $model = $this->makeInventoryMock();
        $model->expects(self::once())->method('saveProduct')->with($data)->willReturn($expected);

        $controller = new InventoryController($model);
        self::assertSame($expected, $controller->saveProduct($data));
    }

    public function testAdjustStockDelegatesDataAndReturnsResult(): void
    {
        $data = ['product_id' => 1, 'action' => 'IN', 'quantity' => 5];
        $expected = ['success' => true, 'message' => 'ok'];

        $model = $this->makeInventoryMock();
        $model->expects(self::once())->method('adjustStock')->with($data)->willReturn($expected);

        $controller = new InventoryController($model);
        self::assertSame($expected, $controller->adjustStock($data));
    }

    public function testUpdateProductDelegatesDataAndReturnsResult(): void
    {
        $data = ['product_id' => 1];
        $expected = ['success' => true, 'message' => 'ok'];

        $model = $this->makeInventoryMock();
        $model->expects(self::once())->method('updateProduct')->with($data)->willReturn($expected);

        $controller = new InventoryController($model);
        self::assertSame($expected, $controller->updateProduct($data));
    }

    public function testDeleteProductDelegates(): void
    {
        $expected = ['success' => true, 'message' => 'deleted'];

        $model = $this->makeInventoryMock();
        $model->expects(self::once())->method('deleteProduct')->with(9)->willReturn($expected);

        $controller = new InventoryController($model);
        self::assertSame($expected, $controller->deleteProduct(9));
    }

    public function testCleanupTestProductDelegates(): void
    {
        $expected = ['success' => true, 'message' => 'cleaned'];

        $model = $this->makeInventoryMock();
        $model->expects(self::once())->method('cleanupTestProduct')->with(9)->willReturn($expected);

        $controller = new InventoryController($model);
        self::assertSame($expected, $controller->cleanupTestProduct(9));
    }
}
