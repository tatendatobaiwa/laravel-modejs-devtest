<?php

namespace Tests\Unit\Requests;

use Tests\TestCase;
use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StoreUserRequestTest extends TestCase
{
    use RefreshDatabase;

    private function makeRequest(array $data = []): StoreUserRequest
    {
        $request = new StoreUserRequest();
        $request->merge($data);
        return $request;
    }

    private function validateData(array $data): \Illuminate\Validation\Validator
    {
        $request = $this->makeRequest($data);
        return Validator::make($data, $request->rules(), $request->messages());
    }

    /** @test */
    public function it_authorizes_all_users()
    {
        $request = new StoreUserRequest();
        $this->assertTrue($request->authorize());
    }

    /** @test */
    public function it_validates_required_fields()
    {
        $validator = $this->validateData([]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
        $this->assertArrayHasKey('salary_local_currency', $validator->errors()->toArray());
        $this->assertArrayHasKey('local_currency_code', $validator->errors()->toArray());
    }

    /** @test */
    public function it_validates_name_field()
    {
        // Valid names
        $validNames = [
            'John Doe',
            'Mary-Jane Smith',
            "O'Connor",
            'Jean-Pierre',
            'Dr. Smith',
            'José María',
        ];

        foreach ($validNames as $name) {
            $validator = $this->validateData([
                'name' => $name,
                'email' => 'test@example.com',
                'salary_local_currency' => 50000,
                'local_currency_code' => 'USD',
            ]);
            $this->assertFalse($validator->errors()->has('name'), "Name '{$name}' should be valid");
        }

        // Invalid names
        $invalidNames = [
            'A', // Too short
            str_repeat('A', 256), // Too long
            'John123', // Contains numbers
            'John@Doe', // Contains special characters
            '', // Empty
        ];

        foreach ($invalidNames as $name) {
            $validator = $this->validateData([
                'name' => $name,
                'email' => 'test@example.com',
                'salary_local_currency' => 50000,
                'local_currency_code' => 'USD',
            ]);
            $this->assertTrue($validator->errors()->has('name'), "Name '{$name}' should be invalid");
        }
    }

    /** @test */
    public function it_validates_email_field()
    {
        // Valid emails
        $validEmails = [
            'test@example.com',
            'user.name@domain.co.uk',
            'user+tag@example.org',
            'user123@test-domain.com',
        ];

        foreach ($validEmails as $email) {
            $validator = $this->validateData([
                'name' => 'John Doe',
                'email' => $email,
                'salary_local_currency' => 50000,
                'local_currency_code' => 'USD',
            ]);
            $this->assertFalse($validator->errors()->has('email'), "Email '{$email}' should be valid");
        }

        // Invalid emails
        $invalidEmails = [
            'invalid-email',
            'test@',
            '@example.com',
            'test..test@example.com',
            str_repeat('a', 250) . '@example.com', // Too long
        ];

        foreach ($invalidEmails as $email) {
            $validator = $this->validateData([
                'name' => 'John Doe',
                'email' => $email,
                'salary_local_currency' => 50000,
                'local_currency_code' => 'USD',
            ]);
            $this->assertTrue($validator->errors()->has('email'), "Email '{$email}' should be invalid");
        }
    }

    /** @test */
    public function it_validates_restricted_email_domains()
    {
        $restrictedEmails = [
            'test@tempmail.com',
            'user@10minutemail.com',
            'spam@guerrillamail.com',
        ];

        foreach ($restrictedEmails as $email) {
            $validator = $this->validateData([
                'name' => 'John Doe',
                'email' => $email,
                'salary_local_currency' => 50000,
                'local_currency_code' => 'USD',
            ]);
            $this->assertTrue($validator->errors()->has('email'), "Email '{$email}' should be restricted");
        }
    }

    /** @test */
    public function it_validates_salary_local_currency_field()
    {
        // Valid salaries
        $validSalaries = [
            50000,
            50000.50,
            0,
            999999.99,
        ];

        foreach ($validSalaries as $salary) {
            $validator = $this->validateData([
                'name' => 'John Doe',
                'email' => 'test@example.com',
                'salary_local_currency' => $salary,
                'local_currency_code' => 'USD',
            ]);
            $this->assertFalse($validator->errors()->has('salary_local_currency'), "Salary '{$salary}' should be valid");
        }

        // Invalid salaries
        $invalidSalaries = [
            -1000, // Negative
            1000000, // Too high
            'not-a-number', // Not numeric
            50000.999, // Too many decimal places
        ];

        foreach ($invalidSalaries as $salary) {
            $validator = $this->validateData([
                'name' => 'John Doe',
                'email' => 'test@example.com',
                'salary_local_currency' => $salary,
                'local_currency_code' => 'USD',
            ]);
            $this->assertTrue($validator->errors()->has('salary_local_currency'), "Salary '{$salary}' should be invalid");
        }
    }

    /** @test */
    public function it_validates_local_currency_code_field()
    {
        // Valid currency codes
        $validCurrencies = ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY'];

        foreach ($validCurrencies as $currency) {
            $validator = $this->validateData([
                'name' => 'John Doe',
                'email' => 'test@example.com',
                'salary_local_currency' => 50000,
                'local_currency_code' => $currency,
            ]);
            $this->assertFalse($validator->errors()->has('local_currency_code'), "Currency '{$currency}' should be valid");
        }

        // Invalid currency codes
        $invalidCurrencies = [
            'XYZ', // Not supported
            'usd', // Lowercase
            'US', // Too short
            'USDD', // Too long
            '123', // Numbers
        ];

        foreach ($invalidCurrencies as $currency) {
            $validator = $this->validateData([
                'name' => 'John Doe',
                'email' => 'test@example.com',
                'salary_local_currency' => 50000,
                'local_currency_code' => $currency,
            ]);
            $this->assertTrue($validator->errors()->has('local_currency_code'), "Currency '{$currency}' should be invalid");
        }
    }

    /** @test */
    public function it_validates_optional_commission_field()
    {
        // Valid commissions (including null)
        $validCommissions = [null, 0, 500.50, 99999.99];

        foreach ($validCommissions as $commission) {
            $validator = $this->validateData([
                'name' => 'John Doe',
                'email' => 'test@example.com',
                'salary_local_currency' => 50000,
                'local_currency_code' => 'USD',
                'commission' => $commission,
            ]);
            $this->assertFalse($validator->errors()->has('commission'), "Commission '{$commission}' should be valid");
        }

        // Invalid commissions
        $invalidCommissions = [
            -100, // Negative
            100000, // Too high
            'not-a-number', // Not numeric
            500.999, // Too many decimal places
        ];

        foreach ($invalidCommissions as $commission) {
            $validator = $this->validateData([
                'name' => 'John Doe',
                'email' => 'test@example.com',
                'salary_local_currency' => 50000,
                'local_currency_code' => 'USD',
                'commission' => $commission,
            ]);
            $this->assertTrue($validator->errors()->has('commission'), "Commission '{$commission}' should be invalid");
        }
    }

    /** @test */
    public function it_validates_optional_notes_field()
    {
        // Valid notes
        $validNotes = [
            null,
            '',
            'Short note',
            str_repeat('A', 1000), // Max length
        ];

        foreach ($validNotes as $notes) {
            $validator = $this->validateData([
                'name' => 'John Doe',
                'email' => 'test@example.com',
                'salary_local_currency' => 50000,
                'local_currency_code' => 'USD',
                'notes' => $notes,
            ]);
            $this->assertFalse($validator->errors()->has('notes'), "Notes should be valid");
        }

        // Invalid notes
        $invalidNotes = [
            str_repeat('A', 1001), // Too long
        ];

        foreach ($invalidNotes as $notes) {
            $validator = $this->validateData([
                'name' => 'John Doe',
                'email' => 'test@example.com',
                'salary_local_currency' => 50000,
                'local_currency_code' => 'USD',
                'notes' => $notes,
            ]);
            $this->assertTrue($validator->errors()->has('notes'), "Notes should be invalid");
        }
    }

    /** @test */
    public function it_validates_optional_document_field()
    {
        Storage::fake('local');

        // Valid document types
        $validDocuments = [
            UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf'),
            UploadedFile::fake()->create('document.doc', 1024, 'application/msword'),
            UploadedFile::fake()->create('document.docx', 1024, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            UploadedFile::fake()->image('image.jpg', 100, 100)->size(1024),
            UploadedFile::fake()->image('image.jpeg', 100, 100)->size(1024),
            UploadedFile::fake()->image('image.png', 100, 100)->size(1024),
        ];

        foreach ($validDocuments as $document) {
            $validator = $this->validateData([
                'name' => 'John Doe',
                'email' => 'test@example.com',
                'salary_local_currency' => 50000,
                'local_currency_code' => 'USD',
                'document' => $document,
            ]);
            $this->assertFalse($validator->errors()->has('document'), "Document type should be valid");
        }

        // Invalid documents
        $invalidDocuments = [
            UploadedFile::fake()->create('document.txt', 1024, 'text/plain'), // Wrong type
            UploadedFile::fake()->create('document.pdf', 6000, 'application/pdf'), // Too large (>5MB)
        ];

        foreach ($invalidDocuments as $document) {
            $validator = $this->validateData([
                'name' => 'John Doe',
                'email' => 'test@example.com',
                'salary_local_currency' => 50000,
                'local_currency_code' => 'USD',
                'document' => $document,
            ]);
            $this->assertTrue($validator->errors()->has('document'), "Document should be invalid");
        }
    }

    /** @test */
    public function it_has_custom_validation_messages()
    {
        $request = new StoreUserRequest();
        $messages = $request->messages();

        $this->assertArrayHasKey('name.required', $messages);
        $this->assertArrayHasKey('email.required', $messages);
        $this->assertArrayHasKey('salary_local_currency.required', $messages);
        $this->assertArrayHasKey('local_currency_code.required', $messages);
        $this->assertEquals('The name field is required.', $messages['name.required']);
    }

    /** @test */
    public function it_has_custom_attribute_names()
    {
        $request = new StoreUserRequest();
        $attributes = $request->attributes();

        $this->assertEquals('salary', $attributes['salary_local_currency']);
        $this->assertEquals('currency', $attributes['local_currency_code']);
        $this->assertEquals('uploaded document', $attributes['document']);
    }

    /** @test */
    public function it_sanitizes_name_input()
    {
        $request = $this->makeRequest(['name' => '  John   Doe  ']);
        $request->prepareForValidation();

        $this->assertEquals('John Doe', $request->input('name'));
    }

    /** @test */
    public function it_sanitizes_email_input()
    {
        $request = $this->makeRequest(['email' => '  TEST@EXAMPLE.COM  ']);
        $request->prepareForValidation();

        $this->assertEquals('test@example.com', $request->input('email'));
    }

    /** @test */
    public function it_sanitizes_numeric_inputs()
    {
        $request = $this->makeRequest([
            'salary_local_currency' => '50,000.50',
            'commission' => '1,500.75',
        ]);
        $request->prepareForValidation();

        $this->assertEquals(50000.50, $request->input('salary_local_currency'));
        $this->assertEquals(1500.75, $request->input('commission'));
    }

    /** @test */
    public function it_sanitizes_currency_code_input()
    {
        $request = $this->makeRequest(['local_currency_code' => 'usd']);
        $request->prepareForValidation();

        $this->assertEquals('USD', $request->input('local_currency_code'));
    }

    /** @test */
    public function it_sanitizes_text_input()
    {
        $request = $this->makeRequest(['notes' => '  <script>alert("xss")</script>  Test note  ']);
        $request->prepareForValidation();

        $this->assertEquals('alert("xss") Test note', $request->input('notes'));
    }

    /** @test */
    public function it_detects_existing_user_during_validation()
    {
        $existingUser = User::factory()->create(['email' => 'existing@example.com']);

        $request = $this->makeRequest([
            'name' => 'John Doe',
            'email' => 'existing@example.com',
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD',
        ]);

        // Simulate validation passing
        $validator = $this->validateData($request->all());
        if ($validator->passes()) {
            $request->passedValidation();
        }

        $this->assertTrue($request->has('existing_user_id'));
        $this->assertEquals($existingUser->id, $request->input('existing_user_id'));
    }

    /** @test */
    public function it_provides_sanitized_data_with_default_commission()
    {
        $request = $this->makeRequest([
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD',
        ]);

        $sanitizedData = $request->getSanitizedData();

        $this->assertEquals(500.00, $sanitizedData['commission']);
    }

    /** @test */
    public function it_preserves_provided_commission_in_sanitized_data()
    {
        $request = $this->makeRequest([
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'salary_local_currency' => 50000,
            'local_currency_code' => 'USD',
            'commission' => 750,
        ]);

        $sanitizedData = $request->getSanitizedData();

        $this->assertEquals(750.00, $sanitizedData['commission']);
    }

    /** @test */
    public function it_identifies_update_operations()
    {
        $existingUser = User::factory()->create(['email' => 'existing@example.com']);

        $request = $this->makeRequest([
            'email' => 'existing@example.com',
            'existing_user_id' => $existingUser->id,
        ]);

        $this->assertTrue($request->isUpdate());
        $this->assertEquals($existingUser->id, $request->getExistingUserId());
    }

    /** @test */
    public function it_identifies_create_operations()
    {
        $request = $this->makeRequest([
            'email' => 'new@example.com',
        ]);

        $this->assertFalse($request->isUpdate());
        $this->assertNull($request->getExistingUserId());
    }

    /** @test */
    public function it_handles_edge_cases_in_sanitization()
    {
        $request = $this->makeRequest([
            'name' => null,
            'email' => null,
            'salary_local_currency' => null,
            'commission' => null,
            'local_currency_code' => null,
            'notes' => null,
        ]);
        $request->prepareForValidation();

        $this->assertNull($request->input('name'));
        $this->assertNull($request->input('email'));
        $this->assertNull($request->input('salary_local_currency'));
        $this->assertNull($request->input('commission'));
        $this->assertNull($request->input('local_currency_code'));
        $this->assertNull($request->input('notes'));
    }

    /** @test */
    public function it_handles_multiple_decimal_points_in_numeric_sanitization()
    {
        $request = $this->makeRequest([
            'salary_local_currency' => '50.000.50',
            'commission' => '1.500.75',
        ]);
        $request->prepareForValidation();

        $this->assertEquals(50000.50, $request->input('salary_local_currency'));
        $this->assertEquals(1500.75, $request->input('commission'));
    }

    /** @test */
    public function it_removes_non_alphabetic_characters_from_currency_code()
    {
        $request = $this->makeRequest(['local_currency_code' => 'u$d123']);
        $request->prepareForValidation();

        $this->assertEquals('USD', $request->input('local_currency_code'));
    }
}