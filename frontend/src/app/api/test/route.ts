import { NextResponse } from 'next/server';

export async function GET() {
  try {
    // Test the backend API health endpoint
    const backendUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';
    const response = await fetch(`${backendUrl}/health`);
    
    if (!response.ok) {
      return NextResponse.json(
        { 
          status: 'error', 
          message: `Backend API returned ${response.status}`,
          backend_url: backendUrl
        }, 
        { status: 500 }
      );
    }
    
    const data = await response.json();
    
    return NextResponse.json({
      status: 'success',
      message: 'API connection test successful',
      backend_url: backendUrl,
      backend_response: data,
      timestamp: new Date().toISOString(),
    });
  } catch (error) {
    return NextResponse.json(
      { 
        status: 'error', 
        message: 'Failed to connect to backend API',
        error: error instanceof Error ? error.message : 'Unknown error',
        backend_url: process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api'
      }, 
      { status: 500 }
    );
  }
}