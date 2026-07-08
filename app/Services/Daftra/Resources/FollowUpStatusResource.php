<?php

namespace App\Services\Daftra\Resources;

class FollowUpStatusResource extends Resource
{
    protected function path(): string
    {
        return '/follow_up_statuses';
    }

    public function entityKey(): string
    {
        return 'FollowUpStatus';
    }
}
