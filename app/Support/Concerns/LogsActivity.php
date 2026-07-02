<?php

namespace App\Support\Concerns;

use App\Models\ActivityLog;

/**
 * Records a household-scoped activity log entry whenever a model using this
 * trait is created, updated, or deleted through Eloquent. Bulk operations
 * that go through the query builder (mass update/insert) intentionally
 * bypass these events — callers log a single summary entry for those instead
 * (see ImportController::store and CategorizeController::apply).
 */
trait LogsActivity
{
    protected static function bootLogsActivity(): void
    {
        static::created(fn ($model) => $model->recordActivity('created'));
        static::updated(fn ($model) => $model->recordActivity('updated'));
        static::deleted(fn ($model) => $model->recordActivity('deleted'));
    }

    protected function recordActivity(string $action): void
    {
        if (! auth()->check()) {
            return;
        }

        $changes = null;
        if ($action === 'updated') {
            $changes = $this->getChanges();
            unset($changes['updated_at']);
            if (empty($changes)) {
                return;
            }
        }

        ActivityLog::create([
            'household_id' => $this->household_id,
            'user_id' => auth()->id(),
            'subject_type' => static::class,
            'subject_id' => $this->getKey(),
            'action' => $action,
            'summary' => $this->activitySummary($action),
            'changes' => $changes,
        ]);
    }

    protected function activitySummary(string $action): string
    {
        $name = class_basename($this);
        $label = $this->activityLabel();

        return match ($action) {
            'created' => "Added {$name}: {$label}",
            'updated' => "Updated {$name}: {$label}",
            'deleted' => "Removed {$name}: {$label}",
            default => "{$name}: {$label}",
        };
    }
}
