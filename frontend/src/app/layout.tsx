import type { Metadata } from "next";
import { Geist, Geist_Mono } from "next/font/google";
import "./globals.css";
import { ServiceWorkerProvider } from "@/components/ServiceWorkerProvider";
import { ClientOnlyPerformance } from "@/components/ClientOnlyPerformance";
import { OptimizedPreloader } from "@/components/ResourcePreloader";

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

export const metadata: Metadata = {
  title: "Salary Management System",
  description: "Custom salary view and admin management application",
  keywords: "salary, admin, management, performance",
  authors: [{ name: "Development Team" }],
  robots: "index, follow",
};

export const viewport = {
  width: "device-width",
  initialScale: 1,
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en">
      <head>
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
        <link rel="dns-prefetch" href="//api.example.com" />
        <link rel="manifest" href="/manifest.json" />
        <meta name="theme-color" content="#0d80f2" />
        <meta name="apple-mobile-web-app-capable" content="yes" />
        <meta name="apple-mobile-web-app-status-bar-style" content="default" />
        <meta name="apple-mobile-web-app-title" content="SalaryApp" />
        <link rel="apple-touch-icon" href="/icon-192.png" />
      </head>
      <body
        className={`${geistSans.variable} ${geistMono.variable} antialiased`}
      >
        <OptimizedPreloader />
        <ServiceWorkerProvider>
          <ClientOnlyPerformance>
            {children}
          </ClientOnlyPerformance>
        </ServiceWorkerProvider>
      </body>
    </html>
  );
}
