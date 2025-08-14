'use client';

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { usePerformanceMonitor, PerformanceOptimizer } from '@/lib/performance';
import { CacheManager, MemoryCache, LocalStorageCache } from '@/lib/cache';

// Performance optimization hook
export function usePerformanceOptimization() {
  const { measureCustom } = usePerformanceMonitor();
  const [isOptimizing, setIsOptimizing] = useState(false);

  // Optimized debounce with performance tracking
  const createOptimizedDebounce = useCallback(<T extends (...args: any[]) => any>(
    func: T,
    wait: number,
    trackingName?: string
  ) => {
    return PerformanceOptimizer.debounce((...args: Parameters<T>) => {
      if (trackingName) {
        measureCustom(trackingName, () => func(...args));
      } else {
        func(...args);
      }
    }, wait);
  }, [measureCustom]);

  // Cache management
  const cacheManager = useMemo(() => ({
    invalidateUsers: () => CacheManager.invalidateUsers(),
    invalidateSalaries: () => CacheManager.invalidateSalaries(),
    invalidateSearch: () => CacheManager.invalidateSearch(),
    clearAll: () => CacheManager.clearAll(),
    preload: (key: string) => CacheManager.preload(key),
  }), []);

  return {
    createOptimizedDebounce,
    cacheManager,
    isOptimizing,
    measureCustom,
  };
}