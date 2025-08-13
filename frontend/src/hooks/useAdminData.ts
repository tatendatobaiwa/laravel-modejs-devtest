import { useState, useEffect, useCallback } from 'react';
import { adminApi } from '@/lib/api/admin';
import { UserWithSalary, SearchParams, PaginatedResponse } from '@/lib/api/types';
import { getErrorInfo } from '@/lib/api/errors';

interface UseAdminDataState {
  users: UserWithSalary[];
  loading: boolean;
  error: string | null;
  pagination: {
    currentPage: number;
    totalPages: number;
    total: number;
    perPage: number;
  };
}

interface UseAdminDataOptions {
  initialPage?: number;
  initialPerPage?: number;
  autoLoad?: boolean;
}

export function useAdminData({
  initialPage = 1,
  initialPerPage = 20,
  autoLoad = true,
}: UseAdminDataOptions = {}) {
  const [state, setState] = useState<UseAdminDataState>({
    users: [],
    loading: false,
    error: null,
    pagination: {
      currentPage: initialPage,
      totalPages: 1,
      total: 0,
      perPage: initialPerPage,
    },
  });

  const [searchParams, setSearchParams] = useState<SearchParams>({
    page: initialPage,
    per_page: initialPerPage,
  });

  const loadUsers = useCallback(async (params: SearchParams = searchParams) => {
    setState(prev => ({ ...prev, loading: true, error: null }));

    try {
      const response: PaginatedResponse<UserWithSalary> = await adminApi.getUsers(params);
      
      setState(prev => ({
        ...prev,
        users: response.data,
        loading: false,
        pagination: {
          currentPage: response.pagination.current_page,
          totalPages: response.pagination.last_page,
          total: response.pagination.total,
          perPage: response.pagination.per_page,
        },
      }));
    } catch (error) {
      const errorInfo = getErrorInfo(error as Error);
      setState(prev => ({
        ...prev,
        loading: false,
        error: errorInfo.message,
        users: [],
      }));
    }
  }, [searchParams]);

  const search = useCallback((query: string) => {
    const newParams = {
      ...searchParams,
      search: query || undefined,
      page: 1, // Reset to first page on search
    };
    setSearchParams(newParams);
    loadUsers(newParams);
  }, [searchParams, loadUsers]);

  const filter = useCallback((filters: Record<string, string>) => {
    const newParams = {
      ...searchParams,
      filter_by: Object.keys(filters).length > 0 ? filters : undefined,
      page: 1, // Reset to first page on filter
    };
    setSearchParams(newParams);
    loadUsers(newParams);
  }, [searchParams, loadUsers]);

  const sort = useCallback((sortBy: string, direction: 'asc' | 'desc' = 'asc') => {
    const newParams = {
      ...searchParams,
      sort_by: sortBy,
      sort_direction: direction,
    };
    setSearchParams(newParams);
    loadUsers(newParams);
  }, [searchParams, loadUsers]);

  const changePage = useCallback((page: number) => {
    const newParams = {
      ...searchParams,
      page,
    };
    setSearchParams(newParams);
    loadUsers(newParams);
  }, [searchParams, loadUsers]);

  const changePerPage = useCallback((perPage: number) => {
    const newParams = {
      ...searchParams,
      per_page: perPage,
      page: 1, // Reset to first page when changing page size
    };
    setSearchParams(newParams);
    loadUsers(newParams);
  }, [searchParams, loadUsers]);

  const refresh = useCallback(() => {
    loadUsers(searchParams);
  }, [loadUsers, searchParams]);

  const updateUser = useCallback(async (userId: number, userData: Partial<UserWithSalary>) => {
    try {
      const response = await adminApi.updateUser(userId, userData, true);
      
      if (response.success) {
        // Update the user in the local state for optimistic updates
        setState(prev => ({
          ...prev,
          users: prev.users.map(user => 
            user.id === userId ? { ...user, ...response.data } : user
          ),
        }));
        
        return response.data;
      }
    } catch (error) {
      // Refresh data on error to ensure consistency
      refresh();
      throw error;
    }
  }, [refresh]);

  const deleteUser = useCallback(async (userId: number, reason?: string) => {
    try {
      await adminApi.deleteUser(userId, reason);
      
      // Remove user from local state
      setState(prev => ({
        ...prev,
        users: prev.users.filter(user => user.id !== userId),
        pagination: {
          ...prev.pagination,
          total: prev.pagination.total - 1,
        },
      }));
    } catch (error) {
      refresh();
      throw error;
    }
  }, [refresh]);

  // Auto-load on mount
  useEffect(() => {
    if (autoLoad) {
      loadUsers();
    }
  }, []);

  return {
    ...state,
    searchParams,
    actions: {
      loadUsers,
      search,
      filter,
      sort,
      changePage,
      changePerPage,
      refresh,
      updateUser,
      deleteUser,
    },
  };
}