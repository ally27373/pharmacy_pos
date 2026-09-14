<?php

declare(strict_types=1);

require_once __DIR__ . '/../Models/Reports.php';

class ReportsController
{
    private Reports $reports;

    public function __construct()
    {
        $this->reports = new Reports();
    }

    public function getReportData(array $filters = []): array
    {
        return $this->reports->getReportData($filters);
    }

    public function getExportRows(array $filters = []): array
    {
        return $this->reports->getExportRows($filters);
    }

    public function getCategories(): array
    {
        return $this->reports->getCategories();
    }

    public function getTypes(): array
    {
        return $this->reports->getTypes();
    }
}
