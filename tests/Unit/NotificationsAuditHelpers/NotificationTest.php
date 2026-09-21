<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../Support/MocksPdo.php';
require_once __DIR__ . '/../../../app/Models/Notification.php';

final class NotificationTest extends TestCase
{
    use MocksPdo;

    private function makeNotification(array $rows): Notification
    {
        $statement = $this->createStatementMock();
        $statement->expects($this->once())
            ->method('execute');
        $statement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn($rows);

        $pdo = $this->pdoThatPrepares($statement, 'FROM products');

        return new Notification($pdo);
    }

    public function test_constructor_accepts_injected_pdo_without_touching_real_database(): void
    {
        $pdo = $this->createPdoMock();
        $notification = new Notification($pdo);

        $this->assertInstanceOf(Notification::class, $notification);
    }

    public function test_empty_result_set_yields_empty_notifications_and_zeroed_summary(): void
    {
        $notification = $this->makeNotification([]);

        $result = $notification->getLiveNotifications();

        $this->assertSame([], $result['notifications']);
        $this->assertSame(
            ['all' => 0, 'out_of_stock' => 0, 'low_stock' => 0, 'near_expiry' => 0],
            $result['summary']
        );
    }

    public function test_out_of_stock_row_is_mapped_correctly(): void
    {
        $rows = [
            [
                'product_id' => '1',
                'product_name' => 'Paracetamol',
                'quantity' => '0',
                'reorder_level' => '10',
                'expiration_date' => null,
                'category_name' => 'Pain Relief',
                'type_name' => 'Tablet',
                'alert_type' => 'out_of_stock',
                'days_left' => null,
            ],
        ];

        $notification = $this->makeNotification($rows);
        $result = $notification->getLiveNotifications();

        $this->assertCount(1, $result['notifications']);
        $item = $result['notifications'][0];

        $this->assertSame(1, $item['product_id']);
        $this->assertSame('out_of_stock', $item['type']);
        $this->assertSame('Out of stock', $item['message']);
        $this->assertSame('Currently unavailable.', $item['details']);
        $this->assertSame(0, $item['quantity']);
        $this->assertSame(10, $item['reorder_level']);
        $this->assertNull($item['days_left']);

        $this->assertSame(1, $result['summary']['all']);
        $this->assertSame(1, $result['summary']['out_of_stock']);
        $this->assertSame(0, $result['summary']['low_stock']);
        $this->assertSame(0, $result['summary']['near_expiry']);
    }

    public function test_low_stock_row_reports_remaining_units_in_details(): void
    {
        $rows = [
            [
                'product_id' => '2',
                'product_name' => 'Amoxicillin',
                'quantity' => '5',
                'reorder_level' => '10',
                'expiration_date' => null,
                'category_name' => 'Antibiotics',
                'type_name' => 'Capsule',
                'alert_type' => 'low_stock',
                'days_left' => null,
            ],
        ];

        $notification = $this->makeNotification($rows);
        $result = $notification->getLiveNotifications();

        $item = $result['notifications'][0];
        $this->assertSame('low_stock', $item['type']);
        $this->assertSame('Low stock', $item['message']);
        $this->assertSame('5 unit(s) remaining.', $item['details']);
        $this->assertSame(1, $result['summary']['low_stock']);
    }

    public function test_near_expiry_today_uses_expires_today_wording(): void
    {
        $rows = [
            [
                'product_id' => '3',
                'product_name' => 'Cough Syrup',
                'quantity' => '50',
                'reorder_level' => '10',
                'expiration_date' => '2026-09-21',
                'category_name' => 'Cough & Cold',
                'type_name' => 'Syrup',
                'alert_type' => 'near_expiry',
                'days_left' => '0',
            ],
        ];

        $notification = $this->makeNotification($rows);
        $result = $notification->getLiveNotifications();

        $item = $result['notifications'][0];
        $this->assertSame('Expires today', $item['message']);
        $this->assertSame('Expiration date: 2026-09-21', $item['details']);
        $this->assertSame(0, $item['days_left']);
    }

    public function test_near_expiry_tomorrow_uses_singular_day_wording(): void
    {
        $rows = [
            [
                'product_id' => '4',
                'product_name' => 'Vitamin C',
                'quantity' => '40',
                'reorder_level' => '10',
                'expiration_date' => '2026-09-22',
                'category_name' => 'Vitamins',
                'type_name' => 'Tablet',
                'alert_type' => 'near_expiry',
                'days_left' => '1',
            ],
        ];

        $notification = $this->makeNotification($rows);
        $result = $notification->getLiveNotifications();

        $item = $result['notifications'][0];
        $this->assertSame('Expires tomorrow', $item['message']);
        $this->assertSame('1 day left • 2026-09-22', $item['details']);
        $this->assertSame(1, $item['days_left']);
    }

    public function test_near_expiry_multiple_days_uses_plural_wording(): void
    {
        $rows = [
            [
                'product_id' => '5',
                'product_name' => 'Ibuprofen',
                'quantity' => '30',
                'reorder_level' => '10',
                'expiration_date' => '2026-10-06',
                'category_name' => 'Pain Relief',
                'type_name' => 'Tablet',
                'alert_type' => 'near_expiry',
                'days_left' => '15',
            ],
        ];

        $notification = $this->makeNotification($rows);
        $result = $notification->getLiveNotifications();

        $item = $result['notifications'][0];
        $this->assertSame('Near expiry', $item['message']);
        $this->assertSame('15 days left • 2026-10-06', $item['details']);
        $this->assertSame(15, $item['days_left']);
    }

    public function test_row_with_no_alert_type_is_skipped_defensively(): void
    {
        $rows = [
            [
                'product_id' => '6',
                'product_name' => 'Healthy Stock Item',
                'quantity' => '100',
                'reorder_level' => '10',
                'expiration_date' => null,
                'category_name' => 'General',
                'type_name' => 'Tablet',
                'alert_type' => null,
                'days_left' => null,
            ],
        ];

        $notification = $this->makeNotification($rows);
        $result = $notification->getLiveNotifications();

        $this->assertSame([], $result['notifications']);
        $this->assertSame(0, $result['summary']['all']);
    }

    public function test_summary_all_counts_unique_products_while_per_type_counts_every_row(): void
    {
        // Not reachable through the real SQL (CASE assigns exactly one
        // alert_type per row), but the PHP aggregation code defends against
        // a product id repeating under different alert types, so we exercise
        // that path directly against the documented behaviour.
        $rows = [
            [
                'product_id' => '7',
                'product_name' => 'Duplicate Product',
                'quantity' => '0',
                'reorder_level' => '10',
                'expiration_date' => null,
                'category_name' => 'General',
                'type_name' => 'Tablet',
                'alert_type' => 'out_of_stock',
                'days_left' => null,
            ],
            [
                'product_id' => '7',
                'product_name' => 'Duplicate Product',
                'quantity' => '0',
                'reorder_level' => '10',
                'expiration_date' => null,
                'category_name' => 'General',
                'type_name' => 'Tablet',
                'alert_type' => 'low_stock',
                'days_left' => null,
            ],
        ];

        $notification = $this->makeNotification($rows);
        $result = $notification->getLiveNotifications();

        $this->assertCount(2, $result['notifications']);
        $this->assertSame(1, $result['summary']['all']);
        $this->assertSame(1, $result['summary']['out_of_stock']);
        $this->assertSame(1, $result['summary']['low_stock']);
    }

    public function test_full_result_set_mixes_all_alert_types_with_correct_summary_counts(): void
    {
        $rows = [
            [
                'product_id' => '1', 'product_name' => 'A', 'quantity' => '0',
                'reorder_level' => '10', 'expiration_date' => null,
                'category_name' => 'Cat', 'type_name' => 'Type',
                'alert_type' => 'out_of_stock', 'days_left' => null,
            ],
            [
                'product_id' => '2', 'product_name' => 'B', 'quantity' => '3',
                'reorder_level' => '10', 'expiration_date' => null,
                'category_name' => 'Cat', 'type_name' => 'Type',
                'alert_type' => 'low_stock', 'days_left' => null,
            ],
            [
                'product_id' => '3', 'product_name' => 'C', 'quantity' => '20',
                'reorder_level' => '10', 'expiration_date' => '2026-09-25',
                'category_name' => 'Cat', 'type_name' => 'Type',
                'alert_type' => 'near_expiry', 'days_left' => '4',
            ],
        ];

        $notification = $this->makeNotification($rows);
        $result = $notification->getLiveNotifications();

        $this->assertCount(3, $result['notifications']);
        $this->assertSame(3, $result['summary']['all']);
        $this->assertSame(1, $result['summary']['out_of_stock']);
        $this->assertSame(1, $result['summary']['low_stock']);
        $this->assertSame(1, $result['summary']['near_expiry']);
    }
}
