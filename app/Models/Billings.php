<?php

require_once __DIR__ . '/../../config/database.php';

class Billing
{
    private PDO $conn;

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? (new Database())->connect();
    }

    public function getAllBillings(
        int $page = 1,
        int $limit = 20,
        string $search = ''
    ): array {
        $page = max(1, $page);
        $limit = min(max(1, $limit), 100);
        $search = trim($search);

        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = "
                CONCAT(
                    COALESCE(s.transaction_number, ''), ' ',
                    CONCAT('INV-', LPAD(s.sale_id, 6, '0')), ' ',
                    COALESCE(p.payment_method, ''), ' ',
                    COALESCE(p.payment_status, ''), ' ',
                    COALESCE(p.payment_id, '')
                ) LIKE :search
            ";
            $params[':search'] = '%' . $search . '%';
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "
            SELECT COUNT(*)
            FROM payments p
            INNER JOIN sales s ON p.sale_id = s.sale_id
            {$whereSql}
        ";

        $countStmt = $this->conn->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $totalPages = $total > 0 ? (int) ceil($total / $limit) : 0;

        if ($totalPages > 0 && $page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $limit;

        $sql = "
            SELECT
                p.payment_id,
                p.sale_id,
                s.transaction_number,
                CONCAT('INV-', LPAD(s.sale_id,6,'0')) AS invoice_number,
                p.payment_method,
                p.amount_due,
                p.amount_paid,
                p.change_amount,
                p.reference_number,
                p.payment_status,
                p.payment_date
            FROM payments p
            INNER JOIN sales s ON p.sale_id = s.sale_id
            {$whereSql}
            ORDER BY p.payment_date DESC, p.payment_id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $billings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'billings' => $billings,
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

    public function getBillingDetails($paymentId)
    {
        $sql = "
            SELECT
                p.payment_id,
                p.payment_method,
                p.reference_number,
                p.amount_due,
                p.amount_paid,
                p.change_amount,
                p.payment_status,
                p.payment_date,
                s.sale_id,
                s.transaction_number,
                CONCAT('INV-', LPAD(s.sale_id,6,'0')) AS invoice_number
            FROM payments p
            INNER JOIN sales s ON p.sale_id = s.sale_id
            WHERE p.payment_id = :payment_id
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':payment_id' => $paymentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
