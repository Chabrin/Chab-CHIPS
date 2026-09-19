<?php

namespace App\Http\Controllers;

use App\Models\Client;
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

        return view('search.index', [
            'term' => $term,
            'clients' => $clients,
            'staff' => $staff,
            'properties' => $properties,
            'invoices' => $invoices,
        ]);
    }
}
