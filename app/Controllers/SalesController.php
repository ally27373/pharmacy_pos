<?php

require_once __DIR__ . '/../Models/Sales.php';

class SalesController
{
    private Sales $sales;

    public function __construct()
    {
        $this->sales = new Sales();
    }

    public function getTransactions(
        int $page = 1,
        int $limit = 20,
        string $search = ''
    ): array {
        return $this->sales->getAllSales($page, $limit, $search);
    }

    public function getSaleDetails(int $saleId)
    {
        return $this->sales->getSaleDetails($saleId);
    }
}
