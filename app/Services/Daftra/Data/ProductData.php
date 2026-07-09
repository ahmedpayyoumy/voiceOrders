<?php

namespace App\Services\Daftra\Data;

class ProductData extends Data
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $name = null,
        public readonly ?string $sku = null,
        public readonly ?string $description = null,
        public readonly ?float $unit_price = null,
        public readonly ?float $cost = null,
        public readonly ?int $type = null,
        public readonly ?int $track_stock = null,
        public readonly ?int $category_id = null,
        public readonly ?int $store_id = null,
        public readonly ?string $unit = null,
        public readonly ?int $tax1 = null,
        public readonly ?int $tax2 = null,
        public readonly ?bool $is_active = null,
        public readonly ?int $follow_up_status = null,
    ) {}

    public static function fromArray(array $data): static
    {
        if (($data['type'] ?? null) === 1 && ! array_key_exists('track_stock', $data)) {
            $data['track_stock'] = 1;
        }

        return parent::fromArray($data);
    }

    protected function moduleKey(): string
    {
        return 'Product';
    }
}
