import { onCLS, onFID, onFCP, onLCP, onTTFB, type Metric } from 'web-vitals';

// Performance metrics interface
export interface PerformanceMetrics {
  cls: number | null;
  fid: number | null;
  fcp: number | null;
  lcp: number | null;
  ttfb: number | null;
  timestamp: number;
  url: string;
}

// Performance monitoring class
class PerformanceMonitor {
  private metrics: Partial<PerformanceMetrics> = {};
  private callbacks: ((metrics: PerformanceMetrics) => void)[] = [];
  private isClient: boolean = false;
  private initialized: boolean = false;

  constructor() {
    // Only initialize on client side
    if (typeof window !== 'undefined') {
      this.isClient = true;
      // Defer initialization to avoid SSR issues
      setTimeout(() => this.initializeMetrics(), 0);
    }
  }

  private initializeMetrics() {
    // Only run on client side to avoid SSR issues
    if (!this.isClient || typeof window === 'undefined' || this.initialized) {
      return;
    }

    this.initialized = true;

    try {
      // Initialize Web Vitals with error handling
      onCLS(this.handleMetric.bind(this));
      onFID(this.handleMetric.bind(this));
      onFCP(this.handleMetric.bind(this));
      onLCP(this.handleMetric.bind(this));
      onTTFB(this.handleMetric.bind(this));
    } catch (error) {
      console.warn('Failed to initialize performance monitoring:', error);
    }
  }

  private handleMetric(metric: Metric) {
    if (!this.isClient || typeof window === 'undefined') {
      return;
    }

    const metricName = metric.name.toLowerCase() as keyof PerformanceMetrics;
    (this.metrics as any)[metricName] = metric.value;

    // Check if we have all metrics
    if (this.isComplete()) {
      const completeMetrics: PerformanceMetrics = {
        cls: this.metrics.cls || null,
        fid: this.metrics.fid || null,
        fcp: this.metrics.fcp || null,
        lcp: this.metrics.lcp || null,
        ttfb: this.metrics.ttfb || null,
        timestamp: Date.now(),
        url: window.location.href,
      };

      this.callbacks.forEach(callback => callback(completeMetrics));
    }
  }

  private isComplete(): boolean {
    return !!(this.metrics.cls !== undefined &&
      this.metrics.fcp !== undefined &&
      this.metrics.lcp !== undefined &&
      this.metrics.ttfb !== undefined);
  }

  public onMetricsComplete(callback: (metrics: PerformanceMetrics) => void) {
    this.callbacks.push(callback);
  }

  public getMetrics(): Partial<PerformanceMetrics> {
    return { ...this.metrics };
  }

  // Custom performance measurements
  public measureCustom(name: string, fn: () => void | Promise<void>) {
    if (!this.isClient || typeof window === 'undefined' || typeof performance === 'undefined') {
      // Just execute the function without measuring on server side
      return fn();
    }

    const start = performance.now();

    const result = fn();

    if (result instanceof Promise) {
      return result.then(() => {
        const duration = performance.now() - start;
        this.logCustomMetric(name, duration);
      });
    } else {
      const duration = performance.now() - start;
      this.logCustomMetric(name, duration);
    }
  }

  private logCustomMetric(name: string, duration: number) {
    if (process.env.NODE_ENV === 'development') {
      console.log(`Performance: ${name} took ${duration.toFixed(2)}ms`);
    }

    // Send to analytics in production
    if (typeof window !== 'undefined' && window.gtag) {
      window.gtag('event', 'timing_complete', {
        name: name,
        value: Math.round(duration),
      });
    }
  }
}

// Singleton instance - lazy loaded on client side only
let performanceMonitorInstance: PerformanceMonitor | null = null;

export const performanceMonitor = (() => {
  if (typeof window === 'undefined') {
    return null;
  }

  if (!performanceMonitorInstance) {
    performanceMonitorInstance = new PerformanceMonitor();
  }

  return performanceMonitorInstance;
})();

// Hook for React components
export function usePerformanceMonitor() {
  if (!performanceMonitor) {
    // Return no-op functions for server side
    return {
      measureCustom: (name: string, fn: () => void | Promise<void>) => fn(),
      getMetrics: () => ({}),
      onMetricsComplete: () => { },
    };
  }

  return {
    measureCustom: performanceMonitor.measureCustom.bind(performanceMonitor),
    getMetrics: performanceMonitor.getMetrics.bind(performanceMonitor),
    onMetricsComplete: performanceMonitor.onMetricsComplete.bind(performanceMonitor),
  };
}

// Performance optimization utilities
export class PerformanceOptimizer {
  // Debounce function for performance
  static debounce<T extends (...args: any[]) => any>(
    func: T,
    wait: number
  ): (...args: Parameters<T>) => void {
    let timeout: NodeJS.Timeout;
    return (...args: Parameters<T>) => {
      clearTimeout(timeout);
      timeout = setTimeout(() => func.apply(null, args), wait);
    };
  }

  // Throttle function for performance
  static throttle<T extends (...args: any[]) => any>(
    func: T,
    limit: number
  ): (...args: Parameters<T>) => void {
    let inThrottle: boolean;
    return (...args: Parameters<T>) => {
      if (!inThrottle) {
        func.apply(null, args);
        inThrottle = true;
        setTimeout(() => (inThrottle = false), limit);
      }
    };
  }

  // Lazy load images
  static createIntersectionObserver(
    callback: (entries: IntersectionObserverEntry[]) => void,
    options?: IntersectionObserverInit
  ): IntersectionObserver {
    return new IntersectionObserver(callback, {
      rootMargin: '50px',
      threshold: 0.1,
      ...options,
    });
  }

  // Memory usage monitoring
  static getMemoryUsage(): any | null {
    if ('memory' in performance) {
      return (performance as any).memory;
    }
    return null;
  }

  // Bundle size tracking
  static trackBundleSize() {
    if (typeof window !== 'undefined' && 'performance' in window) {
      const navigation = performance.getEntriesByType('navigation')[0] as PerformanceNavigationTiming;
      const transferSize = navigation.transferSize;
      const encodedBodySize = navigation.encodedBodySize;

      if (process.env.NODE_ENV === 'development') {
        console.log('Bundle metrics:', {
          transferSize: `${(transferSize / 1024).toFixed(2)}KB`,
          encodedBodySize: `${(encodedBodySize / 1024).toFixed(2)}KB`,
        });
      }
    }
  }
}

// Global performance tracking
if (typeof window !== 'undefined') {
  // Track bundle size on load
  window.addEventListener('load', () => {
    PerformanceOptimizer.trackBundleSize();
  });

  // Track memory usage periodically in development
  if (process.env.NODE_ENV === 'development') {
    setInterval(() => {
      const memory = PerformanceOptimizer.getMemoryUsage();
      if (memory) {
        console.log('Memory usage:', {
          used: `${(memory.usedJSHeapSize / 1024 / 1024).toFixed(2)}MB`,
          total: `${(memory.totalJSHeapSize / 1024 / 1024).toFixed(2)}MB`,
          limit: `${(memory.jsHeapSizeLimit / 1024 / 1024).toFixed(2)}MB`,
        });
      }
    }, 30000); // Every 30 seconds
  }
}