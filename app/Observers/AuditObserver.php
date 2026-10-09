<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class AuditObserver
{
    public function created(Model $model): void
    {
        $this->record($model, 'created', null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $changes = Arr::except($model->getChanges(), ['updated_at', 'password', 'remember_token']);
        if ($model->wasChanged('password')) {
            $changes['password_changed'] = true;
        }
        if ($changes !== []) {
            $this->record($model, 'updated', Arr::only($model->getRawOriginal(), array_keys($changes)), $changes);
        }
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted', $model->getAttributes(), null);
    }

    private function record(Model $model, string $action, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'table_name' => $model->getTable(),
            'record_id' => $model->getKey(),
            'old_values' => $old === null ? null : Arr::except($old, ['password', 'remember_token']),
            'new_values' => $new === null ? null : Arr::except($new, ['password', 'remember_token']),
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
