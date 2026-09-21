<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

class DataManagement
{
    private PDO $conn;

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? (new Database())->connect();
    }

    /**
     * Get the current product/inventory dataset for export.
     */
    public function getInventoryForExport(): array
    {
        $sql = "
            SELECT
                p.product_id,
                p.barcode,
                p.product_name,
                p.generic_name,
                p.brand_name,
                c.category_name,
                pt.type_name AS product_type,
                s.supplier_name,
                p.dosage,
                p.strength,
                p.unit,
                p.unit_cost,
                p.selling_price,
                p.quantity,
                p.reorder_level,
                p.manufacturing_date,
                p.expiration_date,
                p.batch_number,
                p.product_status,
                p.description,
                p.created_at,
                p.updated_at
            FROM products p

            LEFT JOIN categories c
                ON c.category_id = p.category_id

            LEFT JOIN product_types pt
                ON pt.type_id = p.type_id

            LEFT JOIN suppliers s
                ON s.supplier_id = p.supplier_id

            ORDER BY
                p.product_name ASC,
                p.product_id ASC
        ";

        return $this->conn
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get completed sales transaction lines for export.
     *
     * Cancelled/void transactions are excluded.
     */
    public function getSalesForExport(): array
    {
        $sql = "
            SELECT
                s.sale_id,
                s.transaction_number,

                DATE(s.created_at) AS sale_date,
                TIME(s.created_at) AS sale_time,

                p.product_id,
                p.barcode,
                p.product_name,

                si.quantity,
                si.unit_price,
                si.discount_amount,
                si.subtotal,

                s.subtotal AS transaction_subtotal,
                s.discount_amount AS transaction_discount,
                s.vat_amount,
                s.total_amount,

                s.payment_status,
                s.transaction_status,

                pay.payment_methods,

                u.user_id AS cashier_id,
                u.username AS cashier_username,
                u.full_name AS cashier_name,

                s.remarks

            FROM sales s

            INNER JOIN sale_items si
                ON si.sale_id = s.sale_id

            INNER JOIN products p
                ON p.product_id = si.product_id

            LEFT JOIN users u
                ON u.user_id = s.cashier_id

            LEFT JOIN (
                SELECT
                    sale_id,

                    GROUP_CONCAT(
                        DISTINCT payment_method
                        ORDER BY payment_id
                        SEPARATOR ', '
                    ) AS payment_methods

                FROM payments

                GROUP BY sale_id
            ) pay
                ON pay.sale_id = s.sale_id

            WHERE s.transaction_status = 'Completed'

            ORDER BY
                s.created_at ASC,
                s.sale_id ASC,
                si.sale_item_id ASC
        ";

        return $this->conn
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count current inventory records.
     */
    public function countInventory(): int
    {
        return (int) $this->conn
            ->query("
                SELECT COUNT(*)
                FROM products
            ")
            ->fetchColumn();
    }

    /**
     * Count completed sales line items.
     */
    public function countSalesRows(): int
    {
        return (int) $this->conn
            ->query("
                SELECT COUNT(*)
                FROM sales s

                INNER JOIN sale_items si
                    ON si.sale_id = s.sale_id

                WHERE s.transaction_status = 'Completed'
            ")
            ->fetchColumn();
    }
}