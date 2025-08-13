// Common API response structure
export interface ApiResponse<T = any> {
  success: boolean;
  data: T;
  message?: string;
  errors?: Record<string, string[]>;
}

// Pagination metadata
export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
}

// Paginated response structure
export interface PaginatedResponse<T> extends ApiResponse<T[]> {
  pagination: PaginationMeta;
}

// User related types
export interface User {
  id: number;
  name: string;
  email: string;
  email_verified_at: string | null;
  created_at: string;
  updated_at: string;
}

// Salary related types
export interface Salary {
  id: number;
  user_id: number;
  salary_local_currency: number;
  local_currency_code: string;
  salary_euros: number;
  commission: number;
  displayed_salary: number;
  effective_date: string;
  notes: string | null;
  created_at: string;
  updated_at: string;
}

// Combined user with salary data
export interface UserWithSalary extends User {
  current_salary: Salary | null;
  salary_histories: SalaryHistory[];
  uploaded_documents: UploadedDocument[];
}

// Salary history for audit trail
export interface SalaryHistory {
  id: number;
  user_id: number;
  old_values: Record<string, any>;
  new_values: Record<string, any>;
  changed_by: number;
  change_reason: string | null;
  created_at: string;
}

// File upload related types
export interface UploadedDocument {
  id: number;
  user_id: number;
  filename: string;
  original_filename: string;
  file_path: string;
  file_size: number;
  mime_type: string;
  created_at: string;
  updated_at: string;
}

// Request types for user operations
export interface CreateUserRequest {
  name: string;
  email: string;
  salary_local_currency: number;
  local_currency_code?: string;
  document?: File;
}

export interface UpdateUserRequest {
  name?: string;
  email?: string;
  salary_local_currency?: number;
  local_currency_code?: string;
}

// Request types for salary operations
export interface UpdateSalaryRequest {
  salary_local_currency?: number;
  salary_euros?: number;
  commission?: number;
  notes?: string;
}

// Bulk operations
export interface BulkUpdateRequest {
  users: Array<{
    id: number;
    salary_local_currency?: number;
    salary_euros?: number;
    commission?: number;
  }>;
}

// Search and filter parameters
export interface SearchParams {
  search?: string;
  page?: number;
  per_page?: number;
  sort_by?: string;
  sort_direction?: 'asc' | 'desc';
  filter_by?: Record<string, string>;
}

// Admin dashboard statistics
export interface DashboardStats {
  total_users: number;
  total_salaries: number;
  average_salary: number;
  total_commission: number;
  recent_registrations: number;
  recent_updates: number;
}

// Commission settings
export interface Commission {
  id: number;
  amount: number;
  effective_date: string;
  created_at: string;
  updated_at: string;
}

// Error response structure
export interface ErrorResponse {
  success: false;
  message: string;
  errors?: Record<string, string[]>;
  code?: string;
}

// File upload progress callback
export type UploadProgressCallback = (progress: number) => void;

// API client configuration
export interface ApiClientConfig {
  baseUrl: string;
  timeout?: number;
  retries?: number;
  retryDelay?: number;
}