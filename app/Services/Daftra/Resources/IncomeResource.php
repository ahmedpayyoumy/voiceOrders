<?php

namespace App\Services\Daftra\Resources;

class IncomeResource extends Resource
{
    protected function path(): string { return '/incomes'; }
    protected function entityKey(): string { return 'Income'; }
}
