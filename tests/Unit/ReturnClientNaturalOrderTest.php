<?php

namespace Tests\Unit;

use App\Models\ReturnClient;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ReturnClientNaturalOrderTest extends TestCase
{
    /**
     * Build return lines (no database needed) from [entry_type, unit_code, tenant_name] triples, in the given order.
     */
    private function lines(array $rows): Collection {
        return collect($rows)->map(fn($r) => new ReturnClient(['entry_type' => $r[0], 'unit_code' => $r[1], 'tenant_name' => $r[2] ?? null]));
    }

    private function units(Collection $lines): array {
        return $lines->pluck('unit_code')->all();
    }

    public function test_b2_comes_before_b10(): void
    {
        // The database's text order: B1, B10, B11, B2, B3
        $sorted = ReturnClient::sortNaturally($this->lines([
            ['RENT CURRENT', 'B1'], ['RENT CURRENT', 'B10'], ['RENT CURRENT', 'B11'], ['RENT CURRENT', 'B2'], ['RENT CURRENT', 'B3'],
        ]));

        $this->assertSame(['B1', 'B2', 'B3', 'B10', 'B11'], $this->units($sorted));
    }

    public function test_letters_group_first_then_numbers_inside_each_letter(): void
    {
        $sorted = ReturnClient::sortNaturally($this->lines([
            ['RENT CURRENT', 'B1'], ['RENT CURRENT', 'A10'], ['RENT CURRENT', 'A2'], ['RENT CURRENT', 'A1'], ['RENT CURRENT', 'B10'], ['RENT CURRENT', 'B2'],
        ]));

        $this->assertSame(['A1', 'A2', 'A10', 'B1', 'B2', 'B10'], $this->units($sorted));
    }

    public function test_zero_padded_and_suffixed_codes_keep_their_order(): void
    {
        $sorted = ReturnClient::sortNaturally($this->lines([
            ['RENT CURRENT', '010'], ['RENT CURRENT', '067B'], ['RENT CURRENT', '002'], ['RENT CURRENT', '067A'], ['RENT CURRENT', 'F10'], ['RENT CURRENT', 'F02'],
        ]));

        $this->assertSame(['002', '010', '067A', '067B', 'F02', 'F10'], $this->units($sorted));
    }

    public function test_case_does_not_matter(): void
    {
        $sorted = ReturnClient::sortNaturally($this->lines([['RENT CURRENT', 'b10'], ['RENT CURRENT', 'B2'], ['RENT CURRENT', 'b1']]));

        $this->assertSame(['b1', 'B2', 'b10'], $this->units($sorted));
    }

    public function test_entry_type_groups_stay_in_the_order_they_came_in(): void
    {
        // The database ordered the groups; only the units inside each group are re-ordered
        $sorted = ReturnClient::sortNaturally($this->lines([
            ['Commission', null], ['RENT ARREARS', 'B10'], ['RENT ARREARS', 'B2'], ['RENT CURRENT', 'B10'], ['RENT CURRENT', 'B2'], ['Service Charge', 'B10'], ['Service Charge', 'B2'],
        ]));

        $this->assertSame(['Commission', 'RENT ARREARS', 'RENT ARREARS', 'RENT CURRENT', 'RENT CURRENT', 'Service Charge', 'Service Charge'], $sorted->pluck('entry_type')->all());
        $this->assertSame([null, 'B2', 'B10', 'B2', 'B10', 'B2', 'B10'], $this->units($sorted));
    }

    public function test_lines_with_no_unit_go_last_in_their_group(): void
    {
        $sorted = ReturnClient::sortNaturally($this->lines([['Other', null], ['Other', 'B10'], ['Other', ''], ['Other', 'B2']]));

        $this->assertSame(['B2', 'B10'], array_slice($this->units($sorted), 0, 2));
        $this->assertCount(4, $sorted);
    }

    public function test_lines_of_the_same_unit_keep_their_tenant_name_order(): void
    {
        // Rows arrive from the database already ordered by tenant name inside the entry type
        $sorted = ReturnClient::sortNaturally($this->lines([
            ['RENT CURRENT', 'B2', 'ANNE'], ['RENT CURRENT', 'B1', 'ZAWADI'], ['RENT CURRENT', 'B2', 'ZAINAB'], ['RENT CURRENT', 'B1', 'ZED'],
        ]));

        $this->assertSame(['B1', 'B1', 'B2', 'B2'], $this->units($sorted));
        $this->assertSame(['ZAWADI', 'ZED', 'ANNE', 'ZAINAB'], $sorted->pluck('tenant_name')->all());
    }
}
