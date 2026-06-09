<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Models\Application;
use App\Models\StatusLog;
use App\Models\User;

class StatusLogService
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function record(
        Application $application,
        User $actor,
        string $action,
        ?ApplicationStatus $from,
        ApplicationStatus $to,
        string $message,
        array $metadata = [],
    ): StatusLog {
        return StatusLog::query()->create([
            'application_id' => $application->id,
            'actor_user_id' => $actor->id,
            'actor_role' => $actor->role instanceof UserRole ? $actor->role->value : $actor->role,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'message' => $message,
            'metadata' => [
                'action' => $action,
                ...$metadata,
            ],
        ]);
    }
}
