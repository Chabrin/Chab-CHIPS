<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Two small settings HR can change without a developer. A missing or unreadable value falls back to its default. */
class PerformanceSetting extends Model
{
    /** The fewest committee members who must attend for a meeting or hearing to be recorded as held. */
    public const MIN_COMMITTEE = 'min_committee_present';

    /** How many days after a review is finalised the staff member can appeal on their own. */
    public const APPEAL_DAYS = 'appeal_window_days';

    public const DEFAULTS = [
        self::MIN_COMMITTEE => 1,
        self::APPEAL_DAYS => 14,
    ];

    protected $table = 'performance_settings';

    protected $fillable = ['key', 'value'];

    public static function number(string $key): int
    {
        $default = self::DEFAULTS[$key] ?? 0;
        $value = static::query()->where('key', $key)->value('value');

        return is_numeric($value) && (int) $value >= 0 ? (int) $value : $default;
    }

    public static function put(string $key, int $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
    }
}
