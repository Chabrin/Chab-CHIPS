# Sub Module 

INSERT INTO cb_sub_modules (sub_module_name, sub_module_url, icon, `order`, added_by, added_on, module_id, created_at, updated_at) VALUES('SHIF Rates', '/hrsettings/shif/rates', NULL, 7, 'peter', '2025-11-20 09:49:47', 4, '2025-11-20 09:49:47', '2025-11-20 09:49:47');
INSERT INTO cb_sub_modules (sub_module_name, sub_module_url, icon, `order`, added_by, added_on, module_id, created_at, updated_at) VALUES('NSSF Rates', '/hrsettings/nssf/rates', NULL, 8, 'peter', '2025-11-20 09:49:47', 4, '2025-11-20 09:49:47', '2025-11-20 09:49:47');
INSERT INTO cb_sub_modules (sub_module_name, sub_module_url, icon, `order`, added_by, added_on, module_id, created_at, updated_at) VALUES('Payroll Elements', '/hrsettings/payroll/elements', NULL, 6, 'peter', '2025-11-20 09:49:47', 4, '2025-11-20 09:49:47', '2025-11-20 09:49:47');
INSERT INTO cb_sub_modules (sub_module_name, sub_module_url, icon, `order`, added_by, added_on, module_id, created_at, updated_at) VALUES('Income Tax Settings', '/hrsettings/incometax/settings', NULL, 5, 'peter', '2025-11-20 09:49:47', 4, '2025-11-20 09:49:47', '2025-11-20 09:49:47');
INSERT INTO cb_sub_modules (sub_module_name, sub_module_url, icon, `order`, added_by, added_on, module_id, created_at, updated_at) VALUES('Payroll Calender', '/hrsettings/payroll/calendar', NULL, 4, 'maina', '2025-11-22 11:58:33', 4, '2025-11-22 11:58:33', '2025-11-22 11:58:33');
INSERT INTO cb_sub_modules (sub_module_name, sub_module_url, icon, `order`, added_by, added_on, module_id, created_at, updated_at) VALUES('Zone Assignments', '/hrsettings/zone/assignments', NULL, 11, 'maina', '2025-11-23 11:07:21', 4, '2025-11-23 11:07:21', '2025-11-23 11:07:21');



# Permissions
payroll_elements
workdays
public_holiday
shif_rates
nssf_rates
payroll_calendar
zone_assignments

NEW 
UPDATE cb_sub_modules
SET sub_module_name='Leave Applications', sub_module_url='/hr/leave-management/leave-applications', icon=NULL, `order`=8, added_by='kenneth', added_on='2025-11-20 01:46:23', module_id=3, created_at='2025-11-20 01:46:23', updated_at='2025-11-20 01:46:23'
WHERE id=15;
UPDATE cb_sub_modules
SET sub_module_name='Leave Calendar', sub_module_url='/hr/leave-management/leave-calendar-plan', icon=NULL, `order`=9, added_by='kenneth', added_on='2025-11-20 01:46:41', module_id=3, created_at='2025-11-20 01:46:41', updated_at='2025-11-20 01:46:41'
WHERE id=16;

Permissions
leave_calendar_plans
leave_applications

NEW 25 Nov 2025 Employee Contracts
#permissions
Human Resource module - contracts



NEW 26th November 2025
# Permissions
discipline_records

#Migrations
Added incident and action_taken migrations for staff discipline therefore run migrations

NEW 27th November 2025
# Permissions
employee_terminations

# Sub Module
Human Resource - > Employee Terminations -> /hr/employee/termination

# Module
Approval Control -> thumb_up

# Sub Module
Approval Control -> Maker–Checker -> /maker-checker/confirmations
Human Resource -> Communication -> /hr/communication

# Permissions
communications

Run migrations to get the communications table


# NEW 2025-12-02
# Permissions
communication_templates

#Sub Module
HR Settings -> Templates -> /hrsettings/communication/templates

remove communication from human resource not needed the new communication template handles that 


#NEW 3rd December 2025
Modulel -> HR Reports -> Leave Balance -> /hr/leave-management/leave-balance-report


#NEW 7th December 2025
Run Migrations


#new 11th December 2025
#Permissions
leave_adjustments

# Sub Module
Human Resource -> Leave Management -> Leave Adjustments -> /hr/leave-management/leave-adjustment

Run Migrations as well


#New 16th December 2025
#Sub-Module
Property - Inspections - /property/inspections


#New 12th January 2026
#Sub Menues

INSERT INTO cb_sub_modules
(sub_module_name, sub_module_url, icon, `order`, added_by, added_on, module_id, created_at, updated_at)
VALUES('Recovery', '/property/recoveries', NULL, 5, 'maina', '2026-01-12 13:16:44', 17, '2026-01-12 13:16:44', '2026-01-12 13:16:44');




#NEW 22nd January 2026

#Permissions
clients
properties
units
tenants
service_agreements
recoveries
lease_agreements
inspections

#Sub Modules
INSERT INTO cb_sub_modules
(sub_module_name, sub_module_url, icon, `order`, added_by, added_on, module_id, created_at, updated_at)
VALUES('Property Confirmations', '/property/confirmations', NULL, 2, 'maina', '2026-01-19 10:25:13', 18, '2026-01-19 10:25:13', '2026-01-19 10:25:13');




#NEW 29th January 2026
#Permissions
INSERT INTO cb_permissions
(name, display_name, description, created_at, updated_at, module_id, `action`)
VALUES('create_property_settings_items', 'create_property_settings_items', NULL, '2026-01-22 13:07:33', '2026-01-22 13:07:33', 21, NULL);
INSERT INTO cb_permissions
(name, display_name, description, created_at, updated_at, module_id, `action`)
VALUES('read_property_settings_items', 'read_property_settings_items', NULL, '2026-01-22 13:07:33', '2026-01-22 13:07:33', 21, NULL);
INSERT INTO cb_permissions
(name, display_name, description, created_at, updated_at, module_id, `action`)
VALUES('update_property_settings_items', 'update_property_settings_items', NULL, '2026-01-22 13:07:33', '2026-01-22 13:07:33', 21, NULL);
INSERT INTO cb_permissions
(name, display_name, description, created_at, updated_at, module_id, `action`)
VALUES('delete_property_settings_items', 'delete_property_settings_items', NULL, '2026-01-22 13:07:34', '2026-01-22 13:07:34', 21, NULL);


#Sub Module
INSERT INTO cb_sub_modules
(sub_module_name, sub_module_url, icon, `order`, added_by, added_on, module_id, created_at, updated_at)
VALUES('Items', '/property/settings/items', NULL, 1, 'maina', '2026-01-22 12:53:59', 21, '2026-01-22 12:53:59', '2026-01-22 12:53:59');
INSERT INTO cb_sub_modules
(sub_module_name, sub_module_url, icon, `order`, added_by, added_on, module_id, created_at, updated_at)
VALUES('Zones', '/property/settings/zones', NULL, 2, 'maina', '2026-01-22 13:26:40', 21, '2026-01-22 13:26:40', '2026-01-22 13:26:40');

#Main Menu
INSERT INTO cb_modules
(module_name, module_url, icon, module_order, added_by, added_on, created_at, updated_at, deleted, deleted_by, deleted_at, deleted_reason)
VALUES('Property Settings', NULL, 'settings_slow_motion', 10, 'maina', '2026-01-22 12:51:33', '2026-01-22 12:51:33', '2026-01-22 12:51:33', 0, NULL, NULL, NULL);





#NEW 5th February 2026
Sub Module
Service Agreements  /property/service-agreements
INSERT INTO cb_sub_modules
(sub_module_name, sub_module_url, icon, `order`, added_by, added_on, module_id, created_at, updated_at)
VALUES('Service Agreements', '/property/service-agreements', NULL, 7, 'maina', '2026-01-27 13:51:23', 17, '2026-01-27 13:51:23', '2026-01-27 13:51:23');


$NEW 12th February 2026
#Permissions
INSERT INTO cb_permissions
(name, display_name, description, created_at, updated_at, module_id, `action`)
VALUES('create_tenant_movements', 'create_tenant_movements', NULL, '2026-01-30 13:12:48', '2026-01-30 13:12:48', 17, NULL);
INSERT INTO cb_permissions
(name, display_name, description, created_at, updated_at, module_id, `action`)
VALUES('read_tenant_movements', 'read_tenant_movements', NULL, '2026-01-30 13:12:49', '2026-01-30 13:12:49', 17, NULL);
INSERT INTO cb_permissions
(name, display_name, description, created_at, updated_at, module_id, `action`)
VALUES('update_tenant_movements', 'update_tenant_movements', NULL, '2026-01-30 13:12:50', '2026-01-30 13:12:50', 17, NULL);
INSERT INTO cb_permissions
(name, display_name, description, created_at, updated_at, module_id, `action`)
VALUES('delete_tenant_movements', 'delete_tenant_movements', NULL, '2026-01-30 13:12:50', '2026-01-30 13:12:50', 17, NULL);
INSERT INTO cb_permissions
(name, display_name, description, created_at, updated_at, module_id, `action`)
VALUES('create_tenant_blacklist', 'create_tenant_blacklist', NULL, '2026-01-30 14:28:51', '2026-01-30 14:28:51', 17, NULL);
INSERT INTO cb_permissions
(name, display_name, description, created_at, updated_at, module_id, `action`)
VALUES('read_tenant_blacklist', 'read_tenant_blacklist', NULL, '2026-01-30 14:28:51', '2026-01-30 14:28:51', 17, NULL);
INSERT INTO cb_permissions
(name, display_name, description, created_at, updated_at, module_id, `action`)
VALUES('update_tenant_blacklist', 'update_tenant_blacklist', NULL, '2026-01-30 14:28:51', '2026-01-30 14:28:51', 17, NULL);
INSERT INTO cb_permissions
(name, display_name, description, created_at, updated_at, module_id, `action`)
VALUES('delete_tenant_blacklist', 'delete_tenant_blacklist', NULL, '2026-01-30 14:28:51', '2026-01-30 14:28:51', 17, NULL);
INSERT INTO cb_permissions
(name, display_name, description, created_at, updated_at, module_id, `action`)
VALUES('create_repair_requests', 'create_repair_requests', NULL, '2026-02-03 13:20:53', '2026-02-03 13:20:53', 17, NULL);
INSERT INTO cb_permissions
(name, display_name, description, created_at, updated_at, module_id, `action`)
VALUES('read_repair_requests', 'read_repair_requests', NULL, '2026-02-03 13:20:54', '2026-02-03 13:20:54', 17, NULL);
INSERT INTO cb_permissions
(name, display_name, description, created_at, updated_at, module_id, `action`)
VALUES('update_repair_requests', 'update_repair_requests', NULL, '2026-02-03 13:20:54', '2026-02-03 13:20:54', 17, NULL);
INSERT INTO cb_permissions
(name, display_name, description, created_at, updated_at, module_id, `action`)
VALUES('delete_repair_requests', 'delete_repair_requests', NULL, '2026-02-03 13:20:54', '2026-02-03 13:20:54', 17, NULL);





#Sub Modules
Movements	/property/movements
Blacklist	/property/blacklist
R&M	/property/repairs
Repairs	/property/settings/repairs