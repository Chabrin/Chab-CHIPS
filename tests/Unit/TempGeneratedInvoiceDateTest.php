<?php

namespace Tests\Unit;

use App\Models\TempGeneratedInvoice;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TempGeneratedInvoiceDateTest extends TestCase
{
    #[DataProvider('calendarDates')]
    public function test_staging_json_preserves_nairobi_calendar_dates(string $dueDate, string $period): void
    {
        $originalTimezone = date_default_timezone_get();
        date_default_timezone_set('Africa/Nairobi');

        try {
            $invoice = new TempGeneratedInvoice;
            $invoice->setDateFormat('Y-m-d H:i:s');
            $invoice->date_due = $dueDate;
            $invoice->returns_period = $period;

            $response = json_decode($invoice->toJson(), true, 512, JSON_THROW_ON_ERROR);

            $this->assertSame($dueDate, $response['date_due']);
            $this->assertSame($period, $response['returns_period']);
            $this->assertSame($dueDate, $invoice->date_due->format('Y-m-d'));
            $this->assertSame($period, $invoice->returns_period->format('Y-m-d'));
        } finally {
            date_default_timezone_set($originalTimezone);
        }
    }

    public static function calendarDates(): array
    {
        return [
            'reported due date' => ['2026-11-28', '2026-11-01'],
            'month boundary' => ['2026-12-01', '2026-12-01'],
            'year boundary' => ['2027-01-01', '2027-01-01'],
            'leap day' => ['2028-02-29', '2028-02-01'],
        ];
    }
}
