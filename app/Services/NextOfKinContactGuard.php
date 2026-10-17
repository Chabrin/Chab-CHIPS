<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientDraftRepeater;
use App\Models\ClientNextOfKin;

/**
 * Keeps a client's own email and mobile number different from their next of kin's.
 *
 * Phones are compared by their last 9 digits, so 0712345678, +254712345678 and
 * 254712345678 all count as the same number. Emails are compared ignoring case and spaces.
 */
class NextOfKinContactGuard
{
    /**
     * Reduce a phone number to its last 9 digits so different spellings of one number match.
     *
     * @param string|null $phone
     */
    public function normalizePhone(?string $phone): ?string {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return null;
        }

        return strlen($digits) > 9 ? substr($digits, -9) : $digits;
    }

    /**
     * Lower-case and trim an email so equal addresses compare equal.
     *
     * @param string|null $email
     */
    public function normalizeEmail(?string $email): ?string {
        $email = strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }

    /**
     * Which contact details a next of kin shares with the client.
     *
     * @param string|null $clientEmail
     * @param string|null $clientMobile
     * @param string|null $kinEmail
     * @param string|null $kinMobile
     */
    public function sharedWithClient(?string $clientEmail, ?string $clientMobile, ?string $kinEmail, ?string $kinMobile): array {
        $cEmail = $this->normalizeEmail($clientEmail);
        $cMobile = $this->normalizePhone($clientMobile);

        return [
            'email' => $cEmail !== null && $cEmail === $this->normalizeEmail($kinEmail),
            'mobile' => $cMobile !== null && $cMobile === $this->normalizePhone($kinMobile),
        ];
    }

    /**
     * Error messages for the next of kin form, keyed by kin field (email, mobile).
     *
     * @param string|null $clientEmail
     * @param string|null $clientMobile
     * @param string|null $kinEmail
     * @param string|null $kinMobile
     */
    public function kinErrors(?string $clientEmail, ?string $clientMobile, ?string $kinEmail, ?string $kinMobile): array {
        $shared = $this->sharedWithClient($clientEmail, $clientMobile, $kinEmail, $kinMobile);
        $errors = [];

        if ($shared['email']) {
            $errors['email'] = ["The next of kin's email cannot be the same as the client's email."];
        }
        if ($shared['mobile']) {
            $errors['mobile'] = ["The next of kin's mobile number cannot be the same as the client's mobile number."];
        }

        return $errors;
    }

    /**
     * Error messages for the client form, checked against every next of kin staged under a
     * draft token. Keyed by client field (email_address, mobile_number).
     *
     * @param string|null $draftToken
     * @param string|null $clientEmail
     * @param string|null $clientMobile
     */
    public function draftErrors(?string $draftToken, ?string $clientEmail, ?string $clientMobile): array {
        if (!$draftToken) {
            return [];
        }

        $kin = ClientDraftRepeater::where('draft_token', $draftToken)->where('kind', 'nextofkin')->get();

        return $this->clientErrors($kin->map(fn($row) => [
            'name' => trim($row->kin_first_name . ' ' . $row->kin_last_name),
            'email' => $row->kin_email,
            'mobile' => $row->kin_mobile,
        ]), $clientEmail, $clientMobile);
    }

    /**
     * Error messages for the client form, checked against the next of kin already saved for a client.
     *
     * @param int $clientId
     * @param string|null $clientEmail
     * @param string|null $clientMobile
     */
    public function savedErrors(int $clientId, ?string $clientEmail, ?string $clientMobile): array {
        $kin = ClientNextOfKin::where('parent_id', $clientId)->get();

        return $this->clientErrors($kin->map(fn($row) => [
            'name' => $row->names ?: trim($row->kin_first_name . ' ' . $row->kin_last_name),
            'email' => $row->email_address,
            'mobile' => $row->mobile_number,
        ]), $clientEmail, $clientMobile);
    }

    /**
     * Error messages for the next of kin form against a client already in the system.
     *
     * @param int $clientId
     * @param string|null $kinEmail
     * @param string|null $kinMobile
     */
    public function kinErrorsForClient(int $clientId, ?string $kinEmail, ?string $kinMobile): array {
        $client = Client::withoutGlobalScopes()->find($clientId);

        return $client ? $this->kinErrors($client->email_address, $client->mobile_number, $kinEmail, $kinMobile) : [];
    }

    /**
     * Build the client form messages from a list of next of kin ([name, email, mobile]).
     *
     * @param iterable $kinList
     * @param string|null $clientEmail
     * @param string|null $clientMobile
     */
    protected function clientErrors(iterable $kinList, ?string $clientEmail, ?string $clientMobile): array {
        $errors = [];

        foreach ($kinList as $kin) {
            $shared = $this->sharedWithClient($clientEmail, $clientMobile, $kin['email'], $kin['mobile']);
            $name = $kin['name'] !== '' ? $kin['name'] : 'a next of kin';

            if ($shared['email'] && !isset($errors['email_address'])) {
                $errors['email_address'] = ["This email is the same as the one for next of kin {$name}. Use a different email."];
            }
            if ($shared['mobile'] && !isset($errors['mobile_number'])) {
                $errors['mobile_number'] = ["This mobile number is the same as the one for next of kin {$name}. Use a different number."];
            }
        }

        return $errors;
    }
}
