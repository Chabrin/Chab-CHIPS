<?php

namespace Tests\Unit;

use App\Exceptions\BankStatementException;
use App\Services\BankStatement\CoopStatementParser;
use App\Services\BankStatement\EquityStatementParser;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * The two bank templates are read into line rows: the unit code is found, amounts and dates are read whatever way the
 * cell holds them, and every row is judged Pending, Invalid or Skipped. A file in the wrong template is refused.
 */
class BankStatementParserTest extends TestCase
{
    const EQUITY_HEADERS = ['TRAN_DATE', 'SECURITY NO.', 'TRAN_AMT', 'TRAN_TYPE', 'VALUE_DATE', 'REF_NUM', 'TRAN_RMKS', 'SECURITY NO 2', 'TRAN_PARTICULAR', 'MODE OF PAYMENT'];
    const COOP_HEADERS = ['REFERENCE', 'POSTINGDATE', 'VALUEDATE', 'CODE', 'ST_NAME', 'CREDITACCOUNT', 'AMOUNT', 'USERID', 'PAYMENTMETHOD', 'CHEQUENUMBER', 'NARRATION'];

    protected array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    /**
     * Write rows to a temporary xlsx file and give its path.
     *
     * @param array $rows header row first
     */
    private function sheet(array $rows): string {
        $book = new Spreadsheet();
        $book->getActiveSheet()->fromArray($rows, null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'stmt') . '.xlsx';
        (new Xlsx($book))->save($path);

        return $this->files[] = $path;
    }

    public function test_equity_row_gives_unit_code_payer_amount_and_date_from_an_excel_serial(): void
    {
        // 46336 is 10 Nov 2026 as Excel stores it
        $path = $this->sheet([self::EQUITY_HEADERS, [46336, 'S26231428', 7000, 'CREDIT', 46336, '00KECYeUNL8Lvb', '', 'KECYeUNL8Lvb', '187G-8/Akinyi/KECYeUNL8Lvb/KECYeUNL8Lvb/10-11-2026', 'CASH']]);

        $line = (new EquityStatementParser())->parse($path)[0];

        $this->assertSame(2, $line['row_no']);
        $this->assertSame('2026-11-10', $line['tran_date']);
        $this->assertSame(7000.0, $line['tran_amt']);
        $this->assertSame('00KECYeUNL8Lvb', $line['ref_num']);
        $this->assertSame('187G-8', $line['unit_code']);
        $this->assertSame('Akinyi', $line['payer_name']);
        $this->assertSame('Pending', $line['match_status']);
    }

    public function test_equity_line_without_a_unit_code_is_still_pending_with_no_code(): void
    {
        $path = $this->sheet([self::EQUITY_HEADERS, [46336, 'S26396756', 14910, 'CREDIT', 46336, '00hzO6tCyp9G1r', '', 'UKAMP1YJSJ', 'UKAMP1YJSJ Barnabus Mukungu Mutinda 0430263787755/', 'CASH']]);

        $line = (new EquityStatementParser())->parse($path)[0];

        $this->assertNull($line['unit_code']);
        $this->assertNull($line['payer_name']);
        $this->assertSame('Pending', $line['match_status']);
    }

    public function test_equity_unit_code_is_found_in_the_middle_of_the_text_when_only_one_is_there(): void
    {
        $parser = new EquityStatementParser();

        $this->assertSame('406A-F2-23', $parser->unitCode('RENT FOR 406A-F2-23 NOV'));
        $this->assertSame('379D-SHOP8', $parser->unitCode('379d-shop8/Yaka'));
        $this->assertNull($parser->unitCode('RENT 187G-8 AND 187G-9'), 'two different codes cannot be told apart');
        $this->assertNull($parser->unitCode('KECYeUNL8Lvb 10-11-2026 0430263787755'));
    }

    public function test_a_debit_line_is_skipped_and_unreadable_rows_are_invalid(): void
    {
        $path = $this->sheet([
            self::EQUITY_HEADERS,
            [46336, 'S1', 5000, 'DEBIT', 46336, 'R1', '', '', '187G-8/X', 'CASH'],
            [46336, 'S2', 'abc', 'CREDIT', 46336, 'R2', '', '', '187G-8/X', 'CASH'],
            ['not a date', 'S3', 100, 'CREDIT', 'x', 'R3', '', '', '187G-8/X', 'CASH'],
            [46336, '', 100, 'CREDIT', 46336, '', '', '', '187G-8/X', 'CASH'],
        ]);

        $lines = (new EquityStatementParser())->parse($path);

        $this->assertSame('Skipped', $lines[0]['match_status']);
        $this->assertSame('Invalid', $lines[1]['match_status']);
        $this->assertStringContainsStringIgnoringCase('amount', $lines[1]['match_note']);
        $this->assertSame('Invalid', $lines[2]['match_status']);
        $this->assertStringContainsStringIgnoringCase('date', $lines[2]['match_note']);
        $this->assertSame('Invalid', $lines[3]['match_status']);
        $this->assertStringContainsStringIgnoringCase('reference', $lines[3]['match_note']);
    }

    public function test_written_dates_and_comma_amounts_are_read(): void
    {
        $path = $this->sheet([self::COOP_HEADERS, ['CHQ-1', '10/11/2026', '10/11/2026', '187G-8', 'Akinyi', '0123', 'KES 12,500.50', 'u1', 'CASH', '', 'rent']]);

        $line = (new CoopStatementParser())->parse($path)[0];

        $this->assertSame('2026-11-10', $line['posting_date']);
        $this->assertSame(12500.5, $line['amount']);
        $this->assertSame('187G-8', $line['unit_code']);
        $this->assertSame('Akinyi', $line['payer_name']);
        $this->assertSame('Pending', $line['match_status']);
    }

    public function test_coop_unit_code_falls_back_to_the_narration(): void
    {
        $path = $this->sheet([self::COOP_HEADERS, ['R9', '2026-11-10', '', 'ABC', 'Akinyi', '0123', 1000, 'u1', 'CASH', '', 'Rent 76F-F4 Nov']]);

        $this->assertSame('76F-F4', (new CoopStatementParser())->parse($path)[0]['unit_code']);
    }

    public function test_blank_rows_are_skipped_and_the_header_may_sit_below_a_title_row(): void
    {
        $path = $this->sheet([['Equity statement'], self::EQUITY_HEADERS, [], [46336, 'S1', 100, 'CREDIT', 46336, 'R1', '', '', '76F-F4/X', 'CASH']]);

        $lines = (new EquityStatementParser())->parse($path);

        $this->assertCount(1, $lines);
        $this->assertSame(4, $lines[0]['row_no']);
    }

    public function test_a_file_in_the_wrong_template_is_refused_with_the_missing_columns(): void
    {
        $coop = $this->sheet([self::COOP_HEADERS]);

        $this->expectException(BankStatementException::class);
        $this->expectExceptionMessage('This does not look like the Equity Bank template. Missing columns: TRAN_DATE');

        (new EquityStatementParser())->parse($coop);
    }

    public function test_a_file_that_is_not_a_spreadsheet_is_refused(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'stmt') . '.xlsx';
        file_put_contents($path, "\x00\x01\x02 not a workbook");
        $this->files[] = $path;

        $this->expectException(BankStatementException::class);

        (new CoopStatementParser())->parse($path);
    }

    /**
     * The Co-op file as the bank sends it: capitals month with a time and trailing tabs, the unit code in CODE (even with
     * a "/" or a space in it), and a blank CODE read from the first "~" part or any code in the narration.
     */
    public function test_a_real_coop_file_is_read_with_its_dates_and_every_form_of_unit_code(): void
    {
        $tabs = "\n\t\t\t";
        $path = $this->sheet([
            self::COOP_HEADERS,
            ['UKDC5238L9' . $tabs, '13-NOV-2026 00:21:34' . $tabs, '13-NOV-2026 00:00:00', '49c-025' . $tabs, '49c-025', '0114', '100' . $tabs, 'FIVUSR', 'IBAPS', '0', 'UKDC5238L9~759625~254722796519~49c-025~WAFULA~2026-11-13'],
            ['U2', '13-NOV-2026 08:00:00', '13-NOV-2026', '12F-B/S', '12F-B/S', '0114', '500', 'U', 'POS', '0', 'POS'],
            ['27E-28~M.D', '13-NOV-2026 09:00:00', '13-NOV-2026', '', '', '0114', '300', 'U', 'BR', '0', '73E-SHOP 1~000013~NEW SUNCITY -ROOM No. SHOP 1'],
            ['UKCUH81YND', '13-NOV-2026 10:00:00', '13-NOV-2026', '', '', '0114', '200', 'U', 'FPC', '0', '752624:MBANKING~UKCUH81YND   4165147   179G-B101 ~E CHANNELS~ENOV3'],
            ['UKDBR1QDM2', '13-NOV-2026 11:00:00', '13-NOV-2026', '', '', '0114', '100', 'U', 'IBAPS', '0', 'UKDBR1QDM2~254703952213~01148039906100~MPESAC2B_400200~MICHAEL KANYARA'],
        ]);

        $lines = (new CoopStatementParser())->parse($path);

        $this->assertSame('2026-11-13', $lines[0]['posting_date']);
        $this->assertSame('Pending', $lines[0]['match_status']);
        $this->assertSame(100.0, $lines[0]['amount']);
        $this->assertSame('49C-025', $lines[0]['unit_code']);
        $this->assertSame('12F-B/S', $lines[1]['unit_code'], 'the slash is part of the code');
        $this->assertSame('73E-SHOP 1', $lines[2]['unit_code']);
        $this->assertSame('179G-B101', $lines[3]['unit_code']);
        $this->assertNull($lines[4]['unit_code'], 'an M-Pesa line carries a mobile number, not a unit code');
    }

    /**
     * The Equity file as the bank sends it: the code in REF_NUM, inside "APP|code/Rent", or in TRAN_RMKS for a cheque,
     * whose missing REF_NUM falls back to the bank's own transaction number.
     */
    public function test_a_real_equity_file_finds_app_and_cheque_codes_and_gives_a_cheque_a_reference(): void
    {
        $path = $this->sheet([
            self::EQUITY_HEADERS,
            [46339, 'S1', 21100, 'CREDIT', 46339, '56G-B9', 'MIKE ROOM B9', '194460', '56G-B9/MIKE MAYAS RO/631713521341/13-11-2026 09:', 'CASH'],
            [46339, 'S2', 3000, 'CREDIT', 46339, '00KEyXHH7fZIeN', '', 'KEyXHH7fZIeN', 'APP|375F-61/Rent/KEyXHH7fZIeN', 'CASH'],
            [46339, 'S3', 24000, 'CREDIT', 46343, '', '170F-B05', '', 'Chq:009190 ASHLEYS KENYA LTD', 'CHEQUE'],
            [46339, 'S4', 12000, 'CREDIT', 46338, '001fXGL4mEgyjO', '', 'UKD9T1SRB7', 'FRIDAH GAKII|UKD9T1SRB7 FRIDAH GAKII 043026378775', 'CASH'],
        ]);

        $lines = (new EquityStatementParser())->parse($path);

        $this->assertSame('56G-B9', $lines[0]['unit_code']);
        $this->assertSame('375F-61', $lines[1]['unit_code']);
        $this->assertSame('170F-B05', $lines[2]['unit_code']);
        $this->assertSame('S3', $lines[2]['ref_num']);
        $this->assertSame('Pending', $lines[2]['match_status']);
        $this->assertNull($lines[3]['unit_code'], 'a paybill line quotes no unit code');
    }
}
