<?php

namespace App\Services\Performance;

/**
 * The overall score of a review: the average of the 1 to 10 ratings, each counted by its criterion's weight. A criterion
 * nobody has rated yet is left out of the average, never counted as zero. Equal weights give a plain average.
 */
class PerformanceScorer
{
    public const MIN_RATING = 1;

    public const MAX_RATING = 10;

    /**
     * @param  iterable  $items  rows or models with a weight and self_rating, supervisor_rating and hr_rating
     * @param  string  $who  'self', 'supervisor' or 'hr'
     */
    public static function score(iterable $items, string $who): ?float
    {
        $sum = 0.0;
        $weights = 0.0;
        foreach ($items as $item) {
            $rating = data_get($item, $who.'_rating');
            $weight = (float) data_get($item, 'weight', 1);
            if ($rating === null || $rating === '' || $weight <= 0) {
                continue;
            }
            $sum += $weight * (float) $rating;
            $weights += $weight;
        }

        return $weights > 0 ? round($sum / $weights, 2) : null;
    }

    /** Whether every criterion has been rated by this person, which is what submitting needs. */
    public static function isComplete(iterable $items, string $who): bool
    {
        $count = 0;
        foreach ($items as $item) {
            $count++;
            $rating = data_get($item, $who.'_rating');
            if ($rating === null || $rating === '') {
                return false;
            }
        }

        return $count > 0;
    }

    public static function isValidRating($value): bool
    {
        return is_int($value) && $value >= self::MIN_RATING && $value <= self::MAX_RATING;
    }
}
