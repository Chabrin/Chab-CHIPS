<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientBankDetail;
use App\Models\Property;
use App\Models\PropertyOwnershipTransfer;
use App\Models\ServiceAgreement;
use App\Models\SlaInvoiceableItem;
use App\Services\PropertyOwnershipTransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PropertyOwnershipTransferController extends Controller
{
    public function __construct(private PropertyOwnershipTransferService $transfers) {}

    public function index(Request $request)
    {
        $this->transfers->access($request->user(), 'view');
        $query = PropertyOwnershipTransfer::whereIn('property_id', Property::select('id'))->with(['property', 'fromClient', 'toClient'])->latest('id');
        if ($request->filled('property_id')) {
            $query->where('property_id', $request->integer('property_id'));
        }
        if (in_array($request->status, ['draft', 'needs_review', 'reviewed', 'approved', 'completed', 'cancelled'], true)) {
            $query->where('status', $request->status);
        }

        return view('property.ownership-transfers.index', ['transfers' => $query->paginate(25)->withQueryString()]);
    }

    public function create(Request $request)
    {
        $this->transfers->access($request->user(), 'create');

        return view('property.ownership-transfers.create', [
            'properties' => Property::with('client')->orderBy('property_name')->get(),
            'clients' => Client::where('reference_number', 'like', 'LAN%')->where('current_status_id', 5)->orderBy('names')->get(),
            'selectedProperty' => $request->integer('property_id'),
        ]);
    }

    public function banks(Request $request, Client $client)
    {
        $this->transfers->access($request->user(), 'create');

        return response()->json(['banks' => ClientBankDetail::where('client_id', $client->id)->get()->map(fn ($bank) => [
            'id' => $bank->id, 'label' => $bank->account_name.' — ending '.substr((string) $bank->account_number, -4),
        ])]);
    }

    public function store(Request $request)
    {
        $this->transfers->access($request->user(), 'create');
        $data = $request->validate([
            'property_id' => 'required|integer', 'to_client_id' => 'required|integer', 'bank_detail_id' => 'required|integer',
            'effective_date' => 'required|date_format:Y-m-d', 'arrears_policy' => 'required|in:transfer,retain',
            'reason' => 'required|string|min:10|max:4000', 'commission_percentage' => 'required|numeric|gt:0|lte:100',
            'duration_years' => 'required|integer|min:1|max:30', 'handover_documents' => 'nullable|array|max:10',
            'handover_documents.*' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ]);
        $documents = [];
        try {
            foreach ($request->file('handover_documents', []) as $file) {
                $documents[] = [
                    'path' => $file->store('property-ownership-transfers', 'local'), 'name' => $file->getClientOriginalName(),
                ];
            }
            $data['documents'] = $documents;
            $transfer = $this->transfers->create($data, $request->user());
        } catch (\Throwable $e) {
            foreach ($documents as $document) {
                Storage::disk('local')->delete($document['path']);
            }
            throw $e;
        }

        return redirect()->route('property-transfers.show', $transfer)->with('success', 'Transfer request created. Finance or a Director must reconcile the handover before approval.');
    }

    public function show(Request $request, PropertyOwnershipTransfer $transfer)
    {
        $property = $this->transfers->visible($transfer, $request->user());
        $transfer->load(['fromClient', 'toClient', 'events.actor']);
        $snapshot = $transfer->status === 'completed' ? $transfer->snapshot : $this->transfers->snapshot($transfer);

        return view('property.ownership-transfers.show', compact('transfer', 'property', 'snapshot'));
    }

    public function decide(Request $request, PropertyOwnershipTransfer $transfer)
    {
        $data = $request->validate(['action' => 'required|in:review,approve,complete,reset,cancel',
            'notes' => 'required_unless:action,complete|nullable|string|min:10|max:4000',
            'confirmed' => 'exclude_unless:action,review|required|accepted',
        ]);
        match ($data['action']) {
            'review' => $this->transfers->review($transfer, $request->user(), $data['notes']),
            'approve' => $this->transfers->approve($transfer, $request->user(), $data['notes']),
            'complete' => $this->transfers->complete($transfer, $request->user()),
            'reset', 'cancel' => $this->transfers->reset($transfer, $request->user(), $data['notes'], $data['action'] === 'cancel'),
        };

        $message = match ($data['action']) {
            'review' => 'Reconciliation recorded. The next step is approval of the handover and incoming SLA terms.',
            'approve' => 'Handover approved. Complete the transfer on '.$transfer->effective_date->format('d M Y').', before raising invoices.',
            'complete' => 'Ownership transfer completed. The incoming landlord and new SLA are now linked to this property.',
            'reset' => 'A fresh reconciliation and approval are required before completion.',
            'cancel' => 'Transfer request cancelled. Property ownership has not changed.',
        };

        return back()->with('success', $message);
    }

    public function agreement(Request $request, PropertyOwnershipTransfer $transfer, string $side)
    {
        $property = $this->transfers->visible($transfer, $request->user());
        $agreement = $this->agreementFor($transfer, $side);
        $agreement->load(['createdBy', 'serviceAgreementDocuments']);
        $landlord = $side === 'outgoing' ? $transfer->fromClient : $transfer->toClient;
        $items = SlaInvoiceableItem::where('sla_id', $agreement->id)->with('item.cycle')->get();

        return view('property.ownership-transfers.agreement', compact('transfer', 'property', 'agreement', 'landlord', 'items', 'side'));
    }

    public function agreementDocument(Request $request, PropertyOwnershipTransfer $transfer, string $side, int $document)
    {
        $this->transfers->visible($transfer, $request->user());
        $agreement = $this->agreementFor($transfer, $side);
        $file = $agreement->serviceAgreementDocuments()->findOrFail($document);
        abort_unless($file->sla_documents && Storage::disk('public')->exists($file->sla_documents), 404);

        return Storage::disk('public')->download($file->sla_documents, basename($file->sla_documents));
    }

    private function agreementFor(PropertyOwnershipTransfer $transfer, string $side): ServiceAgreement
    {
        abort_unless(in_array($side, ['outgoing', 'incoming'], true), 404);
        $id = $side === 'outgoing' ? $transfer->old_sla_id : $transfer->new_sla_id;
        abort_unless($id, 404, 'The incoming SLA is created when the ownership transfer is completed.');

        return ServiceAgreement::where('property_id', $transfer->property_id)->findOrFail($id);
    }

    public function document(Request $request, PropertyOwnershipTransfer $transfer, int $document)
    {
        $this->transfers->visible($transfer, $request->user());
        $file = $transfer->documents[$document] ?? null;
        abort_unless($file && Storage::disk('local')->exists($file['path']), 404);

        return Storage::disk('local')->download($file['path'], $file['name']);
    }

    public function statement(Request $request, PropertyOwnershipTransfer $transfer)
    {
        $property = $this->transfers->visible($transfer, $request->user());
        abort_unless($transfer->snapshot, 422, 'Finance must review the handover before a statement is available.');
        $transfer->load(['fromClient', 'toClient', 'events.actor']);

        return view('property.ownership-transfers.statement', ['transfer' => $transfer, 'property' => $property, 'snapshot' => $transfer->snapshot]);
    }
}
