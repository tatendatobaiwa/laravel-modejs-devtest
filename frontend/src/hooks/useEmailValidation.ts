import { useState, useCallback, useRef } from 'react';
import { userApi } from '@/lib/api/user';
import { ValidationError } from '@/lib/api/client';

interface UseEmailValidationOptions {
  debounceMs?: number;
  excludeUserId?: number;
}

interface EmailValidationState {
  isChecking: boolean;
  isAvailable: boolean | null;
  error: string | null;
}

export function useEmailValidation({ 
  debounceMs = 500, 
  excludeUserId 
}: UseEmailValidationOptions = {}) {
  const [state, setState] = useState<EmailValidationState>({
    isChecking: false,
    isAvailable: null,
    error: null,
  });

  const timeoutRef = useRef<NodeJS.Timeout | null>(null);
  const lastEmailRef = useRef<string>('');

  const validateEmail = useCallback(async (email: string) => {
    // Clear previous timeout
    if (timeoutRef.current) {
      clearTimeout(timeoutRef.current);
    }

    // Reset state if email is empty
    if (!email.trim()) {
      setState({
        isChecking: false,
        isAvailable: null,
        error: null,
      });
      return;
    }

    // Basic email format validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      setState({
        isChecking: false,
        isAvailable: null,
        error: 'Please enter a valid email address',
      });
      return;
    }

    // Skip if same email as last check
    if (email === lastEmailRef.current) {
      return;
    }

    lastEmailRef.current = email;

    // Set checking state
    setState(prev => ({
      ...prev,
      isChecking: true,
      error: null,
    }));

    // Debounce the API call
    timeoutRef.current = setTimeout(async () => {
      try {
        const response = await userApi.checkEmailAvailability(email, excludeUserId);
        
        setState({
          isChecking: false,
          isAvailable: response.data.available,
          error: null,
        });
      } catch (error) {
        let errorMessage = 'Unable to check email availability';
        
        if (error instanceof ValidationError) {
          errorMessage = 'Invalid email format';
        }

        setState({
          isChecking: false,
          isAvailable: null,
          error: errorMessage,
        });
      }
    }, debounceMs);
  }, [debounceMs, excludeUserId]);

  const reset = useCallback(() => {
    if (timeoutRef.current) {
      clearTimeout(timeoutRef.current);
    }
    
    setState({
      isChecking: false,
      isAvailable: null,
      error: null,
    });
    
    lastEmailRef.current = '';
  }, []);

  return {
    ...state,
    validateEmail,
    reset,
  };
}