<?php

require_once __DIR__ . '/../Models/InventoryHistory.php';

class InventoryHistoryController
{
    private $historyModel;

    public function __construct(?InventoryHistory $historyModel = null)
    {
        $this->historyModel = $historyModel ?? new InventoryHistory();
    }

    public function getHistory(
        int $page = 1,
        int $limit = 20,
        string $search = '',
        string $action = '',
        string $fromDate = '',
        string $toDate = ''
    ): array
    {
        return $this->historyModel->getHistory(
            $page, $limit, $search, $action, $fromDate, $toDate
        );
    }
}