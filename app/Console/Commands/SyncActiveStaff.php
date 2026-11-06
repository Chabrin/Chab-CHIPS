<?php

namespace App\Console\Commands;

use App\Services\ActiveStaffSync;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SyncActiveStaff extends Command
{
    protected $signature = 'app:sync-active-staff
                            {file : The active staff list from the legacy system (.xlsx or .csv)}
                            {--apply : Write the changes. Without this nothing is changed and you only see what would happen}
                            {--force : Do not ask for confirmation before writing}';

    protected $description = 'Update staff details and active/terminated status from the legacy active staff list';

    public function handle(ActiveStaffSync $sync): int
    {
        $path = $this->argument('file');
        if (!is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $rows = $this->readRows($path);
        if (!$rows) {
            $this->error('No staff rows were found. The first row must hold the column names, starting with EMPNUMB.');

            return self::FAILURE;
        }

        $report = $sync->run($rows, false);
        $this->summarise($report);
        $file = $this->writeReport($report, 'preview');
        $this->line("Details of every change (kept on this server only): {$file}");

        if (!$this->option('apply')) {
            $this->warn('Nothing was changed. Run again with --apply to write these changes.');

            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm('Write these changes to the staff records?')) {
            $this->warn('Cancelled. Nothing was changed.');

            return self::SUCCESS;
        }

        $report = $sync->run($rows, true);
        $this->info('Done. ' . $report['staff_changed'] . ' staff records updated, ' . count($report['created']) . ' added.');
        $this->line('Record of what was written: ' . $this->writeReport($report, 'applied'));

        return self::SUCCESS;
    }

    /** @return array<int, array<string, mixed>> */
    private function readRows(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        // Read cells as written (no number formatting), so "002" and branch code "03033" keep their zeros
        $grid = $sheet->rangeToArray('A1:' . $sheet->getHighestColumn() . $sheet->getHighestRow(), null, false, false, false);

        $header = array_map(fn ($name) => strtoupper(trim((string) $name)), array_shift($grid) ?? []);
        if (!in_array('EMPNUMB', $header, true)) {
            return [];
        }

        $rows = [];
        foreach ($grid as $cells) {
            $row = array_combine($header, array_pad(array_slice($cells, 0, count($header)), count($header), null));
            if (trim((string) ($row['EMPNUMB'] ?? '')) !== '') {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** Counts only: names and numbers stay in the report file */
    private function summarise(array $report): void
    {
        $this->newLine();
        $this->info($report['applied'] ? 'Result' : 'Preview (nothing written yet)');
        $this->line('People in the file ................ ' . $report['file_rows']);
        $this->line('Found in the system ............... ' . $report['matched']);
        $this->line('Not in the system, to be added .... ' . count($report['created']));
        $this->line('Staff whose details change ........ ' . $report['staff_changed']);
        $this->line('Back to Active (were Terminated) .. ' . count($report['reactivated']));
        $this->line('Active, not on the list -> Terminated ' . count($report['made_inactive']));
        if ($report['no_number']) {
            $this->line('Not on the list, left alone (no staff number) .. ' . count($report['no_number']));
        }
        foreach ($report['left_alone'] as $status => $count) {
            $this->line("Not on the list, left as status {$status} .. {$count}");
        }

        $this->newLine();
        $this->line('Fields changed (number of staff):');
        foreach ($report['field_counts'] as $field => $count) {
            $this->line('  ' . str_pad($field, 18) . $count);
        }

        if ($report['ambiguous']) {
            $this->warn('Skipped, the staff number matches more than one staff record: ' . implode(', ', $report['ambiguous']));
        }
        if ($report['duplicate_in_file']) {
            $this->warn('Skipped, listed twice in the file: ' . implode(', ', $report['duplicate_in_file']));
        }
        if ($report['unknown_titles']) {
            $this->warn('Job titles with no matching position (position kept): ' . implode(', ', array_map(fn ($title, $count) => "{$title} ({$count})", array_keys($report['unknown_titles']), $report['unknown_titles'])));
        }
        if ($report['skipped']) {
            $this->warn(count($report['skipped']) . ' values in the file could not be used and the system value was kept (listed in the report file).');
        }
    }

    private function writeReport(array $report, string $kind): string
    {
        $directory = storage_path('app/staff-sync');
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $file = $directory . DIRECTORY_SEPARATOR . date('Ymd-His') . "-{$kind}.csv";
        $handle = fopen($file, 'w');
        fputcsv($handle, ['what', 'staff_number', 'field', 'was', 'now_or_reason']);
        foreach ($report['changes'] as [$number, $field, $old, $new]) {
            fputcsv($handle, ['changed', $number, $field, $old, $new]);
        }
        foreach ($report['skipped'] as [$number, $field, $reason]) {
            fputcsv($handle, ['kept', $number, $field, '', $reason]);
        }
        foreach (['created' => 'added', 'reactivated' => 'back to active', 'made_inactive' => 'terminated (not on the list)'] as $key => $label) {
            foreach ($report[$key] as $number) {
                fputcsv($handle, [$label, $number, 'status', '', '']);
            }
        }
        fclose($handle);

        return $file;
    }
}
