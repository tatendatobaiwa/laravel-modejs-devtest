'use client';

import { useState } from 'react';
import Layout from '@/components/Layout';
import Button from '@/components/Button';
import DataTable from '@/components/DataTable';

interface SalaryRecord {
  id: number;
  name: string;
  email: string;
  department: string;
  salary: string;
  salaryLocal: string;
  salaryEuros: number;
  commission: number;
  displayedSalary: number;
}

const mockData: SalaryRecord[] = [
  {
    id: 1,
    name: 'Sophia Carter',
    email: 'sophia.carter@email.com',
    department: 'Marketing',
    salary: '$75,000',
    salaryLocal: '$75,000',
    salaryEuros: 68000,
    commission: 500,
    displayedSalary: 68500
  },
  {
    id: 2,
    name: 'Ethan Mitchell',
    email: 'ethan.mitchell@email.com',
    department: 'Sales',
    salary: '$82,000',
    salaryLocal: '$82,000',
    salaryEuros: 74000,
    commission: 500,
    displayedSalary: 74500
  },
  {
    id: 3,
    name: 'Olivia Bennett',
    email: 'olivia.bennett@email.com',
    department: 'Engineering',
    salary: '$95,000',
    salaryLocal: '$95,000',
    salaryEuros: 86000,
    commission: 500,
    displayedSalary: 86500
  },
  {
    id: 4,
    name: 'Liam Coleman',
    email: 'liam.coleman@email.com',
    department: 'Finance',
    salary: '$88,000',
    salaryLocal: '$88,000',
    salaryEuros: 80000,
    commission: 500,
    displayedSalary: 80500
  },
  {
    id: 5,
    name: 'Ava Hughes',
    email: 'ava.hughes@email.com',
    department: 'HR',
    salary: '$70,000',
    salaryLocal: '$70,000',
    salaryEuros: 63000,
    commission: 500,
    displayedSalary: 63500
  }
];

const columns = [
  { key: 'name', label: 'Name', sortable: true, width: 'w-[400px]' },
  { key: 'email', label: 'Email', sortable: true, width: 'w-[400px]' },
  { key: 'department', label: 'Department', sortable: true, width: 'w-[400px]' },
  { key: 'salary', label: 'Salary', sortable: true, width: 'w-[400px]' },
  { key: 'actions', label: 'Actions', sortable: false, width: 'w-60' }
];

export default function AdminPage() {
  const [searchQuery, setSearchQuery] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [filteredData, setFilteredData] = useState(mockData);

  const handleSearch = (query: string) => {
    setSearchQuery(query);
    if (!query.trim()) {
      setFilteredData(mockData);
    } else {
      const filtered = mockData.filter(record =>
        record.name.toLowerCase().includes(query.toLowerCase()) ||
        record.email.toLowerCase().includes(query.toLowerCase())
      );
      setFilteredData(filtered);
    }
    setCurrentPage(1);
  };

  const handleRowAction = (action: string, row: SalaryRecord) => {
    if (action === 'update') {
      // TODO: Implement update modal/form
      console.log('Update record:', row);
      alert(`Update functionality for ${row.name} will be implemented`);
    }
  };

  const handleBulkUpdate = () => {
    // TODO: Implement bulk update functionality
    alert('Bulk update functionality will be implemented');
  };

  const handleAddNewUser = () => {
    // TODO: Implement add new user functionality
    alert('Add new user functionality will be implemented');
  };

  const totalPages = Math.ceil(filteredData.length / 10);

  return (
    <Layout 
      brandName="Salary Management"
      navigationItems={[
        { href: '/dashboard', label: 'Dashboard' },
        { href: '/users', label: 'Users' },
        { href: '/reports', label: 'Reports' }
      ]}
      showUserProfile={true}
    >
      <div className="flex flex-wrap justify-between gap-3 p-4">
        <div className="flex min-w-72 flex-col gap-3">
          <p className="text-white tracking-light text-[32px] font-bold leading-tight">User Salaries</p>
          <p className="text-[#9cabba] text-sm font-normal leading-normal">
            Manage and update user salary information.
          </p>
        </div>
        <Button variant="secondary" size="sm" onClick={handleAddNewUser}>
          Add New User
        </Button>
      </div>
      
      <div className="px-4 py-3">
        <input
          placeholder="Search by name or email"
          className="w-full px-4 py-2 bg-[#283039] text-white rounded-lg border-none focus:outline-none focus:ring-2 focus:ring-[#0d80f2] h-12"
          value={searchQuery}
          onChange={(e) => handleSearch(e.target.value)}
        />
      </div>
      
      <div className="flex gap-3 p-3 flex-wrap pr-4">
        <Button variant="outline" size="sm">
          Department
        </Button>
        <Button variant="outline" size="sm">
          Salary Range
        </Button>
      </div>
      
      <div className="flex justify-stretch">
        <div className="flex flex-1 gap-3 flex-wrap px-4 py-3 justify-start">
          <Button onClick={handleBulkUpdate}>
            Bulk Update
          </Button>
          <Button variant="secondary" onClick={handleAddNewUser}>
            Add New User
          </Button>
        </div>
      </div>
      
      <p className="text-[#9cabba] text-sm font-normal leading-normal pb-3 pt-1 px-4 underline cursor-pointer">
        Download CSV template for bulk updates
      </p>
      
      <DataTable
        columns={columns}
        data={filteredData}
        onRowAction={handleRowAction}
        searchable={false}
        pagination={{
          currentPage,
          totalPages,
          onPageChange: setCurrentPage
        }}
      />
      
      <h2 className="text-white text-[22px] font-bold leading-tight tracking-[-0.015em] px-4 pb-3 pt-5">
        Activity Log
      </h2>
      
      <div className="flex gap-3 p-3 flex-wrap pr-4">
        <Button variant="outline" size="sm">
          Admin
        </Button>
        <Button variant="outline" size="sm">
          Action Type
        </Button>
      </div>
      
      <div className="px-4 py-3">
        <div className="flex overflow-hidden rounded-lg border border-[#3b4754] bg-[#111418]">
          <table className="flex-1">
            <thead>
              <tr className="bg-[#1b2127]">
                <th className="px-4 py-3 text-left text-white w-[400px] text-sm font-medium leading-normal">
                  Timestamp
                </th>
                <th className="px-4 py-3 text-left text-white w-[400px] text-sm font-medium leading-normal">
                  Admin
                </th>
                <th className="px-4 py-3 text-left text-white w-[400px] text-sm font-medium leading-normal">
                  Action
                </th>
              </tr>
            </thead>
            <tbody>
              <tr className="border-t border-t-[#3b4754]">
                <td className="h-[72px] px-4 py-2 w-[400px] text-[#9cabba] text-sm font-normal leading-normal">
                  2024-07-26 10:30 AM
                </td>
                <td className="h-[72px] px-4 py-2 w-[400px] text-[#9cabba] text-sm font-normal leading-normal">
                  Admin 1
                </td>
                <td className="h-[72px] px-4 py-2 w-[400px] text-[#9cabba] text-sm font-normal leading-normal">
                  Added new user: Sophia Carter
                </td>
              </tr>
              <tr className="border-t border-t-[#3b4754]">
                <td className="h-[72px] px-4 py-2 w-[400px] text-[#9cabba] text-sm font-normal leading-normal">
                  2024-07-25 03:15 PM
                </td>
                <td className="h-[72px] px-4 py-2 w-[400px] text-[#9cabba] text-sm font-normal leading-normal">
                  Admin 2
                </td>
                <td className="h-[72px] px-4 py-2 w-[400px] text-[#9cabba] text-sm font-normal leading-normal">
                  Updated salary for Ethan Mitchell
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      
      <h2 className="text-white text-[22px] font-bold leading-tight tracking-[-0.015em] px-4 pb-3 pt-5">
        Email Notification Settings
      </h2>
      
      <div className="px-4">
        <label className="flex gap-x-3 py-3 flex-row">
          <input
            type="checkbox"
            className="h-5 w-5 rounded border-[#3b4754] border-2 bg-transparent text-[#0d80f2] checked:bg-[#0d80f2] checked:border-[#0d80f2] focus:ring-0 focus:ring-offset-0 focus:border-[#3b4754] focus:outline-none"
          />
          <p className="text-white text-base font-normal leading-normal">
            Notify me when a new user registers
          </p>
        </label>
        <label className="flex gap-x-3 py-3 flex-row">
          <input
            type="checkbox"
            className="h-5 w-5 rounded border-[#3b4754] border-2 bg-transparent text-[#0d80f2] checked:bg-[#0d80f2] checked:border-[#0d80f2] focus:ring-0 focus:ring-offset-0 focus:border-[#3b4754] focus:outline-none"
          />
          <p className="text-white text-base font-normal leading-normal">
            Notify me when bulk salary updates are performed
          </p>
        </label>
      </div>
    </Layout>
  );
}
