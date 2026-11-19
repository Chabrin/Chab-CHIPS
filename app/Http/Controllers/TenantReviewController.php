<?php

namespace App\Http\Controllers;

use App\Models\TenantReview;
use App\Services\TenantReviewService;
use App\Services\TenantReviewWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TenantReviewController extends Controller
{
    public function __construct(private TenantReviewService $reviews, private TenantReviewWorkflowService $workflows) {}

    public function index(Request $request)
    {
        $user = $request->user();
        abort_if($user->block, 403);
        $this->workflows->current();
        $query = TenantReview::with(['tenant', 'lease', 'workflowVersion'])->orderByDesc('id');
        if (!$this->reviews->accounts($user)) {
            $roleVersions = $this->workflows->eligibleVersions($user, 'role');
            $zoneVersions = $this->workflows->eligibleVersions($user, 'zone_manager');
            $staffIds = DB::table('sys_user_staff_links')->where('user_id', $user->id)->pluck('staff_id');
            $query->where(function ($q) use ($user, $staffIds, $roleVersions, $zoneVersions) {
                $q->where('prepared_by', $user->id)->orWhereIn('workflow_version_id', $roleVersions)
                    ->orWhere(function ($scoped) use ($staffIds, $zoneVersions) {
                        $scoped->whereIn('workflow_version_id', $zoneVersions)
                            ->whereHas('lease', fn ($lease) => $lease->whereIn('property_id',
                                DB::table('sys_pm_properties as p')->join('sys_set_zones as z', 'z.id', '=', 'p.zone_id')
                                    ->whereIn('z.zone_manager_id', $staffIds)->select('p.id')));
                    });
            });
        }
        if ($request->boolean('accounts')) {
            abort_unless($this->reviews->accounts($user), 403);
            $query->where('accounts_pending', true);
        }
        $items = $query->paginate(25);
        return view('property.tenant-reviews.index', compact('items'));
    }

    public function show(Request $request, TenantReview $review)
    {
        abort_unless($this->reviews->canView($review, $request->user()), 403);
        $review->load(['tenant.documents', 'tenant.tenantBankDetails.bank', 'tenant.tenantNextOfKin', 'lease', 'events.actor']);
        $invoices = \App\Models\DebtorInvoice::where('lease_id', $review->lease_id)->get();
        $inspections = \App\Models\MoveInInspection::where('tenant_id', $review->tenant_id)->get();
        $canAct = $this->reviews->reviewer($review, $request->user());
        $canAccounts = $review->accounts_pending && $this->reviews->accounts($request->user());
        $canCorrect = $review->stage === 'correction' && (int) $review->prepared_by === (int) $request->user()->id;
        return view('property.tenant-reviews.show', compact('review', 'canAct', 'canAccounts', 'canCorrect', 'invoices', 'inspections'));
    }

    public function decide(Request $request, TenantReview $review)
    {
        $data = $request->validate(['revision' => 'required|integer', 'stage' => 'required|string',
            'action' => 'required|in:approve,return,reject', 'reason' => 'nullable|string|max:2000']);
        $this->reviews->decide($review, $request->user(), $data['revision'], $data['stage'], $data['action'], $data['reason'] ?? null);
        return back()->with('success', 'Decision recorded.');
    }

    public function resubmit(Request $request, TenantReview $review)
    {
        $data = $request->validate(['revision' => 'required|integer']);
        DB::transaction(function () use ($request, $review, $data) {
            $locked = $this->reviews->lockForCorrection($review->tenant_id, $request->user());
            abort_unless($locked && $locked->revision === (int) $data['revision'], 409);
            $this->reviews->resubmit($locked, $request->user());
        });
        return back()->with('success', 'Signed and sent to '.$review->steps()[1]['label'].'.');
    }

    public function reconcile(Request $request, TenantReview $review)
    {
        abort_unless($this->reviews->accounts($request->user()), 403);
        $data = $request->validate(['reason' => 'required|string|max:2000', 'revision' => 'required|integer']);
        DB::transaction(function () use ($review, $request, $data) {
            $review = TenantReview::lockForUpdate()->findOrFail($review->id);
            abort_unless($review->accounts_pending && $review->revision === (int) $data['revision'], 409);
            $review->events()->create(['revision' => $review->revision, 'actor_id' => $request->user()->id,
                'stage' => 'accounts', 'action' => 'reconciled', 'reason' => $data['reason'], 'created_at' => now()]);
            $review->update(['accounts_pending' => false]);
        });
        return back()->with('success', 'Accounts reconciliation recorded.');
    }

    public function setup(Request $request)
    {
        abort_unless($this->workflows->canManage($request->user()), 403);
        $version = $this->workflows->current();
        $roles = DB::table('cb_roles')->orderBy('display_name')->get(['id', 'name', 'display_name']);
        $history = \App\Models\TenantReviewWorkflowVersion::with('author')->orderByDesc('number')->get();
        return view('property.tenant-reviews.setup', compact('version', 'roles', 'history'));
    }

    public function publish(Request $request)
    {
        abort_unless($this->workflows->canManage($request->user()), 403);
        $data = $request->validate(['version' => 'required|integer', 'steps' => 'required|array|min:1|max:11',
            'steps.*.role_id' => 'required|integer']);
        $version = $this->workflows->publish(array_column($data['steps'], 'role_id'), $data['version'], $request->user());
        return back()->with('success', 'Tenant approval version '.$version->number.' published.');
    }
}
