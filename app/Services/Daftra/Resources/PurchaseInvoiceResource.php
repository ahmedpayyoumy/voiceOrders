<?php

namespace App\Services\Daftra\Resources;

class PurchaseInvoiceResource extends Resource
{
    protected function path(): string
    {
        return '/purchase_invoices';
    }

    public function entityKey(): string
    {
        return 'PurchaseInvoice';
    }
}
