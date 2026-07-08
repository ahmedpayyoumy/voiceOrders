<?php

namespace App\Services\Daftra\Resources;

class StaffResource extends Resource
{
    protected function path(): string
    {
        return '/staff';
    }

    public function entityKey(): string
    {
        return 'Staff';
    }
}
