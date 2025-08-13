'use client';

import { InputHTMLAttributes, forwardRef } from 'react';

interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  label?: string;
  error?: string | boolean;
  variant?: 'default' | 'search';
  isLoading?: boolean;
  success?: boolean;
  helperText?: string;
}

const Input = forwardRef<HTMLInputElement, InputProps>(({ 
  label, 
  error, 
  variant = 'default',
  isLoading = false,
  success = false,
  helperText,
  className = '', 
  ...props 
}, ref) => {
  const baseClasses = 'flex w-full min-w-0 flex-1 resize-none overflow-hidden rounded-lg text-white focus:outline-0 focus:ring-0 border-none bg-[#283039] focus:border-none h-14 placeholder:text-[#9cabba] p-4 text-base font-normal leading-normal';
  
  const variants = {
    default: baseClasses,
    search: 'flex w-full min-w-0 flex-1 resize-none overflow-hidden rounded-lg text-white focus:outline-0 focus:ring-0 border-none bg-[#283039] focus:border-none h-10 placeholder:text-[#9cabba] px-4 text-base font-normal leading-normal'
  };
  
  let borderColor = '';
  const hasError = error && error !== false;
  if (hasError) borderColor = 'border-red-500';
  else if (success) borderColor = 'border-green-500';
  
  const classes = `${variants[variant]} ${borderColor} ${className}`;
  
  return (
    <div className="flex flex-col min-w-40 flex-1">
      {label && (
        <p className="text-white text-base font-medium leading-normal pb-2">
          {label}
        </p>
      )}
      <div className="relative">
        <input
          ref={ref}
          className={classes}
          {...props}
        />
        
        {/* Loading spinner */}
        {isLoading && (
          <div className="absolute right-3 top-1/2 transform -translate-y-1/2">
            <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-[#0d80f2]"></div>
          </div>
        )}
        
        {/* Success checkmark */}
        {success && !isLoading && (
          <div className="absolute right-3 top-1/2 transform -translate-y-1/2">
            <svg className="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
            </svg>
          </div>
        )}
        
        {/* Error icon */}
        {hasError && !isLoading && (
          <div className="absolute right-3 top-1/2 transform -translate-y-1/2">
            <svg className="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
        )}
      </div>
      
      {/* Error message */}
      {typeof error === 'string' && error && (
        <p className="text-red-500 text-sm mt-1">{error}</p>
      )}
      
      {/* Success message */}
      {success && !hasError && helperText && (
        <p className="text-green-500 text-sm mt-1">{helperText}</p>
      )}
      
      {/* Helper text */}
      {!hasError && !success && helperText && (
        <p className="text-[#9cabba] text-sm mt-1">{helperText}</p>
      )}
    </div>
  );
});

Input.displayName = 'Input';

export default Input;
