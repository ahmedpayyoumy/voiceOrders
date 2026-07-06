<?php

namespace App\Services\Daftra\Data;

class InvoiceData extends Data
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
        public readonly ?string $issue_date = null,
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
        return 'Invoice';
    }

    public static function fromArray(array $data): static
    {
        if (isset($data['items']) && is_array($data['items'])) {
            $data['items'] = array_map(
                fn ($item) => is_array($item) ? InvoiceItemData::fromArray($item) : $item,
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
                fn (InvoiceItemData $item) => $item->toArray()['InvoiceItem'] ?? $item->toArray(),
                $this->items,
            );
        }

        if ($this->issue_date !== null) {
            $body['Invoice']['date'] = $this->issue_date;
            unset($body['Invoice']['issue_date']);
        }

        return $body;
    }
}
