<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Support/MocksPdo.php';
require_once __DIR__ . '/../../../app/Controllers/BillingController.php';

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for app/Controllers/BillingController.php.
 *
 * Billing itself is mocked out via the constructor-injection seam.
 */
final class BillingControllerTest extends TestCase
{
    use MocksPdo;

    public function testGetBillingsUsesDefaultArgsAndReturnsModelResult(): void
    {
        $billing = $this->createMock(Billing::class);
        $billing->expects($this->once())
            ->method('getAllBillings')
            ->with(1, 20, '')
            ->willReturn(['billings' => [], 'pagination' => []]);

        $controller = new BillingController($billing);
        $result = $controller->getBillings();

        $this->assertSame(['billings' => [], 'pagination' => []], $result);
    }

    public function testGetBillingsPassesThroughExplicitArgs(): void
    {
        $billing = $this->createMock(Billing::class);
        $billing->expects($this->once())
            ->method('getAllBillings')
            ->with(2, 50, 'gcash')
            ->willReturn(['billings' => [['payment_id' => 1]], 'pagination' => []]);

        $controller = new BillingController($billing);
        $result = $controller->getBillings(2, 50, 'gcash');

        $this->assertSame(['billings' => [['payment_id' => 1]], 'pagination' => []], $result);
    }

    public function testGetBillingDetailsDelegatesToModel(): void
    {
        $billing = $this->createMock(Billing::class);
        $billing->expects($this->once())
            ->method('getBillingDetails')
            ->with(7)
            ->willReturn(['payment_id' => 7]);

        $controller = new BillingController($billing);
        $result = $controller->getBillingDetails(7);

        $this->assertSame(['payment_id' => 7], $result);
    }
}
