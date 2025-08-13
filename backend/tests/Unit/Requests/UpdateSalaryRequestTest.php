<?php

namespace Tests\Unit\Requests;

use Tests\TestCase;
use App\Http\Requests\UpdateSalaryRequest;
use App\Models\User;
use App\Models\Salary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class UpdateSalaryRequestTest extends TestCase
{
    use RefreshDatabase;

    private function makeRequest(array $data = [], ?Salary $salary = null): UpdateSalaryRequest
    {
        $request = new UpdateSalaryRequest();
        $request->merge($data);
        
        if ($salary) {
            $request->setRouteResolver(function () use ($salary) {
                $route = new \Illuminate\Routing\Route(['PUT'], '/salaries/{salary}', []);
                $route->bind(new \Illuminate\Http\Request());
                $route->setParameter('salary', $salary);
                return $route;
            });
        }
        
        return $request;
    }

    private function validateData(array $data, ?Salary $salary = null): \Illuminate\Validation\Validator
    {
        $request = $this->makeRequest($data, $salary);
        return Validator::make($data, $request->rules(), $request->messages());
    }

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock authenticated user for authorization
        $user = User::factory()->create();
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('id')->andReturn($user->id);
    }

    /** @test */
    public function it_authorizes_authenticated_users()
    {
        $request = new UpdateSalaryRequest();
        $this->assertTrue($request->authorize());
    }

    /** @test */
    public function it_validates_salary_local_currency_field()
    {
        // Valid salaries
        $validSalaries = [50000, 50000.50, 999999.99];

        foreach ($validSalaries as $salary) {
            $validator = $this->validateData([
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
                'salary_local_currency' => 50000,
                'local_currency_code' => $currency,
            ]);
            $this->assertTrue($validator->errors()->has('local_currency_code'), "Currency '{$currency}' should be invalid");
        }
    }

    /** @test */
    public function it_validates_salary_euros_field()
    {
        // Valid euro salaries
        $validSalaries = [40000, 42500.50, 999999.99];

        foreach ($validSalar