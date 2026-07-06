<?php

namespace App\Services\Daftra\Resources;

class PurchaseInvoiceResource extends Resource
{
    protected function path(): string
    {
        return '/purchase_invoices';
    }

    protected function entityKey(): string
    {
        return 'PurchaseInvoice';
    }
}
