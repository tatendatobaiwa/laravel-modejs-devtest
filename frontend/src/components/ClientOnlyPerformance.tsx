'use client';

import { useEffect, useState } from 'react';
import { PerformanceProvider, PerformanceMonitor, CriticalResourcePreloader, RoutePerformanceTracker, BundleSizeReporter } from '@/components/PerformanceProvider';
import { PerformanceDashboard } from '@/components/PerformanceDashboard';

export function ClientOnlyPerformance({ children }: { children: React.ReactNode }) {
  const [isClient, setIsClient] = useState(false);

  useEffect(() => {
    setIsClient(true);
  }, []);

  if (!isClient) {
    return <>{children}</>;
  }

  return (
    <PerformanceProvider>
      <CriticalResourcePreloader />
      <RoutePerformanceTracker />
      <BundleSizeReporter />
      {children}
      <PerformanceMonitor />
      <PerformanceDashboard />
    </PerformanceProvider>
  );
}