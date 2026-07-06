<?php

namespace App\Services\Daftra\Resources;

class WorkOrderAppointmentResource extends Resource
{
    protected function path(): string
    {
        return '/work_order_appointments';
    }

    protected function entityKey(): string
    {
        return 'Appointment';
    }
}
