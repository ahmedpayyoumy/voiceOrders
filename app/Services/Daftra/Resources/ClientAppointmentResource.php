<?php

namespace App\Services\Daftra\Resources;

class ClientAppointmentResource extends Resource
{
    protected function path(): string
    {
        return '/client_appointments';
    }

    protected function entityKey(): string
    {
        return 'Appointment';
    }
}
