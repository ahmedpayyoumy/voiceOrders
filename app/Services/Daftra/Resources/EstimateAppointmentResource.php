<?php

namespace App\Services\Daftra\Resources;

class EstimateAppointmentResource extends Resource
{
    protected function path(): string
    {
        return '/estimate_appointments';
    }

    protected function entityKey(): string
    {
        return 'Appointment';
    }
}
