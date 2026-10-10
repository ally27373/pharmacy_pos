<?php

/*
| These are CLI diagnostics kept alongside the app they exercise. Serving
| them from the document root let any visitor trigger a live SARIMA refresh
| or dump forecast internals and database structure in a PHP error page.
| Refuse every non-CLI SAPI, and answer 404 so the path is not advertised.
*/
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}


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
