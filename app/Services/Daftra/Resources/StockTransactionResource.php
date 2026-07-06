<?php

namespace App\Services\Daftra\Resources;

class StockTransactionResource extends Resource
{
    protected function path(): string
    {
        return '/stock_transactions';
    }

    protected function entityKey(): string
    {
        return 'StockTransaction';
    }
}
