<?php

namespace App\Services\UnitTenantUpload;

use App\Models\Client;
use App\Models\LeaseAgreement;
use App\Models\Unit;

class ExistingUnitAvailability
{
    public static function blocked(Unit $unit): bool
    {
        return LeaseAgreement::withoutGlobalScopes()->where('unit_id', $unit->id)->whereIn('current_status_id', [1, 5])->exists()
            || Client::withAll()->where('type_id', 2)->where('unit_id', $unit->id)->whereIn('current_status_id', [1, 5])->exists();
    }
}
