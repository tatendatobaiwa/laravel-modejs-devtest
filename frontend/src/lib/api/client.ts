const API_BASE_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';

// Error types for better error handling
export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
    public data?: any
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

export class NetworkError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'NetworkError';
  }
}

export class ValidationError extends Error {
  constructor(
    message: string,
    public errors: Record<string, string[]>
  ) {
    super(message);
    this.name = 'ValidationError';
  }
}

// Token management interface
interface TokenManager {
  getToken(): string | null;
  setToken(token: string): void;
  removeToken(): void;
}

class LocalStorageTokenManager implements TokenManager {
  private readonly TOKEN_KEY = 'api_token';

  getToken(): string | null {
    if (typeof window === 'undefined') return null;
    return localStorage.getItem(this.TOKEN_KEY);
  }

  setToken(token: string): void {
    if (typeof window === 'undefined') return;
    localStorage.setItem(this.TOKEN_KEY, token);
  }

  removeToken(): void {
    if (typeof window === 'undefined') return;
    localStorage.removeItem(this.TOKEN_KEY);
  }
}

// Request/Response interceptor types
type RequestInterceptor = (config: RequestInit, url: string) => RequestInit | Promise<RequestInit>;
type ResponseInterceptor = (response: Response, url: string) => Response | Promise<Response>;
type ErrorInterceptor = (error: Error, url: string) => Error | Promise<Error>;

class ApiClient {
  private baseUrl: string;
  private tokenManager: TokenManager;
  private requestInterceptors: RequestInterceptor[] = [];
  private responseInterceptors: ResponseInterceptor[] = [];
  private errorInterceptors: ErrorInterceptor[] = [];

  constructor(baseUrl: string, tokenManager?: TokenManager) {
    this.baseUrl = baseUrl;
    this.tokenManager = tokenManager || new LocalStorageTokenManager();
    
    // Add default logging interceptors
    this.addRequestInterceptor(this.logRequest.bind(this));
    this.addResponseInterceptor(this.logResponse.bind(this));
    this.addErrorInterceptor(this.logError.bind(this));
  }

  // Interceptor management
  addRequestInterceptor(interceptor: RequestInterceptor): void {
    this.requestInterceptors.push(interceptor);
  }

  addResponseInterceptor(interceptor: ResponseInterceptor): void {
    this.responseInterceptors.push(interceptor);
  }

  addErrorInterceptor(interceptor: ErrorInterceptor): void {
    this.errorInterceptors.push(interceptor);
  }

  // Default logging interceptors
  private logRequest(config: RequestInit, url: string): RequestInit {
    if (process.env.NODE_ENV === 'development') {
      console.log(`🚀 API Request: ${config.method || 'GET'} ${url}`, {
        headers: config.headers,
        body: config.body instanceof FormData ? '[FormData]' : config.body
      });
    }
    return config;
  }

  private logResponse(response: Response, url: string): Response {
    if (process.env.NODE_ENV === 'development') {
      console.log(`✅ API Response: ${response.status} ${url}`, {
        status: response.status,
        statusText: response.statusText,
        headers: Object.fromEntries(response.headers.entries())
      });
    }
    return response;
  }

  private logError(error: Error, url: string): Error {
    if (process.env.NODE_ENV === 'development') {
      console.error(`❌ API Error: ${url}`, error);
    }
    return error;
  }

  // Token management methods
  setAuthToken(token: string): void {
    this.tokenManager.setToken(token);
  }

  getAuthToken(): string | null {
    return this.tokenManager.getToken();
  }

  clearAuthToken(): void {
    this.tokenManager.removeToken();
  }

  private async request<T>(
    endpoint: string,
    options: RequestInit = {}
  ): Promise<T> {
    const url = `${this.baseUrl}${endpoint}`;
    
    // Prepare initial config
    let config: RequestInit = {
      headers: {
        'Accept': 'application/json',
        ...options.headers,
      },
      ...options,
    };

    // Add Content-Type for non-FormData requests
    if (!(options.body instanceof FormData)) {
      config.headers = {
        'Content-Type': 'application/json',
        ...config.headers,
      };
    }

    // Add authentication token if available
    const token = this.getAuthToken();
    if (token) {
      config.headers = {
        'Authorization': `Bearer ${token}`,
        ...config.headers,
      };
    }

    // Apply request interceptors
    for (const interceptor of this.requestInterceptors) {
      config = await interceptor(config, url);
    }

    try {
      const response = await fetch(url, config);
      
      // Apply response interceptors
      let processedResponse = response;
      for (const interceptor of this.responseInterceptors) {
        processedResponse = await interceptor(processedResponse, url);
      }

      // Handle different response statuses
      if (!processedResponse.ok) {
        await this.handleErrorResponse(processedResponse, url);
      }
      
      // Handle empty responses
      const contentType = processedResponse.headers.get('content-type');
      if (!contentType || !contentType.includes('application/json')) {
        return {} as T;
      }

      const data = await processedResponse.json();
      return data;
    } catch (error) {
      // Apply error interceptors
      let processedError = error as Error;
      for (const interceptor of this.errorInterceptors) {
        processedError = await interceptor(processedError, url);
      }

      // Convert network errors to our custom error types
      if (processedError.name === 'TypeError' && processedError.message.includes('fetch')) {
        throw new NetworkError('Network connection failed. Please check your internet connection.');
      }

      throw processedError;
    }
  }

  private async handleErrorResponse(response: Response, url: string): Promise<never> {
    let errorData: any = {};
    
    try {
      const contentType = response.headers.get('content-type');
      if (contentType && contentType.includes('application/json')) {
        errorData = await response.json();
      }
    } catch {
      // If we can't parse the error response, use default message
    }

    const message = errorData.message || this.getDefaultErrorMessage(response.status);

    switch (response.status) {
      case 400:
        throw new ApiError(message, response.status, errorData);
      case 401:
        // Clear token on unauthorized
        this.clearAuthToken();
        throw new ApiError('Authentication required. Please log in again.', response.status, errorData);
      case 403:
        throw new ApiError('Access denied. You do not have permission to perform this action.', response.status, errorData);
      case 404:
        throw new ApiError('The requested resource was not found.', response.status, errorData);
      case 422:
        // Laravel validation errors
        const errors = errorData.errors || {};
        throw new ValidationError(message, errors);
      case 429:
        throw new ApiError('Too many requests. Please try again later.', response.status, errorData);
      case 500:
        throw new ApiError('Internal server error. Please try again later.', response.status, errorData);
      case 503:
        throw new ApiError('Service temporarily unavailable. Please try again later.', response.status, errorData);
      default:
        throw new ApiError(message, response.status, errorData);
    }
  }

  private getDefaultErrorMessage(status: number): string {
    switch (status) {
      case 400: return 'Bad request. Please check your input.';
      case 401: return 'Authentication required.';
      case 403: return 'Access denied.';
      case 404: return 'Resource not found.';
      case 422: return 'Validation failed.';
      case 429: return 'Too many requests.';
      case 500: return 'Internal server error.';
      case 503: return 'Service unavailable.';
      default: return 'An unexpected error occurred.';
    }
  }

  async get<T>(endpoint: string, params?: Record<string, string>): Promise<T> {
    const url = params ? `${endpoint}?${new URLSearchParams(params).toString()}` : endpoint;
    return this.request<T>(url, { method: 'GET' });
  }

  async post<T>(endpoint: string, data?: unknown): Promise<T> {
    const config: RequestInit = {
      method: 'POST',
    };

    if (data instanceof FormData) {
      config.body = data;
    } else if (data) {
      config.body = JSON.stringify(data);
    }

    return this.request<T>(endpoint, config);
  }

  async put<T>(endpoint: string, data: unknown): Promise<T> {
    return this.request<T>(endpoint, {
      method: 'PUT',
      body: JSON.stringify(data),
    });
  }

  async patch<T>(endpoint: string, data: unknown): Promise<T> {
    return this.request<T>(endpoint, {
      method: 'PATCH',
      body: JSON.stringify(data),
    });
  }

  async delete<T>(endpoint: string): Promise<T> {
    return this.request<T>(endpoint, { method: 'DELETE' });
  }

  // Utility method for file uploads with progress tracking
  async uploadFile<T>(
    endpoint: string,
    file: File,
    additionalData?: Record<string, string>,
    onProgress?: (progress: number) => void
  ): Promise<T> {
    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      const formData = new FormData();
      
      formData.append('file', file);
      if (additionalData) {
        Object.entries(additionalData).forEach(([key, value]) => {
          formData.append(key, value);
        });
      }

      // Add auth token if available
      const token = this.getAuthToken();
      if (token) {
        xhr.setRequestHeader('Authorization', `Bearer ${token}`);
      }

      xhr.upload.addEventListener('progress', (event) => {
        if (event.lengthComputable && onProgress) {
          const progress = (event.loaded / event.total) * 100;
          onProgress(progress);
        }
      });

      xhr.addEventListener('load', async () => {
        if (xhr.status >= 200 && xhr.status < 300) {
          try {
            const response = JSON.parse(xhr.responseText);
            resolve(response);
          } catch {
            resolve({} as T);
          }
        } else {
          try {
            const errorData = JSON.parse(xhr.responseText);
            reject(new ApiError(errorData.message || 'Upload failed', xhr.status, errorData));
          } catch {
            reject(new ApiError('Upload failed', xhr.status));
          }
        }
      });

      xhr.addEventListener('error', () => {
        reject(new NetworkError('Upload failed due to network error'));
      });

      xhr.open('POST', `${this.baseUrl}${endpoint}`);
      xhr.send(formData);
    });
  }
}

export const apiClient = new ApiClient(API_BASE_URL);
