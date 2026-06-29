<?php

namespace App\Services\Daftra\Data;

class AppointmentData extends Data
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?int $model_id = null,
        public readonly ?int $staff_id = null,
        public readonly ?string $title = null,
        public readonly ?string $description = null,
        public readonly ?string $date = null,
        public readonly ?string $time = null,
        public readonly ?string $status = null,
        public readonly ?string $notes = null,
    ) {}

    protected function moduleKey(): string
    {
        return 'Appointment';
    }
}
