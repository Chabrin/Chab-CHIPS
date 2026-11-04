<?php

namespace Tests\Unit;

use App\Exports\ReturnStatementExport;
use App\Models\ReturnClient;
use App\Models\ReturnSummary;
use App\Services\ReturnStatementService;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Tests\TestCase;

class ReturnStatementServiceTest extends TestCase
{
    private function line(int $id, string $type, float $amount, array $extra = []): ReturnClient
    {
        $line = new ReturnClient();
        $line->forceFill(array_merge(['id' => $id, 'entry_type' => $type, 'net_amount' => $amount,
            'property_code' => 'P1', 'unit_code' => 'B2', 'tenant_name' => 'Anne', 'remarks' => ''], $extra));
        return $line;
    }

    public function test_signed_balances_and_totals_survive_pivoting_without_mixing_properties(): void
    {
        $data = app(ReturnStatementService::class)->build(collect([
            $this->line(1, 'RENT CURRENT', 20000), $this->line(2, 'RENT ARREARS', -3000),
            $this->line(3, 'RENT CURRENT', 10000, ['unit_code' => 'B10']),
            $this->line(4, 'RENT CURRENT', 12000, ['property_code' => 'P2']),
            $this->line(5, 'Commission', 2000, ['unit_code' => null, 'tenant_name' => null]),
        ]));
        $this->assertCount(3, $data['tenantRows']);
        $this->assertSame(['B2', 'B2', 'B10'], array_column($data['tenantRows'], 'unit_code'));
        $this->assertSame(-3000.0, $data['totals']['rent_arrears']);
        $this->assertSame(42000.0, $data['totals']['monthly_rent']);
    }

    public function test_note_comes_from_the_current_rent_line_regardless_of_line_order(): void
    {
        $service = app(ReturnStatementService::class);
        $lines = collect([$this->line(3, 'Water Bill', 500, ['remarks' => 'Water note']),
            $this->line(2, 'RENT CURRENT', 10000, ['remarks' => 'Tenant note']),
            $this->line(1, 'RENT ARREARS', 2000, ['remarks' => 'Older note'])]);
        foreach ([$lines, $lines->reverse()] as $input) {
            $row = $service->build($input)['tenantRows'][0];
            $this->assertSame(2, $row['remarks_line_id']);
            $this->assertSame('Tenant note', $row['remarks']);
        }
        $this->assertSame('Water note', $lines[0]->remarks);
    }

    public function test_prepaid_rent_does_not_get_mixed_into_current_month_rent(): void
    {
        $data = app(ReturnStatementService::class)->build(collect([
            $this->line(1, 'RENT CURRENT', 20000), $this->line(2, 'RENT PREPAYMENT', -5000),
        ]));
        $this->assertSame(-5000.0, $data['tenantRows'][0]['rent_arrears']);
        $this->assertSame(20000.0, $data['tenantRows'][0]['monthly_rent']);
    }

    public function test_excel_matches_statement_columns_and_treats_remarks_as_text(): void
    {
        $return = new ReturnSummary(['return_code' => 'R1', 'commission_total' => 20000, 'commission_rate' => 10]);
        $lines = collect([$this->line(1, 'RENT CURRENT', 20000, ['remarks' => '=1+1', 'commission_amount' => 2000, 'vat_amount' => 320])]);
        $service = app(ReturnStatementService::class);
        $data = $service->build($lines);
        $data['summary'] = $service->summary($return, $lines, $data['totals']);
        $book = app(ReturnStatementExport::class)->build($return, $data,
            ['landlord_label' => 'Landlord', 'property_label' => 'Property', 'period' => 'November 2026'], []);
        $sheet = $book->getActiveSheet();
        $this->assertSame('Monthly Rent', $sheet->getCell('L8')->getValue());
        $this->assertSame('Remarks', $sheet->getCell('M8')->getValue());
        $this->assertSame('Arrears / Prepayments', $sheet->getCell('D8')->getValue());
        $this->assertSame('=1+1', $sheet->getCell('M9')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('M9')->getDataType());
        $this->assertSame(17680.0, $data['summary']['net_income']);
        $values = array_column($sheet->toArray(), 0);
        $this->assertSame(1, count(array_filter($values, fn($value) => $value === 'Prepared by')));
        $this->assertStringNotContainsString('Prepared', $sheet->getHeaderFooter()->getOddFooter());
        $book->disconnectWorksheets();
    }

    public function test_screen_escapes_notes_and_places_summary_after_the_tenant_table(): void
    {
        $return = new ReturnSummary(['id' => 1, 'return_code' => 'R1', 'commission_total' => 10000]);
        $lines = collect([$this->line(1, 'RENT CURRENT', 10000, ['remarks' => '<script>alert(1)</script>'])]);
        $service = app(ReturnStatementService::class);
        $data = $service->build($lines);
        $data['summary'] = $service->summary($return, $lines, $data['totals']);
        $html = view('property.processed-returns.partials.statement', array_merge($data, ['return' => $return]))->render();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertLessThan(strpos($html, 'Financial summary'), strpos($html, '</table>'));
        $this->assertStringContainsString('statement-save', $html);
    }

    public function test_both_detail_pages_keep_processing_controls_and_the_statement_container(): void
    {
        $return = new ReturnSummary(['id' => 1, 'return_code' => 'R1', 'return_type' => 'MAINRETURN', 'paid_status' => 0]);
        $headerInfo = ['return_code' => 'R1', 'property_label' => 'Sample property', 'landlord_label' => 'Sample landlord',
            'period' => '11/2026', 'return_type' => 'MAINRETURN'];
        foreach (['property.processed-returns.details', 'property.returns.unprocessed-details'] as $view) {
            $html = view($view, ['return' => $return, 'lineTotals' => (object) [],
                'allReturns' => collect([$return]), 'headerInfo' => $headerInfo])->render();
            $this->assertStringContainsString('id="return-transaction-details"', $html);
            $this->assertStringContainsString('id="return-statement"', $html);
            $this->assertStringContainsString('return-statement.js', $html);
        }
    }
}
