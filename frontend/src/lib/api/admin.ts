import { apiClient } from './client';
import {
  ApiResponse,
  PaginatedResponse,
  UserWithSalary,
  UpdateSalaryRequest,
  BulkUpdateRequest,
  SearchParams,
  DashboardStats,
  Commission,
  SalaryHistory,
} from './types';

/**
 * Admin operations API
 * Handles administrative functions like user management, salary updates, and dashboard data
 */
export const adminApi = {
  /**
   * Get all users with advanced filtering and pagination
   */
  async getUsers(params: SearchParams = {}): Promise<PaginatedResponse<UserWithSalary>> {
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

    return apiClient.get<PaginatedResponse<UserWithSalary>>('/admin/users', searchParams);
  },

  /**
   * Get user by ID (admin view with full details)
   */
  async getUser(id: number): Promise<ApiResponse<UserWithSalary>> {
    return apiClient.get<ApiResponse<UserWithSalary>>(`/admin/users/${id}`);
  },

  /**
   * Update user information (admin only)
   */
  async updateUser(
    id: number,
    userData: Partial<UserWithSalary>,
    realTimeUpdate: boolean = false
  ): Promise<ApiResponse<UserWithSalary>> {
    const endpoint = `/admin/users/${id}`;
    
    if (realTimeUpdate) {
      // For real-time updates, we might want to use WebSockets or Server-Sent Events
      // For now, just make the regular API call with a flag
      console.log('Real-time update requested for user:', id);
    }

    return apiClient.put<ApiResponse<UserWithSalary>>(endpoint, userData);
  },

  /**
   * Update user salary with automatic calculations
   */
  async updateSalary(
    userId: number,
    salaryData: UpdateSalaryRequest,
    realTimeUpdate: boolean = false
  ): Promise<ApiResponse<UserWithSalary>> {
    const endpoint = `/admin/users/${userId}/salary`;
    
    if (realTimeUpdate) {
      console.log('Real-time salary update requested for user:', userId);
    }

    return apiClient.put<ApiResponse<UserWithSalary>>(endpoint, salaryData);
  },

  /**
   * Bulk update users with progress tracking
   */
  async bulkUpdateUsers(
    updates: BulkUpdateRequest['users'],
    onProgress?: (progress: number) => void
  ): Promise<ApiResponse<{ updated: number; failed: number; errors: any[] }>> {
    // For progress tracking, we'll simulate it since the API call is atomic
    if (onProgress) {
      onProgress(0);
    }

    try {
      const result = await apiClient.post<ApiResponse<{ updated: number; failed: number; errors: any[] }>>(
        '/admin/users/bulk-update',
        { users: updates }
      );

      if (onProgress) {
        onProgress(100);
      }

      return result;
    } catch (error) {
      if (onProgress) {
        onProgress(0);
      }
      throw error;
    }
  },

  /**
   * Bulk update salaries with progress tracking
   */
  async bulkUpdateSalaries(
    updates: Array<{
      user_id: number;
      salary_local_currency?: number;
      salary_euros?: number;
      commission?: number;
    }>,
    onProgress?: (progress: number) => void
  ): Promise<ApiResponse<{ updated: number; failed: number; errors: any[] }>> {
    if (onProgress) {
      onProgress(0);
    }

    try {
      const result = await apiClient.post<ApiResponse<{ updated: number; failed: number; errors: any[] }>>(
        '/admin/salaries/bulk-update',
        { salaries: updates }
      );

      if (onProgress) {
        onProgress(100);
      }

      return result;
    } catch (error) {
      if (onProgress) {
        onProgress(0);
      }
      throw error;
    }
  },

  /**
   * Get dashboard statistics with caching
   */
  async getDashboardStats(useCache: boolean = true): Promise<ApiResponse<DashboardStats>> {
    const params = useCache ? { cache: 'true' } : {};
    return apiClient.get<ApiResponse<DashboardStats>>('/admin/dashboard/stats', params);
  },

  /**
   * Get recent user activities
   */
  async getRecentActivities(
    limit: number = 20,
    useCache: boolean = true
  ): Promise<ApiResponse<any[]>> {
    const params: Record<string, string> = {
      limit: limit.toString(),
    };
    
    if (useCache) {
      params.cache = 'true';
    }

    return apiClient.get<ApiResponse<any[]>>('/admin/dashboard/activities', params);
  },

  /**
   * Get salary statistics and trends
   */
  async getSalaryStats(
    period: 'week' | 'month' | 'quarter' | 'year' = 'month',
    useCache: boolean = true
  ): Promise<ApiResponse<any>> {
    const params: Record<string, string> = { period };
    
    if (useCache) {
      params.cache = 'true';
    }

    return apiClient.get<ApiResponse<any>>('/admin/dashboard/salary-stats', params);
  },

  /**
   * Update global commission settings
   */
  async updateCommission(amount: number, effectiveDate?: string): Promise<ApiResponse<Commission>> {
    const data: any = { amount };
    if (effectiveDate) {
      data.effective_date = effectiveDate;
    }

    return apiClient.put<ApiResponse<Commission>>('/admin/commission', data);
  },

  /**
   * Get commission history
   */
  async getCommissionHistory(
    page: number = 1,
    perPage: number = 20
  ): Promise<PaginatedResponse<Commission>> {
    return apiClient.get<PaginatedResponse<Commission>>('/admin/commission/history', {
      page: page.toString(),
      per_page: perPage.toString(),
    });
  },

  /**
   * Get user salary history (admin view)
   */
  async getUserSalaryHistory(
    userId: number,
    page: number = 1,
    perPage: number = 20
  ): Promise<PaginatedResponse<SalaryHistory>> {
    return apiClient.get<PaginatedResponse<SalaryHistory>>(`/admin/users/${userId}/salary-history`, {
      page: page.toString(),
      per_page: perPage.toString(),
    });
  },

  /**
   * Export users data (CSV/Excel)
   */
  async exportUsers(
    format: 'csv' | 'excel' = 'csv',
    filters?: SearchParams
  ): Promise<ApiResponse<{ download_url: string }>> {
    const params: Record<string, string> = { format };
    
    if (filters) {
      if (filters.search) params.search = filters.search;
      if (filters.filter_by) {
        Object.entries(filters.filter_by).forEach(([key, value]) => {
          params[`filter[${key}]`] = value;
        });
      }
    }

    return apiClient.get<ApiResponse<{ download_url: string }>>('/admin/users/export', params);
  },

  /**
   * Import users from file
   */
  async importUsers(
    file: File,
    onProgress?: (progress: number) => void
  ): Promise<ApiResponse<{ imported: number; failed: number; errors: any[] }>> {
    if (onProgress) {
      return apiClient.uploadFile<ApiResponse<{ imported: number; failed: number; errors: any[] }>>(
        '/admin/users/import',
        file,
        {},
        onProgress
      );
    }

    const formData = new FormData();
    formData.append('file', file);
    
    return apiClient.post<ApiResponse<{ imported: number; failed: number; errors: any[] }>>(
      '/admin/users/import',
      formData
    );
  },

  /**
   * Delete user (admin only)
   */
  async deleteUser(userId: number, reason?: string): Promise<ApiResponse<void>> {
    const data = reason ? { reason } : {};
    return apiClient.delete<ApiResponse<void>>(`/admin/users/${userId}`, data);
  },

  /**
   * Restore deleted user
   */
  async restoreUser(userId: number): Promise<ApiResponse<UserWithSalary>> {
    return apiClient.post<ApiResponse<UserWithSalary>>(`/admin/users/${userId}/restore`);
  },

  /**
   * Get system logs
   */
  async getSystemLogs(
    level: 'error' | 'warning' | 'info' | 'debug' = 'error',
    page: number = 1,
    perPage: number = 50
  ): Promise<PaginatedResponse<any>> {
    return apiClient.get<PaginatedResponse<any>>('/admin/logs', {
      level,
      page: page.toString(),
      per_page: perPage.toString(),
    });
  },

  /**
   * Get audit trail
   */
  async getAuditTrail(
    userId?: number,
    action?: string,
    page: number = 1,
    perPage: number = 50
  ): Promise<PaginatedResponse<any>> {
    const params: Record<string, string> = {
      page: page.toString(),
      per_page: perPage.toString(),
    };

    if (userId) params.user_id = userId.toString();
    if (action) params.action = action;

    return apiClient.get<PaginatedResponse<any>>('/admin/audit-trail', params);
  },

  /**
   * Update system settings
   */
  async updateSettings(settings: Record<string, any>): Promise<ApiResponse<Record<string, any>>> {
    return apiClient.put<ApiResponse<Record<string, any>>>('/admin/settings', settings);
  },

  /**
   * Get system settings
   */
  async getSettings(): Promise<ApiResponse<Record<string, any>>> {
    return apiClient.get<ApiResponse<Record<string, any>>>('/admin/settings');
  },
};

/**
 * Admin utility functions
 */
export const adminUtils = {
  /**
   * Calculate bulk update progress
   */
  calculateBulkProgress(completed: number, total: number): number {
    return Math.round((completed / total) * 100);
  },

  /**
   * Format bulk operation results
   */
  formatBulkResults(result: { updated: number; failed: number; errors: any[] }): string {
    const { updated, failed, errors } = result;
    let message = `Updated ${updated} records`;
    
    if (failed > 0) {
      message += `, ${failed} failed`;
    }
    
    if (errors.length > 0) {
      message += `. First error: ${errors[0].message || 'Unknown error'}`;
    }
    
    return message;
  },

  /**
   * Validate bulk update data
   */
  validateBulkUpdateData(updates: any[]): { valid: boolean; errors: string[] } {
    const errors: string[] = [];
    
    if (!Array.isArray(updates)) {
      errors.push('Updates must be an array');
      return { valid: false, errors };
    }
    
    if (updates.length === 0) {
      errors.push('No updates provided');
      return { valid: false, errors };
    }
    
    if (updates.length > 1000) {
      errors.push('Too many updates (max 1000)');
      return { valid: false, errors };
    }
    
    updates.forEach((update, index) => {
      if (!update.id && !update.user_id) {
        errors.push(`Update ${index + 1}: Missing ID`);
      }
      
      if (update.salary_local_currency && (update.salary_local_currency <= 0 || update.salary_local_currency > 10000000)) {
        errors.push(`Update ${index + 1}: Invalid salary amount`);
      }
    });
    
    return { valid: errors.length === 0, errors };
  },

  /**
   * Generate export filename
   */
  generateExportFilename(type: string, format: string): string {
    const timestamp = new Date().toISOString().split('T')[0];
    return `${type}_export_${timestamp}.${format}`;
  },

  /**
   * Format dashboard stats for display
   */
  formatDashboardStats(stats: DashboardStats): Record<string, string> {
    return {
      totalUsers: stats.total_users.toLocaleString(),
      totalSalaries: stats.total_salaries.toLocaleString(),
      averageSalary: new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'EUR',
        minimumFractionDigits: 0,
      }).format(stats.average_salary),
      totalCommission: new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'EUR',
        minimumFractionDigits: 0,
      }).format(stats.total_commission),
      recentRegistrations: stats.recent_registrations.toString(),
      recentUpdates: stats.recent_updates.toString(),
    };
  },

  /**
   * Check if user has admin permissions
   */
  hasAdminPermissions(user: any): boolean {
    return user?.role === 'admin' || user?.permissions?.includes('admin');
  },

  /**
   * Get cache key for dashboard data
   */
  getCacheKey(type: string, params?: Record<string, any>): string {
    const baseKey = `admin_${type}`;
    if (!params) return baseKey;
    
    const paramString = Object.entries(params)
      .sort(([a], [b]) => a.localeCompare(b))
      .map(([key, value]) => `${key}:${value}`)
      .join('_');
    
    return `${baseKey}_${paramString}`;
  },
};