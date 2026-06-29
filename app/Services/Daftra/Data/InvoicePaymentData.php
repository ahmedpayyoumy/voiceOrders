<?php

namespace App\Services\Daftra\Data;

class InvoicePaymentData extends Data
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?int $invoice_id = null,
        public readonly ?int $client_id = null,
        public readonly ?float $amount = null,
        public readonly ?string $date = null,
        public readonly ?string $payment_method = null,
        public readonly ?int $treasury_id = null,
        public readonly ?string $notes = null,
    ) {}

    protected function moduleKey(): string
    {
        return 'InvoicePayment';
    }
}
