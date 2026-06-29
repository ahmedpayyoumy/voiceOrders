<?php

namespace App\Services\Daftra\Data;

class PurchaseInvoiceItemData extends Data
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?int $invoice_id = null,
        public readonly ?string $item = null,
        public readonly ?string $description = null,
        public readonly ?float $unit_price = null,
        public readonly ?int $quantity = null,
        public readonly ?int $product_id = null,
        public readonly ?int $tax1 = null,
        public readonly ?int $tax2 = null,
    ) {}

    protected function moduleKey(): string
    {
        return 'InvoiceItem';
    }
}
