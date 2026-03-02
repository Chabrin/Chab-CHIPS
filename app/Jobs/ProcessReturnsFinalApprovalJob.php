<?php

namespace App\Jobs;

use App\Models\ClientReturnsBatch;
use App\Traits\HandlesReturnApprovalWorkflow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessReturnsFinalApprovalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, HandlesReturnApprovalWorkflow;

    protected $batchId;
    protected $approvedBy;
    protected $comments;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300;

    /**
     * Create a new job instance.
     *
     * @param int $batchId
     * @param int $approvedBy
     * @param string|null $comments
     */
    public function __construct($batchId, $approvedBy, $comments = null)
    {
        $this->batchId = $batchId;
        $this->approvedBy = $approvedBy;
        $this->comments = $comments;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            // Retrieve the batch
            $batch = ClientReturnsBatch::find($this->batchId);

            if (!$batch) {
                Log::error('Batch not found for final approval job', [
                    'batch_id' => $this->batchId
                ]);
                return;
            }

            Log::info('Starting final approval workflow (Background Job)', [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'approved_by' => $this->approvedBy
            ]);

            // Process the final approval using the trait method
            $this->processFinalApproval($batch, $this->approvedBy, $this->comments);

            Log::info('Final approval workflow completed successfully (Background Job)', [
                'batch_id' => $batch->id,
                'approved_by' => $this->approvedBy
            ]);
        } catch (\Exception $e) {
            Log::error('Error in final approval background job', [
                'batch_id' => $this->batchId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::error('Final approval job failed', [
            'batch_id' => $this->batchId,
            'approved_by' => $this->approvedBy,
            'error' => $exception->getMessage()
        ]);
    }
}
