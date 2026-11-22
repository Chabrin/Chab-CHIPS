<?php

namespace App\Http\Controllers;

use App\Exceptions\ClientAccountException;
use App\Models\Client;
use App\Services\Comms\EmailService;
use App\Services\ErrorDispatcher;
use App\Services\LandlordPaidOwnerService;
use App\Traits\ChecksMenuAccess;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * The Paid Owner link. Staff make a signed link for a landlord (and may email it); the landlord opens it without a
 * login and reports a tenant who paid them rent directly. The report is a Pending Paid Owner recovery that the confirmer
 * checks on the Recovery page. Mirrors the tenant-exit consent and advance approval links.
 */
class LandlordPaidOwnerController extends Controller
{
    use ChecksMenuAccess;

    const RECOVERY_MENU_URL = '/property/recoveries';

    protected LandlordPaidOwnerService $reports;
    protected EmailService $email;

    /**
     * Inject the service behind the link and the mailer that sends it.
     */
    public function __construct(LandlordPaidOwnerService $reports, EmailService $email) {
        $this->reports = $reports;
        $this->email = $email;
    }

    /**
     * Make the signed link for a landlord (AJAX, staff). With send_email = 1 it is also emailed to the landlord's address.
     */
    public function makeLink(Request $request, $encryptedId) {
        if (!$this->menuAllowed(self::RECOVERY_MENU_URL)) {
            return $this->denied('json');
        }

        try {
            $client = Client::findOrFail((int) Crypt::decrypt($encryptedId));
            $url = $this->reports->link((int) $client->id);
            $emailed = false;
            $emailError = null;

            if ($request->boolean('send_email')) {
                if (!filter_var($client->email_address, FILTER_VALIDATE_EMAIL)) {
                    $emailError = 'This landlord has no valid email address on file.';
                } else {
                    $body = "Dear " . ($client->names ?: 'Landlord') . ",\n\nIf a tenant has paid you rent directly instead of through us, please tell us by opening the link below and filling in the payment. We will check it and make sure the rent is not paid out to you a second time.\n\n" . $url . "\n\nThe link works for " . LandlordPaidOwnerService::LINK_DAYS . " days.\n\nRegards,\nProperty Management Team";
                    [$emailed, $emailError] = $this->email->send($client->email_address, (string) $client->names, 'Tenant payments made directly to you', $body, 'paid_owner_link', null, 'landlord', (int) $client->id, null, null, null, null, 'application/pdf', 'client', (int) $client->id);
                }
            }

            return response()->json(['success' => true, 'url' => $url, 'days' => LandlordPaidOwnerService::LINK_DAYS, 'emailed' => (bool) $emailed, 'email_error' => $emailError]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error making the paid owner link: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Could not make the link.'], 500);
        }
    }

    /**
     * The landlord's page: choose property, unit and invoice, then say how much the tenant paid them and when. Public, signed.
     */
    public function show(Request $request, $encryptedId) {
        try {
            $clientId = (int) Crypt::decrypt($encryptedId);
            $client = Client::withoutGlobalScopes()->findOrFail($clientId);

            return view('property.paid-owner-report.form', [
                'landlord' => $client->names,
                'options' => $this->reports->options($clientId),
                'submitUrl' => $request->fullUrl(),
            ]);
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error loading the paid owner report page: ' . $e->getMessage());
            abort(404);
        }
    }

    /**
     * Record the landlord's report. Public, signed. The checks run against the landlord the link was made for.
     */
    public function submit(Request $request, $encryptedId) {
        try {
            $clientId = (int) Crypt::decrypt($encryptedId);
            $client = Client::withoutGlobalScopes()->findOrFail($clientId);

            $data = $request->validate([
                'invoice_id' => ['required', 'integer'],
                'amount' => ['required', 'numeric', 'min:1'],
                'paid_on' => ['required', 'date'],
                'reference' => ['nullable', 'string', 'max:100'],
                'note' => ['nullable', 'string', 'max:500'],
                'proof' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:10240'],
            ]);

            $recovery = $this->reports->report($clientId, $data, $request->file('proof'));

            Log::info('Paid owner reported by a landlord', ['recovery' => $recovery->ref_number, 'client_id' => $clientId]);

            return view('property.paid-owner-report.thanks', ['landlord' => $client->names, 'reference' => $recovery->ref_number]);
        } catch (ClientAccountException $e) {
            return back()->withErrors(['report' => $e->getMessage()])->withInput();
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->validator)->withInput();
        } catch (Exception $e) {
            ErrorDispatcher::dispatch($e);
            Log::error('Error recording a landlord paid owner report: ' . $e->getMessage());
            return back()->withErrors(['report' => 'Sorry, we could not record this. Please try again or contact us.'])->withInput();
        }
    }
}
