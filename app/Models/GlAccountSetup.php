<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlAccountSetup extends Model
{
    protected $table = 'sys_gl_accounts_setup';
    public $timestamps = false;

    protected $fillable = [
        'date_time',
        'retained_earnings_id',
        'income_summary_account_id',
        'receivable_control_id',
        'payable_control_account_id',
        'bank_charges_account_id',
        'vat_control_account_id',
        'other_income_account_id',
        'bank_interest_account_id',
        'accrued_payroll_expense_account_id',
        'payroll_expense_account_id',
        'net_salaries_account_id',
        'other_employee_deductions_id',
        'clients_account_id',
        'tenants_account_id',
        'revenues_account_id',
        'clients_bank_account_id',
        'petty_cash_account_id',
        'loans_receivable_account_id',
        'interests_receivable_account_id',
        'business_bank_account_id',
        'tenants_suspense_account_id',
        'revenue_vat',
        'loans_interests_revenue_account_id',
        'rent_commission_account_id',
        'active_fiscal_year_id',
        'returns_payable_account_id',
        'clients_advance_loans_payable_id',
        'co_op_bank_id',
        'equity_bank_account_id',
        'mpesa_account_id',
        'earned_commission_acc_id',
        'clients_vat',
        'deposits_payable_account_id',
        'miscallenous_revenue_id',
        'payment_voucher_control_account_id',
    ];
}
