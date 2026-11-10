<?php

namespace App\Http\Controllers;

use App\Models\CommBatch;
use App\Models\EmailLog;
use App\Models\SmsLog;
use App\Services\Comms\AudienceResolver;
use App\Services\Comms\CommBatchService;
use App\Traits\ChecksMenuAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The Communications module pages and AJAX: compose and send email/SMS to a chosen audience
 * (one client, picked clients, all tenants, a property's or zone's tenants, landlords, staff),
 * browse what was sent (batches and per-recipient logs) and the metrics. All sending logic
 * lives in CommBatchService / EmailService / SmsService.
 *
 * Every method checks the menu permission of the page it belongs to. The AccessChecker middleware
 * only guards a path that is itself a menu page, so without these checks the AJAX endpoints (send,
 * estimate, search, logs, metrics) would be open to any signed-in user.
 */
class CommunicationsController extends Controller
{
    use ChecksMenuAccess;

    protected CommBatchService $batches;
    protected AudienceResolver $audience;

    /**
     * Inject the batch service and audience resolver.
     *
     * @param CommBatchService $batches
     * @param AudienceResolver $audience
     */
    public function __construct(CommBatchService $batches, AudienceResolver $audience) {
        $this->batches = $batches;
        $this->audience = $audience;
    }

    /**
     * Batches page: everything that was sent, newest first.
     */
    public function index() {
        if (!$this->menuAllowed('/communications')) {
            return $this->denied('page');
        }

        return view('communications.index');
    }

    /**
     * Compose page: pick channel, audience, write the message, estimate, send.
     */
    public function compose() {
        if (!$this->menuAllowed('/communications/compose')) {
            return $this->denied('page');
        }

        $zones = DB::table('sys_set_zones')->orderBy('zone_name')->get(['id', 'zone_name']);

        return view('communications.compose', compact('zones'));
    }

    /**
     * Per-recipient logs page across all batches and system sends.
     */
    public function logs() {
        if (!$this->menuAllowed('/communications/logs')) {
            return $this->denied('page');
        }

        return view('communications.logs');
    }

    /**
     * Metrics page: volumes, failure rates, SMS units.
     */
    public function metrics() {
        if (!$this->menuAllowed('/communications/metrics')) {
            return $this->denied('page');
        }

        return view('communications.metrics');
    }

    /**
     * One batch: summary and its per-recipient outcomes.
     *
     * @param int $id
     */
    public function show($id) {
        if (!$this->menuAllowed('/communications')) {
            return $this->denied('page');
        }

        $batch = CommBatch::with('sender:id,name')->findOrFail($id);

        return view('communications.show', compact('batch'));
    }

    /**
     * AJAX: batch rows for the index table.
     */
    public function batchesData(Request $request) {
        if (!$this->menuAllowed('/communications')) {
            return $this->denied('json');
        }

        $query = CommBatch::with('sender:id,name')->orderByDesc('id');

        if ($request->filled('channel')) {
            $query->where('channel', $request->input('channel'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $rows = $query->limit(2000)->get()->map(fn ($b) => [
            'id' => $b->id,
            'date' => $b->created_at?->format('d M Y H:i'),
            'channel' => strtoupper($b->channel),
            'subject' => $b->subject ?: mb_substr($b->body, 0, 60),
            'audience' => $b->audience_label,
            'recipients' => $b->recipient_count,
            'sent' => $b->sent_count,
            'failed' => $b->failed_count,
            'skipped' => $b->skipped_count,
            'status' => $b->status,
            'sender' => $b->sender->name ?? '—',
        ]);

        return response()->json(['success' => true, 'count' => $rows->count(), 'data' => $rows]);
    }

    /**
     * AJAX: estimate a send — counts, SMS units and the first recipient's resolved message.
     */
    public function estimate(Request $request) {
        if (!$this->menuAllowed('/communications/compose')) {
            return $this->denied('json');
        }

        $data = $this->validatePayload($request, false, true);

        $result = $this->batches->estimate($data['channel'], $data['audience_type'], $data['params'], $data['subject'] ?? '', $data['body']);

        return response()->json(['success' => true] + $result);
    }

    /**
     * AJAX: create the batch and queue the send.
     */
    public function send(Request $request) {
        if (!$this->menuAllowed('/communications/compose')) {
            return $this->denied('json');
        }

        $data = $this->validatePayload($request, true);

        $attachmentPath = null;
        $attachmentName = null;
        if ($data['channel'] === 'email' && $request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentPath = $file->store('comms', 'local');
            $attachmentName = $file->getClientOriginalName();
        }

        [$batch, $error] = $this->batches->dispatch($data['channel'], $data['audience_type'], $data['params'], $data['subject'] ?? '', $data['body'], (int) auth()->id(), $attachmentPath, $attachmentName);

        if (!$batch) {
            return response()->json(['success' => false, 'message' => $error], 422);
        }

        return response()->json(['success' => true, 'batch_id' => $batch->id, 'message' => 'Queued for ' . ($batch->recipient_count - $batch->skipped_count) . ' recipient(s).' . ($batch->skipped_count ? " {$batch->skipped_count} skipped (no contact on record)." : '')]);
    }

    /**
     * AJAX: one batch's per-recipient log rows.
     *
     * @param int $id
     */
    public function batchLogsData(Request $request, $id) {
        if (!$this->menuAllowed('/communications')) {
            return $this->denied('json');
        }

        $batch = CommBatch::findOrFail($id);

        $query = $batch->channel === 'sms' ? SmsLog::where('batch_id', $batch->id) : EmailLog::where('batch_id', $batch->id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $rows = $query->orderBy('id')->limit(10000)->get()->map(fn ($log) => $this->logRow($log, $batch->channel));

        return response()->json(['success' => true, 'count' => $rows->count(), 'data' => $rows]);
    }

    /**
     * AJAX: flat per-recipient log search across batches and system sends.
     */
    public function logsData(Request $request) {
        if (!$this->menuAllowed('/communications/logs')) {
            return $this->denied('json');
        }

        $channel = $request->input('channel', 'email');
        $query = $channel === 'sms' ? SmsLog::query() : EmailLog::query();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('sent_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('sent_at', '<=', $request->input('date_to'));
        }
        if ($request->filled('search')) {
            $term = '%' . mb_strtolower((string) $request->input('search')) . '%';
            $contactColumn = $channel === 'sms' ? 'recipient_phone' : 'recipient_email';
            $query->where(function ($q) use ($term, $contactColumn) {
                $q->whereRaw('LOWER(recipient_name) LIKE ?', [$term])->orWhereRaw("LOWER({$contactColumn}) LIKE ?", [$term]);
            });
        }

        $rows = $query->orderByDesc('id')->limit(5000)->get()->map(fn ($log) => $this->logRow($log, $channel));

        return response()->json(['success' => true, 'count' => $rows->count(), 'data' => $rows, 'truncated' => $rows->count() >= 5000]);
    }

    /**
     * AJAX: metrics for the last N days — totals, failure rate, per-day volumes, SMS units, top errors.
     */
    public function metricsData(Request $request) {
        if (!$this->menuAllowed('/communications/metrics')) {
            return $this->denied('json');
        }

        $days = min(365, max(1, (int) $request->input('days', 30)));
        $from = now()->subDays($days)->startOfDay();

        $totals = [];
        foreach (['email' => 'cb_email_logs', 'sms' => 'cb_sms_logs'] as $channel => $table) {
            $totals[$channel] = DB::table($table)->where('sent_at', '>=', $from)->selectRaw("
                count(*) as total,
                sum(case when status = 'sent' then 1 else 0 end) as sent,
                sum(case when status = 'failed' then 1 else 0 end) as failed,
                sum(case when status = 'skipped' then 1 else 0 end) as skipped" . ($channel === 'sms' ? ", sum(case when status = 'sent' then units else 0 end) as units" : '')
            )->first();
        }

        $perDay = [];
        foreach (['email' => 'cb_email_logs', 'sms' => 'cb_sms_logs'] as $channel => $table) {
            $perDay[$channel] = DB::table($table)->where('sent_at', '>=', $from)
                ->selectRaw("DATE(sent_at) as day, count(*) as total, sum(case when status = 'failed' then 1 else 0 end) as failed")
                ->groupBy('day')->orderBy('day')->get();
        }

        $topErrors = [];
        foreach (['email' => 'cb_email_logs', 'sms' => 'cb_sms_logs'] as $channel => $table) {
            $topErrors[$channel] = DB::table($table)->where('sent_at', '>=', $from)->where('status', 'failed')
                ->selectRaw('error, count(*) as n')->groupBy('error')->orderByDesc('n')->limit(5)->get();
        }

        $byType = DB::table('cb_email_logs')->where('sent_at', '>=', $from)
            ->selectRaw('type, count(*) as n')->groupBy('type')->orderByDesc('n')->limit(8)->get();

        return response()->json(['success' => true, 'days' => $days, 'totals' => $totals, 'per_day' => $perDay, 'top_errors' => $topErrors, 'email_by_type' => $byType]);
    }

    /**
     * AJAX: re-queue a batch's failed recipients.
     *
     * @param int $id
     */
    public function resendFailed($id) {
        if (!$this->menuAllowed('/communications/compose')) {
            return $this->denied('json');
        }

        $batch = CommBatch::findOrFail($id);
        $count = $this->batches->resendFailed($batch);

        if ($count === 0) {
            return response()->json(['success' => false, 'message' => 'This batch has no failed recipients.'], 422);
        }

        return response()->json(['success' => true, 'message' => "Re-queued {$count} failed recipient(s)."]);
    }

    /**
     * AJAX: property search for the audience picker (select2). ?q= matches name or reference number.
     */
    public function propertySearch(Request $request) {
        if (!$this->menuAllowed('/communications/compose')) {
            return $this->denied('json');
        }

        $term = trim((string) $request->input('q', ''));

        $query = DB::table('sys_pm_properties')->orderBy('property_name')->limit(20);

        if ($term !== '') {
            $like = '%' . mb_strtolower($term) . '%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(property_name) LIKE ?', [$like])->orWhereRaw('LOWER(reference_number) LIKE ?', [$like]);
            });
        }

        $results = $query->get(['id', 'property_name', 'reference_number'])->map(fn ($p) => [
            'id' => $p->id,
            'text' => $p->property_name . ' (' . $p->reference_number . ')',
        ]);

        return response()->json(['results' => $results]);
    }

    /**
     * AJAX: the live counters and status of one batch, polled by the batch page while it is sending.
     *
     * @param int $id
     */
    public function batchStatus($id) {
        if (!$this->menuAllowed('/communications')) {
            return $this->denied('json');
        }

        $batch = CommBatch::findOrFail($id);

        return response()->json([
            'success' => true,
            'status' => $batch->status,
            'recipients' => $batch->recipient_count,
            'sent' => $batch->sent_count,
            'failed' => $batch->failed_count,
            'skipped' => $batch->skipped_count,
        ]);
    }

    /**
     * AJAX: client search for the audience picker (select2). ?q= term, ?kind= tenant|landlord|any.
     */
    public function clientSearch(Request $request) {
        if (!$this->menuAllowed('/communications/compose')) {
            return $this->denied('json');
        }

        $term = trim((string) $request->input('q', ''));
        $kind = $request->input('kind', 'any');

        $query = DB::table('sys_pm_clients')->orderBy('names')->limit(20);

        if ($kind === 'tenant') {
            $query->where('type_id', '2');
        } elseif ($kind === 'landlord') {
            $query->where('type_id', '1');
        }

        if ($term !== '') {
            $like = '%' . mb_strtolower($term) . '%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(names) LIKE ?', [$like])->orWhereRaw('LOWER(reference_number) LIKE ?', [$like]);
            });
        }

        $results = $query->get(['id', 'names', 'reference_number', 'email_address', 'mobile_number'])->map(fn ($c) => [
            'id' => $c->id,
            'text' => $c->names . ' (' . $c->reference_number . ')',
            'email' => $c->email_address,
            'mobile' => $c->mobile_number,
        ]);

        return response()->json(['results' => $results]);
    }

    /**
     * Validate the compose payload and normalise the audience params.
     *
     * @param Request $request
     * @param bool $forSend attachment rules only apply on the real send
     * @param bool $draft the compose page counts an audience before the message is written, so subject and body may be empty
     * @return array channel, audience_type, params, subject, body
     */
    protected function validatePayload(Request $request, bool $forSend = false, bool $draft = false): array {
        $validated = $request->validate([
            'channel' => 'required|in:email,sms',
            'audience_type' => 'required|in:' . implode(',', AudienceResolver::TYPES),
            'subject' => ($draft ? 'nullable' : 'required_if:channel,email|nullable') . '|string|max:255',
            'body' => ($draft ? 'nullable' : 'required') . '|string|max:' . ($request->input('channel') === 'sms' ? 1600 : 50000),
            'client_id' => 'required_if:audience_type,single_client|nullable|integer',
            'client_ids' => 'required_if:audience_type,clients|nullable|array|max:500',
            'client_ids.*' => 'integer',
            'property_id' => 'required_if:audience_type,property|nullable|integer',
            'zone_id' => 'required_if:audience_type,zone|nullable|integer',
            'attachment' => ($forSend ? 'nullable|file|max:10240' : 'nullable'),
        ]);

        $params = match ($validated['audience_type']) {
            'single_client' => ['client_id' => (int) $validated['client_id']],
            'clients' => ['client_ids' => array_map('intval', $validated['client_ids'] ?? [])],
            'property' => ['property_id' => (int) $validated['property_id']],
            'zone' => ['zone_id' => (int) $validated['zone_id']],
            default => [],
        };

        return ['channel' => $validated['channel'], 'audience_type' => $validated['audience_type'], 'params' => $params, 'subject' => $validated['subject'] ?? '', 'body' => $validated['body'] ?? ''];
    }

    /**
     * One log row shaped for the tables.
     *
     * @param EmailLog|SmsLog $log
     * @param string $channel
     */
    protected function logRow($log, string $channel): array {
        return [
            'id' => $log->id,
            'date' => $log->sent_at?->format('d M Y H:i'),
            'type' => $log->type,
            'recipient' => $log->recipient_name,
            'contact' => $channel === 'sms' ? $log->recipient_phone : $log->recipient_email,
            'subject' => $channel === 'sms' ? mb_substr($log->message, 0, 60) : $log->subject,
            'units' => $channel === 'sms' ? $log->units : null,
            'status' => $log->status,
            'error' => $log->error,
            'batch_id' => $log->batch_id,
        ];
    }
}
