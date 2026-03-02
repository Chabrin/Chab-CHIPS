<?php

namespace App\Traits;

use App\Models\Company;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

trait PdfGenerationTrait
{
    /**
     * Generate PDF using DomPDF with optional encryption
     */
    public function generatePdf(string $viewName, array $data, array $options = []): string
    {
        // Default options
        $defaultOptions = [
            'paper' => 'A4',
            'orientation' => 'portrait',
            'font' => 'Helvetica',
            'encryption' => null,
        ];

        $options = array_merge($defaultOptions, $options);

        // Prepare company logo data
        $this->prepareCompanyLogoData($data);

        // Generate PDF with DomPDF
        $pdf = Pdf::loadView($viewName, $data);
        $pdf->setPaper($options['paper'], $options['orientation']);

        $pdf->setOptions([
            'defaultFont' => $options['font'],
            'isPhpEnabled' => true,
            'isRemoteEnabled' => true,
            'fontSubsetting' => false,
            'chroot' => public_path(),
        ]);

        // Apply encryption if specified using DomPDF's setEncryption method
        if (!empty($options['encryption'])) {
            return $this->applyDomPdfEncryption($pdf, $options['encryption']);
        }

        return $pdf->output();
    }

    /**
     * Apply encryption to DomPDF using setEncryption method
     */
    private function applyDomPdfEncryption($pdf, array $encryptionConfig): string
    {
        try {
            // Render the PDF first (required before encryption)
            $pdf->render();

            // Get the CPDF instance and apply encryption
            $password = $encryptionConfig['user_password'] ?? '';
            $pdf->getDomPDF()->getCanvas()->get_cpdf()->setEncryption($password, $password);

            Log::info('PDF encrypted successfully with DomPDF', [
                'password_set' => !empty($password)
            ]);

            return $pdf->output();
        } catch (\Exception $e) {
            Log::error('DomPDF encryption failed: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);

            // Fallback to unencrypted PDF
            Log::warning('Returning unencrypted PDF due to encryption failure');
            return $pdf->output();
        }
    }

    /**
     * Prepare company logo data for PDF templates
     */
    private function prepareCompanyLogoData(array &$data): void
    {
        if (!isset($data['company'])) {
            $data['company'] = Company::where('is_host', 1)->first();
        }

        $company = $data['company'];
        if ($company && $company->logo) {
            $logoPath = public_path('storage/' . $company->logo);
            if (file_exists($logoPath)) {
                $mimeType = @mime_content_type($logoPath) ?: 'image/png';
                $company->logo_data = 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($logoPath));
            }
        }
    }

    /**
     * Generate filename with timestamp and customer info
     */
    public function generatePdfFilename(string $prefix, $member = null, string $extension = 'pdf'): string
    {
        $firstName = $member->first_name ?? 'Member';
        $timestamp = now()->format('His');
        return "{$prefix}_{$firstName}_{$timestamp}.{$extension}";
    }
}
