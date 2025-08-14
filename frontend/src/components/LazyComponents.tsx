'use client';

import React, { lazy, Suspense, ComponentType } from 'react';
import { PerformanceOptimizer } from '@/lib/performance';

// Loading component
const LoadingSpinner = () => (
  <div className="flex items-center justify-center p-8">
    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
    <span className="ml-2 text-gray-600">Loading...</span>
  </div>
);

// Error boundary for lazy components
class LazyErrorBoundary extends React.Component<
  { children: React.ReactNode; fallback?: React.ComponentType },
  { hasError: boolean }
> {
  constructor(props: { children: React.ReactNode; fallback?: React.ComponentType }) {
    super(props);
    this.state = { hasError: false };
  }

  static getDerivedStateFromError(): { hasError: boolean } {
    return { hasError: true };
  }

  componentDidCatch(error: Error, errorInfo: React.ErrorInfo) {
    console.error('Lazy component error:', error, errorInfo);
  }

  render() {
    if (this.state.hasError) {
      const Fallback = this.props.fallback || (() => (
        <div className="p-4 text-center text-red-600">
          Failed to load component. Please refresh the page.
        </div>
      ));
      return <Fallback />;
    }

    return this.props.children;
  }
}

// Higher-order component for lazy loading with performance monitoring
export function withLazyLoading<P extends object>(
  importFn: () => Promise<{ default: ComponentType<P> }>,
  displayName: string,
  fallback?: React.ComponentType
) {
  const LazyComponent = lazy(() => {
    const start = performance.now();
    return importFn().then(module => {
      const duration = performance.now() - start;
      if (process.env.NODE_ENV === 'development') {
        console.log(`Lazy loaded ${displayName} in ${duration.toFixed(2)}ms`);
      }
      return module;
    });
  });

  // LazyComponent.displayName = `Lazy(${displayName})`;

  return function LazyWrapper(props: P) {
    return (
      <LazyErrorBoundary fallback={fallback}>
        <Suspense fallback={<LoadingSpinner />}>
          <LazyComponent {...props} />
        </Suspense>
      </LazyErrorBoundary>
    );
  };
}

// Lazy loaded components
export const LazyDataTable = withLazyLoading(
  () => import('@/components/DataTable'),
  'DataTable'
);

export const LazyAdvancedFilters = withLazyLoading(
  () => import('@/components/AdvancedFilters'),
  'AdvancedFilters'
);

export const LazyAdvancedSearch = withLazyLoading(
  () => import('@/components/AdvancedSearch'),
  'AdvancedSearch'
);

export const LazyFileUpload = withLazyLoading(
  () => import('@/components/FileUpload'),
  'FileUpload'
);

export const LazyModal = withLazyLoading(
  () => import('@/components/Modal'),
  'Modal'
);

export const LazySearchPresets = withLazyLoading(
  () => import('@/components/SearchPresets'),
  'SearchPresets'
);

// Intersection Observer hook for lazy loading
export function useIntersectionObserver(
  callback: (entries: IntersectionObserverEntry[]) => void,
  options?: IntersectionObserverInit
) {
  const [observer, setObserver] = React.useState<IntersectionObserver | null>(null);

  React.useEffect(() => {
    const obs = PerformanceOptimizer.createIntersectionObserver(callback, options);
    setObserver(obs);

    return () => {
      obs.disconnect();
    };
  }, [callback, options]);

  return observer;
}

// Lazy image component with intersection observer
export function LazyImage({
  src,
  alt,
  className,
  placeholder = '/placeholder.svg',
  ...props
}: React.ImgHTMLAttributes<HTMLImageElement> & {
  placeholder?: string;
}) {
  const [isLoaded, setIsLoaded] = React.useState(false);
  const [isInView, setIsInView] = React.useState(false);
  const imgRef = React.useRef<HTMLImageElement>(null);

  const observer = useIntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          setIsInView(true);
        }
      });
    },
    { threshold: 0.1 }
  );

  React.useEffect(() => {
    if (observer && imgRef.current) {
      observer.observe(imgRef.current);
    }

    return () => {
      if (observer && imgRef.current) {
        observer.unobserve(imgRef.current);
      }
    };
  }, [observer]);

  return (
    <img
      ref={imgRef}
      src={isInView ? src : placeholder}
      alt={alt}
      className={`transition-opacity duration-300 ${
        isLoaded ? 'opacity-100' : 'opacity-50'
      } ${className}`}
      onLoad={() => setIsLoaded(true)}
      {...props}
    />
  );
}

// Lazy content component for sections
export function LazySection({
  children,
  className,
  threshold = 0.1,
}: {
  children: React.ReactNode;
  className?: string;
  threshold?: number;
}) {
  const [isVisible, setIsVisible] = React.useState(false);
  const sectionRef = React.useRef<HTMLDivElement>(null);

  const observer = useIntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          setIsVisible(true);
        }
      });
    },
    { threshold }
  );

  React.useEffect(() => {
    if (observer && sectionRef.current) {
      observer.observe(sectionRef.current);
    }

    return () => {
      if (observer && sectionRef.current) {
        observer.unobserve(sectionRef.current);
      }
    };
  }, [observer]);

  return (
    <div ref={sectionRef} className={className}>
      {isVisible ? children : <div className="h-32 bg-gray-100 animate-pulse rounded" />}
    </div>
  );
}

// Virtual scrolling component for large lists
export function VirtualList<T>({
  items,
  itemHeight,
  containerHeight,
  renderItem,
  className,
}: {
  items: T[];
  itemHeight: number;
  containerHeight: number;
  renderItem: (item: T, index: number) => React.ReactNode;
  className?: string;
}) {
  const [scrollTop, setScrollTop] = React.useState(0);
  const containerRef = React.useRef<HTMLDivElement>(null);

  const startIndex = Math.floor(scrollTop / itemHeight);
  const endIndex = Math.min(
    startIndex + Math.ceil(containerHeight / itemHeight) + 1,
    items.length
  );

  const visibleItems = items.slice(startIndex, endIndex);
  const totalHeight = items.length * itemHeight;
  const offsetY = startIndex * itemHeight;

  const handleScroll = PerformanceOptimizer.throttle((e: React.UIEvent<HTMLDivElement>) => {
    setScrollTop(e.currentTarget.scrollTop);
  }, 16); // ~60fps

  return (
    <div
      ref={containerRef}
      className={`overflow-auto ${className}`}
      style={{ height: containerHeight }}
      onScroll={handleScroll}
    >
      <div style={{ height: totalHeight, position: 'relative' }}>
        <div style={{ transform: `translateY(${offsetY}px)` }}>
          {visibleItems.map((item, index) => (
            <div key={startIndex + index} style={{ height: itemHeight }}>
              {renderItem(item, startIndex + index)}
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}