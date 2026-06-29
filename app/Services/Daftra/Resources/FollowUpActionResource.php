<?php

namespace App\Services\Daftra\Resources;

class FollowUpActionResource extends Resource
{
    protected function path(): string { return '/follow_up_actions'; }
    protected function entityKey(): string { return 'FollowUpAction'; }
}
