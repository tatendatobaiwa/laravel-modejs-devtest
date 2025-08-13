<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * Login user and create token.
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string|min:6',
                'device_name' => 'sometimes|string|max:255',
                'remember' => 'sometimes|boolean',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                throw ValidationException::withMessages([
                    'email' => ['The provided credentials are incorrect.'],
                ]);
            }

            // Check if user account is active (not soft deleted)
            if ($user->trashed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is deactivated. Please contact administrator.',
                    'error' => 'Account deactivated'
                ], 403);
            }

            // Create token with appropriate expiration
            $deviceName = $request->get('device_name', 'API Token');
            $remember = $request->boolean('remember', false);
            
            // Set token expiration based on remember option
            $expiresAt = $remember ? now()->addDays(30) : now()->addHours(24);
            
            $token = $user->createToken($deviceName, ['*'], $expiresAt);

            // Log successful login
            Log::info('User logged in successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'device_name' => $deviceName,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'remember' => $remember,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => $user->load(['salary', 'uploadedDocuments']),
                    'token' => $token->plainTextToken,
                    'token_type' => 'Bearer',
                    'expires_at' => $expiresAt,
                ]
            ]);

        } catch (ValidationException $e) {
            Log::warning('Login validation failed', [
                'email' => $request->email,
                'ip_address' => $request->ip(),
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Login error', [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during login.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Logout user (revoke current token).
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Revoke the current token
            $request->user()->currentAccessToken()->delete();

            Log::info('User logged out successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Logout successful'
            ]);

        } catch (\Exception $e) {
            Log::error('Logout error', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during logout.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Logout from all devices (revoke all tokens).
     */
    public function logoutAll(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Revoke all tokens for the user
            $user->tokens()->delete();

            Log::info('User logged out from all devices', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Logged out from all devices successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Logout all error', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during logout.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get current user information.
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->user()->load([
                'salary',
                'uploadedDocuments' => function($query) {
                    $query->latest()->limit(5);
                },
                'salaryHistory' => function($query) {
                    $query->latest()->limit(3);
                }
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user,
                    'permissions' => $this->getUserPermissions($user),
                    'statistics' => [
                        'salary_changes_count' => $user->salaryHistory()->count(),
                        'documents_count' => $user->uploadedDocuments()->count(),
                        'account_age_days' => $user->created_at->diffInDays(now()),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get user info error', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving user information.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Refresh token (create new token and revoke current one).
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'device_name' => 'sometimes|string|max:255',
            ]);

            $user = $request->user();
            $currentToken = $request->user()->currentAccessToken();
            
            // Create new token
            $deviceName = $request->get('device_name', 'Refreshed API Token');
            $newToken = $user->createToken($deviceName, ['*'], now()->addHours(24));
            
            // Revoke current token
            $currentToken->delete();

            Log::info('Token refreshed successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'device_name' => $deviceName,
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => [
                    'token' => $newToken->plainTextToken,
                    'token_type' => 'Bearer',
                    'expires_at' => now()->addHours(24),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Token refresh error', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while refreshing token.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get user's active tokens.
     */
    public function tokens(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $tokens = $user->tokens()->get()->map(function ($token) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'abilities' => $token->abilities,
                    'last_used_at' => $token->last_used_at,
                    'expires_at' => $token->expires_at,
                    'created_at' => $token->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'tokens' => $tokens,
                    'total_count' => $tokens->count(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get tokens error', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving tokens.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Revoke a specific token.
     */
    public function revokeToken(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'token_id' => 'required|integer|exists:personal_access_tokens,id',
            ]);

            $user = $request->user();
            $token = $user->tokens()->where('id', $request->token_id)->first();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token not found or does not belong to you.',
                    'error' => 'Token not found'
                ], 404);
            }

            $tokenName = $token->name;
            $token->delete();

            Log::info('Token revoked successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'token_id' => $request->token_id,
                'token_name' => $tokenName,
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Token revoked successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Token revoke error', [
                'user_id' => $request->user()?->id,
                'token_id' => $request->token_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while revoking token.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get user permissions based on role.
     */
    private function getUserPermissions(User $user): array
    {
        $permissions = [
            'can_view_own_profile' => true,
            'can_update_own_profile' => true,
            'can_upload_documents' => true,
        ];

        // Check if user is admin
        $adminEmails = config('app.admin_emails', []);
        $isAdmin = !empty($adminEmails) 
            ? in_array($user->email, $adminEmails)
            : str_contains(strtolower($user->email), 'admin');

        if ($isAdmin) {
            $permissions = array_merge($permissions, [
                'can_view_all_users' => true,
                'can_manage_users' => true,
                'can_manage_salaries' => true,
                'can_view_admin_dashboard' => true,
                'can_manage_commissions' => true,
                'can_view_statistics' => true,
                'can_perform_bulk_operations' => true,
            ]);
        }

        return $permissions;
    }
}