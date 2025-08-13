import { apiClient } from './client';
import {
  ApiResponse,
  PaginatedResponse,
  User,
  UserWithSalary,
  CreateUserRequest,
  UpdateUserRequest,
  SearchParams,
  UploadedDocument,
  UploadProgressCallback,
} from './types';

/**
 * User operations API
 * Handles user registration, profile management, and file uploads
 */
export const userApi = {
  /**
   * Register a new user with salary information
   * Supports file upload with progress tracking
   */
  async register(
    userData: CreateUserRequest,
    onProgress?: UploadProgressCallback
  ): Promise<ApiResponse<UserWithSalary>> {
    const formData = new FormData();
    
    // Add user data to form
    formData.append('name', userData.name);
    formData.append('email', userData.email);
    formData.append('salary_local_currency', userData.salary_local_currency.toString());
    
    if (userData.local_currency_code) {
      formData.append('local_currency_code', userData.local_currency_code);
    }
    
    // Add file if provided
    if (userData.document) {
      formData.append('document', userData.document);
    }

    // Use file upload method if file is present and progress callback is provided
    if (userData.document && onProgress) {
      return apiClient.uploadFile<ApiResponse<UserWithSalary>>(
        '/users',
        userData.document,
        {
          name: userData.name,
          email: userData.email,
          salary_local_currency: userData.salary_local_currency.toString(),
          local_currency_code: userData.local_currency_code || 'EUR',
        },
        onProgress
      );
    }

    return apiClient.post<ApiResponse<UserWithSalary>>('/users', formData);
  },

  /**
   * Update user information with optimistic updates support
   */
  async update(
    id: number,
    userData: UpdateUserRequest,
    optimistic: boolean = false
  ): Promise<ApiResponse<UserWithSalary>> {
    const endpoint = `/users/${id}`;
    
    if (optimistic) {
      // For optimistic updates, we might want to implement a queue system
      // For now, just make the regular API call
      console.log('Optimistic update requested for user:', id);
    }

    return apiClient.put<ApiResponse<UserWithSalary>>(endpoint, userData);
  },

  /**
   * Get user by ID with full salary information
   */
  async getById(id: number): Promise<ApiResponse<UserWithSalary>> {
    return apiClient.get<ApiResponse<UserWithSalary>>(`/users/${id}`);
  },

  /**
   * Get current user profile (requires authentication)
   */
  async getProfile(): Promise<ApiResponse<UserWithSalary>> {
    return apiClient.get<ApiResponse<UserWithSalary>>('/user/profile');
  },

  /**
   * Update current user profile
   */
  async updateProfile(userData: UpdateUserRequest): Promise<ApiResponse<UserWithSalary>> {
    return apiClient.put<ApiResponse<UserWithSalary>>('/user/profile', userData);
  },

  /**
   * Search and filter users with advanced parameters
   */
  async search(params: SearchParams): Promise<PaginatedResponse<UserWithSalary>> {
    const searchParams: Record<string, string> = {};
    
    if (params.search) searchParams.search = params.search;
    if (params.page) searchParams.page = params.page.toString();
    if (params.per_page) searchParams.per_page = params.per_page.toString();
    if (params.sort_by) searchParams.sort_by = params.sort_by;
    if (params.sort_direction) searchParams.sort_direction = params.sort_direction;
    
    // Add filter parameters
    if (params.filter_by) {
      Object.entries(params.filter_by).forEach(([key, value]) => {
        searchParams[`filter[${key}]`] = value;
      });
    }

    return apiClient.get<PaginatedResponse<UserWithSalary>>('/users/search', searchParams);
  },

  /**
   * Upload document for a user
   */
  async uploadDocument(
    userId: number,
    file: File,
    onProgress?: UploadProgressCallback
  ): Promise<ApiResponse<UploadedDocument>> {
    if (onProgress) {
      return apiClient.uploadFile<ApiResponse<UploadedDocument>>(
        `/users/${userId}/documents`,
        file,
        {},
        onProgress
      );
    }

    const formData = new FormData();
    formData.append('document', file);
    
    return apiClient.post<ApiResponse<UploadedDocument>>(`/users/${userId}/documents`, formData);
  },

  /**
   * Get user documents
   */
  async getDocuments(userId: number): Promise<ApiResponse<UploadedDocument[]>> {
    return apiClient.get<ApiResponse<UploadedDocument[]>>(`/users/${userId}/documents`);
  },

  /**
   * Delete user document
   */
  async deleteDocument(userId: number, documentId: number): Promise<ApiResponse<void>> {
    return apiClient.delete<ApiResponse<void>>(`/users/${userId}/documents/${documentId}`);
  },

  /**
   * Check if email is available (for real-time validation)
   */
  async checkEmailAvailability(email: string, excludeUserId?: number): Promise<ApiResponse<{ available: boolean }>> {
    const params: Record<string, string> = { email };
    if (excludeUserId) {
      params.exclude_user_id = excludeUserId.toString();
    }
    
    return apiClient.get<ApiResponse<{ available: boolean }>>('/users/check-email', params);
  },

  /**
   * Get user salary history
   */
  async getSalaryHistory(
    userId: number,
    page: number = 1,
    perPage: number = 20
  ): Promise<PaginatedResponse<any>> {
    return apiClient.get<PaginatedResponse<any>>(`/users/${userId}/salary-history`, {
      page: page.toString(),
      per_page: perPage.toString(),
    });
  },

  /**
   * Export user data (GDPR compliance)
   */
  async exportUserData(userId: number): Promise<ApiResponse<{ download_url: string }>> {
    return apiClient.get<ApiResponse<{ download_url: string }>>(`/users/${userId}/export`);
  },

  /**
   * Delete user account (soft delete)
   */
  async deleteAccount(userId: number, reason?: string): Promise<ApiResponse<void>> {
    const data = reason ? { reason } : {};
    return apiClient.delete<ApiResponse<void>>(`/users/${userId}`, data);
  },
};

/**
 * Utility functions for user operations
 */
export const userUtils = {
  /**
   * Format user display name
   */
  formatDisplayName(user: User): string {
    return user.name || user.email.split('@')[0];
  },

  /**
   * Get user initials for avatar
   */
  getUserInitials(user: User): string {
    if (user.name) {
      return user.name
        .split(' ')
        .map(part => part.charAt(0).toUpperCase())
        .slice(0, 2)
        .join('');
    }
    return user.email.charAt(0).toUpperCase();
  },

  /**
   * Check if user has complete profile
   */
  hasCompleteProfile(user: UserWithSalary): boolean {
    return !!(
      user.name &&
      user.email &&
      user.current_salary &&
      user.current_salary.salary_local_currency > 0
    );
  },

  /**
   * Calculate profile completion percentage
   */
  getProfileCompletionPercentage(user: UserWithSalary): number {
    let completed = 0;
    const total = 5;

    if (user.name) completed++;
    if (user.email) completed++;
    if (user.current_salary?.salary_local_currency) completed++;
    if (user.current_salary?.local_currency_code) completed++;
    if (user.uploaded_documents?.length > 0) completed++;

    return Math.round((completed / total) * 100);
  },

  /**
   * Validate email format
   */
  isValidEmail(email: string): boolean {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  },

  /**
   * Validate salary amount
   */
  isValidSalary(salary: number): boolean {
    return salary > 0 && salary <= 10000000; // Max 10M
  },

  /**
   * Format salary for display
   */
  formatSalary(amount: number, currency: string = 'EUR'): string {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: currency,
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(amount);
  },
};