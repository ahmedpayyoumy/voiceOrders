<?php

namespace App\Services\Daftra\Resources;

class NoteResource extends Resource
{
    protected function path(): string
    {
        return '/notes';
    }

    public function entityKey(): string
    {
        return 'Note';
    }
}
