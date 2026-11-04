<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientAdvance;
use App\Models\PaymentVoucherHeader;
use App\Models\ReturnSummary;
use App\Models\DebtorInvoice;
use App\Models\Property;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class SearchController extends Controller
{
    /**
     * Search clients, staff, properties, and invoices for the given term and
     * show the results grouped by entity type.
     *
     * @param Request $request
     */
    public function index(Request $request)
    {
        $term = trim((string) $request->get('q', ''));

        if (strlen($term) < 2) {
            return view('search.index', [
                'term' => $term,
                'clients' => collect(),
                'staff' => collect(),
                'properties' => collect(),
                'invoices' => collect(),
                'returns' => collect(),
                'vouchers' => collect(),
                'advances' => collect(),
            ]);
        }

        $needle = strtolower($term);

        $clients = Client::where(function ($query) use ($needle) {
                $query->whereRaw('LOWER(names) LIKE ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(second_name) LIKE ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(email_address) LIKE ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(reference_number) LIKE ?', ["%{$needle}%"])
                    ->orWhere('mobile_number', 'LIKE', "%{$needle}%")
                    ->orWhere('national_id', 'LIKE', "%{$needle}%");
            })
            ->orderBy('names')
            ->limit(25)
            ->get(['id', 'names', 'second_name', 'last_name', 'mobile_number', 'email_address', 'reference_number']);

        $staff = Staff::where(function ($query) use ($needle) {
                $query->whereRaw('LOWER(full_names) LIKE ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(email_address) LIKE ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(staff_number) LIKE ?', ["%{$needle}%"])
                    ->orWhere('mobile_number', 'LIKE', "%{$needle}%");
            })
            ->orderBy('full_names')
            ->limit(25)
            ->get(['id', 'full_names', 'staff_number', 'mobile_number', 'email_address'])
            ->each(function ($member) {
                $member->encrypted_id = Crypt::encrypt($member->id);
            });

        $properties = Property::where(function ($query) use ($needle) {
                $query->whereRaw('LOWER(property_name) LIKE ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(reference_number) LIKE ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(lr_number) LIKE ?', ["%{$needle}%"]);
            })
            ->orderBy('property_name')
            ->limit(25)
            ->get(['id', 'property_name', 'reference_number', 'lr_number'])
            ->each(function ($property) {
                $property->encrypted_id = Crypt::encrypt($property->id);
            });

        $invoices = DebtorInvoice::with(['tenant:id,names', 'client:id,names'])
            ->whereRaw('LOWER(reference_number) LIKE ?', ["%{$needle}%"])
            ->orderByDesc('date_time')
            ->limit(25)
            ->get(['id', 'reference_number', 'total_amount', 'current_balance', 'tenant_id', 'client_id', 'date_time']);

        $returns = $this->searchReturns($needle);
        $vouchers = $this->searchVouchers($needle);
        $advances = $this->searchAdvances($needle);

        return view('search.index', [
            'term' => $term,
            'returns' => $returns,
            'vouchers' => $vouchers,
            'advances' => $advances,
            'clients' => $clients,
            'staff' => $staff,
            'properties' => $properties,
            'invoices' => $invoices,
        ]);
    }

    /**
     * Find returns by return code (e.g. MAINRETURN / MINIRETURN codes).
     *
     * @param string $needle lower-cased search term
     */
    private function searchReturns($needle) {
        return ReturnSummary::whereRaw('LOWER(return_code) LIKE ?', ["%{$needle}%"])
            ->orderByDesc('id')
            ->limit(25)
            ->get(['id', 'return_code', 'return_type', 'landlord_code', 'property_code', 'year', 'month', 'net_balance', 'approved_status', 'paid_status'])
            ->each(function ($return) {
                $return->encrypted_id = Crypt::encrypt($return->id);
            });
    }

    /**
     * Find payment vouchers by voucher number.
     *
     * @param string $needle lower-cased search term
     */
    private function searchVouchers($needle) {
        return PaymentVoucherHeader::whereRaw('LOWER(voucher_number) LIKE ?', ["%{$needle}%"])
            ->where('is_deleted', 0)
            ->orderByDesc('id')
            ->limit(25)
            ->get(['id', 'voucher_number', 'vendor_name', 'debit_amount', 'voucher_status', 'voucher_date'])
            ->each(function ($voucher) {
                $voucher->encrypted_id = Crypt::encrypt($voucher->id);
            });
    }

    /**
     * Find client advances by reference number.
     *
     * @param string $needle lower-cased search term
     */
    private function searchAdvances($needle) {
        return ClientAdvance::with('client:id,names')
            ->whereRaw('LOWER(reference_number) LIKE ?', ["%{$needle}%"])
            ->orderByDesc('id')
            ->limit(25)
            ->get(['id', 'reference_number', 'client_id', 'amount', 'current_balance', 'current_status_id'])
            ->each(function ($advance) {
                $advance->encrypted_id = Crypt::encrypt($advance->id);
            });
    }
}
