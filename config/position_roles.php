<?php

/*
|--------------------------------------------------------------------------
| Positions that already have a system role
|--------------------------------------------------------------------------
|
| Every position has one matching role. A role that was made before positions had roles is linked to its position by name; when the
| two are named differently, say which role belongs to which position here (position slug => role name). `php artisan
| hr:link-position-roles` lists what it will do and changes nothing until it is run with --apply. These pairs are a first guess to
| be confirmed: change them before running it with --apply.
|
*/

return [

    'existing' => [
        'operations_hr_manager' => 'hr_manager',
        'managing_director' => 'directors',
        'board_members' => 'directors',
        'qa_valuation_manager' => 'qc_manager',
        'it_support_officer' => 'it_department',
        'zone_managers' => 'zone_manager',
        'senior_field_officers' => 'senior_field_officer',
        'field_officers' => 'field_officer',
        'accountant' => 'accountant',
        'internal_auditor' => 'internal_auditor',
        'property_manager' => 'property_manager',
        'office_assistant' => 'office_assistant',
    ],

];
