<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Who may open, download or remove staff documents
    |--------------------------------------------------------------------------
    |
    | Staff documents (ID and KRA PIN copies, signed contracts, certificates) are only for these roles, by role name (the name column
    | on the Roles page). A person also needs the usual staff permission; the role alone is not enough. Change the list in the .env
    | file with STAFF_DOCUMENT_ROLES, names separated by commas, and nothing else needs to be touched.
    |
    */

    'roles' => array_values(array_filter(array_map('trim', explode(',', (string) env('STAFF_DOCUMENT_ROLES', 'hr_manager,directors'))))),

    /*
    |--------------------------------------------------------------------------
    | Who may record an exception to the HELB compliance certificate
    |--------------------------------------------------------------------------
    |
    | The certificate is mandatory for every new staff member. Only these roles may let one go without it, and must give a written
    | reason. Change the list in the .env file with STAFF_DOCUMENT_EXCEPTION_ROLES.
    |
    */

    'exception_roles' => array_values(array_filter(array_map('trim', explode(',', (string) env('STAFF_DOCUMENT_EXCEPTION_ROLES', 'hr_manager'))))),

];
