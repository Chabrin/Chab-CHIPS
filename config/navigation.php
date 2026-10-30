<?php

/* Named full-page routes whose paths do not sit beneath their menu destination.
 * These are only hints: the sidebar may select a destination only if it is
 * already present in the authenticated user's permission-filtered menu.
 */
return [
    'route_aliases' => [
        'settings.hr.positions.show' => '/hr/staff/positions',
        'discipline.tracker.show' => '/hr/discipline/tracker',
        'discipline.tracker.create.incident' => '/hr/discipline/tracker',
        'employee.termination.show' => '/hr/employee/termination',
        'tenant.lease.create' => '/property/leases',
        'property.movements.create' => '/tenant/movements',
        'property.movements.show' => '/tenant/movements',
        'property.movements.edit' => '/tenant/movements',
        'returns.batches.details' => '/returns/list',
        'returns.details' => '/returns/list',
        'property.properties.edit' => '/property/listings',
        'property.sla.show' => '/property/service-agreements',
        'property.sla.edit' => '/property/service-agreements',
        'property.inspection.create' => '/property/inspections',
        'property.recovery.create' => '/property/recoveries',
        'property.recoveries.show' => '/property/recoveries',
        'property.recoveries.edit' => '/property/recoveries',
        'viewcompany' => '/list/companies',
        'editcompany' => '/list/companies',
    ],
];
