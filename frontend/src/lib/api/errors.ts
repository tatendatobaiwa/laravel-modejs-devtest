import { ApiError, ValidationError, NetworkError } from './client';

// User-friendly error message mapping
export const ERROR_MESSAGES = {
  NETWORK_ERROR: 'Unable to connect to the server. Please check your internet connection and try again.',
  UNAUTHORIZED: 'Your session has expired. Please log in again.',
  FORBIDDEN: 'You do not have permission to perform this action.',
  NOT_FOUND: 'The requested resource could not be found.',
  VALIDATION_ERROR: 'Please check your input and try again.',
  SERVER_ERROR: 'Something went wrong on our end. Please try again later.',
  RATE_LIMITED: 'Too many requests. Please wait a moment before trying again.',
  SERVICE_UNAVAILABLE: 'The service is temporarily unavailable. Please try again later.',
  UNKNOWN_ERROR: 'An unexpected error occurred. Please try again.',
} as const;

// Error severity levels
export enum ErrorSeverity {
  LOW = 'low',
  MEDIUM = 'medium',
  HIGH = 'high',
  CRITICAL = 'critical',
}

// Enhanced error information
export interface ErrorInfo {
  message: string;
  severity: ErrorSeverity;
  retryable: boolean;
  userAction?: string;
  technicalDetails?: string;
}

/**
 * Converts API errors to user-friendly error information
 */
export function getErrorInfo(error: Error): ErrorInfo {
  if (error instanceof NetworkError) {
    return {
      message: ERROR_MESSAGES.NETWORK_ERROR,
      severity: ErrorSeverity.HIGH,
      retryable: true,
      userAction: 'Check your internet connection and try again.',
      technicalDetails: error.message,
    };
  }

  if (error instanceof ValidationError) {
    const fieldErrors = Object.entries(error.errors)
      .map(([field, messages]) => `${field}: ${messages.join(', ')}`)
      .join('; ');

    return {
      message: ERROR_MESSAGES.VALIDATION_ERROR,
      severity: ErrorSeverity.MEDIUM,
      retryable: true,
      userAction: 'Please correct the highlighted fields and try again.',
      technicalDetails: fieldErrors,
    };
  }

  if (error instanceof ApiError) {
    switch (error.status) {
      case 401:
        return {
          message: ERROR_MESSAGES.UNAUTHORIZED,
          severity: ErrorSeverity.HIGH,
          retryable: false,
          userAction: 'Please log in again.',
          technicalDetails: error.message,
        };

      case 403:
        return {
          message: ERROR_MESSAGES.FORBIDDEN,
          severity: ErrorSeverity.MEDIUM,
          retryable: false,
          userAction: 'Contact your administrator if you believe this is an error.',
          technicalDetails: error.message,
        };

      case 404:
        return {
          message: ERROR_MESSAGES.NOT_FOUND,
          severity: ErrorSeverity.MEDIUM,
          retryable: false,
          userAction: 'Please check the URL or navigate back to the previous page.',
          technicalDetails: error.message,
        };

      case 429:
        return {
          message: ERROR_MESSAGES.RATE_LIMITED,
          severity: ErrorSeverity.MEDIUM,
          retryable: true,
          userAction: 'Please wait a few minutes before trying again.',
          technicalDetails: error.message,
        };

      case 500:
      case 502:
      case 503:
      case 504:
        return {
          message: error.status === 503 ? ERROR_MESSAGES.SERVICE_UNAVAILABLE : ERROR_MESSAGES.SERVER_ERROR,
          severity: ErrorSeverity.HIGH,
          retryable: true,
          userAction: 'Please try again in a few minutes. If the problem persists, contact support.',
          technicalDetails: error.message,
        };

      default:
        return {
          message: error.message || ERROR_MESSAGES.UNKNOWN_ERROR,
          severity: ErrorSeverity.MEDIUM,
          retryable: true,
          userAction: 'Please try again. If the problem persists, contact support.',
          technicalDetails: error.message,
        };
    }
  }

  // Generic error handling
  return {
    message: ERROR_MESSAGES.UNKNOWN_ERROR,
    severity: ErrorSeverity.MEDIUM,
    retryable: true,
    userAction: 'Please try again. If the problem persists, contact support.',
    technicalDetails: error.message,
  };
}

/**
 * Formats validation errors for display
 */
export function formatValidationErrors(errors: Record<string, string[]>): string[] {
  return Object.entries(errors).flatMap(([field, messages]) =>
    messages.map(message => `${field.replace('_', ' ')}: ${message}`)
  );
}

/**
 * Determines if an error should trigger a retry
 */
export function shouldRetry(error: Error, attemptCount: number, maxRetries: number = 3): boolean {
  if (attemptCount >= maxRetries) {
    return false;
  }

  const errorInfo = getErrorInfo(error);
  return errorInfo.retryable;
}

/**
 * Calculates retry delay with exponential backoff
 */
export function getRetryDelay(attemptCount: number, baseDelay: number = 1000): number {
  return Math.min(baseDelay * Math.pow(2, attemptCount), 10000); // Max 10 seconds
}

/**
 * Error boundary helper for React components
 */
export class ApiErrorBoundary {
  static getDerivedStateFromError(error: Error) {
    return {
      hasError: true,
      error: getErrorInfo(error),
    };
  }

  static componentDidCatch(error: Error, errorInfo: any) {
    // Log error to monitoring service
    console.error('API Error caught by boundary:', error, errorInfo);
    
    // In production, you might want to send this to an error tracking service
    if (process.env.NODE_ENV === 'production') {
      // Example: Sentry.captureException(error, { extra: errorInfo });
    }
  }
}

/**
 * Hook for handling API errors in components
 */
export function useErrorHandler() {
  const handleError = (error: Error, context?: string) => {
    const errorInfo = getErrorInfo(error);
    
    // Log error with context
    console.error(`Error in ${context || 'unknown context'}:`, {
      error,
      errorInfo,
      timestamp: new Date().toISOString(),
    });

    // Return formatted error for UI display
    return errorInfo;
  };

  return { handleError };
}