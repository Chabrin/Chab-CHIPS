<?php

namespace App\Services;

use App\Models\TenantReviewWorkflowVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TenantReviewWorkflowService
{
    public function canManage(User $user): bool
    {
        return !$user->block && $user->hasPermission('update_approval_workflows');
    }

    public function current(): TenantReviewWorkflowVersion
    {
        if (!Schema::hasTable('tenant_review_workflow_versions')) {
            throw ValidationException::withMessages(['workflow' => 'Apply the flexible tenant approval migration first.']);
        }
        $id = DB::table('tenant_review_workflows')->where('id', 1)->value('published_version_id');
        return TenantReviewWorkflowVersion::findOrFail($id);
    }

    public function defaultSteps(): array
    {
        $steps = [];
        foreach (['field_officer', 'zone_manager', 'assistant_internal_auditor'] as $stage) {
            $label = config('tenant_review.roles.'.$stage);
            $role = DB::table('cb_roles')->where('name', $label)->orWhere('display_name', $label)->first();
            if (!$role) throw new \RuntimeException('Missing tenant approval role '.$label);
            $steps[] = ['stage' => $stage, 'role_id' => (int) $role->id, 'label' => $label,
                'scope' => $stage === 'assistant_internal_auditor' ? 'role' : $stage];
        }
        return $steps;
    }

    public function publish(array $roleIds, int $expectedVersion, User $user): TenantReviewWorkflowVersion
    {
        abort_unless($this->canManage($user), 403);
        if (!$roleIds || count($roleIds) > 11) {
            throw ValidationException::withMessages(['steps' => 'Choose between one and eleven approval steps after the Field Officer.']);
        }
        $steps = [$this->current()->steps[0]];
        foreach (array_values($roleIds) as $index => $roleId) {
            $role = DB::table('cb_roles')->find($roleId);
            if (!$role) throw ValidationException::withMessages(['steps' => 'Choose an existing approver role for each step.']);
            $zoneRole = config('tenant_review.roles.zone_manager');
            $isZone = $role->name === $zoneRole || $role->display_name === $zoneRole;
            $steps[] = ['stage' => 'approval_'.($index + 1), 'role_id' => (int) $role->id,
                'label' => $role->display_name ?: $role->name, 'scope' => $isZone ? 'zone_manager' : 'role'];
        }
        return DB::transaction(function () use ($steps, $expectedVersion, $user) {
            $head = DB::table('tenant_review_workflows')->where('id', 1)->lockForUpdate()->first();
            if (!$head || (int) $head->published_version_id !== $expectedVersion) {
                throw ValidationException::withMessages(['version' => 'The setup changed. Refresh before publishing.']);
            }
            $number = (int) TenantReviewWorkflowVersion::max('number') + 1;
            $version = TenantReviewWorkflowVersion::create(['number' => $number, 'steps' => $steps,
                'published_by' => $user->id, 'created_at' => now()]);
            DB::table('tenant_review_workflows')->where('id', 1)->update(['published_version_id' => $version->id]);
            return $version;
        });
    }

    public function roleIds(User $user): array
    {
        return DB::table('cb_role_user')->where('user_id', $user->id)->pluck('role_id')->map(fn ($id) => (int) $id)->all();
    }

    public function eligibleVersions(User $user, string $scope): array
    {
        $roles = $this->roleIds($user);
        return TenantReviewWorkflowVersion::get()->filter(function ($version) use ($roles, $scope) {
            return collect(array_slice($version->steps, 1))->contains(fn ($step) =>
                $step['scope'] === $scope && in_array((int) $step['role_id'], $roles, true));
        })->pluck('id')->all();
    }

    public function participates(User $user): bool
    {
        return !$user->block && ($this->eligibleVersions($user, 'role') || $this->eligibleVersions($user, 'zone_manager'));
    }
}
