<?php

namespace App\Services;

use App\Models\LeaseAgreement;
use App\Models\LeaseContractTemplate;
use App\Notifications\LeaseContractNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LeaseContractService
{
    /**
     * Available template placeholders mapped to lease data.
     */
    private function buildPlaceholders(LeaseAgreement $lease, string $signerName = '', string $signedDate = ''): array
    {
        $firstItem = $lease->invoiceableItems->first();
        $rentAmount = $firstItem ? number_format($firstItem->amount, 2) : 'N/A';

        return [
            '{{tenant_name}}'       => $lease->tenant->names ?? 'N/A',
            '{{property_name}}'     => $lease->property->property_name ?? 'N/A',
            '{{unit_code}}'         => $lease->unit->unit_code ?? 'N/A',
            '{{ref_number}}'        => $lease->ref_number ?? 'N/A',
            '{{date_start}}'        => $lease->date_start ? $lease->date_start->format('d M Y') : 'N/A',
            '{{date_end}}'          => $lease->date_end ? $lease->date_end->format('d M Y') : 'N/A',
            '{{years}}'             => $lease->years ?? 'N/A',
            '{{escalation_rate}}'   => ($lease->escalation_rate ?? '0') . '%',
            '{{rent_amount}}'       => $rentAmount,
            '{{signer_name}}'       => $signerName,
            '{{signed_date}}'       => $signedDate,
        ];
    }

    /**
     * Render template HTML with placeholders replaced.
     */
    private function renderHtml(string $html, array $placeholders): string
    {
        return str_replace(array_keys($placeholders), array_values($placeholders), $html);
    }

    /**
     * Generate unsigned PDF for the lease contract.
     * Returns the stored relative path.
     */
    public function generateUnsignedPdf(LeaseAgreement $lease, LeaseContractTemplate $template): string
    {
        $lease->load(['tenant', 'property', 'unit', 'invoiceableItems']);

        $placeholders = $this->buildPlaceholders($lease);
        $bodyHtml = $this->renderHtml($template->html_body, $placeholders);

        $pdfView = view('pdf.lease-contract', [
            'bodyHtml'    => $bodyHtml,
            'lease'       => $lease,
            'signerName'  => null,
            'signedDate'  => null,
        ])->render();

        $pdf = Pdf::loadHTML($pdfView);
        $pdf->setPaper('A4', 'portrait');

        $fileName = "lease_contracts/{$lease->id}_unsigned.pdf";
        Storage::disk('public')->put($fileName, $pdf->output());

        return $fileName;
    }

    /**
     * Generate signed PDF with signer name and date embedded.
     * Returns the stored relative path.
     */
    public function generateSignedPdf(LeaseAgreement $lease, string $signerName): string
    {
        $lease->load(['tenant', 'property', 'unit', 'invoiceableItems', 'contractTemplate']);

        $template = $lease->contractTemplate
            ?? LeaseContractTemplate::where('is_default', true)->first();

        if (!$template) {
            throw new \RuntimeException('No contract template found to generate signed PDF.');
        }

        $signedDate = now()->format('d M Y');
        $placeholders = $this->buildPlaceholders($lease, $signerName, $signedDate);
        $bodyHtml = $this->renderHtml($template->html_body, $placeholders);

        $pdfView = view('pdf.lease-contract', [
            'bodyHtml'   => $bodyHtml,
            'lease'      => $lease,
            'signerName' => $signerName,
            'signedDate' => $signedDate,
        ])->render();

        $pdf = Pdf::loadHTML($pdfView);
        $pdf->setPaper('A4', 'portrait');

        $fileName = "lease_contracts/{$lease->id}_signed.pdf";
        Storage::disk('public')->put($fileName, $pdf->output());

        return $fileName;
    }

    /**
     * Send contract to tenant.
     * Generates the unsigned PDF, stores a hashed token, and dispatches the notification.
     */
    public function sendToTenant(LeaseAgreement $lease): void
    {
        $lease->load(['tenant', 'property', 'unit', 'invoiceableItems']);

        // Resolve template
        $template = $lease->contractTemplate
            ?? LeaseContractTemplate::where('is_default', true)->first();

        if (!$template) {
            Log::warning("No lease contract template found. Skipping contract send for lease #{$lease->id}.");
            return;
        }

        $tenant = $lease->tenant;
        if (!$tenant || !$tenant->email_address) {
            Log::warning("Tenant has no email address. Skipping contract send for lease #{$lease->id}.");
            return;
        }

        // Generate unsigned PDF
        $unsignedPath = $this->generateUnsignedPdf($lease, $template);

        // Generate raw token (sent in email) — store only the hash
        $rawToken = Str::random(64);
        $hashedToken = hash('sha256', $rawToken);

        $lease->unsigned_pdf_path = $unsignedPath;
        $lease->contract_sign_token = $hashedToken;
        $lease->contract_sent_at = now();
        $lease->contract_template_id = $template->id;
        $lease->save();

        // Notify tenant via email
        $tenant->notify(new LeaseContractNotification($lease, $rawToken, $unsignedPath));

        Log::info("Lease contract sent to tenant", [
            'lease_id' => $lease->id,
            'tenant_id' => $tenant->id,
            'email' => $tenant->email_address,
        ]);
    }
}
