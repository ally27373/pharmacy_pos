<?php

require_once __DIR__ . '/../Models/Dashboard.php';

class DashboardController
{
    private Dashboard $dashboard;

    public function __construct()
    {
        $this->dashboard = new Dashboard();
    }

    

public function getDashboardData($period = '7days')
{

$bestMonth = $_GET['best_month'] ?? date('n');

$bestYear = $_GET['best_year'] ?? date('Y');

$bestLimit = $_GET['best_limit'] ?? 5;

$mode = $_GET['mode'] ?? 'best';

$fastMonth = $_GET['fast_month'] ?? date('n');

$fastYear = $_GET['fast_year'] ?? date('Y');

$fastLimit = $_GET['fast_limit'] ?? 5;
    return [

        /*
        |--------------------------------------------------------------------------
        | GLOBAL FILTER
        | Controls KPI cards only
        |--------------------------------------------------------------------------
        */

        'total_sales' =>
            $this->dashboard->getTotalSales($period),

        'items_sold' =>
            $this->dashboard->getItemsSold($period),

        'transactions' =>
            $this->dashboard->getTransactions($period),

        'sales_growth' =>
            $this->dashboard->getSalesGrowth($period),
            'sales_growth_details' =>
    $this->dashboard->getSalesGrowthDetails($period),


        /*
        |--------------------------------------------------------------------------
        | ANALYTICS CHARTS
        | Temporary default periods
        | These will receive their own filters later
        |--------------------------------------------------------------------------
        */

        'revenue_by_category' =>
            $this->dashboard->getRevenueByCategory('30days'),

        'monthly_sales'
    => $this->dashboard->getMonthlySales(
        $_GET['sales_year'] ?? date('Y')
    ),

    'available_sales_years' => $this->dashboard->getAvailableSalesYears(),

    'monthly_demand'
    => $this->dashboard->getMonthlyDemand(
        $_GET['demand_month'] ?? date('n'),
        $_GET['demand_year'] ?? date('Y')
    ),

        'payment_methods' =>
            $this->dashboard->getPaymentMethods('30days'),

        'demand_quantity'
    => $this->dashboard->getMonthlyDemand(

        $_GET['demand_month']
            ?? date('n'),

        $_GET['demand_year']
            ?? date('Y')

    ),

      'best_sellers' =>

$mode === 'least'

?

$this->dashboard->getLeastSellingProducts(
    $bestMonth,
    $bestYear,
    $bestLimit
)

:

$this->dashboard->getBestSellingProducts(
    $bestMonth,
    $bestYear,
    $bestLimit
),
'least_sellers' =>
    $this->dashboard->getLeastSellingProducts(
        $bestMonth,
        $bestYear,
        $bestLimit
    ),


        /*
        |--------------------------------------------------------------------------
        | INVENTORY
        |--------------------------------------------------------------------------
        */

        'total_categories' =>
            $this->dashboard->getTotalCategories(),

        'out_of_stock' =>
            $this->dashboard->getOutOfStockProducts(),

        'low_stock' =>
            $this->dashboard->getLowStockProducts(),

        'total_products' =>
            $this->dashboard->getTotalProducts(),

        'quantity_by_category' =>
            $this->dashboard->getQuantityByCategory(),

        'inventory_products' =>
            $this->dashboard->getInventoryProducts(),

        'near_expiry' =>
            $this->dashboard->getNearExpiryProducts(),

        'fast_moving' =>

$this->dashboard->getFastMovingProducts(

    $fastMonth,

    $fastYear,

    $fastLimit

),

        'inventory_insights' =>
            $this->dashboard->getInventoryInsights(),


        /*
        |--------------------------------------------------------------------------
        | SARIMA
        |--------------------------------------------------------------------------
        */

        'forecast' =>
            $this->dashboard->getForecastData(),

        'historical_demand' =>
            $this->dashboard->getHistoricalDemand(),

        'product_demand_forecast' =>
            $this->dashboard->getProductDemandForecast(),

            'annual_forecast' =>
    $this->getAnnualForecastArtifact()

    ];
}

    public function exportSalesHistory()
{
    return $this->dashboard->exportSalesHistory();
}

public function getDemandAnalytics($year = null, $month = null)
{
    return $this->dashboard
        ->getDemandQuantity($year, $month);
}

private function getAnnualForecastArtifact(): array
{
    $projectRoot = dirname(__DIR__, 2);

    $timezone = new DateTimeZone('Asia/Manila');
    $currentYear = (new DateTimeImmutable('now', $timezone))->format('Y');

    $file = $projectRoot
        . DIRECTORY_SEPARATOR . 'sarima_forecasting'
        . DIRECTORY_SEPARATOR . 'outputs'
        . DIRECTORY_SEPARATOR . 'deployment'
        . DIRECTORY_SEPARATOR . 'annual_forecast_' . $currentYear . '.json';

    if (!is_file($file) || filesize($file) === 0) {
        return [];
    }

    $json = file_get_contents($file);

    if ($json === false || trim($json) === '') {
        return [];
    }

    $data = json_decode($json, true);

    return is_array($data) ? $data : [];
}


    
}