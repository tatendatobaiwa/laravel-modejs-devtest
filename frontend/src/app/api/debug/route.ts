import { NextResponse } from 'next/server';

export async function GET() {
  const backendUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';
  
  const testEndpoints = [
    '/health',
    '/v1/public/info',
    '/v1/admin/dashboard',
    '/v1/admin/users',
  ];

  const results = [];

  for (const endpoint of testEndpoints) {
    try {
      const response = await fetch(`${backendUrl}${endpoint}`, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
        },
      });
      
      results.push({
        endpoint,
        status: response.status,
        statusText: response.statusText,
        available: response.ok,
      });
    } catch (error) {
      results.push({
        endpoint,
        status: 0,
        statusText: 'Network Error',
        available: false,
        error: error instanceof Error ? error.message : 'Unknown error',
      });
    }
  }

  return NextResponse.json({
    backendUrl,
    timestamp: new Date().toISOString(),
    endpoints: results,
  });
}