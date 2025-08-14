// Service Worker registration and management
export class ServiceWorkerManager {
  private static instance: ServiceWorkerManager;
  private registration: ServiceWorkerRegistration | null = null;
  private updateAvailable = false;

  private constructor() {}

  static getInstance(): ServiceWorkerManager {
    if (!ServiceWorkerManager.instance) {
      ServiceWorkerManager.instance = new ServiceWorkerManager();
    }
    return ServiceWorkerManager.instance;
  }

  // Register service worker
  async register(): Promise<void> {
    if (typeof window === 'undefined' || !('serviceWorker' in navigator)) {
      console.log('Service Worker not supported');
      return;
    }

    try {
      this.registration = await navigator.serviceWorker.register('/sw.js', {
        scope: '/',
      });

      console.log('Service Worker registered successfully');

      // Listen for updates
      this.registration.addEventListener('updatefound', () => {
        const newWorker = this.registration!.installing;
        if (newWorker) {
          newWorker.addEventListener('statechange', () => {
            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
              this.updateAvailable = true;
              this.notifyUpdateAvailable();
            }
          });
        }
      });

      // Listen for messages from service worker
      navigator.serviceWorker.addEventListener('message', this.handleMessage.bind(this));

    } catch (error) {
      console.error('Service Worker registration failed:', error);
    }
  }

  // Handle messages from service worker
  private handleMessage(event: MessageEvent) {
    const { type, data } = event.data;

    switch (type) {
      case 'PERFORMANCE_DATA':
        this.handlePerformanceData(data);
        break;
      case 'CACHE_UPDATED':
        console.log('Cache updated:', data);
        break;
      default:
        console.log('Unknown message from service worker:', event.data);
    }
  }

  // Handle performance data from service worker
  private handlePerformanceData(data: any) {
    if (process.env.NODE_ENV === 'development') {
      console.log('SW Performance:', {
        url: data.url,
        duration: `${data.duration.toFixed(2)}ms`,
        cached: data.cached ? 'HIT' : 'MISS',
      });
    }

    // Send to analytics if available
    if (typeof window !== 'undefined' && window.gtag) {
      window.gtag('event', 'sw_performance', {
        event_category: 'performance',
        event_label: data.cached ? 'cache_hit' : 'cache_miss',
        value: Math.round(data.duration),
      });
    }
  }

  // Notify about available updates
  private notifyUpdateAvailable() {
    if (typeof window !== 'undefined') {
      // Dispatch custom event
      window.dispatchEvent(new CustomEvent('sw-update-available'));
      
      // Show notification (you can customize this)
      if (confirm('A new version is available. Would you like to update?')) {
        this.activateUpdate();
      }
    }
  }

  // Activate service worker update
  async activateUpdate(): Promise<void> {
    if (!this.registration || !this.registration.waiting) {
      return;
    }

    // Tell the waiting service worker to skip waiting
    this.registration.waiting.postMessage({ type: 'SKIP_WAITING' });

    // Reload the page to use the new service worker
    window.location.reload();
  }

  // Clear all caches
  async clearCache(): Promise<void> {
    if (this.registration && this.registration.active) {
      this.registration.active.postMessage({ type: 'CLEAR_CACHE' });
    }

    // Also clear browser caches
    if ('caches' in window) {
      const cacheNames = await caches.keys();
      await Promise.all(cacheNames.map(name => caches.delete(name)));
    }
  }

  // Cache API response manually
  async cacheApiResponse(url: string, response: any): Promise<void> {
    if (this.registration && this.registration.active) {
      this.registration.active.postMessage({
        type: 'CACHE_API_RESPONSE',
        url,
        response,
      });
    }
  }

  // Check if update is available
  isUpdateAvailable(): boolean {
    return this.updateAvailable;
  }

  // Get registration status
  getRegistration(): ServiceWorkerRegistration | null {
    return this.registration;
  }

  // Unregister service worker
  async unregister(): Promise<void> {
    if (this.registration) {
      await this.registration.unregister();
      this.registration = null;
      console.log('Service Worker unregistered');
    }
  }
}

// Hook for React components
export function useServiceWorker() {
  const [isRegistered, setIsRegistered] = React.useState(false);
  const [updateAvailable, setUpdateAvailable] = React.useState(false);
  const [isOnline, setIsOnline] = React.useState(true);

  React.useEffect(() => {
    const swManager = ServiceWorkerManager.getInstance();
    
    // Register service worker
    swManager.register().then(() => {
      setIsRegistered(true);
    });

    // Listen for update notifications
    const handleUpdateAvailable = () => {
      setUpdateAvailable(true);
    };

    // Listen for online/offline status
    const handleOnline = () => setIsOnline(true);
    const handleOffline = () => setIsOnline(false);

    window.addEventListener('sw-update-available', handleUpdateAvailable);
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    return () => {
      window.removeEventListener('sw-update-available', handleUpdateAvailable);
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, []);

  const activateUpdate = () => {
    const swManager = ServiceWorkerManager.getInstance();
    swManager.activateUpdate();
  };

  const clearCache = () => {
    const swManager = ServiceWorkerManager.getInstance();
    return swManager.clearCache();
  };

  return {
    isRegistered,
    updateAvailable,
    isOnline,
    activateUpdate,
    clearCache,
  };
}

// Global service worker manager instance
export const swManager = ServiceWorkerManager.getInstance();

// Auto-register in production
if (typeof window !== 'undefined' && process.env.NODE_ENV === 'production') {
  swManager.register();
}

declare global {
  interface Window {
    gtag?: (...args: any[]) => void;
  }
}

// React import for the hook
import React from 'react';