<?php

namespace App\Services\BankStatement;

use App\Services\Comms\EmailService;
use App\Services\InvoiceBillingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The receipt for a payment that came in through a bank statement: what was received, which invoices it paid and what
 * is still owed on them, as a PDF emailed to the tenant. The email goes out through EmailService, the one way the system
 * sends mail, so it shows in the Communications logs whether it was sent or failed.
 */
class PaymentReceiptService
{
    protected EmailService $email;

    /**
     * @param EmailService $email
     */
    public function __construct(EmailService $email) {
        $this->email = $email;
    }

    /**
     * Email the receipt of one payment to the tenant and record the result on the statement line: Sent, Failed or
     * NoEmail when the tenant has no email address on file.
     *
     * @param string $bank equity or coop
     * @param int $lineId the statement line that was paid
     * @param int $paymentId
     * @param int|null $sentBy the user shown as the sender in the email log
     * @return string the receipt status written to the line
     */
    public function send(string $bank, int $lineId, int $paymentId, ?int $sentBy = null): string {
        $table = BankStatementBanks::get($bank)['table'];
        $data = $this->receiptData($paymentId);
        $status = 'NoEmail';

        if ($data['tenant_email']) {
            [$ok] = $this->email->send(
                $data['tenant_email'],
                $data['tenant_name'],
                'Payment receipt ' . $data['receipt_number'],
                $this->body($data),
                'receipt',
                null,
                'tenant',
                $data['tenant_id'],
                null,
                null,
                $this->pdf($data),
                'Receipt_' . $data['receipt_number'] . '.pdf',
                'application/pdf',
                'payment',
                $paymentId,
                $sentBy
            );

            $status = $ok ? 'Sent' : 'Failed';
        }

        DB::table($table)->where('id', $lineId)->update(['receipt_status' => $status, 'updated_at' => now()]);

        return $status;
    }

    /**
     * The receipt as PDF bytes.
     *
     * @param array $data from receiptData()
     */
    public function pdf(array $data): string {
        return Pdf::loadView('pdf.payment_receipt', $data)->setPaper('A4', 'portrait')->setOption(['enable_font_subsetting' => true])->output();
    }

    /**
     * Everything the receipt shows, read from the saved payment: who paid, for which unit, how much, and the invoices it
     * was allocated to with the balance left on each. Money received that no invoice could take yet is shown as credit.
     *
     * @param int $paymentId
     */
    public function receiptData(int $paymentId): array {
        $payment = DB::table('sys_pm_tenants_payments')->where('id', $paymentId)->first();
        $tenant = DB::table('sys_pm_clients')->where('id', $payment->tenant_id)->first(['id', 'names', 'email_address']);
        $unit = DB::table('sys_pm_units')->where('id', $payment->unit_id)->value('unit_code');
        $property = DB::table('sys_pm_properties')->where('id', $payment->property_id)->value('property_name');
        $bank = $payment->extract_id ? DB::table('cb_bank_statement_uploads')->where('id', $payment->extract_id)->value('bank') : null;

        $allocations = DB::table('sys_pm_tenants_payments_invoices as a')
            ->join('sys_pm_debtors_invoices as i', 'i.id', '=', 'a.invoice_id')
            ->leftJoin('sys_set_invoiceable_items as si', 'si.id', '=', 'i.item_type_id')
            ->where('a.payment_id', $paymentId)
            ->orderBy('a.id')
            ->get(['a.amount_allocated', 'a.new_invoice_balance', 'i.reference_number', 'i.narration', 'i.date_due', 'i.returns_period', 'i.item_type_id', 'i.return_type_id', 'si.item_name'])
            ->map(fn ($row) => [
                'invoice' => $row->reference_number,
                'description' => $row->narration ?: (InvoiceBillingService::itemName($row) . ' FOR ' . Carbon::parse($row->returns_period ?: $row->date_due)->format('F Y')),
                'allocated' => (float) $row->amount_allocated,
                'balance' => (float) $row->new_invoice_balance,
            ])->all();

        $allocated = array_sum(array_column($allocations, 'allocated'));

        return [
            'payment_id' => $paymentId,
            'receipt_number' => $payment->receipt_number,
            'payment_date' => Carbon::parse($payment->payment_date)->format('d M Y'),
            'amount' => (float) $payment->amount,
            'reference' => $payment->reference,
            'bank' => $bank ? (BankStatementBanks::all()[$bank]['label'] ?? $bank) : null,
            'tenant_id' => (int) $payment->tenant_id,
            'tenant_name' => $tenant->names ?? 'Tenant',
            'tenant_email' => $tenant && filter_var($tenant->email_address, FILTER_VALIDATE_EMAIL) ? $tenant->email_address : null,
            'unit' => $unit,
            'property' => $property,
            'allocations' => $allocations,
            'allocated_total' => round($allocated, 2),
            'credit' => round((float) $payment->amount - $allocated, 2),
        ];
    }

    /**
     * The short HTML the PDF travels in.
     *
     * @param array $data from receiptData()
     */
    private function body(array $data): string {
        $credit = $data['credit'] > 0.004 ? '<p>An amount of KES ' . number_format($data['credit'], 2) . ' is held as credit on your account and will be applied to your next invoice.</p>' : '';

        return '<p>Dear ' . e($data['tenant_name']) . ',</p>'
            . '<p>We have received your payment of <strong>KES ' . number_format($data['amount'], 2) . '</strong> on ' . e($data['payment_date'])
            . ' for ' . e(trim(($data['unit'] ?? '') . ' ' . ($data['property'] ?? ''))) . '. Your receipt number is <strong>' . e($data['receipt_number']) . '</strong>.</p>'
            . $credit
            . '<p>The receipt is attached.</p><p>Thank you.</p>';
    }
}
