<?php

namespace App\Services\Daftra\Resources;

class EstimateResource extends Resource
{
    protected function path(): string { return '/estimates'; }
    protected function entityKey(): string { return 'Estimate'; }
}
