<?php

require_once __DIR__ . '/../../config/database.php';

class Export
{
    private PDO $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    public function getSalesHistory()
    {
        $sql = "

        SELECT

            DATE(s.created_at) AS sale_date,

            p.product_name,

            SUM(si.quantity) AS quantity_sold

        FROM sales s

        INNER JOIN sale_items si
            ON s.sale_id = si.sale_id

        INNER JOIN products p
            ON si.product_id = p.product_id

        WHERE s.transaction_status='Completed'

        GROUP BY

            DATE(s.created_at),
            p.product_id,
            p.product_name

        ORDER BY sale_date ASC

        ";

        return $this->conn
                    ->query($sql)
                    ->fetchAll(PDO::FETCH_ASSOC);
    }
}