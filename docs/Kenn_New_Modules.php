#Permissions
staff_positions
tax_bands
staff


#Modules
INSERT INTO cb_sub_modules (sub_module_name, sub_module_url, icon,
`order`, added_by, added_on, module_id, created_at, updated_at)
VALUES('Income Tax Rates', '/payroll/tax-bands', NULL, 2, 'kenneth', '2025-11-21 16:30:01', 6, '2025-11-21 16:30:01', '2025-11-21 16:30:01');


##NEW 24-11-2025
#Permissions
payroll_processing
payslips


#Modules
INSERT INTO cb_sub_modules (sub_module_name, sub_module_url, icon, `order`, added_by, added_on, module_id, created_at, updated_at) VALUES('Payroll Processing', '/payroll/processing', NULL, 5, 'kenneth', '2025-11-24 11:50:12', 6, '2025-11-24 11:50:12', '2025-11-24 11:50:12');
INSERT INTO cb_sub_modules (sub_module_name, sub_module_url, icon, `order`, added_by, added_on, module_id, created_at, updated_at) VALUES('Statutory Deductions', '/statutory/deductions', NULL, 4, 'kenneth', '2025-11-24 18:46:34', 7, '2025-11-24 18:46:34', '2025-11-24 18:46:34');

###NEW 25-11-2025
Module
Property, Icon: brick

Sub-Modules
Clients - /property/clients
Properties - /property/listings
Tenants - /tenants/listing



## NEW 1st Dec 2025
# Modules
Roles -------- System Users --- /roles/users

#Permissions
Roles ----------- users

## 3rd Dec 2025
Modules
Payroll Mgt------------ HELB Loans ---------- /payroll/helbloans
Payrll Mgt------------ Prorated Deductions ----- /payroll/prorated/deductions

Permissions
Under Payroll Mgt ------------ helb_loans
Under Payroll Mgt ------------ prorated_deductions


########## 2026 ###########
## NEW 10 Jan 2026
MODULES
Menu: Client Trans, Icon swap_horiz
Menu:Tenant Trans, Icon account_balance_wallet


SUBMODULES: 11 is Client, 12 is Tenants

INSERT INTO cb_sub_modules (sub_module_name, sub_module_url, module_id) VALUES('Invoices', '/client/invoices', 11);
INSERT INTO cb_sub_modules (sub_module_name, sub_module_url, module_id) VALUES('Client Ledgers', '/client/ledger', 11);
INSERT INTO cb_sub_modules (sub_module_name, sub_module_url, module_id) VALUES('Advance Requests', '/client/advances', 11);
INSERT INTO cb_sub_modules (sub_module_name, sub_module_url, module_id) VALUES('Tenant Payments', '/tenant/payments', 12);
INSERT INTO cb_sub_modules (sub_module_name, sub_module_url, module_id) VALUES('Arrears', '/tenant/arrears', 12);
INSERT INTO cb_sub_modules (sub_module_name, sub_module_url, module_id) VALUES('Tenant Deposits', '/tenant/deposits', 12);
INSERT INTO cb_sub_modules (sub_module_name, sub_module_url, module_id) VALUES('Tenant Movements', '/tenant/movements', 12);
INSERT INTO cb_sub_modules (sub_module_name, sub_module_url, module_id) VALUES('Payment Allocations', '/tenant/paymentallocations', 12);

## NEW 18 Jan 2026
Tenant Trans - Tenant Statements - /tenants/statements

## NEW 28 Jan 2026
Tenant Trans ----- /generate/invoices
Permissions - invoices


####New Module
INSERT INTO cb_modules (module_name, module_url, icon, module_order,
added_by, added_on, created_at, updated_at, deleted, deleted_by,
deleted_at, deleted_reason) VALUES('Client Returns', NULL,
'change_circle', 10, 'kenneth', '2026-01-28 12:36:40',
'2026-01-28 12:36:40', '2026-01-28 12:36:40', 0, NULL, NULL, NULL);

#Sub Modules for Client Returns
Returns List - /returns/list
Payout Accounts - /payout/accounts
Processed Returns - /processed/returns

Permissions
client_advances ------ Client Trans

22nd March 2026
New Sub Module --> Under Client Trans: /landlord/account/statement

6th April 2026
New Sub Module --> Under IT settings: /modules/mapper (Modules Mapper)

### Super Modules
INSERT INTO cb_super_modules ("name", icon, "order", created_at, updated_at) VALUES('Management', 'admin_panel_settings', 1, '2026-04-06 06:12:16.000', '2026-04-06 06:12:16.000');
INSERT INTO cb_super_modules ("name", icon, "order", created_at, updated_at) VALUES('Accounting', 'account_balance', 2, '2026-04-06 06:12:16.000', '2026-04-06 06:12:16.000');
INSERT INTO cb_super_modules ("name", icon, "order", created_at, updated_at) VALUES('HR', 'groups', 3, '2026-04-06 06:12:16.000', '2026-04-06 06:12:16.000');
INSERT INTO cb_super_modules ("name", icon, "order", created_at, updated_at) VALUES('Auditing', 'verified', 4, '2026-04-06 06:12:16.000', '2026-04-06 06:12:16.000');
INSERT INTO cb_super_modules ("name", icon, "order", created_at, updated_at) VALUES('GIS', 'map', 5, '2026-04-06 06:12:16.000', '2026-04-06 06:12:16.000');
INSERT INTO cb_super_modules ("name", icon, "order", created_at, updated_at) VALUES('Configurations', 'tune', 6, '2026-04-06 06:12:16.000', '2026-04-06 06:12:16.000');
INSERT INTO cb_super_modules ("name", icon, "order", created_at, updated_at) VALUES('System Setup', 'settings', 7, '2026-04-06 06:12:17.000', '2026-04-06 06:12:17.000');
INSERT INTO cb_super_modules ("name", icon, "order", created_at, updated_at) VALUES('Reports', 'analytics', 8, '2026-04-06 06:12:17.000', '2026-04-06 06:12:17.000');