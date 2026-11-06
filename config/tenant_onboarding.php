<?php

return [
    // Enable only in a separate test installation until the workflow is accepted.
    'enabled' => env('TENANT_ONBOARDING_ENABLED', false),
    'roles' => [
        'field_officer' => env('TENANT_ONBOARDING_FIELD_OFFICER_ROLE', 'Field Officer'),
        'zone_manager' => env('TENANT_ONBOARDING_ZONE_MANAGER_ROLE', 'Zone Manager'),
        'internal_auditor' => env('TENANT_ONBOARDING_INTERNAL_AUDITOR_ROLE', 'Internal Auditor'),
        'assistant_auditor' => env('TENANT_ONBOARDING_ASSISTANT_AUDITOR_ROLE', 'Assistant Auditor'),
    ],
];
