<?php

require_once __DIR__ . '/../../config/database.php';

class InventoryHistory
{
    private PDO $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    public function getHistory(
        int $page = 1,
        int $limit = 20,
        string $search = '',
        string $action = '',
        string $fromDate = '',
        string $toDate = ''
    ): array
    {
        $page = max(1, $page);
        $limit = min(max(1, $limit), 100);
        $search = trim($search);
        $action = strtoupper(trim($action));
        $fromDate = trim($fromDate);
        $toDate = trim($toDate);

        if (!in_array($action, ['', 'IN', 'OUT'], true)) {
            $action = '';
        }

        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = '(p.product_name LIKE :search_product OR p.barcode LIKE :search_barcode)';
            $searchValue = '%' . $search . '%';
            $params[':search_product'] = $searchValue;
            $params[':search_barcode'] = $searchValue;
        }

        if ($action !== '') {
            $where[] = 'ih.action_type = :action_type';
            $params[':action_type'] = $action;
        }

        if ($fromDate !== '') {
            $where[] = 'DATE(ih.created_at) >= :from_date';
            $params[':from_date'] = $fromDate;
        }

        if ($toDate !== '') {
            $where[] = 'DATE(ih.created_at) <= :to_date';
            $params[':to_date'] = $toDate;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countSql = "
            SELECT COUNT(*)
            FROM inventory_history ih
            INNER JOIN products p ON ih.product_id = p.product_id
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
                ih.history_id,
                ih.product_id,
                p.product_name,
                p.barcode,
                ih.action_type,
                ih.quantity,
                ih.remarks,
                ih.created_at
            FROM inventory_history ih
            INNER JOIN products p ON ih.product_id = p.product_id
            {$whereSql}
            ORDER BY ih.created_at DESC, ih.history_id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'history' => $stmt->fetchAll(PDO::FETCH_ASSOC),
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
}
