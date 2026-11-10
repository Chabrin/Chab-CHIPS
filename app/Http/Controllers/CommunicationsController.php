<?php

namespace App\Http\Controllers;

use App\Models\CommBatch;
use App\Models\EmailLog;
use App\Models\SmsLog;
use App\Services\Comms\AudienceResolver;
use App\Services\Comms\CommBatchService;
use App\Services\Comms\CommSettings;
use App\Services\Comms\EmailService;
use App\Services\Comms\MailConfigService;
use App\Services\Comms\SmsService;
use App\Traits\ChecksMenuAccess;
use Carbon\Carbon;
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

    /** Rows counted before a list says "100,000+": a count that stops early stays fast on a table of millions. */
    const COUNT_CAP = 100000;

    protected CommBatchService $batches;
    protected AudienceResolver $audience;
    protected CommSettings $settings;
    protected MailConfigService $mailConfig;
    protected EmailService $email;
    protected SmsService $sms;

    /**
     * Inject the batch service, audience resolver, credentials store and the two senders.
     *
     * @param CommBatchService $batches
     * @param AudienceResolver $audience
     * @param CommSettings $settings
     * @param MailConfigService $mailConfig
     * @param EmailService $email
     * @param SmsService $sms
     */
    public function __construct(CommBatchService $batches, AudienceResolver $audience, CommSettings $settings, MailConfigService $mailConfig, EmailService $email, SmsService $sms) {
        $this->batches = $batches;
        $this->audience = $audience;
        $this->settings = $settings;
        $this->mailConfig = $mailConfig;
        $this->email = $email;
        $this->sms = $sms;
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
        $this->applyDateRange($query, 'created_at', $request->input('date_from'), $request->input('date_to'));

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

        // DataTables' own search box arrives as search[value]
        $this->applyRecipientSearch($query, $batch->channel, (string) $request->input('search.value', ''));

        // a batch's recipients read in the order they were processed
        return $this->pagedLogResponse($request, $query, $batch->channel, 'asc');
    }

    /**
     * AJAX: flat per-recipient log search across batches and system sends.
     */
    public function logsData(Request $request) {
        if (!$this->menuAllowed('/communications/logs')) {
            return $this->denied('json');
        }

        $channel = $request->input('channel', 'email') === 'sms' ? 'sms' : 'email';
        $query = $channel === 'sms' ? SmsLog::query() : EmailLog::query();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        $this->applyDateRange($query, 'sent_at', $request->input('date_from'), $request->input('date_to'));
        $this->applyRecipientSearch($query, $channel, (string) $request->input('q', ''));

        return $this->pagedLogResponse($request, $query, $channel);
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
     * Credentials page: the mail and SMS settings, with test buttons.
     */
    public function settingsPage() {
        if (!$this->menuAllowed(CommSettings::PAGE)) {
            return $this->denied('page');
        }

        return view('communications.settings');
    }

    /**
     * AJAX: the saved settings for the credentials page. Secrets are never included, only whether
     * one is saved, plus what the server's .env would use when nothing is saved.
     */
    public function settingsData() {
        if (!$this->menuAllowed(CommSettings::PAGE)) {
            return $this->denied('json');
        }

        return response()->json([
            'success' => true,
            'mail' => $this->settings->view('mail'),
            'sms' => $this->settings->view('sms'),
            'defaults' => ['mailer' => config('mail.default'), 'mail_host' => config('mail.mailers.smtp.host'), 'mail_from' => config('mail.from.address'), 'sms_provider' => config('services.sms.provider')],
            'user_email' => optional(auth()->user())->email,
        ]);
    }

    /**
     * AJAX: save the mail credentials, or with use_env go back to the server's .env.
     */
    public function saveMailSettings(Request $request) {
        if (!$this->menuAllowed(CommSettings::PAGE)) {
            return $this->denied('json');
        }

        if ($request->boolean('use_env')) {
            $this->settings->clear('mail', auth()->id());

            return response()->json(['success' => true, 'message' => 'Mail will now send through the server defaults.']);
        }

        $data = $request->validate([
            'host' => ['required', 'string', 'max:190', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'port' => 'required|integer|between:1,65535',
            'encryption' => 'required|in:tls,ssl,none',
            'username' => 'nullable|string|max:190',
            'password' => 'nullable|string|max:500',
            'from_address' => 'required|email|max:190',
            'from_name' => 'nullable|string|max:120',
            'reply_to' => 'nullable|email|max:190',
            'reply_to_name' => 'nullable|string|max:120',
        ]);

        $this->settings->save('mail', $data, auth()->id());

        return response()->json(['success' => true, 'message' => 'Mail settings saved. Send a test email to confirm they work.']);
    }

    /**
     * AJAX: save the SMS gateway credentials, or with use_env go back to the server's .env.
     */
    public function saveSmsSettings(Request $request) {
        if (!$this->menuAllowed(CommSettings::PAGE)) {
            return $this->denied('json');
        }

        if ($request->boolean('use_env')) {
            $this->settings->clear('sms', auth()->id());

            return response()->json(['success' => true, 'message' => 'SMS will now use the server defaults.']);
        }

        $data = $request->validate([
            'provider' => 'required|in:none,africastalking',
            'username' => 'required_if:provider,africastalking|nullable|string|max:190',
            'api_key' => 'nullable|string|max:500',
            'sender_id' => 'nullable|string|max:20',
        ]);

        if ($data['provider'] === 'africastalking' && trim((string) ($data['api_key'] ?? '')) === '' && !$this->settings->view('sms')['has_secret']) {
            return response()->json(['success' => false, 'message' => 'Enter the API key.', 'errors' => ['api_key' => ['Enter the API key.']]], 422);
        }

        $this->settings->save('sms', $data, auth()->id());

        return response()->json(['success' => true, 'message' => 'SMS settings saved. Send a test SMS to confirm they work.']);
    }

    /**
     * AJAX: send one real test email through whatever credentials are in force, and log it.
     */
    public function testMail(Request $request) {
        if (!$this->menuAllowed(CommSettings::PAGE)) {
            return $this->denied('json');
        }

        $data = $request->validate(['to' => 'required|email|max:190']);
        $via = $this->mailConfig->config() ? 'the saved settings' : 'the server defaults';

        [$ok, $error] = $this->email->send($data['to'], $data['to'], config('app.company_name') . ' mail test', '<p>This is a test email from the Communications module.</p><p>If you can read it, sending works.</p>', 'test');

        return response()->json(['success' => $ok, 'message' => $ok ? "Test email sent to {$data['to']} using {$via}." : 'Test email failed: ' . $error], $ok ? 200 : 422);
    }

    /**
     * AJAX: send one real test SMS through whatever gateway is in force, and log it.
     */
    public function testSms(Request $request) {
        if (!$this->menuAllowed(CommSettings::PAGE)) {
            return $this->denied('json');
        }

        $data = $request->validate(['to' => ['required', 'string', 'regex:/^\+?[0-9 ]{9,15}$/']]);

        [$ok, $error] = $this->sms->send(str_replace(' ', '', $data['to']), 'Test', config('app.company_name') . ' SMS test: sending works.', 'test');

        return response()->json(['success' => $ok, 'message' => $ok ? "Test SMS sent to {$data['to']}." : 'Test SMS failed: ' . $error], $ok ? 200 : 422);
    }

    /**
     * AJAX: staff search for the audience picker (select2). ?q= matches name, staff number or email.
     * Lists active staff and staff on leave, the people who can still be contacted.
     */
    public function staffSearch(Request $request) {
        if (!$this->menuAllowed('/communications/compose')) {
            return $this->denied('json');
        }

        $term = trim((string) $request->input('q', ''));

        $query = DB::table('sys_staff')->whereIn('current_status_id', [1, 4])->orderBy('full_names')->limit(20);

        if ($term !== '') {
            $like = '%' . mb_strtolower($term) . '%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(full_names) LIKE ?', [$like])->orWhereRaw('LOWER(staff_number) LIKE ?', [$like])->orWhereRaw('LOWER(email_address) LIKE ?', [$like]);
            });
        }

        $results = $query->get(['id', 'full_names', 'staff_number', 'email_address', 'mobile_number'])->map(fn ($st) => [
            'id' => $st->id,
            'text' => $st->full_names . ' (' . $st->staff_number . ')',
            'email' => $st->email_address,
            'mobile' => $st->mobile_number,
        ]);

        return response()->json(['results' => $results]);
    }

    /**
     * AJAX: one batch's message as it was written, with its {placeholders}, for the read-message popup.
     *
     * @param int $id
     */
    public function batchMessage($id) {
        if (!$this->menuAllowed('/communications')) {
            return $this->denied('json');
        }

        $batch = CommBatch::with('sender:id,name')->findOrFail($id);

        return response()->json([
            'success' => true,
            'kind' => 'batch',
            'channel' => $batch->channel,
            'subject' => $batch->subject,
            'body' => $batch->body,
            'audience' => $batch->audience_label,
            'attachment' => $batch->attachment_name,
            'sender' => $batch->sender->name ?? null,
            'when' => optional($batch->created_at)->format('d M Y H:i'),
            'status' => $batch->status,
            'recipients' => $batch->recipient_count,
            'sent' => $batch->sent_count,
            'failed' => $batch->failed_count,
            'skipped' => $batch->skipped_count,
        ]);
    }

    /**
     * AJAX: the message one person received (the body after placeholders were filled in), for the
     * read-message popup. A message that was part of a batch can be read from the Sent or the Logs
     * page; a system message that belongs to no batch (a payslip, a password reset) only from Logs.
     * The body goes out as plain text, so stored HTML is never rendered in the browser.
     *
     * @param string $channel email | sms
     * @param int $id
     */
    public function logMessage($channel, $id) {
        if (!in_array($channel, ['email', 'sms'], true)) {
            return response()->json(['success' => false, 'message' => 'Unknown channel.'], 404);
        }

        $log = $channel === 'sms' ? SmsLog::findOrFail($id) : EmailLog::findOrFail($id);

        $allowed = $log->batch_id ? $this->menuAllowed(['/communications', '/communications/logs']) : $this->menuAllowed('/communications/logs');
        if (!$allowed) {
            return $this->denied('json');
        }

        return response()->json([
            'success' => true,
            'kind' => 'log',
            'channel' => $channel,
            'recipient' => $log->recipient_name,
            'contact' => $channel === 'sms' ? $log->recipient_phone : $log->recipient_email,
            'subject' => $channel === 'sms' ? null : $log->subject,
            'body' => $channel === 'sms' ? (string) $log->message : $this->plainText((string) $log->body),
            'type' => $log->type,
            'status' => $log->status,
            'error' => $log->error,
            'batch_id' => $log->batch_id,
            'when' => optional($log->sent_at)->format('d M Y H:i'),
            'units' => $channel === 'sms' ? $log->units : null,
            'message_id' => $channel === 'sms' ? $log->message_id : null,
            'has_attachment' => $channel === 'email' ? (bool) $log->has_attachment : false,
        ]);
    }

    /**
     * An email body as plain text: line-break tags become newlines, other tags are dropped and
     * entities are decoded, so the popup can show it safely with textContent.
     *
     * @param string $html
     */
    protected function plainText(string $html): string {
        $text = preg_replace('#<br\s*/?>|</?(?:p|div|li|tr|h[1-6])\b[^>]*>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/\n{3,}/", "\n\n", $text));
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

        $query = DB::table('sys_pm_clients as c')
            ->leftJoin('cb_tenant_records as r', function ($join) {
                $join->on('r.id', '=', 'c.id')->where('c.type_id', '2');
            })
            ->selectRaw('c.id, c.reference_number, ' . AudienceResolver::CLIENT_CONTACT_SQL)
            ->orderBy('c.names')
            ->limit(20);

        if ($kind === 'tenant') {
            $query->where('c.type_id', '2');
        } elseif ($kind === 'landlord') {
            $query->where('c.type_id', '1');
        }

        if ($term !== '') {
            $like = '%' . mb_strtolower($term) . '%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(c.names) LIKE ?', [$like])->orWhereRaw('LOWER(r.names) LIKE ?', [$like])->orWhereRaw('LOWER(c.reference_number) LIKE ?', [$like]);
            });
        }

        $results = $query->get()->map(fn ($c) => [
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
            'staff_ids' => 'required_if:audience_type,staff|nullable|array|max:500',
            'staff_ids.*' => 'integer',
            'property_id' => 'required_if:audience_type,property|nullable|integer',
            'zone_id' => 'required_if:audience_type,zone|nullable|integer',
            'attachment' => ($forSend ? 'nullable|file|max:10240' : 'nullable'),
        ]);

        $params = match ($validated['audience_type']) {
            'single_client' => ['client_id' => (int) $validated['client_id']],
            'clients' => ['client_ids' => array_map('intval', $validated['client_ids'] ?? [])],
            'staff' => ['staff_ids' => array_map('intval', $validated['staff_ids'] ?? [])],
            'property' => ['property_id' => (int) $validated['property_id']],
            'zone' => ['zone_id' => (int) $validated['zone_id']],
            default => [],
        };

        return ['channel' => $validated['channel'], 'audience_type' => $validated['audience_type'], 'params' => $params, 'subject' => $validated['subject'] ?? '', 'body' => $validated['body'] ?? ''];
    }

    /**
     * Restrict a query to a date range on a timestamp column, inclusive of both days. Written as a
     * range on the raw column (not DATE(column)) so Postgres can use the index on it.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $column
     * @param string|null $from Y-m-d
     * @param string|null $to Y-m-d
     */
    protected function applyDateRange($query, string $column, ?string $from, ?string $to): void {
        if ($start = $this->dayStart($from)) {
            $query->where($column, '>=', $start);
        }
        if ($end = $this->dayStart($to)) {
            $query->where($column, '<', $end->addDay());
        }
    }

    /**
     * The start of a Y-m-d day, or null when empty or not a date.
     *
     * @param string|null $date
     */
    protected function dayStart(?string $date): ?Carbon {
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Narrow a log query to recipients whose name or contact contains the term (case-insensitive).
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $channel email | sms
     * @param string $term
     */
    protected function applyRecipientSearch($query, string $channel, string $term): void {
        $term = trim($term);
        if ($term === '') {
            return;
        }

        $like = '%' . mb_strtolower($term) . '%';
        $contactColumn = $channel === 'sms' ? 'recipient_phone' : 'recipient_email';
        $query->where(function ($q) use ($like, $contactColumn) {
            $q->whereRaw('LOWER(recipient_name) LIKE ?', [$like])->orWhereRaw("LOWER({$contactColumn}) LIKE ?", [$like]);
        });
    }

    /**
     * One page of log rows in the DataTables server-side format (draw, recordsTotal, recordsFiltered,
     * data). The page is a limit/offset on the primary key; the total is counted only up to
     * COUNT_CAP and flagged as capped when there are more.
     *
     * @param Request $request start, length, order[0][dir], draw
     * @param \Illuminate\Database\Eloquent\Builder $query already filtered
     * @param string $channel email | sms
     * @param string $defaultDirection asc | desc, when the request does not choose
     */
    protected function pagedLogResponse(Request $request, $query, string $channel, string $defaultDirection = 'desc') {
        $start = max(0, (int) $request->input('start', 0));
        $length = min(100, max(10, (int) $request->input('length', 50)));
        $direction = in_array($request->input('order.0.dir'), ['asc', 'desc'], true) ? $request->input('order.0.dir') : $defaultDirection;

        [$total, $capped] = $this->countCapped($query);

        $rows = (clone $query)->orderBy('id', $direction)->offset($start)->limit($length)->get()->map(fn ($log) => $this->logRow($log, $channel));

        return response()->json(['success' => true, 'draw' => (int) $request->input('draw', 0), 'recordsTotal' => $total, 'recordsFiltered' => $total, 'capped' => $capped, 'cap' => self::COUNT_CAP, 'count' => $total, 'data' => $rows]);
    }

    /**
     * How many rows match, counting at most $cap of them: the count stops after cap + 1 rows, so it
     * stays fast on a very large table.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query already filtered
     * @param int $cap
     * @return array [count (at most $cap), whether there are more than $cap]
     */
    protected function countCapped($query, int $cap = self::COUNT_CAP): array {
        $limited = (clone $query)->toBase()->reorder()->select('id')->limit($cap + 1);
        $n = (int) DB::query()->fromSub($limited, 'capped')->count();

        return [min($n, $cap), $n > $cap];
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
