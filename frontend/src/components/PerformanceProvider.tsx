'use client';

import React, { createContext, useContext, useEffect, useState } from 'react';
import { SWRConfig } from 'swr';
import { performanceMonitor, PerformanceMetrics, usePerformanceMonitor } from '@/lib/performance';
import { cacheConfig, CacheManager } from '@/lib/cache';

// Performance context
interface PerformanceContextType {
  metrics: Partial<PerformanceMetrics>;
  isLoading: boolean;
  clearCache: () => Promise<void>;
  preloadData: (keys: string[]) => Promise<void>;
}

const PerformanceContext = createContext<PerformanceContextType | null>(null);

// Performance provider component
export function PerformanceProvider({ children }: { children: React.ReactNode }) {
  const [metrics, setMetrics] = useState<Partial<PerformanceMetrics>>({});
  const [isLoading, setIsLoading] = useState(true);
  const { getMetrics, onMetricsComplete } = usePerformanceMonitor();

  useEffect(() => {
    // Only run on client side
    if (typeof window === 'undefined') {
      setIsLoading(false);
      return;
    }

    // Initialize performance monitoring
    const updateMetrics = () => {
      setMetrics(getMetrics());
    };

    // Update metrics periodically
    const interval = setInterval(updateMetrics, 1000);

    // Listen for complete metrics
    onMetricsComplete((completeMetrics) => {
      setMetrics(completeMetrics);
      setIsLoading(false);
      
      // Log performance in development
      if (process.env.NODE_ENV === 'development') {
        console.log('Performance metrics:', completeMetrics);
      }
      
      // Send to analytics in production
      if (typeof window !== 'undefined' && window.gtag) {
        window.gtag('event', 'web_vitals', {
          cls: completeMetrics.cls,
          fid: completeMetrics.fid,
          fcp: completeMetrics.fcp,
          lcp: completeMetrics.lcp,
          ttfb: completeMetrics.ttfb,
        });
      }
    });

    return () => {
      clearInterval(interval);
    };
  }, [getMetrics, onMetricsComplete]);

  const clearCache = async () => {
    await CacheManager.clearAll();
  };

  const preloadData = async (keys: string[]) => {
    await Promise.all(keys.map(key => CacheManager.preload(key)));
  };

  const contextValue: PerformanceContextType = {
    metrics,
    isLoading,
    clearCache,
    preloadData,
  };

  return (
    <PerformanceContext.Provider value={contextValue}>
      <SWRConfig value={cacheConfig}>
        {children}
      </SWRConfig>
    </PerformanceContext.Provider>
  );
}

// Hook to use performance context
export function usePerformanceContext() {
  const context = useContext(PerformanceContext);
  if (!context) {
    throw new Error('usePerformanceContext must be used within a PerformanceProvider');
  }
  return context;
}

// Performance monitoring component
export function PerformanceMonitor() {
  const { metrics, isLoading } = usePerformanceContext();
  
  // Only show in development
  if (process.env.NODE_ENV !== 'development') {
    return null;
  }

  return (
    <div className="fixed bottom-4 right-4 bg-black bg-opacity-80 text-white p-3 rounded-lg text-xs font-mono z-50">
      <div className="font-bold mb-2">Performance Metrics</div>
      {isLoading ? (
        <div>Loading metrics...</div>
      ) : (
        <div className="space-y-1">
          {metrics.cls !== null && (
            <div className={`${(metrics.cls || 0) > 0.1 ? 'text-red-400' : 'text-green-400'}`}>
              CLS: {metrics.cls?.toFixed(3)}
            </div>
          )}
          {metrics.fid !== null && (
            <div className={`${(metrics.fid || 0) > 100 ? 'text-red-400' : 'text-green-400'}`}>
              FID: {metrics.fid?.toFixed(0)}ms
            </div>
          )}
          {metrics.fcp !== null && (
            <div className={`${(metrics.fcp || 0) > 1800 ? 'text-red-400' : 'text-green-400'}`}>
              FCP: {metrics.fcp?.toFixed(0)}ms
            </div>
          )}
          {metrics.lcp !== null && (
            <div className={`${(metrics.lcp || 0) > 2500 ? 'text-red-400' : 'text-green-400'}`}>
              LCP: {metrics.lcp?.toFixed(0)}ms
            </div>
          )}
          {metrics.ttfb !== null && (
            <div className={`${(metrics.ttfb || 0) > 800 ? 'text-red-400' : 'text-green-400'}`}>
              TTFB: {metrics.ttfb?.toFixed(0)}ms
            </div>
          )}
        </div>
      )}
    </div>
  );
}

// Performance optimization component for critical resources
export function CriticalResourcePreloader() {
  const { preloadData } = usePerformanceContext();

  useEffect(() => {
    // Preload critical data on app start
    const criticalKeys = [
      '/api/admin/dashboard',
      '/api/users?limit=20',
    ];
    
    preloadData(criticalKeys).catch(console.error);
  }, [preloadData]);

  return null;
}

// Component to track route changes
export function RoutePerformanceTracker() {
  useEffect(() => {
    // Only run on client side
    if (typeof window === 'undefined') return;

    const handleRouteChange = () => {
      // Track route change performance
      if (performanceMonitor) {
        performanceMonitor.measureCustom('route_change', () => {
          // Route change logic would go here
        });
      }
    };

    // Listen for route changes (Next.js specific)
    window.addEventListener('beforeunload', handleRouteChange);
    
    return () => {
      window.removeEventListener('beforeunload', handleRouteChange);
    };
  }, []);

  return null;
}

// Bundle size reporter
export function BundleSizeReporter() {
  useEffect(() => {
    if (typeof window !== 'undefined' && process.env.NODE_ENV === 'development') {
      const reportBundleSize = () => {
        const scripts = Array.from(document.querySelectorAll('script[src]'));
        const styles = Array.from(document.querySelectorAll('link[rel="stylesheet"]'));
        
        let totalSize = 0;
        const resources: { type: string; url: string; size: number }[] = [];
        
        // Get resource sizes from performance API
        const entries = performance.getEntriesByType('resource') as PerformanceResourceTiming[];
        
        entries.forEach(entry => {
          if (entry.name.includes('/_next/') || entry.name.includes('/static/')) {
            const size = entry.transferSize || entry.encodedBodySize || 0;
            totalSize += size;
            resources.push({
              type: entry.name.includes('.js') ? 'JavaScript' : 
                    entry.name.includes('.css') ? 'CSS' : 'Other',
              url: entry.name,
              size,
            });
          }
        });
        
        console.group('Bundle Analysis');
        console.log(`Total bundle size: ${(totalSize / 1024).toFixed(2)}KB`);
        console.table(
          resources
            .sort((a, b) => b.size - a.size)
            .slice(0, 10)
            .map(r => ({
              Type: r.type,
              Size: `${(r.size / 1024).toFixed(2)}KB`,
              URL: r.url.split('/').pop(),
            }))
        );
        console.groupEnd();
      };
      
      // Report after initial load
      setTimeout(reportBundleSize, 2000);
    }
  }, []);

  return null;
}