<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientDraftRepeater extends Model
{
    protected $table = 'clients_draft_repeaters';

    // No DB-level enum for kind — validate application-side against this list.
    public const KINDS = ['bank', 'contact', 'nextofkin', 'partner'];

    protected $fillable = [
        'draft_token',
        'kind',
        'bank_id',
        'branch_id',
        'account_name',
        'account_number',
        'contact_name',
        'contact_email',
        'contact_mobile',
        'kin_first_name',
        'kin_second_name',
        'kin_last_name',
        'kin_mobile',
        'kin_email',
        'kin_relationship',
        'kin_national_id',
        'partner_name',
        'partner_national_id',
        'partner_kra_pin',
        'partner_phone',
    ];
}
