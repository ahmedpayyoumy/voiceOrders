<?php

namespace App\Services\Daftra\Resources;

class RequisitionResource extends Resource
{
    protected function path(): string
    {
        return '/requisitions';
    }

    public function entityKey(): string
    {
        return 'Requisition';
    }
}
