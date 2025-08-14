// Main API exports
export { ApiError, NetworkError, ValidationError } from './client';
import { userApi, userUtils } from './user';
import { adminApi, adminUtils } from './admin';
import { salaryApi } from './salary'; // Legacy - deprecated

export { userApi, userUtils, adminApi, adminUtils, salaryApi };

// Error handling utilities
export {
  getErrorInfo,
  formatValidationErrors,
  shouldRetry,
  getRetryDelay,
  useErrorHandler,
  ERROR_MESSAGES,
  ErrorSeverity,
  type ErrorInfo,
} from './errors';

// Type definitions
export type {
  ApiResponse,
  PaginatedResponse,
  PaginationMeta,
  User,
  UserWithSalary,
  Salary,
  SalaryHistory,
  UploadedDocument,
  CreateUserRequest,
  UpdateUserRequest,
  UpdateSalaryRequest,
  BulkUpdateRequest,
  SearchParams,
  DashboardStats,
  Commission,
  ErrorResponse,
  UploadProgressCallback,
  ApiClientConfig,
} from './types';

// Re-export commonly used functions
export { apiClient } from './client';

// Convenience exports for common operations
export const api = {
  // User operations
  user: userApi,
  
  // Admin operations  
  admin: adminApi,
  
  // Legacy support
  salary: salaryApi,
  
  // Utilities
  utils: {
    user: userUtils,
    admin: adminUtils,
  },
};

// Default export for convenience
export default api;