<?php

namespace App\Services;

use App\Models\EmploymentCategory;
use App\Models\Staff;
use App\Models\StaffDraft;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staff numbers held for part-finished staff records (drafts), so two people adding staff at the same time never end up with the
 * same number. Numbers run per employment category (Permanent plain, Contract CT-001 and so on).
 *
 * - A draft gets a number the first time it is saved: the next one after the highest number any staff member or any other draft
 *   already holds in its category.
 * - When a draft goes (discarded, purged, or moved to another category) the remaining drafts in that category close the gap: each
 *   takes the number of the draft before it, so the numbers stay in one run with no hole left by the draft that went.
 * - When a draft becomes a staff member its number becomes theirs, and the other drafts keep theirs.
 *
 * Everything here that reads and then writes numbers must run inside locked(), because two requests could read the same "next"
 * number at once. Staff numbers already given to real staff are never touched.
 */
class StaffNumbers
{
    private const LOCK_NAME = 'chab-staff-numbers';
    private const LOCK_ID = 7710250001;

    private static int $depth = 0;

    /**
     * Run $callback while holding the one lock that hands out staff numbers. Held until the work (including its transaction) is
     * finished; asking again inside the callback just carries on, since this request already has it.
     */
    public static function locked(callable $callback)
    {
        if (self::$depth > 0) {
            return $callback();
        }

        self::$depth++;
        try {
            $driver = DB::connection()->getDriverName();

            if ($driver === 'pgsql') {
                // Released by PostgreSQL when the transaction ends, so it covers the commit of whatever the callback saved
                return DB::transaction(function () use ($callback) {
                    DB::select('select pg_advisory_xact_lock(?)', [self::LOCK_ID]);

                    return $callback();
                });
            }

            if ($driver === 'mysql' || $driver === 'mariadb') {
                $got = DB::selectOne('select get_lock(?, 10) as got', [self::LOCK_NAME]);
                if ((int) ($got->got ?? 0) !== 1) {
                    throw new \RuntimeException('Staff numbers are being handed out to someone else right now. Try again in a moment.');
                }
                try {
                    return $callback();
                } finally {
                    DB::select('select release_lock(?)', [self::LOCK_NAME]);
                }
            }

            // SQLite (tests, local) has one writer at a time already
            return $callback();
        } finally {
            self::$depth--;
        }
    }

    /**
     * The employment category a draft's number is held in: the one chosen on the form when it is a real, switched-on category,
     * otherwise the one the draft already had, otherwise the default (Permanent when it is on).
     *
     * @param array<string, mixed> $fields the form's fields
     */
    public static function categoryFor(array $fields, ?StaffDraft $draft = null): string
    {
        $codes = EmploymentCategory::activeCodes();
        $sent = $fields['employment_type'] ?? null;
        if (is_string($sent) && in_array($sent, $codes, true)) {
            return $sent;
        }
        if ($draft && filled($draft->employment_type)) {
            return $draft->employment_type;
        }

        return in_array('Permanent', $codes, true) ? 'Permanent' : ($codes[0] ?? 'Permanent');
    }

    /**
     * Drafts that are still open and hold a number in this category, lowest number first.
     *
     * @return Collection<int, StaffDraft>
     */
    public static function reservations(string $category): Collection
    {
        if (!Schema::hasTable('staff_drafts') || !Schema::hasColumn('staff_drafts', 'staff_number')) {
            return collect();
        }

        return StaffDraft::query()
            ->whereNull('completed_at')
            ->where('employment_type', $category)
            ->whereNotNull('staff_number')
            ->get(['id', 'employment_type', 'staff_number'])
            ->sortBy([
                fn (StaffDraft $a, StaffDraft $b) => Staff::numberPart($a->staff_number) <=> Staff::numberPart($b->staff_number),
                fn (StaffDraft $a, StaffDraft $b) => $a->id <=> $b->id,
            ])
            ->values();
    }

    /** The highest number held by an open draft in this category (0 when none), leaving out one draft if given. */
    public static function highestReserved(string $category, ?int $exceptDraftId = null): int
    {
        return (int) self::reservations($category)
            ->reject(fn (StaffDraft $draft) => $exceptDraftId !== null && $draft->id === $exceptDraftId)
            ->map(fn (StaffDraft $draft) => Staff::numberPart($draft->staff_number))
            ->max();
    }

    /**
     * Give the draft a number in the category (on the model; the caller saves it). Nothing changes when it already holds one in
     * that category. When it moves from another category the draft there is taken out and that category closes up.
     * Call inside locked().
     */
    public static function reserve(StaffDraft $draft, string $category): void
    {
        if (filled($draft->staff_number) && $draft->employment_type === $category && $draft->completed_at === null) {
            return;
        }

        if ($draft->exists && filled($draft->staff_number) && filled($draft->employment_type)) {
            self::closeGap($draft->employment_type, $draft->id);
        }

        $next = max(Staff::highestStaffNumber($category), self::highestReserved($category, $draft->exists ? $draft->id : null)) + 1;
        $draft->employment_type = $category;
        $draft->staff_number = Staff::formatStaffNumber($category, $next);
    }

    /**
     * The draft is leaving its category (discarded, purged, or moving): every draft after it takes the number of the one before
     * it, so the run has no hole. The highest number in the run is the one freed. Call inside locked().
     */
    public static function closeGap(string $category, int $leavingDraftId): void
    {
        $held = self::reservations($category);
        if (!$held->contains(fn (StaffDraft $draft) => $draft->id === $leavingDraftId)) {
            return;
        }

        $slots = $held->pluck('staff_number')->all();
        array_pop($slots);

        $staying = $held->reject(fn (StaffDraft $draft) => $draft->id === $leavingDraftId)->values();
        foreach ($staying as $index => $draft) {
            if ($draft->staff_number !== $slots[$index]) {
                // A plain update: renumbering is not the person saving, so "last saved" is left alone
                DB::table('staff_drafts')->where('id', $draft->id)->update(['staff_number' => $slots[$index]]);
            }
        }
    }

    /**
     * The staff number for a new staff member: the number held by their draft when it is in the same category and nobody has
     * taken it, otherwise the next one free. Call inside locked().
     */
    public static function numberForNewStaff(string $category, ?StaffDraft $draft = null): string
    {
        if ($draft && filled($draft->staff_number) && $draft->employment_type === $category && $draft->completed_at === null
            && !Staff::where('staff_number', $draft->staff_number)->exists()) {
            return $draft->staff_number;
        }

        return Staff::nextStaffNumber($category);
    }
}
