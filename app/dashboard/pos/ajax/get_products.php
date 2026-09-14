<?php

header('Content-Type: application/json');

require_once '../../../Controllers/POSController.php';

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