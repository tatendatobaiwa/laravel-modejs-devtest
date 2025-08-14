import type { Metadata } from 'next';
import Link from 'next/link';

export const metadata: Metadata = {
  title: '404 - Page Not Found | Salary Management System',
  description: 'The page you are looking for could not be found.',
};

export const viewport = {
  width: 'device-width',
  initialScale: 1,
};

export default function NotFound() {
  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50">
      <div className="max-w-md w-full text-center">
        <div className="mb-8">
          <h1 className="text-9xl font-bold text-gray-200">404</h1>
          <h2 className="text-2xl font-semibold text-gray-800 mb-4">
            Page Not Found
          </h2>
          <p className="text-gray-600 mb-8">
            The page you are looking for might have been removed, had its name changed, 
            or is temporarily unavailable.
          </p>
        </div>
        
        <div className="space-y-4">
          <Link
            href="/"
            className="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors"
          >
            Go to Homepage
          </Link>
          
          <div className="text-sm text-gray-500">
            <Link
              href="/admin"
              className="text-blue-600 hover:text-blue-800 mx-2"
            >
              Admin Dashboard
            </Link>
            |
            <Link
              href="/user"
              className="text-blue-600 hover:text-blue-800 mx-2"
            >
              User Dashboard
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}