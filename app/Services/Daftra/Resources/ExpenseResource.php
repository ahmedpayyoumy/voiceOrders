<?php

namespace App\Services\Daftra\Resources;

class ExpenseResource extends Resource
{
    protected function path(): string
    {
        return '/expenses';
    }

    public function entityKey(): string
    {
        return 'Expense';
    }
}
