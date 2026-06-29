<?php

namespace App\Services\Daftra\Resources;

class StoreResource extends Resource
{
    protected function path(): string { return '/stores'; }
    protected function entityKey(): string { return 'Store'; }
}
