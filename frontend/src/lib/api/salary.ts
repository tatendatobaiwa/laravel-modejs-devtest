import { apiClient } from './client';

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

export const salaryApi = {
  async register(data: FormData): Promise<SalaryResponse> {
    return apiClient.post<SalaryResponse>('/users', data);
  },

  async update(id: number, data: Partial<SalaryData>): Promise<SalaryResponse> {
    return apiClient.put<SalaryResponse>(`/users/${id}`, data);
  },

  async getAll(page: number = 1, search?: string): Promise<SalaryListResponse> {
    const params = new URLSearchParams();
    params.append('page', page.toString());
    if (search) {
      params.append('search', search);
    }
    
    return apiClient.get<SalaryListResponse>(`/users?${params.toString()}`);
  },

  async getById(id: number): Promise<SalaryResponse> {
    return apiClient.get<SalaryResponse>(`/users/${id}`);
  },

  async bulkUpdate(data: Partial<SalaryData>[]): Promise<SalaryResponse> {
    return apiClient.post<SalaryResponse>('/users/bulk-update', { users: data });
  },

  async updateCommission(amount: number): Promise<SalaryResponse> {
    return apiClient.put<SalaryResponse>('/commission', { amount });
  }
};
