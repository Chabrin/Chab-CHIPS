<?php

namespace App\Services;

use App\Models\Client;
use App\Models\LeaseAgreement;
use App\Models\Property;
use App\Models\TenantReview;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TenantReviewService
{
    public function directorApprovalAccess(User $user): bool
    {
        return $this->hasRole($user, 'directors')
            && TenantReviewAccess::allows($user, 'view_tenant_onboarding');
    }

    public function canDirectApprove(TenantReview $review, User $user): bool
    {
        return $this->directorApprovalAccess($user) && $review->currentStep() !== null;
    }

    public function hasRole(User $user, array|string $names): bool
    {
        return !$user->block && DB::table('cb_role_user as ru')
            ->join('cb_roles as r', 'r.id', '=', 'ru.role_id')
            ->where('ru.user_id', $user->id)
            ->where(fn ($q) => $q->whereIn('r.name', (array) $names)->orWhereIn('r.display_name', (array) $names))
            ->exists();
    }

    public function reviewer(TenantReview $review, User $user): bool
    {
        if (!TenantReviewAccess::allows($user, 'view_tenant_onboarding') || !TenantReviewAccess::allows($user, 'review_tenant_onboarding')) return false;
        $step = $review->currentStep();
        if (!$step || (int) $review->prepared_by === (int) $user->id
            || !$this->matchesStep($review, $user, $step)) {
            return false;
        }
        return !$review->events()->where('revision', $review->revision)
            ->where('action', 'signed')->where('actor_id', $user->id)->exists();
    }

    private function matchesStep(TenantReview $review, User $user, array $step): bool
    {
        if ($user->block || !DB::table('cb_role_user')->where('user_id', $user->id)->where('role_id', $step['role_id'])->exists()) return false;
        if ($step['scope'] === 'role') return true;
        if ($step['scope'] !== 'zone_manager') return false;
        $property = DB::table('sys_pm_properties')->find($review->lease->property_id);
        $manager = DB::table('sys_set_zones')->where('id', $property?->zone_id)->value('zone_manager_id');

        return $manager && DB::table('sys_user_staff_links')->where('user_id', $user->id)->where('staff_id', $manager)->exists();
    }

    public function accounts(User $user): bool
    {
        return $this->hasRole($user, config('tenant_review.roles.accounts'));
    }

    public function canReconcile(User $user): bool
    {
        return $this->accounts($user) && TenantReviewAccess::allows($user, 'view_tenant_onboarding')
            && TenantReviewAccess::allows($user, 'reconcile_tenant_onboarding', TenantReviewAccess::ACCOUNTS);
    }

    public function canView(TenantReview $review, User $user): bool
    {
        if (!TenantReviewAccess::allows($user, 'view_tenant_onboarding')) return false;
        if ((int) $review->prepared_by === (int) $user->id || $this->accounts($user) || $this->directorApprovalAccess($user)) return true;
        return collect(array_slice($review->steps(), 1))->contains(fn ($step) => $this->matchesStep($review, $user, $step));
    }

    public function register(Client $tenant, LeaseAgreement $lease, User $user): TenantReview
    {
        $this->assertPreparer((int) $lease->property_id, $user);
        $version = app(TenantReviewWorkflowService::class)->current();
        $review = TenantReview::create(['tenant_id' => $tenant->id, 'lease_id' => $lease->id,
            'prepared_by' => $user->id, 'stage' => $version->steps[1]['stage'], 'revision' => 1,
            'workflow_version_id' => $version->id]);
        $this->record($review, $user, 'saved_and_signed', null, 'field_officer');
        return $review;
    }

    public function assertPreparer(int $propertyId, User $user): void
    {
        if (!TenantReviewAccess::permissionGranted($user, 'create_tenants')) {
            throw ValidationException::withMessages(['property_id' => 'Your role needs the create_tenants permission before saving and signing a new tenant.']);
        }
        if (!TenantReviewAccess::allows($user, 'view_tenant_onboarding')) {
            throw ValidationException::withMessages(['property_id' => 'Your role needs the New Tenant Approvals module and view_tenant_onboarding permission before saving and signing.']);
        }
        if (!Schema::hasTable('tenant_reviews')) {
            throw ValidationException::withMessages(['property_id' => 'Apply the tenant approval migration before saving a new tenant.']);
        }
        app(TenantReviewWorkflowService::class)->current();
        $property = Property::withoutGlobalScopes()->findOrFail($propertyId);
        if (!$this->hasRole($user, config('tenant_review.roles.field_officer'))
            || !DB::table('sys_user_staff_links')->where('user_id', $user->id)->where('staff_id', $property->field_officer_id)->exists()) {
            throw ValidationException::withMessages(['property_id' =>
                'Your account cannot sign for this property. The assigned Field Officer must save and sign the tenant.']);
        }
    }

    public function decide(TenantReview $review, User $user, int $revision, string $stage, string $action, ?string $reason): void
    {
        DB::transaction(function () use ($review, $user, $revision, $stage, $action, $reason) {
            $review = TenantReview::lockForUpdate()->findOrFail($review->id);
            if ($review->revision !== $revision || $review->stage !== $stage) {
                throw ValidationException::withMessages(['review' => 'This review changed. Refresh before deciding.']);
            }
            $direct = $action === 'direct_approve';
            abort_unless($direct ? $this->canDirectApprove($review, $user) : $this->reviewer($review, $user), 403);
            $lease = LeaseAgreement::withoutGlobalScopes()->lockForUpdate()->findOrFail($review->lease_id);
            $tenant = Client::withoutGlobalScopes()->lockForUpdate()->findOrFail($review->tenant_id);
            if ((int) $lease->current_status_id !== 1 || (int) $tenant->current_status_id !== 1) {
                throw ValidationException::withMessages(['review' => 'The tenant and lease must both be pending.']);
            }
            if ($direct) {
                if (!trim((string) $reason)) {
                    throw ValidationException::withMessages(['reason' => 'Record a reason for the direct Director approval.']);
                }
                $reasonBlocked = $lease->approvalBlockReason();
                if ($reasonBlocked) throw ValidationException::withMessages(['review' => $reasonBlocked]);
                $lease->update(['current_status_id' => 5]);
                $tenant->update(['current_status_id' => 5]);
                $review->update(['stage' => 'approved']);
                $this->record($review, $user, 'direct_approved', trim($reason), 'director_override');
                return;
            }
            if (in_array($action, ['return', 'reject'], true) && !trim((string) $reason)) {
                throw ValidationException::withMessages(['reason' => 'Give the Field Officer a reason.']);
            }
            if ($action === 'return') {
                $review->stage = 'correction';
            } elseif ($action === 'reject') {
                $unit = Unit::withoutGlobalScopes()->lockForUpdate()->findOrFail($lease->unit_id);
                $review->accounts_pending = app(TenantReviewInvoiceService::class)->rejectInvoices($lease, $user);
                $lease->update(['current_status_id' => 41, 'lease_status' => 'Rejected']);
                $tenant->update(['current_status_id' => 41, 'unit_id' => null, 'property_id' => null]);
                if (!$unit->activeLeaseAgreement()->exists()) $unit->update(['occupancy_status_id' => 31]);
                $review->stage = 'rejected';
            } elseif ($action === 'approve' && $review->nextStep()) {
                $review->stage = $review->nextStep()['stage'];
            } elseif ($action === 'approve') {
                $priorStages = array_column(array_slice($review->steps(), 1, -1), 'stage');
                $signedStages = $review->events()->where('revision', $review->revision)->where('action', 'signed')->pluck('stage')->all();
                if (array_diff($priorStages, $signedStages)) {
                    throw ValidationException::withMessages(['review' => 'Every earlier approval step must be signed first.']);
                }
                $reasonBlocked = $lease->approvalBlockReason();
                if ($reasonBlocked) throw ValidationException::withMessages(['review' => $reasonBlocked]);
                $lease->update(['current_status_id' => 5]);
                $tenant->update(['current_status_id' => 5]);
                $review->stage = 'approved';
            } else {
                abort(422);
            }
            $review->save();
            $this->record($review, $user, $action === 'approve' ? 'signed' : $action, $reason, $stage);
        });
    }

    public function lockForCorrection(int $tenantId, User $user): ?TenantReview
    {
        if (!Schema::hasTable('tenant_reviews')) return null;
        $review = TenantReview::where('tenant_id', $tenantId)->lockForUpdate()->first();
        if (!$review || $review->stage === 'approved') return null;
        abort_unless(TenantReviewAccess::allows($user, 'view_tenant_onboarding'), 403);
        abort_unless(TenantReviewAccess::permissionGranted($user, 'update_tenants'), 403);
        abort_unless(!$user->block && (int) $review->prepared_by === (int) $user->id && $review->stage === 'correction', 403,
            'Pending onboarding details can only be corrected by the preparing Field Officer after a return.');
        return $review;
    }

    public function resubmit(TenantReview $review, User $user): void
    {
        $review->update(['revision' => $review->revision + 1, 'stage' => $review->firstApprovalStage()]);
        $this->record($review, $user, 'corrected_and_signed', null, 'field_officer');
    }

    private function record(TenantReview $review, User $user, string $action, ?string $reason, string $stage): void
    {
        $review->unsetRelation('tenant')->unsetRelation('lease');
        $review->events()->create(['actor_id' => $user->id, 'revision' => $review->revision,
            'stage' => $stage, 'action' => $action, 'reason' => $reason,
            'snapshot' => ['tenant' => $review->tenant->getAttributes(), 'lease' => $review->lease->getAttributes()],
            'created_at' => now()]);
        $reviewId = $review->id;
        DB::afterCommit(function () use ($reviewId) {
            try {
                $review = TenantReview::findOrFail($reviewId);
                $roleIds = array_column($review->steps(), 'role_id');
                $accountsRoles = config('tenant_review.roles.accounts');
                $recipients = User::where('block', false)->whereHas('roles', fn ($q) => $q
                    ->whereIn('cb_roles.id', $roleIds)->orWhereIn('name', $accountsRoles)->orWhereIn('display_name', $accountsRoles))->get();
                $recipients->push(User::find($review->prepared_by));
                foreach ($recipients->filter()->unique('id') as $recipient) {
                    if (!$this->canView($review, $recipient)) continue;
                    if ((int) $recipient->id !== (int) $review->prepared_by && !$this->reviewer($review, $recipient)
                        && !($review->accounts_pending && $this->canReconcile($recipient))) continue;
                    \App\Models\Notification::create(['type' => 'tenant_review', 'notifiable_type' => User::class,
                        'notifiable_id' => $recipient->id, 'title' => 'Tenant onboarding update',
                        'message' => $review->stageLabel(), 'data' => ['review_id' => $review->id],
                        'action_url' => route('tenant-reviews.show', $review, false)]);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Tenant review notification failed.', ['review_id' => $reviewId, 'exception' => get_class($e)]);
            }
        });
    }

    public static function legacyOnly($query, string $column, string $kind)
    {
        if (!Schema::hasTable('tenant_reviews')) return $query;
        return $query->whereNotExists(fn ($q) => $q->selectRaw('1')->from('tenant_reviews')
            ->whereColumn('tenant_reviews.'.$kind.'_id', $column));
    }

    public static function assertLegacy(int $id, string $kind): void
    {
        if (Schema::hasTable('tenant_reviews') && TenantReview::where($kind.'_id', $id)->exists()) {
            throw ValidationException::withMessages(['review' => 'Use New Tenant Approvals for this record.']);
        }
    }

    public static function assertLeaseEditable(int $id): void
    {
        if (Schema::hasTable('tenant_reviews') && TenantReview::where('lease_id', $id)->where('stage', '!=', 'approved')->exists()) {
            throw ValidationException::withMessages(['review' => 'Return this onboarding to the Field Officer for correction.']);
        }
    }

    public static function assertTenantEditable(int $id): void
    {
        if (Schema::hasTable('tenant_reviews') && TenantReview::where('tenant_id', $id)->where('stage', '!=', 'approved')->exists()) {
            throw ValidationException::withMessages(['review' => 'Complete this tenant onboarding review first.']);
        }
    }
}
