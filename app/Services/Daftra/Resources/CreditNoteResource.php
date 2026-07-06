<?php

namespace App\Services\Daftra\Resources;

class CreditNoteResource extends Resource
{
    protected function path(): string
    {
        return '/credit_notes';
    }

    protected function entityKey(): string
    {
        return 'CreditNote';
    }
}
