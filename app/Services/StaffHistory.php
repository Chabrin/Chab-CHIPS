<?php

namespace App\Services;

use App\Models\AuditLogs;
use App\Models\Bank;
use App\Models\Contract;
use App\Models\ContractChange;
use App\Models\Department;
use App\Models\EmployeeDocument;
use App\Models\EmployeeDocumentRemoval;
use App\Models\Position;
use App\Models\Staff;
use App\Models\User;
use App\Models\Zone;
use App\Support\MaskedNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * The History of a staff member or of a contract: who changed what, when, what it was and what it is now, newest first.
 *
 * It only reads. The changes are recorded as they happen (the audit log, the contract change log and the document removal
 * history) and nothing in the system edits or deletes them, so this is the same list however many times it is opened.
 * ID numbers, KRA PINs and bank accounts show only their last four digits, as they do in lists.
 */
class StaffHistory
{
    /** Most rows shown on one page; the page says when there are more. */
    public const LIMIT = 300;

    private const STAFF_LABELS = [
        'full_names' => 'Full names', 'employment_type' => 'Employment category', 'gender' => 'Gender', 'address' => 'Address',
        'mobile_number' => 'Mobile number', 'email_address' => 'Official email', 'personal_email' => 'Personal email',
        'birth_date' => 'Date of birth', 'national_id' => 'National ID', 'passport' => 'Passport number', 'bank_name' => 'Bank',
        'pin_number' => 'KRA PIN', 'hire_date' => 'Hire date', 'department_id' => 'Department', 'position_id' => 'Position',
        'username' => 'Username', 'current_status_id' => 'Status', 'branch_id' => 'Branch', 'account_name' => 'Account name',
        'account_number' => 'Bank account number', 'zone_id' => 'Zone', 'zone_area_id' => 'Zone area', 'kins_names' => 'Next of kin',
        'kins_relationship' => 'Next of kin relationship', 'staff_number' => 'Staff number', 'district' => 'District',
        'location' => 'Location', 'sub_location' => 'Sub-location', 'marital' => 'Marital status', 'birth_place' => 'Place of birth',
        'nhif_number' => 'SHA number', 'nssf_number' => 'NSSF number', 'spouse_name' => 'Spouse', 'citizenship' => 'Citizenship',
        'disability' => 'Disability', 'photo' => 'Photo', 'signature' => 'Signature',
    ];

    private const CONTRACT_LABELS = [
        'date_start' => 'Start date', 'date_end' => 'End date', 'current_status_id' => 'Contract status', 'position_id' => 'Position',
        'basic_salary' => 'Basic salary', 'staff_number' => 'Staff number', 'period_value' => 'Contract period', 'period_unit' => 'Period unit',
        'probation_months' => 'Probation (months)', 'probation_end' => 'Probation ends',
    ];

    /** Fields that are plumbing, not something a person changed. */
    private const SKIP = ['id', 'date_time', 'created_at', 'updated_at', 'uid', 'system_password', 'permission_group_id', 'created_by', 'staff_id'];

    /** Shown as **** and the last four characters. */
    private const MASKED = ['national_id', 'passport', 'pin_number', 'account_number', 'nhif_number', 'nssf_number'];

    private array $lookups = [];

    public function __construct(private PayrollPreRunCheck $check)
    {
    }

    /**
     * Everything recorded about a staff member: the record itself, their contracts and salary, and their documents.
     *
     * @return array{rows: array<int, array>, total: int, shown: int}
     */
    public function forStaff(int $staffId): array
    {
        $contractIds = Contract::where('staff_id', $staffId)->pluck('id')->all();
        $rows = $this->staffRecordRows($staffId)
            ->merge($this->contractRows($staffId, $contractIds, null))
            ->merge($this->documentRows($staffId));

        return $this->finish($rows);
    }

    /**
     * Everything recorded about one contract: its dates, status, position, salary and the earnings and deductions on it.
     *
     * @return array{rows: array<int, array>, total: int, shown: int}
     */
    public function forContract(int $contractId): array
    {
        $contract = Contract::find($contractId);

        return $this->finish($this->contractRows($contract?->staff_id, [$contractId], $contractId));
    }

    // ---- the staff record -------------------------------------------------------------------------------------------------

    private function staffRecordRows(int $staffId): Collection
    {
        return $this->audits('sys_staff', [$staffId])->flatMap(function (AuditLogs $audit) {
            return match ($audit->operation) {
                'create' => [$this->row($audit->created_at, $audit->user_id, 'Staff record', 'Staff record created')],
                'delete' => [$this->row($audit->created_at, $audit->user_id, 'Staff record', 'Staff record deleted')],
                default => $this->fieldRows($audit, 'Staff record', self::STAFF_LABELS, 'staff'),
            };
        });
    }

    // ---- contracts --------------------------------------------------------------------------------------------------------

    private function contractRows(?int $staffId, array $contractIds, ?int $onlyContract): Collection
    {
        $rows = collect();
        $changes = ContractChange::with('element')
            ->when($onlyContract, fn ($q) => $q->where('contract_id', $onlyContract), fn ($q) => $q->where('staff_id', $staffId))
            ->get();

        // A salary change is logged twice (the contract change log, and the audit log of the contract); the first is shown
        $loggedSalaries = $changes->where('field', ContractChange::BASIC_SALARY)->map(fn ($c) => $c->contract_id . '|' . round((float) $c->new_value, 2))->all();

        foreach ($changes as $change) {
            $rows->push($this->changeRow($change));
        }

        $audits = $this->audits('sys_hr_employee_contracts', $contractIds);
        // Contracts that were deleted are no longer in the list, but their delete is still on record
        if ($onlyContract === null && $staffId) {
            $audits = $audits->merge(
                AuditLogs::where('table_name', 'sys_hr_employee_contracts')->where('operation', 'delete')->get()
                    ->filter(fn (AuditLogs $audit) => (int) ($audit->old_data['staff_id'] ?? 0) === $staffId)
            )->unique('id');
        }
        foreach ($audits as $audit) {
            if ($audit->operation === 'create') {
                // The contract change log says it already, with the starting salary
                if (!$changes->contains(fn ($c) => $c->field === ContractChange::CONTRACT_CREATED && (int) $c->contract_id === (int) $audit->record_id)) {
                    $rows->push($this->row($audit->created_at, $audit->user_id, 'Contract', 'Contract created'));
                }
            } elseif ($audit->operation === 'delete') {
                $rows->push($this->row($audit->created_at, $audit->user_id, 'Contract', 'Contract deleted'));
            } else {
                foreach ($this->fieldRows($audit, 'Contract', self::CONTRACT_LABELS, 'contract') as $row) {
                    $skip = $row['what'] === 'Basic salary' && in_array($audit->record_id . '|' . round((float) $this->rawNew($audit, 'basic_salary'), 2), $loggedSalaries, true);
                    if (!$skip) {
                        $rows->push($row);
                    }
                }
            }
        }

        return $rows;
    }

    private function changeRow(ContractChange $change): array
    {
        $by = $change->changed_by;
        $at = $change->created_at;
        if ($change->field === ContractChange::CONTRACT_CREATED) {
            return $this->row($at, $by, 'Contract', 'Contract created', null, $change->new_value ? 'Basic salary ' . $this->money($change->new_value) : null);
        }
        if ($change->field === ContractChange::BASIC_SALARY) {
            return $this->row($at, $by, 'Contract', 'Basic salary', $this->money($change->old_value), $this->money($change->new_value));
        }

        return $this->row($at, $by, 'Contract', $this->check->describeElementChange($change));
    }

    // ---- documents --------------------------------------------------------------------------------------------------------

    private function documentRows(int $staffId): Collection
    {
        $rows = collect();
        $documentIds = EmployeeDocument::where('employee_id', $staffId)->pluck('id')->all();
        $removals = Schema::hasTable('employee_document_removals') ? EmployeeDocumentRemoval::where('employee_id', $staffId)->get() : collect();

        // A document being added is on the audit log by its id (those that are still here or were removed since)
        $ids = array_merge($documentIds, $removals->pluck('original_id')->filter()->all());
        foreach ($this->audits('employee_documents', $ids) as $audit) {
            if ($audit->operation === 'create') {
                $title = $audit->new_data['title'] ?? $audit->new_data['file_name'] ?? 'Document';
                $rows->push($this->row($audit->created_at, $audit->user_id, 'Document', 'Document added: ' . $title));
            }
        }
        foreach ($removals as $removal) {
            $title = $removal->title ?: ($removal->file_name ?: 'Document');
            $what = $removal->reason === 'Removed' ? 'Document removed: ' . $title : 'Document replaced: ' . $title;
            $rows->push($this->row($removal->removed_at, $removal->removed_by, 'Document', $what, $removal->file_name, null));
        }

        return $rows;
    }

    // ---- shared -----------------------------------------------------------------------------------------------------------

    /**
     * The audit log of one kind of record, for these record ids.
     */
    private function audits(string $table, array $recordIds): Collection
    {
        if (!$recordIds || !Schema::hasTable('cb_audit_logs')) {
            return collect();
        }

        return AuditLogs::where('table_name', $table)->whereIn('record_id', $recordIds)->orderBy('id')->get();
    }

    /**
     * One row for every field an update changed.
     *
     * @return array<int, array>
     */
    private function fieldRows(AuditLogs $audit, string $area, array $labels, string $kind): array
    {
        $new = (array) ($audit->new_data ?? []);
        $old = (array) ($audit->old_data ?? []);
        $rows = [];
        foreach ($new as $field => $value) {
            if (in_array($field, self::SKIP, true)) {
                continue;
            }
            $label = $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
            if (in_array($field, ['photo', 'signature'], true)) {
                $rows[] = $this->row($audit->created_at, $audit->user_id, $area, $label . ' changed');
                continue;
            }
            $rows[] = $this->row($audit->created_at, $audit->user_id, $area, $label, $this->show($field, $old[$field] ?? null, $kind), $this->show($field, $value, $kind));
        }

        return $rows;
    }

    private function rawNew(AuditLogs $audit, string $field)
    {
        return ((array) ($audit->new_data ?? []))[$field] ?? null;
    }

    /**
     * A recorded value as a person reads it: names instead of ids, dates, money, masked numbers.
     */
    private function show(string $field, $value, string $kind): string
    {
        if ($value === null || $value === '') {
            return 'empty';
        }
        if (in_array($field, self::MASKED, true)) {
            return MaskedNumber::tail((string) $value);
        }
        switch ($field) {
            case 'position_id':
                return $this->lookup('position', fn () => Position::pluck('position_name', 'id'))[$value] ?? 'Position ' . $value;
            case 'department_id':
                return $this->lookup('department', fn () => Department::pluck('department_name', 'id'))[$value] ?? 'Department ' . $value;
            case 'bank_name':
                return $this->lookup('bank', fn () => Bank::pluck('bank_name', 'id'))[$value] ?? (string) $value;
            case 'zone_id':
                return $this->lookup('zone', fn () => Zone::pluck('zone_name', 'id'))[$value] ?? 'Zone ' . $value;
            case 'current_status_id':
                return $kind === 'staff' ? (Staff::STATUSES[(int) $value] ?? (string) $value) : ((int) $value === 5 ? 'Active' : 'Inactive');
            case 'basic_salary':
                return $this->money($value);
            case 'birth_date':
            case 'hire_date':
            case 'date_start':
            case 'date_end':
            case 'probation_end':
                return $this->date($value);
        }

        return (string) $value;
    }

    private function lookup(string $key, callable $load): Collection
    {
        return $this->lookups[$key] ??= $load();
    }

    private function money($value): string
    {
        return $value === null || $value === '' ? 'empty' : 'KES ' . number_format((float) $value, 2);
    }

    private function date($value): string
    {
        try {
            return Carbon::parse($value)->format('d M Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function row($at, $by, string $area, string $what, ?string $old = null, ?string $new = null): array
    {
        return ['at' => $at ? Carbon::parse($at) : null, 'by_id' => $by ? (int) $by : null, 'by' => null, 'area' => $area, 'what' => $what, 'old' => $old, 'new' => $new];
    }

    /**
     * Newest first, with the names of who made each change, and no more than LIMIT rows.
     */
    private function finish(Collection $rows): array
    {
        $names = User::whereIn('id', $rows->pluck('by_id')->filter()->unique()->all())->pluck('name', 'id');
        $sorted = $rows->map(function (array $row) use ($names) {
            $row['by'] = $row['by_id'] ? ($names[$row['by_id']] ?? 'User ' . $row['by_id']) : 'System';
            unset($row['by_id']);

            return $row;
        })->sortByDesc(fn (array $row) => $row['at']?->timestamp ?? 0)->values();

        return ['rows' => $sorted->take(self::LIMIT)->all(), 'total' => $sorted->count(), 'shown' => min(self::LIMIT, $sorted->count())];
    }
}
