<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CommunicationTemplate;
use App\Jobs\DispatchCommunication;
use Carbon\Carbon;

class DispatchScheduledCommunications extends Command
{
    protected $signature = 'communications:dispatch';
    protected $description = 'Dispatch scheduled communication templates';

    public function handle()
    {
        $now = Carbon::now();
        
        // Find approved general communication templates that are scheduled for now or earlier
        $templates = CommunicationTemplate::where('template_type', 'general')
            ->where('current_status_id', 5) // Approved
            ->whereNotNull('general_config')
            ->get()
            ->filter(function($template) use ($now) {
                $config = $template->general_config;
                
                if (!isset($config['scheduled_date']) || !isset($config['scheduled_time'])) {
                    return false;
                }
                
                $scheduledDateTime = Carbon::parse($config['scheduled_date'] . ' ' . $config['scheduled_time']);
                
                // Check if scheduled time has arrived and template hasn't been sent yet
                return $now->gte($scheduledDateTime) && $template->current_status_id == 5;
            });

        if ($templates->isEmpty()) {
            $this->info('No communications ready to dispatch.');
            return;
        }

        $this->info("Found {$templates->count()} communication(s) to dispatch.");

        foreach ($templates as $template) {
            try {
                DispatchCommunication::dispatch($template->id);
                $this->info("Dispatched: {$template->template_name} (ID: {$template->id})");
            } catch (\Exception $e) {
                $this->error("Failed to dispatch template {$template->id}: " . $e->getMessage());
            }
        }

        $this->info('Communication dispatch completed.');
    }
}