'use client';

import { ReactNode } from 'react';
import Header from './Header';

interface LayoutProps {
  children: ReactNode;
  brandName?: string;
  navigationItems?: Array<{ href: string; label: string }>;
  showAuthButtons?: boolean;
  showUserProfile?: boolean;
}

export default function Layout({ 
  children, 
  brandName,
  navigationItems,
  showAuthButtons,
  showUserProfile 
}: LayoutProps) {
  return (
    <div className="relative flex size-full min-h-screen flex-col bg-[#111418] dark group/design-root overflow-x-hidden">
      <div className="layout-container flex h-full grow flex-col">
        <Header
          brandName={brandName}
          navigationItems={navigationItems}
          showAuthButtons={showAuthButtons}
          showUserProfile={showUserProfile}
        />
        <div className="px-40 flex flex-1 justify-center py-5">
          <div className="layout-content-container flex flex-col max-w-[960px] flex-1">
            {children}
          </div>
        </div>
      </div>
    </div>
  );
}
