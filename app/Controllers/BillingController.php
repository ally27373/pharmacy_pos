<?php

require_once __DIR__ . '/../Models/Billings.php';

class BillingController
{
    private Billing $billing;

    public function __construct(?Billing $billing = null)
    {
        $this->billing = $billing ?? new Billing();
    }

    public function getBillings(
        int $page = 1,
        int $limit = 20,
        string $search = ''
    ): array {
        return $this->billing->getAllBillings($page, $limit, $search);
    }

    public function getBillingDetails(int $paymentId)
    {
        return $this->billing->getBillingDetails($paymentId);
    }
}
