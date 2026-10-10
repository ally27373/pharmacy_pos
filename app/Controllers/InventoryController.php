<?php

require_once __DIR__ . '/../Models/Inventory.php';

class InventoryController
{
    private Inventory $inventory;

    public function __construct(?Inventory $inventory = null)
    {
        $this->inventory = $inventory ?? new Inventory();
    }

    public function getProducts(
        int $page = 1,
        int $limit = 20,
        string $search = '',
        string $category = '',
        string $type = ''
    ): array {
        return $this->inventory->getProducts($page, $limit, $search, $category, $type);
    }

    public function getProductDetails($id)
    {
        return $this->inventory->getProductDetails($id);
    }

    public function getProductBatches(int $productId): array
    {
        return $this->inventory->getProductBatches($productId);
    }

    public function getCategories(): array
    {
        return $this->inventory->getCategories();
    }

    public function getTypes(): array
    {
        return $this->inventory->getTypes();
    }

    public function getSuppliers(): array
    {
        return $this->inventory->getSuppliers();
    }

    public function saveProduct(array $data): array
    {
        return $this->inventory->saveProduct($data);
    }

    public function adjustStock(array $data): array
    {
        return $this->inventory->adjustStock($data);
    }

    public function updateProduct(array $data): array
    {
        return $this->inventory->updateProduct($data);
    }

    public function archiveProduct(int $productId): array
    {
        return $this->inventory->archiveProduct($productId);
    }

    public function unarchiveProduct(int $productId): array
    {
        return $this->inventory->unarchiveProduct($productId);
    }

    public function deleteArchivedProduct(int $productId): array
    {
        return $this->inventory->deleteArchivedProduct($productId);
    }

    public function cleanupTestProduct(int $productId): array
    {
        return $this->inventory->cleanupTestProduct($productId);
    }
}
