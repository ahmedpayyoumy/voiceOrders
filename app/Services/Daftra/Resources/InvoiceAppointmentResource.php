<?php

namespace App\Services\Daftra\Resources;

class InvoiceAppointmentResource extends Resource
{
    protected function path(): string
    {
        return '/invoice_appointments';
    }

    public function entityKey(): string
    {
        return 'Appointment';
    }
}
