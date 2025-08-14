'use client';

import { useState, useEffect, useCallback } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import Layout from '@/components/Layout';
import Button from '@/components/Button';
import Input from '@/components/Input';
import { LazyDataTable, LazyAdvancedFilters, LazyAdvancedSearch, LazySearchPresets, LazyModal } from '@/components/LazyComponents';
import { SmartLoader, ProgressiveLoader } from '@/components/OptimizedLoading';
import { useAdminData } from '@/hooks/useAdminData';
import { useBulkOperations } from '@/hooks/useBulkOperations';
import { useSearchAndFilterState } from '@/hooks/useUrlState';
import { useSearchWithHistory } from '@/hooks/useSearchHistory';
import { SearchPreset } from '@/hooks/useSearchPresets';
import { UserWithSalary } from '@/lib/api/types';
import { userUtils } from '@/lib/api/user';

export default function AdminPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const [selectedRows, setSelectedRows] = useState<number[]>([]);
  const [showBulkModal, setShowBulkModal] = useState(false);
  const [showEditModal, setShowEditModal] = useState(false);
  const [editingUser, setEditingUser] = useState<UserWithSalary | null>(null);
  const [showSuccessMessage, setShowSuccessMessage] = useState(false);

  // URL state management for search and filters
  const { state: urlState, updateState: updateUrlState, reset: resetUrlState } = useSearchAndFilterState();

  // Custom hooks
  const adminData = useAdminData({
    initialPage: urlState.page || 1,
    initialPerPage: urlState.per_page || 20,
    autoLoad: false, // We'll load manually after URL state is ready
  });

  const bulkOps = useBulkOperations();

  // Advanced search with history
  const searchWithHistory = useSearchWithHistory(
    (query) => {
      updateUrlState({ search: query, page: 1 });
    },
    {
      maxHistory: 10,
      storageKey: 'admin_search_history',
      debounceMs: 500,
    }
  );

  // Extract filters from URL state (excluding pagination and search)
  const filters = Object.entries(urlState)
    .filter(([key]) => !['search', 'page', 'per_page', 'sort_by', 'sort_direction'].includes(key))
    .reduce((acc, [key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        acc[key] = value;
      }
      return acc;
    }, {} as Record<string, any>);

  // Check for registration success message
  useEffect(() => {
    if (searchParams.get('registered') === 'true') {
      setShowSuccessMessage(true);
      setTimeout(() => setShowSuccessMessage(false), 5000);
    }
  }, [searchParams]);

  const columns = [
    { 
      key: 'name', 
      label: 'Name', 
      sortable: true, 
      editable: true,
      width: 'w-[200px]',
      type: 'text' as const
    },
    { 
      key: 'email', 
      label: 'Email', 
      sortable: true, 
      editable: true,
      width: 'w-[250px]',
      type: 'email' as const
    },
    { 
      key: 'salary_local_currency', 
      label: 'Local Salary', 
      sortable: true, 
      editable: true,
      width: 'w-[150px]',
      type: 'number' as const,
      formatter: (value: number) => value ? userUtils.formatSalary(value, 'USD') : 'N/A'
    },
    { 
      key: 'salary_euros', 
      label: 'Salary (EUR)', 
      sortable: true, 
      editable: true,
      width: 'w-[150px]',
      type: 'number' as const,
      formatter: (value: number) => value ? userUtils.formatSalary(value, 'EUR') : 'N/A'
    },
    { 
      key: 'commission', 
      label: 'Commission', 
      sortable: true, 
      editable: true,
      width: 'w-[120px]',
      type: 'number' as const,
      formatter: (value: number) => value ? userUtils.formatSalary(value, 'EUR') : 'N/A'
    },
    { 
      key: 'displayed_salary', 
      label: 'Total Salary', 
      sortable: true, 
      width: 'w-[150px]',
      formatter: (value: number) => value ? userUtils.formatSalary(value, 'EUR') : 'N/A'
    },
    { 
      key: 'created_at', 
      label: 'Registered', 
      sortable: true, 
      width: 'w-[120px]',
      formatter: (value: string) => value ? new Date(value).toLocaleDateString() : 'N/A'
    },
    { key: 'actions', label: 'Actions', sortable: false, width: 'w-[150px]' }
  ];

  // Enhanced filter options for advanced filtering
  const filterOptions = [
    {
      key: 'department',
      label: 'Department',
      type: 'multiselect' as const,
      options: [
        { value: 'engineering', label: 'Engineering' },
        { value: 'marketing', label: 'Marketing' },
        { value: 'sales', label: 'Sales' },
        { value: 'finance', label: 'Finance' },
        { value: 'hr', label: 'Human Resources' },
        { value: 'operations', label: 'Operations' },
        { value: 'design', label: 'Design' },
        { value: 'product', label: 'Product Management' },
      ],
    },
    {
      key: 'salary',
      label: 'Salary Range (EUR)',
      type: 'range' as const,
      min: 0,
      max: 200000,
      step: 1000,
      validation: (value: any) => {
        if (value && (isNaN(Number(value)) || Number(value) < 0)) {
          return 'Salary must be a positive number';
        }
        return null;
      },
    },
    {
      key: 'commission',
      label: 'Commission Range (EUR)',
      type: 'range' as const,
      min: 0,
      max: 10000,
      step: 100,
    },
    {
      key: 'created',
      label: 'Registration Date',
      type: 'date' as const,
    },
    {
      key: 'email_domain',
      label: 'Email Domain',
      type: 'text' as const,
      placeholder: 'e.g., company.com, gmail.com',
      validation: (value: string) => {
        if (value && !/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(value)) {
          return 'Please enter a valid domain (e.g., company.com)';
        }
        return null;
      },
    },
    {
      key: 'salary_currency',
      label: 'Local Currency',
      type: 'select' as const,
      options: [
        { value: 'USD', label: 'US Dollar (USD)' },
        { value: 'EUR', label: 'Euro (EUR)' },
        { value: 'GBP', label: 'British Pound (GBP)' },
        { value: 'CAD', label: 'Canadian Dollar (CAD)' },
        { value: 'AUD', label: 'Australian Dollar (AUD)' },
        { value: 'JPY', label: 'Japanese Yen (JPY)' },
        { value: 'CHF', label: 'Swiss Franc (CHF)' },
        { value: 'SEK', label: 'Swedish Krona (SEK)' },
      ],
    },
    {
      key: 'name_pattern',
      label: 'Name Pattern',
      type: 'text' as const,
      placeholder: 'Search by name pattern (supports wildcards)',
    },
    {
      key: 'has_documents',
      label: 'Has Uploaded Documents',
      type: 'select' as const,
      options: [
        { value: 'yes', label: 'Has Documents' },
        { value: 'no', label: 'No Documents' },
      ],
    },
    {
      key: 'salary_verified',
      label: 'Salary Verification Status',
      type: 'select' as const,
      options: [
        { value: 'verified', label: 'Verified' },
        { value: 'pending', label: 'Pending Verification' },
        { value: 'unverified', label: 'Not Verified' },
      ],
      dependsOn: 'has_documents',
      conditional: (filters: Record<string, any>) => filters.has_documents === 'yes',
    },
  ];

  // Load data when URL state changes
  useEffect(() => {
    const searchParams = {
      search: urlState.search,
      page: urlState.page || 1,
      per_page: urlState.per_page || 20,
      sort_by: urlState.sort_by,
      sort_direction: urlState.sort_direction,
      filter_by: Object.keys(filters).length > 0 ? filters : undefined,
    };

    adminData.actions.loadUsers(searchParams);
  }, [urlState, adminData.actions, filters]);

  // Sync search input with URL state
  useEffect(() => {
    if (urlState.search !== searchWithHistory.query) {
      searchWithHistory.updateQuery(urlState.search || '');
    }
  }, [urlState.search, searchWithHistory]);

  const handleSearch = useCallback((query: string) => {
    searchWithHistory.updateQuery(query);
  }, [searchWithHistory]);

  const handleFilter = useCallback((newFilters: Record<string, any>) => {
    updateUrlState({ ...newFilters, page: 1 });
  }, [updateUrlState]);

  const handleSort = useCallback((column: string, direction: 'asc' | 'desc') => {
    updateUrlState({ sort_by: column, sort_direction: direction });
  }, [updateUrlState]);

  const handlePageChange = useCallback((page: number) => {
    updateUrlState({ page });
  }, [updateUrlState]);

  const handlePerPageChange = useCallback((perPage: number) => {
    updateUrlState({ per_page: perPage, page: 1 });
  }, [updateUrlState]);

  const handleRowAction = useCallback((action: string, row: unknown, index: number) => {
    switch (action) {
      case 'edit':
        setEditingUser(row as UserWithSalary);
        setShowEditModal(true);
        break;
      case 'delete':
        if (confirm('Are you sure you want to delete this user?')) {
          adminData.actions.deleteUser((row as UserWithSalary).id, 'Admin deletion');
        }
        break;
      case 'bulk-edit':
        setSelectedRows(row as number[]);
        setShowBulkModal(true);
        break;
      case 'bulk-delete':
        if (confirm(`Are you sure you want to delete ${(row as number[]).length} users?`)) {
          console.log('Bulk delete:', row);
        }
        break;
    }
  }, [adminData.actions]);

  const handleCellEdit = useCallback(async (rowIndex: number, columnKey: string, value: any) => {
    const user = adminData.users[rowIndex];
    if (!user) return;

    const updateData: Partial<UserWithSalary> = {};
    
    if (columnKey === 'salary_local_currency' || columnKey === 'salary_euros' || columnKey === 'commission') {
      updateData.current_salary = {
        ...user.current_salary!,
        [columnKey]: parseFloat(value) || 0,
      };
    } else {
      updateData[columnKey as keyof UserWithSalary] = value;
    }

    await adminData.actions.updateUser(user.id, updateData);
  }, [adminData.users, adminData.actions]);

  const handleExport = useCallback(async (format: 'csv' | 'excel') => {
    try {
      await bulkOps.actions.exportUsers(format, { 
        search: urlState.search,
        filter_by: filters 
      });
    } catch (error) {
      console.error('Export failed:', error);
    }
  }, [bulkOps.actions, filters, urlState.search]);

  const handleResetFilters = useCallback(() => {
    resetUrlState();
    searchWithHistory.clearSearch();
  }, [resetUrlState, searchWithHistory]);

  const handleApplyPreset = useCallback((preset: SearchPreset) => {
    // Apply both search and filters from preset
    searchWithHistory.updateQuery(preset.search);
    updateUrlState({ 
      search: preset.search, 
      page: 1,
      ...preset.filters 
    });
  }, [searchWithHistory, updateUrlState]);

  const handleSavePreset = useCallback((name: string, description?: string) => {
    // Preset is saved by the SearchPresets component
    console.log(`Saved preset: ${name}`, { search: searchWithHistory.query, filters });
  }, [searchWithHistory.query, filters]);

  return (
    <Layout 
      brandName="PayWise Admin"
      navigationItems={[
        { href: '/admin', label: 'Users' },
        { href: '/register', label: 'Add User' },
        { href: '/settings', label: 'Settings' }
      ]}
      showUserProfile={true}
    >
      {/* Success Message */}
      {showSuccessMessage && (
        <div className="mx-4 mt-4 p-4 bg-green-500/10 border border-green-500/20 rounded-lg">
          <div className="flex items-center gap-2">
            <svg className="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
            </svg>
            <p className="text-green-400 text-sm">User registered successfully!</p>
          </div>
        </div>
      )}

      {/* Header */}
      <div className="flex flex-wrap justify-between gap-3 p-4">
        <div className="flex min-w-72 flex-col gap-3">
          <p className="text-white tracking-light text-[32px] font-bold leading-tight">User Management</p>
          <p className="text-[#9cabba] text-sm font-normal leading-normal">
            Manage user accounts and salary information with advanced filtering and bulk operations.
          </p>
        </div>
        <div className="flex gap-2">
          <Button variant="secondary" size="sm" onClick={() => router.push('/register')}>
            Add New User
          </Button>
          <Button variant="outline" size="sm" onClick={() => handleExport('csv')}>
            Export CSV
          </Button>
        </div>
      </div>

      {/* Advanced Filters */}
      <ProgressiveLoader
        fallback={<SmartLoader type="Form" />}
        delay={100}
      >
        <LazyAdvancedFilters
          filters={filters}
          onFiltersChange={handleFilter}
          filterOptions={filterOptions}
          onReset={handleResetFilters}
          loading={adminData.loading || searchWithHistory.isSearching}
          showActiveCount={true}
        />
      </ProgressiveLoader>

      {/* Search Presets */}
      <div className="px-4 py-2 border-b border-[#3b4754]">
        <LazySearchPresets
          currentSearch={searchWithHistory.query}
          currentFilters={filters}
          onApplyPreset={handleApplyPreset}
          onSavePreset={handleSavePreset}
        />
      </div>

      {/* Advanced Search */}
      <div className="px-4 py-3">
        <LazyAdvancedSearch
          value={searchWithHistory.query}
          onChange={handleSearch}
          placeholder="Search users by name, email, salary, or use filters like name:john, email:@company.com..."
          loading={searchWithHistory.isSearching}
          recentSearches={searchWithHistory.searchHistory}
          onClearRecent={searchWithHistory.clearHistory}
          searchFilters={[
            {
              key: 'status',
              label: 'Status',
              prefix: 'status:',
              examples: ['status:active', 'status:inactive'],
            },
            {
              key: 'verified',
              label: 'Verified',
              prefix: 'verified:',
              examples: ['verified:yes', 'verified:no'],
            },
          ]}
        />
      </div>

      {/* Data Table */}
      <ProgressiveLoader
        fallback={<SmartLoader type="Table" />}
        delay={150}
      >
        <LazyDataTable
          columns={columns}
          data={adminData.users}
          loading={adminData.loading || searchWithHistory.isSearching}
          onRowAction={handleRowAction}
          onCellEdit={handleCellEdit}
          onSort={handleSort}
          searchable={false} // We're using the advanced search above
          selectable={true}
          selectedRows={selectedRows}
          onSelectionChange={setSelectedRows}
          pagination={{
            currentPage: adminData.pagination.currentPage,
            totalPages: adminData.pagination.totalPages,
            total: adminData.pagination.total,
            perPage: adminData.pagination.perPage,
            onPageChange: handlePageChange,
            onPerPageChange: handlePerPageChange,
          }}
        />
      </ProgressiveLoader>

      {/* Error Display */}
      {adminData.error && (
        <div className="mx-4 mt-4 p-4 bg-red-500/10 border border-red-500/20 rounded-lg">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
              <svg className="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <p className="text-red-400 text-sm">{adminData.error}</p>
            </div>
            <Button variant="outline" size="sm" onClick={adminData.actions.refresh}>
              Retry
            </Button>
          </div>
        </div>
      )}

      {/* Bulk Edit Modal */}
      <LazyModal
        isOpen={showBulkModal}
        onClose={() => setShowBulkModal(false)}
        title="Bulk Edit Salaries"
        size="lg"
        actions={
          <>
            <Button variant="outline" onClick={() => setShowBulkModal(false)}>
              Cancel
            </Button>
            <Button 
              onClick={() => setShowBulkModal(false)}
              disabled={bulkOps.isProcessing}
            >
              {bulkOps.isProcessing ? 'Updating...' : 'Update All'}
            </Button>
          </>
        }
      >
        <div className="space-y-4">
          <p className="text-[#9cabba] text-sm">
            Update salary information for {selectedRows.length} selected users.
          </p>
          
          {bulkOps.isProcessing && (
            <div className="space-y-2">
              <div className="flex justify-between text-sm">
                <span className="text-white">Processing...</span>
                <span className="text-[#9cabba]">{Math.round(bulkOps.progress)}%</span>
              </div>
              <div className="w-full bg-[#3b4754] rounded-full h-2">
                <div 
                  className="bg-[#0d80f2] h-2 rounded-full transition-all duration-300 ease-out"
                  style={{ width: `${bulkOps.progress}%` }}
                />
              </div>
            </div>
          )}

          {bulkOps.results && (
            <div className="p-3 bg-green-500/10 border border-green-500/20 rounded-lg">
              <p className="text-green-400 text-sm">{bulkOps.getResultsMessage()}</p>
            </div>
          )}

          {bulkOps.error && (
            <div className="p-3 bg-red-500/10 border border-red-500/20 rounded-lg">
              <p className="text-red-400 text-sm">{bulkOps.error}</p>
            </div>
          )}

          <div className="grid grid-cols-2 gap-4">
            <Input
              label="Commission (EUR)"
              type="number"
              placeholder="500"
              min="0"
              step="1"
            />
            <Input
              label="Salary Increase (%)"
              type="number"
              placeholder="5"
              min="0"
              step="0.1"
            />
          </div>
        </div>
      </LazyModal>

      {/* Edit User Modal */}
      <LazyModal
        isOpen={showEditModal}
        onClose={() => setShowEditModal(false)}
        title={`Edit User: ${editingUser?.name}`}
        size="md"
        actions={
          <>
            <Button variant="outline" onClick={() => setShowEditModal(false)}>
              Cancel
            </Button>
            <Button onClick={() => setShowEditModal(false)}>
              Save Changes
            </Button>
          </>
        }
      >
        {editingUser && (
          <div className="space-y-4">
            <Input
              label="Name"
              value={editingUser.name}
              onChange={() => {}}
            />
            <Input
              label="Email"
              type="email"
              value={editingUser.email}
              onChange={() => {}}
            />
            <div className="grid grid-cols-2 gap-4">
              <Input
                label="Local Salary"
                type="number"
                value={editingUser.current_salary?.salary_local_currency || 0}
                onChange={() => {}}
              />
              <Input
                label="Commission (EUR)"
                type="number"
                value={editingUser.current_salary?.commission || 0}
                onChange={() => {}}
              />
            </div>
          </div>
        )}
      </LazyModal>
    </Layout>
  );
}