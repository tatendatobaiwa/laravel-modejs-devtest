'use client';

import { InputHTMLAttributes, forwardRef } from 'react';

interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  label?: string;
  error?: string;
  variant?: 'default' | 'search';
}

const Input = forwardRef<HTMLInputElement, InputProps>(({ 
  label, 
  error, 
  variant = 'default',
  className = '', 
  ...props 
}, ref) => {
  const baseClasses = 'flex w-full min-w-0 flex-1 resize-none overflow-hidden rounded-lg text-white focus:outline-0 focus:ring-0 border-none bg-[#283039] focus:border-none h-14 placeholder:text-[#9cabba] p-4 text-base font-normal leading-normal';
  
  const variants = {
    default: baseClasses,
    search: 'flex w-full min-w-0 flex-1 resize-none overflow-hidden rounded-lg text-white focus:outline-0 focus:ring-0 border-none bg-[#283039] focus:border-none h-10 placeholder:text-[#9cabba] px-4 text-base font-normal leading-normal'
  };
  
  const classes = `${variants[variant]} ${error ? 'border-red-500' : ''} ${className}`;
  
  return (
    <div className="flex flex-col min-w-40 flex-1">
      {label && (
        <p className="text-white text-base font-medium leading-normal pb-2">
          {label}
        </p>
      )}
      <input
        ref={ref}
        className={classes}
        {...props}
      />
      {error && (
        <p className="text-red-500 text-sm mt-1">{error}</p>
      )}
    </div>
  );
});

Input.displayName = 'Input';

export default Input;
