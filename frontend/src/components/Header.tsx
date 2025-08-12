'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';

interface HeaderProps {
  brandName?: string;
  navigationItems?: Array<{ href: string; label: string }>;
  showAuthButtons?: boolean;
  showUserProfile?: boolean;
}

export default function Header({ 
  brandName = 'SalaryPro', 
  navigationItems = [],
  showAuthButtons = false,
  showUserProfile = false 
}: HeaderProps) {
  const pathname = usePathname();

  return (
    <header className="flex items-center justify-between whitespace-nowrap border-b border-solid border-b-[#283039] px-10 py-3">
      <div className="flex items-center gap-4 text-white">
        <div className="size-4">
          <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M4 4H17.3334V17.3334H30.6666V30.6666H44V44H4V4Z" fill="currentColor" />
          </svg>
        </div>
        <h2 className="text-white text-lg font-bold leading-tight tracking-[-0.015em]">
          {brandName}
        </h2>
      </div>
      
      <div className="flex flex-1 justify-end gap-8">
        {navigationItems.length > 0 && (
          <div className="flex items-center gap-9">
            {navigationItems.map((item) => (
              <Link
                key={item.href}
                href={item.href}
                className={`text-sm font-medium leading-normal ${
                  pathname === item.href ? 'text-[#0d80f2]' : 'text-white'
                }`}
              >
                {item.label}
              </Link>
            ))}
          </div>
        )}
        
        {showAuthButtons && (
          <div className="flex gap-2">
            <Link
              href="/login"
              className="flex min-w-[84px] max-w-[480px] cursor-pointer items-center justify-center overflow-hidden rounded-lg h-10 px-4 bg-[#0d80f2] text-white text-sm font-bold leading-normal tracking-[0.015em]"
            >
              <span className="truncate">Login</span>
            </Link>
            <Link
              href="/register"
              className="flex min-w-[84px] max-w-[480px] cursor-pointer items-center justify-center overflow-hidden rounded-lg h-10 px-4 bg-[#283039] text-white text-sm font-bold leading-normal tracking-[0.015em]"
            >
              <span className="truncate">Sign Up</span>
            </Link>
          </div>
        )}
        
        {showUserProfile && (
          <div
            className="bg-center bg-no-repeat aspect-square bg-cover rounded-full size-10"
            style={{
              backgroundImage: 'url("https://lh3.googleusercontent.com/aida-public/AB6AXuCJl5kp-izOKYYK_j6tMnD0Evb8wECQumBYKmY6WFLj0V1pPWOkI3jYJ6qGjadAcjdlv1lD3VRQI97fjGFUV-Aes2FiAw3GHdAHCS1HIRA6a3ginOPWAqhmTBXznWKnrZNrUkzdqIthj4zFUCdTL6PQPa20RO2VYoKEv7_tB7ufAyESVWAOuZT7bDhBl0NJ_tyrFdKsqQwTVBDBL_VmKvptWZ2vvOs8IZlt8K4b-jOmDPDkuuV3A8R05QAolGR-8gf7PSGmDfAm_Hu")'
            }}
          />
        )}
      </div>
    </header>
  );
}
