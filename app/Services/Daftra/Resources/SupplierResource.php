<?php

namespace App\Services\Daftra\Resources;

class SupplierResource extends Resource
{
    protected function path(): string { return '/suppliers'; }
    protected function entityKey(): string { return 'Supplier'; }
}
