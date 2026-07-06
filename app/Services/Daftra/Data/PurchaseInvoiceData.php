<?php

namespace App\Services\Daftra\Data;

class PurchaseInvoiceData extends Data
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?int $supplier_id = null,
        public readonly ?int $store_id = null,
        public readonly ?string $date = null,
        public readonly ?string $notes = null,
        public readonly ?string $po_number = null,
        public readonly ?float $discount = null,
        public readonly ?string $currency_code = null,
        public readonly ?array $items = null,
    ) {}

    protected function moduleKey(): string
    {
        return 'PurchaseInvoice';
    }

    public static function fromArray(array $data): static
    {
        if (isset($data['items']) && is_array($data['items'])) {
            $data['items'] = array_map(
                fn ($item) => is_array($item) ? PurchaseInvoiceItemData::fromArray($item) : $item,
                $data['items'],
            );
        }

        return parent::fromArray($data);
    }

    public function toRequestBody(): array
    {
        $body = parent::toArray();

        if ($this->items !== null) {
            $body['InvoiceItem'] = array_map(
                fn (PurchaseInvoiceItemData $item) => $item->toArray()['InvoiceItem'] ?? $item->toArray(),
                $this->items,
            );
        }

        return $body;
    }
}
