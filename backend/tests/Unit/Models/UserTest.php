<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\User;
use App\Models\Salary;
use App\Models\SalaryHistory;
use App\Models\UploadedDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Collection;

class UserTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_has_fillable_attributes()
    {
        $fillable = ['name', 'email', 'password', 'email_verified_at'];
        $user = new User();
        
        $this->assertEquals($fillable, $user->getFillable());
    }

    /** @test */
    public function it_has_hidden_attributes()
    {
        $hidden = ['password', 'remember_token', 'deleted_at'];
        $user = new User();
        
        $this->assertEquals($hidden, $user->getHidden());
    }

    /** @test */
    public function it_casts_attributes_correctly()
    {
        $user = User::factory()->create([
            'email_verified_at' => '2024-01-01 12:00:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $user->email_verified_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $user->created_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $user->updated_at);
    }

    /** @test */
    public function it_has_one_salary_relationship()
    {
        $user = User::factory()->create();
        $salary = Salary::factory()->forUser($user)->create();

        $this->assertInstanceOf(Salary::class, $user->salary);
        $this->assertEquals($salary->id, $user->salary->id);
    }

    /** @test */
    public function it_has_many_salary_history_relationship()
    {
        $user = User::factory()->create();
        $histories = SalaryHistory::factory()->count(3)->forUser($user)->create();

        $this->assertInstanceOf(Collection::class, $user->salaryHistory);
        $this->assertCount(3, $user->salaryHistory);
        $this->assertInstanceOf(SalaryHistory::class, $user->salaryHistory->first());
    }

    /** @test */
    public function it_has_many_uploaded_documents_relationship()
    {
        $user = User::factory()->create();
        
        // Create uploaded documents manually since we don't have a factory yet
        $documents = collect([
            UploadedDocument::create([
                'user_id' => $user->id,
                'original_filename' => 'test1.pdf',
                'stored_filename' => 'stored1.pdf',
                'file_path' => 'documents/stored1.pdf',
                'file_size' => 1024,
                'mime_type' => 'application/pdf',
                'is_verified' => true,
            ]),
            UploadedDocument::create([
                'user_id' => $user->id,
                'original_filename' => 'test2.pdf',
                'stored_filename' => 'stored2.pdf',
                'file_path' => 'documents/stored2.pdf',
                'file_size' => 2048,
                'mime_type' => 'application/pdf',
                'is_verified' => false,
            ]),
        ]);

        $this->assertInstanceOf(Collection::class, $user->uploadedDocuments);
        $this->assertCount(2, $user->uploadedDocuments);
        $this->assertInstanceOf(UploadedDocument::class, $user->uploadedDocuments->first());
    }

    /** @test */
    public function it_returns_salary_history_ordered_by_creation_date()
    {
        $user = User::factory()->create();
        
        $oldHistory = SalaryHistory::factory()->forUser($user)->create([
            'created_at' => now()->subDays(5),
        ]);
        
        $newHistory = SalaryHistory::factory()->forUser($user)->create([
            'created_at' => now()->subDays(1),
        ]);

        $orderedHistory = $user->salaryHistoryOrdered;
        
        $this->assertEquals($newHistory->id, $orderedHistory->first()->id);
        $this->assertEquals($oldHistory->id, $orderedHistory->last()->id);
    }

    /** @test */
    public function it_returns_only_verified_documents()
    {
        $user = User::factory()->create();
        
        $verifiedDoc = UploadedDocument::create([
            'user_id' => $user->id,
            'original_filename' => 'verified.pdf',
            'stored_filename' => 'verified.pdf',
            'file_path' => 'documents/verified.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'is_verified' => true,
        ]);
        
        $unverifiedDoc = UploadedDocument::create([
            'user_id' => $user->id,
            'original_filename' => 'unverified.pdf',
            'stored_filename' => 'unverified.pdf',
            'file_path' => 'documents/unverified.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'is_verified' => false,
        ]);

        $verifiedDocuments = $user->verifiedDocuments;
        
        $this->assertCount(1, $verifiedDocuments);
        $this->assertEquals($verifiedDoc->id, $verifiedDocuments->first()->id);
    }

    /** @test */
    public function it_can_search_users_by_name_or_email()
    {
        $user1 = User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user2 = User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);
        $user3 = User::factory()->create(['name' => 'Bob Johnson', 'email' => 'bob@test.com']);

        // Search by name
        $results = User::search('John')->get();
        $this->assertCount(2, $results); // John Doe and Bob Johnson
        $this->assertTrue($results->contains($user1));
        $this->assertTrue($results->contains($user3));

        // Search by email
        $results = User::search('example.com')->get();
        $this->assertCount(2, $results); // john@example.com and jane@example.com
        $this->assertTrue($results->contains($user1));
        $this->assertTrue($results->contains($user2));

        // Search by partial name
        $results = User::search('Jane')->get();
        $this->assertCount(1, $results);
        $this->assertTrue($results->contains($user2));
    }

    /** @test */
    public function it_can_scope_users_with_salary()
    {
        $userWithSalary = User::factory()->create();
        $userWithoutSalary = User::factory()->create();
        
        Salary::factory()->forUser($userWithSalary)->create();

        $usersWithSalary = User::withSalary()->get();
        
        // Both users should be returned, but one will have salary loaded
        $this->assertCount(2, $usersWithSalary);
        
        $userWithSalaryFromQuery = $usersWithSalary->find($userWithSalary->id);
        $userWithoutSalaryFromQuery = $usersWithSalary->find($userWithoutSalary->id);
        
        $this->assertTrue($userWithSalaryFromQuery->relationLoaded('salary'));
        $this->assertTrue($userWithoutSalaryFromQuery->relationLoaded('salary'));
    }

    /** @test */
    public function it_returns_display_name_attribute()
    {
        $userWithName = User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $userWithoutName = User::factory()->create(['name' => '', 'email' => 'jane@example.com']);

        $this->assertEquals('John Doe', $userWithName->display_name);
        $this->assertEquals('jane@example.com', $userWithoutName->display_name);
    }

    /** @test */
    public function it_checks_if_user_has_current_salary()
    {
        $userWithSalary = User::factory()->create();
        $userWithoutSalary = User::factory()->create();
        
        Salary::factory()->forUser($userWithSalary)->create();

        $this->assertTrue($userWithSalary->fresh()->hasCurrentSalary());
        $this->assertFalse($userWithoutSalary->hasCurrentSalary());
    }

    /** @test */
    public function it_returns_current_displayed_salary_attribute()
    {
        $user = User::factory()->create();
        $salary = Salary::factory()->forUser($user)->withAmounts(50000, 42500, 600)->create();

        $this->assertEquals(43100.00, $user->fresh()->current_displayed_salary);
    }

    /** @test */
    public function it_returns_null_for_current_displayed_salary_when_no_salary()
    {
        $user = User::factory()->create();

        $this->assertNull($user->current_displayed_salary);
    }

    /** @test */
    public function it_identifies_admin_users_by_email()
    {
        config(['app.admin_emails' => ['admin@example.com', 'superadmin@example.com']]);
        
        $adminUser = User::factory()->create(['email' => 'admin@example.com']);
        $regularUser = User::factory()->create(['email' => 'user@example.com']);

        $this->assertTrue($adminUser->isAdmin());
        $this->assertFalse($regularUser->isAdmin());
    }

    /** @test */
    public function it_falls_back_to_email_contains_admin_check()
    {
        config(['app.admin_emails' => []]);
        
        $adminUser = User::factory()->create(['email' => 'admin@example.com']);
        $adminUser2 = User::factory()->create(['email' => 'test.admin@example.com']);
        $regularUser = User::factory()->create(['email' => 'user@example.com']);

        $this->assertTrue($adminUser->isAdmin());
        $this->assertTrue($adminUser2->isAdmin());
        $this->assertFalse($regularUser->isAdmin());
    }

    /** @test */
    public function it_returns_correct_role_attribute()
    {
        config(['app.admin_emails' => ['admin@example.com']]);
        
        $adminUser = User::factory()->create(['email' => 'admin@example.com']);
        $regularUser = User::factory()->create(['email' => 'user@example.com']);

        $this->assertEquals('admin', $adminUser->role);
        $this->assertEquals('user', $regularUser->role);
    }

    /** @test */
    public function it_uses_soft_deletes()
    {
        $user = User::factory()->create();
        $userId = $user->id;

        $user->delete();

        // User should be soft deleted
        $this->assertSoftDeleted('users', ['id' => $userId]);
        
        // User should not be found in normal queries
        $this->assertNull(User::find($userId));
        
        // User should be found in withTrashed queries
        $this->assertNotNull(User::withTrashed()->find($userId));
    }

    /** @test */
    public function it_can_be_restored_after_soft_delete()
    {
        $user = User::factory()->create();
        $userId = $user->id;

        $user->delete();
        $this->assertSoftDeleted('users', ['id' => $userId]);

        $user->restore();
        $this->assertDatabaseHas('users', ['id' => $userId, 'deleted_at' => null]);
    }

    /** @test */
    public function it_validates_email_uniqueness_at_database_level()
    {
        $user1 = User::factory()->create(['email' => 'test@example.com']);
        
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        User::factory()->create(['email' => 'test@example.com']);
    }

    /** @test */
    public function it_hashes_password_automatically()
    {
        $user = User::factory()->create(['password' => 'plaintext']);
        
        $this->assertNotEquals('plaintext', $user->password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('plaintext', $user->password));
    }
}