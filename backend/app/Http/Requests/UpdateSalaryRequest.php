<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Salary;

class UpdateSalaryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only allow admin users to update salaries
        // In a real application, you would check for admin role/permission
        return auth()->check() && $this->userIsAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'salary_local_currency' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                'max:999999.99',
                'regex:/^\d+(\.\d{1,2})?$/', // Allow up to 2 decimal places
                function ($attribute, $value, $fail) {
                    // Business rule: minimum salary validation
                    if ($value < $this->getMinimumSalaryForCurrency()) {
                        $fail('The salary is below the minimum allowed for this currency.');
                    }
                },
            ],
            'local_currency_code' => [
                'sometimes',
                'required',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
                Rule::in(['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY']), // Supported currencies
            ],
            'salary_euros' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                'max:999999.99',
                'regex:/^\d+(\.\d{1,2})?$/',
            ],
            'commission' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:50000.00', // Business rule: maximum commission limit
                'regex:/^\d+(\.\d{1,2})?$/',
                function ($attribute, $value, $fail) {
                    // Business rule: commission cannot exceed 50% of salary
                    $salaryEuros = $this->input('salary_euros') ?? $this->getSalary()?->salary_euros ?? 0;
                    if ($value > ($salaryEuros * 0.5)) {
                        $fail('The commission cannot exceed 50% of the salary in euros.');
                    }
                },
            ],
            'effective_date' => [
                'sometimes',
                'nullable',
                'date',
                'before_or_equal:today',
                'after:2020-01-01', // Business rule: no salaries before 2020
            ],
            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],
            'change_reason' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'min:10', // Require meaningful reason
            ],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'salary_local_currency.required' => 'The salary in local currency is required.',
            'salary_local_currency.numeric' => 'The salary must be a valid number.',
            'salary_local_currency.min' => 'The salary must be greater than or equal to 0.',
            'salary_local_currency.max' => 'The salary may not be greater than 999,999.99.',
            'salary_local_currency.regex' => 'The salary must be a valid decimal number with up to 2 decimal places.',
            
            'local_currency_code.required' => 'The currency code is required.',
            'local_currency_code.size' => 'The currency code must be exactly 3 characters.',
            'local_currency_code.regex' => 'The currency code must be 3 uppercase letters.',
            'local_currency_code.in' => 'The selected currency is not supported.',
            
            'salary_euros.required' => 'The salary in euros is required.',
            'salary_euros.numeric' => 'The euro salary must be a valid number.',
            'salary_euros.min' => 'The euro salary must be greater than or equal to 0.',
            'salary_euros.max' => 'The euro salary may not be greater than 999,999.99.',
            'salary_euros.regex' => 'The euro salary must be a valid decimal number with up to 2 decimal places.',
            
            'commission.numeric' => 'The commission must be a valid number.',
            'commission.min' => 'The commission must be greater than or equal to 0.',
            'commission.max' => 'The commission may not be greater than 50,000.00.',
            'commission.regex' => 'The commission must be a valid decimal number with up to 2 decimal places.',
            
            'effective_date.date' => 'The effective date must be a valid date.',
            'effective_date.before_or_equal' => 'The effective date cannot be in the future.',
            'effective_date.after' => 'The effective date must be after January 1, 2020.',
            
            'notes.max' => 'The notes may not be greater than 1000 characters.',
            
            'change_reason.required' => 'A reason for the change is required.',
            'change_reason.min' => 'The change reason must be at least 10 characters long.',
            'change_reason.max' => 'The change reason may not be greater than 255 characters.',
        ];
    }

    /**
     * Get custom attribute names for validation errors.
     */
    public function attributes(): array
    {
        return [
            'salary_local_currency' => 'salary in local currency',
            'local_currency_code' => 'currency code',
            'salary_euros' => 'salary in euros',
            'effective_date' => 'effective date',
            'change_reason' => 'reason for change',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitize and normalize input data
        $data = [];
        
        if ($this->has('salary_local_currency')) {
            $data['salary_local_currency'] = $this->sanitizeNumeric($this->input('salary_local_currency'));
        }
        
        if ($this->has('salary_euros')) {
            $data['salary_euros'] = $this->sanitizeNumeric($this->input('salary_euros'));
        }
        
        if ($this->has('commission')) {
            $data['commission'] = $this->sanitizeNumeric($this->input('commission'));
        }
        
        if ($this->has('local_currency_code')) {
            $data['local_currency_code'] = $this->sanitizeCurrencyCode($this->input('local_currency_code'));
        }
        
        if ($this->has('notes')) {
            $data['notes'] = $this->sanitizeText($this->input('notes'));
        }
        
        if ($this->has('change_reason')) {
            $data['change_reason'] = $this->sanitizeText($this->input('change_reason'));
        }

        $this->merge($data);
    }

    /**
     * Handle a passed validation attempt.
     */
    protected function passedValidation(): void
    {
        // Set default commission if not provided
        if (!$this->has('commission') || $this->input('commission') === null) {
            $this->merge(['commission' => 500.00]);
        }

        // Set effective date if not provided
        if (!$this->has('effective_date') || $this->input('effective_date') === null) {
            $this->merge(['effective_date' => now()->toDateString()]);
        }

        // Add the authenticated user as the person making the change
        $this->merge(['changed_by' => auth()->id()]);
    }

    /**
     * Check if the authenticated user is an admin.
     */
    private function userIsAdmin(): bool
    {
        // In a real application, this would check roles/permissions
        // For now, we'll assume any authenticated user is admin
        // You should implement proper role-based authorization
        return true;
        
        // Example implementation with roles:
        // return auth()->user()->hasRole('admin') || auth()->user()->hasPermission('manage_salaries');
    }

    /**
     * Get the salary being updated.
     */
    private function getSalary(): ?Salary
    {
        // Get salary from route parameter
        return $this->route('salary');
    }

    /**
     * Get minimum salary for the given currency.
     */
    private function getMinimumSalaryForCurrency(): float
    {
        $currency = $this->input('local_currency_code') ?? $this->getSalary()?->local_currency_code ?? 'EUR';
        
        // Business rules for minimum salaries by currency
        $minimumSalaries = [
            'USD' => 15000.00,  // $15,000 minimum
            'EUR' => 12000.00,  // €12,000 minimum
            'GBP' => 10000.00,  // £10,000 minimum
            'CAD' => 20000.00,  // CAD $20,000 minimum
            'AUD' => 25000.00,  // AUD $25,000 minimum
            'JPY' => 1500000.00, // ¥1,500,000 minimum
        ];

        return $minimumSalaries[$currency] ?? 0;
    }

    /**
     * Sanitize numeric input.
     */
    private function sanitizeNumeric(?string $value): ?float
    {
        if (!$value) {
            return null;
        }

        // Remove any non-numeric characters except decimal point
        $cleaned = preg_replace('/[^\d\.]/', '', $value);
        
        // Ensure only one decimal point
        $parts = explode('.', $cleaned);
        if (count($parts) > 2) {
            $cleaned = $parts[0] . '.' . $parts[1];
        }

        return is_numeric($cleaned) ? (float) $cleaned : null;
    }

    /**
     * Sanitize currency code input.
     */
    private function sanitizeCurrencyCode(?string $code): ?string
    {
        if (!$code) {
            return null;
        }

        // Convert to uppercase and remove non-alphabetic characters
        return strtoupper(preg_replace('/[^A-Za-z]/', '', $code));
    }

    /**
     * Sanitize text input.
     */
    private function sanitizeText(?string $text): ?string
    {
        if (!$text) {
            return null;
        }

        // Remove potentially harmful HTML/script tags and normalize whitespace
        $text = strip_tags($text);
        $text = preg_replace('/\s+/', ' ', trim($text));
        
        return $text;
    }

    /**
     * Get the validated and sanitized data with calculated fields.
     */
    public function getSanitizedData(): array
    {
        $validated = $this->validated();
        
        // Calculate displayed salary if both salary_euros and commission are present
        if (isset($validated['salary_euros']) && isset($validated['commission'])) {
            $validated['displayed_salary'] = round($validated['salary_euros'] + $validated['commission'], 2);
        }

        return $validated;
    }

    /**
     * Get validation rules for bulk update operations.
     */
    public function getBulkUpdateRules(): array
    {
        return [
            'salaries' => 'required|array|min:1|max:100', // Limit bulk operations
            'salaries.*.id' => 'required|integer|exists:salaries,id',
            'salaries.*.salary_local_currency' => $this->rules()['salary_local_currency'],
            'salaries.*.salary_euros' => $this->rules()['salary_euros'],
            'salaries.*.commission' => $this->rules()['commission'],
            'salaries.*.change_reason' => $this->rules()['change_reason'],
        ];
    }

    /**
     * Validate business rules for salary updates.
     */
    public function validateBusinessRules(): array
    {
        $errors = [];
        
        // Check if salary increase is reasonable (not more than 100% increase)
        if ($this->has('salary_euros')) {
            $currentSalary = $this->getSalary();
            if ($currentSalary && $this->input('salary_euros') > ($currentSalary->salary_euros * 2)) {
                $errors['salary_euros'] = 'Salary increase cannot exceed 100% of current salary without additional approval.';
            }
        }

        // Check if commission is within reasonable bounds
        if ($this->has('commission')) {
            $commission = $this->input('commission');
            if ($commission > 10000) {
                $errors['commission'] = 'Commission amounts over €10,000 require additional approval.';
            }
        }

        return $errors;
    }
}