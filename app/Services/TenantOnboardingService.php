<?php

namespace App\Services;

use App\Models\Property;
use App\Models\TenantOnboarding;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TenantOnboardingService
{
    public function enabled(): bool
    {
        return (bool) config('tenant_onboarding.enabled');
    }

    public function reviewers(TenantOnboarding $submission, ?string $stage = null): array
    {
        $stage ??= $submission->stage;
        $role = config('tenant_onboarding.roles.'.$stage);
        if (!$role) {
            return [];
        }
        $users = DB::table('josya_users as u')
            ->join('cb_role_user as ru', 'ru.user_id', '=', 'u.id')
            ->join('cb_roles as r', 'r.id', '=', 'ru.role_id')
            ->where('u.block', 0)
            ->where(fn ($q) => $q->where('r.name', $role)->orWhere('r.display_name', $role));

        if (in_array($stage, ['field_officer', 'zone_manager'], true)) {
            $property = DB::table('sys_pm_properties')->find($submission->property_id);
            $staffId = $stage === 'field_officer'
                ? $property?->field_officer_id
                : DB::table('sys_set_zones')->where('id', $property?->zone_id)->value('zone_manager_id');
            if (!$staffId) {
                return [];
            }
            $users->whereExists(fn ($q) => $q->selectRaw('1')->from('sys_user_staff_links as link')
                ->whereColumn('link.user_id', 'u.id')->where('link.staff_id', $staffId));
        }

        if ($stage !== 'field_officer') {
            $previous = DB::table('tenant_onboarding_events')->where('submission_id', $submission->id)
                ->where('revision', $submission->revision)->where('action', 'signed')->pluck('actor_id');
            $users->whereNotIn('u.id', $previous->push($submission->prepared_by)->unique());
        }
        return $users->distinct()->pluck('u.name', 'u.id')->all();
    }

    public function canView(TenantOnboarding $submission, User $user): bool
    {
        if ($submission->prepared_by === (int) $user->id || $user->hasPermission('update_approval_workflows')) {
            return true;
        }
        if (DB::table('tenant_onboarding_events')->where('submission_id', $submission->id)->where('actor_id', $user->id)->exists()) {
            return true;
        }
        return array_key_exists($user->id, $this->reviewers($submission));
    }

    public function visibleSubmissions(User $user)
    {
        $query = TenantOnboarding::query();
        if ($user->hasPermission('update_approval_workflows')) {
            return $query;
        }
        $roles = DB::table('cb_roles as r')->join('cb_role_user as ru', 'ru.role_id', '=', 'r.id')
            ->where('ru.user_id', $user->id)->get(['r.name', 'r.display_name']);
        $roleNames = $roles->pluck('name')->merge($roles->pluck('display_name'))->all();
        $staffIds = DB::table('sys_user_staff_links')->where('user_id', $user->id)->pluck('staff_id');
        return $query->where(function ($visible) use ($user, $roleNames, $staffIds) {
            $visible->where('prepared_by', $user->id)
                ->orWhereExists(fn ($event) => $event->selectRaw('1')->from('tenant_onboarding_events')
                    ->whereColumn('submission_id', 'tenant_onboarding_submissions.id')->where('actor_id', $user->id));
            foreach (array_keys(TenantOnboarding::STAGES) as $stage) {
                if (!in_array(config('tenant_onboarding.roles.'.$stage), $roleNames, true)) {
                    continue;
                }
                $visible->orWhere(function ($assigned) use ($stage, $staffIds) {
                    $assigned->where('stage', $stage)->where('state', 'pending');
                    if ($stage === 'field_officer') {
                        $assigned->whereExists(fn ($property) => $property->selectRaw('1')->from('sys_pm_properties as p')
                            ->whereColumn('p.id', 'tenant_onboarding_submissions.property_id')->whereIn('p.field_officer_id', $staffIds));
                    } elseif ($stage === 'zone_manager') {
                        $assigned->whereExists(fn ($zone) => $zone->selectRaw('1')->from('sys_pm_properties as p')
                            ->join('sys_set_zones as z', 'z.id', '=', 'p.zone_id')
                            ->whereColumn('p.id', 'tenant_onboarding_submissions.property_id')->whereIn('z.zone_manager_id', $staffIds));
                    }
                });
            }
        });
    }

    public function canAct(TenantOnboarding $submission, User $user): bool
    {
        return $submission->state === 'pending' && array_key_exists($user->id, $this->reviewers($submission));
    }

    public function assertUnitAvailable(Unit $unit, ?int $submissionId = null): void
    {
        if ($unit->activeLeaseAgreement()->exists()) {
            throw ValidationException::withMessages(['unit_id' => 'This unit already has a tenancy.']);
        }
        $reservation = TenantOnboarding::where('reserved_unit_id', $unit->id);
        if ($submissionId) {
            $reservation->where('id', '<>', $submissionId);
        }
        if ($reservation->exists()) {
            throw ValidationException::withMessages(['unit_id' => 'This unit is reserved by another pending tenant submission.']);
        }
    }

    public function submit(array $payload, array $documents, User $user, ?TenantOnboarding $existing = null, ?int $expectedRevision = null, ?int $batchTenantId = null): TenantOnboarding
    {
        abort_unless($this->enabled(), 404);
        return DB::transaction(function () use ($payload, $documents, $user, $existing, $expectedRevision, $batchTenantId) {
            Property::findOrFail($payload['property_id']);
            if ($existing) {
                $submission = TenantOnboarding::lockForUpdate()->findOrFail($existing->id);
                abort_unless($submission->prepared_by === (int) $user->id && $submission->state === 'changes_requested', 403);
                $this->checkVersion($submission, $expectedRevision);
                $submission->revision++;
            } else {
                $submission = new TenantOnboarding(['prepared_by' => $user->id, 'revision' => 1]);
            }
            $unit = Unit::where('property_id', $payload['property_id'])->lockForUpdate()->findOrFail($payload['unit_id']);
            $this->assertUnitAvailable($unit, $existing?->id);
            $submission->fill([
                'property_id' => $unit->property_id, 'unit_id' => $unit->id,
                'reserved_unit_id' => $unit->id, 'state' => 'pending', 'stage' => 'field_officer',
                'payload' => $payload, 'documents' => $documents, 'batch_tenant_id' => $batchTenantId ?? $submission->batch_tenant_id,
            ])->save();
            $this->record($submission, $user, 'submitted', null, true);
            // Submission by the assigned Field Officer is their explicit sign-off.
            if (array_key_exists($user->id, $this->reviewers($submission))) {
                $this->record($submission, $user, 'signed');
                $submission->update(['stage' => 'zone_manager']);
            }
            return $submission;
        });
    }

    public function decide(TenantOnboarding $submission, User $user, int $revision, string $stage, string $action, ?string $comment): TenantOnboarding
    {
        abort_unless($this->enabled(), 404);
        return DB::transaction(function () use ($submission, $user, $revision, $stage, $action, $comment) {
            $submission = TenantOnboarding::lockForUpdate()->findOrFail($submission->id);
            $this->checkVersion($submission, $revision, $stage);
            abort_unless($this->canAct($submission, $user), 403);
            if (in_array($action, ['return', 'reject'], true)) {
                if (!trim((string) $comment)) {
                    throw ValidationException::withMessages(['comment' => 'Give a reason so the Field Officer knows what needs attention.']);
                }
                $this->record($submission, $user, $action, $comment);
                $submission->state = $action === 'return' ? 'changes_requested' : 'rejected';
                if ($action === 'reject') {
                    $submission->reserved_unit_id = null;
                }
                $submission->save();
                return $submission;
            }
            abort_unless($action === 'sign' && $stage !== 'assistant_auditor', 422);
            $this->record($submission, $user, 'signed', $comment);
            $stages = array_keys(TenantOnboarding::STAGES);
            $submission->update(['stage' => $stages[array_search($stage, $stages, true) + 1]]);
            return $submission;
        });
    }

    public function effect(TenantOnboarding $submission, User $user, int $revision, callable $createTenancy): TenantOnboarding
    {
        abort_unless($this->enabled(), 404);
        return DB::transaction(function () use ($submission, $user, $revision, $createTenancy) {
            $submission = TenantOnboarding::lockForUpdate()->findOrFail($submission->id);
            $this->checkVersion($submission, $revision, 'assistant_auditor');
            abort_unless($this->canAct($submission, $user), 403);
            $signed = DB::table('tenant_onboarding_events')->where('submission_id', $submission->id)
                ->where('revision', $revision)->where('action', 'signed')->pluck('stage')->unique()->all();
            abort_unless(count(array_intersect(['field_officer', 'zone_manager', 'internal_auditor'], $signed)) === 3, 409);
            $unit = Unit::where('property_id', $submission->property_id)->lockForUpdate()->findOrFail($submission->unit_id);
            $this->assertUnitAvailable($unit, $submission->id);
            $payload = $submission->payload;
            if ((float) $unit->rent_amount !== (float) $payload['rent_amount']) {
                throw ValidationException::withMessages(['unit_id' => 'The unit rent changed during review. Return the submission for correction and fresh signatures.']);
            }
            $sla = DB::table('sys_pm_service_agreements')->where('id', $payload['sla_ref'])
                ->where('property_id', $submission->property_id)->where('current_status_id', 5)->first();
            if (!$sla) {
                throw ValidationException::withMessages(['sla_ref' => 'The property service agreement must be approved before effecting.']);
            }
            $effectedAt = now();
            $result = $createTenancy($submission, $effectedAt);
            $submission->fill($result + [
                'state' => 'effected', 'effected_by' => $user->id, 'effected_at' => $effectedAt,
                'reserved_unit_id' => null,
            ])->save();
            $this->record($submission, $user, 'effected');
            return $submission;
        });
    }

    private function checkVersion(TenantOnboarding $submission, ?int $revision, ?string $stage = null): void
    {
        if ($submission->revision !== $revision || ($stage && $submission->stage !== $stage) || $submission->state === 'effected') {
            throw ValidationException::withMessages(['revision' => 'This submission has changed. Refresh the page before acting.']);
        }
    }

    private function record(TenantOnboarding $submission, User $user, string $action, ?string $comment = null, bool $snapshot = false): void
    {
        DB::table('tenant_onboarding_events')->insert([
            'submission_id' => $submission->id, 'revision' => $submission->revision,
            'stage' => $submission->stage, 'action' => $action, 'actor_id' => $user->id,
            'actor_name' => $user->name, 'comment' => $comment,
            'snapshot' => $snapshot ? Crypt::encryptString(json_encode(['payload' => $submission->payload, 'documents' => $submission->documents])) : null,
            'created_at' => now(),
        ]);
    }
}
