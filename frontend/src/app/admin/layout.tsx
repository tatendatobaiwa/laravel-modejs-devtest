import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Admin Dashboard | PayWise Admin',
  description: 'Administrative dashboard for salary management system',
};

export const viewport = {
  width: 'device-width',
  initialScale: 1,
};

export default function AdminLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return children;
}