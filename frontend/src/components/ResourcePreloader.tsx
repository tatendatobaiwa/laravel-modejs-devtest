'use client';

import { useEffect } from 'react';

interface PreloadResource {
  href: string;
  as: 'script' | 'style' | 'font' | 'image' | 'fetch';
  type?: string;
  crossOrigin?: 'anonymous' | 'use-credentials';
}

const CRITICAL_RESOURCES: PreloadResource[] = [
  // Critical API endpoints
  { href: '/api/health', as: 'fetch' },
  { href: '/api/v1/admin/dashboard', as: 'fetch' },
  
  // Critical fonts (if using custom fonts)
  // { href: '/fonts/inter.woff2', as: 'font', type: 'font/woff2', crossOrigin: 'anonymous' },
  
  // Critical images
  // { href: '/logo.svg', as: 'image' },
];

const ROUTE_PREFETCH_MAP: Record<string, string[]> = {
  '/': ['/admin'],
  '/admin': ['/register', '/settings'],
  '/register': ['/admin'],
};

export function ResourcePreloader() {
  useEffect(() => {
    // Preload critical resources
    CRITICAL_RESOURCES.forEach(resource => {
      const link = document.createElement('link');
      link.rel = 'preload';
      link.href = resource.href;
      link.as = resource.as;
      
      if (resource.type) {
        link.type = resource.type;
      }
      
      if (resource.crossOrigin) {
        link.crossOrigin = resource.crossOrigin;
      }
      
      // Add error handling
      link.onerror = () => {
        console.warn(`Failed to preload resource: ${resource.href}`);
      };
      
      document.head.appendChild(link);
    });

    // Prefetch route-specific resources
    const currentPath = window.location.pathname;
    const routesToPrefetch = ROUTE_PREFETCH_MAP[currentPath] || [];
    
    routesToPrefetch.forEach(route => {
      const link = document.createElement('link');
      link.rel = 'prefetch';
      link.href = route;
      document.head.appendChild(link);
    });

    // Preload critical chunks
    if ('requestIdleCallback' in window) {
      requestIdleCallback(() => {
        // Preload vendor chunk
        const vendorScript = document.createElement('link');
        vendorScript.rel = 'preload';
        vendorScript.href = '/_next/static/chunks/vendor.js';
        vendorScript.as = 'script';
        document.head.appendChild(vendorScript);
      });
    }

    // Cleanup function
    return () => {
      // Remove preload links to prevent memory leaks
      const preloadLinks = document.querySelectorAll('link[rel="preload"], link[rel="prefetch"]');
      preloadLinks.forEach(link => {
        if (CRITICAL_RESOURCES.some(resource => resource.href === link.getAttribute('href'))) {
          link.remove();
        }
      });
    };
  }, []);

  return null;
}

// Service Worker registration for caching
export function ServiceWorkerRegistration() {
  useEffect(() => {
    if ('serviceWorker' in navigator && process.env.NODE_ENV === 'production') {
      navigator.serviceWorker
        .register('/sw.js')
        .then(registration => {
          console.log('SW registered: ', registration);
        })
        .catch(registrationError => {
          console.log('SW registration failed: ', registrationError);
        });
    }
  }, []);

  return null;
}

// DNS prefetch for external domains
export function DNSPrefetch() {
  useEffect(() => {
    const domains = [
      'fonts.googleapis.com',
      'fonts.gstatic.com',
      // Add your API domain if external
      // 'api.yourdomain.com',
    ];

    domains.forEach(domain => {
      const link = document.createElement('link');
      link.rel = 'dns-prefetch';
      link.href = `//${domain}`;
      document.head.appendChild(link);
    });
  }, []);

  return null;
}

// Critical CSS inliner
export function CriticalCSS() {
  useEffect(() => {
    // Inline critical CSS for above-the-fold content
    const criticalCSS = `
      /* Critical styles for initial render */
      .loading-skeleton {
        background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size: 200% 100%;
        animation: loading 1.5s infinite;
      }
      
      @keyframes loading {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
      }
      
      .fade-in {
        animation: fadeIn 0.3s ease-in-out;
      }
      
      @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
      }
    `;

    const style = document.createElement('style');
    style.textContent = criticalCSS;
    document.head.appendChild(style);

    return () => {
      style.remove();
    };
  }, []);

  return null;
}

// Combined preloader component
export function OptimizedPreloader() {
  return (
    <>
      <ResourcePreloader />
      <ServiceWorkerRegistration />
      <DNSPrefetch />
      <CriticalCSS />
    </>
  );
}