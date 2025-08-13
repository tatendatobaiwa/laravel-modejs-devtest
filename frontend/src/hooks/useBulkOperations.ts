import { useState, useCallback } from 'react';
import { adminApi, adminUtils } from '@/lib/api/admin';
import { UserWithSalary, UpdateSalaryRequest } from '@/lib/api/types';
import { getErrorInfo } from '@/lib/api/errors';

interface BulkOperationState {
  isProcessing: boolean;
  progress: number;
  results: {
    updated: number;
    failed: number;
    errors: any[];
  } | null;
  error: string | null;
}

interface BulkUpdateItem {
  id: number;
  data: Partial<UserWithSalary>;
}

interface BulkSalaryUpdateItem {
  user_id: number;
  salary_local_currency?: number;
  salary_euros?: number;
  commission?: number;
}

export function useBulkOperations() {
  const [state, setState] = useState<BulkOperationState>({
    isProcessing: false,
    progress: 0,
    results: null,
    error: null,
  });

  const reset = useCallback(() => {
    setState({
      isProcessing: false,
      progress: 0,
      results: null,
      error: null,
    });
  }, []);

  const bulkUpdateUsers = useCallback(async (updates: BulkUpdateItem[]) => {
    // Validate input
    const validation = adminUtils.validateBulkUpdateData(updates);
    if (!validation.valid) {
      setState(prev => ({
        ...prev,
        error: validation.errors.join(', '),
      }));
      return;
    }

    setState(prev => ({
      ...prev,
      isProcessing: true,
      progress: 0,
      error: null,
      results: null,
    }));

    try {
      const bulkData = updates.map(update => ({
        id: update.id,
        ...update.data,
      }));

      const response = await adminApi.bulkUpdateUsers(
        bulkData,
        (progress) => {
          setState(prev => ({ ...prev, progress }));
        }
      );

      if (response.success) {
        setState(prev => ({
          ...prev,
          isProcessing: false,
          progress: 100,
          results: response.data,
        }));
        
        return response.data;
      } else {
        throw new Error(response.message || 'Bulk update failed');
      }
    } catch (error) {
      const errorInfo = getErrorInfo(error as Error);
      setState(prev => ({
        ...prev,
        isProcessing: false,
        progress: 0,
        error: errorInfo.message,
      }));
      throw error;
    }
  }, []);

  const bulkUpdateSalaries = useCallback(async (updates: BulkSalaryUpdateItem[]) => {
    // Validate input
    const validation = adminUtils.validateBulkUpdateData(updates);
    if (!validation.valid) {
      setState(prev => ({
        ...prev,
        error: validation.errors.join(', '),
      }));
      return;
    }

    setState(prev => ({
      ...prev,
      isProcessing: true,
      progress: 0,
      error: null,
      results: null,
    }));

    try {
      const response = await adminApi.bulkUpdateSalaries(
        updates,
        (progress) => {
          setState(prev => ({ ...prev, progress }));
        }
      );

      if (response.success) {
        setState(prev => ({
          ...prev,
          isProcessing: false,
          progress: 100,
          results: response.data,
        }));
        
        return response.data;
      } else {
        throw new Error(response.message || 'Bulk salary update failed');
      }
    } catch (error) {
      const errorInfo = getErrorInfo(error as Error);
      setState(prev => ({
        ...prev,
        isProcessing: false,
        progress: 0,
        error: errorInfo.message,
      }));
      throw error;
    }
  }, []);

  const importUsers = useCallback(async (file: File) => {
    setState(prev => ({
      ...prev,
      isProcessing: true,
      progress: 0,
      error: null,
      results: null,
    }));

    try {
      const response = await adminApi.importUsers(
        file,
        (progress) => {
          setState(prev => ({ ...prev, progress }));
        }
      );

      if (response.success) {
        setState(prev => ({
          ...prev,
          isProcessing: false,
          progress: 100,
          results: {
            updated: response.data.imported,
            failed: response.data.failed,
            errors: response.data.errors,
          },
        }));
        
        return response.data;
      } else {
        throw new Error(response.message || 'Import failed');
      }
    } catch (error) {
      const errorInfo = getErrorInfo(error as Error);
      setState(prev => ({
        ...prev,
        isProcessing: false,
        progress: 0,
        error: errorInfo.message,
      }));
      throw error;
    }
  }, []);

  const exportUsers = useCallback(async (
    format: 'csv' | 'excel' = 'csv',
    filters?: any
  ) => {
    setState(prev => ({
      ...prev,
      isProcessing: true,
      progress: 50, // Simulate progress for export
      error: null,
    }));

    try {
      const response = await adminApi.exportUsers(format, filters);
      
      if (response.success) {
        setState(prev => ({
          ...prev,
          isProcessing: false,
          progress: 100,
        }));
        
        // Trigger download
        const link = document.createElement('a');
        link.href = response.data.download_url;
        link.download = adminUtils.generateExportFilename('users', format);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        return response.data;
      } else {
        throw new Error(response.message || 'Export failed');
      }
    } catch (error) {
      const errorInfo = getErrorInfo(error as Error);
      setState(prev => ({
        ...prev,
        isProcessing: false,
        progress: 0,
        error: errorInfo.message,
      }));
      throw error;
    }
  }, []);

  const getResultsMessage = useCallback(() => {
    if (!state.results) return '';
    return adminUtils.formatBulkResults(state.results);
  }, [state.results]);

  return {
    ...state,
    actions: {
      bulkUpdateUsers,
      bulkUpdateSalaries,
      importUsers,
      exportUsers,
      reset,
    },
    getResultsMessage,
  };
}