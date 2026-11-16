<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class Activity
{
    public static function log(string $action, string $description, ?Model $subject = null, ?int $projectId = null, array $properties = []): ActivityLog
    {
        return ActivityLog::create([
            'user_id' => auth()->id(),
            'project_id' => $projectId ?? $subject?->getAttribute('project_id'),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'action' => $action,
            'description' => $description,
            // Done from a "Log in as" tab: record which Super admin really did it.
            'properties' => ($properties + array_filter(['impersonated_by' => request()->attributes->get('impersonation')?->impersonator_id])) ?: null,
        ]);
    }
}
