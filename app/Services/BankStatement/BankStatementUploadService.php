<?php

namespace App\Services\BankStatement;

use App\Exceptions\BankStatementException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Puts a bank statement into the repository: checks it is the chosen bank's template, refuses a file that was uploaded
 * before, keeps the file on the private disk and stages every line in that bank's line table. Nothing is matched or paid
 * until the upload is confirmed.
 */
class BankStatementUploadService
{
    const MAX_ROWS = 10000;

    /**
     * Stage one uploaded statement. Everything is saved together or not at all.
     *
     * @param string $bank equity or coop
     * @param UploadedFile $file the statement file
     * @param int $userId who uploads it
     * @return int the new upload's id
     */
    public function upload(string $bank, UploadedFile $file, int $userId): int {
        $definition = BankStatementBanks::get($bank);
        $realPath = $file->getRealPath();
        $hash = hash_file('sha256', $realPath);

        $earlier = DB::table('cb_bank_statement_uploads')->where('file_hash', $hash)->first(['id', 'created_at']);
        if ($earlier) {
            throw new BankStatementException('This exact file was already uploaded as statement #' . $earlier->id . ' on ' . substr((string) $earlier->created_at, 0, 10) . '.');
        }

        $lines = (new $definition['parser'])->parse($realPath);

        if (!$lines) {
            throw new BankStatementException('The file has a header but no rows under it.');
        }
        if (count($lines) > self::MAX_ROWS) {
            throw new BankStatementException('The file has more than ' . number_format(self::MAX_ROWS) . ' rows. Split it and upload the parts.');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $stored = Storage::disk('local')->putFileAs('bank-statements/' . $bank, $file, $hash . '.' . (in_array($extension, ['xlsx', 'xls', 'csv'], true) ? $extension : 'xlsx'));
        $now = now();
        $payable = array_filter($lines, fn ($line) => $line['match_status'] === 'Pending');

        try {
            return DB::transaction(function () use ($bank, $definition, $file, $stored, $hash, $lines, $payable, $userId, $now) {
                $uploadId = DB::table('cb_bank_statement_uploads')->insertGetId([
                    'bank' => $bank,
                    'file_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'file_path' => $stored,
                    'file_hash' => $hash,
                    'status' => 'Uploaded',
                    'line_count' => count($lines),
                    'total_amount' => round(array_sum(array_column($payable, $definition['amount'])), 2),
                    'uploaded_by' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach (array_chunk($lines, 500) as $chunk) {
                    DB::table($definition['table'])->insert(array_map(fn ($line) => $line + ['upload_id' => $uploadId, 'created_at' => $now, 'updated_at' => $now], $chunk));
                }

                return $uploadId;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($stored);

            throw $e;
        }
    }

    /**
     * Delete an upload that has not been confirmed, with its lines and its file. A confirmed upload is part of the
     * record and stays.
     *
     * @param int $uploadId
     */
    public function delete(int $uploadId): void {
        DB::transaction(function () use ($uploadId) {
            $upload = DB::table('cb_bank_statement_uploads')->where('id', $uploadId)->lockForUpdate()->first();

            if (!$upload) {
                throw new BankStatementException('That statement does not exist.');
            }
            if ($upload->status !== 'Uploaded') {
                throw new BankStatementException('Only a statement that has not been confirmed can be deleted.');
            }

            DB::table(BankStatementBanks::get($upload->bank)['table'])->where('upload_id', $uploadId)->delete();
            DB::table('cb_bank_statement_uploads')->where('id', $uploadId)->delete();
            Storage::disk('local')->delete($upload->file_path);
        });
    }
}
