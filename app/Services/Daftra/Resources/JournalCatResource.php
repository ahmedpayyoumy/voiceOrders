<?php

namespace App\Services\Daftra\Resources;

class JournalCatResource extends Resource
{
    protected function path(): string
    {
        return '/journal_cats';
    }

    protected function entityKey(): string
    {
        return 'JournalCat';
    }
}
