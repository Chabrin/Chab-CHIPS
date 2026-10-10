<?php

namespace App\Http\Controllers;

use App\Models\DebtorInvoice;
use Illuminate\Http\Request;
use App\Services\ErrorDispatcher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Generated Invoices tab of the client page — lists real, already-created
 * invoices (sys_pm_debtors_invoices) for one property, with a details modal
 * and a per-invoice PDF download. Read-only: doesn't create or modify
 * invoices (see RentInvoicingController/UtilityInvoicingController for that).
 */
class ClientInvoicesController extends Controller
{
    /**
     * Client/tenant `names` on this app stores the salutation baked into the
     * string itself (e.g. "Mr John Kamau Mwangi"), not just in the separate
     * `title` column — strip it so the UI shows just the name.
     */
    private function stripSalutation(?string $name): string
    {
        return $name ? preg_replace('/^(Mr|Mrs|Ms|Dr|Prof)\.?\s+/i', '', trim($name)) : '-';
    }

    /**
     * Invoices for a property (AJAX, for the Generated Invoices DataTable).
     */
    public function data(Request $request)
    {
        try {
            $request->validate(['property_id' => 'required|integer|exists:sys_pm_properties,id']);

            $invoices = DebtorInvoice::with(['tenant:id,names', 'tenant.tenantRecord:id,names', 'unit:id,unit_code'])
                ->where('property_id', $request->property_id)
                ->tenantInvoices()
                ->orderByDesc('date_time')
                ->limit(500)
                ->get()
                ->map(function ($invoice) {
                    return [
                        'id' => $invoice->id,
                        'encrypted_id' => Crypt::encrypt($invoice->id),
                        'tenant_name' => $this->stripSalutation($invoice->tenant->names ?? null),
                        'unit_code' => $invoice->unit->unit_code ?? '-',
                        'return_type_id' => $invoice->return_type_id,
                        'reference_number' => $invoice->reference_number,
                        'returns_period' => $invoice->returns_period ? date('M Y', strtotime($invoice->returns_period)) : '-',
                        'date_due' => optional($invoice->date_due)->format('d M Y'),
                        'total_amount' => number_format($invoice->total_amount ?? 0, 2),
                        'current_balance' => number_format($invoice->current_balance ?? 0, 2),
                        'status_name' => $invoice->status_name,
                        'current_status_id' => $invoice->current_status_id,
                    ];
                });

            return response()->json(['data' => $invoices]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading generated invoices: ' . $e->getMessage());
            return response()->json(['data' => [], 'message' => 'Failed to load invoices'], 500);
        }
    }

    /**
     * Full detail for one invoice (AJAX, feeds the details modal).
     */
    public function details($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $invoice = DebtorInvoice::with(['tenant.tenantRecord', 'property', 'unit'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'invoice' => [
                    'reference_number' => $invoice->reference_number,
                    'narration' => $invoice->narration,
                    'tenant_name' => $this->stripSalutation($invoice->tenant->names ?? null),
                    'tenant_mobile' => $invoice->tenant->mobile_number ?? '-',
                    'property_name' => $invoice->property->property_name ?? '-',
                    'unit_code' => $invoice->unit->unit_code ?? '-',
                    'return_type_id' => $invoice->return_type_id,
                    'returns_period' => $invoice->returns_period ? date('F Y', strtotime($invoice->returns_period)) : '-',
                    'date_due' => optional($invoice->date_due)->format('d M Y'),
                    'total_amount' => number_format($invoice->total_amount ?? 0, 2),
                    'vat_ammount' => number_format($invoice->vat_ammount ?? 0, 2),
                    'comm_ammount' => number_format($invoice->comm_ammount ?? 0, 2),
                    'payable_amount' => number_format($invoice->payable_amount ?? 0, 2),
                    'current_balance' => number_format($invoice->current_balance ?? 0, 2),
                    'status_name' => $invoice->status_name,
                    'batch_number' => $invoice->batch_number,
                    'encrypted_id' => Crypt::encrypt($invoice->id),
                ],
            ]);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading invoice details: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load invoice details'], 500);
        }
    }

    /**
     * Download one invoice as a PDF — same DomPDF pattern used everywhere
     * else in this codebase (InvoicingController::exportTenantStatementPdf,
     * TenantExitController's PDF exports, ...), themed after
     * tenant-statement-pdf.blade.php.
     */
    public function pdf($encryptedId)
    {
        try {
            $id = Crypt::decrypt($encryptedId);
            $invoice = DebtorInvoice::with(['tenant.tenantRecord', 'property', 'unit'])->findOrFail($id);

            $pdf = Pdf::loadView('property.invoices.invoice-pdf', ['invoice' => $invoice]);
            $pdf->setPaper('A4', 'portrait');

            $fileName = 'Invoice-' . $invoice->reference_number . '.pdf';

            return $pdf->download($fileName);
        } catch (\Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error generating invoice PDF: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to generate invoice PDF');
        }
    }
}
