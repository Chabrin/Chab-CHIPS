<?php

namespace App\Models;

use App\Services\StaffNumbers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A part-finished Add Staff form. The fields typed so far and the files attached are kept on the server, not in the browser,
 * because onboarding records hold ID numbers and office computers are shared. The payload is encrypted at rest and the files
 * live on the private disk. A draft belongs to the person who started it and goes when the staff member is saved.
 */
class StaffDraft extends Model
{
    protected $table = 'staff_drafts';

    protected $fillable = [
        'created_by', 'updated_by', 'title', 'payload', 'last_tab', 'stages',
        'onboarding_invitation_id', 'employment_type', 'staff_number', 'staff_id', 'completed_at',
    ];

    protected $casts = ['stages' => 'array', 'completed_at' => 'datetime'];

    /** The stages of onboarding on Add Staff are its tabs, in order, keyed by the tab's pane id. */
    public const STAGES = [
        'personal' => 'Personal Information',
        'employment' => 'Employment Details',
        'contact' => 'Contact Information',
        'banking' => 'Banking & Statutory',
        'documents' => 'Documents',
        'user' => 'System User',
    ];

    /** What a stage can be. 'skipped' is a tab that does not apply (System User for casual staff). */
    public const STAGE_STATUSES = ['complete', 'pending', 'problem', 'skipped'];

    /**
     * The stages of a staff member who has just been saved: everything on Add Staff is done, and System User does not apply when
     * the category gets no login.
     *
     * @return array<string, string>
     */
    public static function finishedStages(bool $skipsLogin): array
    {
        $stages = array_fill_keys(array_keys(self::STAGES), 'complete');
        if ($skipsLogin) {
            $stages['user'] = 'skipped';
        }

        return $stages;
    }

    /**
     * The stages as they were last saved, for every known stage: anything missing or not a known status is 'pending'.
     *
     * @return array<string, string>
     */
    public function stageStatuses(): array
    {
        $saved = is_array($this->stages) ? $this->stages : [];
        $out = [];
        foreach (array_keys(self::STAGES) as $key) {
            $out[$key] = in_array($saved[$key] ?? null, self::STAGE_STATUSES, true) ? $saved[$key] : 'pending';
        }

        return $out;
    }

    public function setPayloadAttribute(?array $value): void
    {
        $this->attributes['payload'] = Crypt::encryptString(json_encode($value ?? ['fields' => [], 'files' => []], JSON_THROW_ON_ERROR));
    }

    public function getPayloadAttribute(?string $value): array
    {
        return $value === null ? ['fields' => [], 'files' => []] : json_decode(Crypt::decryptString($value), true, 512, JSON_THROW_ON_ERROR);
    }

    /** Drafts still being worked on. A finished one is only what is left of it once the staff member is saved. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('completed_at');
    }

    /**
     * Drafts a user may see and open: every draft that is not finished. Whoever can add staff can continue anyone's, the way any of
     * them can review and continue an onboarding invitation, so that an onboarding is not stuck when the person who began it is away.
     * Only the people who can add staff reach drafts at all (the controllers check that); the creator is kept to show who began it.
     */
    public function scopeVisibleTo(Builder $query, ?int $userId = null): Builder
    {
        return $query->open();
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    /** What was typed into the form, by field name. */
    public function fields(): array
    {
        return $this->payload['fields'] ?? [];
    }

    /**
     * Files saved with the draft, keyed by their Add Staff form field ("photo", "documents.national_id", "signature").
     * Each has path (on the private disk), name, size and mime; documents also have type_name, as onboarding files do, so
     * they can be carried onto the new staff record the same way.
     */
    public function files(): array
    {
        return $this->payload['files'] ?? [];
    }

    public function directory(): string
    {
        return "staff_drafts/{$this->id}";
    }

    /**
     * Remove the draft and everything saved with it. The staff number it held goes back, and the drafts after it in that category
     * each take the number of the one before, so the numbers stay in one run.
     */
    public function discard(): void
    {
        StaffNumbers::locked(function () {
            DB::transaction(function () {
                if ($this->completed_at === null && filled($this->staff_number) && filled($this->employment_type)) {
                    StaffNumbers::closeGap($this->employment_type, $this->id);
                }
                $this->delete();
            });
            Storage::disk('local')->deleteDirectory($this->directory());
        });
    }

    /**
     * The staff member has been saved. Everything that was typed and every file goes (they now belong to the staff record), and
     * what stays is a small record of the onboarding: who it was, which stages were done, when it started and finished, and the
     * staff number. A number the draft held but the staff member did not end up with goes back and the others close up.
     */
    public function finish(Staff $staff): void
    {
        StaffNumbers::locked(function () use ($staff) {
            DB::transaction(function () use ($staff) {
                if (filled($this->staff_number) && $this->staff_number !== $staff->staff_number && filled($this->employment_type) && $this->exists) {
                    StaffNumbers::closeGap($this->employment_type, $this->id);
                }

                $this->payload = ['fields' => [], 'files' => []];
                $this->title = $this->title ?: Str::limit(mb_strtoupper((string) $staff->full_names), 255, '');
                $this->staff_id = $staff->id;
                $this->staff_number = $staff->staff_number;
                $this->employment_type = $staff->employment_type;
                $this->completed_at = now();
                $this->save();
            });
        });

        Storage::disk('local')->deleteDirectory($this->directory());
    }
}
