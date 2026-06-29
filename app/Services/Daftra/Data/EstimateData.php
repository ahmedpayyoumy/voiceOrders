<?php

namespace App\Services\Daftra\Data;

class EstimateData extends Data
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?int $client_id = null,
        public readonly ?int $store_id = null,
        public readonly ?string $name = null,
        public readonly ?string $client_business_name = null,
        public readonly ?string $client_first_name = null,
        public readonly ?string $client_last_name = null,
        public readonly ?string $client_email = null,
        public readonly ?string $client_address1 = null,
        public readonly ?string $client_address2 = null,
        public readonly ?string $client_city = null,
        public readonly ?string $client_state = null,
        public readonly ?string $client_postal_code = null,
        public readonly ?string $client_country_code = null,
        public readonly ?string $date = null,
        public readonly ?string $notes = null,
        public readonly ?string $po_number = null,
        public readonly ?bool $draft = null,
        public readonly ?float $discount = null,
        public readonly ?string $currency_code = null,
        public readonly ?int $follow_up_status = null,
        public readonly ?array $items = null,
    ) {}

    protected function moduleKey(): string
    {
        return 'Estimate';
    }

    public function toRequestBody(): array
    {
        $body = parent::toArray();

        if ($this->items !== null) {
            $body['InvoiceItem'] = array_map(
                fn (InvoiceItemData $item) => $item->toArray()['InvoiceItem'] ?? $item->toArray(),
                $this->items,
            );
        }

        return $body;
    }
}
