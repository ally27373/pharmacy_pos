<?php

require_once __DIR__ . '/../../config/database.php';

class Sales
{
    private PDO $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    public function getAllSales(
        int $page = 1,
        int $limit = 20,
        string $search = ''
    ): array {
        $page = max(1, $page);
        $limit = min(max(1, $limit), 100);
        $search = trim($search);
        $offset = ($page - 1) * $limit;

        $where = ["s.transaction_status = 'Completed'"];
        $params = [];

        if ($search !== '') {
            $where[] = "
                CONCAT(
                    COALESCE(s.transaction_number, ''), ' ',
                    CONCAT('INV-', LPAD(s.sale_id, 6, '0')), ' ',
                    COALESCE(s.sale_id, ''), ' ',
                    COALESCE(pay.payment_method, '')
                ) LIKE :search
            ";
            $params[':search'] = '%' . $search . '%';
        }

        $whereSql = implode(' AND ', $where);

        $countSql = "
            SELECT COUNT(*)
            FROM sales s
            LEFT JOIN payments pay ON pay.sale_id = s.sale_id
            WHERE {$whereSql}
        ";

        $countStmt = $this->conn->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $sql = "
            SELECT
                s.sale_id,
                s.transaction_number,
                CONCAT('INV-', LPAD(s.sale_id, 6, '0')) AS invoice_number,
                DATE(s.created_at) AS sale_date,
                TIME(s.created_at) AS sale_time,
                COALESCE(pay.payment_method, '—') AS payment_method,
                s.payment_status,
                s.total_amount,
                s.created_at
            FROM sales s
            LEFT JOIN payments pay ON pay.sale_id = s.sale_id
            WHERE {$whereSql}
            ORDER BY s.created_at DESC, s.sale_id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $totalPages = $total > 0 ? (int) ceil($total / $limit) : 0;

        if ($totalPages > 0 && $page > $totalPages) {
            $page = $totalPages;
        }

        return [
            'transactions' => $transactions,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_previous' => $page > 1,
                'has_next' => $page < $totalPages,
            ],
        ];
    }

    public function getSaleDetails($saleId)
    {
        $sql = "
            SELECT
                s.sale_id,
                s.transaction_number,
                CONCAT('INV-', LPAD(s.sale_id,6,'0')) AS invoice_number,
                pay.payment_method,
                pay.reference_number,
                pay.amount_due,
                pay.amount_paid,
                pay.change_amount,
                s.payment_status,
                s.total_amount,
                s.created_at,
                p.product_name,
                si.quantity,
                si.unit_price,
                si.subtotal,
                (
                    SELECT GROUP_CONCAT(
                        CONCAT(
                            COALESCE(pb.batch_number, 'N/A'),
                            ' × ', sib.quantity,
                            CASE
                                WHEN pb.expiration_date IS NOT NULL
                                THEN CONCAT(' | Exp ', DATE_FORMAT(pb.expiration_date, '%Y-%m-%d'))
                                ELSE ''
                            END
                        )
                        ORDER BY pb.expiration_date IS NULL, pb.expiration_date ASC, pb.batch_id ASC
                        SEPARATOR ', '
                    )
                    FROM sale_item_batches sib
                    INNER JOIN product_batches pb ON pb.batch_id = sib.batch_id
                    WHERE sib.sale_item_id = si.sale_item_id
                ) AS batch_allocations
            FROM sales s
            INNER JOIN sale_items si ON s.sale_id = si.sale_id
            INNER JOIN products p ON si.product_id = p.product_id
            LEFT JOIN payments pay ON pay.sale_id = s.sale_id
            WHERE s.sale_id = :sale_id
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':sale_id' => $saleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
