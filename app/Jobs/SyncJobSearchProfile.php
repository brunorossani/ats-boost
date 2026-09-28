<?php

namespace App\Jobs;

use App\Actions\Jobs\SyncSearchProfile;
use App\Models\JobSearchProfile;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncJobSearchProfile implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 180;

    public function __construct(public int $profileId) {}

    public function uniqueId(): string
    {
        return (string) $this->profileId;
    }

    public function handle(SyncSearchProfile $sync): void
    {
        $profile = JobSearchProfile::find($this->profileId);

        if ($profile?->is_active && $profile->hasResume()) {
            $sync->handle($profile);
        }
    }
}
