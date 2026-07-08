<?php

namespace App\Services\Daftra\Resources;

class WorkOrderResource extends Resource
{
    protected function path(): string
    {
        return '/work_orders';
    }

    public function entityKey(): string
    {
        return 'WorkOrder';
    }
}
