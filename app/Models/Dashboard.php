<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../Services/ForecastService.php';

class Dashboard
{
    private PDO $conn;
    private ?\App\Services\ForecastService $forecastService;
    private ?string $productDemandForecastFile;

public function __construct(
    ?PDO $conn = null,
    ?\App\Services\ForecastService $forecastService = null,
    ?string $productDemandForecastFile = null
)
{
    $this->conn = $conn ?? (new Database())->connect();
    $this->forecastService = $forecastService;
    $this->productDemandForecastFile = $productDemandForecastFile;
}

private function getDateCondition($period, $column = 'created_at')
{
    switch ($period) {

        case 'today':
            return "DATE($column)=CURDATE()";

        case '7days':
            return "$column >= DATE_SUB(NOW(), INTERVAL 7 DAY)";

        case '30days':
            return "$column >= DATE_SUB(NOW(), INTERVAL 30 DAY)";

        case '3months':
            return "$column >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";

case '1year':
    return "$column >= MAKEDATE(YEAR(CURDATE()), 1)";

        case 'all':
    return "1=1";

default:
    return "$column >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    }
}

public function getTotalSales($period = '7days')
{
    $where = $this->getDateCondition($period, 'created_at');

    $sql = "
        SELECT
            COALESCE(SUM(total_amount),0) AS total_sales

        FROM sales

        WHERE transaction_status='Completed'

        AND $where
    ";

    return $this->conn
        ->query($sql)
        ->fetch(PDO::FETCH_ASSOC)['total_sales'];
}

    public function getTotalProducts()
    {
        $sql = "
            SELECT COUNT(*) AS total_products
            FROM products
        ";

        return $this->conn
            ->query($sql)
            ->fetch(PDO::FETCH_ASSOC)['total_products'];
    }

    public function getLowStockProducts()
    {
        $sql = "
            SELECT COUNT(*) AS low_stock
            FROM products
            WHERE quantity <= reorder_level
            AND product_status != 'Expired'
        ";

        return $this->conn
            ->query($sql)
            ->fetch(PDO::FETCH_ASSOC)['low_stock'];
    }

    public function getTotalUsers()
    {
        $sql = "
            SELECT COUNT(*) AS total_users
            FROM users
        ";

        return $this->conn
            ->query($sql)
            ->fetch(PDO::FETCH_ASSOC)['total_users'];
    }

   public function getItemsSold($period = '7days')
{
    $where = $this->getDateCondition($period, 's.created_at');

$sql = "

SELECT

COALESCE(SUM(si.quantity),0) AS items_sold

FROM sale_items si

INNER JOIN sales s

ON si.sale_id=s.sale_id

WHERE s.transaction_status='Completed'

AND $where

";

    return $this->conn
        ->query($sql)
        ->fetch(PDO::FETCH_ASSOC)['items_sold'];
}

public function getTransactions($period = '7days')
{
    $where = $this->getDateCondition($period,'created_at');

$sql="

SELECT COUNT(*) AS total_transactions

FROM sales

WHERE transaction_status='Completed'

AND $where

";

    return $this->conn
        ->query($sql)
        ->fetch(PDO::FETCH_ASSOC)['total_transactions'];
}

public function getSalesGrowth($period = '7days')
{
    $details = $this->getSalesGrowthDetails($period);

    return $details['growth_percent'];
}

public function getSalesGrowthDetails($period = '7days'): array
{
    $currentCondition = $this->getDateCondition($period, 'created_at');

    switch ($period) {

        case 'today':

            $previousCondition = "
                created_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                AND created_at < CURDATE()
            ";

            $comparisonLabel = 'vs yesterday';

            break;

        case '7days':

            $previousCondition = "
                created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
            ";

            $comparisonLabel = 'vs previous 7 days';

            break;

        case '30days':

            $previousCondition = "
                created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
            ";

            $comparisonLabel = 'vs previous 30 days';

            break;

        case '3months':

            $previousCondition = "
                created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                AND created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH)
            ";

            $comparisonLabel = 'vs previous 3 months';

            break;

        case '1year':

            $previousCondition = "
                created_at >= DATE_SUB(
                    MAKEDATE(YEAR(CURDATE()), 1),
                    INTERVAL 1 YEAR
                )
                AND created_at < DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
            ";

            $comparisonLabel = 'vs same period last year';

            break;

        case 'all':

            $currentCondition = "
                created_at >= MAKEDATE(YEAR(CURDATE()), 1)
            ";

            $previousCondition = "
                created_at >= MAKEDATE(YEAR(CURDATE()) - 1, 1)
                AND created_at < MAKEDATE(YEAR(CURDATE()), 1)
            ";

            $comparisonLabel = 'vs previous year';

            break;

        default:

            $previousCondition = "
                created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
            ";

            $comparisonLabel = 'vs previous 7 days';

            break;
    }

    $sql = "
        SELECT

            COALESCE(
                SUM(
                    CASE
                        WHEN {$currentCondition}
                        THEN total_amount
                        ELSE 0
                    END
                ),
                0
            ) AS current_sales,

            COALESCE(
                SUM(
                    CASE
                        WHEN {$previousCondition}
                        THEN total_amount
                        ELSE 0
                    END
                ),
                0
            ) AS previous_sales

        FROM sales

        WHERE transaction_status = 'Completed'
    ";

    $stmt = $this->conn->query($sql);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $currentSales =
        (float) ($row['current_sales'] ?? 0);

    $previousSales =
        (float) ($row['previous_sales'] ?? 0);

    /*
     * No previous sales means there is no valid
     * percentage-growth baseline.
     */
    if ($previousSales == 0.0) {

        return [
            'current_sales' => $currentSales,
            'previous_sales' => $previousSales,
            'growth_percent' => null,
            'has_previous_period' => false,
            'has_current_period' => $currentSales > 0,
            'comparison_label' => $comparisonLabel
        ];
    }

    $growthPercent = round(
        (
            ($currentSales - $previousSales)
            / $previousSales
        ) * 100,
        2
    );

    return [
        'current_sales' => $currentSales,
        'previous_sales' => $previousSales,
        'growth_percent' => $growthPercent,
        'has_previous_period' => true,
        'has_current_period' => $currentSales > 0,
        'comparison_label' => $comparisonLabel
    ];
}

public function getAvailableSalesYears(): array
{
    $sql = "
        SELECT DISTINCT YEAR(created_at) AS sales_year
        FROM sales
        WHERE transaction_status = 'Completed'
        ORDER BY sales_year DESC
    ";

    $years = $this->conn
        ->query($sql)
        ->fetchAll(PDO::FETCH_COLUMN);

    return array_map('intval', $years);
}

public function getAnalyticsYears(): array
{
    $currentYear = (int) date('Y');

    $sql = "
        SELECT DISTINCT YEAR(created_at) AS sales_year
        FROM sales
        WHERE transaction_status = 'Completed'
          AND created_at IS NOT NULL
        ORDER BY sales_year DESC
    ";

    $years = $this->conn
        ->query($sql)
        ->fetchAll(PDO::FETCH_COLUMN);

    $years = array_map('intval', $years);


    if (!in_array($currentYear, $years, true)) {
        array_unshift($years, $currentYear);
    }


    $years = array_values(
        array_filter(
            $years,
            static fn (int $year): bool =>
                $year >= $currentYear - 2
        )
    );

    rsort($years);

    return $years;
}


public function getRevenueByCategory($period = '7days')
{
    $where = $this->getDateCondition($period, 's.created_at');

    $sql = "
        SELECT
            c.category_name,
            COALESCE(SUM(si.subtotal), 0) AS revenue

        FROM categories c

        INNER JOIN products p
            ON c.category_id = p.category_id

        INNER JOIN sale_items si
            ON p.product_id = si.product_id

        INNER JOIN sales s
            ON si.sale_id = s.sale_id

        WHERE
            s.transaction_status = 'Completed'
            AND $where

        GROUP BY
            c.category_id,
            c.category_name

        ORDER BY revenue DESC
    ";

    return $this->conn
        ->query($sql)
        ->fetchAll(PDO::FETCH_ASSOC);
}

public function getMonthlySales($year = null)
{
    $year = $year ?? date('Y');

    $sql = "
        SELECT
            MONTH(created_at) AS month,
            SUM(total_amount) AS total_sales

        FROM sales

        WHERE
            transaction_status = 'Completed'

            AND YEAR(created_at) = :year

        GROUP BY MONTH(created_at)

        ORDER BY MONTH(created_at)
    ";

    $stmt = $this->conn->prepare($sql);

    $stmt->execute([
        ':year' => $year
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getPaymentMethods($period = '7days')
{
    $where = $this->getDateCondition($period, 's.created_at');

    $sql = "

        SELECT

            p.payment_method,

            COUNT(*) AS total

        FROM payments p

        INNER JOIN sales s
            ON p.sale_id = s.sale_id

        WHERE

            s.transaction_status = 'Completed'

            AND p.payment_status = 'Paid'

            AND $where

        GROUP BY p.payment_method

        ORDER BY total DESC

    ";

    return $this->conn
        ->query($sql)
        ->fetchAll(PDO::FETCH_ASSOC);
}
public function getDemandQuantity($year = null, $month = null)
{
    $sql = "
        SELECT
            p.product_name,
            MONTH(s.created_at) AS month,
            YEAR(s.created_at) AS year,
            SUM(si.quantity) AS total_quantity

        FROM products p

        INNER JOIN sale_items si
            ON p.product_id = si.product_id

        INNER JOIN sales s
            ON si.sale_id = s.sale_id

        WHERE
            s.transaction_status = 'Completed'
    ";

    $params = [];

    if ($year !== null) {

        $sql .= "
            AND YEAR(s.created_at) = :year
        ";

        $params[':year'] = $year;
    }

    if ($month !== null) {

        $sql .= "
            AND MONTH(s.created_at) = :month
        ";

        $params[':month'] = $month;
    }

    $sql .= "

        GROUP BY
            p.product_id,
            p.product_name,
            YEAR(s.created_at),
            MONTH(s.created_at)

        ORDER BY
            year ASC,
            month ASC,
            total_quantity DESC
    ";

    $stmt = $this->conn->prepare($sql);

    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getBestSellingProducts(
    $month = null,
    $year = null,
    $limit = 5
)
{
    $sql = "

    SELECT

        p.product_name,

        SUM(si.quantity) AS total_quantity

    FROM sale_items si

    INNER JOIN products p
        ON p.product_id = si.product_id

    INNER JOIN sales s
        ON s.sale_id = si.sale_id

    WHERE

        s.transaction_status='Completed'

    ";

    $params = [];

    if ($month !== null) {

        $sql .= "

            AND MONTH(s.created_at)=:month

        ";

        $params[':month'] = $month;

    }

    if ($year !== null) {

        $sql .= "

            AND YEAR(s.created_at)=:year

        ";

        $params[':year'] = $year;

    }

    $sql .= "

    GROUP BY

        p.product_id,
        p.product_name

    ORDER BY

        total_quantity DESC

    LIMIT " . (int)$limit;

    $stmt = $this->conn->prepare($sql);

    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function getLeastSellingProducts(
    $month = null,
    $year = null,
    $limit = 5
)
{
    $sql = "

    SELECT

        p.product_name,

        SUM(si.quantity) AS total_quantity

    FROM sale_items si

    INNER JOIN products p
        ON p.product_id = si.product_id

    INNER JOIN sales s
        ON s.sale_id = si.sale_id

    WHERE

        s.transaction_status='Completed'

    ";

    $params = [];

    if ($month !== null) {

        $sql .= "

            AND MONTH(s.created_at)=:month

        ";

        $params[':month'] = $month;

    }

    if ($year !== null) {

        $sql .= "

            AND YEAR(s.created_at)=:year

        ";

        $params[':year'] = $year;

    }

    $sql .= "

    GROUP BY

        p.product_id,
        p.product_name

    ORDER BY

        total_quantity ASC

    LIMIT " . (int)$limit;

    $stmt = $this->conn->prepare($sql);

    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
public function getTotalCategories()
{
    $sql = "
        SELECT COUNT(*) AS total_categories
        FROM categories
    ";

    return $this->conn
        ->query($sql)
        ->fetch(PDO::FETCH_ASSOC)['total_categories'];
}

public function getOutOfStockProducts()
{
    $sql = "
        SELECT COUNT(*) AS out_of_stock
        FROM products
        WHERE quantity = 0
    ";

    return $this->conn
        ->query($sql)
        ->fetch(PDO::FETCH_ASSOC)['out_of_stock'];
}

public function getQuantityByCategory()
{
    $sql = "
        SELECT

            c.category_name,

            COALESCE(SUM(p.quantity),0) AS total_quantity

        FROM categories c

        INNER JOIN products p

            ON c.category_id = p.category_id

        WHERE p.quantity > 0

        GROUP BY c.category_id, c.category_name

        ORDER BY total_quantity DESC
    ";

    return $this->conn
        ->query($sql)
        ->fetchAll(PDO::FETCH_ASSOC);
}

public function getInventoryProducts()
{
    $sql = "
        SELECT
            p.product_id,
            p.product_name,
            p.barcode,
            p.quantity,
            p.selling_price,
            c.category_name,
            t.type_name,
            p.product_status
        FROM products p
        LEFT JOIN categories c
            ON c.category_id = p.category_id
        LEFT JOIN product_types t
            ON t.type_id = p.type_id
        ORDER BY
            p.product_name ASC,
            p.product_id ASC
    ";

    return $this->conn
        ->query($sql)
        ->fetchAll(PDO::FETCH_ASSOC);
}

public function getNearExpiryProducts()
{
    $sql = "

        SELECT

            product_name,
            batch_number,
            quantity,
            expiration_date,

            DATEDIFF(expiration_date, CURDATE()) AS days_left

        FROM products

        WHERE expiration_date IS NOT NULL

        AND expiration_date >= CURDATE()

        AND expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)

        ORDER BY expiration_date ASC

        LIMIT 10

    ";

    return $this->conn
        ->query($sql)
        ->fetchAll(PDO::FETCH_ASSOC);
}

public function getFastMovingProducts(

    $month,

    $year,

    $limit = 5

)
{

    $sql = "

        SELECT

            p.product_name,

            SUM(si.quantity) AS total_sold

        FROM sale_items si

        INNER JOIN products p

            ON p.product_id = si.product_id

        INNER JOIN sales s

            ON s.sale_id = si.sale_id

        WHERE

            MONTH(s.created_at) = :month

        AND

            YEAR(s.created_at) = :year

        GROUP BY p.product_id

        ORDER BY total_sold DESC

        LIMIT :limit

    ";

    $stmt = $this->conn->prepare($sql);

    $stmt->bindValue(

        ':month',

        (int)$month,

        PDO::PARAM_INT

    );

    $stmt->bindValue(

        ':year',

        (int)$year,

        PDO::PARAM_INT

    );

    $stmt->bindValue(

        ':limit',

        (int)$limit,

        PDO::PARAM_INT

    );

    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);

}

public function getBestSeller()
{
    $sql = "

        SELECT

            p.product_name

        FROM sale_items si

        INNER JOIN products p

            ON p.product_id = si.product_id

        GROUP BY p.product_id

        ORDER BY SUM(si.quantity) DESC

        LIMIT 1

    ";

    return $this->conn->query($sql)->fetchColumn() ?: 'No Sales Yet';
}

public function getHighestStock()
{
    $sql = "

        SELECT product_name

        FROM products

        ORDER BY quantity DESC

        LIMIT 1

    ";

    return $this->conn->query($sql)->fetchColumn() ?: 'No Products';
}

public function getLowestStock()
{
    $sql = "

        SELECT product_name

        FROM products

        WHERE quantity <= reorder_level

        ORDER BY quantity ASC

        LIMIT 1

    ";

    return $this->conn->query($sql)->fetchColumn() ?: 'None';
}

public function getNearestExpiry()
{
    $sql = "

        SELECT product_name

        FROM products

        WHERE expiration_date >= CURDATE()

        ORDER BY expiration_date ASC

        LIMIT 1

    ";

    return $this->conn->query($sql)->fetchColumn() ?: 'None';
}

public function getInventoryInsights()
{
    return [

        'best_seller' => $this->getBestSeller(),

        'highest_stock' => $this->getHighestStock(),

        'low_stock_product' => $this->getLowestStock(),

        'expiring_product' => $this->getNearestExpiry()

    ];
}

public function exportSalesHistory()
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

public function getForecastData()
{
    $forecastService = $this->forecastService ?? new \App\Services\ForecastService();

    $result = $forecastService->getForecast();

    if (!$result['success']) {
        return [];
    }

    return $result['data'];
}

public function getProductDemandForecast(): array
{
    $file = $this->productDemandForecastFile ?? (
        __DIR__
        . DIRECTORY_SEPARATOR . '..'
        . DIRECTORY_SEPARATOR . '..'
        . DIRECTORY_SEPARATOR . 'sarima_forecasting'
        . DIRECTORY_SEPARATOR . 'outputs'
        . DIRECTORY_SEPARATOR . 'deployment'
        . DIRECTORY_SEPARATOR . 'product_demand_forecast_30_day.json'
    );

    $file = realpath($file);

    if ($file === false || !is_file($file) || filesize($file) === 0) {
        return [
            'metadata' => [],
            'products' => []
        ];
    }

    $json = file_get_contents($file);

    if ($json === false || trim($json) === '') {
        return [
            'metadata' => [],
            'products' => []
        ];
    }

    $data = json_decode($json, true);

    if (!is_array($data)) {
        return [
            'metadata' => [],
            'products' => []
        ];
    }

    return [
        'metadata' => is_array($data['metadata'] ?? null)
            ? $data['metadata']
            : [],

        'products' => is_array($data['products'] ?? null)
            ? $data['products']
            : []
    ];
}

public function getHistoricalDemand()
{
    $sql = "

        SELECT

            DATE(s.created_at) AS date,

            SUM(si.quantity) AS quantity_sold

        FROM sales s

        INNER JOIN sale_items si

            ON s.sale_id = si.sale_id

        WHERE

            s.transaction_status = 'Completed'

        GROUP BY

            DATE(s.created_at)

        ORDER BY

            date ASC

    ";

    return $this->conn
        ->query($sql)
        ->fetchAll(PDO::FETCH_ASSOC);
}

public function getMonthlyDemand($month = null, $year = null)
{
    $month = $month ?? date('n');
    $year  = $year ?? date('Y');

    $sql = "

        SELECT

            DAY(s.created_at) AS day,

            SUM(si.quantity) AS total_quantity

        FROM sales s

        INNER JOIN sale_items si

            ON s.sale_id = si.sale_id

        WHERE

            s.transaction_status = 'Completed'

            AND MONTH(s.created_at) = :month

            AND YEAR(s.created_at) = :year

        GROUP BY

            DAY(s.created_at)

        ORDER BY

            DAY(s.created_at) ASC

    ";

    $stmt = $this->conn->prepare($sql);

    $stmt->execute([

        ':month' => $month,

        ':year' => $year

    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


}

