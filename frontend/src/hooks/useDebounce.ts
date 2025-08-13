import { useState, useEffect, useRef, useCallback } from 'react';

/**
 * Hook that debounces a value
 */
export function useDebounce<T>(value: T, delay: number): T {
  const [debouncedValue, setDebouncedValue] = useState<T>(value);

  useEffect(() => {
    const handler = setTimeout(() => {
      setDebouncedValue(value);
    }, delay);

    return () => {
      clearTimeout(handler);
    };
  }, [value, delay]);

  return debouncedValue;
}

/**
 * Hook that provides a debounced callback function
 */
export function useDebouncedCallback<T extends (...args: any[]) => any>(
  callback: T,
  delay: number,
  deps: React.DependencyList = []
): T {
  const timeoutRef = useRef<NodeJS.Timeout>();
  const callbackRef = useRef(callback);

  // Update callback ref when dependencies change
  useEffect(() => {
    callbackRef.current = callback;
  }, [callback, ...deps]);

  const debouncedCallback = useCallback(
    ((...args: Parameters<T>) => {
      if (timeoutRef.current) {
        clearTimeout(timeoutRef.current);
      }

      timeoutRef.current = setTimeout(() => {
        callbackRef.current(...args);
      }, delay);
    }) as T,
    [delay]
  );

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      if (timeoutRef.current) {
        clearTimeout(timeoutRef.current);
      }
    };
  }, []);

  return debouncedCallback;
}

/**
 * Hook for debounced search functionality
 */
export function useDebouncedSearch(
  onSearch: (query: string) => void,
  delay: number = 500
) {
  const [searchQuery, setSearchQuery] = useState('');
  const [isSearching, setIsSearching] = useState(false);
  const debouncedQuery = useDebounce(searchQuery, delay);

  // Execute search when debounced query changes
  useEffect(() => {
    if (debouncedQuery !== searchQuery) {
      setIsSearching(false);
    }
    
    onSearch(debouncedQuery);
  }, [debouncedQuery, onSearch]);

  // Set searching state when query changes
  useEffect(() => {
    if (searchQuery !== debouncedQuery) {
      setIsSearching(true);
    }
  }, [searchQuery, debouncedQuery]);

  const updateQuery = useCallback((query: string) => {
    setSearchQuery(query);
  }, []);

  const clearSearch = useCallback(() => {
    setSearchQuery('');
  }, []);

  return {
    searchQuery,
    isSearching,
    updateQuery,
    clearSearch,
  };
}

/**
 * Hook for debounced API calls with loading state
 */
export function useDebouncedApi<T, P extends any[]>(
  apiCall: (...args: P) => Promise<T>,
  delay: number = 500
) {
  const [data, setData] = useState<T | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<Error | null>(null);
  
  const debouncedApiCall = useDebouncedCallback(
    async (...args: P) => {
      setLoading(true);
      setError(null);
      
      try {
        const result = await apiCall(...args);
        setData(result);
      } catch (err) {
        setError(err as Error);
      } finally {
        setLoading(false);
      }
    },
    delay
  );

  const reset = useCallback(() => {
    setData(null);
    setError(null);
    setLoading(false);
  }, []);

  return {
    data,
    loading,
    error,
    execute: debouncedApiCall,
    reset,
  };
}

/**
 * Hook for debounced form validation
 */
export function useDebouncedValidation<T>(
  value: T,
  validator: (value: T) => string | null,
  delay: number = 300
) {
  const [error, setError] = useState<string | null>(null);
  const [isValidating, setIsValidating] = useState(false);
  const debouncedValue = useDebounce(value, delay);

  useEffect(() => {
    if (value !== debouncedValue) {
      setIsValidating(true);
    }
  }, [value, debouncedValue]);

  useEffect(() => {
    const validationError = validator(debouncedValue);
    setError(validationError);
    setIsValidating(false);
  }, [debouncedValue, validator]);

  return {
    error,
    isValidating,
  };
}

/**
 * Hook for throttled function calls (different from debounce)
 * Throttle ensures function is called at most once per interval
 */
export function useThrottle<T extends (...args: any[]) => any>(
  callback: T,
  delay: number
): T {
  const lastCallRef = useRef<number>(0);
  const timeoutRef = useRef<NodeJS.Timeout>();

  const throttledCallback = useCallback(
    ((...args: Parameters<T>) => {
      const now = Date.now();
      const timeSinceLastCall = now - lastCallRef.current;

      if (timeSinceLastCall >= delay) {
        lastCallRef.current = now;
        callback(...args);
      } else {
        if (timeoutRef.current) {
          clearTimeout(timeoutRef.current);
        }
        
        timeoutRef.current = setTimeout(() => {
          lastCallRef.current = Date.now();
          callback(...args);
        }, delay - timeSinceLastCall);
      }
    }) as T,
    [callback, delay]
  );

  useEffect(() => {
    return () => {
      if (timeoutRef.current) {
        clearTimeout(timeoutRef.current);
      }
    };
  }, []);

  return throttledCallback;
}