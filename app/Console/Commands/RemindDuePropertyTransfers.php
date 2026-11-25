<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Property;
use App\Models\PropertyOwnershipTransfer;
use App\Models\User;
use App\Services\PropertyTransferAccess;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RemindDuePropertyTransfers extends Command
{
    public const TYPE = 'PropertyOwnershipTransferDue';

    protected $signature = 'property-transfers:remind-due {--dry-run : Preview reminders without saving notifications} {--transfer= : Limit the check to one transfer ID}';

    protected $description = 'Remind authorized Directors to complete approved ownership transfers on their effective date';

    public function handle(): int
    {
        $today = CarbonImmutable::today('Africa/Nairobi')->toDateString();
        $created = 0;
        $due = PropertyOwnershipTransfer::where('status', 'approved')->whereDate('effective_date', $today);
        $transferId = $this->option('transfer');
        if ($transferId !== null) {
            if (! ctype_digit((string) $transferId) || (int) $transferId < 1) {
                $this->error('Use a positive transfer ID.');

                return self::FAILURE;
            }
            $due->whereKey((int) $transferId);
        }
        $this->info('Approved ownership transfers due today: '.(clone $due)->count().'.');

        // Clear reminders for completed/cancelled requests or dates that have passed.
        // Keep history; an old reminder must not continue to imply the transfer is executable.
        if (! $this->option('dry-run')) {
            Notification::where('type', self::TYPE)->unread()
                ->when($transferId !== null, fn ($query) => $query->where($this->notificationTransferKey(), $this->notificationTransferValue((int) $transferId)))
                ->whereNotIn($this->notificationTransferKey(), (clone $due)->pluck('id')->map(fn ($id) => (string) $id)->all())
                ->update(['read_at' => now()]);
        }

        $due->select('id')->chunkById(100, function ($transfers) use ($today, &$created) {
            foreach ($transfers as $item) {
                $created += DB::transaction(function () use ($item, $today) {
                    // Serialize repeated scheduler runs and completion/cancellation of this request.
                    $transfer = PropertyOwnershipTransfer::lockForUpdate()->find($item->id);
                    if (! $transfer || $transfer->status !== 'approved' || $transfer->effective_date->toDateString() !== $today) {
                        return 0;
                    }
                    $sent = 0;
                    User::where('block', false)->chunkById(100, function ($users) use ($transfer, &$sent) {
                        foreach ($users as $user) {
                            if (! PropertyTransferAccess::isDirector($user)
                                || ! PropertyTransferAccess::allows($user, 'view')
                                || ! PropertyTransferAccess::allows($user, 'execute')) {
                                continue;
                            }
                            $property = $this->visibleProperty($transfer, $user);
                            if (! $property) {
                                continue;
                            }
                            $alreadySent = Notification::where('type', self::TYPE)->where('notifiable_type', $user->getMorphClass())
                                ->where('notifiable_id', $user->id)->where($this->notificationTransferKey(), $this->notificationTransferValue($transfer->id))->exists();
                            if ($alreadySent) {
                                continue;
                            }
                            $sent++;
                            if ($this->option('dry-run')) {
                                continue;
                            }
                            Notification::create([
                                'type' => self::TYPE, 'notifiable_type' => $user->getMorphClass(), 'notifiable_id' => $user->id,
                                'data' => ['transfer_id' => $transfer->id, 'effective_date' => $transfer->effective_date->toDateString()],
                                'title' => 'Ownership transfer due today',
                                'message' => $transfer->reference.' — '.$property->property_name.' ('.$property->reference_number.') is approved for handover on '.$transfer->effective_date->format('d M Y').'. Open the request, check the handover position and complete the transfer before raising invoices.',
                                'action_url' => route('property-transfers.show', $transfer, false),
                            ]);
                        }
                    });

                    return $sent;
                });
            }
        });
        $this->info(($this->option('dry-run') ? 'Preview only — would create ' : 'Created ').$created.' in-app reminder(s). No ownership changes were made.');

        return self::SUCCESS;
    }

    private function notificationTransferKey(): Expression|string
    {
        // The notifications migration stores JSON in TEXT. PostgreSQL's JSON
        // operators require an explicit cast; the model's array cast only affects PHP.
        return DB::getDriverName() === 'pgsql'
            ? DB::raw('(CAST("data" AS jsonb) ->> \'transfer_id\')')
            : 'data->transfer_id';
    }

    private function notificationTransferValue(int $id): int|string
    {
        // PostgreSQL ->> returns text; keep native JSON values for other drivers.
        return DB::getDriverName() === 'pgsql' ? (string) $id : $id;
    }

    private function visibleProperty(PropertyOwnershipTransfer $transfer, User $user): ?Property
    {
        // Reuse the application's exact active-property and user-assignment scopes.
        // A console command has no signed-in user, so evaluate them as the recipient.
        $guard = Auth::guard();
        $previous = $guard->user();
        try {
            $guard->setUser($user);

            return Property::find($transfer->property_id);
        } finally {
            $previous ? $guard->setUser($previous) : $guard->forgetUser();
        }
    }
}
