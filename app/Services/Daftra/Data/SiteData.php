<?php

namespace App\Services\Daftra\Data;

class SiteData extends Data
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?string $address = null,
        public readonly ?string $currency = null,
        public readonly ?string $timezone = null,
        public readonly ?string $tax_number = null,
        public readonly ?string $logo_url = null,
    ) {}

    protected function moduleKey(): string
    {
        return 'Site';
    }
}
