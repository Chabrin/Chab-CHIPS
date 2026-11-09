<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\User;
use Tests\Concerns\RendersPagesWithPlainLayout;
use Tests\TestCase;

/**
 * The "Recurring for all staff" table on the contract forms draws NSSF as Tier I, Tier II and a total,
 * using the rows helper and the preview endpoint.
 */
class ContractAllStaffTableViewTest extends TestCase
{
    use RendersPagesWithPlainLayout;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usePlainLayout();
        $user = \Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermission')->andReturn(true);
        $user->id = 7;
        $this->actingAs($user);
    }

    protected function tearDown(): void
    {
        $this->removePlainLayout();
        parent::tearDown();
    }

    private function createPage(): string
    {
        return view('staff.contracts.contracts_create', ['staffSignedContracts' => []])->render();
    }

    private function editPage(): string
    {
        $contract = new Contract();
        $contract->forceFill(['id' => 3, 'staff_id' => 5, 'basic_salary' => 50000]);
        $contract->setRelation('signedDocument', null);

        return view('staff.contracts.contracts_edit', [
            'contract' => $contract, 'visibleElements' => collect(), 'hiddenElements' => collect(), 'staffCopy' => null,
        ])->render();
    }

    public function test_both_contract_forms_load_the_rows_helper_before_the_page_script(): void
    {
        foreach ([$this->createPage(), $this->editPage()] as $page) {
            $this->assertSame(1, preg_match('/<script src="[^"]*assets\/js\/payroll-all-staff-rows\.js\?v=\d+"><\/script>/', $page, $tag, PREG_OFFSET_CAPTURE), 'The rows helper is not loaded');
            $this->assertLessThan(strpos($page, 'PayrollAllStaffRows.build'), $tag[0][1]);
        }
    }

    public function test_both_contract_forms_draw_the_table_from_the_helper_and_the_preview(): void
    {
        foreach ([$this->createPage(), $this->editPage()] as $page) {
            $this->assertStringContainsString('id="allStaffItems"', $page);
            $this->assertStringContainsString('/ajax/payroll/elements/all-staff-preview', $page);
            $this->assertStringContainsString('PayrollAllStaffRows.build(res.elements)', $page);
        }
    }

    public function test_the_tier_rows_are_indented_and_the_total_is_bold(): void
    {
        $page = $this->createPage();

        $this->assertStringContainsString("toggleClass('ps-4', row.kind === 'tier')", $page);
        $this->assertStringContainsString("total: 'fw-bold table-light'", $page);
    }
}
