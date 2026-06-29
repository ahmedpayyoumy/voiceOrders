<?php

namespace App\Services\Daftra\Resources;

class TimeTrackingResource extends Resource
{
    protected function path(): string { return '/time_tracking'; }
    protected function entityKey(): string { return 'TimeTracking'; }
}
