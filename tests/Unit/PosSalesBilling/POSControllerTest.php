<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Support/MocksPdo.php';
require_once __DIR__ . '/../../../app/Controllers/POSController.php';

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for app/Controllers/POSController.php.
 *
 * POS itself is mocked out via the constructor-injection seam, so these
 * tests only verify the controller's own logic: $_GET parsing/normalizing
 * and delegation to the model. processSale() reads php://input directly
 * and touches $_SESSION/http_response_code(), which isn't practically
 * unit-testable without a much larger seam than TESTING.md allows for —
 * its business logic is already fully covered via POSTest::processSale().
 */
final class POSControllerTest extends TestCase
{
    use MocksPdo;

    private array $originalGet;

    protected function setUp(): void
    {
        $this->originalGet = $_GET;
    }

    protected function tearDown(): void
    {
        $_GET = $this->originalGet;
    }

    public function testGetProductsUsesDefaultsWhenNoQueryParamsGiven(): void
    {
        $_GET = [];

        $pos = $this->createMock(POS::class);
        $pos->expects($this->once())
            ->method('getAvailableProducts')
            ->with(1, 20, '', '', '')
            ->willReturn(['products' => [], 'pagination' => []]);

        $controller = new POSController($pos);
        $result = $controller->getProducts();

        $this->assertSame(['products' => [], 'pagination' => []], $result);
    }

    public function testGetProductsNormalizesAndTrimsQueryParams(): void
    {
        $_GET = [
            'page' => '0',
            'limit' => '500',
            'search' => '  amox  ',
            'category' => '  Antibiotics  ',
            'type' => '  Tablet  ',
        ];

        $pos = $this->createMock(POS::class);
        $pos->expects($this->once())
            ->method('getAvailableProducts')
            ->with(1, 100, 'amox', 'Antibiotics', 'Tablet')
            ->willReturn(['products' => [], 'pagination' => []]);

        $controller = new POSController($pos);
        $controller->getProducts();
    }

    public function testGetProductsPassesThroughValidPageAndLimit(): void
    {
        $_GET = ['page' => '3', 'limit' => '15'];

        $pos = $this->createMock(POS::class);
        $pos->expects($this->once())
            ->method('getAvailableProducts')
            ->with(3, 15, '', '', '')
            ->willReturn(['products' => [], 'pagination' => []]);

        $controller = new POSController($pos);
        $controller->getProducts();
    }

    public function testGetCategoriesDelegatesToModel(): void
    {
        $pos = $this->createMock(POS::class);
        $pos->expects($this->once())
            ->method('getCategories')
            ->willReturn([['category_id' => 1, 'category_name' => 'Antibiotics']]);

        $controller = new POSController($pos);
        $result = $controller->getCategories();

        $this->assertSame([['category_id' => 1, 'category_name' => 'Antibiotics']], $result);
    }

    public function testGetProductTypesDelegatesToModel(): void
    {
        $pos = $this->createMock(POS::class);
        $pos->expects($this->once())
            ->method('getProductTypes')
            ->willReturn([['type_id' => 1, 'type_name' => 'Tablet']]);

        $controller = new POSController($pos);
        $result = $controller->getProductTypes();

        $this->assertSame([['type_id' => 1, 'type_name' => 'Tablet']], $result);
    }
}
