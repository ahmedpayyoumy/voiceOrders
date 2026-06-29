<?php

namespace App\Services\Daftra\Resources;

class JournalResource extends Resource
{
    protected function path(): string { return '/journals'; }
    protected function entityKey(): string { return 'Journal'; }
}
