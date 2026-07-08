<?php

namespace App\Services\Daftra\Resources;

class InvoicePaymentResource extends Resource
{
    protected function path(): string
    {
        return '/invoice_payments';
    }

    public function entityKey(): string
    {
        return 'InvoicePayment';
    }
}
