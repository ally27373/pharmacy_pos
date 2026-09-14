<?php

require_once __DIR__ . '/Models/SalesImport.php';

try {

    $salesImport = new SalesImport();

    $product = $salesImport->findProduct(
        'PARACETAMOL 500MG'
    );

    echo '<pre>';

    if ($product) {

        print_r($product);

    } else {

        echo "Product not found.";

    }

    echo '</pre>';

} catch (Throwable $e) {

    echo '<pre>';

    echo "ERROR:\n";
    echo $e->getMessage();

    echo '</pre>';
}