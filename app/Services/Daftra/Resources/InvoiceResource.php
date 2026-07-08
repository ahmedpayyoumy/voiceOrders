<?php

namespace App\Services\Daftra\Resources;

class InvoiceResource extends Resource
{
    protected function path(): string
    {
        return '/invoices';
    }

    public function entityKey(): string
    {
        return 'Invoice';
    }
}
