<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Services\Leave\LeaveApprovalRouter;
use App\Services\Leave\LeaveHandoverService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LeaveHandoverController extends Controller
{
    public function acknowledge(Request $request, int $id, LeaveHandoverService $handovers)
    {
        $data = $request->validate(['revision' => 'required|string|size:64', 'note' => 'nullable|string|max:2000']);
        $handovers->acknowledge(LeaveRequest::findOrFail($id), $request->user(), $data['revision'], $data['note'] ?? null);
        return back()->with('success', 'Relief arrangements acknowledged. The applicant, supervisor and HR have been notified.');
    }

    public function download(Request $request, int $id, string $attachment, LeaveHandoverService $handovers, LeaveApprovalRouter $router)
    {
        $leave = LeaveRequest::findOrFail($id);
        $user = $request->user();
        abort_unless(!$user->block && $user->activation != 0 && $user->roles()->exists()
            && ($handovers->isRecipient($user, $leave) || $router->canReview($user, $leave)), 403);
        $file = collect($leave->handover_attachments ?? [])->firstWhere('id', $attachment);
        abort_unless($file && Storage::disk('local')->exists($file['path']), 404);
        return Storage::disk('local')->download($file['path'], $file['name'], [
            'Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
