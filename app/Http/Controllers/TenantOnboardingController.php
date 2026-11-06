<?php

namespace App\Http\Controllers;

use App\Models\TenantOnboarding;
use App\Services\TenantOnboardingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TenantOnboardingController extends Controller
{
    public function __construct(private TenantOnboardingService $workflow) {}

    public function index(Request $request)
    {
        abort_unless($this->workflow->enabled(), 404);
        $submissions = $this->workflow->visibleSubmissions($request->user())->orderByDesc('id')->paginate(25);
        return view('property.tenant-onboarding.index', compact('submissions'));
    }

    public function show(Request $request, TenantOnboarding $submission)
    {
        abort_unless($this->workflow->enabled() && $this->workflow->canView($submission, $request->user()), 403);
        $payload = $submission->payload;
        $property = DB::table('sys_pm_properties')->find($submission->property_id);
        $unit = DB::table('sys_pm_units')->find($submission->unit_id);
        $reviewers = $this->workflow->reviewers($submission);
        $canAct = $this->workflow->canAct($submission, $request->user());
        $history = DB::table('tenant_onboarding_events')->where('submission_id', $submission->id)->orderBy('id')->get();
        return view('property.tenant-onboarding.show', compact('submission', 'payload', 'property', 'unit', 'reviewers', 'canAct', 'history'));
    }

    public function decide(Request $request, TenantOnboarding $submission)
    {
        $data = $request->validate([
            'revision' => 'required|integer|min:1', 'stage' => 'required|string',
            'action' => 'required|in:sign,return,reject', 'comment' => 'nullable|string|max:3000',
        ]);
        $this->workflow->decide($submission, $request->user(), $data['revision'], $data['stage'], $data['action'], $data['comment'] ?? null);
        return redirect()->route('tenant-onboarding.show', $submission)->with('success', 'Your decision has been recorded.');
    }

    public function correct(Request $request, TenantOnboarding $submission)
    {
        abort_unless($this->workflow->enabled() && $submission->prepared_by === (int) $request->user()->id && $submission->state === 'changes_requested', 403);
        return view('property.tenant-onboarding.correct', compact('submission'));
    }

    public function document(Request $request, TenantOnboarding $submission, int $index)
    {
        abort_unless($this->workflow->enabled() && $this->workflow->canView($submission, $request->user()), 403);
        $document = array_values($submission->documents)[$index] ?? null;
        abort_unless($document && Storage::disk('local')->exists($document['path']), 404);
        return Storage::disk('local')->download($document['path'], $document['name']);
    }
}
