<?php

namespace App\Services\Daftra\Resources;

class AttendanceLogResource extends Resource
{
    protected function path(): string { return '/client_attendance_logs'; }
    protected function entityKey(): string { return 'ClientAttendanceLog'; }
}
