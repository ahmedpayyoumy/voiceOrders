<?php

namespace App\Services\Daftra\Resources;

class JournalAccountResource extends Resource
{
    protected function path(): string
    {
        return '/journal_accounts';
    }

    protected function entityKey(): string
    {
        return 'JournalAccount';
    }
}
