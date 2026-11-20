<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantReview extends Model
{
    protected $guarded = [];

    protected $casts = ['revision' => 'integer', 'accounts_pending' => 'boolean'];

    public function lease()
    {
        return $this->belongsTo(LeaseAgreement::class, 'lease_id')->withoutGlobalScopes();
    }

    public function tenant()
    {
        return $this->belongsTo(Client::class, 'tenant_id')->withoutGlobalScopes();
    }

    public function events()
    {
        return $this->hasMany(TenantReviewEvent::class, 'review_id')->orderBy('id');
    }

    public function workflowVersion()
    {
        return $this->belongsTo(TenantReviewWorkflowVersion::class, 'workflow_version_id');
    }

    public function steps(): array
    {
        if (!$this->workflow_version_id) return app(\App\Services\TenantReviewWorkflowService::class)->defaultSteps();
        if (!$this->workflowVersion) throw new \RuntimeException('The saved tenant approval version is missing.');
        return $this->workflowVersion->steps;
    }

    public function currentStep(): ?array
    {
        return collect(array_slice($this->steps(), 1))->firstWhere('stage', $this->stage);
    }

    public function nextStep(): ?array
    {
        $steps = $this->steps();
        $index = array_search($this->stage, array_column($steps, 'stage'), true);
        return $index === false ? null : ($steps[$index + 1] ?? null);
    }

    public function firstApprovalStage(): string
    {
        return $this->steps()[1]['stage'];
    }

    public function stageLabel(): string
    {
        $step = $this->currentStep();
        if ($step) return 'Awaiting '.$step['label'];
        return [
            'correction' => 'Returned to Field Officer',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
        ][$this->stage] ?? $this->stage;
    }
}
