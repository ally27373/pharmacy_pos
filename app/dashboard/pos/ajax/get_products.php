<?php

declare(strict_types=1);

/*
| Path depths from /app/dashboard/pos/ajax:
|   ../../../../  -> project root   (config/ lives here)
|   ../../../     -> app/           (Middleware/ and Controllers/ live here)
*/

require_once __DIR__ . '/../../../../config/session.php';
require_once __DIR__ . '/../../../Middleware/AuthMiddleware.php';

header('Content-Type: application/json; charset=utf-8');

/*
| The product catalogue is readable by any signed-in account: ringing up a
| sale is the cashier's job, and the POS screen needs this list. It is still
| not anonymous -- without a guard this leaked the whole catalogue, including
| cost price.
*/
AuthMiddleware::requireAuthenticatedApi();

require_once __DIR__ . '/../../../Controllers/POSController.php';

try {

    $controller = new POSController();

    $result = $controller->getProducts();


    echo json_encode([

        'success' => true,

        'products' =>
            $result['products'] ?? [],

        'pagination' =>
            $result['pagination'] ?? []

    ]);

}
catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([

        'success' => false,

        'message' =>
            'Unable to load products.',

        'error' =>
            $e->getMessage()

    ]);

}