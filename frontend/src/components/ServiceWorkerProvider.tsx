'use client';

import React, { useEffect, useState } from 'react';
import { useServiceWorker } from '@/lib/serviceWorker';
import Button from './Button';

export function ServiceWorkerProvider({ children }: { children: React.ReactNode }) {
  const { isRegistered, updateAvailable, isOnline, activateUpdate, clearCache } = useServiceWorker();
  const [showUpdatePrompt, setShowUpdatePrompt] = useState(false);
  const [showOfflineNotice, setShowOfflineNotice] = useState(false);

  useEffect(() => {
    if (updateAvailable) {
      setShowUpdatePrompt(true);
    }
  }, [updateAvailable]);

  useEffect(() => {
    if (!isOnline) {
      setShowOfflineNotice(true);
      const timer = setTimeout(() => setShowOfflineNotice(false), 5000);
      return () => clearTimeout(timer);
    }
  }, [isOnline]);

  const handleUpdate = () => {
    activateUpdate();
    setShowUpdatePrompt(false);
  };

  const handleDismissUpdate = () => {
    setShowUpdatePrompt(false);
  };

  return (
    <>
      {children}
      
      {/* Update Available Notification */}
      {showUpdatePrompt && (
        <div className="fixed top-4 right-4 bg-blue-600 text-white p-4 rounded-lg shadow-lg z-50 max-w-sm">
          <div className="flex items-start gap-3">
            <div className="flex-shrink-0">
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
              </svg>
            </div>
            <div className="flex-1">
              <h4 className="font-semibold mb-1">Update Available</h4>
              <p className="text-sm text-blue-100 mb-3">
                A new version of the app is available. Update now for the latest features and improvements.
              </p>
              <div className="flex gap-2">
                <Button
                  size="sm"
                  onClick={handleUpdate}
                  className="bg-white text-blue-600 hover:bg-blue-50"
                >
                  Update Now
                </Button>
                <Button
                  size="sm"
                  variant="outline"
                  onClick={handleDismissUpdate}
                  className="border-blue-300 text-blue-100 hover:bg-blue-700"
                >
                  Later
                </Button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Offline Notice */}
      {showOfflineNotice && (
        <div className="fixed top-4 left-1/2 transform -translate-x-1/2 bg-yellow-600 text-white p-3 rounded-lg shadow-lg z-50">
          <div className="flex items-center gap-2">
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>
            <span className="text-sm font-medium">
              You're offline. Some features may be limited.
            </span>
          </div>
        </div>
      )}

      {/* Online Status Indicator */}
      <div className={`fixed bottom-4 right-20 w-3 h-3 rounded-full transition-colors duration-300 ${
        isOnline ? 'bg-green-500' : 'bg-red-500'
      }`} title={isOnline ? 'Online' : 'Offline'} />

      {/* Service Worker Status (Development Only) */}
      {process.env.NODE_ENV === 'development' && (
        <div className="fixed bottom-16 right-4 text-xs text-gray-500">
          SW: {isRegistered ? 'Active' : 'Inactive'}
        </div>
      )}
    </>
  );
}

// Hook for components to interact with service worker
export function useAppUpdate() {
  const { updateAvailable, activateUpdate, clearCache } = useServiceWorker();
  
  return {
    updateAvailable,
    activateUpdate,
    clearCache,
  };
}