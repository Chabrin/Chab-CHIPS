<?php

namespace App\Services;

use App\Models\ClientDraftRepeater;

/**
 * Records a new client must have staged before it can be saved: at least one bank account
 * (every client type, since returns are paid out to it) and at least one next of kin for
 * individuals (companies and partnerships have partners and contacts instead).
 */
class ClientDraftRequirements
{
    /**
     * Error messages for whatever is missing, keyed banks and next_of_kin.
     *
     * @param string|null $draftToken
     * @param int $clientTypeId 1 Individual, 3 Company, 4 Partnership and Trust
     */
    public function missing(?string $draftToken, int $clientTypeId): array {
        $errors = [];

        if ($this->countStaged($draftToken, 'bank') === 0) {
            $errors['banks'] = ['Add at least one bank account on the Bank Details tab.'];
        }

        $isEntity = in_array($clientTypeId, [3, 4], true);
        if (!$isEntity && $this->countStaged($draftToken, 'nextofkin') === 0) {
            $errors['next_of_kin'] = ['Add at least one next of kin on the Next of Kin tab.'];
        }

        return $errors;
    }

    /**
     * How many rows of one kind are staged under a draft token.
     *
     * @param string|null $draftToken
     * @param string $kind bank, contact, nextofkin or partner
     */
    protected function countStaged(?string $draftToken, string $kind): int {
        return $draftToken
            ? ClientDraftRepeater::where('draft_token', $draftToken)->where('kind', $kind)->count()
            : 0;
    }
}
