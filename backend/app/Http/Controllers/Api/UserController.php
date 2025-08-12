<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Salary;
use App\Models\SalaryHistory;
use App\Models\Commission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'salary_document' => 'required|file|mimes:pdf,doc,docx,xls,xlsx|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Check if user exists by email
            $user = User::where('email', $request->email)->first();

            if ($user) {
                // Update existing user
                $user->update([
                    'name' => $request->name,
                ]);
            } else {
                // Create new user
                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => bcrypt('temporary_password'), // You might want to generate a random password
                ]);
            }

            // Handle file upload
            $file = $request->file('salary_document');
            $fileName = time() . '_' . $user->id . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('salary_documents', $fileName, 'public');

            // Get default commission
            $commission = Commission::getDefaultCommission();

            // Create or update salary record
            $salary = Salary::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'salary_local_currency' => '€0.00', // This would be extracted from the document
                    'salary_euros' => 0.00, // This would be extracted from the document
                    'commission' => $commission->amount,
                    'document_path' => $filePath,
                    'notes' => 'Salary document uploaded',
                ]
            );

            // Log salary history if this is an update
            if ($salary->wasRecentlyCreated === false) {
                SalaryHistory::create([
                    'user_id' => $user->id,
                    'old_salary_local_currency' => $salary->getOriginal('salary_local_currency'),
                    'new_salary_local_currency' => $salary->salary_local_currency,
                    'old_salary_euros' => $salary->getOriginal('salary_euros'),
                    'new_salary_euros' => $salary->salary_euros,
                    'old_commission' => $salary->getOriginal('commission'),
                    'new_commission' => $salary->commission,
                    'changed_by' => $user->id, // Self-update for now
                    'change_reason' => 'Document re-upload',
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $user->wasRecentlyCreated ? 'User created successfully' : 'User updated successfully',
                'data' => [
                    'user' => $user->load('salary'),
                    'salary' => $salary,
                ]
            ], $user->wasRecentlyCreated ? 201 : 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing the request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index(Request $request)
    {
        $query = User::with('salary');

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Pagination
        $perPage = $request->get('per_page', 10);
        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ]
        ]);
    }

    public function show(User $user)
    {
        $user->load(['salary', 'salaryHistory']);
        
        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'salary_local_currency' => 'sometimes|string|max:255',
            'salary_euros' => 'sometimes|numeric|min:0',
            'commission' => 'sometimes|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Update user if name is provided
            if ($request->has('name')) {
                $user->update(['name' => $request->name]);
            }

            // Update salary if provided
            if ($user->salary && ($request->has('salary_local_currency') || $request->has('salary_euros') || $request->has('commission'))) {
                $oldSalary = $user->salary->toArray();
                
                $user->salary->update($request->only(['salary_local_currency', 'salary_euros', 'commission']));

                // Log salary history
                SalaryHistory::create([
                    'user_id' => $user->id,
                    'old_salary_local_currency' => $oldSalary['salary_local_currency'],
                    'new_salary_local_currency' => $user->salary->salary_local_currency,
                    'old_salary_euros' => $oldSalary['salary_euros'],
                    'new_salary_euros' => $user->salary->salary_euros,
                    'old_commission' => $oldSalary['commission'],
                    'new_commission' => $user->salary->commission,
                    'changed_by' => $user->id, // Admin ID would be used here
                    'change_reason' => 'Admin update',
                ]);
            }

            DB::commit();

            $user->load('salary', 'salaryHistory');

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => $user
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(User $user)
    {
        try {
            // Delete associated files
            if ($user->salary && $user->salary->document_path) {
                Storage::disk('public')->delete($user->salary->document_path);
            }

            // Delete user and associated records
            $user->salaryHistory()->delete();
            $user->salary()->delete();
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting the user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function bulkUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'users' => 'required|array',
            'users.*.id' => 'required|exists:users,id',
            'users.*.salary_local_currency' => 'sometimes|string',
            'users.*.salary_euros' => 'sometimes|numeric|min:0',
            'users.*.commission' => 'sometimes|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $updatedUsers = [];

            foreach ($request->users as $userData) {
                $user = User::find($userData['id']);
                
                if ($user && $user->salary) {
                    $oldSalary = $user->salary->toArray();
                    
                    $user->salary->update(array_filter($userData, function($key) {
                        return in_array($key, ['salary_local_currency', 'salary_euros', 'commission']);
                    }, ARRAY_FILTER_USE_KEY));

                    // Log salary history
                    SalaryHistory::create([
                        'user_id' => $user->id,
                        'old_salary_local_currency' => $oldSalary['salary_local_currency'],
                        'new_salary_local_currency' => $user->salary->salary_local_currency,
                        'old_salary_euros' => $oldSalary['salary_euros'],
                        'new_salary_euros' => $user->salary->salary_euros,
                        'old_commission' => $oldSalary['commission'],
                        'new_commission' => $user->salary->commission,
                        'changed_by' => $user->id, // Admin ID would be used here
                        'change_reason' => 'Bulk update',
                    ]);

                    $updatedUsers[] = $user->load('salary');
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bulk update completed successfully',
                'data' => [
                    'updated_users' => $updatedUsers,
                    'total_updated' => count($updatedUsers)
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during bulk update',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
