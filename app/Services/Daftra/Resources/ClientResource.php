<?php

namespace App\Services\Daftra\Resources;

class ClientResource extends Resource
{
    protected function path(): string { return '/clients'; }
    protected function entityKey(): string { return 'Client'; }
}
