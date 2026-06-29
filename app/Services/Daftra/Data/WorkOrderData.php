<?php

namespace App\Services\Daftra\Data;

class WorkOrderData extends Data
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?int $client_id = null,
        public readonly ?int $staff_id = null,
        public readonly ?int $store_id = null,
        public readonly ?string $subject = null,
        public readonly ?string $description = null,
        public readonly ?string $date = null,
        public readonly ?string $due_date = null,
        public readonly ?string $status = null,
        public readonly ?float $total = null,
        public readonly ?string $notes = null,
        public readonly ?int $follow_up_status = null,
    ) {}

    protected function moduleKey(): string
    {
        return 'WorkOrder';
    }
}
