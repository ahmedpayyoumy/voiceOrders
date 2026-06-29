<?php

namespace App\Services\Daftra\Data;

class StoreData extends Data
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $name = null,
        public readonly ?string $address1 = null,
        public readonly ?string $address2 = null,
        public readonly ?string $city = null,
        public readonly ?string $state = null,
        public readonly ?string $postal_code = null,
        public readonly ?string $country_code = null,
        public readonly ?string $phone = null,
        public readonly ?string $email = null,
        public readonly ?bool $is_active = null,
    ) {}

    protected function moduleKey(): string
    {
        return 'Store';
    }
}
