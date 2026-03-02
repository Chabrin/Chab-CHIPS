<?php

namespace App\Traits;

use App\Models\Member;
use App\Models\Account;
use App\Models\Trans;
use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Log;

trait TemplateVariableHelper
{
    /**
     * Replace variables in email template content
     *
     * @param string $content The template content with variables
     * @param array $data Data to replace variables with
     * @return string The content with variables replaced
     */
    public function replaceTemplateVariables(string $content, array $data = []): string
    {
        try {
            // Extract all variables from content (format: {{variable_name}})
            preg_match_all('/\{\{([^}]+)\}\}/', $content, $matches);

            if (empty($matches[1])) {
                return $content;
            }

            $replacements = [];

            foreach ($matches[1] as $variable) {
                $variable = trim($variable);
                $value = $this->getVariableValue($variable, $data);
                $replacements['{{' . $variable . '}}'] = $value;
            }

            return str_replace(array_keys($replacements), array_values($replacements), $content);
        } catch (\Exception $e) {
            Log::error('Error replacing template variables: ' . $e->getMessage(), [
                'content' => $content,
                'data' => $data
            ]);
            return $content; // Return original content if replacement fails
        }
    }

    /**
     * Get the value for a specific variable
     *
     * @param string $variable The variable name
     * @param array $data The data array
     * @return string The variable value
     */
    protected function getVariableValue(string $variable, array $data): string
    {
        // Direct data lookup first
        if (isset($data[$variable])) {
            return $this->formatValue($data[$variable]);
        }

        // Handle nested data (e.g., member.name, account.number)
        if (strpos($variable, '.') !== false) {
            $parts = explode('.', $variable);
            $value = $data;

            foreach ($parts as $part) {
                if (is_array($value) && isset($value[$part])) {
                    $value = $value[$part];
                } elseif (is_object($value) && isset($value->{$part})) {
                    $value = $value->{$part};
                } else {
                    return "{{$variable}}"; // Return original if not found
                }
            }

            return $this->formatValue($value);
        }

        // Handle model-based variables
        return $this->getModelVariableValue($variable, $data);
    }

    /**
     * Get variable value from model relationships
     *
     * @param string $variable The variable name
     * @param array $data The data array
     * @return string The variable value
     */
    protected function getModelVariableValue(string $variable, array $data): string
    {
        // Member-related variables
        if (isset($data['member']) && $data['member'] instanceof Member) {
            $member = $data['member'];

            switch ($variable) {
                case 'member_name':
                case 'member_fullname':
                    return $this->formatValue($member->first_name . ' ' . $member->last_name);
                case 'member_first_name':
                    return $this->formatValue($member->first_name);
                case 'member_last_name':
                    return $this->formatValue($member->last_name);
                case 'member_email':
                    return $this->formatValue($member->email ?? $member->e_mail ?? $member->contact_email ?? '');
                case 'member_phone':
                    return $this->formatValue($member->phone);
                case 'member_id_number':
                    return $this->formatValue($member->id_number);
                case 'member_pension_number':
                    return $this->formatValue($member->pension_number);
            }
        }

        // Account-related variables
        if (isset($data['account']) && $data['account'] instanceof Account) {
            $account = $data['account'];

            switch ($variable) {
                case 'account_number':
                    return $this->formatValue($account->account_number ?? $account->account_no ?? '');
                case 'account_name':
                    return $this->formatValue($account->account_name);
                case 'account_type':
                    return $this->formatValue($account->account_type);
                case 'account_balance':
                    return $this->formatCurrency($account->balance ?? 0);
            }
        }

        // Transaction-related variables
        if (isset($data['transaction']) && $data['transaction'] instanceof Trans) {
            $transaction = $data['transaction'];

            switch ($variable) {
                case 'transaction_id':
                    return $this->formatValue($transaction->id);
                case 'transaction_amount':
                case 'amount':
                    return $this->formatCurrency($transaction->amount);
                case 'transaction_type':
                    return $this->formatValue($transaction->trans_type ?? $transaction->transaction_type ?? '');
                case 'transaction_date':
                    return $this->formatDate($transaction->trans_date ?? $transaction->created_at ?? now());
                case 'transaction_description':
                    return $this->formatValue($transaction->description ?? '');
                case 'transaction_reference':
                    return $this->formatValue($transaction->reference ?? $transaction->reference_no ?? '');
            }
        }

        // Company/System variables
        switch ($variable) {
            case 'company_name':
                return $this->formatValue(config('app.company_name', 'Pension Administration System'));
            case 'company_email':
                return $this->formatValue(config('app.company_email', 'info@pensionsystem.com'));
            case 'company_phone':
                return $this->formatValue(config('app.company_phone', '+1234567890'));
            case 'system_date':
                return $this->formatDate(now());
            case 'current_year':
                return $this->formatValue(date('Y'));
            case 'support_email':
                return $this->formatValue(config('app.support_email', 'support@pensionsystem.com'));
        }

        // Return placeholder if variable not found
        return "{{$variable}}";
    }

    /**
     * Format a value for display
     *
     * @param mixed $value The value to format
     * @return string The formatted value
     */
    protected function formatValue($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return (string) $value;
    }

    /**
     * Format currency amount
     *
     * @param float|string $amount The amount to format
     * @return string The formatted currency
     */
    protected function formatCurrency($amount): string
    {
        return number_format((float) $amount, 2, '.', ',');
    }

    /**
     * Format date for display
     *
     * @param mixed $date The date to format
     * @return string The formatted date
     */
    protected function formatDate($date): string
    {
        if (empty($date)) {
            return '';
        }

        try {
            if (is_string($date)) {
                $date = \Carbon\Carbon::parse($date);
            }

            return $date->format('F j, Y');
        } catch (\Exception $e) {
            Log::warning('Error formatting date: ' . $e->getMessage(), ['date' => $date]);
            return (string) $date;
        }
    }

    /**
     * Get available template variables for a specific template type
     *
     * @param string $templateType The template type
     * @return array Array of available variables with descriptions
     */
    public function getAvailableVariables(string $templateType = ''): array
    {
        $variables = [
            // Member variables
            'member_name' => 'Full member name',
            'member_first_name' => 'Member first name',
            'member_last_name' => 'Member last name',
            'member_email' => 'Member email address',
            'member_phone' => 'Member phone number',
            'member_id_number' => 'Member ID number',
            'member_pension_number' => 'Member pension number',

            // Account variables
            'account_number' => 'Account number',
            'account_name' => 'Account name',
            'account_type' => 'Account type',
            'account_balance' => 'Current account balance',

            // Transaction variables
            'transaction_id' => 'Transaction ID',
            'amount' => 'Transaction amount',
            'transaction_amount' => 'Transaction amount',
            'transaction_type' => 'Transaction type',
            'transaction_date' => 'Transaction date',
            'transaction_description' => 'Transaction description',
            'transaction_reference' => 'Transaction reference',

            // System variables
            'company_name' => 'Company name',
            'company_email' => 'Company email',
            'company_phone' => 'Company phone',
            'system_date' => 'Current date',
            'current_year' => 'Current year',
            'support_email' => 'Support email address'
        ];

        // Filter variables based on template type
        switch (strtolower($templateType)) {
            case 'deposit':
            case 'withdrawal':
                return array_intersect_key($variables, array_flip([
                    'member_name',
                    'member_first_name',
                    'member_last_name',
                    'member_email',
                    'account_number',
                    'account_name',
                    'account_balance',
                    'amount',
                    'transaction_type',
                    'transaction_date',
                    'transaction_reference',
                    'company_name',
                    'support_email',
                    'system_date'
                ]));

            case 'statement':
                return array_intersect_key($variables, array_flip([
                    'member_name',
                    'member_first_name',
                    'member_last_name',
                    'member_email',
                    'member_pension_number',
                    'account_number',
                    'account_name',
                    'account_balance',
                    'company_name',
                    'company_email',
                    'system_date',
                    'current_year'
                ]));

            case 'notification':
            case 'reminder':
                return array_intersect_key($variables, array_flip([
                    'member_name',
                    'member_first_name',
                    'member_email',
                    'company_name',
                    'support_email',
                    'system_date'
                ]));

            default:
                return $variables;
        }
    }

    /**
     * Process an email template with data
     *
     * @param EmailTemplate $template The email template
     * @param array $data The data for variable replacement
     * @return array Processed template with subject and body
     */
    public function processEmailTemplate(EmailTemplate $template, array $data = []): array
    {
        if (!$template->confirmed) {
            throw new \Exception('Cannot use unconfirmed email template');
        }

        return [
            'subject' => $this->replaceTemplateVariables($template->subject, $data),
            'body' => $this->replaceTemplateVariables($template->body, $data),
            'template_id' => $template->id,
            'template_name' => $template->name
        ];
    }

    /**
     * Validate template variables against available variables
     *
     * @param string $content The template content
     * @param string $templateType The template type
     * @return array Array of invalid variables
     */
    public function validateTemplateVariables(string $content, string $templateType = ''): array
    {
        preg_match_all('/\{\{([^}]+)\}\}/', $content, $matches);

        if (empty($matches[1])) {
            return [];
        }

        $availableVariables = array_keys($this->getAvailableVariables($templateType));
        $usedVariables = array_map('trim', $matches[1]);

        return array_diff($usedVariables, $availableVariables);
    }
}
