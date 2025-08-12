'use client';

import { useState } from 'react';
import Layout from '@/components/Layout';
import Input from '@/components/Input';
import Button from '@/components/Button';

export default function SettingsPage() {
  const [formData, setFormData] = useState({
    name: 'Admin User',
    email: 'admin@salarypro.com'
  });
  const [theme, setTheme] = useState<'light' | 'dark'>('dark');
  const [notifications, setNotifications] = useState({
    salaryUpdates: true,
    announcements: false
  });

  const handleInputChange = (field: string, value: string) => {
    setFormData(prev => ({ ...prev, [field]: value }));
  };

  const handleThemeChange = (selectedTheme: 'light' | 'dark') => {
    setTheme(selectedTheme);
  };

  const handleNotificationChange = (key: string, value: boolean) => {
    setNotifications(prev => ({ ...prev, [key]: value }));
  };

  const handleSave = async () => {
    try {
      // TODO: Implement API call to save settings
      console.log('Saving settings:', { formData, theme, notifications });
      alert('Settings saved successfully!');
    } catch (error) {
      console.error('Save error:', error);
      alert('Failed to save settings. Please try again.');
    }
  };

  return (
    <Layout 
      brandName="SalaryTrack"
      navigationItems={[
        { href: '/dashboard', label: 'Dashboard' },
        { href: '/reports', label: 'Reports' },
        { href: '/settings', label: 'Settings' }
      ]}
      showUserProfile={true}
    >
      <div className="flex flex-wrap justify-between gap-3 p-4">
        <div className="flex min-w-72 flex-col gap-3">
          <p className="text-white tracking-light text-[32px] font-bold leading-tight">Settings</p>
          <p className="text-[#9cabba] text-sm font-normal leading-normal">
            Manage your account details and preferences.
          </p>
        </div>
      </div>
      
      <h3 className="text-white text-lg font-bold leading-tight tracking-[-0.015em] px-4 pb-2 pt-4">
        Account Details
      </h3>
      
      <div className="flex max-w-[480px] flex-wrap items-end gap-4 px-4 py-3">
        <Input
          label="Name"
          value={formData.name}
          onChange={(e) => handleInputChange('name', e.target.value)}
        />
      </div>
      
      <div className="flex max-w-[480px] flex-wrap items-end gap-4 px-4 py-3">
        <Input
          label="Email"
          value={formData.email}
          onChange={(e) => handleInputChange('email', e.target.value)}
        />
      </div>
      
      <h3 className="text-white text-lg font-bold leading-tight tracking-[-0.015em] px-4 pb-2 pt-4">
        Appearance
      </h3>
      
      <div className="flex flex-wrap gap-3 p-4">
        <label
          className={`text-sm font-medium leading-normal flex items-center justify-center rounded-lg border px-4 h-11 text-white relative cursor-pointer transition-all ${
            theme === 'light' 
              ? 'border-[3px] px-3.5 border-[#0d80f2]' 
              : 'border-[#3b4754]'
          }`}
        >
          Light
          <input 
            type="radio" 
            className="invisible absolute" 
            name="theme" 
            checked={theme === 'light'}
            onChange={() => handleThemeChange('light')}
          />
        </label>
        <label
          className={`text-sm font-medium leading-normal flex items-center justify-center rounded-lg border px-4 h-11 text-white relative cursor-pointer transition-all ${
            theme === 'dark' 
              ? 'border-[3px] px-3.5 border-[#0d80f2]' 
              : 'border-[#3b4754]'
          }`}
        >
          Dark
          <input 
            type="radio" 
            className="invisible absolute" 
            name="theme" 
            checked={theme === 'dark'}
            onChange={() => handleThemeChange('dark')}
          />
        </label>
      </div>
      
      <h3 className="text-white text-lg font-bold leading-tight tracking-[-0.015em] px-4 pb-2 pt-4">
        Notification Preferences
      </h3>
      
      <div className="px-4">
        <label className="flex gap-x-3 py-3 flex-row">
          <input
            type="checkbox"
            className="h-5 w-5 rounded border-[#3b4754] border-2 bg-transparent text-[#0d80f2] checked:bg-[#0d80f2] checked:border-[#0d80f2] focus:ring-0 focus:ring-offset-0 focus:border-[#3b4754] focus:outline-none"
            checked={notifications.salaryUpdates}
            onChange={(e) => handleNotificationChange('salaryUpdates', e.target.checked)}
          />
          <p className="text-white text-base font-normal leading-normal">
            Receive email notifications about salary updates
          </p>
        </label>
        <label className="flex gap-x-3 py-3 flex-row">
          <input
            type="checkbox"
            className="h-5 w-5 rounded border-[#3b4754] border-2 bg-transparent text-[#0d80f2] checked:bg-[#0d80f2] checked:border-[#0d80f2] focus:ring-0 focus:ring-offset-0 focus:border-[#3b4754] focus:outline-none"
            checked={notifications.announcements}
            onChange={(e) => handleNotificationChange('announcements', e.target.checked)}
          />
          <p className="text-white text-base font-normal leading-normal">
            Receive in-app notifications about important announcements
          </p>
        </label>
      </div>
      
      <div className="flex px-4 py-3 justify-end">
        <Button onClick={handleSave}>
          Save Changes
        </Button>
      </div>
    </Layout>
  );
}
