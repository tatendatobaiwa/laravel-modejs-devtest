'use client';

import { useState } from 'react';
import Layout from '@/components/Layout';
import Input from '@/components/Input';
import FileUpload from '@/components/FileUpload';
import Button from '@/components/Button';

export default function RegisterPage() {
  const [formData, setFormData] = useState({
    name: '',
    email: ''
  });
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const handleInputChange = (field: string, value: string) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    if (errors[field]) {
      setErrors(prev => ({ ...prev, [field]: '' }));
    }
  };

  const handleFileSelect = (file: File) => {
    setSelectedFile(file);
  };

  const validateForm = () => {
    const newErrors: Record<string, string> = {};
    
    if (!formData.name.trim()) {
      newErrors.name = 'Name is required';
    }
    
    if (!formData.email.trim()) {
      newErrors.email = 'Email is required';
    } else if (!/\S+@\S+\.\S+/.test(formData.email)) {
      newErrors.email = 'Please enter a valid email address';
    }
    
    if (!selectedFile) {
      newErrors.file = 'Please select a salary document';
    }
    
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!validateForm()) {
      return;
    }
    
    try {
      const formDataToSend = new FormData();
      formDataToSend.append('name', formData.name);
      formDataToSend.append('email', formData.email);
      if (selectedFile) {
        formDataToSend.append('salary_document', selectedFile);
      }
      
      // TODO: Implement API call to Laravel backend
      console.log('Form data:', formDataToSend);
      
      // For now, just show success message
      alert('Registration successful!');
    } catch (error) {
      console.error('Registration error:', error);
      alert('Registration failed. Please try again.');
    }
  };

  return (
    <Layout 
      brandName="PayWise"
      navigationItems={[
        { href: '/dashboard', label: 'Dashboard' },
        { href: '/reports', label: 'Reports' },
        { href: '/settings', label: 'Settings' }
      ]}
      showUserProfile={true}
    >
      <div className="flex flex-wrap justify-between gap-3 p-4">
        <div className="flex min-w-72 flex-col gap-3">
          <p className="text-white tracking-light text-[32px] font-bold leading-tight">User Registration</p>
          <p className="text-[#9cabba] text-sm font-normal leading-normal">
            Please fill in the details below to register and upload your salary information.
          </p>
        </div>
      </div>
      
      <form onSubmit={handleSubmit}>
        <div className="flex max-w-[480px] flex-wrap items-end gap-4 px-4 py-3">
          <Input
            label="Full Name"
            placeholder="Enter your full name"
            value={formData.name}
            onChange={(e) => handleInputChange('name', e.target.value)}
            error={errors.name}
          />
        </div>
        
        <div className="flex max-w-[480px] flex-wrap items-end gap-4 px-4 py-3">
          <Input
            label="Email Address"
            placeholder="Enter your email address"
            type="email"
            value={formData.email}
            onChange={(e) => handleInputChange('email', e.target.value)}
            error={errors.email}
          />
        </div>
        
        <FileUpload
          onFileSelect={handleFileSelect}
          acceptedTypes=".pdf,.doc,.docx,.xls,.xlsx"
          maxSize={5 * 1024 * 1024}
        />
        
        {errors.file && (
          <p className="text-red-500 text-sm px-4">{errors.file}</p>
        )}
        
        <div className="flex px-4 py-3 justify-end">
          <Button type="submit">
            Register
          </Button>
        </div>
      </form>
    </Layout>
  );
}
