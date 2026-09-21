<?php

require_once __DIR__ . '/../../config/database.php';

class Notification
{
    private PDO $conn;


    public function __construct(?PDO $conn = null)
    {
        $this->conn =
            $conn ?? (new Database())->connect();
    }


    /**
     * Get current live inventory alerts.
     *
     * These are calculated directly from products.
     *
     * Out of Stock:
     * quantity <= 0
     *
     * Low Stock:
     * quantity > 0 AND quantity <= reorder_level
     *
     * Near Expiry:
     * expiration date is today through 30 days ahead
     */
    public function getLiveNotifications(): array
    {

        /*
        |--------------------------------------------------------------------------
        | LIVE INVENTORY ALERT QUERY
        |--------------------------------------------------------------------------
        */

        $sql = "

            SELECT

                p.product_id,

                p.product_name,

                p.quantity,

                p.reorder_level,

                p.expiration_date,

                c.category_name,

                t.type_name,

                CASE

                    WHEN p.quantity <= 0
                        THEN 'out_of_stock'

                    WHEN p.quantity <=
                         COALESCE(p.reorder_level, 10)

                        THEN 'low_stock'

                    WHEN p.expiration_date IS NOT NULL

                         AND DATEDIFF(
                             p.expiration_date,
                             CURRENT_DATE
                         ) BETWEEN 0 AND 30

                        THEN 'near_expiry'

                END AS alert_type,

                DATEDIFF(
                    p.expiration_date,
                    CURRENT_DATE
                ) AS days_left

            FROM products p

            LEFT JOIN categories c
                ON c.category_id =
                   p.category_id

            LEFT JOIN product_types t
                ON t.type_id =
                   p.type_id

            WHERE

                p.product_status != 'Expired'

                AND

                (

                    p.quantity <= 0

                    OR

                    (
                        p.quantity > 0
                        AND p.quantity <=
                            COALESCE(
                                p.reorder_level,
                                10
                            )
                    )

                    OR

                    (
                        p.expiration_date IS NOT NULL
                        AND DATEDIFF(
                            p.expiration_date,
                            CURRENT_DATE
                        ) BETWEEN 0 AND 30
                    )

                )

            ORDER BY

                CASE

                    WHEN p.quantity <= 0
                        THEN 1

                    WHEN p.quantity <=
                         COALESCE(p.reorder_level, 10)
                        THEN 2

                    ELSE 3

                END,

                p.product_name ASC

        ";


        $stmt =
            $this->conn->prepare($sql);

        $stmt->execute();


        $rows =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        /*
        |--------------------------------------------------------------------------
        | BUILD NOTIFICATION ARRAY
        |--------------------------------------------------------------------------
        */

        $notifications = [];


        foreach ($rows as $row) {

            $type =
                $row['alert_type'];


            if (!$type) {
                continue;
            }


            $message =
                '';

            $details =
                '';


            /*
            |--------------------------------------------------------------------------
            | OUT OF STOCK
            |--------------------------------------------------------------------------
            */

            if (
                $type === 'out_of_stock'
            ) {

                $message =
                    'Out of stock';


                $details =
                    'Currently unavailable.';

            }


            /*
            |--------------------------------------------------------------------------
            | LOW STOCK
            |--------------------------------------------------------------------------
            */

            elseif (
                $type === 'low_stock'
            ) {

                $message =
                    'Low stock';


                $details =
                    (int) $row['quantity']
                    . ' unit(s) remaining.';

            }


            /*
            |--------------------------------------------------------------------------
            | NEAR EXPIRY
            |--------------------------------------------------------------------------
            */

            elseif (
                $type === 'near_expiry'
            ) {

                $days =
                    (int) $row['days_left'];


                if ($days === 0) {

                    $message =
                        'Expires today';

                    $details =
                        'Expiration date: '
                        . $row['expiration_date'];

                }
                elseif ($days === 1) {

                    $message =
                        'Expires tomorrow';

                    $details =
                        '1 day left • '
                        . $row['expiration_date'];

                }
                else {

                    $message =
                        'Near expiry';

                    $details =
                        $days
                        . ' days left • '
                        . $row['expiration_date'];

                }

            }


            $notifications[] = [

                'product_id' =>
                    (int) $row['product_id'],

                'product_name' =>
                    $row['product_name'],

                'type' =>
                    $type,

                'message' =>
                    $message,

                'details' =>
                    $details,

                'quantity' =>
                    (int) $row['quantity'],

                'reorder_level' =>
                    (int) $row['reorder_level'],

                'expiration_date' =>
                    $row['expiration_date'],

                'days_left' =>
                    $row['days_left'] !== null
                        ? (int) $row['days_left']
                        : null,

                'category_name' =>
                    $row['category_name'],

                'type_name' =>
                    $row['type_name']

            ];

        }


        /*
        |--------------------------------------------------------------------------
        | SUMMARY
        |--------------------------------------------------------------------------
        */

        $outOfStock =
            0;

        $lowStock =
            0;

        $nearExpiry =
            0;


        foreach (
            $notifications
            as $notification
        ) {

            switch (
                $notification['type']
            ) {

                case 'out_of_stock':

                    $outOfStock++;

                    break;


                case 'low_stock':

                    $lowStock++;

                    break;


                case 'near_expiry':

                    $nearExpiry++;

                    break;

            }

        }


        /*
        |--------------------------------------------------------------------------
        | ALL ALERTS
        |--------------------------------------------------------------------------
        |
        | A product can technically have more than one
        | condition. We count unique products here.
        |
        */

        $uniqueProducts = [];


        foreach (
            $notifications
            as $notification
        ) {

            $uniqueProducts[
                $notification['product_id']
            ] = true;

        }


        return [

            'summary' => [

                'all' =>
                    count($uniqueProducts),

                'out_of_stock' =>
                    $outOfStock,

                'low_stock' =>
                    $lowStock,

                'near_expiry' =>
                    $nearExpiry

            ],

            'notifications' =>
                $notifications

        ];

    }

}