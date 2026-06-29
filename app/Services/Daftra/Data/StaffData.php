<?php

namespace App\Services\Daftra\Data;

class StaffData extends Data
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $first_name = null,
        public readonly ?string $last_name = null,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?string $role = null,
        public readonly ?float $salary = null,
        public readonly ?string $hire_date = null,
        public readonly ?bool $is_active = null,
        public readonly ?string $notes = null,
    ) {}

    protected function moduleKey(): string
    {
        return 'Staff';
    }
}
