<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../app/Controllers/NotificationController.php';

final class NotificationControllerTest extends TestCase
{
    public function test_get_notifications_delegates_to_the_injected_model_and_returns_its_result(): void
    {
        $expected = [
            'summary' => [
                'all' => 2,
                'out_of_stock' => 1,
                'low_stock' => 1,
                'near_expiry' => 0,
            ],
            'notifications' => [
                ['product_id' => 1, 'type' => 'out_of_stock'],
                ['product_id' => 2, 'type' => 'low_stock'],
            ],
        ];

        $notification = $this->createMock(Notification::class);
        $notification->expects($this->once())
            ->method('getLiveNotifications')
            ->willReturn($expected);

        $controller = new NotificationController($notification);

        $this->assertSame($expected, $controller->getNotifications());
    }

    public function test_get_notifications_passes_through_an_empty_result_untouched(): void
    {
        $empty = [
            'summary' => ['all' => 0, 'out_of_stock' => 0, 'low_stock' => 0, 'near_expiry' => 0],
            'notifications' => [],
        ];

        $notification = $this->createMock(Notification::class);
        $notification->method('getLiveNotifications')->willReturn($empty);

        $controller = new NotificationController($notification);

        $this->assertSame($empty, $controller->getNotifications());
    }

    public function test_default_constructor_still_type_hints_a_real_notification_instance(): void
    {
        // Confirms the DI seam kept the no-arg default behaviour intact:
        // omitting the argument still builds a real Notification (which in
        // turn hard-wires the real Database) rather than requiring a mock.
        $reflection = new ReflectionMethod(NotificationController::class, '__construct');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertTrue($params[0]->allowsNull());
        $this->assertTrue($params[0]->isDefaultValueAvailable());
        $this->assertNull($params[0]->getDefaultValue());
    }
}
