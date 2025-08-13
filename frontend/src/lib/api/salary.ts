// This file is deprecated - use userApi and adminApi instead
// Keeping for backward compatibility during migration

import { apiClient } from './client';
import { userApi } from './user';
import { adminApi } from './admin';

export interface SalaryData {
  id?: number;
  name: string;
  email: string;
  salary_local_currency: string;
  salary_euros?: number;
  commission?: number;
  displayed_salary?: number;
  document_path?: string;
}

export interface SalaryResponse {
  success: boolean;
  data: SalaryData;
  message?: string;
}

export interface SalaryListResponse {
  success: boolean;
  data: SalaryData[];
  pagination?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  message?: string;
}

// Legacy API - use userApi and adminApi for new implementations
export const salaryApi = {
  async register(data: FormData): Promise<SalaryResponse> {
    return userApi.register(data);
  },

  async update(id: number, data: Partial<SalaryData>): Promise<SalaryResponse> {
    return adminApi.updateUser(id, data);
  },

  async getAll(page: number = 1, search?: string): Promise<SalaryListResponse> {
    return adminApi.getUsers({ page, search });
  },

  async getById(id: number): Promise<SalaryResponse> {
    return userApi.getById(id);
  },

  async bulkUpdate(data: Partial<SalaryData>[]): Promise<SalaryResponse> {
    return adminApi.bulkUpdateUsers(data);
  },

  async updateCommission(amount: number): Promise<SalaryResponse> {
    return adminApi.updateCommission(amount);
  }
};
