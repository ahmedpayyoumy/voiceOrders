<?php

namespace App\Services\Daftra\Data;

class RequisitionData extends Data
{
    public function __construct(
        public readonly ?int $store_id = null,
        public readonly ?int $type = null,
        public readonly ?int $order_type = null,
        public readonly ?int $to_store_id = null,
        public readonly ?string $date = null,
        public readonly ?string $currency_code = null,
        public readonly ?int $journal_account_id = null,
        public readonly ?string $number = null,
        public readonly ?string $notes = null,
        public readonly ?string $attachment = null,
        public readonly ?int $draft = null,
        public readonly ?int $is_manufacturing_order = null,
        public readonly ?int $is_stock_request = null,
        public readonly ?int $work_order_id = null,
        public readonly ?array $items = null,
    ) {}

    protected function moduleKey(): string
    {
        return 'Requisition';
    }

    public static function fromArray(array $data): static
    {
        if (isset($data['items']) && is_array($data['items'])) {
            $data['items'] = array_map(
                fn ($item) => is_array($item) ? RequisitionItemData::fromArray($item) : $item,
                $data['items'],
            );
        }

        return parent::fromArray($data);
    }

    public function toRequestBody(): array
    {
        $body = parent::toArray();

        unset($body['Requisition']['items']);

        if ($this->items !== null) {
            $body['RequisitionItem'] = array_map(
                fn (RequisitionItemData $item) => $item->toArray()['RequisitionItem'] ?? $item->toArray(),
                $this->items,
            );
        }

        return $body;
    }
}
