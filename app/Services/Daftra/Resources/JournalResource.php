<?php

namespace App\Services\Daftra\Resources;

class JournalResource extends Resource
{
    protected function path(): string
    {
        return '/journals';
    }

    public function entityKey(): string
    {
        return 'Journal';
    }
}
