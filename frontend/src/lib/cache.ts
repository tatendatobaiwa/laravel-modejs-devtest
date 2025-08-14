import useSWR, { SWRConfiguration, mutate } from 'swr';
import { performanceMonitor } from './performance';

// Cache configuration
export const cacheConfig: SWRConfiguration = {
  // Revalidation settings
  revalidateOnFocus: false,
  revalidateOnReconnect: true,
  revalidateIfStale: true,
  
  // Cache settings
  dedupingInterval: 2000,
  focusThrottleInterval: 5000,
  
  // Error retry
  errorRetryCount: 3,
  errorRetryInterval: 5000,
  
  // Performance optimizations
  shouldRetryOnError: (error) => {
    // Don't retry on 4xx errors
    return error.status >= 500;
  },
  
  // Custom fetcher with performance monitoring
  fetcher: async (url: string) => {
    return performanceMonitor.measureCustom(`API: ${url}`, async () => {
      const response = await fetch(url, {
        headers: {
          'Content-Type': 'application/json',
        },
      });
      
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
      
      return response.json();
    });
  },
};

// Cache keys for consistent invalidation
export const CACHE_KEYS = {
  USERS: '/api/users',
  USER: (id: string) => `/api/users/${id}`,
  SALARIES: '/api/salaries',
  SALARY: (id: string) => `/api/salaries/${id}`,
  ADMIN_DASHBOARD: '/api/admin/dashboard',
  SEARCH_RESULTS: (query: string) => `/api/search?q=${encodeURIComponent(query)}`,
} as const;

// Custom hooks with caching
export function useUsers(options?: SWRConfiguration) {
  return useSWR(CACHE_KEYS.USERS, {
    ...cacheConfig,
    ...options,
  });
}

export function useUser(id: string, options?: SWRConfiguration) {
  return useSWR(id ? CACHE_KEYS.USER(id) : null, {
    ...cacheConfig,
    ...options,
  });
}

export function useSalaries(options?: SWRConfiguration) {
  return useSWR(CACHE_KEYS.SALARIES, {
    ...cacheConfig,
    ...options,
  });
}

export function useAdminDashboard(options?: SWRConfiguration) {
  return useSWR(CACHE_KEYS.ADMIN_DASHBOARD, {
    ...cacheConfig,
    // Admin data changes less frequently
    revalidateIfStale: false,
    dedupingInterval: 10000,
    ...options,
  });
}

export function useSearchResults(query: string, options?: SWRConfiguration) {
  return useSWR(
    query ? CACHE_KEYS.SEARCH_RESULTS(query) : null,
    {
      ...cacheConfig,
      // Search results should be fresh
      revalidateIfStale: true,
      dedupingInterval: 1000,
      ...options,
    }
  );
}

// Cache management utilities
export class CacheManager {
  // Invalidate specific cache keys
  static async invalidate(keys: string | string[]) {
    const keyArray = Array.isArray(keys) ? keys : [keys];
    await Promise.all(keyArray.map(key => mutate(key)));
  }

  // Invalidate all user-related caches
  static async invalidateUsers() {
    await mutate(
      key => typeof key === 'string' && key.startsWith('/api/users'),
      undefined,
      { revalidate: true }
    );
  }

  // Invalidate all salary-related caches
  static async invalidateSalaries() {
    await mutate(
      key => typeof key === 'string' && key.startsWith('/api/salaries'),
      undefined,
      { revalidate: true }
    );
  }

  // Invalidate search caches
  static async invalidateSearch() {
    await mutate(
      key => typeof key === 'string' && key.startsWith('/api/search'),
      undefined,
      { revalidate: true }
    );
  }

  // Clear all caches
  static async clearAll() {
    await mutate(() => true, undefined, { revalidate: false });
  }

  // Preload data for better UX
  static async preload(key: string, fetcher?: (key: string) => Promise<any>) {
    await mutate(key, fetcher ? fetcher(key) : cacheConfig.fetcher!(key));
  }

  // Get cache statistics
  static getCacheStats() {
    // This would require access to SWR's internal cache
    // For now, return basic info
    return {
      timestamp: Date.now(),
      // Add more stats as needed
    };
  }
}

// Local storage cache for persistent data
export class LocalStorageCache {
  private static prefix = 'salary_app_';
  
  static set(key: string, value: any, ttl?: number): void {
    try {
      const item = {
        value,
        timestamp: Date.now(),
        ttl: ttl ? Date.now() + ttl : null,
      };
      localStorage.setItem(this.prefix + key, JSON.stringify(item));
    } catch (error) {
      console.warn('Failed to set localStorage item:', error);
    }
  }

  static get<T>(key: string): T | null {
    try {
      const item = localStorage.getItem(this.prefix + key);
      if (!item) return null;

      const parsed = JSON.parse(item);
      
      // Check TTL
      if (parsed.ttl && Date.now() > parsed.ttl) {
        this.remove(key);
        return null;
      }

      return parsed.value;
    } catch (error) {
      console.warn('Failed to get localStorage item:', error);
      return null;
    }
  }

  static remove(key: string): void {
    try {
      localStorage.removeItem(this.prefix + key);
    } catch (error) {
      console.warn('Failed to remove localStorage item:', error);
    }
  }

  static clear(): void {
    try {
      Object.keys(localStorage)
        .filter(key => key.startsWith(this.prefix))
        .forEach(key => localStorage.removeItem(key));
    } catch (error) {
      console.warn('Failed to clear localStorage:', error);
    }
  }

  // Cache user preferences
  static setUserPreferences(preferences: Record<string, any>): void {
    this.set('user_preferences', preferences, 30 * 24 * 60 * 60 * 1000); // 30 days
  }

  static getUserPreferences(): Record<string, any> | null {
    return this.get('user_preferences');
  }

  // Cache search history
  static addSearchHistory(query: string): void {
    const history = this.get<string[]>('search_history') || [];
    const updatedHistory = [query, ...history.filter(h => h !== query)].slice(0, 10);
    this.set('search_history', updatedHistory, 7 * 24 * 60 * 60 * 1000); // 7 days
  }

  static getSearchHistory(): string[] {
    return this.get('search_history') || [];
  }
}

// Memory cache for temporary data
export class MemoryCache {
  private static cache = new Map<string, { value: any; timestamp: number; ttl?: number }>();

  static set(key: string, value: any, ttl?: number): void {
    this.cache.set(key, {
      value,
      timestamp: Date.now(),
      ttl: ttl ? Date.now() + ttl : undefined,
    });
  }

  static get<T>(key: string): T | null {
    const item = this.cache.get(key);
    if (!item) return null;

    // Check TTL
    if (item.ttl && Date.now() > item.ttl) {
      this.cache.delete(key);
      return null;
    }

    return item.value;
  }

  static has(key: string): boolean {
    return this.cache.has(key);
  }

  static remove(key: string): void {
    this.cache.delete(key);
  }

  static clear(): void {
    this.cache.clear();
  }

  static size(): number {
    return this.cache.size;
  }

  // Cleanup expired items
  static cleanup(): void {
    const now = Date.now();
    for (const [key, item] of this.cache.entries()) {
      if (item.ttl && now > item.ttl) {
        this.cache.delete(key);
      }
    }
  }
}

// Auto cleanup memory cache every 5 minutes
if (typeof window !== 'undefined') {
  setInterval(() => {
    MemoryCache.cleanup();
  }, 5 * 60 * 1000);
}