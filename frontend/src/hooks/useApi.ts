import { useState, useCallback, useRef, useEffect } from 'react';
import { 
  ApiError, 
  NetworkError, 
  ValidationError,
  getErrorInfo,
  type ErrorInfo,
  type UploadProgressCallback 
} from '../lib/api';

// Hook state interface
interface ApiState<T> {
  data: T | null;
  loading: boolean;
  error: ErrorInfo | null;
  progress?: number;
}

// Hook options
interface UseApiOptions {
  immediate?: boolean;
  retries?: number;
  retryDelay?: number;
  onSuccess?: (data: any) => void;
  onError?: (error: ErrorInfo) => void;
}

/**
 * Custom hook for API operations with loading states, error handling, and retry logic
 */
export function useApi<T = any>(
  apiFunction: (...args: any[]) => Promise<T>,
  options: UseApiOptions = {}
) {
  const {
    immediate = false,
    retries = 0,
    retryDelay = 1000,
    onSuccess,
    onError,
  } = options;

  const [state, setState] = useState<ApiState<T>>({
    data: null,
    loading: false,
    error: null,
  });

  const retryCountRef = useRef(0);
  const abortControllerRef = useRef<AbortController | null>(null);

  // Execute the API function
  const execute = useCallback(async (...args: any[]): Promise<T | null> => {
    // Cancel any ongoing request
    if (abortControllerRef.current) {
      abortControllerRef.current.abort();
    }

    // Create new abort controller
    abortControllerRef.current = new AbortController();

    setState(prev => ({
      ...prev,
      loading: true,
      error: null,
      progress: undefined,
    }));

    try {
      const result = await apiFunction(...args);
      
      setState(prev => ({
        ...prev,
        data: result,
        loading: false,
        error: null,
      }));

      retryCountRef.current = 0;
      onSuccess?.(result);
      return result;
    } catch (error) {
      const errorInfo = getErrorInfo(error as Error);
      
      // Check if we should retry
      if (retryCountRef.current < retries && errorInfo.retryable) {
        retryCountRef.current++;
        
        // Wait before retrying
        await new Promise(resolve => 
          setTimeout(resolve, retryDelay * retryCountRef.current)
        );
        
        // Retry the request
        return execute(...args);
      }

      setState(prev => ({
        ...prev,
        loading: false,
        error: errorInfo,
      }));

      onError?.(errorInfo);
      return null;
    }
  }, [apiFunction, retries, retryDelay, onSuccess, onError]);

  // Execute immediately if requested
  useEffect(() => {
    if (immediate) {
      execute();
    }
  }, [immediate, execute]);

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
      }
    };
  }, []);

  // Reset state
  const reset = useCallback(() => {
    setState({
      data: null,
      loading: false,
      error: null,
    });
    retryCountRef.current = 0;
  }, []);

  // Cancel ongoing request
  const cancel = useCallback(() => {
    if (abortControllerRef.current) {
      abortControllerRef.current.abort();
    }
    setState(prev => ({
      ...prev,
      loading: false,
    }));
  }, []);

  return {
    ...state,
    execute,
    reset,
    cancel,
    retry: () => execute(),
  };
}

/**
 * Hook for file upload operations with progress tracking
 */
export function useFileUpload<T = any>(
  uploadFunction: (file: File, onProgress?: UploadProgressCallback, ...args: any[]) => Promise<T>,
  options: UseApiOptions = {}
) {
  const [state, setState] = useState<ApiState<T> & { progress: number }>({
    data: null,
    loading: false,
    error: null,
    progress: 0,
  });

  const { onSuccess, onError } = options;

  const upload = useCallback(async (file: File, ...args: any[]): Promise<T | null> => {
    setState({
      data: null,
      loading: true,
      error: null,
      progress: 0,
    });

    const onProgress: UploadProgressCallback = (progress) => {
      setState(prev => ({
        ...prev,
        progress,
      }));
    };

    try {
      const result = await uploadFunction(file, onProgress, ...args);
      
      setState({
        data: result,
        loading: false,
        error: null,
        progress: 100,
      });

      onSuccess?.(result);
      return result;
    } catch (error) {
      const errorInfo = getErrorInfo(error as Error);
      
      setState(prev => ({
        ...prev,
        loading: false,
        error: errorInfo,
        progress: 0,
      }));

      onError?.(errorInfo);
      return null;
    }
  }, [uploadFunction, onSuccess, onError]);

  const reset = useCallback(() => {
    setState({
      data: null,
      loading: false,
      error: null,
      progress: 0,
    });
  }, []);

  return {
    ...state,
    upload,
    reset,
  };
}

/**
 * Hook for paginated data with search and filtering
 */
export function usePaginatedApi<T = any>(
  apiFunction: (params: any) => Promise<{ data: T[]; pagination: any }>,
  initialParams: any = {}
) {
  const [state, setState] = useState<{
    data: T[];
    pagination: any;
    loading: boolean;
    error: ErrorInfo | null;
    params: any;
  }>({
    data: [],
    pagination: null,
    loading: false,
    error: null,
    params: { page: 1, per_page: 20, ...initialParams },
  });

  const fetchData = useCallback(async (newParams?: any) => {
    const params = newParams ? { ...state.params, ...newParams } : state.params;
    
    setState(prev => ({
      ...prev,
      loading: true,
      error: null,
      params,
    }));

    try {
      const result = await apiFunction(params);
      
      setState(prev => ({
        ...prev,
        data: result.data,
        pagination: result.pagination,
        loading: false,
      }));
    } catch (error) {
      const errorInfo = getErrorInfo(error as Error);
      
      setState(prev => ({
        ...prev,
        loading: false,
        error: errorInfo,
      }));
    }
  }, [apiFunction, state.params]);

  // Load data on mount
  useEffect(() => {
    fetchData();
  }, []);

  const setPage = useCallback((page: number) => {
    fetchData({ page });
  }, [fetchData]);

  const setSearch = useCallback((search: string) => {
    fetchData({ search, page: 1 });
  }, [fetchData]);

  const setFilters = useCallback((filters: Record<string, any>) => {
    fetchData({ filter_by: filters, page: 1 });
  }, [fetchData]);

  const setSorting = useCallback((sortBy: string, sortDirection: 'asc' | 'desc') => {
    fetchData({ sort_by: sortBy, sort_direction: sortDirection, page: 1 });
  }, [fetchData]);

  const refresh = useCallback(() => {
    fetchData();
  }, [fetchData]);

  return {
    ...state,
    setPage,
    setSearch,
    setFilters,
    setSorting,
    refresh,
    refetch: fetchData,
  };
}

/**
 * Hook for optimistic updates
 */
export function useOptimisticUpdate<T = any>(
  data: T[],
  updateFunction: (id: number, updates: Partial<T>) => Promise<T>
) {
  const [optimisticData, setOptimisticData] = useState<T[]>(data);
  const [pendingUpdates, setPendingUpdates] = useState<Set<number>>(new Set());

  // Update optimistic data when actual data changes
  useEffect(() => {
    setOptimisticData(data);
  }, [data]);

  const updateOptimistically = useCallback(async (
    id: number, 
    updates: Partial<T>,
    optimisticUpdates?: Partial<T>
  ) => {
    // Apply optimistic update immediately
    if (optimisticUpdates) {
      setOptimisticData(prev => 
        prev.map(item => 
          (item as any).id === id 
            ? { ...item, ...optimisticUpdates }
            : item
        )
      );
    }

    setPendingUpdates(prev => new Set(prev).add(id));

    try {
      const result = await updateFunction(id, updates);
      
      // Update with actual result
      setOptimisticData(prev => 
        prev.map(item => 
          (item as any).id === id ? result : item
        )
      );
      
      setPendingUpdates(prev => {
        const newSet = new Set(prev);
        newSet.delete(id);
        return newSet;
      });

      return result;
    } catch (error) {
      // Revert optimistic update on error
      setOptimisticData(data);
      setPendingUpdates(prev => {
        const newSet = new Set(prev);
        newSet.delete(id);
        return newSet;
      });
      
      throw error;
    }
  }, [data, updateFunction]);

  return {
    data: optimisticData,
    pendingUpdates,
    updateOptimistically,
  };
}