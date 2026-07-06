<?php

namespace App\Services\Daftra\Resources;

class ExpenseResource extends Resource
{
    protected function path(): string
    {
        return '/expenses';
    }

    protected function entityKey(): string
    {
        return 'Expense';
    }
}
