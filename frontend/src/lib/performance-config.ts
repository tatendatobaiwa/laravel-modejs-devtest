// Performance configuration and utilities

export const PERFORMANCE_CONFIG = {
  // Lazy loading thresholds
  INTERSECTION_THRESHOLD: 0.1,
  INTERSECTION_ROOT_MARGIN: '50px',
  
  // Debounce/throttle timings
  SEARCH_DEBOUNCE: 300,
  SCROLL_THROTTLE: 16, // ~60fps
  RESIZE_THROTTLE: 100,
  
  // Cache settings
  API_CACHE_TIME: 5 * 60 * 1000, // 5 minutes
  STATIC_CACHE_TIME: 24 * 60 * 60 * 1000, // 24 hours
  
  // Bundle splitting
  CHUNK_SIZE_LIMIT: 244000, // ~244KB
  MIN_CHUNK_SIZE: 20000, // ~20KB
  
  // Virtual scrolling
  VIRTUAL_ITEM_HEIGHT: 50,
  VIRTUAL_OVERSCAN: 5,
  
  // Image optimization
  IMAGE_QUALITY: 85,
  IMAGE_FORMATS: ['webp', 'avif'],
  
  // Preloading
  MAX_PRELOAD_RESOURCES: 10,
  PREFETCH_DELAY: 2000, // 2 seconds
};

// Performance monitoring utilities
export class PerformanceUtils {
  private static metrics = new Map<string, number>();
  
  static startMeasure(name: string) {
    if (typeof performance !== 'undefined') {
      this.metrics.set(name, performance.now());
    }
  }
  
  static endMeasure(name: string): number {
    if (typeof performance !== 'undefined') {
      const start = this.metrics.get(name);
      if (start) {
        const duration = performance.now() - start;
        this.metrics.delete(name);
        
        if (process.env.NODE_ENV === 'development') {
          console.log(`⚡ ${name}: ${duration.toFixed(2)}ms`);
        }
        
        return duration;
      }
    }
    return 0;
  }
  
  static measureAsync<T>(name: string, fn: () => Promise<T>): Promise<T> {
    this.startMeasure(name);
    return fn().finally(() => {
      this.endMeasure(name);
    });
  }
  
  static measureSync<T>(name: string, fn: () => T): T {
    this.startMeasure(name);
    try {
      return fn();
    } finally {
      this.endMeasure(name);
    }
  }
  
  // Memory usage tracking
  static getMemoryUsage() {
    if (typeof performance !== 'undefined' && 'memory' in performance) {
      const memory = (performance as any).memory;
      return {
        used: Math.round(memory.usedJSHeapSize / 1024 / 1024),
        total: Math.round(memory.totalJSHeapSize / 1024 / 1024),
        limit: Math.round(memory.jsHeapSizeLimit / 1024 / 1024),
      };
    }
    return null;
  }
  
  // Network information
  static getNetworkInfo() {
    if ('connection' in navigator) {
      const connection = (navigator as any).connection;
      return {
        effectiveType: connection.effectiveType,
        downlink: connection.downlink,
        rtt: connection.rtt,
        saveData: connection.saveData,
      };
    }
    return null;
  }
  
  // Device capabilities
  static getDeviceCapabilities() {
    return {
      cores: navigator.hardwareConcurrency || 1,
      memory: (navigator as any).deviceMemory || 'unknown',
      platform: navigator.platform,
      userAgent: navigator.userAgent,
    };
  }
  
  // Performance recommendations
  static getPerformanceRecommendations() {
    const network = this.getNetworkInfo();
    const device = this.getDeviceCapabilities();
    const memory = this.getMemoryUsage();
    
    const recommendations = [];
    
    if (network?.effectiveType === '2g' || network?.saveData) {
      recommendations.push('Enable data saver mode');
      recommendations.push('Reduce image quality');
      recommendations.push('Disable animations');
    }
    
    if (device.cores <= 2) {
      recommendations.push('Reduce concurrent operations');
      recommendations.push('Use smaller chunk sizes');
    }
    
    if (memory && memory.used > memory.limit * 0.8) {
      recommendations.push('Clear caches');
      recommendations.push('Reduce memory usage');
    }
    
    return recommendations;
  }
}

// Resource hints utilities
export class ResourceHints {
  private static addedHints = new Set<string>();
  
  static preload(href: string, as: string, type?: string) {
    if (this.addedHints.has(href)) return;
    
    const link = document.createElement('link');
    link.rel = 'preload';
    link.href = href;
    link.as = as;
    if (type) link.type = type;
    
    document.head.appendChild(link);
    this.addedHints.add(href);
  }
  
  static prefetch(href: string) {
    if (this.addedHints.has(href)) return;
    
    const link = document.createElement('link');
    link.rel = 'prefetch';
    link.href = href;
    
    document.head.appendChild(link);
    this.addedHints.add(href);
  }
  
  static dnsPrefetch(hostname: string) {
    if (this.addedHints.has(hostname)) return;
    
    const link = document.createElement('link');
    link.rel = 'dns-prefetch';
    link.href = `//${hostname}`;
    
    document.head.appendChild(link);
    this.addedHints.add(hostname);
  }
  
  static preconnect(href: string, crossOrigin?: boolean) {
    if (this.addedHints.has(href)) return;
    
    const link = document.createElement('link');
    link.rel = 'preconnect';
    link.href = href;
    if (crossOrigin) link.crossOrigin = 'anonymous';
    
    document.head.appendChild(link);
    this.addedHints.add(href);
  }
}

// Critical resource loading
export class CriticalResourceLoader {
  private static loadedResources = new Set<string>();
  
  static async loadCriticalCSS(href: string): Promise<void> {
    if (this.loadedResources.has(href)) return;
    
    return new Promise((resolve, reject) => {
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = href;
      link.onload = () => {
        this.loadedResources.add(href);
        resolve();
      };
      link.onerror = reject;
      
      document.head.appendChild(link);
    });
  }
  
  static async loadCriticalJS(src: string): Promise<void> {
    if (this.loadedResources.has(src)) return;
    
    return new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = src;
      script.async = true;
      script.onload = () => {
        this.loadedResources.add(src);
        resolve();
      };
      script.onerror = reject;
      
      document.head.appendChild(script);
    });
  }
}