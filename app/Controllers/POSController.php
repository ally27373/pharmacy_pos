<?php

require_once __DIR__ . '/../Models/POS.php';

class POSController
{
    private POS $pos;

    public function __construct(?POS $pos = null)
    {
        $this->pos = $pos ?? new POS();
    }

    /*
    |--------------------------------------------------------------------------
    | GET PRODUCTS
    |--------------------------------------------------------------------------
    |
    | Supports:
    |
    | ?page=1
    | ?limit=20
    | ?search=amoxicillin
    | ?category=Antibiotics
    | ?type=Tablet
    |
    */

    public function getProducts(): array
    {
        /*
        |--------------------------------------------------------------------------
        | PAGINATION
        |--------------------------------------------------------------------------
        */

        $page = isset($_GET['page'])
            ? (int) $_GET['page']
            : 1;

        $limit = isset($_GET['limit'])
            ? (int) $_GET['limit']
            : 20;


        /*
        |--------------------------------------------------------------------------
        | FILTERS
        |--------------------------------------------------------------------------
        */

        $search =
            isset($_GET['search'])
                ? trim((string) $_GET['search'])
                : '';

        $category =
            isset($_GET['category'])
                ? trim((string) $_GET['category'])
                : '';

        $type =
            isset($_GET['type'])
                ? trim((string) $_GET['type'])
                : '';


        /*
        |--------------------------------------------------------------------------
        | NORMALIZE PAGINATION
        |--------------------------------------------------------------------------
        */

        $page = max(
            1,
            $page
        );

        $limit = min(
            max(1, $limit),
            100
        );


        /*
        |--------------------------------------------------------------------------
        | FETCH PRODUCTS
        |--------------------------------------------------------------------------
        */

        return $this->pos->getAvailableProducts(
            $page,
            $limit,
            $search,
            $category,
            $type
        );
    }


    /*
    |--------------------------------------------------------------------------
    | PROCESS SALE
    |--------------------------------------------------------------------------
    */

    public function processSale()
    {
        $data =
            json_decode(
                file_get_contents("php://input"),
                true
            );


        if (!$data) {

            http_response_code(400);

            echo json_encode([

                "success" => false,

                "message" =>
                    "Invalid request."

            ]);

            return;
        }


        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $data['cashier_id'] = (int) ($_SESSION['user_id'] ?? 0);

        $result =
            $this->pos->processSale(
                $data
            );


        echo json_encode(
            $result
        );
    }

    
public function getCategories(): array
{
    return $this->pos->getCategories();
}


public function getProductTypes(): array
{
    return $this->pos->getProductTypes();
}


}