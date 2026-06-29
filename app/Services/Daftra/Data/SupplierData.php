<?php

namespace App\Services\Daftra\Data;

class SupplierData extends Data
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $business_name = null,
        public readonly ?string $first_name = null,
        public readonly ?string $last_name = null,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?string $mobile = null,
        public readonly ?string $address1 = null,
        public readonly ?string $address2 = null,
        public readonly ?string $city = null,
        public readonly ?string $state = null,
        public readonly ?string $postal_code = null,
        public readonly ?string $country_code = null,
        public readonly ?string $notes = null,
        public readonly ?string $tax_number = null,
        public readonly ?int $currency_code = null,
        public readonly ?int $follow_up_status = null,
    ) {}

    protected function moduleKey(): string
    {
        return 'Supplier';
    }
}
