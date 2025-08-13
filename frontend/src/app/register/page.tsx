'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import Layout from '@/components/Layout';
import Input from '@/components/Input';
import FileUpload from '@/components/FileUpload';
import Button from '@/components/Button';
import { useForm } from '@/hooks/useForm';
import { useEmailValidation } from '@/hooks/useEmailValidation';
import { useFileUpload } from '@/hooks/useFileUpload';
import { userApi } from '@/lib/api/user';
import { CreateUserRequest } from '@/lib/api/types';
import { ValidationError, ApiError } from '@/lib/api/client';
import { getErrorInfo } from '@/lib/api/errors';

interface RegistrationFormData {
  name: string;
  email: string;
  salary_local_currency: string;
  local_currency_code: string;
}

export default function RegisterPage() {
  const router = useRouter();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [submitSuccess, setSubmitSuccess] = useState(false);

  // Email validation hook
  const emailValidation = useEmailValidation({ debounceMs: 500 });

  // File upload hook
  const fileUpload = useFileUpload({
    maxSize: 5 * 1024 * 1024, // 5MB
    acceptedTypes: ['.pdf', '.doc', '.docx', '.xls', '.xlsx'],
    onProgress: (progress) => {
      console.log('Upload progress:', progress);
    },
    onError: (error) => {
      console.error('File upload error:', error);
    },
  });

  // Form hook
  const form = useForm<RegistrationFormData>({
    initialValues: {
      name: '',
      email: '',
      salary_local_currency: '',
      local_currency_code: 'EUR',
    },
    validate: (values) => {
      const errors: Record<string, string> = {};

      if (!values.name.trim()) {
        errors.name = 'Name is required';
      } else if (values.name.trim().length < 2) {
        errors.name = 'Name must be at least 2 characters';
      }

      if (!values.email.trim()) {
        errors.email = 'Email is required';
      } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(values.email)) {
        errors.email = 'Please enter a valid email address';
      } else if (emailValidation.isAvailable === false) {
        errors.email = 'This email is already registered. Please use a different email or try updating your existing record.';
      }

      if (!values.salary_local_currency.trim()) {
        errors.salary_local_currency = 'Salary is required';
      } else {
        const salary = parseFloat(values.salary_local_currency);
        if (isNaN(salary) || salary <= 0) {
          errors.salary_local_currency = 'Please enter a valid salary amount';
        } else if (salary > 10000000) {
          errors.salary_local_currency = 'Salary amount seems too high. Please verify.';
        }
      }

      if (!fileUpload.file) {
        errors.file = 'Please select a salary document';
      }

      return errors;
    },
    onSubmit: async (values) => {
      await handleRegistration(values);
    },
  });

  // Handle email validation
  useEffect(() => {
    if (form.values.email) {
      emailValidation.validateEmail(form.values.email);
    } else {
      emailValidation.reset();
    }
  }, [form.values.email]);

  const handleRegistration = async (values: RegistrationFormData) => {
    if (!fileUpload.file) {
      form.setErrors({ file: 'Please select a salary document' });
      return;
    }

    setIsSubmitting(true);
    setSubmitError(null);
    fileUpload.startUpload();

    try {
      const registrationData: CreateUserRequest = {
        name: values.name.trim(),
        email: values.email.trim().toLowerCase(),
        salary_local_currency: parseFloat(values.salary_local_currency),
        local_currency_code: values.local_currency_code,
        document: fileUpload.file,
      };

      const response = await userApi.register(
        registrationData,
        (progress) => {
          fileUpload.updateProgress(progress);
        }
      );

      if (response.success) {
        setSubmitSuccess(true);
        fileUpload.updateProgress(100);
        
        // Show success message and redirect after delay
        setTimeout(() => {
          router.push('/admin?registered=true');
        }, 2000);
      } else {
        throw new Error(response.message || 'Registration failed');
      }
    } catch (error) {
      console.error('Registration error:', error);
      
      let errorMessage = 'Registration failed. Please try again.';
      
      if (error instanceof ValidationError) {
        // Handle Laravel validation errors
        const validationErrors: Record<string, string> = {};
        Object.entries(error.errors).forEach(([field, messages]) => {
          validationErrors[field] = messages[0]; // Take first error message
        });
        form.setErrors(validationErrors);
        errorMessage = 'Please correct the errors and try again.';
      } else if (error instanceof ApiError) {
        const errorInfo = getErrorInfo(error);
        errorMessage = errorInfo.message;
        
        // Handle specific API errors
        if (error.status === 422 && error.data?.errors?.email) {
          form.setErrors({ email: error.data.errors.email[0] });
        }
      }
      
      setSubmitError(errorMessage);
      fileUpload.setError('Upload failed. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleFileSelect = (file: File) => {
    const success = fileUpload.selectFile(file);
    if (success && form.errors.file) {
      form.setErrors({ ...form.errors, file: '' });
    }
  };

  const handleRetryUpload = () => {
    if (fileUpload.file) {
      form.handleSubmit();
    }
  };

  const getEmailValidationState = () => {
    if (emailValidation.isChecking) {
      return { isLoading: true, success: false, helperText: 'Checking email availability...' };
    }
    
    if (emailValidation.error) {
      return { isLoading: false, success: false, helperText: emailValidation.error };
    }
    
    if (emailValidation.isAvailable === true) {
      return { isLoading: false, success: true, helperText: 'Email is available' };
    }
    
    if (emailValidation.isAvailable === false) {
      return { isLoading: false, success: false, helperText: 'Email is already registered' };
    }
    
    return { isLoading: false, success: false, helperText: undefined };
  };

  const emailState = getEmailValidationState();

  if (submitSuccess) {
    return (
      <Layout 
        brandName="PayWise"
        navigationItems={[
          { href: '/admin', label: 'Admin Panel' },
          { href: '/settings', label: 'Settings' }
        ]}
        showUserProfile={false}
      >
        <div className="flex flex-col items-center justify-center min-h-[400px] p-8">
          <div className="flex flex-col items-center gap-6 max-w-md text-center">
            <div className="w-16 h-16 bg-green-500 rounded-full flex items-center justify-center">
              <svg className="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
              </svg>
            </div>
            <div>
              <h2 className="text-white text-2xl font-bold mb-2">Registration Successful!</h2>
              <p className="text-[#9cabba] text-sm">
                Your information has been submitted successfully. You will be redirected to the admin panel shortly.
              </p>
            </div>
          </div>
        </div>
      </Layout>
    );
  }

  return (
    <Layout 
      brandName="PayWise"
      navigationItems={[
        { href: '/admin', label: 'Admin Panel' },
        { href: '/settings', label: 'Settings' }
      ]}
      showUserProfile={false}
    >
      <div className="flex flex-wrap justify-between gap-3 p-4">
        <div className="flex min-w-72 flex-col gap-3">
          <p className="text-white tracking-light text-[32px] font-bold leading-tight">User Registration</p>
          <p className="text-[#9cabba] text-sm font-normal leading-normal">
            Please fill in the details below to register and upload your salary information.
          </p>
        </div>
      </div>
      
      {submitError && (
        <div className="mx-4 mb-4 p-4 bg-red-500/10 border border-red-500/20 rounded-lg">
          <div className="flex items-center gap-2">
            <svg className="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p className="text-red-400 text-sm">{submitError}</p>
          </div>
        </div>
      )}
      
      <form onSubmit={form.handleSubmit}>
        <div className="flex max-w-[480px] flex-wrap items-end gap-4 px-4 py-3">
          <Input
            label="Full Name"
            placeholder="Enter your full name"
            value={form.values.name}
            onChange={(e) => form.handleChange('name', e.target.value)}
            error={form.errors.name}
            disabled={isSubmitting}
          />
        </div>
        
        <div className="flex max-w-[480px] flex-wrap items-end gap-4 px-4 py-3">
          <Input
            label="Email Address"
            placeholder="Enter your email address"
            type="email"
            value={form.values.email}
            onChange={(e) => form.handleChange('email', e.target.value)}
            error={form.errors.email || emailState.helperText}
            isLoading={emailState.isLoading}
            success={emailState.success}
            disabled={isSubmitting}
          />
        </div>

        <div className="flex max-w-[480px] flex-wrap items-end gap-4 px-4 py-3">
          <div className="flex gap-4 w-full">
            <div className="flex-1">
              <Input
                label="Salary (Local Currency)"
                placeholder="Enter your salary"
                type="number"
                min="0"
                step="0.01"
                value={form.values.salary_local_currency}
                onChange={(e) => form.handleChange('salary_local_currency', e.target.value)}
                error={form.errors.salary_local_currency}
                disabled={isSubmitting}
              />
            </div>
            <div className="w-24">
              <label className="text-white text-base font-medium leading-normal pb-2 block">
                Currency
              </label>
              <select
                value={form.values.local_currency_code}
                onChange={(e) => form.handleChange('local_currency_code', e.target.value)}
                disabled={isSubmitting}
                className="flex w-full min-w-0 flex-1 resize-none overflow-hidden rounded-lg text-white focus:outline-0 focus:ring-0 border-none bg-[#283039] focus:border-none h-14 px-4 text-base font-normal leading-normal"
              >
                <option value="EUR">EUR</option>
                <option value="USD">USD</option>
                <option value="GBP">GBP</option>
                <option value="CAD">CAD</option>
                <option value="AUD">AUD</option>
              </select>
            </div>
          </div>
        </div>
        
        <FileUpload
          onFileSelect={handleFileSelect}
          acceptedTypes=".pdf,.doc,.docx,.xls,.xlsx"
          maxSize={5 * 1024 * 1024}
          progress={fileUpload.progress}
          isUploading={fileUpload.isUploading}
          error={fileUpload.error}
          selectedFile={fileUpload.file}
          onRemoveFile={fileUpload.removeFile}
          onRetry={handleRetryUpload}
        />
        
        {form.errors.file && (
          <p className="text-red-500 text-sm px-4">{form.errors.file}</p>
        )}
        
        <div className="flex px-4 py-3 justify-end">
          <Button 
            type="submit" 
            disabled={isSubmitting || emailValidation.isChecking || emailValidation.isAvailable === false}
          >
            {isSubmitting ? 'Registering...' : 'Register'}
          </Button>
        </div>
      </form>
    </Layout>
  );
}
