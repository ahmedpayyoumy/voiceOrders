<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:reset-user-quotas')]
#[Description('Reset monthly usage quotas for users whose quota reset date has passed.')]
class ResetUserQuotas extends Command
{
    public function handle()
    {
        $now = now();

        $count = User::where('quota_reset_at', '<=', $now)
            ->where('used_this_month', '>', 0)
            ->update([
                'used_this_month' => 0,
                'quota_reset_at' => null,
            ]);

        $this->info("Reset quotas for {$count} users.");

        return static::SUCCESS;
    }
}
