<?php

namespace App\Services\Daftra\Resources;

class RefundReceiptResource extends Resource
{
    protected function path(): string
    {
        return '/refund_receipts';
    }

    public function entityKey(): string
    {
        return 'RefundReceipt';
    }
}
