<?php

namespace App\Services\Daftra\Resources;

class NoteResource extends Resource
{
    protected function path(): string { return '/notes'; }
    protected function entityKey(): string { return 'Note'; }
}
