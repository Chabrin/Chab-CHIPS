<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SLAController;
use App\Http\Controllers\TaxController;
use App\Http\Controllers\FinanceTaxController;
use App\Http\Controllers\FinanceTaxSettingsController;
use App\Http\Controllers\Finance\TransactionTypesController;
use App\Http\Controllers\Finance\RecurrentClientRemittancesController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\LogsController;
use App\Http\Controllers\FundsController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\TransController;
use App\Http\Controllers\UnitsController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\LeasesController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ClientsController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\TenantsController;
use App\Http\Controllers\AccountsController;
use App\Http\Controllers\PayslipsController;
use App\Http\Controllers\RecoveryController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ApprovalsController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CompaniesController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HrReportsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BulkUploadController;
use App\Http\Controllers\EodReportsController;
use App\Http\Controllers\HrSettingsController;
use App\Http\Controllers\PropertiesController;
use App\Http\Controllers\SecuritiesController;
use App\Http\Controllers\InspectionsController;
use App\Http\Controllers\FundManagersController;
use App\Http\Controllers\TransactionsController;
use App\Http\Controllers\ContributionsController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\InterestRatesController;
use App\Http\Controllers\SchemeUploadsController;
use App\Http\Controllers\SystemControlController;
use App\Http\Controllers\UserAccessLogController;
use App\Http\Controllers\ApprovalRequestsController;
use App\Http\Controllers\WorkflowApprovalController;
use App\Http\Controllers\DisciplineTrackerController;
use App\Http\Controllers\PropertyConfirmationController;
use App\Http\Controllers\ClientReturnsController;
use App\Http\Controllers\CompanyBankAccountController;
use App\Http\Controllers\ChequeBookController;
use App\Http\Controllers\SelfServiceController;
use App\Http\Controllers\PaymentsAllocationController;



##### START CHABRIN - ROUTES FILE #####


Route::middleware(['auth', 'onelogin', 'acess_checker'])->group(function () {

    // Notifications
    Route::get('/notifications', [App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/unread-count', [App\Http\Controllers\NotificationController::class, 'getUnreadCount'])->name('notifications.unread-count');
    Route::get('/notifications/{id}', [App\Http\Controllers\NotificationController::class, 'show'])->name('notifications.show');
    Route::post('/notifications/{id}/read', [App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
    Route::post('/notifications/read-all', [App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');
    Route::delete('/notifications/{id}', [App\Http\Controllers\NotificationController::class, 'destroy'])->name('notifications.destroy');

    ####### HR SECTION START ########

    // HR Settings - Departments
    Route::get('/hrsettings/departments', [HrSettingsController::class, 'departmentIndex'])->name('settings.hr.departments');

    // HR Departments AJAX CRUD
    Route::get('/ajax/hr/department/list', [HrSettingsController::class, 'departmentList'])->name('ajax.hr.department.list');
    Route::post('/ajax/hr/department', [HrSettingsController::class, 'departmentStore'])->name('ajax.hr.department.store');
    Route::post('/ajax/hr/department/{id}', [HrSettingsController::class, 'departmentUpdate'])->name('ajax.hr.department.update');
    Route::delete('/ajax/hr/department/{id}', [HrSettingsController::class, 'departmentDelete'])->name('ajax.hr.department.delete');

    // HR Settings - Leave Types
    Route::get('/hrsettings/leavetypes', [HrSettingsController::class, 'leaveTypeIndex'])->name('settings.hr.leave_types');

    // HR Leave Types AJAX CRUD
    Route::get('/ajax/hr/leave-type/list', [HrSettingsController::class, 'leaveTypeList'])->name('ajax.hr.leave_type.list');

    // HR Settings - Staff Positions
    Route::get('/hr/staff/positions', [HrSettingsController::class, 'positionsIndex'])->name('settings.hr.positions');
    Route::get('/hrsettings/positions/{id}', [HrSettingsController::class, 'positionsShow'])->name('settings.hr.positions.show');
    Route::post('/hrsettings/positions/{id}/elements', [HrSettingsController::class, 'positionsUpdateElements'])->name('settings.hr.positions.update-elements');

    // HR Positions AJAX
    Route::get('/ajax/hr/positions/list', [HrSettingsController::class, 'positionsList'])->name('ajax.hr.positions.list');
    Route::get('/ajax/hr/positions/dropdowns', [HrSettingsController::class, 'positionsDropdowns'])->name('ajax.hr.positions.dropdowns');
    Route::get('/ajax/hr/positions/get', [HrSettingsController::class, 'positionsGet'])->name('ajax.hr.positions.get');
    Route::post('/ajax/hr/positions', [HrSettingsController::class, 'positionsStoreAjax'])->name('ajax.hr.positions.store');
    Route::post('/ajax/hr/positions/{id}', [HrSettingsController::class, 'positionsUpdateAjax'])->name('ajax.hr.positions.update');

    // Payroll Settings - Tax Bands
    Route::get('/payroll/tax-bands', [PayrollController::class, 'taxBandsIndex'])->name('payroll.tax_bands');

    // Payroll Tax Bands AJAX CRUD
    Route::get('/ajax/payroll/tax-bands/list', [PayrollController::class, 'taxBandsList'])->name('ajax.payroll.tax_bands.list');
    Route::post('/ajax/payroll/tax-bands', [PayrollController::class, 'taxBandsStore'])->name('ajax.payroll.tax_bands.store');
    Route::post('/ajax/payroll/tax-bands/{id}', [PayrollController::class, 'taxBandsUpdate'])->name('ajax.payroll.tax_bands.update');

    // Payroll Settings - Elements
    Route::get('/payroll/elements', [PayrollController::class, 'payrollElementsIndex'])->name('payroll.elements');

    // Payroll Elements AJAX
    Route::get('/ajax/payroll/elements/list', [PayrollController::class, 'payrollElementsList'])->name('ajax.payroll.elements.list');
    Route::get('/ajax/payroll/elements/list/simpler', [PayrollController::class, 'payrollElementsListSimpler'])->name('ajax.payroll.elements.list.simpler');
    Route::get('/ajax/payroll/elements/accounts/list', [PayrollController::class, 'payrollElementsListAccounts'])->name('ajax.payroll.elements.accounts.list');
    Route::get('/ajax/payroll/elements/staff_positions/list', [PayrollController::class, 'payrollElementsListStaffPositions'])->name('ajax.payroll.elements.staff_positions.list');
    Route::post('/ajax/save/payroll/elements', [PayrollController::class, 'payrollElementsStore'])->name('ajax.payroll.elements.store');
    Route::post('/ajax/payroll/elements/{id}', [PayrollController::class, 'payrollElementsUpdate'])->name('ajax.payroll.elements.update');
    Route::delete('/ajax/payroll/elements/{id}', [PayrollController::class, 'payrollElementsDelete'])->name('ajax.payroll.elements.delete');
    Route::post('/ajax/hr/leave-type', [HrSettingsController::class, 'leaveTypeStore'])->name('ajax.hr.leave_type.store');
    Route::post('/ajax/hr/leave-type/{id}', [HrSettingsController::class, 'leaveTypeUpdate'])->name('ajax.hr.leave_type.update');
    Route::delete('/ajax/hr/leave-type/{id}', [HrSettingsController::class, 'leaveTypeDelete'])->name('ajax.hr.leave_type.delete');

    // Staff Management Routes
    Route::get('/hr/employees', [StaffController::class, 'index'])->name('staff.index');
    Route::get('/hr/employees/create', [StaffController::class, 'create'])->name('staff.create');
    Route::post('/hr/employees', [StaffController::class, 'store'])->name('staff.store');
    Route::get('/hr/employees/{id}', [StaffController::class, 'show'])->name('staff.show');
    Route::get('/hr/employees/{id}/edit', [StaffController::class, 'edit'])->name('staff.edit');
    Route::put('/hr/employees/{id}', [StaffController::class, 'update'])->name('staff.update');
    Route::patch('/hr/employees/{id}/status', [StaffController::class, 'updateStatus'])->name('staff.updateStatus');
    Route::post('/hr/employees/{id}/convert-user', [StaffController::class, 'convertToUser'])->name('staff.convertToUser');
    Route::delete('/hr/employees/{id}', [StaffController::class, 'destroy'])->name('staff.destroy');

    // AJAX Routes for Staff
    Route::get('/ajax/zone-areas/{zoneId}', [StaffController::class, 'getZoneAreas'])->name('ajax.zone_areas');

    // Employee Contracts
    Route::get('/hr/contracts', [StaffController::class, 'contractsIndex'])->name('contract.index');
    Route::get('/hr/contracts/create', [StaffController::class, 'contractsCreate'])->name('contract.create');
    Route::get('/hr/contracts/bulk-edit', [StaffController::class, 'bulkEditContractsIndex'])->name('contract.bulk.edit');
    Route::put('/hr/contracts/bulk-update', [StaffController::class, 'bulkUpdateContracts'])->name('contract.bulk.update');
    Route::put('/hr/contracts/{id}/elements', [StaffController::class, 'bulkUpdateContractElements'])->name('contract.elements.update');
    Route::get('/hr/contracts/{id}/edit', [StaffController::class, 'contractsEdit'])->name('contract.edit');
    Route::put('/hr/contracts/{id}', [StaffController::class, 'contractUpdate'])->name('contract.update');
    Route::get('/ajax/hr/contracts/list', [StaffController::class, 'contractList'])->name('ajax.contract.list');
    Route::post('/hr/contracts/store', [StaffController::class, 'contractStore'])->name('contract.store');
    Route::post('/ajax/hr/contracts/{id}', [StaffController::class, 'contractUpdate'])->name('ajax.contract.update');
    Route::delete('/ajax/hr/contracts/{id}', [StaffController::class, 'contractDelete'])->name('ajax.contract.delete');

    // Staff Discipline Tracker
    Route::get('/ajax/incidents/list', [StaffController::class, 'disciplineIncidentsList'])->name('ajax.incidents.list');
    Route::get('/ajax/action-taken/list', [StaffController::class, 'disciplineActionsTakenList'])->name('ajax.action.taken.list');
    Route::post('/ajax/incidents/store', [StaffController::class, 'incidentsStore']);
    Route::post('/ajax/action-taken/store', [StaffController::class, 'actionTakenStore']);
    Route::delete('/ajax/discipline/document/delete/{id}', [StaffController::class, 'disciplineDocumentDelete']);

    // NEW Discipline Tracker - 14-Step Workflow
    Route::get('/hr/discipline/tracker', [DisciplineTrackerController::class, 'index'])->name('discipline.tracker.index');
    Route::get('/discipline/tracker/cases/list', [DisciplineTrackerController::class, 'casesList'])->name('discipline.tracker.cases.list');
    Route::get('/discipline/tracker/case/{encryptedId}', [DisciplineTrackerController::class, 'show'])->name('discipline.tracker.show');
    Route::get('/discipline/tracker/case/{caseId}/timeline', [DisciplineTrackerController::class, 'getTimeline'])->name('discipline.tracker.timeline');

    // Step 1: Create Incident Report
    Route::get('/discipline/tracker/create/incident', [DisciplineTrackerController::class, 'createIncident'])->name('discipline.tracker.create.incident');
    Route::post('/discipline/tracker/store/incident', [DisciplineTrackerController::class, 'storeIncident'])->name('discipline.tracker.store.incident');

    // Step 2: Register Case
    Route::post('/discipline/tracker/case/{caseId}/register', [DisciplineTrackerController::class, 'registerCase'])->name('discipline.tracker.register.case');

    // Step 3: Assign Investigator
    Route::post('/discipline/tracker/case/{caseId}/assign-investigator', [DisciplineTrackerController::class, 'assignInvestigator'])->name('discipline.tracker.assign.investigator');

    // Step 4: Upload Evidence
    Route::post('/discipline/tracker/case/{caseId}/upload-evidence', [DisciplineTrackerController::class, 'uploadEvidence'])->name('discipline.tracker.upload.evidence');

    // Step 5: Add Witness Statement
    Route::post('/discipline/tracker/case/{caseId}/add-witness', [DisciplineTrackerController::class, 'addWitnessStatement'])->name('discipline.tracker.add.witness');

    // Step 6: Add Investigation Note
    Route::post('/discipline/tracker/case/{caseId}/add-note', [DisciplineTrackerController::class, 'addInvestigationNote'])->name('discipline.tracker.add.note');

    // Step 7: Issue Show Cause Notice
    Route::post('/discipline/tracker/case/{caseId}/issue-show-cause', [DisciplineTrackerController::class, 'issueShowCause'])->name('discipline.tracker.issue.show.cause');
    Route::post('/discipline/tracker/case/{id}/extend-deadline', [DisciplineTrackerController::class, 'extendResponseDeadline'])->name('discipline.tracker.extend.deadline');
    Route::get('/discipline/tracker/reports/lapsed-deadlines', [DisciplineTrackerController::class, 'lapsedDeadlinesReport'])->name('discipline.tracker.reports.lapsed');
    Route::post('/discipline/tracker/request-extension', [DisciplineTrackerController::class, 'requestDeadlineExtension'])->name('discipline.tracker.request.extension');
    Route::post('/discipline/tracker/review-extension', [DisciplineTrackerController::class, 'reviewExtensionRequest'])->name('discipline.tracker.review.extension');

    // Step 8: Submit Employee Response
    Route::post('/discipline/tracker/case/{caseId}/submit-response', [DisciplineTrackerController::class, 'submitEmployeeResponse'])->name('discipline.tracker.submit.response');

    // Step 9: Schedule Hearing
    Route::post('/discipline/tracker/case/{caseId}/schedule-hearing', [DisciplineTrackerController::class, 'scheduleHearing'])->name('discipline.tracker.schedule.hearing');

    // Step 10: Record Hearing Minutes
    Route::post('/discipline/tracker/case/{caseId}/record-minutes', [DisciplineTrackerController::class, 'recordHearingMinutes'])->name('discipline.tracker.record.minutes');

    // Step 11: Save Final Outcome
    Route::post('/discipline/tracker/case/{caseId}/save-outcome', [DisciplineTrackerController::class, 'saveFinalOutcome'])->name('discipline.tracker.save.outcome');

    // Step 12: Assign Training (Optional)
    Route::post('/discipline/tracker/case/{caseId}/assign-training', [DisciplineTrackerController::class, 'assignTraining'])->name('discipline.tracker.assign.training');

    // Step 13: Update Disciplinary History
    Route::post('/discipline/tracker/case/{caseId}/update-history', [DisciplineTrackerController::class, 'updateDisciplinaryHistory'])->name('discipline.tracker.update.history');

    // Step 14: Close Case
    Route::post('/discipline/tracker/case/{caseId}/close-case', [DisciplineTrackerController::class, 'closeCase'])->name('discipline.tracker.close.case');

    // Skip Step (for flexible workflow)
    Route::post('/discipline/tracker/case/{caseId}/skip-step', [DisciplineTrackerController::class, 'skipStep'])->name('discipline.tracker.skip.step');


    // Employee Termination Routes
    Route::get('/hr/employee/termination', [StaffController::class, 'employeeTerminationIndex'])->name('employee.termination.index');
    Route::get('/hr/employees/termination/{encryptedTerminationID}', [StaffController::class, 'employeeTerminationShow'])->name('employee.termination.show');
    Route::get('/ajax/hr/employee/termination/list', [StaffController::class, 'employeeTerminationList'])->name('ajax.employee.termination.list');
    Route::get('/hr/employee/termination/create', [StaffController::class, 'employeeTerminationCreate'])->name('employee.termination.create');
    Route::get('/hr/employee/termination/edit/{id}', [StaffController::class, 'employeeTerminationEdit'])->name('employee.termination.edit');
    Route::post('/hr/employee/termination', [StaffController::class, 'employeeTerminationStore'])->name('employee.termination.store');
    Route::get('/ajax/hr/employee/termination/contracts/list/{staffId}', [StaffController::class, 'terminationContractList'])->name('ajax.contract.list');
    Route::post('/hr/employee/termination/{id}', [StaffController::class, 'employeeTerminationUpdate'])->name('employee.termination.update');
    Route::delete('/ajax/hr/employee/termination/document/{id}/delete', [StaffController::class, 'employeeTerminationDocumentDelete']);

    // HR Settings - Workdays
    Route::get('/hrsettings/workdays', [HrSettingsController::class, 'workdaysIndex'])->name('settings.hr.workdays');
    Route::get('/ajax/hr/workdays/list', [HrSettingsController::class, 'workDaysList'])->name('ajax.workDays.list');
    Route::post('/ajax/hr/workdays', [HrSettingsController::class, 'workDaysStore'])->name('ajax.workDays.store');
    Route::post('/ajax/hr/workdays/{id}', [HrSettingsController::class, 'workDaysUpdate'])->name('ajax.workDays.update');
    Route::delete('/ajax/hr/workdays/{id}', [HrSettingsController::class, 'workDaysDelete'])->name('ajax.workDays.delete');

    // HR Settings - Communication Templates
    Route::get('/hrsettings/communication/templates', [HrSettingsController::class, 'communicationTemplatesIndex'])->name('settings.hr.communication.templates');
    Route::get('/hrsettings/communication/templates/create', [HrSettingsController::class, 'communicationTemplatesCreate'])->name('settings.hr.communication.templates.create');
    Route::get('/hrsettings/communication/templates/edit/{encryptedTemplateId}', [HrSettingsController::class, 'communicationTemplatesEdit'])->name('settings.hr.communication.templates.edit');
    Route::get('/hrsettings/communication/templates/show/{id}', [HrSettingsController::class, 'communicationTemplatesShow'])->name('hr.settings.templates.show');
    Route::delete('/ajax/hr-settings/communication-templates/delete-file/{id}', [HrSettingsController::class, 'communicationTemplatesDeleteFile']);
    Route::get('/ajax/hr-settings/communication-templates/list', [HrSettingsController::class, 'communicationTemplatesList']);
    Route::post('/ajax/hr-settings/communication-templates/store', [HrSettingsController::class, 'communicationTemplatesStore']);
    Route::put('/ajax/hr-settings/communication-templates/update/{id}', [HrSettingsController::class, 'communicationTemplatesUpdate']);
    Route::delete('/ajax/hr-settings/communication-templates/delete/{id}', [HrSettingsController::class, 'communicationTemplatesDelete']);

    Route::get('/ajax/hr-settings/communications/list', [HrSettingsController::class, 'communicationsList']);
    Route::get('/ajax/hr-settings/communications/show/{id}', [HrSettingsController::class, 'communicationsShow']);

    // HR Settings - Public Holidays
    Route::get('/hrsettings/publicholidays', [HrSettingsController::class, 'publicHolidaysIndex'])->name('settings.hr.publicholidays');
    Route::get('/get/ajax/hr/publicholidays/list', [HrSettingsController::class, 'publicHolidaysList'])->name('ajax.publicHolidays.list');
    Route::post('/ajax/hr/publicholidays', [HrSettingsController::class, 'publicHolidaysStore'])->name('ajax.publicHolidays.store');
    Route::post('/ajax/hr/publicholidays/{id}', [HrSettingsController::class, 'publicHolidaysUpdate'])->name('ajax.publicHolidays.update');
    Route::delete('/ajax/hr/publicholidays/{id}', [HrSettingsController::class, 'publicHolidaysDelete'])->name('ajax.publicHolidays.delete');

    // HR Settings - Payroll Calendar
    Route::get('/payroll/payroll/calendar', [PayrollController::class, 'calendarIndex'])->name('settings.hr.payroll.calendar');
    Route::get('/ajax/hr/payroll/calendar/list', [PayrollController::class, 'calendarList'])->name('ajax.payrollCalendar.list');
    Route::post('/ajax/hr/payroll/calendar', [PayrollController::class, 'calendarStore'])->name('ajax.payrollCalendar.store');
    Route::post('/ajax/hr/payroll/calendar/{id}', [PayrollController::class, 'calendarUpdate'])->name('ajax.payrollCalendar.update');
    Route::delete('/ajax/hr/payroll/calendar/{id}', [PayrollController::class, 'calendarDelete'])->name('ajax.payrollCalendar.delete');

    // Payroll Periods
    Route::get('/ajax/hr/payroll/calendar/{calendarId}/periods', [PayrollController::class, 'periodsList'])->name('ajax.payrollPeriods.list');
    Route::post('/ajax/hr/payroll/calendar/{calendarId}/periods', [PayrollController::class, 'periodsStore'])->name('ajax.payrollPeriods.store');
    Route::post('/ajax/hr/payroll/calendar/{calendarId}/periods/{periodId}', [PayrollController::class, 'periodsUpdate'])->name('ajax.payrollPeriods.update');
    Route::delete('/ajax/hr/payroll/calendar/{calendarId}/periods/{periodId}', [PayrollController::class, 'periodsDelete'])->name('ajax.payrollPeriods.delete');

    // Deductions - HELB Loans
    Route::get('/payroll/helbloans', [App\Http\Controllers\DeductionsController::class, 'helbIndex'])->name('deductions.helb');
    Route::get('/payroll/helbloans/{id}', [App\Http\Controllers\DeductionsController::class, 'helbShow'])->name('deductions.helb.show');
    Route::get('/ajax/deductions/helb/list', [App\Http\Controllers\DeductionsController::class, 'helbList'])->name('ajax.helb.list');
    Route::get('/ajax/deductions/helb/{id}/repeats', [App\Http\Controllers\DeductionsController::class, 'helbRepeatsList'])->name('ajax.helb.repeats');
    Route::get('/ajax/deductions/helb/staff', [App\Http\Controllers\DeductionsController::class, 'getStaffList'])->name('ajax.helb.staff');
    Route::post('/ajax/deductions/helb', [App\Http\Controllers\DeductionsController::class, 'helbStore'])->name('ajax.helb.store');
    Route::post('/ajax/deductions/helb/{id}', [App\Http\Controllers\DeductionsController::class, 'helbUpdate'])->name('ajax.helb.update');
    Route::delete('/ajax/deductions/helb/{id}', [App\Http\Controllers\DeductionsController::class, 'helbDestroy'])->name('ajax.helb.delete');

    // Deductions - Prorated Deductions
    Route::get('/payroll/prorated/deductions', [App\Http\Controllers\DeductionsController::class, 'proratedIndex'])->name('deductions.prorated');
    Route::get('/payroll/prorated/deductions/{id}', [App\Http\Controllers\DeductionsController::class, 'proratedShow'])->name('deductions.prorated.show');
    Route::get('/ajax/deductions/prorated/list', [App\Http\Controllers\DeductionsController::class, 'proratedList'])->name('ajax.prorated.list');
    Route::get('/ajax/deductions/prorated/{id}/repeats', [App\Http\Controllers\DeductionsController::class, 'proratedRepeatsList'])->name('ajax.prorated.repeats');
    Route::get('/ajax/deductions/prorated/elements', [App\Http\Controllers\DeductionsController::class, 'getElementsList'])->name('ajax.prorated.elements');
    Route::post('/ajax/deductions/prorated', [App\Http\Controllers\DeductionsController::class, 'proratedStore'])->name('ajax.prorated.store');
    Route::post('/ajax/deductions/prorated/{id}', [App\Http\Controllers\DeductionsController::class, 'proratedUpdate'])->name('ajax.prorated.update');

    // HR Settings - Income Tax Settings
    Route::get('/hrsettings/incometax/settings', [PayrollController::class, 'incomeTaxSettingsIndex'])->name('settings.hr.incometax.settings');

    // Payroll Processing
    Route::get('/payroll/processing', [PayrollController::class, 'processingIndex'])->name('payroll.processing');
    Route::get('/ajax/payroll/recent-periods', [PayrollController::class, 'getRecentPeriodsSummary'])->name('ajax.payroll.recentPeriods');
    Route::get('/ajax/payroll/unprocessed-periods', [PayrollController::class, 'getUnprocessedPeriods'])->name('ajax.payroll.unprocessedPeriods');
    Route::post('/ajax/payroll/process', [PayrollController::class, 'processPayroll'])->name('ajax.payroll.process');

    // Payslips
    Route::get('/payroll/payslips', [PayslipsController::class, 'index'])->name('payroll.payslips');
    Route::get('/ajax/payslips/search-employees', [PayslipsController::class, 'searchEmployees'])->name('ajax.payslips.search');
    Route::post('/ajax/payslips/view', [PayslipsController::class, 'viewPayslip'])->name('ajax.payslips.view');
    Route::get('/payslips/download/{staffId}/{month}', [PayslipsController::class, 'downloadPayslip'])->name('payslips.download');
    Route::post('/ajax/payslips/email', [PayslipsController::class, 'emailPayslip'])->name('ajax.payslips.email');

    // HR Reports - Statutory Deductions
    Route::get('/statutory/deductions', [HrReportsController::class, 'statutoryDeductions'])->name('reports.statutory.deductions');
    Route::post('/ajax/statutory-deductions/data', [HrReportsController::class, 'getStatutoryDeductions'])->name('ajax.statutory.deductions.data');
    Route::post('/statutory-deductions/download', [HrReportsController::class, 'downloadStatutoryDeductions'])->name('statutory.deductions.download');

    // HR Settings - Shift Rates
    Route::get('/hrsettings/shif/rates', [HrSettingsController::class, 'shifRatesIndex'])->name('settings.hr.shif.rates');
    Route::get('/ajax/hr/shif/rates/list', [HrSettingsController::class, 'shifRatesList'])->name('ajax.shifRates.list');
    Route::post('/ajax/hr/shif/rates', [HrSettingsController::class, 'shifRatesStore'])->name('ajax.shifRates.store');
    Route::post('/ajax/hr/shif/rates/{id}', [HrSettingsController::class, 'shifRatesUpdate'])->name('ajax.shifRates.update');
    Route::delete('/ajax/hr/shif/rates/{id}', [HrReportsController::class, 'shifRatesDelete'])->name('ajax.shifRates.delete');


    // HR Settings - NSSF Rates
    Route::get('/hrsettings/nssf/rates', [HrSettingsController::class, 'nssfRatesIndex'])->name('settings.hr.nssf.rates');
    Route::get('/ajax/hr/nssf/rates/list', [HrSettingsController::class, 'nssfRatesList'])->name('ajax.nssfRates.list');
    Route::post('/ajax/hr/nssf/rates', [HrSettingsController::class, 'nssfRatesStore'])->name('ajax.nssfRates.store');
    Route::post('/ajax/hr/nssf/rates/{id}', [HrSettingsController::class, 'nssfRatesUpdate'])->name('ajax.nssfRates.update');
    Route::delete('/ajax/hr/nssf/rates/{id}', [HrSettingsController::class, 'nssfRatesDelete'])->name('ajax.nssfRates.delete');

    // HR Settings - Zone Assignments
    Route::get('/hr/zone/assignments', [HrSettingsController::class, 'zoneAssignmentsIndex'])->name('settings.hr.zone.assignments');
    Route::get('/hr/zone/assignments/{id}/view', [HrSettingsController::class, 'zoneAssignmentView'])->name('settings.hr.zone.assignments.view');
    Route::get('/ajax/hr/zone/assignments/list', [HrSettingsController::class, 'zoneAssignmentsList'])->name('ajax.zoneAssignments.list');
    Route::post('/ajax/hr/zone/assignments', [HrSettingsController::class, 'zoneAssignmentsStore'])->name('ajax.zoneAssignments.store');
    Route::post('/ajax/hr/zone/assignments/{id}', [HrSettingsController::class, 'zoneAssignmentsUpdate'])->name('ajax.zoneAssignments.update');
    Route::post('/ajax/hr/zone/assignments/{id}/unassign-property', [HrSettingsController::class, 'zoneAssignmentUnassignProperty'])->name('ajax.zoneAssignments.unassignProperty');
    Route::delete('/ajax/hr/zone/assignments/{id}', [HrSettingsController::class, 'zoneAssignmentsDelete'])->name('ajax.zoneAssignments.delete');

    Route::get('/ajax/hr/properties/by-zone/{zoneId}', [HrSettingsController::class, 'propertiesByZone'])->name('ajax.properties.byZone');
    Route::get('/ajax/hr/properties/available-in-zone', [HrSettingsController::class, 'availablePropertiesInZone'])->name('ajax.properties.availableInZone');
    Route::get('/ajax/hr/staff/list', [HrSettingsController::class, 'staffList'])->name('ajax.staff.list');
    Route::get('/ajax/hr/assignment_types/list', [HrSettingsController::class, 'assignmentTypesList'])->name('ajax.assignmentTypes.list');
    Route::get('/ajax/hr/roles/list', [HrSettingsController::class, 'rolesList'])->name('ajax.roles.list');
    Route::get('/ajax/hr/zones/list', [HrSettingsController::class, 'zonesList'])->name('ajax.zones.list');

    ####### Leave Management Section Start ########

    // Leave Calendar Plan Routes
    Route::get('/ajax/hr/leave_types/list', [\App\Http\Controllers\LeaveManagementController::class, 'leaveTypesList'])->name('ajax.leaveTypes.list');
    Route::get('/hr/leave-management/leave-calendar-plan', [\App\Http\Controllers\LeaveManagementController::class, 'leaveCalendarPlan'])->name('leave-management.leave-calendar-plan');
    Route::get('/ajax/leave-management/leave-calendar-plan/list', [\App\Http\Controllers\LeaveManagementController::class, 'leaveCalendarPlanList'])->name('ajax.leaveCalendarPlan.list');
    Route::post('/ajax/leave-management/leave-calendar-plan', [\App\Http\Controllers\LeaveManagementController::class, 'leaveCalendarPlanStore'])->name('ajax.leaveCalendarPlan.store');
    Route::post('/ajax/leave-management/leave-calendar-plan/{id}', [\App\Http\Controllers\LeaveManagementController::class, 'leaveCalendarPlanUpdate'])->name('ajax.leaveCalendarPlan.update');
    Route::delete('/ajax/leave-management/leave-calendar-plan/{id}', [\App\Http\Controllers\LeaveManagementController::class, 'leaveCalendarPlanDelete'])->name('ajax.leaveCalendarPlan.delete');

    Route::get('/ajax/hr/leave-management/leave-calendar-plan/available-days/{staffId}/{leaveTypeId}', [\App\Http\Controllers\LeaveManagementController::class, 'calenderPlanAvailableLeaveDays'])->name('ajax.leave.available-days');
    Route::post('/ajax/hr/leave-management/leave-calendar-plan/validate-date', [\App\Http\Controllers\LeaveManagementController::class, 'validateStartDate'])->name('ajax.leaveCalendarPlan.validateDate');
    Route::post('/ajax/hr/leave-management/leave-calendar-plan/generate-annual', [\App\Http\Controllers\LeaveManagementController::class, 'generateAnnualPlans'])->name('leave.calendar.generate.annual');

    // Leave Applications Routes
    Route::get('/hr/leave-management/leave-applications', [\App\Http\Controllers\LeaveManagementController::class, 'leaveApplications'])->name('leave-management.leave-applications');
    Route::get('/hr/leave-management/leave-applications/{encryptedLeaveApplicationId}', [\App\Http\Controllers\LeaveManagementController::class, 'leaveApplicationsByStaff'])->name('leave-management.leave-applications.by-staff');
    Route::get('/ajax/leave-management/leave-applications/list', [\App\Http\Controllers\LeaveManagementController::class, 'leaveApplicationsList'])->name('ajax.leaveApplications.list');
    Route::post('/ajax/leave-management/leave-applications', [\App\Http\Controllers\LeaveManagementController::class, 'leaveApplicationsStore'])->name('ajax.leaveApplications.store');
    Route::post('/ajax/leave-management/leave-applications/{id}', [\App\Http\Controllers\LeaveManagementController::class, 'leaveApplicationsUpdate'])->name('ajax.leaveApplications.update');
    Route::delete('/ajax/leave-management/leave-applications/{id}', [\App\Http\Controllers\LeaveManagementController::class, 'leaveApplicationsDelete'])->name('ajax.leaveApplications.delete');
    Route::get('/ajax/hr/leave/available-days/{staffId}/{leaveTypeId}', [\App\Http\Controllers\LeaveManagementController::class, 'availableLeaveDays'])->name('ajax.leave.available-days');
    Route::get('/ajax/hr/publicholidays/list', [\App\Http\Controllers\LeaveManagementController::class, 'publicHolidaysList'])->name('ajax.publicholidays.list');


    // Leave Adjustments
    Route::get('/hr/leave-management/leave-adjustments', [\App\Http\Controllers\LeaveManagementController::class, 'leaveAdjustments'])->name('leave.adjustments');
    Route::get('/ajax/leave-management/leave-adjustments/list', [\App\Http\Controllers\LeaveManagementController::class, 'leaveAdjustmentsList']);
    Route::get('/ajax/leave-management/leave-adjustments/{id}', [\App\Http\Controllers\LeaveManagementController::class, 'leaveAdjustmentDetails']);
    Route::post('/ajax/leave-management/leave-adjustments/{id}/adjust', [\App\Http\Controllers\LeaveManagementController::class, 'adjustLeaveDays']);
    Route::get('/ajax/leave-management/leave-adjustments/{id}/history', [\App\Http\Controllers\LeaveManagementController::class, 'leaveAdjustmentHistory']);

    // Leave Balance Report
    Route::get('/hr/leave-management/leave-balance-report', [\App\Http\Controllers\LeaveManagementController::class, 'leaveBalanceReport'])
        ->name('leave-management.leave-balance-report');
    Route::get('/ajax/leave-management/leave-balance-report-data', [\App\Http\Controllers\LeaveManagementController::class, 'leaveBalanceReportData'])
        ->name('leave-management.leave-balance-report-data');
    Route::get('/ajax/leave-management/export-leave-balance-report', [\App\Http\Controllers\LeaveManagementController::class, 'exportLeaveBalanceReport'])
        ->name('leave-management.export-leave-balance-report');

    ####### Leave Management Section End ########

    Route::get('/maker-checker/confirmations', [WorkflowApprovalController::class, 'index'])->name('workflow.approvals.confirmations');
    Route::get('/ajax/workflow/approvals/list', [WorkflowApprovalController::class, 'approvalsList'])->name('ajax.workflow.approvals.list');
    Route::get('/ajax/workflow-approvals/leave-application-days/{id}', [WorkflowApprovalController::class, 'getLeaveApplicationDays']);

    // Single Approve/Reject
    Route::post('/ajax/workflow/approval/{id}/approve', [WorkflowApprovalController::class, 'approve'])->name('ajax.workflow.approval.approve');
    Route::post('/ajax/workflow/approval/{id}/reject', [WorkflowApprovalController::class, 'reject'])->name('ajax.workflow.approval.reject');

    // Bulk Approve/Reject
    Route::post('/ajax/workflow/approval/bulk/approve', [WorkflowApprovalController::class, 'bulkApprove'])->name('ajax.workflow.approval.bulk.approve');
    Route::post('/ajax/workflow/approval/bulk/reject', [WorkflowApprovalController::class, 'bulkReject'])->name('ajax.workflow.approval.bulk.reject');

    // Employee Attendance Routes
    Route::get('/hr/employee/attendance', [StaffController::class, 'attendanceIndex'])->name('attendance.index');
    Route::get('/ajax/hr/employee/attendance/list', [StaffController::class, 'attendanceList'])->name('ajax.attendance.list');
    Route::post('/ajax/hr/employee/attendance/upload', [StaffController::class, 'attendanceUpload'])->name('ajax.attendance.upload');
    Route::get('/ajax/hr/employee/attendance/sample/download', [StaffController::class, 'attendanceSampleDownload'])->name('attendance.sample.download');

    // Staff Hr Communication Routes
    Route::get('/hr/communication', [StaffController::class, 'communicationIndex'])->name('hr.communication.index');
    Route::post('/hr/communication/send', [StaffController::class, 'communicationSend'])->name('hr.communication.send');

    ###### HR SECTION END ########



    ####### PROPERTY MANAGEMENT SECTION START ########

    // Clients
    Route::get('/property/clients', [App\Http\Controllers\ClientsController::class, 'index'])->name('property.clients.index');
    Route::get('/property/clients/data', [ClientsController::class, 'getCLients'])->name('property.clients.data');
    Route::get('/property/clients/create', [ClientsController::class, 'create'])->name('property.clients.create');

    Route::get('/ajax/clients/list', [App\Http\Controllers\ClientsController::class, 'getCLients'])->name('ajax.clients.list');
    Route::get('/property/clients/{encryptedId}/edit', [ClientsController::class, 'edit'])->name('property.clients.edit');
    Route::put('/property/clients/{encryptedId}', [ClientsController::class, 'update'])->name('property.clients.update');

    Route::post('/property/clients', [ClientsController::class, 'store'])->name('property.clients.store');
    Route::get('/property/clients/{encryptedId}', [App\Http\Controllers\ClientsController::class, 'show'])->name('property.clients.show');
    Route::get('/property/clients/{encryptedId}/properties', [App\Http\Controllers\ClientsController::class, 'getProperties'])->name('property.clients.properties');
    Route::get('/property/clients/{encryptedId}/related-properties', [App\Http\Controllers\ClientsController::class, 'getRelatedProperties'])->name('property.clients.related-properties');
    Route::get('/property/clients/{encryptedId}/properties-dropdown', [App\Http\Controllers\ClientsController::class, 'getClientProperties'])->name('property.clients.properties.dropdown');
    Route::get('/property/clients/{encryptedId}/leases', [App\Http\Controllers\ClientsController::class, 'getLeases'])->name('property.clients.leases');
    Route::get('/property/clients/{encryptedId}/recoveries', [App\Http\Controllers\ClientsController::class, 'getRecoveries'])->name('property.clients.recoveries');
    Route::get('/property/clients/{encryptedId}/inspections', [App\Http\Controllers\ClientsController::class, 'getInspections'])->name('property.clients.inspections');
    Route::get('/property/clients/{encryptedId}/repairs', [App\Http\Controllers\ClientsController::class, 'getRepairs'])->name('property.clients.repairs');
    Route::get('/property/clients/{encryptedId}/returns-config', [App\Http\Controllers\ClientsController::class, 'getReturnsConfig'])->name('property.clients.returns-config');
    Route::get('/property/clients/{encryptedId}/returns-config/options', [App\Http\Controllers\ClientsController::class, 'getReturnsConfigOptions'])->name('property.clients.returns-config.options');
    Route::get('/property/clients/{encryptedId}/returns-config/{batchId}', [App\Http\Controllers\ClientsController::class, 'getReturnsConfigBatch'])->name('property.clients.returns-config.show');
    Route::post('/property/clients/{encryptedId}/returns-config', [App\Http\Controllers\ClientsController::class, 'storeReturnsConfig'])->name('property.clients.returns-config.store');
    Route::put('/property/clients/{encryptedId}/returns-config/{batchId}', [App\Http\Controllers\ClientsController::class, 'updateReturnsConfig'])->name('property.clients.returns-config.update');
    Route::delete('/property/clients/{encryptedId}/returns-config/{batchId}', [App\Http\Controllers\ClientsController::class, 'deleteReturnsConfig'])->name('property.clients.returns-config.delete');
    Route::delete('/property/clients/{encryptedId}/returns-config', [App\Http\Controllers\ClientsController::class, 'disbandAllReturnsConfig'])->name('property.clients.returns-config.disband-all');
    Route::get('/property/units', [App\Http\Controllers\ClientsController::class, 'getUnits'])->name('property.units.get');
    Route::get('/property/tenants', [App\Http\Controllers\ClientsController::class, 'tenantIndex'])->name('property.tenants.index');
    Route::get('/property/tenants/data', [App\Http\Controllers\ClientsController::class, 'getTenantsData'])->name('property.tenants.data');
    Route::get('/ajax/property/tenants', [App\Http\Controllers\ClientsController::class, 'getTenants'])->name('ajax.property.tenants.get');
    Route::get('/ajax/banks-and-branches', [ClientsController::class, 'getBanksAndBranches'])->name('ajax.banks.branches');

    // Client Bank Details AJAX CRUD
    Route::get('/ajax/clients/{encryptedClientId}/bank-details', [App\Http\Controllers\ClientBankDetailsController::class, 'getClientBankDetails'])->name('ajax.clients.bank-details');
    Route::get('/ajax/clients/bank-details/{encryptedId}', [App\Http\Controllers\ClientBankDetailsController::class, 'getBankDetail'])->name('ajax.clients.bank-details.show');
    Route::post('/ajax/clients/bank-details', [App\Http\Controllers\ClientBankDetailsController::class, 'store'])->name('ajax.clients.bank-details.store');
    Route::put('/ajax/clients/bank-details/{encryptedId}', [App\Http\Controllers\ClientBankDetailsController::class, 'update'])->name('ajax.clients.bank-details.update');
    Route::delete('/ajax/clients/bank-details/{encryptedId}', [App\Http\Controllers\ClientBankDetailsController::class, 'destroy'])->name('ajax.clients.bank-details.destroy');
    Route::post('/ajax/clients/bank-details/{encryptedId}/set-default', [App\Http\Controllers\ClientBankDetailsController::class, 'setDefault'])->name('ajax.clients.bank-details.set-default');
    Route::get('/ajax/client-bank-details/banks', [App\Http\Controllers\ClientBankDetailsController::class, 'getBanks'])->name('ajax.client-bank-details.banks');
    Route::get('/ajax/client-bank-details/branches/{bankId}', [App\Http\Controllers\ClientBankDetailsController::class, 'getBranches'])->name('ajax.client-bank-details.branches');

    // Tenants
    Route::get('/tenants/listing', [App\Http\Controllers\ClientsController::class, 'tenantsListing'])->name('property.tenants.listing');
    Route::get('/ajax/tenants/listing/data', [App\Http\Controllers\ClientsController::class, 'getTenantsListingData'])->name('ajax.tenants.listing.data');
    Route::get('/tenants/search', [App\Http\Controllers\UtilityController::class, 'searchTenants'])->name('utility.tenants.search');
    Route::get('/tenants/{id}/details', [App\Http\Controllers\UtilityController::class, 'getTenantDetails'])->name('utility.tenants.details');

    // Tenant Statements (MUST come before /tenants/{encryptedId} route)
    Route::get('/tenants/statements', [App\Http\Controllers\InvoicingController::class, 'tenantInvoices'])->name('invoices.tenant.index');
    Route::get('/tenants/statements/create', [App\Http\Controllers\InvoicingController::class, 'createTenantInvoice'])->name('invoices.tenant.create');
    Route::post('/ajax/invoices/tenant/search', [App\Http\Controllers\InvoicingController::class, 'searchTenantInvoices'])->name('ajax.invoices.tenant.search');
    Route::get('/ajax/statements/properties', [App\Http\Controllers\InvoicingController::class, 'getPropertiesForStatements'])->name('ajax.statements.properties');
    Route::get('/ajax/statements/tenants-by-property', [App\Http\Controllers\InvoicingController::class, 'getTenantsByProperty'])->name('ajax.statements.tenants.by.property');
    Route::get('/ajax/statements/search-tenants', [App\Http\Controllers\InvoicingController::class, 'searchTenantsDirectly'])->name('ajax.statements.search.tenants');
    Route::get('/ajax/statements/properties-by-landlord', [App\Http\Controllers\InvoicingController::class, 'getPropertiesByLandlord'])->name('ajax.statements.properties.by.landlord');
    Route::post('/ajax/invoices/tenant/store', [App\Http\Controllers\InvoicingController::class, 'storeTenantInvoice'])->name('ajax.invoices.tenant.store');
    Route::get('/invoices/tenant/export', [App\Http\Controllers\InvoicingController::class, 'exportTenantInvoices'])->name('invoices.tenant.export');
    Route::get('/invoices/tenant/statement/export/excel', [App\Http\Controllers\InvoicingController::class, 'exportTenantStatementExcel'])->name('invoices.tenant.statement.export.excel');
    Route::get('/invoices/tenant/statement/export/pdf', [App\Http\Controllers\InvoicingController::class, 'exportTenantStatementPdf'])->name('invoices.tenant.statement.export.pdf');
    Route::get('/ajax/invoices/tenant/{tenantId}/leases', [App\Http\Controllers\InvoicingController::class, 'getLeasesByTenant'])->name('ajax.invoices.tenant.leases');
    Route::get('/ajax/invoices/invoiceable-items', [App\Http\Controllers\InvoicingController::class, 'getInvoiceableItems'])->name('ajax.invoices.invoiceable.items');

    // Bulk Invoice Generation (MUST come before /tenants/{encryptedId} route)
    Route::get('/generate/invoices', [App\Http\Controllers\InvoicingController::class, 'showGenerateInvoices'])->name('invoices.generate.index');
    Route::post('/generate/invoices', [App\Http\Controllers\InvoicingController::class, 'processGenerateInvoices'])->name('invoices.generate.process');
    Route::get('/ajax/invoices/generated-months', [App\Http\Controllers\InvoicingController::class, 'getGeneratedMonths'])->name('ajax.invoices.generated.months');
    Route::get('/ajax/invoices/generation-history', [App\Http\Controllers\InvoicingController::class, 'getGenerationHistory'])->name('ajax.invoices.generation.history');

    // Tenant Invoice Items (MUST come before /tenants/{encryptedId} route)
    Route::get('/tenants/invoiceitems', [App\Http\Controllers\InvoicingController::class, 'tenantInvoiceItems'])->name('invoices.tenant.items.index');
    Route::get('/tenants/invoiceitems/create', [App\Http\Controllers\InvoicingController::class, 'createInvoiceItem'])->name('invoices.tenant.items.create');
    Route::post('/ajax/invoices/items/search', [App\Http\Controllers\InvoicingController::class, 'searchInvoiceItems'])->name('ajax.invoices.items.search');
    Route::post('/ajax/invoices/items/store', [App\Http\Controllers\InvoicingController::class, 'storeInvoiceItem'])->name('ajax.invoices.items.store');

    // Tenant Deposits (MUST come before /tenants/{encryptedId} route)
    Route::get('/tenant/deposits', [App\Http\Controllers\InvoicingController::class, 'tenantDeposits'])->name('deposits.tenant.index');
    Route::get('/tenant/deposits/{id}', [App\Http\Controllers\InvoicingController::class, 'depositDetails'])->name('deposits.tenant.details');

    // Tenant leases (MUST come before /tenants/{encryptedId} route)
    Route::get('/property/leases', [App\Http\Controllers\LeasesController::class, 'index'])->name('property.leases.index');
    Route::get('/property/leases/data', [App\Http\Controllers\LeasesController::class, 'getAllLeases'])->name('property.leases.data');
    Route::get('/property/leases/stats', [App\Http\Controllers\LeasesController::class, 'getStats'])->name('property.leases.stats');
    Route::get('/property/leases/expiring', [App\Http\Controllers\LeasesController::class, 'getExpiringLeases'])->name('property.leases.expiring');
    Route::get('/property/leases/no-end-date', [App\Http\Controllers\LeasesController::class, 'getNoEndDateLeases'])->name('property.leases.no-end-date');
    Route::get('/property/leases/tenants-without-leases', [App\Http\Controllers\LeasesController::class, 'getTenantsWithoutLeases'])->name('property.leases.tenants-without');
    Route::get('/property/leases/properties-units', [App\Http\Controllers\LeasesController::class, 'getPropertiesWithUnits'])->name('property.leases.properties-units');
    Route::post('/property/leases/bulk-renew', [App\Http\Controllers\LeasesController::class, 'bulkRenew'])->name('property.leases.bulk-renew');
    Route::post('/property/leases/bulk-send-contracts', [App\Http\Controllers\LeasesController::class, 'bulkSendContracts'])->name('property.leases.bulk-send-contracts');
    Route::post('/property/leases/store', [App\Http\Controllers\LeasesController::class, 'store'])->name('property.leases.store');
    Route::get('/property/leases/{encryptedId}/view-signed-contract', [App\Http\Controllers\LeasesController::class, 'viewSignedContract'])->name('property.leases.view-signed-contract');
    Route::post('/property/leases/{id}/send-contract', [App\Http\Controllers\LeasesController::class, 'sendContract'])->name('property.leases.send-contract');
    Route::get('/tenants/lease/create', [App\Http\Controllers\LeasesController::class, 'create'])->name('tenant.lease.create');

    // Lease Contract Template Management
    Route::get('/property/lease-settings/contract-templates', [App\Http\Controllers\LeaseContractTemplateController::class, 'index'])->name('property.lease-settings.contract-templates.index');
    Route::get('/property/lease-settings/contract-templates/create', [App\Http\Controllers\LeaseContractTemplateController::class, 'create'])->name('property.lease-settings.contract-templates.create');
    Route::post('/property/lease-settings/contract-templates', [App\Http\Controllers\LeaseContractTemplateController::class, 'store'])->name('property.lease-settings.contract-templates.store');
    Route::get('/property/lease-settings/contract-templates/{id}/edit', [App\Http\Controllers\LeaseContractTemplateController::class, 'edit'])->name('property.lease-settings.contract-templates.edit');
    Route::put('/property/lease-settings/contract-templates/{id}', [App\Http\Controllers\LeaseContractTemplateController::class, 'update'])->name('property.lease-settings.contract-templates.update');
    Route::post('/property/lease-settings/contract-templates/{id}/set-default', [App\Http\Controllers\LeaseContractTemplateController::class, 'setDefault'])->name('property.lease-settings.contract-templates.set-default');
    Route::delete('/property/lease-settings/contract-templates/{id}', [App\Http\Controllers\LeaseContractTemplateController::class, 'destroy'])->name('property.lease-settings.contract-templates.destroy');

    // Lease Escalations
    Route::get('/property/escalations', [App\Http\Controllers\LeaseEscalationController::class, 'index'])->name('property.escalations.index');
    Route::get('/property/escalations/create', [App\Http\Controllers\LeaseEscalationController::class, 'create'])->name('property.escalations.create');
    Route::get('/property/escalations/data', [App\Http\Controllers\LeaseEscalationController::class, 'getEscalations'])->name('property.escalations.data');
    Route::get('/property/escalations/search-landlords', [App\Http\Controllers\LeaseEscalationController::class, 'searchLandlords'])->name('property.escalations.search-landlords');
    Route::get('/property/escalations/landlord-properties', [App\Http\Controllers\LeaseEscalationController::class, 'getLandlordProperties'])->name('property.escalations.landlord-properties');
    Route::get('/property/escalations/property-units', [App\Http\Controllers\LeaseEscalationController::class, 'getPropertyUnits'])->name('property.escalations.property-units');
    Route::post('/property/escalations/store', [App\Http\Controllers\LeaseEscalationController::class, 'store'])->name('property.escalations.store');
    Route::post('/property/escalations/{id}/approve', [App\Http\Controllers\LeaseEscalationController::class, 'approve'])->name('property.escalations.approve');
    Route::post('/property/escalations/{id}/reject', [App\Http\Controllers\LeaseEscalationController::class, 'reject'])->name('property.escalations.reject');
    Route::post('/property/escalations/{id}/cancel', [App\Http\Controllers\LeaseEscalationController::class, 'cancel'])->name('property.escalations.cancel');
    Route::get('/property/escalations/notification-settings', [App\Http\Controllers\LeaseEscalationController::class, 'getNotificationSettings'])->name('property.escalations.notification-settings.get');
    Route::post('/property/escalations/notification-settings', [App\Http\Controllers\LeaseEscalationController::class, 'saveNotificationSettings'])->name('property.escalations.notification-settings.save');

    // Tenant Movements
    Route::get('/tenant/movements', [App\Http\Controllers\TenantMovementController::class, 'index'])->name('property.movements.index');
    Route::get('/property/movements/data', [App\Http\Controllers\TenantMovementController::class, 'getData'])->name('property.movements.data');
    Route::get('/property/movements/create', [App\Http\Controllers\TenantMovementController::class, 'create'])->name('property.movements.create');
    Route::post('/property/movements/store', [App\Http\Controllers\TenantMovementController::class, 'store'])->name('property.movements.store');
    Route::get('/property/movements/{id}', [App\Http\Controllers\TenantMovementController::class, 'show'])->name('property.movements.show');
    Route::get('/property/movements/{id}/edit', [App\Http\Controllers\TenantMovementController::class, 'edit'])->name('property.movements.edit');
    Route::put('/property/movements/{id}', [App\Http\Controllers\TenantMovementController::class, 'update'])->name('property.movements.update');
    Route::get('/client/{clientId}/slas', [App\Http\Controllers\TenantMovementController::class, 'getSLAsByClient']);
    Route::get('/sla/{slaId}/properties', [App\Http\Controllers\TenantMovementController::class, 'getPropertiesBySLA']);
    Route::get('/property/{propertyId}/occupied-units', [App\Http\Controllers\TenantMovementController::class, 'getOccupiedUnits']);
    Route::get('/property/{propertyId}/vacant-units', [App\Http\Controllers\TenantMovementController::class, 'getVacantUnits']);

    // Tenant Blacklist
    Route::get('/property/blacklist', [App\Http\Controllers\TenantBlacklistController::class, 'index'])->name('property.blacklist.index');
    Route::get('/property/blacklist/data', [App\Http\Controllers\TenantBlacklistController::class, 'getData'])->name('property.blacklist.data');
    Route::get('/property/blacklist/create', [App\Http\Controllers\TenantBlacklistController::class, 'create'])->name('property.blacklist.create');
    Route::post('/property/blacklist/store', [App\Http\Controllers\TenantBlacklistController::class, 'store'])->name('property.blacklist.store');
    Route::get('/property/blacklist/{id}', [App\Http\Controllers\TenantBlacklistController::class, 'show'])->name('property.blacklist.show');
    Route::get('/property/blacklist/{id}/edit', [App\Http\Controllers\TenantBlacklistController::class, 'edit'])->name('property.blacklist.edit');
    Route::put('/property/blacklist/{id}', [App\Http\Controllers\TenantBlacklistController::class, 'update'])->name('property.blacklist.update');
    Route::get('/property/blacklist/unit/{unitId}/leases', [App\Http\Controllers\TenantBlacklistController::class, 'getActiveLeases']);
    Route::get('/property/blacklist/reasons', [App\Http\Controllers\TenantBlacklistController::class, 'getReasons']);
    Route::post('/property/blacklist/reasons/store', [App\Http\Controllers\TenantBlacklistController::class, 'storeReason']);

    // Repairs & Maintenance Requests
    Route::get('/property/repairs', [App\Http\Controllers\RepairAndMaintenanceRequestController::class, 'index'])->name('property.repairs.index');
    Route::get('/property/repairs/data', [App\Http\Controllers\RepairAndMaintenanceRequestController::class, 'getData'])->name('property.repairs.data');
    Route::get('/property/repairs/create', [App\Http\Controllers\RepairAndMaintenanceRequestController::class, 'create'])->name('property.repairs.create');
    Route::post('/property/repairs/store', [App\Http\Controllers\RepairAndMaintenanceRequestController::class, 'store'])->name('property.repairs.store');
    Route::get('/property/repairs/{id}', [App\Http\Controllers\RepairAndMaintenanceRequestController::class, 'show'])->name('property.repairs.show');
    Route::get('/property/repairs/{id}/edit', [App\Http\Controllers\RepairAndMaintenanceRequestController::class, 'edit'])->name('property.repairs.edit');
    Route::put('/property/repairs/{id}', [App\Http\Controllers\RepairAndMaintenanceRequestController::class, 'update'])->name('property.repairs.update');
    Route::get('/property/repairs/repair-types/{elementId}', [App\Http\Controllers\RepairAndMaintenanceRequestController::class, 'getRepairTypesByElement']);

    // Tenant details and management (dynamic route - must come after specific routes)
    Route::get('/tenants/{encryptedId}', [App\Http\Controllers\ClientsController::class, 'showTenant'])->name('property.tenants.show');
    Route::get('/property/tenants/{encryptedId}/edit', [TenantsController::class, 'edit'])->name('units.tenants.edit');
    Route::put('/property/tenants/{encryptedId}', [TenantsController::class, 'update'])->name('units.tenants.update');
    Route::delete('/property/tenants/documents/{id}', [TenantsController::class, 'deleteDocument'])->name('units.tenants.documents.delete');

    // Tenant payments (AJAX)
    Route::get('/tenants/{encryptedId}/payments', [App\Http\Controllers\TenantsController::class, 'payments'])->name('tenants.payments');

    // Tenant leases (AJAX)
    Route::get('/tenants/{encryptedId}/leases', [App\Http\Controllers\TenantsController::class, 'leases'])->name('tenants.leases');
    Route::post('/units/tenants', [App\Http\Controllers\TenantsController::class, 'store'])->name('units.tenants.store');
    Route::get('/units/tenants/create', [App\Http\Controllers\TenantsController::class, 'create'])->name('units.tenants.create');

    // Tenant Payments Management
    Route::get('/tenant/payments', [App\Http\Controllers\TenantPaymentsController::class, 'index'])->name('tenant.payments.index');
    Route::get('/tenant/payments/{encryptedId}/details', [App\Http\Controllers\TenantPaymentsController::class, 'showPaymentDetails'])->name('tenant.payments.details');
    Route::post('/ajax/tenant/payments/search', [App\Http\Controllers\TenantPaymentsController::class, 'searchPayments'])->name('ajax.tenant.payments.search');
    Route::get('/ajax/tenant/payments/{id}/details', [App\Http\Controllers\TenantPaymentsController::class, 'getPaymentDetails'])->name('ajax.tenant.payments.details');
    Route::get('/ajax/tenant/{tenantId}/invoices', [App\Http\Controllers\TenantPaymentsController::class, 'getTenantInvoices'])->name('ajax.tenant.invoices');
    Route::delete('/ajax/tenant/payments/allocations/{allocationId}', [App\Http\Controllers\TenantPaymentsController::class, 'deleteAllocation'])->name('ajax.tenant.payments.allocation.delete');

    // Tenant Payment Allocation Page (specific routes BEFORE parameterized routes)
    Route::get('/payment/allocations', [App\Http\Controllers\PaymentsAllocationController::class, 'showPaymentAllocations'])->name('payment.allocations.page');

    // Route::post('/ajax/tenant/payments/search/allocate', [App\Http\Controllers\TenantPaymentsController::class, 'searchPaymentsForAllocation'])->name('ajax.tenant.payments.search.allocate');
    // Route::post('/ajax/tenant/invoices/search', [App\Http\Controllers\TenantPaymentsController::class, 'searchTenantInvoicesForAllocation'])->name('ajax.tenant.invoices.search');
    // Route::post('/ajax/tenant/payments/allocate/store', [App\Http\Controllers\TenantPaymentsController::class, 'storeAllocation'])->name('ajax.tenant.payments.allocate.store');

    // Parameterized route AFTER specific routes
    Route::post('/ajax/tenant/payments/{id}/allocate', [App\Http\Controllers\TenantPaymentsController::class, 'allocatePayment'])->name('ajax.tenant.payments.allocate');

    // Allocated Payments
    Route::post('/ajax/payments/allocated/search', [App\Http\Controllers\TenantPaymentsController::class, 'searchAllocatedPayments'])->name('ajax.payments.allocated.search');
    Route::get('/ajax/payments/allocated/export', [App\Http\Controllers\TenantPaymentsController::class, 'exportAllocatedPayments'])->name('ajax.payments.allocated.export');
    Route::get('/ajax/payments/properties', [App\Http\Controllers\TenantPaymentsController::class, 'getPropertiesForPayments'])->name('ajax.payments.properties');

    // Client Invoices
    Route::get('/client/invoices', [App\Http\Controllers\InvoicingController::class, 'clientInvoices'])->name('invoices.client.index');
    Route::get('/client/invoices/create', [App\Http\Controllers\InvoicingController::class, 'createClientInvoice'])->name('invoices.client.create');
    Route::post('/ajax/invoices/client/search', [App\Http\Controllers\InvoicingController::class, 'searchClientInvoices'])->name('ajax.invoices.client.search');
    Route::post('/ajax/invoices/client/store', [App\Http\Controllers\InvoicingController::class, 'storeClientInvoice'])->name('ajax.invoices.client.store');
    Route::get('/invoices/client/export', [App\Http\Controllers\InvoicingController::class, 'exportClientInvoices'])->name('invoices.client.export');
    Route::get('/ajax/invoices/clients', [App\Http\Controllers\InvoicingController::class, 'getClients'])->name('ajax.invoices.clients');
    Route::get('/ajax/invoices/client/{clientId}/properties', [App\Http\Controllers\InvoicingController::class, 'getPropertiesByClient'])->name('ajax.invoices.client.properties');

    // Client Returns Batches
    Route::get('/returns/list', [App\Http\Controllers\ClientReturnsController::class, 'index'])->name('returns.batches.index');
    Route::get('/returns/batches/{id}/details', [App\Http\Controllers\ClientReturnsController::class, 'showBatchDetails'])->name('returns.batches.details');
    Route::get('/ajax/returns/batches/data', [App\Http\Controllers\ClientReturnsController::class, 'getBatchesData'])->name('ajax.returns.batches.data');
    Route::get('/ajax/returns/batches/{id}', [App\Http\Controllers\ClientReturnsController::class, 'getBatchDetails'])->name('ajax.returns.batches.details.ajax');
    Route::get('/ajax/returns/batches/{id}/returns', [App\Http\Controllers\ClientReturnsController::class, 'getBatchReturnsData'])->name('ajax.returns.batches.returns');

    // Unprocessed Returns
    Route::get('/returns/{id}/details', [App\Http\Controllers\ClientReturnsController::class, 'showUnprocessedReturnDetails'])->name('returns.details');
    Route::get('/ajax/returns/{id}/units', [App\Http\Controllers\ClientReturnsController::class, 'getUnprocessedReturnUnitsData'])->name('ajax.returns.units');
    Route::get('/ajax/returns/{id}/summary', [App\Http\Controllers\ClientReturnsController::class, 'getUnprocessedReturnSummaryData'])->name('ajax.returns.summary');
    Route::get('/returns/{id}/excel', [App\Http\Controllers\ClientReturnsController::class, 'downloadUnprocessedExcel'])->name('returns.excel');
    Route::post('/ajax/returns/lines/{lineId}/recovery', [App\Http\Controllers\ClientReturnsController::class, 'setRecoveryStatus'])->name('ajax.returns.lines.recovery');

    // Returns Approval Routes (Batch-level)
    Route::get('/ajax/returns/batches/{id}/approvals', [App\Http\Controllers\ReturnsApprovalController::class, 'getBatchApprovals'])->name('ajax.returns.batches.approvals');
    Route::post('/ajax/returns/batches/{id}/approvals/comment', [App\Http\Controllers\ReturnsApprovalController::class, 'addApprovalComment'])->name('ajax.returns.batches.approvals.comment');
    Route::post('/ajax/returns/batches/{id}/approvals/approve', [App\Http\Controllers\ReturnsApprovalController::class, 'approveBatch'])->name('ajax.returns.batches.approvals.approve');
    Route::post('/ajax/returns/batches/{id}/approvals/reject', [App\Http\Controllers\ReturnsApprovalController::class, 'rejectBatch'])->name('ajax.returns.batches.approvals.reject');

    // Returns Approval Routes (Return-summary-level)
    Route::get('/ajax/returns/{id}/approvals', [App\Http\Controllers\ReturnsApprovalController::class, 'getReturnApprovals'])->name('ajax.returns.approvals');
    Route::post('/ajax/returns/{id}/approvals/approve', [App\Http\Controllers\ReturnsApprovalController::class, 'approveReturn'])->name('ajax.returns.approvals.approve');
    Route::post('/ajax/returns/{id}/approvals/reject', [App\Http\Controllers\ReturnsApprovalController::class, 'rejectReturn'])->name('ajax.returns.approvals.reject');
    Route::post('/ajax/returns/{id}/approvals/comment', [App\Http\Controllers\ReturnsApprovalController::class, 'addReturnApprovalComment'])->name('ajax.returns.approvals.comment');

    Route::get('/ajax/returns/invoice-history/{leaseId}', [App\Http\Controllers\ClientReturnsController::class, 'getInvoiceHistory'])->name('ajax.returns.invoice.history');
    Route::get('/ajax/returns/properties', [App\Http\Controllers\ClientReturnsController::class, 'getProperties'])->name('ajax.returns.properties');
    Route::get('/ajax/returns/properties/search', [App\Http\Controllers\ClientReturnsController::class, 'getProperties'])->name('ajax.returns.properties.search');

    // Payout Accounts Configuration
    Route::get('/payout/accounts', [App\Http\Controllers\ReturnsConfigController::class, 'index'])->name('payout.accounts.index');
    Route::get('/ajax/payout/accounts/data', [App\Http\Controllers\ReturnsConfigController::class, 'getPayoutAccountsData'])->name('ajax.payout.accounts.data');

    // Client Returns Generation
    Route::get('/returns/generate', [App\Http\Controllers\ClientReturnsController::class, 'showGenerate'])->name('returns.generate.index');
    Route::post('/returns/generate', [App\Http\Controllers\ClientReturnsController::class, 'processGenerate'])->name('returns.generate.process');

    // Processed Returns
    Route::get('/processed/returns', [App\Http\Controllers\ClientReturnsController::class, 'processedReturns'])->name('processed.returns.index');
    Route::get('/ajax/processed/returns/data', [App\Http\Controllers\ClientReturnsController::class, 'getProcessedReturnsData'])->name('ajax.processed.returns.data');
    Route::get('/processed/returns/{id}/details', [App\Http\Controllers\ClientReturnsController::class, 'showReturnDetails'])->name('processed.returns.details');
    Route::get('/ajax/processed/returns/{id}/units', [App\Http\Controllers\ClientReturnsController::class, 'getReturnUnitsData'])->name('ajax.processed.returns.units');
    Route::get('/ajax/processed/returns/{id}/summary', [App\Http\Controllers\ClientReturnsController::class, 'getReturnSummaryData'])->name('ajax.processed.returns.summary');
    Route::get('/processed/returns/{id}/pdf', [App\Http\Controllers\ClientReturnsController::class, 'downloadPdf'])->name('processed.returns.pdf');
    Route::get('/processed/returns/{id}/excel', [App\Http\Controllers\ClientReturnsController::class, 'downloadExcel'])->name('processed.returns.excel');

    // Landlord Account Statement
    Route::get('/landlord/account/statement', [App\Http\Controllers\LandlordController::class, 'accountStatement'])->name('landlord.account.statement');
    Route::get('/ajax/landlord/properties', [App\Http\Controllers\LandlordController::class, 'getProperties'])->name('ajax.landlord.properties');
    Route::get('/ajax/landlord/landlords-by-property', [App\Http\Controllers\LandlordController::class, 'getLandlordsByProperty'])->name('ajax.landlord.landlords.by.property');
    Route::get('/ajax/landlord/search-landlords', [App\Http\Controllers\LandlordController::class, 'searchLandlords'])->name('ajax.landlord.search.landlords');
    Route::post('/ajax/landlord/statement/search', [App\Http\Controllers\LandlordController::class, 'searchStatement'])->name('ajax.landlord.statement.search');
    Route::get('/landlord/account/statement/export/excel', [App\Http\Controllers\LandlordController::class, 'exportStatementExcel'])->name('landlord.statement.export.excel');
    Route::get('/landlord/account/statement/export/pdf', [App\Http\Controllers\LandlordController::class, 'exportStatementPdf'])->name('landlord.statement.export.pdf');



    // Payment Vouchers
    Route::get('/payment/vouchers', [App\Http\Controllers\PaymentVouchersController::class, 'index'])->name('payment.vouchers.index');
    Route::get('/ajax/payment/vouchers/data', [App\Http\Controllers\PaymentVouchersController::class, 'getVouchersData'])->name('ajax.payment.vouchers.data');
    Route::get('/create/payment/voucher', [App\Http\Controllers\PaymentVouchersController::class, 'create'])->name('payment.vouchers.create');
    Route::post('/payment/vouchers/create-full', [App\Http\Controllers\PaymentVouchersController::class, 'createFull'])->name('payment.vouchers.create.full');
    Route::post('/ajax/payment/vouchers/store-header', [App\Http\Controllers\PaymentVouchersController::class, 'storeHeader'])->name('ajax.payment.vouchers.store.header');
    Route::get('/ajax/payment/vouchers/gl-accounts', [App\Http\Controllers\PaymentVouchersController::class, 'searchGlAccounts'])->name('ajax.payment.vouchers.gl.accounts');
    Route::get('/payment/vouchers/{id}/details', [App\Http\Controllers\PaymentVouchersController::class, 'show'])->name('payment.vouchers.details');
    Route::get('/ajax/payment/vouchers/{id}/lines', [App\Http\Controllers\PaymentVouchersController::class, 'getVoucherLines'])->name('ajax.payment.vouchers.lines');
    Route::post('/ajax/payment/vouchers/{id}/lines/add', [App\Http\Controllers\PaymentVouchersController::class, 'addLine'])->name('ajax.payment.vouchers.lines.add');
    Route::delete('/ajax/payment/vouchers/lines/{lineId}/remove', [App\Http\Controllers\PaymentVouchersController::class, 'removeLine'])->name('ajax.payment.vouchers.lines.remove');
    Route::get('/ajax/payment/vouchers/approved-returns', [App\Http\Controllers\PaymentVouchersController::class, 'getApprovedReturns'])->name('ajax.payment.vouchers.approved-returns');

    Route::get('/ajax/invoices/property/{propertyId}/units', [App\Http\Controllers\InvoicingController::class, 'getUnitsByProperty'])->name('ajax.invoices.property.units');


    // Properties
    Route::get('/property/listings', [App\Http\Controllers\PropertiesController::class, 'index'])->name('property.properties.index');
    Route::get('/property/listings/create', [App\Http\Controllers\PropertiesController::class, 'create'])->name('property.properties.create');
    Route::get('/property/properties/data', [PropertiesController::class, 'getProperties'])->name('property.properties.data');

    // Property edit routes
    Route::get('/property/properties/{encryptedId}/edit', [PropertiesController::class, 'edit'])->name('property.properties.edit');
    Route::put('/property/properties/{encryptedId}', [PropertiesController::class, 'update'])->name('property.properties.update');
    // Property Settings
    Route::get('/property/settings/items', [App\Http\Controllers\PropertySettingsController::class, 'index'])->name('property.settings.index');

    // Cycles Route
    Route::get('/property/settings/cycles/data', [App\Http\Controllers\PropertySettingsController::class, 'getCycles'])->name('property.settings.cycles.data');

    // Return Types Routes
    Route::get('/property/settings/return-types/data', [App\Http\Controllers\PropertySettingsController::class, 'getReturnTypes'])->name('property.settings.return-types.data');
    Route::post('/property/settings/return-types', [App\Http\Controllers\PropertySettingsController::class, 'storeReturnType'])->name('property.settings.return-types.store');
    Route::post('/property/settings/return-types/{encryptedId}', [App\Http\Controllers\PropertySettingsController::class, 'updateReturnType'])->name('property.settings.return-types.update');
    Route::delete('/property/settings/return-types/{encryptedId}', [App\Http\Controllers\PropertySettingsController::class, 'deleteReturnType'])->name('property.settings.return-types.delete');

    // Invoiceable Items Routes
    Route::get('/property/settings/invoiceable-items/data', [App\Http\Controllers\PropertySettingsController::class, 'getInvoiceableItems'])->name('property.settings.invoiceable-items.data');
    Route::post('/property/settings/invoiceable-items', [App\Http\Controllers\PropertySettingsController::class, 'storeInvoiceableItem'])->name('property.settings.invoiceable-items.store');
    Route::post('/property/settings/invoiceable-items/{encryptedId}', [App\Http\Controllers\PropertySettingsController::class, 'updateInvoiceableItem'])->name('property.settings.invoiceable-items.update');
    Route::delete('/property/settings/invoiceable-items/{encryptedId}', [App\Http\Controllers\PropertySettingsController::class, 'deleteInvoiceableItem'])->name('property.settings.invoiceable-items.delete');

    // Zones Routes
    Route::get('/property/settings/zones', [App\Http\Controllers\PropertySettingsController::class, 'zonesIndex'])->name('property.settings.zones.index');
    Route::get('/property/settings/zones/data', [App\Http\Controllers\PropertySettingsController::class, 'getZones'])->name('property.settings.zones.data');
    Route::post('/property/settings/zones', [App\Http\Controllers\PropertySettingsController::class, 'storeZone'])->name('property.settings.zones.store');
    Route::post('/property/settings/zones/{encryptedId}', [App\Http\Controllers\PropertySettingsController::class, 'updateZone'])->name('property.settings.zones.update');
    Route::delete('/property/settings/zones/{encryptedId}', [App\Http\Controllers\PropertySettingsController::class, 'deleteZone'])->name('property.settings.zones.delete');

    // Repairs Settings Routes
    Route::get('/property/settings/repairs', [App\Http\Controllers\PropertySettingsController::class, 'repairsIndex'])->name('property.settings.repairs.index');
    Route::get('/property/settings/repairs/property-elements/data', [App\Http\Controllers\PropertySettingsController::class, 'getPropertyElements'])->name('property.settings.repairs.property-elements.data');
    Route::post('/property/settings/repairs/property-elements/store', [App\Http\Controllers\PropertySettingsController::class, 'storePropertyElement'])->name('property.settings.repairs.property-elements.store');
    Route::put('/property/settings/repairs/property-elements/{encryptedId}', [App\Http\Controllers\PropertySettingsController::class, 'updatePropertyElement'])->name('property.settings.repairs.property-elements.update');
    Route::delete('/property/settings/repairs/property-elements/{encryptedId}', [App\Http\Controllers\PropertySettingsController::class, 'deletePropertyElement'])->name('property.settings.repairs.property-elements.delete');
    Route::get('/property/settings/repairs/repair-types/data', [App\Http\Controllers\PropertySettingsController::class, 'getRepairTypes'])->name('property.settings.repairs.repair-types.data');
    Route::post('/property/settings/repairs/repair-types/store', [App\Http\Controllers\PropertySettingsController::class, 'storeRepairType'])->name('property.settings.repairs.repair-types.store');
    Route::put('/property/settings/repairs/repair-types/{encryptedId}', [App\Http\Controllers\PropertySettingsController::class, 'updateRepairType'])->name('property.settings.repairs.repair-types.update');
    Route::delete('/property/settings/repairs/repair-types/{encryptedId}', [App\Http\Controllers\PropertySettingsController::class, 'deleteRepairType'])->name('property.settings.repairs.repair-types.delete');
    Route::get('/property/settings/repairs/property-elements/dropdown', [App\Http\Controllers\PropertySettingsController::class, 'getPropertyElementsDropdown'])->name('property.settings.repairs.property-elements.dropdown');

    // Client Advances Routes
    Route::get('/client/advances', [App\Http\Controllers\ClientAdvanceController::class, 'index'])->name('client.advances.index');
    // Route::get('/client/advances', [App\Http\Controllers\ClientAdvancesController::class, 'index'])->name('client.advances.index');
    Route::get('/client/advances/create', [App\Http\Controllers\ClientAdvanceController::class, 'create'])->name('client.advances.create');
    Route::get('/property/advances/{encryptedId}', [App\Http\Controllers\ClientAdvanceController::class, 'show'])->name('property.advances.show');
    Route::get('/property/advances/{encryptedId}/edit', [App\Http\Controllers\ClientAdvanceController::class, 'edit'])->name('property.advances.edit');
    Route::get('/property/data', [App\Http\Controllers\ClientAdvanceController::class, 'getAdvances'])->name('property.advances.data');
    Route::get('/property/advances/clients', [App\Http\Controllers\ClientAdvanceController::class, 'getClients'])->name('property.advances.clients');
    Route::get('/property/advances/client-properties/{clientId}', [App\Http\Controllers\ClientAdvanceController::class, 'getClientProperties'])->name('property.advances.client-properties');
    Route::get('/property/client-previous', [App\Http\Controllers\ClientAdvanceController::class, 'getClientPreviousAdvances'])->name('property.advances.client-previous');
    Route::get('/property/misc_charges', [App\Http\Controllers\ClientAdvanceController::class, 'getMiscellaneousCharges'])->name('property.advances.misc_charges');
    Route::post('/property/advances/check-eligibility', [App\Http\Controllers\ClientAdvanceController::class, 'checkClientEligibility'])->name('property.advances.check-eligibility');
    Route::post('/property/advances/calculate-amortization', [App\Http\Controllers\ClientAdvanceController::class, 'calculateAmortization'])->name('property.advances.calculate-amortization');
    Route::post('/property/advances', [App\Http\Controllers\ClientAdvanceController::class, 'store'])->name('property.advances.store');
    Route::put('/property/advances/{encryptedId}', [App\Http\Controllers\ClientAdvanceController::class, 'update'])->name('property.advances.update');
    Route::delete('/property/advances/{encryptedId}', [App\Http\Controllers\ClientAdvanceController::class, 'destroy'])->name('property.advances.destroy');
    Route::get('/property/advances/{encryptedId}/schedule/pdf', [App\Http\Controllers\ClientAdvanceController::class, 'downloadPdf'])->name('property.advances.schedule.pdf');
    Route::get('/property/advances/{encryptedId}/schedule/excel', [App\Http\Controllers\ClientAdvanceController::class, 'downloadExcel'])->name('property.advances.schedule.excel');
    Route::post('/property/advances/{encryptedId}/schedule/email', [App\Http\Controllers\ClientAdvanceController::class, 'emailSchedule'])->name('property.advances.schedule.email');
    Route::post('/property/advances/{encryptedId}/post', [App\Http\Controllers\ClientAdvanceController::class, 'postAdvance'])->name('property.advances.post');
    Route::get('/property/advances/{encryptedId}/reschedule', [App\Http\Controllers\ClientAdvanceController::class, 'showRescheduleForm'])->name('property.advances.reschedule');
    Route::post('/property/advances/{encryptedId}/reschedule', [App\Http\Controllers\ClientAdvanceController::class, 'processReschedule'])->name('property.advances.reschedule.store');


    // Route::get('/client/advances/create', [App\Http\Controllers\ClientAdvancesController::class, 'create'])->name('client.advances.create');
    // Route::post('/client/advances/store', [App\Http\Controllers\ClientAdvancesController::class, 'store'])->name('client.advances.store');
    // Route::get('/client/advances/{encryptedId}/details', [App\Http\Controllers\ClientAdvancesController::class, 'show'])->name('client.advances.show');
    // Route::post('/client/advances/search', [App\Http\Controllers\ClientAdvancesController::class, 'searchAdvances'])->name('client.advances.search');
    // Route::post('/client/advances/calculate', [App\Http\Controllers\ClientAdvancesController::class, 'calculateAmortization'])->name('client.advances.calculate');
    // Route::post('/client/advances/{encryptedId}/approve', [App\Http\Controllers\ClientAdvancesController::class, 'approve'])->name('client.advances.approve');
    // Route::get('/client/advances/{encryptedId}/ajax', [App\Http\Controllers\ClientAdvancesController::class, 'getAdvanceDetails'])->name('client.advances.ajax');
    // Route::post('/ajax/deposits/search', [App\Http\Controllers\InvoicingController::class, 'searchDeposits'])->name('ajax.deposits.search');
    // Route::get('/ajax/deposits/details/{id}', [App\Http\Controllers\InvoicingController::class, 'getDepositDetails'])->name('ajax.deposits.details');


    Route::post('/property/listings', [App\Http\Controllers\PropertiesController::class, 'store'])->name('property.properties.store');
    Route::get('/property/listings/{encryptedId}', [App\Http\Controllers\PropertiesController::class, 'show'])->name('property.properties.show');
    Route::get('/property/listings/{encryptedId}/leases', [App\Http\Controllers\PropertiesController::class, 'getLeases'])->name('property.properties.leases');
    Route::get('/property/listings/{encryptedId}/tenants', [App\Http\Controllers\PropertiesController::class, 'getTenants'])->name('property.properties.tenants');
    Route::get('/property/listings/{encryptedId}/repairs', [App\Http\Controllers\PropertiesController::class, 'getRepairs'])->name('property.properties.repairs');
    Route::get('/ajax/zones-with-details', [App\Http\Controllers\PropertiesController::class, 'getZonesWithDetails'])->name('ajax.zones.details');
    Route::get('/ajax/clients-search', [App\Http\Controllers\PropertiesController::class, 'searchClients'])->name('ajax.clients.search');
    Route::get('/client/{clientId}/properties', [App\Http\Controllers\PropertiesController::class, 'getPropertiesByClient'])->name('client.properties');


    // Unit routes
    Route::get('/property/units/create', [UnitsController::class, 'create'])->name('property.units.create');
    Route::post('/property/units', [UnitsController::class, 'store'])->name('property.units.store');
    Route::get('/property/selection/{propertyId}/units', [UnitsController::class, 'getUnits']);
    Route::get('/property/units/{id}/edit', [UnitsController::class, 'edit'])->name('property.units.edit');
    Route::put('/property/units/{id}', [UnitsController::class, 'update'])->name('property.units.update');
    Route::delete('/ajax/units/documents/{id}', [UnitsController::class, 'deleteDocument'])->name('ajax.units.documents.delete');

    // Get SLA for a property
    Route::get('/property/{propertyId}/sla', [SLAController::class, 'getSLAForProperty'])->name('property.sla');

    // Get tenant for a unit in a property
    Route::get('/property/{propertyId}/unit/{unitId}/tenant', [TenantsController::class, 'getTenantForUnit'])->name('property.unit.tenant');


    // Bulk Upload Routes
    Route::get('/property/bulk/sample', [BulkUploadController::class, 'downloadSample'])->name('property.bulk.sample');
    Route::post('/property/bulk/upload', [BulkUploadController::class, 'upload'])->name('property.bulk.upload');

    // Service Level Agreements (SLAs)
    Route::get('property/service-agreements', [SLAController::class, 'index'])->name('property.service.agreements.index');
    Route::get('/property/service-agreements/data', [SLAController::class, 'getAllSLA'])->name('service.agreements.data');
    Route::get('/property/service-agreements', [SLAController::class, 'index'])->name('service.agreements.index');
    Route::get('/property/service-agreements/create', [SLAController::class, 'create'])->name('service.agreements.create');
    Route::get('/ajax/clients/search-select2', [SLAController::class, 'searchClientsAjax'])->name('ajax.clients.search.select2');
    Route::post('/property/service-agreements', [SLAController::class, 'store'])->name('service.agreements.store');
    Route::get('/property/service-agreements/{id}', [SLAController::class, 'show'])->name('service.agreements.show');
    Route::get('/property/service-agreements/{id}/edit', [SLAController::class, 'edit'])->name('service.agreements.edit');
    Route::put('/property/service-agreements/{id}', [SLAController::class, 'update'])->name('service.agreements.update');
    Route::delete('/property/service-agreements/{id}', [SLAController::class, 'destroy'])->name('service.agreements.destroy');
    Route::get('/invoiceable-items', [App\Http\Controllers\SLAController::class, 'getInvoiceableItems'])->name('invoiceable.items');

    // SLA routes
    Route::get('/property/sla/{encryptedId}/show', [App\Http\Controllers\SLAController::class, 'show'])->name('property.sla.show');
    Route::get('/property/sla/{encryptedId}/edit', [App\Http\Controllers\SLAController::class, 'edit'])->name('property.sla.edit');
    Route::put('/property/sla/{encryptedId}', [App\Http\Controllers\SLAController::class, 'update'])->name('property.sla.update');
    Route::delete('/property/sla/documents/{id}', [App\Http\Controllers\SLAController::class, 'deleteDocument'])->name('property.sla.documents.delete');

    // Client SLA route
    Route::get('/property/clients/{encryptedId}/sla', [App\Http\Controllers\SLAController::class, 'getSLA'])->name('property.clients.sla');

    // Inspection routes
    Route::get('/property/inspections', [InspectionsController::class, 'index'])->name('property.inspections.index');
    Route::get('/ajax/property/inspections/list', [InspectionsController::class, 'list'])->name('ajax.property.inspections.list');
    Route::get('/property/inspection/create', [InspectionsController::class, 'create'])->name('property.inspection.create');
    Route::post('/property/inspection/store', [InspectionsController::class, 'store'])->name('property.inspection.store');
    Route::get('/property/inspections/{encryptedId}', [InspectionsController::class, 'show'])->name('property.inspection.show');
    Route::get('/property/inspections/{encryptedId}/edit', [InspectionsController::class, 'edit'])->name('property.inspection.edit');
    Route::put('/property/inspections/{encryptedId}', [InspectionsController::class, 'update'])->name('property.inspection.update');
    Route::get('/ajax/property/inspections/list', [InspectionsController::class, 'list'])->name('ajax.property.inspection.list');
    Route::delete('/property/inspection/{id}', [InspectionsController::class, 'destroy'])->name('property.inspection.destroy');


    // Recovery
    Route::get('/property/recoveries', [RecoveryController::class, 'index'])->name('property.recoveries.index');
    Route::get('/ajax/property/recoveries/list', [RecoveryController::class, 'list'])->name('ajax.property.recoveries.list');
    Route::get('/property/recovery/create', [RecoveryController::class, 'create'])->name('property.recovery.create');
    Route::get('/property/recovery/{encryptedId}', [RecoveryController::class, 'show'])->name('property.recoveries.show');
    Route::get('/property/recovery/{encryptedId}/edit', [RecoveryController::class, 'edit'])->name('property.recoveries.edit');
    Route::put('/property/recovery/{encryptedId}', [RecoveryController::class, 'update'])->name('property.recoveries.update');

    // Property Confirmations
    Route::get('/property/confirmations', [PropertyConfirmationController::class, 'index'])->name('property.confirmations.index');
    Route::get('/ajax/property/confirmations/list', [PropertyConfirmationController::class, 'approvalsList'])->name('property.confirmations.list');
    Route::post('/property/confirmations/{id}/approve', [PropertyConfirmationController::class, 'approve'])->name('property.confirmations.approve');
    Route::post('/property/confirmations/{id}/reject', [PropertyConfirmationController::class, 'reject'])->name('property.confirmations.reject');
    Route::post('/property/confirmations/bulk-approve', [PropertyConfirmationController::class, 'bulkApprove'])->name('property.confirmations.bulk-approve');
    Route::post('/property/confirmations/bulk-reject', [PropertyConfirmationController::class, 'bulkReject'])->name('property.confirmations.bulk-reject');

    // AJAX routes for inspections
    Route::get('/zone/{zoneId}/properties', [InspectionsController::class, 'getPropertiesByZone'])->name('zone.properties');
    Route::get('/property/{propertyId}/units', [InspectionsController::class, 'getUnitsByProperty'])->name('property.units');
    Route::get('/property/{propertyId}/all_units', [InspectionsController::class, 'getAllUnitsByProperty'])->name('property.units');

    // Property Merge
    Route::get('/property/merge', [App\Http\Controllers\PropertyMergeController::class, 'index'])->name('property.merge.index');
    Route::get('/property/merge/create', [App\Http\Controllers\PropertyMergeController::class, 'create'])->name('property.merge.create');
    Route::get('/property/merge/{encryptedId}', [App\Http\Controllers\PropertyMergeController::class, 'show'])->name('property.merge.show');
    Route::get('/ajax/property/merge/list', [App\Http\Controllers\PropertyMergeController::class, 'list'])->name('ajax.property.merge.list');
    Route::get('/ajax/property/merge/client/{clientId}/properties', [App\Http\Controllers\PropertyMergeController::class, 'getClientProperties'])->name('ajax.property.merge.client.properties');
    Route::get('/ajax/property/merge/property/{propertyId}/units', [App\Http\Controllers\PropertyMergeController::class, 'getPropertyUnits'])->name('ajax.property.merge.property.units');
    Route::get('/ajax/property/merge/client/{clientId}/bank-accounts', [App\Http\Controllers\PropertyMergeController::class, 'getClientBankAccounts'])->name('ajax.property.merge.client.bank.accounts');
    Route::post('/ajax/property/merge/preview', [App\Http\Controllers\PropertyMergeController::class, 'preview'])->name('ajax.property.merge.preview');
    Route::post('/property/merge/store', [App\Http\Controllers\PropertyMergeController::class, 'store'])->name('property.merge.store');
    Route::post('/property/merge/{encryptedId}/reverse', [App\Http\Controllers\PropertyMergeController::class, 'reverse'])->name('property.merge.reverse');

    ###### PROPERTY MANAGEMENT SECTION END ########

    Route::get('/get/tenant/balance', [App\Http\Controllers\TenantsController::class, 'getTenantBalance'])->name('get.tenant.balance');



    ###### Ticketing System ######

        // ──────────────────────────────────────────────────────────────────
        //  USER-FACING
        // ──────────────────────────────────────────────────────────────────
        Route::prefix('tickets')->name('tickets.')->group(function () {
        
            // My tickets list
            Route::get('/',           [TicketController::class, 'myTickets'])->name('my');
        
            // Create new ticket
            Route::get('/create',     [TicketController::class, 'create'])->name('create');
            Route::post('/',          [TicketController::class, 'store'])->name('store');
        
            // View a single ticket (user)
            Route::get('/{encryptedId}',         [TicketController::class, 'show'])->name('show');
        
            // User replies to their own ticket
            Route::post('/{encryptedId}/reply',  [TicketController::class, 'reply'])->name('reply');
        
            // Mark admin replies as read (called via JS)
            Route::post('/{encryptedId}/mark-read', [TicketController::class, 'markRead'])->name('mark-read');
        
            // Download attachment (shared — user & admin, controller handles auth check)
            Route::get('/attachments/{encryptedId}/download', [TicketController::class, 'downloadAttachment'])
                ->name('attachment.download');
        
        });
        
        // ──────────────────────────────────────────────────────────────────
        //  ADMIN  (wrap with your admin middleware, e.g. role:admin)
        // ──────────────────────────────────────────────────────────────────
        Route::prefix('admin/tickets')->name('tickets.admin.')->middleware(['auth'])->group(function () {
        
            // Ticket list + stats
            Route::get('/',                                  [TicketController::class, 'adminIndex'])->name('index');
        
            // CSV export
            Route::get('/export',                            [TicketController::class, 'adminExport'])->name('export');
        
            // View & handle a single ticket
            Route::get('/{encryptedId}',                     [TicketController::class, 'adminShow'])->name('show');
        
            // Admin reply (public or internal note)
            Route::post('/{encryptedId}/reply',              [TicketController::class, 'adminReply'])->name('reply');
        
            // Quick status change (Mark Resolved / Close)
            Route::patch('/{encryptedId}/status',            [TicketController::class, 'adminUpdateStatus'])->name('status');
        
            // Update assignment & priority
            Route::patch('/{encryptedId}/assign',            [TicketController::class, 'adminAssign'])->name('assign');
        
        });
});

##### END CHABRIN - ROUTES FILE #####

// Public lease contract signing routes (no auth required, rate-limited)
Route::middleware(['throttle:10,1'])->group(function () {
    Route::get('/lease-contract/sign/{token}', [App\Http\Controllers\LeasesController::class, 'showSigningPage'])->name('lease.contract.sign');
    Route::post('/lease-contract/sign/{token}', [App\Http\Controllers\LeasesController::class, 'submitSignature'])->name('lease.contract.sign.submit');
});

// Public advance approval route — landlord clicks link from approval email (no auth, rate-limited)
Route::middleware(['throttle:5,1'])->group(function () {
    Route::get('/advances/approve/{encryptedId}', [App\Http\Controllers\ClientAdvanceController::class, 'approveAdvance'])->name('property.advances.approve');
});



Route::redirect('/', '/dashboard');
//Create the login routes
Route::get('/login', [LoginController::class, 'login'])->name('login');
Route::post('/login/submit', [LoginController::class, 'authenticate'])->name('login.authenticate');
Route::get('/authenticator/{two_factor_token}', [LoginController::class, 'show2faForm'])->name('authenticator.form');
Route::post('/authenticator/verify', [LoginController::class, 'verify2fa'])->name('authenticator.verify');


// Password Reset Routes
Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('password.email');
Route::get('/reset-password/{token}', [AuthController::class, 'showResetPasswordForm'])->name('password.reset');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');


Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats'])->name('dashboard.stats');
    Route::get('/dashboard/age-distribution', [DashboardController::class, 'getAgeDistributionData'])->name('dashboard.age-distribution');
    // User Access Log Routes
    Route::get('/admin/access-logs', [UserAccessLogController::class, 'index'])->name('admin.access-logs');
    Route::get('/admin/access-logs/user/{userId}', [UserAccessLogController::class, 'userLogs'])->name('admin.access-logs.user');
    Route::get('/admin/access-logs/statistics', [UserAccessLogController::class, 'statistics'])->name('admin.access-logs.statistics');
    Route::post('/admin/access-logs/test', [UserAccessLogController::class, 'test'])->name('admin.access-logs.test');
    // User Access Logs Interface
    Route::get('/user/access', [UserAccessLogController::class, 'userAccessInterface'])->name('user.access');
    Route::get('/user/access/summary', [UserAccessLogController::class, 'accessSummary'])->name('user.access.summary');
    Route::get('/user/access/details', [UserAccessLogController::class, 'accessDetails'])->name('user.access.details');
    Route::post('/user/access/encrypt-id', [UserAccessLogController::class, 'encryptUserId'])->name('user.access.encrypt.id');
    Route::get('/user/access/details/{encryptedUserId}', [UserAccessLogController::class, 'showUserDetails'])->name('user.access.details.show');
    Route::get('/user/access/all', [UserAccessLogController::class, 'allAccess'])->name('user.access.all');
    Route::get('/user/access/export', [UserAccessLogController::class, 'exportAccess'])->name('user.access.export');
});


// Account Activation Routes
Route::get('/account-activation', [AuthController::class, 'showAccountActivationForm'])->name('account.activation');
Route::post('/send-activation', [AuthController::class, 'sendActivationEmail'])->name('account.send-activation');
// Show activation form (token in URL)
Route::get('/activate-account/{token}', [AuthController::class, 'showAccountActivationForm'])->name('account.activate.view');
// Submit activation (set password + activate)
Route::post('/activate-account/{token}', [AuthController::class, 'activateAccount'])->name('account.activate');



//rolestrict section
Route::middleware(['auth', 'onelogin', 'acess_checker'])->group(function () {
    // Roles management
    Route::get('/list/roles', [RolesController::class, 'listRoles'])->name('listroles');
    Route::get('/role/modules/{encryptedId}', [RolesController::class, 'modulesAccess'])->name('role.modules');
    Route::get('/role/permissions/{encryptedId}', [RolesController::class, 'permissionsAccess'])->name('role.permissions');
    // Add permission view
    Route::get('/create/permission', [RolesController::class, 'addPermissionView'])->name('permissions.addview');
    // System Users
    Route::get('/roles/users', [UsersController::class, 'systemUsers'])->name('system.users');
    Route::get('/users/ajax/list', [UsersController::class, 'systemUsersAjax'])->name('user.ajax.list');
    Route::get('/users/ajax/staff-search', [UsersController::class, 'staffSearch'])->name('user.ajax.staff.search');
    Route::get('/users/ajax/{id}', [UsersController::class, 'getUserAjax'])->name('user.ajax.get');
    Route::post('/users/ajax/store', [UsersController::class, 'storeSystemUser'])->name('user.ajax.store');
    Route::post('/users/ajax/update/{id}', [UsersController::class, 'updateSystemUser'])->name('user.ajax.update');
    Route::post('/users/ajax/suspend/{id}', [UsersController::class, 'suspendUser'])->name('user.ajax.suspend');
    Route::post('/users/ajax/activate/{id}', [UsersController::class, 'activateUser'])->name('user.ajax.activate');
    Route::get('/users/ajax/check-email', [UsersController::class, 'checkEmailExists'])->name('user.ajax.check-email');

    Route::get('/user/profile', [LoginController::class, 'profile'])->name('user.profile');
    Route::get('/email/templates', [EmailTemplateController::class, 'index'])->name('email_templates.index');

    Route::get('/confirm/templates', function () {
        return view('email_templates.confirm');
    })->name('email-templates.confirm.view');



    Route::get('/dashboard', [HomeController::class, 'Dashboard'])->name('dashboard');

    // Dashboard AJAX endpoints
    Route::get('/dashboard/stats', [HomeController::class, 'getDashboardStats'])->name('dashboard.stats');
    Route::get('/dashboard/gender', [HomeController::class, 'getStaffByGender'])->name('dashboard.gender');
    Route::get('/dashboard/positions', [HomeController::class, 'getStaffByPosition'])->name('dashboard.positions');
    Route::get('/dashboard/departments', [HomeController::class, 'getStaffByDepartment'])->name('dashboard.departments');
    Route::get('/dashboard/leave-stats', [HomeController::class, 'getLeaveStats'])->name('dashboard.leave_stats');
    Route::get('/dashboard/discipline-stats', [HomeController::class, 'getDisciplineStats'])->name('dashboard.discipline_stats');
    Route::get('/dashboard/contract-stats', [HomeController::class, 'getContractStats'])->name('dashboard.contract_stats');
    //List users
    Route::get('/list/users', [UsersController::class, 'listUsers'])->name('listusers');

    // Confirm users (admin) view and AJAX
    Route::get('/confirm/users', [UsersController::class, 'confirmUsers'])->name('confirm.users');
    Route::get('/confirm/users/list', [UsersController::class, 'confirmUsersAjax'])->name('confirm.users.list');
    Route::post('/confirm/users', [UsersController::class, 'confirmUser'])->name('confirm.user');

    // Add user view
    Route::get('/add/user', [UsersController::class, 'addUserView'])->name('add.user');

    //employee listing
    Route::get('/list/employees', [UsersController::class, 'employeeList'])->name('listemployees');
    Route::get('/employee/view/{employee_no}', [UsersController::class, 'viewEmployee'])->name('viewemployee');
    Route::get('/employee/edit/{employee_no}', [UsersController::class, 'editEmployee'])->name('editemployee');
    Route::get('/add/employee', [UsersController::class, 'addEmployee'])->name('addemployee');

    // Module routes
    Route::get('/add/module', [ModuleController::class, 'addModule'])->name('modules.add');
    Route::get('/add/submodule', [ModuleController::class, 'addSubmodule'])->name('submodules.add');
    Route::get('/modules/mapper', [ModuleController::class, 'mapper'])->name('modules.mapper');

    // Companies management
    Route::get('/list/companies', [CompaniesController::class, 'listCompanies'])->name('listcompanies');
    Route::get('/add/company', [CompaniesController::class, 'addCompany'])->name('addcompany');
    Route::get('/view/company/{encryptedId}', [CompaniesController::class, 'viewCompany'])->name('viewcompany');

    // Employer Logins (Users) for companies
    Route::get('/ajax/employer-logins/{companyId}', [CompaniesController::class, 'ajaxEmployerLoginsList'])->name('ajax.employer-logins.list');
    Route::post('/ajax/employer-logins', [CompaniesController::class, 'ajaxEmployerLoginStore'])->name('ajax.employer-logins.store');
    Route::post('/ajax/employer-logins/{id}/suspend', [CompaniesController::class, 'ajaxEmployerLoginSuspend'])->name('ajax.employer-logins.suspend');
    Route::delete('/ajax/employer-logins/{id}', [CompaniesController::class, 'ajaxEmployerLoginDelete'])->name('ajax.employer-logins.delete');

    // Contributions for companies
    Route::get('/ajax/contributions/{companyId}', [CompaniesController::class, 'ajaxContributionsList'])->name('ajax.contributions.list');
    Route::get('/contributions/details/{encryptedId}', [CompaniesController::class, 'viewContributionDetails'])->name('contributions.details');
    Route::get('/ajax/contributions/details/{encryptedId}', [CompaniesController::class, 'ajaxContributionDetailsList'])->name('ajax.contributions.details.list');


    // Approval Workflows
    Route::get('/approval/workflows', [ApprovalsController::class, 'index'])->name('approval.workflows.index');
    Route::get('/approval/workflows/manage/{id}', [ApprovalsController::class, 'manage'])->name('approval.workflows.manage');
    Route::get('/approval/workflows/{id}/approvers', [ApprovalsController::class, 'getApprovers'])->name('approval.workflows.approvers');
    Route::get('/approval/workflows/{id}/available-approvers', [ApprovalsController::class, 'getAvailableApprovers'])->name('approval.workflows.available-approvers');
    Route::post('/approval/workflows/{id}/add-approver', [ApprovalsController::class, 'addApprover'])->name('approval.workflows.add-approver');
    Route::put('/approval/workflows/{id}/approvers/{approverId}', [ApprovalsController::class, 'updateApprover'])->name('approval.workflows.update-approver');
    Route::delete('/approval/workflows/{id}/approvers/{approverId}', [ApprovalsController::class, 'removeApprover'])->name('approval.workflows.remove-approver');

    // Approval requests (list + actions)
    Route::get('/approval/requests', [ApprovalRequestsController::class, 'index'])->name('approval.requests.index');
    Route::get('/approval/requests/list', [ApprovalRequestsController::class, 'list'])->name('approval.requests.list');
    Route::post('/approval/action', [ApprovalRequestsController::class, 'action'])->name('approval.requests.action');

    // Approval request details with encrypted ID
    Route::get('/approval/requests/details/{encryptedId}', [ApprovalRequestsController::class, 'details'])->name('approval.requests.details');

    // Approval logs/history
    Route::get('/approval/logs', [ApprovalRequestsController::class, 'logs'])->name('approval.logs.index');
    Route::get('/approval/logs/list', [ApprovalRequestsController::class, 'logsList'])->name('approval.logs.list');
    Route::get('/approval/logs/details/{encryptedId}', [ApprovalRequestsController::class, 'logsDetails'])->name('approval.logs.details');

    //Settings routes for get i.e that load views
    Route::get('/settings/gender', [SettingsController::class, 'genderIndex'])->name('settings.gender');
    Route::get('/settings/marital-status', [SettingsController::class, 'maritalStatusIndex'])->name('settings.marital_status');
    Route::get('/settings/salutation', [SettingsController::class, 'salutationIndex'])->name('settings.salutation');

    // System control categories and controls
    Route::get('/settings/control-categories', [SystemControlController::class, 'categories'])->name('settings.control_categories');
    Route::get('/system/controls', [SystemControlController::class, 'index'])->name('settings.cb_system_configs');


    // Currency Settings
    Route::get('/settings/currencies', [SettingsController::class, 'currencyIndex'])->name('settings.currency');
    Route::get('/settings/country', [SettingsController::class, 'countryIndex'])->name('settings.country');

    // Industry Settings
    Route::get('/settings/industry', [SettingsController::class, 'industryIndex'])->name('settings.industry');
    Route::get('/activity/logs', [LogsController::class, 'index'])->name('activity.logs');


    // Company Bank Accounts Settings
    Route::get('/settings/company-bank-accounts', [CompanyBankAccountController::class, 'index'])->name('settings.company-bank-accounts');

    // Banks & Branches Settings
    Route::get('/settings/banks', [SettingsController::class, 'banksIndex'])->name('settings.banks');
    Route::get('/settings/branches', [SettingsController::class, 'branchesIndex'])->name('settings.branches');

    // Self Service
    Route::get('/self-service/my-payslip', [SelfServiceController::class, 'myPayslip'])->name('self-service.my-payslip');
    Route::get('/self-service/my-leave',   [SelfServiceController::class, 'myLeave'])->name('self-service.my-leave');

    // Cheque Books
    Route::get('/finance/cheque-books', [ChequeBookController::class, 'index'])->name('cheque-books.index');
    Route::get('/finance/cheque-books/{id}', [ChequeBookController::class, 'show'])->name('cheque-books.show');

    // Finance Tax Setup
    Route::get('/finance/taxes', [FinanceTaxController::class, 'index'])->name('finance.taxes.index');
    Route::get('/finance/taxes/create', [FinanceTaxController::class, 'create'])->name('finance.taxes.create');
    Route::get('/finance/taxes/{encrypted_id}/edit', [FinanceTaxController::class, 'edit'])->name('finance.taxes.edit');
    Route::get('/ajax/finance/taxes/data', [FinanceTaxController::class, 'getData'])->name('ajax.finance.taxes.data');
    Route::get('/ajax/finance/taxes/accounts', [FinanceTaxController::class, 'getAccounts'])->name('ajax.finance.taxes.accounts');
    Route::get('/ajax/finance/taxes/options', [FinanceTaxSettingsController::class, 'getOptions'])->name('ajax.finance.taxes.options');
    Route::post('/ajax/finance/taxes', [FinanceTaxController::class, 'store'])->name('ajax.finance.taxes.store');
    Route::post('/ajax/finance/taxes/{encrypted_id}', [FinanceTaxController::class, 'update'])->name('ajax.finance.taxes.update');

    // Finance Tax Settings - Default Types
    Route::get('/finance/tax-settings/default-types', [FinanceTaxSettingsController::class, 'defaultTypesIndex'])->name('finance.tax-settings.default-types');
    Route::get('/ajax/finance/tax-settings/default-types/list', [FinanceTaxSettingsController::class, 'defaultTypesList']);
    Route::get('/ajax/finance/tax-settings/default-types/{id}', [FinanceTaxSettingsController::class, 'defaultTypesShow']);
    Route::post('/ajax/finance/tax-settings/default-types', [FinanceTaxSettingsController::class, 'defaultTypesStore']);
    Route::post('/ajax/finance/tax-settings/default-types/{id}', [FinanceTaxSettingsController::class, 'defaultTypesUpdate']);
    Route::delete('/ajax/finance/tax-settings/default-types/{id}', [FinanceTaxSettingsController::class, 'defaultTypesDelete']);

    // Finance Tax Settings - Computations
    Route::get('/finance/tax-settings/computations', [FinanceTaxSettingsController::class, 'computationsIndex'])->name('finance.tax-settings.computations');
    Route::get('/ajax/finance/tax-settings/computations/list', [FinanceTaxSettingsController::class, 'computationsList']);
    Route::get('/ajax/finance/tax-settings/computations/{id}', [FinanceTaxSettingsController::class, 'computationsShow']);
    Route::post('/ajax/finance/tax-settings/computations', [FinanceTaxSettingsController::class, 'computationsStore']);
    Route::post('/ajax/finance/tax-settings/computations/{id}', [FinanceTaxSettingsController::class, 'computationsUpdate']);
    Route::delete('/ajax/finance/tax-settings/computations/{id}', [FinanceTaxSettingsController::class, 'computationsDelete']);

    // Finance Tax Settings - Categories
    Route::get('/finance/tax-settings/categories', [FinanceTaxSettingsController::class, 'categoriesIndex'])->name('finance.tax-settings.categories');
    Route::get('/ajax/finance/tax-settings/categories/list', [FinanceTaxSettingsController::class, 'categoriesList']);
    Route::get('/ajax/finance/tax-settings/categories/{id}', [FinanceTaxSettingsController::class, 'categoriesShow']);
    Route::post('/ajax/finance/tax-settings/categories', [FinanceTaxSettingsController::class, 'categoriesStore']);
    Route::post('/ajax/finance/tax-settings/categories/{id}', [FinanceTaxSettingsController::class, 'categoriesUpdate']);
    Route::delete('/ajax/finance/tax-settings/categories/{id}', [FinanceTaxSettingsController::class, 'categoriesDelete']);

    // Tax Bands Management
    Route::get('/tax/bands', [TaxController::class, 'index'])->name('tax.index');
    Route::get('/tax/bands/data', [TaxController::class, 'getData'])->name('tax.data');
    Route::post('/tax/bands', [TaxController::class, 'store'])->name('tax.store');
    Route::get('/tax/bands/{id}', [TaxController::class, 'show'])->name('tax.show');
    Route::put('/tax/bands/{id}', [TaxController::class, 'update'])->name('tax.update');
    Route::delete('/tax/bands/{id}', [TaxController::class, 'destroy'])->name('tax.destroy');

    // Transaction Types Management
    Route::get('/finance/transaction-types', [TransactionTypesController::class, 'index'])->name('finance.transaction-types.index');
    Route::get('/finance/transaction-types/create', [TransactionTypesController::class, 'create'])->name('finance.transaction-types.create');
    Route::post('/finance/transaction-types', [TransactionTypesController::class, 'store'])->name('finance.transaction-types.store');
    Route::get('/finance/transaction-types/{id}/edit', [TransactionTypesController::class, 'edit'])->name('finance.transaction-types.edit');
    Route::put('/finance/transaction-types/{id}', [TransactionTypesController::class, 'update'])->name('finance.transaction-types.update');
    Route::delete('/finance/transaction-types/{id}', [TransactionTypesController::class, 'destroy'])->name('finance.transaction-types.destroy');
    Route::get('/ajax/finance/transaction-types/data', [TransactionTypesController::class, 'getData'])->name('ajax.finance.transaction-types.data');
    Route::get('/ajax/finance/transaction-types/{id}', [TransactionTypesController::class, 'getOne'])->name('ajax.finance.transaction-types.get-one');

    // Recurrent Client Remittances - AJAX endpoints
    Route::prefix('ajax/finance/recurrent-client-remittances')->name('ajax.finance.recurrent-client-remittances.')->group(function () {
        Route::get('data',              [RecurrentClientRemittancesController::class, 'getData'])         ->name('data');
        Route::get('properties',        [RecurrentClientRemittancesController::class, 'getProperties'])   ->name('properties');
        Route::get('slas',              [RecurrentClientRemittancesController::class, 'getSlas'])          ->name('slas');
        Route::get('invoiceable-items', [RecurrentClientRemittancesController::class, 'getInvoiceableItems'])->name('invoiceable-items');
        Route::get('providers',         [RecurrentClientRemittancesController::class, 'getProviders'])    ->name('providers');
        Route::get('banks',             [RecurrentClientRemittancesController::class, 'getBanks'])         ->name('banks');
        Route::get('bank-branches',     [RecurrentClientRemittancesController::class, 'getBankBranches']) ->name('bank-branches');
        Route::get('team-members',      [RecurrentClientRemittancesController::class, 'getTeamMembers'])  ->name('team-members');
    });

    // Recurrent Client Remittances - Resource routes
    Route::prefix('finance/recurrent-client-remittances')->name('finance.recurrent-client-remittances.')->group(function () {
        Route::get('/',                  [RecurrentClientRemittancesController::class, 'index'])          ->name('index');
        Route::get('create',             [RecurrentClientRemittancesController::class, 'create'])         ->name('create');
        Route::post('/',                 [RecurrentClientRemittancesController::class, 'store'])           ->name('store');
        Route::get('{id}/edit',          [RecurrentClientRemittancesController::class, 'edit'])           ->name('edit');
        Route::put('{id}',               [RecurrentClientRemittancesController::class, 'update'])         ->name('update');
        Route::delete('{id}',            [RecurrentClientRemittancesController::class, 'destroy'])        ->name('destroy');
    });
});

//norolerestrict sections
Route::middleware(['auth', 'onelogin'])->group(function () {



    //Country AJAX CRUD
    Route::get('/ajax/country/list', [SettingsController::class, 'countryList'])->name('ajax.country.list');
    Route::post('/ajax/country', [SettingsController::class, 'countryStore'])->name('ajax.country.store');
    Route::post('/ajax/country/{id}', [SettingsController::class, 'countryUpdate'])->name('ajax.country.update');
    Route::delete('/ajax/country/{id}', [SettingsController::class, 'countryDelete'])->name('ajax.country.delete');

    //Currency Settings
    Route::get('/ajax/currency/list', [SettingsController::class, 'currencyList']);
    Route::post('/ajax/currency/store', [SettingsController::class, 'currencyStore'])->name('ajax.currency.store');
    Route::post('/ajax/currency/{id}', [SettingsController::class, 'currencyUpdate']);
    Route::delete('/ajax/currency/{id}', [SettingsController::class, 'currencyDelete']);


    //Settings Gender
    Route::get('/ajax/gender/list', [SettingsController::class, 'genderList'])->name('ajax.gender.list');
    Route::post('/ajax/gender', [SettingsController::class, 'genderStore'])->name('ajax.gender.store');
    Route::post('/ajax/gender/{id}', [SettingsController::class, 'genderUpdate'])->name('ajax.gender.update');
    Route::delete('/ajax/salutation/{id}', [SettingsController::class, 'salutationDelete'])->name('ajax.salutation.delete');

    // Marital Status AJAX CRUD
    Route::get('/ajax/marital-status/list', [SettingsController::class, 'maritalStatusList'])->name('ajax.marital_status.list');
    Route::post('/ajax/marital-status', [SettingsController::class, 'maritalStatusStore'])->name('ajax.marital_status.store');
    Route::post('/ajax/marital-status/{id}', [SettingsController::class, 'maritalStatusUpdate'])->name('ajax.marital_status.update');
    Route::delete('/ajax/marital-status/{id}', [SettingsController::class, 'maritalStatusDelete'])->name('ajax.marital_status.delete');
    // Salutation AJAX CRUD
    Route::get('/ajax/salutation/list', [SettingsController::class, 'salutationList'])->name('ajax.salutation.list');
    Route::post('/ajax/salutation', [SettingsController::class, 'salutationStore'])->name('ajax.salutation.store');
    Route::post('/ajax/salutation/{id}', [SettingsController::class, 'salutationUpdate'])->name('ajax.salutation.update');
    Route::delete('/ajax/salutation/{id}', [SettingsController::class, 'salutationDelete'])->name('ajax.salutation.delete');

    Route::post('/add/employee', [UsersController::class, 'storeEmployee'])->name('employees.store');
    Route::post('/employee/update/{employee_no}', [UsersController::class, 'updateEmployee'])->name('updateemployee');
    Route::post('/employee/confirm/{employee_no}', [UsersController::class, 'confirmEmployee'])->name('confirmemployee');


    Route::post('/add/module', [ModuleController::class, 'storeModule'])->name('modules.store');
    Route::post('/add/submodule', [ModuleController::class, 'storeSubmodule'])->name('submodules.store');
    Route::post('/modules/mapper/save', [ModuleController::class, 'saveMapper'])->name('modules.mapper.save');

    Route::post('/roles/permissionsaccess/save/{id}', [RolesController::class, 'savePermissionsAccess'])->name('roles.permissionsaccess.save');
    Route::post('/permissions/add', [RolesController::class, 'addPermission'])->name('permissions.add');
    Route::post('/roles/add', [RolesController::class, 'addRole'])->name('roles.add');

    // Save modules access (AJAX)
    Route::post('/roles/modulesaccess/save/{id}', [RolesController::class, 'saveModulesAccess'])->name('roles.modulesaccess.save');

    Route::post('/add/company', [CompaniesController::class, 'storeCompany'])->name('storecompany');
    Route::get('/edit/company/{id}', [CompaniesController::class, 'editCompany'])->name('editcompany');
    Route::post('/update/company/{id}', [CompaniesController::class, 'updateCompany'])->name('updatecompany');



    // Approval Workflows AJAX CRUD
    Route::get('/ajax/approvals', [ApprovalsController::class, 'list'])->name('ajax.approvals.list');
    Route::post('/ajax/approvals', [ApprovalsController::class, 'store'])->name('ajax.approvals.store');
    Route::post('/ajax/approvals/{id}', [ApprovalsController::class, 'update'])->name('ajax.approvals.update');
    Route::delete('/ajax/approvals/{id}', [ApprovalsController::class, 'destroy'])->name('ajax.approvals.delete');


    //Industry AJAX CRUD
    Route::get('/ajax/industry/list', [SettingsController::class, 'industryList']);
    Route::post('/ajax/industry/store', [SettingsController::class, 'industryStore'])->name('ajax.industry.store');
    Route::post('/ajax/industry/{id}', [SettingsController::class, 'industryUpdate']);
    Route::delete('/ajax/industry/{id}', [SettingsController::class, 'industryDelete']);

    // Company Bank Accounts AJAX CRUD
    Route::get('/settings/company-bank-accounts/data', [CompanyBankAccountController::class, 'getData']);
    Route::get('/ajax/company-bank-accounts/banks', [CompanyBankAccountController::class, 'getBanks']);
    Route::get('/ajax/company-bank-accounts/branches', [CompanyBankAccountController::class, 'getBranches']);
    Route::post('/settings/company-bank-accounts/store', [CompanyBankAccountController::class, 'store']);
    Route::post('/settings/company-bank-accounts/update/{id}', [CompanyBankAccountController::class, 'update']);
    Route::delete('/settings/company-bank-accounts/delete/{id}', [CompanyBankAccountController::class, 'destroy']);

    // Cheque Books AJAX
    Route::get('/get/finance/cheque-books/data', [ChequeBookController::class, 'getData']);
    Route::get('/ajax/cheque-books/bank-accounts', [ChequeBookController::class, 'getBankAccounts']);
    Route::post('/finance/cheque-books/store', [ChequeBookController::class, 'store']);
    Route::get('/ajax/cheque-book-leaves/{bookId}', [ChequeBookController::class, 'getLeavesData']);
    Route::post('/ajax/cheque-books/leaves/{leafId}/release-lock', [ChequeBookController::class, 'releaseLock']);
    Route::post('/ajax/cheque-books/leaves/{leafId}/cancel', [ChequeBookController::class, 'cancelLeaf']);
    Route::post('/ajax/voucher-cheque/book', [ChequeBookController::class, 'getChequeBooks'])->name('ajax.voucher-cheque.book');
    Route::post('/ajax/voucher-cheque/lock', [ChequeBookController::class, 'lockNextAvailable'])->name('ajax.voucher-cheque.lock');
    Route::post('/ajax/voucher-cheque/release', [ChequeBookController::class, 'releaseVoucherCheque'])->name('ajax.voucher-cheque.release');

    // Banks AJAX CRUD
    Route::get('/settings/banks/list', [SettingsController::class, 'banksList']);
    Route::post('/settings/banks/store', [SettingsController::class, 'banksStore']);
    Route::post('/settings/banks/update/{id}', [SettingsController::class, 'banksUpdate']);
    Route::delete('/settings/banks/delete/{id}', [SettingsController::class, 'banksDelete']);

    // AJAX endpoint: get branches for a bank
    Route::get('/ajax/banks/{bankId}/branches', [SettingsController::class, 'bankBranches']);

    // AJAX endpoints for recovery form cascading dropdowns
    Route::get('/unit/{unitId}/tenant', [TenantsController::class, 'getTenantByUnit']);
    Route::get('/unit/{unitId}/leases', [LeasesController::class, 'getLeasesByUnit']);

    // AJAX companies list for user assignment
    Route::get('/ajax/companies/list', [CompaniesController::class, 'ajaxCompaniesList'])->name('ajax.companies.list');


    // Branches AJAX CRUD
    Route::get('/settings/branches/list', [SettingsController::class, 'branchesList']);
    Route::post('/settings/branches/store', [SettingsController::class, 'branchesStore']);
    Route::post('/settings/branches/update/{id}', [SettingsController::class, 'branchesUpdate']);
    Route::delete('/settings/branches/delete/{id}', [SettingsController::class, 'branchesDelete']);

    // Control Categories CRUD
    Route::get('/control-categories/list', [SystemControlController::class, 'ajaxListCategories']);
    Route::post('/control-categories', [SystemControlController::class, 'ajaxStoreCategory'])->name('control_categories.store');
    Route::delete('/control-categories/{id}', [SystemControlController::class, 'ajaxDeleteCategory']);

    // System Controls
    Route::get('/system-controls/list', [SystemControlController::class, 'ajaxListControls']);
    Route::post('/system-controls', [SystemControlController::class, 'ajaxStoreControl'])->name('cb_system_configs.store');
    Route::delete('/system-controls/{id}', [SystemControlController::class, 'ajaxDeleteControl']);

    //Email Templates CRUD with permissions 
    Route::get('/email/templates/list', [EmailTemplateController::class, 'list'])->name('email_templates.list');
    Route::get('/email/templates/types', [EmailTemplateController::class, 'getTemplateTypes'])->name('email_templates.types');
    Route::get('/email/templates/{id}', [EmailTemplateController::class, 'show'])->name('email_templates.show');
    Route::post('/email/templates', [EmailTemplateController::class, 'store'])->name('email_templates.store');
    Route::put('/email/templates/{id}', [EmailTemplateController::class, 'update'])->name('email_templates.update');
    Route::post('/email/templates/{id}/confirm', [EmailTemplateController::class, 'confirm'])->name('email_templates.confirm');
    Route::post('/email/templates/bulk-confirm', [EmailTemplateController::class, 'bulkConfirm'])->name('email_templates.bulk_confirm');
    Route::delete('/email/templates/{id}', [EmailTemplateController::class, 'destroy'])->name('email_templates.destroy');


    // ── General Ledger ──────────────────────────────────────────────────────────
    // Pages
    Route::get('/ledger/accountclasses',   [App\Http\Controllers\LedgerController::class, 'classesIndex'])->name('ledger.classes.index');
    Route::get('/ledger/accountgroups',    [App\Http\Controllers\LedgerController::class, 'groupsIndex'])->name('ledger.groups.index');
    Route::get('/ledger/account/categories', [App\Http\Controllers\LedgerController::class, 'categoriesIndex'])->name('ledger.categories.index');
    Route::get('/ledger/accounts',         [App\Http\Controllers\LedgerController::class, 'accountsIndex'])->name('ledger.accounts.index');
    // AJAX data
    Route::get('/ajax/ledger/accountclasses',      [App\Http\Controllers\LedgerController::class, 'getClasses'])->name('ledger.classes.data');
    Route::get('/ajax/ledger/accountgroups',       [App\Http\Controllers\LedgerController::class, 'getGroups'])->name('ledger.groups.data');
    Route::get('/ajax/ledger/account/categories',  [App\Http\Controllers\LedgerController::class, 'getCategories'])->name('ledger.categories.data');
    Route::get('/ajax/ledger/accounts',            [App\Http\Controllers\LedgerController::class, 'getAccounts'])->name('ledger.accounts.data');
    // Dropdown helpers
    Route::get('/ajax/ledger/classes/dropdown',     [App\Http\Controllers\LedgerController::class, 'getClassesDropdown'])->name('ledger.classes.dropdown');
    Route::get('/ajax/ledger/groups/dropdown',      [App\Http\Controllers\LedgerController::class, 'getGroupsDropdown'])->name('ledger.groups.dropdown');
    Route::get('/ajax/ledger/categories/dropdown',  [App\Http\Controllers\LedgerController::class, 'getCategoriesDropdown'])->name('ledger.categories.dropdown');
    // CRUD
    Route::post('/ledger/accountclasses',                        [App\Http\Controllers\LedgerController::class, 'storeClass'])->name('ledger.classes.store');
    Route::post('/ledger/accountclasses/{encryptedId}',          [App\Http\Controllers\LedgerController::class, 'updateClass'])->name('ledger.classes.update');
    Route::delete('/ledger/accountclasses/{encryptedId}',        [App\Http\Controllers\LedgerController::class, 'deleteClass'])->name('ledger.classes.delete');
    Route::post('/ledger/accountgroups',                         [App\Http\Controllers\LedgerController::class, 'storeGroup'])->name('ledger.groups.store');
    Route::post('/ledger/accountgroups/{encryptedId}',           [App\Http\Controllers\LedgerController::class, 'updateGroup'])->name('ledger.groups.update');
    Route::delete('/ledger/accountgroups/{encryptedId}',         [App\Http\Controllers\LedgerController::class, 'deleteGroup'])->name('ledger.groups.delete');
    Route::post('/ledger/account/categories',                    [App\Http\Controllers\LedgerController::class, 'storeCategory'])->name('ledger.categories.store');
    Route::post('/ledger/account/categories/{encryptedId}',      [App\Http\Controllers\LedgerController::class, 'updateCategory'])->name('ledger.categories.update');
    Route::delete('/ledger/account/categories/{encryptedId}',    [App\Http\Controllers\LedgerController::class, 'deleteCategory'])->name('ledger.categories.delete');
    Route::post('/ledger/accounts',                              [App\Http\Controllers\LedgerController::class, 'storeAccount'])->name('ledger.accounts.store');
    Route::post('/ledger/accounts/{encryptedId}',                [App\Http\Controllers\LedgerController::class, 'updateAccount'])->name('ledger.accounts.update');
    Route::delete('/ledger/accounts/{encryptedId}',              [App\Http\Controllers\LedgerController::class, 'deleteAccount'])->name('ledger.accounts.delete');

    // GL Periods
    Route::get('/accounting/fiscalyears',                        [App\Http\Controllers\LedgerController::class, 'periodsIndex'])->name('gl.periods.index');
    Route::get('/ajax/accounting/gl-periods',                    [App\Http\Controllers\LedgerController::class, 'getPeriods'])->name('gl.periods.data');
    Route::post('/accounting/gl-periods',                        [App\Http\Controllers\LedgerController::class, 'storePeriod'])->name('gl.periods.store');
    Route::post('/accounting/gl-periods/{encryptedId}',          [App\Http\Controllers\LedgerController::class, 'updatePeriod'])->name('gl.periods.update');
    Route::delete('/accounting/gl-periods/{encryptedId}',        [App\Http\Controllers\LedgerController::class, 'deletePeriod'])->name('gl.periods.delete');

    // GL Transactions
    Route::get('/accounting/gltransactions',      [App\Http\Controllers\LedgerController::class, 'glTransactionsIndex'])->name('gl.transactions.index');
    Route::get('/ajax/accounting/gl-transactions',[App\Http\Controllers\LedgerController::class, 'getGlTransactions'])->name('gl.transactions.data');
    Route::get('/accounting/gl-transactions/export',[App\Http\Controllers\LedgerController::class, 'downloadGlTransactionsExcel'])->name('gl.transactions.export');

    // GL Settings
    Route::get('/generalledger/settings',              [App\Http\Controllers\LedgerController::class, 'glSettingsIndex'])->name('gl.settings.index');
    Route::post('/generalledger/settings',             [App\Http\Controllers\LedgerController::class, 'updateGlSettings'])->name('gl.settings.update');
    Route::get('/ajax/gl/accounts/search',             [App\Http\Controllers\LedgerController::class, 'searchGlAccountsAjax'])->name('ajax.gl.accounts.search');

    // Approval Workflows
    Route::get('/accounting/approvals', [App\Http\Controllers\WorkflowsController::class, 'index'])->name('accounting.approvals.index');
    Route::get('/accounting/approvals/{encryptedId}', [App\Http\Controllers\WorkflowsController::class, 'show'])->name('accounting.approvals.show');
    Route::get('/ajax/accounting/approvals/data', [App\Http\Controllers\WorkflowsController::class, 'getWorkflowsData'])->name('ajax.accounting.approvals.data');
    Route::post('/ajax/accounting/approvals', [App\Http\Controllers\WorkflowsController::class, 'store'])->name('ajax.accounting.approvals.store');
    Route::put('/ajax/accounting/approvals/{encryptedId}', [App\Http\Controllers\WorkflowsController::class, 'update'])->name('ajax.accounting.approvals.update');
    Route::delete('/ajax/accounting/approvals/{encryptedId}', [App\Http\Controllers\WorkflowsController::class, 'destroy'])->name('ajax.accounting.approvals.destroy');

    // Workflow Approvers
    Route::get('/ajax/accounting/approvals/{encryptedId}/approvers', [App\Http\Controllers\WorkflowsController::class, 'getApprovers'])->name('ajax.accounting.approvals.approvers');
    Route::post('/ajax/accounting/approvals/{encryptedId}/approvers', [App\Http\Controllers\WorkflowsController::class, 'addApprover'])->name('ajax.accounting.approvals.approvers.add');
    Route::put('/ajax/accounting/approvals/approvers/{encryptedId}', [App\Http\Controllers\WorkflowsController::class, 'updateApprover'])->name('ajax.accounting.approvals.approvers.update');
    Route::delete('/ajax/accounting/approvals/approvers/{encryptedId}', [App\Http\Controllers\WorkflowsController::class, 'removeApprover'])->name('ajax.accounting.approvals.approvers.remove');
    Route::get('/ajax/accounting/approvals/{encryptedId}/logs', [App\Http\Controllers\WorkflowsController::class, 'getChangeLogs'])->name('ajax.accounting.approvals.logs');
    Route::get('/ajax/accounting/approvals/users/search', [App\Http\Controllers\WorkflowsController::class, 'searchUsers'])->name('ajax.accounting.approvals.users.search');

    // Approval Requests
    Route::get('/accounting/pending/approvals', [App\Http\Controllers\ApprovalRequestsController::class, 'index'])->name('accounting.pending.approvals');
    Route::get('/accounting/pending/approvals/{encryptedId}', [App\Http\Controllers\ApprovalRequestsController::class, 'show'])->name('accounting.pending.approvals.show');
    Route::get('/ajax/approval-requests/pending', [App\Http\Controllers\ApprovalRequestsController::class, 'getPendingApprovals'])->name('ajax.approval-requests.pending');
    Route::get('/ajax/approval-requests/historical', [App\Http\Controllers\ApprovalRequestsController::class, 'getHistoricalApprovals'])->name('ajax.approval-requests.historical');
    Route::get('/ajax/approval-requests/{encryptedId}', [App\Http\Controllers\ApprovalRequestsController::class, 'getRequestDetails'])->name('ajax.approval-requests.details');
    Route::get('/ajax/approval-requests/{encryptedId}/timeline', [App\Http\Controllers\ApprovalRequestsController::class, 'getTimeline'])->name('ajax.approval-requests.timeline');
    Route::post('/ajax/approval-requests/{encryptedId}/approve', [App\Http\Controllers\ApprovalRequestsController::class, 'approve'])->name('ajax.approval-requests.approve');
    Route::post('/ajax/approval-requests/{encryptedId}/reject', [App\Http\Controllers\ApprovalRequestsController::class, 'reject'])->name('ajax.approval-requests.reject');
    Route::post('/ajax/approval-requests/{encryptedId}/request-changes', [App\Http\Controllers\ApprovalRequestsController::class, 'requestChanges'])->name('ajax.approval-requests.request-changes');
    Route::post('/ajax/approval-requests/{encryptedId}/return', [App\Http\Controllers\ApprovalRequestsController::class, 'returnToPrevious'])->name('ajax.approval-requests.return');
    Route::post('/ajax/approval-requests/{encryptedId}/resubmit', [App\Http\Controllers\ApprovalRequestsController::class, 'resubmit'])->name('ajax.approval-requests.resubmit');
    Route::get('/ajax/approval-requests/for-item', [App\Http\Controllers\ApprovalRequestsController::class, 'getForItem'])->name('ajax.approval-requests.for-item');


    // Activity logs
    // Eligible employees for creating users
    Route::get('/ajax/eligible-employees', [UsersController::class, 'eligibleEmployeesList']);
    // keep compatibility for AJAX caller
    Route::post('/ajax/users', [UsersController::class, 'storeUser']);
    // AJAX users list/get/update for DataTable and modal-driven edit
    Route::get('/ajax/users/list', [UsersController::class, 'listUsersAjax'])->name('ajax.users.list');
    Route::get('/ajax/users/{id}', [UsersController::class, 'getUser']);
    Route::post('/ajax/users/{id}', [UsersController::class, 'updateUser']);

    Route::get('/activity/logs/list', [LogsController::class, 'list'])->name('activity.logs.list');

    // ═══════════════════════════════════════════════════════════
    // PAYMENT ALLOCATION & RETURN THRESHOLD ROUTES
    // ═══════════════════════════════════════════════════════════

    // ★★★ BACKEND SERVICE: Process ALL payments (no filters) ★★★
    // Use this for cron jobs / scheduled tasks
    Route::post('/payments/process/all', [PaymentsAllocationController::class, 'processAllPayments'])->name('payments.process.all');

    // Process Payments - Automatic Allocation (with optional filters)
    Route::post('/payments/process', [PaymentsAllocationController::class, 'processPayments'])->name('payments.process');
    Route::post('/payments/process/lease', [PaymentsAllocationController::class, 'processLeasePayments'])->name('payments.process.lease');

    // Manual Allocation
    Route::post('/payments/allocate/manual', [PaymentsAllocationController::class, 'manualAllocate'])->name('payments.allocate.manual');

    // Allocation History
    Route::get('/payments/{paymentId}/allocations', [PaymentsAllocationController::class, 'getPaymentAllocations'])->name('payments.allocations');
    Route::get('/invoices/{invoiceId}/allocations', [PaymentsAllocationController::class, 'getInvoiceAllocations'])->name('invoices.allocations');

    // Return Threshold Management
    Route::post('/returns/threshold/update', [PaymentsAllocationController::class, 'updateReturnThreshold'])->name('returns.threshold.update');
    Route::post('/returns/monthly/update', [PaymentsAllocationController::class, 'updateMonthlyReturns'])->name('returns.monthly.update');
    Route::post('/returns/threshold/recalculate', [PaymentsAllocationController::class, 'recalculateThresholds'])->name('returns.threshold.recalculate');

    // Returns Analytics & Reporting
    Route::get('/returns/summary', [PaymentsAllocationController::class, 'getReturnsSummary'])->name('returns.summary');
    Route::get('/returns/threshold/breakdown', [PaymentsAllocationController::class, 'getThresholdBreakdown'])->name('returns.threshold.breakdown');
    Route::get('/returns/low-threshold', [PaymentsAllocationController::class, 'getLowThresholdReturns'])->name('returns.low_threshold');
    Route::get('/returns/{returnId}/payability', [PaymentsAllocationController::class, 'checkReturnPayability'])->name('returns.check_payability');
});
