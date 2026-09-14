<?php

require_once '../Controllers/DashboardController.php';

$dashboard = new DashboardController();

$data = $dashboard->exportSalesHistory();

$csvPath = '../../python/data/sales_history.csv';

$file = fopen($csvPath, 'w');

fputcsv($file, [
    'date',
    'product_name',
    'quantity_sold'
]);

foreach ($data as $row)
{
    fputcsv($file, [
        $row['sale_date'],
        $row['product_name'],
        $row['quantity_sold']
    ]);
}

fclose($file);

echo "<h2>✅ Sales History Exported Successfully</h2>";

echo "<p>Total Records: <strong>" . count($data) . "</strong></p>";