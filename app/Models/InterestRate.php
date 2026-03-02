<?php

namespace App\Models;

use App\Observers\AuditLogsObserver;
use Illuminate\Database\Eloquent\Model;

class InterestRate extends Model
{
    protected static function boot()
    {
        parent::boot();
        static::observe(AuditLogsObserver::class);
    }
    protected $table = 'interest_rates';

    protected $fillable = [
        'rate_date',
        'start_date',
        'end_date',
        'period_code',
        'security_code',
        'rate',
        'remark',
        'added_on',
        'added_by',
        'confirmed',
        'confirmed_by',
        'confirmed_date',
        'used',
        'used_by',
        'used_date',
        'effective_annual_yield',
        'deleted',
        'deleted_by',
        'deleted_on',
        'deleted_reason'
    ];

    protected $casts = [
        'rate_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'rate' => 'decimal:4',
        'effective_annual_yield' => 'decimal:4',
        'added_on' => 'datetime',
        'confirmed_date' => 'datetime',
        'used_date' => 'datetime',
        'deleted_on' => 'datetime',
        'confirmed' => 'integer',
        'used' => 'integer'
    ];

    // Relationship with Fund (security_code)
    public function fund()
    {
        return $this->belongsTo(Fund::class, 'security_code', 'fund_code');
    }

    // Scope for non-deleted records
    public function scopeNotDeleted($query)
    {
        return $query->whereNull('deleted');
    }

    // Scope for confirmed records
    public function scopeConfirmed($query)
    {
        return $query->where('confirmed', 1);
    }

    // Scope for unconfirmed records
    public function scopeUnconfirmed($query)
    {
        return $query->where(function ($q) {
            $q->where('confirmed', '!=', 1)->orWhereNull('confirmed');
        });
    }

    /**
     * Calculate period dates based on fund's rate type and fiscal year start
     * 
     * @param Fund $fund
     * @param string $periodCode (e.g., 'Q1', 'M1', 'SM1', 'A')
     * @param int $fiscalYear
     * @return array ['start_date' => Carbon, 'end_date' => Carbon, 'period_code' => string]
     */
    public static function calculatePeriodDates($fund, $periodCode, $fiscalYear)
    {
        $fiscalYearStart = \Carbon\Carbon::parse($fund->fiscal_year_start);
        $startMonth = $fiscalYearStart->month;
        $startDay = $fiscalYearStart->day;

        $periodStart = \Carbon\Carbon::createFromDate($fiscalYear, $startMonth, $startDay);

        switch ($fund->rate_type) {
            case 'monthly':
                $monthOffset = (int) substr($periodCode, 1) - 1; // M1 = 0, M2 = 1, etc.
                $periodStart->addMonths($monthOffset);
                $periodEnd = $periodStart->copy()->addMonth()->subDay();
                break;

            case 'quarterly':
                $quarterOffset = ((int) substr($periodCode, 1) - 1) * 3; // Q1 = 0, Q2 = 3, etc.
                $periodStart->addMonths($quarterOffset);
                $periodEnd = $periodStart->copy()->addMonths(3)->subDay();
                break;

            case 'semi-annually':
                $semiOffset = ((int) substr($periodCode, 2) - 1) * 6; // SM1 = 0, SM2 = 6
                $periodStart->addMonths($semiOffset);
                $periodEnd = $periodStart->copy()->addMonths(6)->subDay();
                break;

            case 'annually':
                $periodEnd = $periodStart->copy()->addYear()->subDay();
                break;

            default:
                throw new \Exception("Invalid rate type: {$fund->rate_type}");
        }

        return [
            'start_date' => $periodStart,
            'end_date' => $periodEnd,
            'period_code' => $periodCode
        ];
    }

    /**
     * Get available periods for a fund based on rate type
     * 
     * @param Fund $fund
     * @return array
     */
    public static function getAvailablePeriods($fund)
    {
        switch ($fund->rate_type) {
            case 'monthly':
                return [
                    'M1' => 'Month 1',
                    'M2' => 'Month 2',
                    'M3' => 'Month 3',
                    'M4' => 'Month 4',
                    'M5' => 'Month 5',
                    'M6' => 'Month 6',
                    'M7' => 'Month 7',
                    'M8' => 'Month 8',
                    'M9' => 'Month 9',
                    'M10' => 'Month 10',
                    'M11' => 'Month 11',
                    'M12' => 'Month 12'
                ];

            case 'quarterly':
                return [
                    'Q1' => 'Quarter 1',
                    'Q2' => 'Quarter 2',
                    'Q3' => 'Quarter 3',
                    'Q4' => 'Quarter 4'
                ];

            case 'semi-annually':
                return [
                    'SM1' => 'Semi-Annual 1',
                    'SM2' => 'Semi-Annual 2'
                ];

            case 'annually':
                return ['A' => 'Annual'];

            default:
                return [];
        }
    }

    /**
     * Get the next available period for a fund in a given fiscal year
     * 
     * @param Fund $fund
     * @param int $fiscalYear
     * @return string|null
     */
    public static function getNextAvailablePeriod($fund, $fiscalYear)
    {
        $periods = array_keys(self::getAvailablePeriods($fund));

        // Get existing periods for this fund and fiscal year
        $existingPeriods = self::where('security_code', $fund->fund_code)
            ->whereNull('deleted')
            ->whereYear('start_date', $fiscalYear)
            ->pluck('period_code')
            ->toArray();

        // Find first period not yet used
        foreach ($periods as $period) {
            if (!in_array($period, $existingPeriods)) {
                return $period;
            }
        }

        return null; // All periods filled
    }

    /**
     * Determine current fiscal year based on fund's fiscal year start
     * 
     * @param Fund $fund
     * @return int
     */
    public static function getCurrentFiscalYear($fund)
    {
        $today = \Carbon\Carbon::today();
        $fiscalYearStart = \Carbon\Carbon::parse($fund->fiscal_year_start);
        $currentYearFiscalStart = \Carbon\Carbon::createFromDate($today->year, $fiscalYearStart->month, $fiscalYearStart->day);

        if ($today->lt($currentYearFiscalStart)) {
            return $today->year - 1;
        }

        return $today->year;
    }
}
