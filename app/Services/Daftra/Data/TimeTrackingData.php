<?php

namespace App\Services\Daftra\Data;

class TimeTrackingData extends Data
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?int $staff_id = null,
        public readonly ?int $work_order_id = null,
        public readonly ?float $hours = null,
        public readonly ?string $date = null,
        public readonly ?string $description = null,
        public readonly ?float $rate = null,
    ) {}

    protected function moduleKey(): string
    {
        return 'TimeTracking';
    }
}
