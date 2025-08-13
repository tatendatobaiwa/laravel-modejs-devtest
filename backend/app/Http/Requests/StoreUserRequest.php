<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\User;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Allow all users to submit the form
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
                'regex:/^[a-zA-Z\s\-\'\.]+$/', // Only letters, spaces, hyphens, apostrophes, and dots
            ],
            'email' => [
                'required',
                'string',
                'email:rfc,dns',
                'max:255',
                'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
                function ($attribute, $value, $fail) {
                    // Custom domain validation - restrict certain domains if needed
                    $restrictedDomains = ['tempmail.com', '10minutemail.com', 'guerrillamail.com'];
                    $domain = substr(strrchr($value, "@"), 1);
                    
                    if (in_array(strtolower($domain), $restrictedDomains)) {
                        $fail('The email domain is not allowed.');
                    }
                },
            ],
            'salary_local_currency' => [
                'required',
                'numeric',
                'min:0',
                'max:999999.99',
                'regex:/^\d+(\.\d{1,2})?$/', // Allow up to 2 decimal places
            ],
            'local_currency_code' => [
                'required',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
                Rule::in(['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY']), // Supported currencies
            ],
            'commission' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999.99',
                'regex:/^\d+(\.\d{1,2})?$/',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'document' => [
                'nullable',
                'file',
                'mimes:pdf,doc,docx,jpg,jpeg,png',
                'max:5120', // 5MB max
            ],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The name field is required.',
            'name.min' => 'The name must be at least 2 characters long.',
            'name.max' => 'The name may not be greater than 255 characters.',
            'name.regex' => 'The name may only contain letters, spaces, hyphens, apostrophes, and dots.',
            
            'email.required' => 'The email field is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.max' => 'The email may not be greater than 255 characters.',
            'email.regex' => 'Please enter a valid email format.',
            
            'salary_local_currency.required' => 'The salary field is required.',
            'salary_local_currency.numeric' => 'The salary must be a valid number.',
            'salary_local_currency.min' => 'The salary must be greater than or equal to 0.',
            'salary_local_currency.max' => 'The salary may not be greater than 999,999.99.',
            'salary_local_currency.regex' => 'The salary must be a valid decimal number with up to 2 decimal places.',
            
            'local_currency_code.required' => 'The currency code is required.',
            'local_currency_code.size' => 'The currency code must be exactly 3 characters.',
            'local_currency_code.regex' => 'The currency code must be 3 uppercase letters.',
            'local_currency_code.in' => 'The selected currency is not supported.',
            
            'commission.numeric' => 'The commission must be a valid number.',
            'commission.min' => 'The commission must be greater than or equal to 0.',
            'commission.max' => 'The commission may not be greater than 99,999.99.',
            'commission.regex' => 'The commission must be a valid decimal number with up to 2 decimal places.',
            
            'notes.max' => 'The notes may not be greater than 1000 characters.',
            
            'document.file' => 'The document must be a valid file.',
            'document.mimes' => 'The document must be a PDF, Word document, or image file (JPG, JPEG, PNG).',
            'document.max' => 'The document may not be larger than 5MB.',
        ];
    }

    /**
     * Get custom attribute names for validation errors.
     */
    public function attributes(): array
    {
        return [
            'salary_local_currency' => 'salary',
            'local_currency_code' => 'currency',
            'document' => 'uploaded document',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitize and normalize input data
        $this->merge([
            'name' => $this->sanitizeName($this->input('name')),
            'email' => $this->sanitizeEmail($this->input('email')),
            'salary_local_currency' => $this->sanitizeNumeric($this->input('salary_local_currency')),
            'commission' => $this->sanitizeNumeric($this->input('commission')),
            'local_currency_code' => $this->sanitizeCurrencyCode($this->input('local_currency_code')),
            'notes' => $this->sanitizeText($this->input('notes')),
        ]);
    }

    /**
     * Handle a passed validation attempt.
     */
    protected function passedValidation(): void
    {
        // Check for existing user with same email and handle merge logic
        $existingUser = User::where('email', $this->input('email'))->first();
        
        if ($existingUser) {
            // Store the existing user ID for controller to handle merge
            $this->merge(['existing_user_id' => $existingUser->id]);
        }
    }

    /**
     * Sanitize name input.
     */
    private function sanitizeName(?string $name): ?string
    {
        if (!$name) {
            return null;
        }

        // Remove extra whitespace and trim
        $name = preg_replace('/\s+/', ' ', trim($name));
        
        // Remove any potentially harmful characters while preserving valid ones
        $name = preg_replace('/[^\p{L}\s\-\'\.]/u', '', $name);
        
        return $name;
    }

    /**
     * Sanitize email input.
     */
    private function sanitizeEmail(?string $email): ?string
    {
        if (!$email) {
            return null;
        }

        // Convert to lowercase and trim
        return strtolower(trim($email));
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
     * Get the validated and sanitized data.
     */
    public function getSanitizedData(): array
    {
        $validated = $this->validated();
        
        // Ensure commission has default value if not provided
        if (!isset($validated['commission']) || $validated['commission'] === null) {
            $validated['commission'] = 500.00; // Default commission
        }

        return $validated;
    }

    /**
     * Check if this is an update operation (existing user found).
     */
    public function isUpdate(): bool
    {
        return $this->has('existing_user_id');
    }

    /**
     * Get the existing user ID if this is an update.
     */
    public function getExistingUserId(): ?int
    {
        return $this->input('existing_user_id');
    }
}