<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Return-specific permissions: global visibility alone does not permit generation or approval. */
class ReturnAccessService
{
    public function roles(int $userId): array
    {
        return DB::table('cb_role_user as ur')->join('cb_roles as r', 'r.id', '=', 'ur.role_id')
            ->where('ur.user_id', $userId)->pluck('r.name')->map(fn ($name) => strtolower(str_replace(' ', '_', trim($name))))->all();
    }

    public function canApproveDirectly(int $userId): bool
    {
        // The existing accountant role is the Chief Accountant role for this workflow, as configured by the business.
        return (bool) array_intersect($this->roles($userId), ['directors', 'accountant']);
    }

    public function canGenerate(int $userId): bool
    {
        return $this->canApproveDirectly($userId) || in_array('field_officer', $this->roles($userId), true);
    }

    public function canForward(int $userId): bool
    {
        return in_array('field_officer', $this->roles($userId), true);
    }

    public function fieldOfficerOwnsProperty(int $userId, int $propertyId): bool
    {
        return DB::table('sys_pm_properties as p')->where('p.id', $propertyId)
            ->whereIn('p.field_officer_id', DB::table('sys_user_staff_links')->where('user_id', $userId)->select('staff_id'))->exists();
    }

    public function generationRefusal(int $userId, ?int $propertyId): ?string
    {
        if ($this->canApproveDirectly($userId)) return null;
        if (!$this->canForward($userId)) return 'Only field officers, directors and the chief accountant can generate returns.';
        if (!$propertyId) return 'Choose one of your assigned properties to generate its returns.';
        return $this->fieldOfficerOwnsProperty($userId, $propertyId) ? null : 'Only the assigned field officer can generate or forward this property\'s returns.';
    }
}
