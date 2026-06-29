<?php

namespace App\Services\Daftra\Resources;

class PurchaseRefundResource extends Resource
{
    protected function path(): string { return '/purchase_refunds'; }
    protected function entityKey(): string { return 'PurchaseRefund'; }
}
