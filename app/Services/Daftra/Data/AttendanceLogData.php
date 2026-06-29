<?php

namespace App\Services\Daftra\Data;

class AttendanceLogData extends Data
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?int $client_id = null,
        public readonly ?string $date = null,
        public readonly ?string $check_in = null,
        public readonly ?string $check_out = null,
        public readonly ?string $notes = null,
    ) {}

    protected function moduleKey(): string
    {
        return 'ClientAttendanceLog';
    }
}
