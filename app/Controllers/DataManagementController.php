<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/DataManagement.php';

class DataManagementController
{
    private DataManagement $dataManagement;

    public function __construct(?DataManagement $dataManagement = null)
    {
        $this->dataManagement = $dataManagement ?? new DataManagement();
    }

    public function exportInventory(): array
    {
        return $this->dataManagement->getInventoryForExport();
    }

    public function exportSales(): array
    {
        return $this->dataManagement->getSalesForExport();
    }
}