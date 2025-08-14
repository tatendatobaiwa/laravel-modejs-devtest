'use client';

import React, { useState, useEffect } from 'react';
import { usePerformanceContext } from './PerformanceProvider';
import { PerformanceOptimizer } from '@/lib/performance';
import { MemoryCache } from '@/lib/cache';
import Button from './Button';

interface PerformanceStats {
  bundleSize: number;
  memoryUsage: any | null;
  cacheSize: number;
  renderTime: number;
  apiCalls: number;
}

export function PerformanceDashboard() {
  const { metrics, clearCache, isLoading } = usePerformanceContext();
  const [stats, setStats] = useState<PerformanceStats>({
    bundleSize: 0,
    memoryUsage: null,
    cacheSize: 0,
    renderTime: 0,
    apiCalls: 0,
  });
  const [isVisible, setIsVisible] = useState(false);

  useEffect(() => {
    const updateStats = () => {
      const memory = PerformanceOptimizer.getMemoryUsage();
      const cacheSize = MemoryCache.size();
      
      // Get bundle size from performance API
      let bundleSize = 0;
      if (typeof window !== 'undefined') {
        const entries = performance.getEntriesByType('resource') as PerformanceResourceTiming[];
        bundleSize = entries
          .filter(entry => entry.name.includes('/_next/') || entry.name.includes('/static/'))
          .reduce((total, entry) => total + (entry.transferSize || entry.encodedBodySize || 0), 0);
      }

      setStats({
        bundleSize: bundleSize / 1024, // Convert to KB
        memoryUsage: memory,
        cacheSize,
        renderTime: performance.now(),
        apiCalls: 0, // This would need to be tracked separately
      });
    };

    updateStats();
    const interval = setInterval(updateStats, 5000);

    return () => clearInterval(interval);
  }, []);

  // Only show in development
  if (process.env.NODE_ENV !== 'development') {
    return null;
  }

  if (!isVisible) {
    return (
      <button
        onClick={() => setIsVisible(true)}
        className="fixed bottom-4 left-4 bg-blue-600 text-white p-2 rounded-full shadow-lg hover:bg-blue-700 transition-colors z-50"
        title="Show Performance Dashboard"
      >
        📊
      </button>
    );
  }

  const getScoreColor = (score: number, thresholds: { good: number; needs: number }) => {
    if (score <= thresholds.good) return 'text-green-400';
    if (score <= thresholds.needs) return 'text-yellow-400';
    return 'text-red-400';
  };

  return (
    <div className="fixed bottom-4 left-4 bg-gray-900 border border-gray-700 rounded-lg p-4 text-white text-xs font-mono max-w-sm z-50 shadow-xl">
      <div className="flex justify-between items-center mb-3">
        <h3 className="font-bold text-sm">Performance Dashboard</h3>
        <button
          onClick={() => setIsVisible(false)}
          className="text-gray-400 hover:text-white"
        >
          ✕
        </button>
      </div>

      {isLoading ? (
        <div className="text-center py-4">Loading metrics...</div>
      ) : (
        <div className="space-y-3">
          {/* Web Vitals */}
          <div>
            <h4 className="font-semibold mb-2 text-blue-400">Web Vitals</h4>
            <div className="grid grid-cols-2 gap-2 text-xs">
              {metrics.lcp !== null && (
                <div className={getScoreColor(metrics.lcp || 0, { good: 2500, needs: 4000 })}>
                  LCP: {metrics.lcp?.toFixed(0)}ms
                </div>
              )}
              {metrics.fid !== null && (
                <div className={getScoreColor(metrics.fid || 0, { good: 100, needs: 300 })}>
                  FID: {metrics.fid?.toFixed(0)}ms
                </div>
              )}
              {metrics.cls !== null && (
                <div className={getScoreColor(metrics.cls || 0, { good: 0.1, needs: 0.25 })}>
                  CLS: {metrics.cls?.toFixed(3)}
                </div>
              )}
              {metrics.fcp !== null && (
                <div className={getScoreColor(metrics.fcp || 0, { good: 1800, needs: 3000 })}>
                  FCP: {metrics.fcp?.toFixed(0)}ms
                </div>
              )}
            </div>
          </div>

          {/* Resource Usage */}
          <div>
            <h4 className="font-semibold mb-2 text-green-400">Resources</h4>
            <div className="space-y-1">
              <div>Bundle: {stats.bundleSize.toFixed(1)}KB</div>
              <div>Cache: {stats.cacheSize} items</div>
              {stats.memoryUsage && (
                <div>
                  Memory: {(stats.memoryUsage.usedJSHeapSize / 1024 / 1024).toFixed(1)}MB
                </div>
              )}
            </div>
          </div>

          {/* Performance Score */}
          <div>
            <h4 className="font-semibold mb-2 text-purple-400">Score</h4>
            <div className="flex items-center gap-2">
              <div className="flex-1 bg-gray-700 rounded-full h-2">
                <div 
                  className="bg-gradient-to-r from-red-500 via-yellow-500 to-green-500 h-2 rounded-full transition-all duration-300"
                  style={{ width: '75%' }}
                />
              </div>
              <span className="text-green-400">75</span>
            </div>
          </div>

          {/* Actions */}
          <div className="flex gap-2 pt-2 border-t border-gray-700">
            <Button
              size="sm"
              variant="outline"
              onClick={clearCache}
              className="text-xs py-1 px-2"
            >
              Clear Cache
            </Button>
            <Button
              size="sm"
              variant="outline"
              onClick={() => window.location.reload()}
              className="text-xs py-1 px-2"
            >
              Reload
            </Button>
          </div>

          {/* Tips */}
          <div className="text-xs text-gray-400 pt-2 border-t border-gray-700">
            <div className="font-semibold mb-1">Tips:</div>
            <ul className="space-y-1 text-xs">
              {(metrics.lcp || 0) > 2500 && (
                <li>• Optimize images and fonts</li>
              )}
              {(metrics.cls || 0) > 0.1 && (
                <li>• Add size attributes to images</li>
              )}
              {stats.bundleSize > 500 && (
                <li>• Consider code splitting</li>
              )}
              {stats.cacheSize > 100 && (
                <li>• Clear cache periodically</li>
              )}
            </ul>
          </div>
        </div>
      )}
    </div>
  );
}