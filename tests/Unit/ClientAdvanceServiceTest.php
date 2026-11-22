<?php

namespace Tests\Unit;

use App\Services\ClientAdvanceService;
use App\Services\GlJournalPoster;
use Tests\TestCase;

class ClientAdvanceServiceTest extends TestCase
{
    public function test_limit_is_eighty_percent_of_a_year_of_average_returns(): void
    {
        $this->assertEquals(960000.0, (new ClientAdvanceService(new GlJournalPoster()))->availableLimit(100000, 0));
    }

    public function test_outstanding_advances_are_taken_off_the_limit(): void
    {
        $this->assertEquals(930000.0, (new ClientAdvanceService(new GlJournalPoster()))->availableLimit(100000, 30000));
    }

    public function test_limit_is_zero_when_outstanding_uses_it_all_up(): void
    {
        $this->assertEquals(0.0, (new ClientAdvanceService(new GlJournalPoster()))->availableLimit(100000, 960000));
    }

    public function test_limit_is_never_negative(): void
    {
        $this->assertEquals(0.0, (new ClientAdvanceService(new GlJournalPoster()))->availableLimit(100000, 2000000));
    }

    public function test_a_client_with_no_returns_has_no_limit(): void
    {
        $this->assertEquals(0.0, (new ClientAdvanceService(new GlJournalPoster()))->availableLimit(0, 0));
    }

    public function test_limit_is_rounded_to_cents(): void
    {
        $this->assertEquals(319999.97, (new ClientAdvanceService(new GlJournalPoster()))->availableLimit(33333.33, 0));
    }

    public function test_no_interest_adjustment_when_the_total_is_unchanged(): void
    {
        $this->assertNull((new ClientAdvanceService(new GlJournalPoster()))->interestAdjustment(95500, 95500));
    }

    public function test_a_change_below_a_cent_is_ignored(): void
    {
        $this->assertNull((new ClientAdvanceService(new GlJournalPoster()))->interestAdjustment(95500, 95500.004));
    }

    public function test_lower_total_after_a_restructure_reduces_the_interest(): void
    {
        $this->assertEquals(['increase' => false, 'amount' => 900.0], (new ClientAdvanceService(new GlJournalPoster()))->interestAdjustment(95500, 94600));
    }

    public function test_longer_term_at_the_same_rate_increases_the_interest(): void
    {
        $this->assertEquals(['increase' => true, 'amount' => 4050.0], (new ClientAdvanceService(new GlJournalPoster()))->interestAdjustment(95500, 99550));
    }

    public function test_adjustment_amount_is_rounded_to_cents(): void
    {
        $this->assertEquals(['increase' => true, 'amount' => 0.1], (new ClientAdvanceService(new GlJournalPoster()))->interestAdjustment(100.10, 100.20));
    }

    private function exceptions(): ClientAdvanceService
    {
        return new ClientAdvanceService(new GlJournalPoster());
    }

    public function test_no_exception_is_given_unless_the_form_asks_for_one(): void
    {
        $errors = ['amount' => ['Amount exceeds available limit']];

        $this->assertSame(['errors' => $errors, 'kind' => null], $this->exceptions()->applyException($errors, false, true, 'Long standing client, board approved'));
    }

    public function test_someone_allowed_overrules_the_limit_with_a_reason(): void
    {
        $out = $this->exceptions()->applyException(['amount' => ['over']], true, true, 'Long standing client, board approved');

        $this->assertSame([], $out['errors']);
        $this->assertSame('limit', $out['kind']);
    }

    public function test_the_two_active_advances_rule_is_its_own_kind_and_both_together_say_both(): void
    {
        $this->assertSame('active_advances', $this->exceptions()->applyException(['client_id' => ['max 2']], true, true, 'Settling the first one next week')['kind']);

        $both = $this->exceptions()->applyException(['amount' => ['over'], 'client_id' => ['max 2']], true, true, 'Settling the first one next week');
        $this->assertSame('both', $both['kind']);
        $this->assertSame([], $both['errors']);
    }

    public function test_someone_not_allowed_cannot_overrule_and_the_limit_error_stays(): void
    {
        $out = $this->exceptions()->applyException(['amount' => ['over']], true, false, 'Long standing client, board approved');

        $this->assertNull($out['kind']);
        $this->assertArrayHasKey('amount', $out['errors']);
        $this->assertArrayHasKey('limit_exception', $out['errors']);
    }

    public function test_an_exception_needs_a_real_reason(): void
    {
        $out = $this->exceptions()->applyException(['amount' => ['over']], true, true, 'ok');

        $this->assertNull($out['kind']);
        $this->assertArrayHasKey('amount', $out['errors']);
        $this->assertArrayHasKey('limit_exception_reason', $out['errors']);
    }

    public function test_other_errors_are_never_overruled(): void
    {
        $out = $this->exceptions()->applyException(['amount' => ['over'], 'disbursement_date' => ['too late']], true, true, 'Long standing client, board approved');

        $this->assertSame('limit', $out['kind']);
        $this->assertSame(['disbursement_date' => ['too late']], $out['errors']);
    }

    public function test_asking_for_an_exception_when_nothing_blocks_the_advance_records_none(): void
    {
        $this->assertSame(['errors' => [], 'kind' => null], $this->exceptions()->applyException([], true, true, 'Long standing client, board approved'));
    }
}
