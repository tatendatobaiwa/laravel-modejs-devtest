import { useState, useEffect, useCallback } from 'react';
import { useRouter, useSearchParams, usePathname } from 'next/navigation';

interface UseUrlStateOptions {
  defaultValues?: Record<string, any>;
  serialize?: (value: any) => string;
  deserialize?: (value: string) => any;
  debounceMs?: number;
}

export function useUrlState<T extends Record<string, any>>(
  options: UseUrlStateOptions = {}
) {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  
  const {
    defaultValues = {},
    serialize = (value) => String(value),
    deserialize = (value) => value,
    debounceMs = 300,
  } = options;

  // Initialize state from URL params
  const [state, setState] = useState<T>(() => {
    const initialState = { ...defaultValues } as T;
    
    // Parse URL parameters
    searchParams.forEach((value, key) => {
      try {
        initialState[key as keyof T] = deserialize(value);
      } catch (error) {
        console.warn(`Failed to deserialize URL param ${key}:`, error);
      }
    });
    
    return initialState;
  });

  // Debounced URL update
  const updateUrl = useCallback(
    debounce((newState: T) => {
      const params = new URLSearchParams();
      
      Object.entries(newState).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '' && value !== defaultValues[key]) {
          try {
            params.set(key, serialize(value));
          } catch (error) {
            console.warn(`Failed to serialize URL param ${key}:`, error);
          }
        }
      });
      
      const queryString = params.toString();
      const newUrl = queryString ? `${pathname}?${queryString}` : pathname;
      
      // Only update if URL actually changed
      if (newUrl !== `${pathname}${window.location.search}`) {
        router.replace(newUrl, { scroll: false });
      }
    }, debounceMs),
    [pathname, router, serialize, defaultValues, debounceMs]
  );

  // Update state and URL
  const updateState = useCallback((updates: Partial<T> | ((prev: T) => T)) => {
    setState(prevState => {
      const newState = typeof updates === 'function' 
        ? updates(prevState)
        : { ...prevState, ...updates };
      
      updateUrl(newState);
      return newState;
    });
  }, [updateUrl]);

  // Set individual field
  const setField = useCallback((key: keyof T, value: T[keyof T]) => {
    updateState({ [key]: value } as Partial<T>);
  }, [updateState]);

  // Remove field (set to default or undefined)
  const removeField = useCallback((key: keyof T) => {
    updateState(prev => {
      const newState = { ...prev };
      if (key in defaultValues) {
        newState[key] = defaultValues[key as keyof typeof defaultValues];
      } else {
        delete newState[key];
      }
      return newState;
    });
  }, [updateState, defaultValues]);

  // Reset to default values
  const reset = useCallback(() => {
    setState(defaultValues as T);
    updateUrl(defaultValues as T);
  }, [defaultValues, updateUrl]);

  // Sync with URL changes (browser back/forward)
  useEffect(() => {
    const newState = { ...defaultValues } as T;
    
    searchParams.forEach((value, key) => {
      try {
        newState[key as keyof T] = deserialize(value);
      } catch (error) {
        console.warn(`Failed to deserialize URL param ${key}:`, error);
      }
    });
    
    setState(newState);
  }, [searchParams, defaultValues, deserialize]);

  return {
    state,
    updateState,
    setField,
    removeField,
    reset,
  };
}

// Debounce utility function
function debounce<T extends (...args: any[]) => any>(
  func: T,
  wait: number
): (...args: Parameters<T>) => void {
  let timeout: NodeJS.Timeout;
  
  return (...args: Parameters<T>) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => func(...args), wait);
  };
}

// Specialized hook for search and filter state
export function useSearchAndFilterState() {
  return useUrlState<{
    search?: string;
    page?: number;
    per_page?: number;
    sort_by?: string;
    sort_direction?: 'asc' | 'desc';
    salary_range?: string;
    recent?: string;
    department?: string;
    commission_min?: number;
    commission_max?: number;
    salary_min?: number;
    salary_max?: number;
    created_from?: string;
    created_to?: string;
  }>({
    defaultValues: {
      page: 1,
      per_page: 20,
      sort_direction: 'asc',
    },
    serialize: (value) => {
      if (typeof value === 'number') return String(value);
      if (typeof value === 'boolean') return value ? '1' : '0';
      return String(value);
    },
    deserialize: (value) => {
      // Try to parse as number
      const num = Number(value);
      if (!isNaN(num) && isFinite(num)) return num;
      
      // Try to parse as boolean
      if (value === '1' || value === 'true') return true;
      if (value === '0' || value === 'false') return false;
      
      // Return as string
      return value;
    },
    debounceMs: 500,
  });
}

// Hook for managing pagination state
export function usePaginationState(defaultPerPage: number = 20) {
  return useUrlState<{
    page: number;
    per_page: number;
  }>({
    defaultValues: {
      page: 1,
      per_page: defaultPerPage,
    },
    serialize: (value) => String(value),
    deserialize: (value) => {
      const num = Number(value);
      return isNaN(num) ? value : num;
    },
  });
}

// Hook for managing sort state
export function useSortState(defaultSortBy?: string) {
  return useUrlState<{
    sort_by?: string;
    sort_direction: 'asc' | 'desc';
  }>({
    defaultValues: {
      sort_by: defaultSortBy,
      sort_direction: 'asc' as const,
    },
  });
}