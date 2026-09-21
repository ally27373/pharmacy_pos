<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

class Reports
{
    private PDO $conn;

    public function __construct(?PDO $conn = null)
    {
        $this->conn = $conn ?? (new Database())->connect();
    }

    /**
     * Returns the common report dataset used by the screen and exports.
     *
     * Source = Sales:
     *   one row per sold product (sale_items)
     *
     * Source = Inventory:
     *   one row per current product, with current stock value
     *
     * Source = All:
     *   both datasets, unified into one report shape.
     */
    private function baseQuery(): string
    {
        return "
            SELECT
                s.created_at AS report_date,
                'Sales' AS source,
                p.product_id,
                p.product_name,
                p.barcode,
                pt.type_name,
                c.category_name,
                si.quantity,
                si.unit_price,
                si.subtotal AS total_amount,
                COALESCE(pay.payment_method, '—') AS payment_method,
                s.transaction_number,
                CONCAT('INV-', LPAD(s.sale_id, 6, '0')) AS invoice_number
            FROM sales s
            INNER JOIN sale_items si
                ON si.sale_id = s.sale_id
            INNER JOIN products p
                ON p.product_id = si.product_id
            INNER JOIN categories c
                ON c.category_id = p.category_id
            INNER JOIN product_types pt
                ON pt.type_id = p.type_id
            LEFT JOIN payments pay
                ON pay.sale_id = s.sale_id
            WHERE s.transaction_status = 'Completed'

            UNION ALL

            SELECT
                COALESCE(p.updated_at, p.created_at) AS report_date,
                'Inventory' AS source,
                p.product_id,
                p.product_name,
                p.barcode,
                pt.type_name,
                c.category_name,
                p.quantity,
                p.selling_price AS unit_price,
                (p.quantity * p.selling_price) AS total_amount,
                '—' AS payment_method,
                NULL AS transaction_number,
                NULL AS invoice_number
            FROM products p
            INNER JOIN categories c
                ON c.category_id = p.category_id
            INNER JOIN product_types pt
                ON pt.type_id = p.type_id
            WHERE p.product_status <> 'Expired'
        ";
    }

    private function normalizeFilters(array $filters): array
    {
        $source = $filters['source'] ?? 'All';
        $source = in_array($source, ['All', 'Sales', 'Inventory'], true)
            ? $source
            : 'All';

        $period = $filters['period'] ?? 'weekly';
        $period = in_array($period, ['weekly', 'monthly', 'custom'], true)
            ? $period
            : 'weekly';

        $from = trim((string) ($filters['from'] ?? ''));
        $to = trim((string) ($filters['to'] ?? ''));

        if ($period === 'weekly') {
            $from = date('Y-m-d', strtotime('-6 days'));
            $to = date('Y-m-d');
        } elseif ($period === 'monthly') {
            $from = date('Y-m-01');
            $to = date('Y-m-d');
        } else {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
                $from = date('Y-m-d', strtotime('-6 days'));
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
                $to = date('Y-m-d');
            }

            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }
        }

        $limit = min(max(1, (int) ($filters['limit'] ?? 25)), 100);
        $page = max(1, (int) ($filters['page'] ?? 1));

        return [
            'page' => $page,
            'limit' => $limit,
            'search' => trim((string) ($filters['search'] ?? '')),
            'source' => $source,
            'category' => trim((string) ($filters['category'] ?? '')),
            'type' => trim((string) ($filters['type'] ?? '')),
            'period' => $period,
            'from' => $from,
            'to' => $to,
        ];
    }

    private function buildWhere(array $filters): array
    {
        $where = [
            'r.report_date >= :from_date',
            'r.report_date < DATE_ADD(:to_date, INTERVAL 1 DAY)',
        ];
        $params = [
            ':from_date' => $filters['from'],
            ':to_date' => $filters['to'],
        ];

        if ($filters['source'] !== 'All') {
            $where[] = 'r.source = :source';
            $params[':source'] = $filters['source'];
        }

        if ($filters['category'] !== '') {
            $where[] = 'r.category_name = :category';
            $params[':category'] = $filters['category'];
        }

        if ($filters['type'] !== '') {
            $where[] = 'r.type_name = :type';
            $params[':type'] = $filters['type'];
        }

if ($filters['search'] !== '') {
    $where[] = "
        (
            r.product_name LIKE :search_product
            OR r.barcode LIKE :search_barcode
            OR COALESCE(r.transaction_number, '') LIKE :search_transaction
            OR COALESCE(r.invoice_number, '') LIKE :search_invoice
            OR COALESCE(r.payment_method, '') LIKE :search_payment
        )
    ";

    $searchValue = '%' . $filters['search'] . '%';

    $params[':search_product'] = $searchValue;
    $params[':search_barcode'] = $searchValue;
    $params[':search_transaction'] = $searchValue;
    $params[':search_invoice'] = $searchValue;
    $params[':search_payment'] = $searchValue;
}

        return [implode(' AND ', $where), $params];
    }

    public function getReportData(array $rawFilters = []): array
    {
        $filters = $this->normalizeFilters($rawFilters);
        [$whereSql, $params] = $this->buildWhere($filters);

        $base = $this->baseQuery();
        $countSql = "SELECT COUNT(*) FROM ({$base}) r WHERE {$whereSql}";

        $countStmt = $this->conn->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $totalPages = $total > 0
            ? (int) ceil($total / $filters['limit'])
            : 0;

        if ($totalPages > 0 && $filters['page'] > $totalPages) {
            $filters['page'] = $totalPages;
        }

        $offset = ($filters['page'] - 1) * $filters['limit'];

        $sql = "
            SELECT
                r.report_date,
                r.source,
                r.product_id,
                r.product_name,
                r.barcode,
                r.type_name,
                r.category_name,
                r.quantity,
                r.unit_price,
                r.total_amount,
                r.payment_method,
                r.transaction_number,
                r.invoice_number
            FROM ({$base}) r
            WHERE {$whereSql}
            ORDER BY r.report_date DESC, r.source ASC, r.product_name ASC, r.product_id ASC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $filters['limit'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'pagination' => [
                'page' => $filters['page'],
                'limit' => $filters['limit'],
                'total' => $total,
                'total_pages' => $totalPages,
                'has_previous' => $filters['page'] > 1,
                'has_next' => $filters['page'] < $totalPages,
            ],
            'filters' => $filters,
        ];
    }

    /**
     * Export all matching rows without pagination.
     */
    public function getExportRows(array $rawFilters = []): array
    {
        $filters = $this->normalizeFilters($rawFilters);
        [$whereSql, $params] = $this->buildWhere($filters);

        $base = $this->baseQuery();
        $sql = "
            SELECT
                r.report_date,
                r.source,
                r.product_name,
                r.barcode,
                r.type_name,
                r.category_name,
                r.quantity,
                r.unit_price,
                r.total_amount,
                r.payment_method,
                r.transaction_number,
                r.invoice_number
            FROM ({$base}) r
            WHERE {$whereSql}
            ORDER BY r.report_date DESC, r.source ASC, r.product_name ASC, r.product_id ASC
        ";

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCategories(): array
    {
        $sql = "
            SELECT DISTINCT c.category_name
            FROM products p
            INNER JOIN categories c ON c.category_id = p.category_id
            WHERE p.category_id IS NOT NULL
            ORDER BY c.category_name ASC
        ";

        return $this->conn->query($sql)->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getTypes(): array
    {
        $sql = "
            SELECT DISTINCT pt.type_name
            FROM products p
            INNER JOIN product_types pt ON pt.type_id = p.type_id
            WHERE p.type_id IS NOT NULL
            ORDER BY pt.type_name ASC
        ";

        return $this->conn->query($sql)->fetchAll(PDO::FETCH_COLUMN);
    }
}
