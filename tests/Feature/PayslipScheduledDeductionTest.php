<?php

namespace Tests\Feature;

use App\Http\Controllers\PayslipsController;
use App\Models\PayrollSummary;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

/**
 * A scheduled deduction (loan, advance...) on a payslip also says what is still owed after that month (UAT A6).
 */
class PayslipScheduledDeductionTest extends TestCase
{
    private function payroll(): PayrollSummary
    {
        $payroll = new PayrollSummary();
        $payroll->forceFill([
            'basic_salary' => 50000, 'period' => '2026-10-01', 'date_time' => '2026-10-31 10:00:00', 'total_deductions' => 12000, 'net_pay' => 38000,
            'tax_payable' => 6000, 'nssf' => 3000, 'nhif' => 1375, 'housing_levy' => 750, 'worked_days' => 31, 'staff_number_id' => 'E-001',
        ]);

        return $payroll;
    }

    /** The lines as PayslipsController hands them to the payslip. */
    private function elements(?float $balanceAfter): \Illuminate\Support\Collection
    {
        return collect([
            ['element_id' => 9, 'element_name' => 'PAYE', 'element_type' => '1', 'payslip_position' => 40, 'amount' => 6000, 'balance_after' => null],
            ['element_id' => 0, 'element_name' => 'Salary advance', 'element_type' => '1', 'payslip_position' => 60, 'amount' => 2000, 'balance_after' => $balanceAfter],
        ]);
    }

    public function test_the_payslip_preview_shows_what_is_still_owed_after_the_deduction(): void
    {
        $html = view('payroll.partials.payslip-preview', ['payroll' => $this->payroll(), 'elements' => $this->elements(19500)])->render();

        $this->assertStringContainsString('Salary advance', $html);
        $this->assertStringContainsString('Balance: 19,500.00', $html);
        $this->assertSame(1, substr_count($html, 'Balance:'), 'only the scheduled deduction has a balance, not PAYE');
    }

    public function test_the_payslip_pdf_shows_what_is_still_owed_after_the_deduction(): void
    {
        $html = view('payroll.partials.payslip-pdf', ['payroll' => $this->payroll(), 'elements' => $this->elements(19500)])->render();

        $this->assertStringContainsString('Salary advance', $html);
        $this->assertStringContainsString('Balance: 19,500.00', $html);
        $this->assertSame(1, substr_count($html, 'Balance:'));
    }

    public function test_the_last_instalment_says_it_is_fully_paid(): void
    {
        foreach (['payslip-preview', 'payslip-pdf'] as $view) {
            $html = view('payroll.partials.' . $view, ['payroll' => $this->payroll(), 'elements' => $this->elements(0)])->render();

            $this->assertStringContainsString('Balance: 0.00 (fully paid)', $html, $view);
        }
    }

    public function test_a_payslip_without_scheduled_deductions_is_unchanged(): void
    {
        $elements = collect([['element_id' => 9, 'element_name' => 'PAYE', 'element_type' => '1', 'payslip_position' => 40, 'amount' => 6000]]);

        foreach (['payslip-preview', 'payslip-pdf'] as $view) {
            $html = view('payroll.partials.' . $view, ['payroll' => $this->payroll(), 'elements' => $elements])->render();

            $this->assertStringNotContainsString('Balance:', $html, $view);
        }
    }

    public function test_the_balance_survives_the_trip_from_the_stored_payslip_to_the_view(): void
    {
        Schema::create('sys_hr_payroll_elements', function (Blueprint $t) {
            $t->id();
            $t->string('element_name')->nullable();
            $t->string('element_key')->nullable();
            $t->string('element_type')->nullable();
            $t->integer('payslip_position')->nullable();
        });
        $stored = [
            ['element_id' => 9, 'element_name' => 'PAYE', 'element_type' => '1', 'payslip_position' => 40, 'amount' => 6000],
            ['element_id' => 0, 'element_name' => 'Salary advance', 'element_type' => '1', 'payslip_position' => 60, 'amount' => 2000, 'scheduled_deduction_id' => 4, 'balance_after' => 19500.0],
        ];

        $method = new ReflectionMethod(PayslipsController::class, 'getPayslipElements');
        $method->setAccessible(true);
        $elements = $method->invoke(app(PayslipsController::class), $stored, null);

        $this->assertEquals(19500, $elements->firstWhere('element_name', 'Salary advance')['balance_after']);
        $this->assertNull($elements->firstWhere('element_name', 'PAYE')['balance_after']);
    }
}
