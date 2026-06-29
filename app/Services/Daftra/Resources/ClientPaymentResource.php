<?php

namespace App\Services\Daftra\Resources;

class ClientPaymentResource extends Resource
{
    protected function path(): string { return '/client_payments'; }
    protected function entityKey(): string { return 'ClientPayment'; }
}
