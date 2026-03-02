<?php

namespace App\Jobs;

use App\Models\ErrorLogs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ErrorDispatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $errorMessage;
    public $stackTrace;
    public $route;
    public $user;

    /**
     * Create a new job instance.
     * @param string $errorMessage
     * @param string|null $stackTrace
     * @param string|null $route
     * @param mixed|null $user
     */
    public function __construct(string $errorMessage, ?string $stackTrace = null, ?string $route = null, $user = null)
    {
        $this->errorMessage = $errorMessage;
        $this->stackTrace = $stackTrace;
        $this->route = $route;
        $this->user = $user;
    }

    public function handle()
    {
        try {
            $log = new ErrorLogs();
            // short summary for quick index
            $log->error = mb_substr($this->errorMessage ?? '', 0, 200);
            // full message and stack trace stored separately
            $log->error_message = $this->errorMessage ?? null;
            $log->stack_trace = $this->stackTrace ?? null;
            $log->dispatch_time = now();
            $log->route = $this->route ?? null;
            $log->resolved = false;

            try {
                if (is_array($this->user) || is_object($this->user)) {
                    $log->user_affected = json_encode($this->user);
                } else {
                    $log->user_affected = $this->user;
                }
            } catch (\Exception $ux) {
                $log->user_affected = null;
            }
            $log->save();
        } catch (\Exception $ex) {
            Log::error('ErrorDispatchJob failed to record error: ' . $ex->getMessage());
        }
    }
}
