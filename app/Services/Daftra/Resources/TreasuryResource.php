<?php

namespace App\Services\Daftra\Resources;

class TreasuryResource extends Resource
{
    protected function path(): string
    {
        return '/treasuries';
    }

    protected function entityKey(): string
    {
        return 'Treasury';
    }
}
