# Management onboarding session checklist

Prepared from the workspace code on 2 November 2026. The running deployment may differ.
Use this with the chief accountant's source records. It does not establish accounting policy.

## Before the session

- [ ] Confirm which system and database are being used; a localhost address does not prove the data is a practice copy.
- [ ] Confirm the operator and approver have the required access and property/zone visibility.
- [ ] Agree whether this is a new tenancy or an existing tenancy being brought into CHIPS.
- [ ] For existing tenancies, agree the cut-off date, first period to invoice, deposits, arrears, prepayments, and unallocated receipts.
- [ ] Identify the source document and reviewer for opening balances; do not create historical bills or receipts by guesswork.
- [ ] Obtain landlord identity/contact details, payout instructions, management agreement, property details, unit schedule, tenant schedule, and tenancy dates.
- [ ] Confirm commission, VAT treatment, registration fee, utilities, service charges, and payment dates with the accountant.
- [ ] Agree who resolves missing information and who confirms completion.

## Inspect the system before making entries

1. Open Clients (`/property/clients`) and search for the landlord by reference and identity, not name alone.
2. Open an existing landlord and inspect Profile, Bank Details, Properties, SLA, Units, Tenants, Leases, Invoicing, Generated Invoices, and Returns Config, where available.
3. On Units and Tenants, select a property before interpreting an empty table.
4. Inspect Approve SLA (`/approvals/sla`), Approve Leases (`/approvals/leases`), and My Onboarding (`/property/my-onboarding`).
5. My Onboarding only lists the current user's recent creations; it is not a complete portfolio register.
6. Inspecting an approval screen is different from approving a record. Approval can change linked records and post account entries.

## Per-landlord entry record

| Check | Source / value / reference |
|---|---|
| Landlord already exists? | |
| Client type and identity confirmed | |
| Phone and email confirmed | |
| Bank, branch, account name and number confirmed | |
| Individual next of kin / entity contacts and partners confirmed | |
| Created or existing landlord reference | |
| Property names, locations, zones and areas | |
| Field officer and responsible staff | |
| Property payout-account selection | |
| SLA dates, commission, VAT settings, payment day | |
| Registration fee: not charged / unpaid / already paid with evidence | |
| Utility and service-charge arrangement | |
| Total units / occupied units / vacant units | |
| Unit-by-unit rent and meter readings | |
| Existing tenants, identity, contact details and lease dates | |
| Deposit amount and who actually holds it | |
| Cut-off date and opening financial position | |
| Approval result and relevant account entries checked | |
| Outstanding issues, owner and next action | |

## Choose an entry path

### Manual path

1. Create or reuse landlord. Current create flow requires a staged bank account; individuals also need staged next of kin. Add/save rows within their modals before submitting the main form.
2. Add property from the landlord's Properties tab. Confirm landlord, zone, area, type, staff and payout account.
3. Create SLA using the agreed management terms. Create units through Units > select property > Add Unit. SLA and units are related setup steps, not a universally enforced one-way sequence.
4. Have the authorised reviewer inspect Approve SLA. The dedicated approval service can approve the pending landlord, property and units together. It may charge or settle a registration fee, or open a zero-value client account entry.
5. Add tenant against the correct vacant unit. Current manual tenant creation also creates a lease and can record a deposit and tenant-ledger credit. Do not add another lease automatically afterward.
6. Inspect the created tenant, lease and deposit records. Unit rent is resolved by the server rather than taken as an independent tenant-form amount.
7. Have the authorised reviewer inspect Approve Leases. The dedicated lease approval requires an approved SLA and can also approve the pending tenant.
8. Reconcile counts, associations, dates, amounts and account effects against the source schedule.

### Combined units-and-tenants upload

1. Use landlord > Tenants > select property > Bulk Upload. This is distinct from the legacy Bulk Upload Data action and the units-only uploader.
2. Download the current template from the system. It has `units`, `individual_tenants`, and `corporate_tenants` sheets.
3. The current template constant is 100 rows; use the workbook's supported range.
4. This path creates new units. Do not first create those same units manually or treat this as an update-existing-units importer.
5. Follow workbook dropdowns and required fields. Units require unit number, topology, rent amount and initial water reading.
6. Keep the unit number consistent and the tenant aligned with the corresponding row number on the units sheet. Do not sort sheets independently.
7. Preserve identity numbers and phone numbers as text. Use unambiguous dates and the actual tenancy terms.
8. Upload to stage, then inspect the batch and every error before submitting.
9. On submit, each unit/tenant pair has its own transaction. Some pairs may succeed while others fail. Read submitted/failed counts and created IDs before retrying.
10. A unit-only row creates a vacant unit; a row with a tenant also creates a lease. Missing SLA blocks tenant/lease submission.
11. The current workbook has no deposit/opening-arrears fields. Do not assume importing identities and leases imports the opening accounts.
12. Reconcile created units, tenants, leases and approvals after submission. Keep the source workbook and batch ID.

## Completion evidence

- [ ] Landlord identity and bank details match the source.
- [ ] Property and unit counts match; vacant units are represented.
- [ ] Every occupied unit has the correct tenant and intended current lease.
- [ ] Unit rent, tenancy dates, meter readings and utility settings match.
- [ ] No accidental duplicate landlord, unit, tenant or lease.
- [ ] Deposit amount, holder and opening balances are reconciled through the agreed process.
- [ ] SLA and lease approvals are reviewed; registration-fee account effects match the decision.
- [ ] Intended first billing period is agreed; existing periods will not be billed twice.
- [ ] Pending items and failed rows have an owner and next action.

## Developer trace for a problem

Record the page, actor/role, timestamp, selected property, record or batch reference, expected result and actual result. Use browser Network to inspect the request and response. Follow route -> controller -> service/trait -> model/table -> account/approval effects -> returned screen. Confirm whether the first request committed before retrying. Redact contact details, identity documents, cookies and credentials from shared diagnostics.

Useful starting points:

- `routes/web.php`
- `app/Http/Controllers/ClientsController.php`
- `app/Services/ClientDraftRequirements.php`
- `app/Http/Controllers/PropertiesController.php`
- `app/Http/Controllers/SLAController.php`
- `app/Services/ServiceAgreementApprovalService.php`
- `app/Services/ClientAccountService.php`
- `app/Http/Controllers/UnitsController.php`
- `app/Http/Controllers/TenantsController.php`
- `app/Traits/LeaseCreationTrait.php`
- `app/Services/LeaseAgreementApprovalService.php`
- `app/Services/UnitTenantUpload/`
- `resources/views/property/clients/details.blade.php`

Practice validation failures, partial uploads, duplicate submissions, approvals and financial effects on a confirmed practice database. On the live system, use actual authorised records and approved correction procedures.
