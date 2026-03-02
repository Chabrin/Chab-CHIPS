<?php

namespace App\Observers;

use App\Models\AuditLogs;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;

class AuditLogsObserver
{


    public function created($model)
    {
        if ($this->shouldLogModel($model)) {
            $table = $model->getTable();
            $newData = collect($model->getAttributes())->except($this->getExcludedColumns($table))->toArray();

            AuditLogs::create([
                'table_name' => $table,
                'record_id' => $this->getPrimaryKeyValue($model),
                'operation' => 'create',
                'new_data' => $newData,
                'user_id' => Auth::id() ?? null,
            ]);
        }
    }


    public function updated($model)
    {
        $dirtyData = $model->getDirty();
        $table = $model->getTable();
        $excluded = $this->getExcludedColumns($table);

        $filteredDirty = collect($dirtyData)->except($excluded)->toArray();
        $oldData = $this->getChangedOriginals($model, $filteredDirty);

        if ($this->shouldLogModel($model) && !empty($filteredDirty)) {
            AuditLogs::create([
                'table_name' => $table,
                'record_id' => $this->getPrimaryKeyValue($model),
                'operation' => 'update',
                'old_data' => $oldData,
                'new_data' => $filteredDirty,
                'user_id' => Auth::id() ?? null,
            ]);
        }
    }


    public function deleted($model)
    {
        if ($this->shouldLogModel($model)) {
            $table = $model->getTable();
            $oldData = collect($model->getAttributes())->except($this->getExcludedColumns($table))->toArray();

            AuditLogs::create([
                'table_name' => $table,
                'record_id' => $this->getPrimaryKeyValue($model),
                'operation' => 'delete',
                'old_data' => $oldData,
                'user_id' => Auth::id() ?? null,
            ]);
        }
    }


    /**
     * Get the primary key value of the model, whether it's 'id' or 'member_no' or something else.
     */
    protected function getPrimaryKey($model)
    {
        return $model->getKeyName();
    }

    protected function shouldLogModel($model)
    {
        $excludedTables = ['um_failed_jobs', 'um_sessions', 'um_jobs', 'um_activity_logs', 'um_password_resets', 'um_error_logs', 'um_email_logs'];

        if (in_array($model->getTable(), $excludedTables)) {
            return false;
        }

        if ($model instanceof \App\Models\User || $model instanceof \Illuminate\Session\SessionManager) {
            return false;
        }

        return true;
    }

    protected function getExcludedColumns($table)
    {
        return [
            'um_users' => ['password']
        ][$table] ?? [];
    }

    protected function getPrimaryKeyValue(Model $model)
    {
        $primaryKey = $model->getKeyName();
        return $model->$primaryKey;
    }

    protected function getChangedOriginals($model, $dirtyData)
    {
        $oldData = [];

        foreach ($dirtyData as $field => $newValue) {
            $oldData[$field] = $model->getOriginal($field);
        }
        return $oldData;
    }
}
